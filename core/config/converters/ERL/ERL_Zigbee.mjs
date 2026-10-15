import * as m from "zigbee-herdsman-converters/lib/modernExtend";
import * as tz from "zigbee-herdsman-converters/converters/toZigbee";
import * as constants from "zigbee-herdsman-converters/lib/constants";
import * as exposes from "zigbee-herdsman-converters/lib/exposes";
import {logger} from "zigbee-herdsman-converters/lib/logger";
import {Zcl} from "zigbee-herdsman";

const e = exposes.presets;
const ea = exposes.access;
const NS = "zhc:fz";

/* Les bitmaps 64 bits sont décodés en BigInt par zigbee-herdsman ; on les publie
   en chaîne hexadécimale pour rester sérialisables en JSON. */
const toHex64 = (value) => {
    if (typeof value === "bigint" || typeof value === "number") return `0x${value.toString(16).padStart(16, "0")}`;
    if (Array.isArray(value)) return `0x${value.map((part) => Number(part).toString(16).padStart(8, "0")).join("")}`;
    return String(value);
};

/* Sérialisation tolérante aux BigInt, que l'on rencontre dans les charges utiles
   du cluster Metering (index 48 bits, bitmaps 64 bits). */
const toJson = (value) => JSON.stringify(value, (key, val) => (typeof val === "bigint" ? val.toString() : val));

/* Les libellés et identifiants du compteur sont des chaînes d'octets de longueur fixe,
   complétées par des octets nuls ou des espaces. Publiés tels quels, les octets nuls
   ressortent en caractères de remplacement dans les interfaces. */
const NUL_BYTE = String.fromCharCode(0);
const toText = (value) => String(value).replaceAll(NUL_BYTE, "").trimEnd();

/* Le type UTC de la ZCL compte les secondes écoulées depuis le 1er janvier 2000. */
const nowAsZclUtc = () => Math.round((Date.now() - constants.OneJanuary2000) / 1000);

/* Rend un horodatage ZCL au format Y-M-D HH:MM:SS, dans le fuseau horaire de la box — souvent UTC,
   ce qui correspond au référentiel de l'ERL. L'heure locale du compteur reste
   disponible séparément via l'attribut LocalTime.
   Les valeurs 0x00000000 et 0xFFFFFFFF signifient « non renseigné ». Un horodatage
   absent peut aussi remonter en NaN : il faut l'écarter explicitement, car il traverse
   les comparaisons et donnerait une date « NaN-NaN-NaN NaN:NaN:NaN ». Le JSON publié
   n'en laisse rien paraître, JSON.stringify rendant NaN comme null. */
const zclUtcToText = (seconds) => {
    const value = Number(seconds);
    if (seconds === undefined || seconds === null || !Number.isFinite(value) || value === 0 || value === 0xffffffff) return "non défini";
    const date = new Date(constants.OneJanuary2000 + value * 1000);
    const pad = (value) => String(value).padStart(2, "0");
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
};

/* Identifiant de fournisseur utilisé par l'ERL */
const ERL_PROVIDER_ID = 0x00000000;

/* Table 12 de la spécification 17-05035 : référence de temps des heures de début. */
const scheduleTimeReferences = {0: "heure UTC", 1: "heure normale", 2: "heure locale"};

/* Table 11 : type de calendrier. Seul le type Linky est défini. */
const scheduleTypes = {0: "calendrier Linky", 0xff: "non spécifié"};

/* Les heures de début des entrées sont exprimées en minutes depuis minuit. */
const minutesToClock = (minutes) => `${String(Math.floor(minutes / 60)).padStart(2, "0")}:${String(minutes % 60).padStart(2, "0")}`;

/* Chaque entrée de profil Linky occupe 4 octets : heure de début sur 16 bits,
   registre tarifaire sur 8 bits, état des 8 contacts secs sur 8 bits
   (section 10.15.2.3.2.3.1 de la spécification). */
const parseDayScheduleEntries = (buffer) => {
    const entries = [];
    if (!buffer) return entries;
    for (let offset = 0; offset + 4 <= buffer.length; offset += 4) {
        const switchState = buffer.readUInt8(offset + 3);
        entries.push({
            start_time: minutesToClock(buffer.readUInt16LE(offset)),
            register_tier: buffer.readUInt8(offset + 2),
            auxiliary_load_switch_state: switchState,
            /* Bit 0 = contact sec 1, bit 7 = contact sec 8. */
            auxiliary_switches: Array.from({length: 8}, (unused, index) => ((switchState >> index) & 1) === 1),
        });
    }
    return entries;
};

/* Un profil journalier peut être transmis en plusieurs commandes : on accumule les
   fragments jusqu'à réception du dernier (section 10.15.2.3.2). */
const dayProfileFragments = new Map();

/* Tables 6 et 7 de la spécification 17-05035 : couleur du jour et du lendemain,
   codées sur deux bits chacune dans LinkyPeakPeriodStatus. */
const linkyDayColors = {0: "non utilisé", 1: "bleu", 2: "blanc", 3: "rouge"};

/* Table 8 : préavis de pointe mobile, bits 4 et 5. */
const linkyPeakPriorNotices = {0: "aucune pointe prévue", 1: "préavis pointe 1", 2: "préavis pointe 2", 3: "préavis pointe 3"};

/* Table 9 : pointe mobile en cours, bits 6 et 7. */
const linkyOnPeakStates = {0: "hors pointe", 1: "pointe 1 en cours", 2: "pointe 2 en cours", 3: "pointe 3 en cours"};

/* Période d'intégration renvoyée par GetProfileResponse, table 10-42 de la
   spécification 17-05019-012 du cluster Metering Linky. La valeur 8 (1 minute)
   complète la table historique de la ZCL, qui s'arrêtait à 7. */
const profileIntervalPeriods = {
    0: "journalier",
    1: "60 minutes",
    2: "30 minutes",
    3: "15 minutes",
    4: "10 minutes",
    5: "7,5 minutes",
    6: "5 minutes",
    7: "2,5 minutes",
    8: "1 minute",
};

/* Table D-5 de la ZCL : statut renvoyé par GetProfileResponse. */
const profileStatuses = {
    0: "succes",
    1: "periode non prise en charge",
    2: "canal d'intervalle non pris en charge",
    3: "date de fin dans le futur",
    4: "aucun intervalle disponible",
};

/* Table 10-46 : format du sous-payload de PublishSnapshot. « Delivered » désigne
   l'énergie soutirée par le client, « Received » l'énergie injectée sur le réseau. */
const snapshotPayloadTypes = {
    0: "registres TOU soutirés, avec facturation",
    1: "registres TOU injectés, avec facturation",
    2: "registres par tranche soutirés, avec facturation",
    3: "registres par tranche injectés, avec facturation",
    4: "registres TOU soutirés, sans facturation",
    5: "registres TOU injectés, sans facturation",
    6: "registres par tranche soutirés, sans facturation",
    7: "registres par tranche injectés, sans facturation",
    128: "données indisponibles",
};

/* Table 10-45 : cause de l'instantané, bitmap 32 bits — un instantané planifié est le
   bit 16, soit 0x00010000. Certains micrologiciels de l'ERL ont numéroté les rangs et
   renvoient 16 pour cette même cause ; les deux conventions coexistent donc sur le
   terrain et sont acceptées à la lecture. */
const snapshotCauses = {
    0: "générale",
    1: "fin de période de facturation",
    2: "fin de période de tranche",
    3: "changement d'information tarifaire",
    4: "changement de matrice de prix",
    5: "changement de seuils de tranche",
    6: "changement de pouvoir calorifique",
    7: "changement de facteur de conversion",
    8: "changement de calendrier",
    9: "pointe mobile",
    10: "déclenché manuellement par le client",
    11: "fin de période de résolution",
    12: "changement d'occupant",
    13: "changement de fournisseur",
    14: "changement de mode du compteur",
    15: "paiement de dette",
    16: "instantané planifié",
    17: "téléchargement de micrologiciel",
};

/* Le champ étant un bitmap, il peut remonter en BigInt selon la pile.
   Au-delà du dernier rang connu la valeur ne peut être qu'un bitmap : on énumère alors
   les bits actifs. En deçà, elle est lue comme un rang, ce que fait l'ERL. Les deux
   lectures se rejoignent sur la seule cause qu'il produit, l'instantané planifié. */
const snapshotCauseToText = (cause) => {
    if (cause === undefined || cause === null) return undefined;
    const value = Number(cause);
    if (!Number.isFinite(value)) return undefined;
    const lastRank = Math.max(...Object.keys(snapshotCauses).map(Number));
    if (value <= lastRank) return snapshotCauses[value] ?? `inconnue (${value})`;
    const causes = [];
    for (let bit = 0; bit < 32; bit += 1) {
        if ((value >>> bit) & 1) causes.push(snapshotCauses[bit] ?? `bit ${bit}`);
    }
    return causes.length > 0 ? causes.join(", ") : `inconnue (${value})`;
};

/* Index 48 bits ; 0xFFFFFFFFFFFF signifie « non disponible ». */
const readSummation = (buffer, offset) => {
    const value = buffer.readUIntLE(offset, 6);
    return value === 0xffffffffffff ? null : value;
};

/* Sous-payload de PublishSnapshot, figures 10-11 à 10-18 de la spécification 17-05019.
   Les huit types commencent par l'index courant sur 48 bits ; les variantes « avec
   facturation » (0 et 1) intercalent ensuite les montants facturés, puis vient le
   nombre de tarifs en usage suivi d'autant d'index de 48 bits.
   Les types par tranche (2, 3, 6 et 7) ajoutent une seconde série indexée par tranche
   que le Linky n'utilise pas : ils restent disponibles en hexadécimal brut. */
/* Un instantané est renvoyé en plusieurs commandes PublishSnapshot : une par sens
   d'énergie, celle du soutirage étant de surcroît découpée en un à trois fragments selon
   le nombre d'index de l'offre — quatre commandes au plus. Seul le sous-payload est
   découpé, les champs d'en-tête étant répétés à l'identique dans chaque fragment
   (section 10.4.2.3.1.7.2). Une coupure pouvant tomber au milieu d'un index de 48 bits,
   les fragments sont rassemblés avant tout décodage. */
const snapshotFragments = new Map();

/* Les sens d'un même instantané arrivent en commandes séparées : on les réunit sous
   l'identifiant commun, et un nouvel identifiant repart d'un agrégat vide. */
const snapshotAggregates = new Map();

const parseSnapshotSubPayload = (payloadType, buffer) => {
    /* Le type 128 annonce qu'aucune donnée n'est disponible : le sous-payload est vide. */
    if (payloadType === 128) return {available: false};
    if (!buffer || buffer.length === 0) return undefined;
    const withBilling = payloadType === 0 || payloadType === 1;
    if (!withBilling && payloadType !== 4 && payloadType !== 5) return undefined;
    /* 6 octets d'index + 1 octet de nombre de tarifs, plus 17 octets de facturation. */
    if (buffer.length < (withBilling ? 24 : 7)) return undefined;
    /* Le sens de l'énergie n'est pas repris ici : il porte la section de l'instantané
       dans laquelle ce sous-payload est rangé. */
    const result = {};
    let offset = 0;
    result.current_summation = readSummation(buffer, offset);
    offset += 6;
    if (withBilling) {
        result.bill_to_date = buffer.readUInt32LE(offset);
        offset += 4;
        result.bill_to_date_time = buffer.readUInt32LE(offset);
        result.bill_to_date_time_text = zclUtcToText(result.bill_to_date_time);
        offset += 4;
        result.projected_bill = buffer.readUInt32LE(offset);
        offset += 4;
        result.projected_bill_time = buffer.readUInt32LE(offset);
        result.projected_bill_time_text = zclUtcToText(result.projected_bill_time);
        offset += 4;
        /* Quartet de poids fort : nombre de décimales des deux montants ci-dessus. */
        result.bill_trailing_digit = buffer.readUInt8(offset) >> 4;
        offset += 1;
    }
    result.number_of_tiers_in_use = buffer.readUInt8(offset);
    offset += 1;
    result.tier_summation = [];
    for (let tier = 0; tier < result.number_of_tiers_in_use && offset + 6 <= buffer.length; tier += 1) {
        result.tier_summation.push(readSummation(buffer, offset));
        offset += 6;
    }
    return result;
};

const erlModernExtend = {
    /* Attributs du cluster Metering décrits par la spécification 17-05031-011 mais
       absents de zigbee-herdsman : le nombre d'index tarifaires utilisés, et les index
       d'énergie réactive par quadrant exigés par le PICS (SEMT.S.A0D05 à A0D08). */
    erlAddMeteringAttributes: () => {
        return m.deviceAddCustomCluster("seMetering", {
            ID: 0x0702,
            attributes: {
                numberOfTiersInUse: {ID: 0x0023, type: Zcl.DataType.UINT8},
                serviceDisconnectReason: {ID: 0x0208, type: Zcl.DataType.ENUM8},
                linkyModeOfOperation: {ID: 0x0209, type: Zcl.DataType.ENUM8},
                currentDayMaxDemandDelivered: {ID: 0x045d, type: Zcl.DataType.UINT48},
                currentDayMaxDemandDeliveredTime: {ID: 0x045e, type: Zcl.DataType.UTC},
                currentDayMaxDemandReceived: {ID: 0x045f, type: Zcl.DataType.UINT48},
                currentDayMaxDemandReceivedTime: {ID: 0x0460, type: Zcl.DataType.UTC},
                previousDayMaxDemandDelivered: {ID: 0x0461, type: Zcl.DataType.UINT48},
                previousDayMaxDemandDeliveredTime: {ID: 0x0462, type: Zcl.DataType.UTC},
                previousDayMaxDemandReceived: {ID: 0x0463, type: Zcl.DataType.UINT48},
                previousDayMaxDemandReceivedTime: {ID: 0x0464, type: Zcl.DataType.UTC},
                currentReactiveSummationQ1: {ID: 0x0d05, type: Zcl.DataType.UINT48},
                currentReactiveSummationQ2: {ID: 0x0d06, type: Zcl.DataType.UINT48},
                currentReactiveSummationQ3: {ID: 0x0d07, type: Zcl.DataType.UINT48},
                currentReactiveSummationQ4: {ID: 0x0d08, type: Zcl.DataType.UINT48},
            },
            commands: {},
            /* zigbee-herdsman s'arrête au champ SnapshotPayloadType : le sous-payload
               qui suit est marqué « TODO » dans sa définition du cluster, si bien que
               ses octets sont perdus au décodage. On redéclare donc la commande avec ce
               dernier champ lu tel quel, la définition custom l'emportant sur la
               définition standard ; son interprétation revient à parseSnapshotSubPayload. */
            commandsResponse: {
                publishSnapshot: {
                    name: "publishSnapshot",
                    ID: 0x06,
                    parameters: [
                        {name: "id", type: Zcl.DataType.UINT32},
                        {name: "time", type: Zcl.DataType.UTC},
                        {name: "totalSnapshotsFound", type: Zcl.DataType.UINT8},
                        {name: "commandIndex", type: Zcl.DataType.UINT8},
                        {name: "totalNumberOfCommands", type: Zcl.DataType.UINT8},
                        {name: "cause", type: Zcl.DataType.BITMAP32},
                        {name: "payloadType", type: Zcl.DataType.ENUM8},
                        {name: "subPayload", type: Zcl.BuffaloZclDataType.BUFFER},
                    ],
                },
            },
        });
    },
    /* Mise à l'heure quotidienne du compteur.
       L'ERL attend un horodatage UTC : le nombre de secondes écoulées depuis le
       1er janvier 2000 à 0 h 0 min 0 s UTC. Si l'attribut vaut 0x00000000 ou
       0xFFFFFFFF, l'ERL se considère comme n'étant pas à l'heure et les commandes
       du cluster Metering ne renvoient plus rien.
       En mode TIC standard le compteur renseigne l'heure lui-même ; en mode TIC
       historique, c'est au coordinateur de le faire, d'où cette synchronisation.
       Elle reste active dans les deux modes : en TIC standard elle corrige une
       éventuelle dérive de l'horloge du compteur, sans effet de bord constaté.
       L'intervalle est réglable, et la valeur 0 désactive la synchronisation.
       Le composant writeTimeDaily de la bibliothèque ne convient pas : il ajoute le
       décalage du fuseau horaire et écrit donc une heure locale. */
    erlWriteTimeDailyUtc: () => {
        return m.poll({
            key: "time_sync",
            defaultIntervalSeconds: 60 * 60 * 24,
            option: e
                .numeric("time_sync_poll_interval", ea.SET)
                .withUnit("s")
                .withValueMin(0)
                .withDescription("Intervalle de mise à l'heure du compteur. 0 désactive la synchronisation."),
            poll: async (device) => {
                const endpoint = device.getEndpoint(1);
                /* 0 = TIC historique, 1 = TIC standard. La valeur est lue à la
                   configuration ; on la relit si le cache est vide. */
                let mode = endpoint.getClusterAttributeValue("seMetering", "linkyModeOfOperation");
                if (mode === undefined || mode === null) {
                    try {
                        await endpoint.read("seMetering", ["linkyModeOfOperation"]);
                        mode = endpoint.getClusterAttributeValue("seMetering", "linkyModeOfOperation");
                    } catch (error) {
                        logger.warning(`ERL: mode de fonctionnement du compteur inconnu, mise à l'heure ignorée (${error})`, NS);
                        return;
                    }
                }
                if (mode !== 0) {
                    logger.debug(`ERL: compteur en TIC standard, mise à l'heure laissée au compteur`, NS);
                    return;
                }
                const time = Math.round((Date.now() - constants.OneJanuary2000) / 1000);
                try {
                    await endpoint.write("genTime", {time: time}, {sendPolicy: "queue"});
                } catch (error) {
                    logger.error(`ERL: mise à l'heure impossible pour '${device.ieeeAddr}' (${error})`, NS);
                }
            },
        });
    },
    /* Le cluster Messaging (0x0703) est présent mais désactivé dans zigbee-herdsman
       (sa définition y est commentée) : il faut donc le déclarer entièrement.
       Il ne porte aucun attribut, uniquement des commandes. */
    erlAddMessaging: () => {
        return m.deviceAddCustomCluster("seMessaging", {
            ID: 0x0703,
            attributes: {},
            /* Commandes reçues par le serveur. */
            commands: {
                getLastMessage: {ID: 0x00, parameters: []},
                messageConfirmation: {
                    ID: 0x01,
                    parameters: [
                        {name: "messageId", type: Zcl.DataType.UINT32},
                        {name: "confirmationTime", type: Zcl.DataType.UTC},
                    ],
                },
            },
            /* Commandes générées par le serveur. */
            commandsResponse: {
                displayMessage: {
                    ID: 0x00,
                    parameters: [
                        {name: "messageId", type: Zcl.DataType.UINT32},
                        {name: "messageControl", type: Zcl.DataType.BITMAP8},
                        {name: "startTime", type: Zcl.DataType.UTC},
                        {name: "durationInMinutes", type: Zcl.DataType.UINT16},
                        {name: "message", type: Zcl.DataType.OCTET_STR},
                    ],
                },
                cancelMessage: {
                    ID: 0x01,
                    parameters: [
                        {name: "messageId", type: Zcl.DataType.UINT32},
                        {name: "messageControl", type: Zcl.DataType.BITMAP8},
                    ],
                },
            },
        });
    },
    erlAddDailySchedule: () => {
        return m.deviceAddCustomCluster("dailySchedule", {
            ID: 0x070d,
            attributes: {
                auxSwitch1Label: {ID: 0x0000, type: Zcl.DataType.OCTET_STR, write: true},
                auxSwitch2Label: {ID: 0x0001, type: Zcl.DataType.OCTET_STR, write: true},
                auxSwitch3Label: {ID: 0x0002, type: Zcl.DataType.OCTET_STR, write: true},
                auxSwitch4Label: {ID: 0x0003, type: Zcl.DataType.OCTET_STR, write: true},
                auxSwitch5Label: {ID: 0x0004, type: Zcl.DataType.OCTET_STR, write: true},
                auxSwitch6Label: {ID: 0x0005, type: Zcl.DataType.OCTET_STR, write: true},
                auxSwitch7Label: {ID: 0x0006, type: Zcl.DataType.OCTET_STR, write: true},
                auxSwitch8Label: {ID: 0x0007, type: Zcl.DataType.OCTET_STR, write: true},
                currentAuxiliaryLoadSwitchState: {ID: 0x0100, type: Zcl.DataType.BITMAP8, report: true},
                currentDeliveredTier: {ID: 0x0101, type: Zcl.DataType.ENUM8, report: true},
                currentTierLabel: {ID: 0x0102, type: Zcl.DataType.OCTET_STR},
                linkyPeakPeriodStatus: {ID: 0x0103, type: Zcl.DataType.BITMAP8, report: true},
                peakStartTime: {ID: 0x0104, type: Zcl.DataType.UTC},
                peakEndTime: {ID: 0x0105, type: Zcl.DataType.UTC},
                currentTariffLabel: {ID: 0x0106, type: Zcl.DataType.OCTET_STR},
            },
            /* Commandes reçues par le serveur DailySchedule, section 10.15.2.4 de la
               spécification 17-05035. L'ERL utilise le ProviderID 0x00000000. */
            commands: {
                getSchedule: {
                    ID: 0x00,
                    parameters: [
                        {name: "providerId", type: Zcl.DataType.UINT32},
                        {name: "earliestStartTime", type: Zcl.DataType.UTC},
                        {name: "minIssuerEventId", type: Zcl.DataType.UINT32},
                        {name: "numberOfSchedules", type: Zcl.DataType.UINT8},
                        {name: "scheduleType", type: Zcl.DataType.ENUM8},
                    ],
                },
                getDayProfile: {
                    ID: 0x01,
                    parameters: [
                        {name: "providerId", type: Zcl.DataType.UINT32},
                        {name: "dayId", type: Zcl.DataType.UINT16},
                    ],
                },
                getScheduleCancellation: {ID: 0x05, parameters: []},
            },
            /* Commandes générées par le serveur, section 10.15.2.3. */
            commandsResponse: {
                publishSchedule: {
                    ID: 0x00,
                    parameters: [
                        {name: "providerId", type: Zcl.DataType.UINT32},
                        {name: "issuerEventId", type: Zcl.DataType.UINT32},
                        {name: "scheduleId", type: Zcl.DataType.UINT32},
                        {name: "dayId", type: Zcl.DataType.UINT16},
                        {name: "startTime", type: Zcl.DataType.UTC},
                        {name: "scheduleType", type: Zcl.DataType.ENUM8},
                        {name: "scheduleTimeReference", type: Zcl.DataType.UINT8},
                        {name: "scheduleName", type: Zcl.DataType.OCTET_STR},
                    ],
                },
                publishDayProfile: {
                    ID: 0x01,
                    parameters: [
                        {name: "providerId", type: Zcl.DataType.UINT32},
                        {name: "issuerEventId", type: Zcl.DataType.UINT32},
                        {name: "dayId", type: Zcl.DataType.UINT16},
                        {name: "totalNumberOfScheduleEntries", type: Zcl.DataType.UINT8},
                        {name: "commandIndex", type: Zcl.DataType.UINT8},
                        {name: "totalNumberOfCommands", type: Zcl.DataType.UINT8},
                        {name: "scheduleType", type: Zcl.DataType.ENUM8},
                        /* Suite d'entrées de 4 octets, décodée par le convertisseur :
                           le type « Series of Schedule Entries » n'existe pas en ZCL. */
                        {name: "dayScheduleEntries", type: Zcl.BuffaloZclDataType.BUFFER},
                    ],
                },
                cancelSchedule: {
                    ID: 0x05,
                    parameters: [
                        {name: "providerId", type: Zcl.DataType.UINT32},
                        {name: "scheduleId", type: Zcl.DataType.UINT32},
                        {name: "scheduleType", type: Zcl.DataType.ENUM8},
                    ],
                },
                cancelAllSchedules: {ID: 0x06, parameters: []},
            },
        });
    },
};

/** @type{Record<string, import('zigbee-herdsman-converters/lib/types').Fz.Converter>} */
const fzLocal = {
    meter_identification: {
        cluster: "seMeterIdentification",
        type: ["readResponse", "attributeReport"],
        convert: (model, msg, publish, options, meta) => {
            const result = {};
            const elements = [
                /* 0x0001*/ "meterTypeId" /* consumer or producer */,
                /* 0x000D*/ "availablePower" /* subscribed power */,
                /* 0x000E*/ "powerThreshold" /* cutoff power */,
                /* 0x000C*/ "pod" /* PRM if any */,
                /* 0x0000*/ "companyName" /* nom du fournisseur */,
                /* 0x0004*/ "dataQualityId" /* qualité des données */,
                /* 0x0006*/ "model" /* modèle du compteur */,
            ];
            for (const at of elements) {
                const atSnake = at
                    .split(/(?=[A-Z])/)
                    .join("_")
                    .toLowerCase();
                if (msg.data[at] !== undefined) {
                    if (at === "pod") result[atSnake] = toText(msg.data[at]);
                    else if (at === "companyName") result[atSnake] = toText(msg.data[at]);
                    /* Publié sous le nom meter_model : « model » seul prêterait à confusion
                       avec le modèle de l'équipement affiché par la box. */
                    else if (at === "model") result.meter_model = toText(msg.data[at]);
                    else result[atSnake] = msg.data[at];
                }
            }
            return result;
        },
    },
    metering: {
        cluster: "seMetering",
        type: ["readResponse", "attributeReport"],
        convert: (model, msg, publish, options, meta) => {
            const result = {};
            const elements = [
                "currentSummDelivered" /* 0x0000, index totalisateur soutirage */,
                "currentSummReceived" /* 0x0001, index totalisateur injection */,
                "activeRegisterTierDelivered" /* 0x0020, index en cours */,
                "currentTier1SummDelivered" /* 0x0100, index 1 */,
                "currentTier2SummDelivered" /* 0x0102, index 2 */,
                "currentTier3SummDelivered" /* 0x0104, index 3 */,
                "currentTier4SummDelivered" /* 0x0106, index 4 */,
                "currentTier5SummDelivered" /* 0x0108, index 5 */,
                "currentTier6SummDelivered" /* 0x010a, index 6 */,
                "520" /* 0x0208 serviceDisconnectReason (new) */,
                "serviceDisconnectReason" /* 0x0208 (new) */,
                "521" /* 0x0209 linkyModeOfOperation (new) */,
                "linkyModeOfOperation" /* 0x0209 (new) */,
                "instantaneousDemand" /* 0x0400, instantaneous total active power in kW */,
                "status" /* 0x0200, indicateurs d'état du compteur */,
                "extendedStatus" /* 0x0204, indicateurs d'état étendus */,
                "siteId" /* 0x0307, identifiant du site */,
                "meterSerialNumber" /* 0x0308, numéro de série du compteur */,
                "moduleSerialNumber" /* 0x030E, numéro de série du module */,
                "currentDayMaxDemandDelivered" /* 0x045D, puissance maximale soutirée du jour */,
                "currentDayMaxDemandDeliveredTime" /* 0x045E, horodatage du maximum soutiré du jour */,
                "currentDayMaxDemandReceived" /* 0x045F, puissance maximale injectée du jour */,
                "currentDayMaxDemandReceivedTime" /* 0x0460, horodatage du maximum injecté du jour */,
                "previousDayMaxDemandDelivered" /* 0x0461, puissance maximale soutirée de la veille */,
                "previousDayMaxDemandDeliveredTime" /* 0x0462, horodatage du maximum soutiré de la veille */,
                "previousDayMaxDemandReceived" /* 0x0463, puissance maximale injectée de la veille */,
                "previousDayMaxDemandReceivedTime" /* 0x0464, horodatage du maximum injecté de la veille */,
                "numberOfTiersInUse" /* 0x0023, nombre d'index tarifaires utilisés */,
                "currentReactiveSummationQ1" /* 0x0D05, index réactif quadrant 1 */,
                "currentReactiveSummationQ2" /* 0x0D06, index réactif quadrant 2 */,
                "currentReactiveSummationQ3" /* 0x0D07, index réactif quadrant 3 */,
                "currentReactiveSummationQ4" /* 0x0D08, index réactif quadrant 4 */,
            ];
            /* logger.info(`HELLO Read result of : ${JSON.stringify(msg.data)}`, NS); */
            for (const at of elements) {
                const atSnake = at
                    .split(/(?=[A-Z])/)
                    .join("_")
                    .toLowerCase();
                if (msg.data[at] !== undefined) {
                    if (at === "520") result["service_disconnect_reason"] = msg.data[at];
                    else if (at === "521") result["linky_mode_of_operation"] = msg.data[at];
                    else if (at === "siteId" || at === "meterSerialNumber" || at === "moduleSerialNumber") result[atSnake] = toText(msg.data[at]);
                    /* extendedStatus est un BITMAP64 renvoyé en BigInt : il doit être converti
                       avant publication, sinon JSON.stringify lève une TypeError */
                    else if (at === "extendedStatus") result[atSnake] = toHex64(msg.data[at]);
                    /* Les totalisateurs sont déclarés sur 48 bits : selon le type annoncé dans
                       la trame, ils peuvent arriver en BigInt. Les valeurs réelles tiennent
                       largement dans un entier JavaScript. */
                    else if (at.endsWith("Time")) {
                        result[atSnake] = msg.data[at];
                        result[`${atSnake}_text`] = zclUtcToText(msg.data[at]);
                    } else if (typeof msg.data[at] === "bigint") result[atSnake] = Number(msg.data[at]);
                    else result[atSnake] = msg.data[at];
                }
            }
            return result;
        },
    },
    dailySchedule: {
        cluster: "dailySchedule" /* 0x070d "dailySchedule" */,
        type: ["readResponse", "attributeReport"],
        convert: (model, msg, publish, options, meta) => {
            const result = {};
            const elements = [
                "auxSwitch1Label" /* 0x0000 auxSwitch1Label (new) */,
                "auxSwitch2Label" /* 0x0001 auxSwitch2Label (new) */,
                "auxSwitch3Label" /* 0x0002 auxSwitch3Label (new) */,
                "auxSwitch4Label" /* 0x0003 auxSwitch4Label (new) */,
                "auxSwitch5Label" /* 0x0004 auxSwitch5Label (new) */,
                "auxSwitch6Label" /* 0x0005 auxSwitch6Label (new) */,
                "auxSwitch7Label" /* 0x0006 auxSwitch7Label (new) */,
                "auxSwitch8Label" /* 0x0007 auxSwitch8Label (new) */,
                "currentAuxiliaryLoadSwitchState" /* 0x0100 currentAuxiliaryLoadSwitchState (new) */,
                "currentDeliveredTier" /* 0x0101 currentDeliveredTier (new) */,
                "currentTierLabel" /* 0x0102 currentTierLabel (new) */,
                "linkyPeakPeriodStatus" /* 0x0103 linkyPeakPeriodStatus (new) */,
                "peakStartTime" /* 0x0104 peakStartTime (new) */,
                "peakEndTime" /* 0x0105 peakEndTime (new) */,
                "currentTariffLabel" /* 0x0106 currentTariffLabel (new) */,
            ];
            /* logger.info(`COUCOU Read result of : ${JSON.stringify(msg.data)}, ${String(msg.data["currentTariffLabel"])}`, NS); */
            for (const at of elements) {
                const atSnake = at
                    .split(/(?=[A-Z])/)
                    .join("_")
                    .toLowerCase();
                if (msg.data[at] !== undefined) {
                    if (at === "linkyPeakPeriodStatus") {
                        const status = msg.data[at];
                        result[atSnake] = status;
                        result.current_day_color = linkyDayColors[status & 0b11];
                        result.next_day_color = linkyDayColors[(status >> 2) & 0b11];
                        result.peak_period_prior_notice = linkyPeakPriorNotices[(status >> 4) & 0b11];
                        result.on_peak = linkyOnPeakStates[(status >> 6) & 0b11];
                    } else if (at === "peakStartTime" || at === "peakEndTime") {
                        result[atSnake] = msg.data[at];
                        result[`${atSnake}_text`] = zclUtcToText(msg.data[at]);
                    } else if (at === "currentTariffLabel") result[atSnake] = toText(msg.data[at]);
                    else if (at === "currentTierLabel") result[atSnake] = toText(msg.data[at]);
                    else if (at === "auxSwitch1Label") result[atSnake] = toText(msg.data[at]);
                    else if (at === "auxSwitch8Label") result[atSnake] = toText(msg.data[at]);
                    else if (at === "auxSwitch2Label") result[atSnake] = toText(msg.data[at]);
                    else if (at === "auxSwitch3Label") result[atSnake] = toText(msg.data[at]);
                    else if (at === "auxSwitch4Label") result[atSnake] = toText(msg.data[at]);
                    else if (at === "auxSwitch5Label") result[atSnake] = toText(msg.data[at]);
                    else if (at === "auxSwitch6Label") result[atSnake] = toText(msg.data[at]);
                    else if (at === "auxSwitch7Label") result[atSnake] = toText(msg.data[at]);
                    else result[atSnake] = msg.data[at];
                }
            }
            return result;
        },
    },
    time: {
        cluster: "genTime",
        type: ["readResponse", "attributeReport"],
        convert: (model, msg, publish, options, meta) => {
            const result = {};
            const elements = [
                "time" /* 0x0000, heure courante du compteur */,
                "timeStatus" /* 0x0001, indicateurs de synchronisation */,
                "timeZone" /* 0x0002, décalage du fuseau horaire, en secondes */,
                "dstStart" /* 0x0003, début de l'heure d'été */,
                "dstEnd" /* 0x0004, fin de l'heure d'été */,
                "dstShift" /* 0x0005, décalage de l'heure d'été, en secondes */,
                "standardTime" /* 0x0006, heure normale */,
                "localTime" /* 0x0007, heure locale */,
                "lastSetTime" /* 0x0008, dernière mise à l'heure */,
                "validUntilTime" /* 0x0009, fin de validité de l'heure */,
            ];
            for (const at of elements) {
                const atSnake = at
                    .split(/(?=[A-Z])/)
                    .join("_")
                    .toLowerCase();
                if (msg.data[at] !== undefined) {
                    result[atSnake] = msg.data[at];
                }
            }
            /* Les horodatages ZCL comptent depuis le 1er janvier 2000 : on publie
               à côté de chaque valeur brute une forme lisible. */
            for (const at of ["time", "dstStart", "dstEnd", "standardTime", "localTime", "lastSetTime", "validUntilTime"]) {
                const atSnake = at
                    .split(/(?=[A-Z])/)
                    .join("_")
                    .toLowerCase();
                if (msg.data[at] !== undefined) {
                    result[`${atSnake}_text`] = zclUtcToText(msg.data[at]);
                }
            }
            return result;
        },
    },
    messaging: {
        cluster: "seMessaging",
        type: ["commandDisplayMessage", "commandCancelMessage"],
        convert: (model, msg, publish, options, meta) => {
            const data = msg.data;
            if (msg.type === "commandCancelMessage") {
                return {message_data: toJson({message_id: data.messageId, cancelled: true})};
            }
            return {
                message_data: toJson({
                    message_id: data.messageId,
                    message_control: data.messageControl,
                    start_time: data.startTime,
                    start_time_text: zclUtcToText(data.startTime),
                    /* zigbee-herdsman rend null la valeur invalide d'un entier 16 bits
                       (0xFFFF), qui signifie ici une durée d'affichage non bornée. */
                    duration_in_minutes: data.durationInMinutes ?? "illimitée",
                    message: toText(data.message),
                }),
            };
        },
    },
    dailyScheduleCommands: {
        cluster: "dailySchedule",
        type: ["commandPublishSchedule", "commandPublishDayProfile", "commandCancelSchedule", "commandCancelAllSchedules"],
        convert: (model, msg, publish, options, meta) => {
            const data = msg.data;
            if (msg.type === "commandPublishSchedule") {
                return {
                    schedule_data: toJson({
                        provider_id: data.providerId,
                        issuer_event_id: data.issuerEventId,
                        schedule_id: data.scheduleId,
                        day_id: data.dayId,
                        start_time: data.startTime,
                        start_time_text: zclUtcToText(data.startTime),
                        schedule_type: scheduleTypes[data.scheduleType] ?? `inconnu (${data.scheduleType})`,
                        schedule_time_reference: scheduleTimeReferences[data.scheduleTimeReference] ?? `inconnue (${data.scheduleTimeReference})`,
                        schedule_name: toText(data.scheduleName),
                    }),
                };
            }
            if (msg.type === "commandPublishDayProfile") {
                const key = `${msg.device.ieeeAddr}|${data.dayId}|${data.issuerEventId}`;
                const fragments = dayProfileFragments.get(key) ?? new Map();
                fragments.set(data.commandIndex, parseDayScheduleEntries(data.dayScheduleEntries));
                dayProfileFragments.set(key, fragments);
                /* Tant que tous les fragments ne sont pas là, on ne publie rien. */
                if (fragments.size < data.totalNumberOfCommands) return;
                dayProfileFragments.delete(key);
                const entries = [...fragments.keys()].sort((a, b) => a - b).flatMap((index) => fragments.get(index));
                return {
                    day_profile_data: toJson({
                        provider_id: data.providerId,
                        issuer_event_id: data.issuerEventId,
                        day_id: data.dayId,
                        schedule_type: scheduleTypes[data.scheduleType] ?? `inconnu (${data.scheduleType})`,
                        total_number_of_schedule_entries: data.totalNumberOfScheduleEntries,
                        entries: entries,
                    }),
                };
            }
            if (msg.type === "commandCancelSchedule") {
                return {
                    schedule_cancellation: toJson({
                        provider_id: data.providerId,
                        schedule_id: data.scheduleId,
                        schedule_type: scheduleTypes[data.scheduleType] ?? `inconnu (${data.scheduleType})`,
                    }),
                };
            }
            return {schedule_cancellation: toJson({cancelled: "tous les calendriers"})};
        },
    },
    meteringProfile: {
        cluster: "seMetering",
        type: ["commandGetProfileRsp"],
        convert: (model, msg, publish, options, meta) => {
            const data = msg.data;
            return {
                profile_data: toJson({
                    end_time: data.endTime,
                    end_time_text: zclUtcToText(data.endTime),
                    status: profileStatuses[data.status] ?? `inconnu (${data.status})`,
                    interval_period: profileIntervalPeriods[data.profileIntervalPeriod] ?? `inconnue (${data.profileIntervalPeriod})`,
                    number_of_periods_delivered: data.numberOfPeriodsDelivered,
                    intervals: data.intervals,
                }),
            };
        },
    },
    meteringSnapshot: {
        cluster: "seMetering",
        type: ["commandPublishSnapshot"],
        convert: (model, msg, publish, options, meta) => {
            const data = msg.data;
            const device = msg.device.ieeeAddr;
            const expected = data.totalNumberOfCommands ?? 1;

            /* Les fragments d'un même sens se rassemblent avant tout décodage. */
            const key = `${device}|${data.id}|${data.payloadType}`;
            const fragments = snapshotFragments.get(key) ?? new Map();
            fragments.set(data.commandIndex ?? 0, data.subPayload ? Buffer.from(data.subPayload) : Buffer.alloc(0));
            snapshotFragments.set(key, fragments);
            /* Tant que tous les fragments ne sont pas là, on ne publie rien pour ce sens. */
            if (fragments.size < expected) return;
            snapshotFragments.delete(key);
            const subPayload = Buffer.concat([...fragments.keys()].sort((a, b) => a - b).map((index) => fragments.get(index)));

            /* Les types pairs portent le soutirage, les impairs l'injection ; le type 128
               annonce un instantané sans données et ne relève d'aucun sens. */
            const section = data.payloadType === 128 ? "unavailable" : data.payloadType % 2 === 0 ? "delivered" : "received";

            /* L'ERL renvoie le même identifiant et le même horodatage pour tous ses
               instantanés — ceux du plus récent — quel que soit l'offset demandé : ils ne
               suffisent donc pas à les distinguer. Comme un GetSnapshot ne renvoie qu'une
               commande par sens, un sens qui se remplit une seconde fois annonce un nouvel
               instantané et non un complément de celui en cours. */
            const previous = snapshotAggregates.get(device);
            const continues = previous !== undefined && previous.id === data.id && previous[section] === undefined;
            const snapshot = continues
                ? previous
                : {
                      id: data.id,
                      time: data.time,
                      time_text: zclUtcToText(data.time),
                      total_snapshots_found: data.totalSnapshotsFound,
                      cause: data.cause,
                      cause_text: snapshotCauseToText(data.cause),
                      commands_received: 0,
                  };
            if (!continues) {
                /* Les fragments restés orphelins de l'instantané précédent n'ont plus d'objet. */
                for (const orphan of snapshotFragments.keys()) {
                    if (orphan.startsWith(`${device}|`)) snapshotFragments.delete(orphan);
                }
            }

            snapshot[section] = {
                payload_type: data.payloadType,
                payload_type_text: snapshotPayloadTypes[data.payloadType] ?? `inconnu (${data.payloadType})`,
                fragments: expected,
                ...(parseSnapshotSubPayload(data.payloadType, subPayload) ?? {}),
                sub_payload_hex: subPayload.length > 0 ? subPayload.toString("hex") : undefined,
            };
            /* Recompté plutôt que cumulé : une même commande rejouée ne doit pas le gonfler. */
            snapshot.commands_received = ["delivered", "received", "unavailable"].reduce(
                (total, name) => total + (snapshot[name]?.fragments ?? 0),
                0,
            );
            snapshotAggregates.set(device, snapshot);
            return {
                snapshot_data: toJson(snapshot),
            };
        },
    },
    meteringSampledData: {
        cluster: "seMetering",
        type: ["commandGetSampledDataRsp"],
        convert: (model, msg, publish, options, meta) => {
            const data = msg.data;
            return {
                sampled_data: toJson({
                    id: data.id,
                    start_time: data.startTime,
                    start_time_text: zclUtcToText(data.startTime),
                    type: data.type,
                    request_interval: data.requestInterval,
                    number_of_samples: data.numberOfSamples,
                    samples: data.samples,
                }),
            };
        },
    },
    electricalMeasurement: {
        cluster: "haElectricalMeasurement",
        type: ["readResponse", "attributeReport"],
        convert: (model, msg, publish, options, meta) => {
            const result = {};
            const elements = [
                "measurementType" /* 0x0000, mono ou triphase */,
                "totalActivePower" /* 0x0304, puissance active totale */,
                "totalApparentPower" /* 0x0306, total apparent power */,
                "rmsVoltage" /* 0x0505, voltage phase A */,
                "rmsCurrent" /* 0x0508, current phase A */,
                "activePower" /* 0x050B, puissance active phase A */,
                "apparentPower" /* 0x050F, apparent power phase A */,
                "rmsVoltagePhB" /* 0x0905, voltage phase B */,
                "rmsCurrentPhB" /* 0x0908, current phase B */,
                "activePowerPhB" /* 0x090B, puissance active phase B */,
                "apparentPowerPhB" /* 0x090F, apparent power phase B */,
                "rmsVoltagePhC" /* 0x0A05, voltage phase C */,
                "rmsCurrentPhC" /* 0x0A08, current phase C */,
                "activePowerPhC" /* 0x0A0B, puissance active phase C */,
                "apparentPowerPhC" /* 0x0a0f, apparent power phase C */,
            ];
            /* logger.info(`Read result of : ${JSON.stringify(msg.data)}`, NS); */
            for (const at of elements) {
                const atSnake = at
                    .split(/(?=[A-Z])/)
                    .join("_")
                    .toLowerCase();
                if (msg.data[at] !== undefined) {
                    result[atSnake] = msg.data[at];
                }
            }
            return result;
        },
    },
};

/* Correspondance entre le nom publié d'une grandeur et l'attribut ZCL qui la
   porte, avec son cluster. Les commandes groupées définies plus bas ne répondent
   qu'à leur propre nom (« currentMaxDemands », par exemple), alors que Jeedom et
   l'interface de Zigbee2MQTT émettent un /get portant le nom de la propriété
   elle-même. Sans cette table, chaque bouton de rafraîchissement échouait sur
   « No converter available ». */
const attributParPropriete = {
    /* Metering */
    active_register_tier_delivered: ["seMetering", "activeRegisterTierDelivered"],
    current_day_max_demand_delivered: ["seMetering", "currentDayMaxDemandDelivered"],
    current_day_max_demand_delivered_time: ["seMetering", "currentDayMaxDemandDeliveredTime"],
    current_day_max_demand_received: ["seMetering", "currentDayMaxDemandReceived"],
    current_day_max_demand_received_time: ["seMetering", "currentDayMaxDemandReceivedTime"],
    current_reactive_summation_q1: ["seMetering", "currentReactiveSummationQ1"],
    current_reactive_summation_q2: ["seMetering", "currentReactiveSummationQ2"],
    current_reactive_summation_q3: ["seMetering", "currentReactiveSummationQ3"],
    current_reactive_summation_q4: ["seMetering", "currentReactiveSummationQ4"],
    current_summ_delivered: ["seMetering", "currentSummDelivered"],
    current_summ_received: ["seMetering", "currentSummReceived"],
    current_tier1_summ_delivered: ["seMetering", "currentTier1SummDelivered"],
    current_tier2_summ_delivered: ["seMetering", "currentTier2SummDelivered"],
    current_tier3_summ_delivered: ["seMetering", "currentTier3SummDelivered"],
    current_tier4_summ_delivered: ["seMetering", "currentTier4SummDelivered"],
    current_tier5_summ_delivered: ["seMetering", "currentTier5SummDelivered"],
    current_tier6_summ_delivered: ["seMetering", "currentTier6SummDelivered"],
    extended_status: ["seMetering", "extendedStatus"],
    instantaneous_demand: ["seMetering", "instantaneousDemand"],
    linky_mode_of_operation: ["seMetering", "linkyModeOfOperation"],
    meter_serial_number: ["seMetering", "meterSerialNumber"],
    module_serial_number: ["seMetering", "moduleSerialNumber"],
    number_of_tiers_in_use: ["seMetering", "numberOfTiersInUse"],
    previous_day_max_demand_delivered: ["seMetering", "previousDayMaxDemandDelivered"],
    previous_day_max_demand_delivered_time: ["seMetering", "previousDayMaxDemandDeliveredTime"],
    previous_day_max_demand_received: ["seMetering", "previousDayMaxDemandReceived"],
    previous_day_max_demand_received_time: ["seMetering", "previousDayMaxDemandReceivedTime"],
    service_disconnect_reason: ["seMetering", "serviceDisconnectReason"],
    site_id: ["seMetering", "siteId"],
    status: ["seMetering", "status"],
    /* ElectricalMeasurement */
    active_power: ["haElectricalMeasurement", "activePower"],
    active_power_ph_b: ["haElectricalMeasurement", "activePowerPhB"],
    active_power_ph_c: ["haElectricalMeasurement", "activePowerPhC"],
    apparent_power: ["haElectricalMeasurement", "apparentPower"],
    apparent_power_ph_b: ["haElectricalMeasurement", "apparentPowerPhB"],
    apparent_power_ph_c: ["haElectricalMeasurement", "apparentPowerPhC"],
    measurement_type: ["haElectricalMeasurement", "measurementType"],
    rms_current: ["haElectricalMeasurement", "rmsCurrent"],
    rms_current_ph_b: ["haElectricalMeasurement", "rmsCurrentPhB"],
    rms_current_ph_c: ["haElectricalMeasurement", "rmsCurrentPhC"],
    rms_voltage: ["haElectricalMeasurement", "rmsVoltage"],
    rms_voltage_ph_b: ["haElectricalMeasurement", "rmsVoltagePhB"],
    rms_voltage_ph_c: ["haElectricalMeasurement", "rmsVoltagePhC"],
    total_active_power: ["haElectricalMeasurement", "totalActivePower"],
    total_apparent_power: ["haElectricalMeasurement", "totalApparentPower"],
    /* DailySchedule */
    aux_switch1_label: ["dailySchedule", "auxSwitch1Label"],
    aux_switch2_label: ["dailySchedule", "auxSwitch2Label"],
    aux_switch3_label: ["dailySchedule", "auxSwitch3Label"],
    aux_switch4_label: ["dailySchedule", "auxSwitch4Label"],
    aux_switch5_label: ["dailySchedule", "auxSwitch5Label"],
    aux_switch6_label: ["dailySchedule", "auxSwitch6Label"],
    aux_switch7_label: ["dailySchedule", "auxSwitch7Label"],
    aux_switch8_label: ["dailySchedule", "auxSwitch8Label"],
    current_auxiliary_load_switch_state: ["dailySchedule", "currentAuxiliaryLoadSwitchState"],
    current_delivered_tier: ["dailySchedule", "currentDeliveredTier"],
    current_tariff_label: ["dailySchedule", "currentTariffLabel"],
    current_tier_label: ["dailySchedule", "currentTierLabel"],
    linky_peak_period_status: ["dailySchedule", "linkyPeakPeriodStatus"],
    peak_end_time: ["dailySchedule", "peakEndTime"],
    peak_start_time: ["dailySchedule", "peakStartTime"],
    /* Time */
    dst_end: ["genTime", "dstEnd"],
    dst_shift: ["genTime", "dstShift"],
    dst_start: ["genTime", "dstStart"],
    last_set_time: ["genTime", "lastSetTime"],
    local_time: ["genTime", "localTime"],
    standard_time: ["genTime", "standardTime"],
    time: ["genTime", "time"],
    time_status: ["genTime", "timeStatus"],
    time_zone: ["genTime", "timeZone"],
    valid_until_time: ["genTime", "validUntilTime"],
    /* MeterIdentification */
    available_power: ["seMeterIdentification", "availablePower"],
    company_name: ["seMeterIdentification", "companyName"],
    data_quality_id: ["seMeterIdentification", "dataQualityId"],
    meter_model: ["seMeterIdentification", "model"],
    meter_type_id: ["seMeterIdentification", "meterTypeId"],
    pod: ["seMeterIdentification", "pod"],
    power_threshold: ["seMeterIdentification", "powerThreshold"],
};

const tzLocal = {
    /* Rafraîchissement d'une grandeur isolée, déclenché par son propre nom. C'est
       ce que fait le bouton de rafraîchissement, aussi bien dans Jeedom que dans
       l'interface de Zigbee2MQTT. Les commandes groupées ci-dessous restent le
       moyen de tout relire en une seule fois. */
    erlGetProperty: {
        key: Object.keys(attributParPropriete),
        convertGet: async (entity, key, meta) => {
            const [cluster, attribut] = attributParPropriete[key];
            await entity.read(cluster, [attribut]);
        },
    },
    meterIdGetAvailablePower: {
        key: ["currentAvailablePower"],
        convertGet: async (entity, key, meta) => {
            await entity.read("seMeterIdentification", ["availablePower", "meterTypeId", "powerThreshold", "pod", "companyName", "dataQualityId", "model"]);
        },
    },
    meteringGetTotalIndexes: {
        key: ["currentTotalSummDelivered"],
        convertGet: async (entity, key, meta) => {
            await entity.read("seMetering", ["currentSummDelivered", "currentSummReceived", "instantaneousDemand"]);
        },
    },
    meteringGetProfile: {
        key: ["get_profile"],
        /* L'ERL répond par une commande asynchrone (GetProfileResponse, PublishSnapshot,
           GetSampledDataResponse) et n'émet pas de réponse par défaut. Il faut désactiver
           les deux attentes, sinon la commande est déclarée en échec au bout de dix
           secondes alors même que la réponse est arrivée et a été traitée. */
        convertSet: async (entity, key, value, meta) => {
            const request = typeof value === "object" && value !== null ? value : {};
            await entity.command(
                "seMetering",
                "getProfile",
                {
                    intervalChannel: request.interval_channel ?? 0,
                    endTime: request.end_time ?? nowAsZclUtc(),
                    numberOfPeriods: request.number_of_periods ?? 1,
                },
                {disableResponse: true, disableDefaultResponse: true},
            );
        },
    },
    meteringGetSnapshot: {
        key: ["get_snapshot"],
        convertSet: async (entity, key, value, meta) => {
            const request = typeof value === "object" && value !== null ? value : {};
            await entity.command(
                "seMetering",
                "getSnapshot",
                {
                    earliestStartTime: request.earliest_start_time ?? 0,
                    latestEndTime: request.latest_end_time ?? nowAsZclUtc(),
                    offset: request.offset ?? 0,
                    /* Bitmap de causes, seul l'instantané planifié étant produit par l'ERL :
                       c'est le bit 16, soit 0x00010000. Les micrologiciels antérieurs à
                       octobre 2026 attendaient ici le rang 16 ; sur ceux-là, il faut saisir
                       cette valeur à la main, sans quoi la demande reste sans réponse. */
                    cause: request.cause ?? 0x00010000,
                },
                {disableResponse: true, disableDefaultResponse: true},
            );
        },
    },
    meteringGetSampledData: {
        key: ["get_sampled_data"],
        convertSet: async (entity, key, value, meta) => {
            const request = typeof value === "object" && value !== null ? value : {};
            await entity.command(
                "seMetering",
                "getSampledData",
                {
                    sampleId: request.sample_id ?? 0,
                    earliestSampleTime: request.earliest_sample_time ?? 0,
                    /* Paramètres attendus par l'ERL : type 4 (InstantaneousDemand),
                       échantillons au pas de 2 secondes. */
                    type: request.type ?? 4,
                    numberOfSamples: request.number_of_samples ?? 22,
                },
                {disableResponse: true, disableDefaultResponse: true},
            );
        },
    },
    messagingGetLastMessage: {
        key: ["get_last_message"],
        convertSet: async (entity, key, value, meta) => {
            await entity.command("seMessaging", "getLastMessage", {}, {disableResponse: true, disableDefaultResponse: true});
        },
    },
    messagingConfirmMessage: {
        key: ["confirm_message"],
        convertSet: async (entity, key, value, meta) => {
            const request = typeof value === "object" && value !== null ? value : {};
            await entity.command(
                "seMessaging",
                "messageConfirmation",
                {
                    messageId: request.message_id ?? 0,
                    confirmationTime: request.confirmation_time ?? nowAsZclUtc(),
                },
                {disableResponse: true, disableDefaultResponse: true},
            );
        },
    },
    dailyScheduleGetSchedule: {
        key: ["get_schedule"],
        /* L'ERL répond par une commande asynchrone (PublishSchedule, PublishDayProfile).
           Il faut désactiver l'attente de la réponse mais aussi celle de la réponse par
           défaut : sans cela, zigbee-herdsman patiente dix secondes puis déclare la
           commande en échec, alors même que la réponse est arrivée et a été traitée. */
        convertSet: async (entity, key, value, meta) => {
            const request = typeof value === "object" && value !== null ? value : {};
            await entity.command(
                "dailySchedule",
                "getSchedule",
                {
                    providerId: request.provider_id ?? ERL_PROVIDER_ID,
                    earliestStartTime: request.earliest_start_time ?? 0,
                    minIssuerEventId: request.min_issuer_event_id ?? 0xffffffff,
                    /* L'ERL n'accepte pas la valeur 0 (« tous les calendriers ») : il
                       attend 1 ou 2, soit le calendrier courant et le suivant. */
                    numberOfSchedules: request.number_of_schedules ?? 1,
                    scheduleType: request.schedule_type ?? 0,
                },
                {disableResponse: true, disableDefaultResponse: true},
            );
        },
    },
    dailyScheduleGetDayProfile: {
        key: ["get_day_profile"],
        convertSet: async (entity, key, value, meta) => {
            const request = typeof value === "object" && value !== null ? value : {};
            await entity.command(
                "dailySchedule",
                "getDayProfile",
                {
                    providerId: request.provider_id ?? ERL_PROVIDER_ID,
                    dayId: request.day_id ?? 0,
                },
                {disableResponse: true, disableDefaultResponse: true},
            );
        },
    },
    dailyScheduleGetCancellation: {
        key: ["get_schedule_cancellation"],
        convertSet: async (entity, key, value, meta) => {
            await entity.command("dailySchedule", "getScheduleCancellation", {}, {disableResponse: true, disableDefaultResponse: true});
        },
    },
    timeGetAll: {
        key: ["currentTime"],
        convertGet: async (entity, key, meta) => {
            await entity.read("genTime", [
                "time",
                "timeStatus",
                "timeZone",
                "dstStart",
                "dstEnd",
                "dstShift",
                "standardTime",
                "localTime",
                "lastSetTime",
                "validUntilTime",
            ]);
        },
    },
    meteringGetMaxDemands: {
        key: ["currentMaxDemands"],
        convertGet: async (entity, key, meta) => {
            await entity.read("seMetering", [
                "currentDayMaxDemandDelivered",
                "currentDayMaxDemandDeliveredTime",
                "currentDayMaxDemandReceived",
                "currentDayMaxDemandReceivedTime",
                "previousDayMaxDemandDelivered",
                "previousDayMaxDemandDeliveredTime",
                "previousDayMaxDemandReceived",
                "previousDayMaxDemandReceivedTime",
            ]);
        },
    },
    meteringGetReactiveSummations: {
        key: ["currentReactiveSummations"],
        convertGet: async (entity, key, meta) => {
            await entity.read("seMetering", [
                "currentReactiveSummationQ1",
                "currentReactiveSummationQ2",
                "currentReactiveSummationQ3",
                "currentReactiveSummationQ4",
            ]);
        },
    },
    meteringGetMeterStatus: {
        key: ["currentMeterStatus"],
        convertGet: async (entity, key, meta) => {
            await entity.read("seMetering", ["status", "extendedStatus", "siteId", "meterSerialNumber", "moduleSerialNumber", "numberOfTiersInUse"]);
        },
    },
    electricalMeasurementGetActivePower: {
        key: ["currentActivePower"],
        convertGet: async (entity, key, meta) => {
            await entity.read("haElectricalMeasurement", ["totalActivePower", "activePower", "activePowerPhB", "activePowerPhC"]);
        },
    },
    meteringGet16Indexes: {
        key: ["current16SummDelivered"],
        convertGet: async (entity, key, meta) => {
            await entity.read("seMetering", [
                "currentTier1SummDelivered",
                "currentTier2SummDelivered",
                "currentTier3SummDelivered",
                "currentTier4SummDelivered",
                "currentTier5SummDelivered",
                "currentTier6SummDelivered",
            ]);
        },
    },
};

export default {
    zigbeeModel: ["ZIGBEE_ERL"],
    model: "ZIGBEE_ERL",
    vendor: "EDFLab",
    description: "Linky Meter TIC Interface",
    extend: [m.onOff({powerOnBehavior: false}), /* m.electricityMeter({threePhase: true}), */ m.identify(), erlModernExtend.erlAddDailySchedule(), erlModernExtend.erlAddMeteringAttributes(), erlModernExtend.erlWriteTimeDailyUtc(), erlModernExtend.erlAddMessaging()],
    fromZigbee: [
        fzLocal.meter_identification,
        fzLocal.metering,
        fzLocal.dailySchedule,
        fzLocal.electricalMeasurement,
        fzLocal.meteringProfile,
        fzLocal.meteringSnapshot,
        fzLocal.meteringSampledData,
        fzLocal.time,
        fzLocal.dailyScheduleCommands,
        fzLocal.messaging,
    ],
    toZigbee: [tz.read, tz.write, tz.command, tzLocal.meteringGetTotalIndexes, tzLocal.meteringGet16Indexes, tzLocal.meterIdGetAvailablePower, tzLocal.meteringGetMeterStatus, tzLocal.electricalMeasurementGetActivePower, tzLocal.meteringGetProfile, tzLocal.meteringGetSnapshot, tzLocal.meteringGetSampledData, tzLocal.meteringGetReactiveSummations, tzLocal.timeGetAll, tzLocal.dailyScheduleGetSchedule, tzLocal.dailyScheduleGetDayProfile, tzLocal.dailyScheduleGetCancellation, tzLocal.messagingGetLastMessage, tzLocal.messagingConfirmMessage, tzLocal.meteringGetMaxDemands, tzLocal.erlGetProperty],
    exposes: [
        e.numeric("current_delivered_tier", ea.STATE_GET).withDescription("index en cours"),
        e.text("current_tariff_label", ea.STATE_GET).withDescription("nom offre"),
        e.text("current_tier_label", ea.STATE_GET).withDescription("nom index en cours"),
        e.text("pod", ea.STATE_GET).withDescription("PRM du compteur"),
        e.text("company_name", ea.STATE_GET).withDescription("nom du fournisseur"),
        e.text("meter_model", ea.STATE_GET).withDescription("modèle du compteur"),
        e.numeric("data_quality_id", ea.STATE_GET).withDescription("qualité des données transmises"),
        e.numeric("current_auxiliary_load_switch_state", ea.STATE_GET).withDescription("position contacts secs"),
        e.text("aux_switch1_label", ea.STATE_GET).withDescription("libellé contact sec 1"),
        e.text("aux_switch2_label", ea.STATE_GET).withDescription("libellé contact sec 2"),
        e.text("aux_switch3_label", ea.STATE_GET).withDescription("libellé contact sec 3"),
        e.text("aux_switch4_label", ea.STATE_GET).withDescription("libellé contact sec 4"),
        e.text("aux_switch5_label", ea.STATE_GET).withDescription("libellé contact sec 5"),
        e.text("aux_switch6_label", ea.STATE_GET).withDescription("libellé contact sec 6"),
        e.text("aux_switch7_label", ea.STATE_GET).withDescription("libellé contact sec 7"),
        e.text("aux_switch8_label", ea.STATE_GET).withDescription("libellé contact sec 8"),
        e.numeric("peak_start_time", ea.STATE_GET).withDescription("début de pointe mobile"),
        e.text("peak_start_time_text", ea.STATE).withDescription("début de pointe mobile, au format Y-M-D HH:MM:SS"),
        e.numeric("peak_end_time", ea.STATE_GET).withDescription("fin de pointe mobile"),
        e.text("peak_end_time_text", ea.STATE).withDescription("fin de pointe mobile, au format Y-M-D HH:MM:SS"),
        e.numeric("linky_peak_period_status", ea.STATE_GET).withDescription("couleur tempo & pointe mobile"),
        e.text("current_day_color", ea.STATE).withDescription("couleur du jour"),
        e.text("next_day_color", ea.STATE).withDescription("couleur du lendemain"),
        e.text("peak_period_prior_notice", ea.STATE).withDescription("préavis de pointe mobile"),
        e.text("on_peak", ea.STATE).withDescription("pointe mobile en cours"),
        e.numeric("meter_type_id", ea.STATE_GET).withDescription("consommateur ou producteur"),
        e.numeric("service_disconnect_reason", ea.STATE_GET).withDescription("disconnect reason"),
        e.numeric("linky_mode_of_operation", ea.STATE_GET).withDescription("histo ou std"),
        e.numeric("status", ea.STATE_GET).withDescription("indicateurs d'état du compteur (bitmap 8 bits)"),
        e.text("extended_status", ea.STATE_GET).withDescription("indicateurs d'état étendus (bitmap 64 bits, en hexadécimal)"),
        e.text("site_id", ea.STATE_GET).withDescription("identifiant du site"),
        e.text("meter_serial_number", ea.STATE_GET).withDescription("numéro de série du compteur"),
        e.text("module_serial_number", ea.STATE_GET).withDescription("numéro de série du module"),
        e.numeric("active_register_tier_delivered", ea.STATE_GET).withDescription("registre d'index actif"),
        e.numeric("number_of_tiers_in_use", ea.STATE_GET).withDescription("nombre d'index tarifaires utilisés"),
        e.numeric("current_tier1_summ_delivered", ea.STATE_GET).withUnit("Wh").withDescription("index 1"),
        e.numeric("current_tier2_summ_delivered", ea.STATE_GET).withUnit("Wh").withDescription("index 2"),
        e.numeric("current_tier3_summ_delivered", ea.STATE_GET).withUnit("Wh").withDescription("index 3"),
        e.numeric("current_tier4_summ_delivered", ea.STATE_GET).withUnit("Wh").withDescription("index 4"),
        e.numeric("current_tier5_summ_delivered", ea.STATE_GET).withUnit("Wh").withDescription("index 5"),
        e.numeric("current_tier6_summ_delivered", ea.STATE_GET).withUnit("Wh").withDescription("index 6"),
        e.numeric("current_summ_delivered", ea.STATE_GET).withUnit("Wh").withDescription("index total"),
        e.numeric("current_summ_received", ea.STATE_GET).withUnit("Wh").withDescription("index injection"),
        e.numeric("current_reactive_summation_q1", ea.STATE_GET).withUnit("varh").withDescription("index réactif quadrant 1"),
        e.numeric("current_reactive_summation_q2", ea.STATE_GET).withUnit("varh").withDescription("index réactif quadrant 2"),
        e.numeric("current_reactive_summation_q3", ea.STATE_GET).withUnit("varh").withDescription("index réactif quadrant 3"),
        e.numeric("current_reactive_summation_q4", ea.STATE_GET).withUnit("varh").withDescription("index réactif quadrant 4"),
        e.numeric("instantaneous_demand", ea.STATE_GET).withUnit("W").withDescription("puissance active instantanée"),
        e.numeric("current_day_max_demand_delivered", ea.STATE_GET).withUnit("W").withDescription("puissance maximale soutirée du jour"),
        e.numeric("current_day_max_demand_delivered_time", ea.STATE_GET).withUnit("s").withDescription("horodatage du maximum soutiré du jour"),
        e.text("current_day_max_demand_delivered_time_text", ea.STATE).withDescription("horodatage du maximum soutiré du jour, au format Y-M-D HH:MM:SS"),
        e.numeric("current_day_max_demand_received", ea.STATE_GET).withUnit("W").withDescription("puissance maximale injectée du jour"),
        e.numeric("current_day_max_demand_received_time", ea.STATE_GET).withUnit("s").withDescription("horodatage du maximum injecté du jour"),
        e.text("current_day_max_demand_received_time_text", ea.STATE).withDescription("horodatage du maximum injecté du jour, au format Y-M-D HH:MM:SS"),
        e.numeric("previous_day_max_demand_delivered", ea.STATE_GET).withUnit("W").withDescription("puissance maximale soutirée de la veille"),
        e.numeric("previous_day_max_demand_delivered_time", ea.STATE_GET).withUnit("s").withDescription("horodatage du maximum soutiré de la veille"),
        e.text("previous_day_max_demand_delivered_time_text", ea.STATE).withDescription("horodatage du maximum soutiré de la veille, au format Y-M-D HH:MM:SS"),
        e.numeric("previous_day_max_demand_received", ea.STATE_GET).withUnit("W").withDescription("puissance maximale injectée de la veille"),
        e.numeric("previous_day_max_demand_received_time", ea.STATE_GET).withUnit("s").withDescription("horodatage du maximum injecté de la veille"),
        e.text("previous_day_max_demand_received_time_text", ea.STATE).withDescription("horodatage du maximum injecté de la veille, au format Y-M-D HH:MM:SS"),
        e.numeric("measurement_type", ea.STATE_GET).withDescription("Mono ou tri"),
        e.numeric("available_power", ea.STATE_GET).withUnit("VA").withValueMin(0).withValueMax(36000).withDescription("Puissance souscrite"),
        e.numeric("power_threshold", ea.STATE_GET).withUnit("VA").withValueMin(0).withDescription("Puissance de coupure"),
        e.numeric("total_active_power", ea.STATE_GET).withUnit("W").withDescription("Puissance active totale"),
        e.numeric("active_power", ea.STATE_GET).withUnit("W").withDescription("Puissance active A"),
        e.numeric("active_power_ph_b", ea.STATE_GET).withUnit("W").withDescription("Puissance active B"),
        e.numeric("active_power_ph_c", ea.STATE_GET).withUnit("W").withDescription("Puissance active C"),
        e.numeric("total_apparent_power", ea.STATE_GET).withUnit("VA").withValueMin(0).withDescription("Puissance apparente totale"),
        e.numeric("apparent_power", ea.STATE_GET).withUnit("VA").withValueMin(0).withDescription("Puissance apparente A"),
        e.numeric("apparent_power_ph_b", ea.STATE_GET).withUnit("VA").withValueMin(0).withDescription("Puissance apparente B"),
        e.numeric("apparent_power_ph_c", ea.STATE_GET).withUnit("VA").withValueMin(0).withDescription("Puissance apparente C"),
        e.numeric("rms_current", ea.STATE_GET).withUnit("A").withValueMin(0).withDescription("Courant A"),
        e.numeric("rms_current_ph_b", ea.STATE_GET).withUnit("A").withValueMin(0).withDescription("Courant B"),
        e.numeric("rms_current_ph_c", ea.STATE_GET).withUnit("A").withValueMin(0).withDescription("Courant C"),
        e.numeric("rms_voltage", ea.STATE_GET).withUnit("V").withValueMin(0).withDescription("Tension A"),
        e.numeric("rms_voltage_ph_b", ea.STATE_GET).withUnit("V").withValueMin(0).withDescription("Tension B"),
        e.numeric("rms_voltage_ph_c", ea.STATE_GET).withUnit("V").withValueMin(0).withDescription("Tension C"),
        e
            .composite("get_profile", "get_profile", ea.SET)
            .withDescription("Demande l'historique de consommation (GetProfile). Le résultat est publié dans profile_data.")
            .withFeature(e.numeric("interval_channel", ea.SET).withDescription("0 = consommation soutirée, 1 = consommation injectée"))
            .withFeature(e.numeric("end_time", ea.SET).withDescription("Horodatage de fin, en secondes depuis le 1er janvier 2000"))
            .withFeature(e.numeric("number_of_periods", ea.SET).withDescription("Nombre de périodes demandées")),
        e
            .composite("get_snapshot", "get_snapshot", ea.SET)
            .withDescription("Demande un instantané de facturation (GetSnapshot). Le résultat est publié dans snapshot_data.")
            .withFeature(e.numeric("earliest_start_time", ea.SET).withDescription("Horodatage minimal, en secondes depuis le 1er janvier 2000"))
            .withFeature(e.numeric("latest_end_time", ea.SET).withDescription("Horodatage maximal, en secondes depuis le 1er janvier 2000"))
            .withFeature(e.numeric("offset", ea.SET).withDescription("Rang de l'instantané recherché"))
            .withFeature(
                e
                    .numeric("cause", ea.SET)
                    .withDescription(
                        "Cause de l'instantané, en bitmap : 65536 = instantané planifié, la seule que produise l'ERL. 4294967295 les demande toutes.",
                    ),
            ),
        e
            .composite("get_sampled_data", "get_sampled_data", ea.SET)
            .withDescription("Demande des échantillons de mesure (GetSampledData). Le résultat est publié dans sampled_data.")
            .withFeature(e.numeric("sample_id", ea.SET).withDescription("Identifiant de la série d'échantillons"))
            .withFeature(e.numeric("earliest_sample_time", ea.SET).withDescription("Horodatage minimal, en secondes depuis le 1er janvier 2000"))
            .withFeature(e.numeric("type", ea.SET).withDescription("Type d'échantillon : 4 = puissance instantanée"))
            .withFeature(e.numeric("number_of_samples", ea.SET).withDescription("Nombre d'échantillons demandés, 22 au maximum")),
        e.numeric("time", ea.STATE_GET).withUnit("s").withDescription("Heure du compteur, en secondes depuis le 1er janvier 2000"),
        e.text("time_text", ea.STATE).withDescription("Heure du compteur, au format Y-M-D HH:MM:SS (fuseau de la box)"),
        e.numeric("time_status", ea.STATE_GET).withDescription("Indicateurs de synchronisation de l'heure (bitmap 8 bits)"),
        e.numeric("time_zone", ea.STATE_GET).withUnit("s").withDescription("Décalage du fuseau horaire"),
        e.numeric("dst_start", ea.STATE_GET).withUnit("s").withDescription("Début de l'heure d'été"),
        e.text("dst_start_text", ea.STATE).withDescription("Début de l'heure d'été, au format Y-M-D HH:MM:SS"),
        e.numeric("dst_end", ea.STATE_GET).withUnit("s").withDescription("Fin de l'heure d'été"),
        e.text("dst_end_text", ea.STATE).withDescription("Fin de l'heure d'été, au format Y-M-D HH:MM:SS"),
        e.numeric("dst_shift", ea.STATE_GET).withUnit("s").withDescription("Décalage appliqué en heure d'été"),
        e.numeric("standard_time", ea.STATE_GET).withUnit("s").withDescription("Heure normale"),
        e.text("standard_time_text", ea.STATE).withDescription("Heure normale, au format Y-M-D HH:MM:SS"),
        e.numeric("local_time", ea.STATE_GET).withUnit("s").withDescription("Heure locale"),
        e.text("local_time_text", ea.STATE).withDescription("Heure locale du compteur, au format Y-M-D HH:MM:SS"),
        e.numeric("last_set_time", ea.STATE_GET).withUnit("s").withDescription("Dernière mise à l'heure du compteur"),
        e.text("last_set_time_text", ea.STATE).withDescription("Dernière mise à l'heure, au format Y-M-D HH:MM:SS"),
        e.numeric("valid_until_time", ea.STATE_GET).withUnit("s").withDescription("Fin de validité de l'heure"),
        e.text("valid_until_time_text", ea.STATE).withDescription("Fin de validité de l'heure, au format Y-M-D HH:MM:SS"),
        e
            .composite("get_schedule", "get_schedule", ea.SET)
            .withDescription("Demande le calendrier tarifaire (GetSchedule). Le résultat est publié dans schedule_data.")
            .withFeature(e.numeric("earliest_start_time", ea.SET).withDescription("Horodatage minimal, en secondes depuis le 1er janvier 2000"))
            .withFeature(e.numeric("min_issuer_event_id", ea.SET).withDescription("Numéro d'événement minimal"))
            .withFeature(e.numeric("number_of_schedules", ea.SET).withDescription("Nombre de calendriers souhaités : 1 (courant) ou 2 (courant et suivant)"))
            .withFeature(e.numeric("schedule_type", ea.SET).withDescription("0 = calendrier Linky")),
        e
            .composite("get_day_profile", "get_day_profile", ea.SET)
            .withDescription("Demande le profil d'une journée (GetDayProfile). Le résultat est publié dans day_profile_data.")
            .withFeature(e.numeric("day_id", ea.SET).withDescription("Identifiant du jour, obtenu via GetSchedule")),
        e.enum("get_schedule_cancellation", ea.SET, ["envoyer"]).withDescription("Demande la dernière annulation de calendrier"),
        e.enum("get_last_message", ea.SET, ["envoyer"]).withDescription("Demande le dernier message diffusé par le distributeur"),
        e
            .composite("confirm_message", "confirm_message", ea.SET)
            .withDescription("Accuse réception d'un message affiché")
            .withFeature(e.numeric("message_id", ea.SET).withDescription("Identifiant du message à confirmer"))
            .withFeature(e.numeric("confirmation_time", ea.SET).withDescription("Horodatage de la confirmation, en secondes depuis le 1er janvier 2000")),
        e.text("message_data", ea.STATE).withDescription("Dernier message reçu du distributeur"),
        e.text("schedule_data", ea.STATE).withDescription("Dernier calendrier tarifaire reçu"),
        e.text("day_profile_data", ea.STATE).withDescription("Dernier profil journalier reçu"),
        e.text("schedule_cancellation", ea.STATE).withDescription("Dernière annulation de calendrier reçue"),
        e.text("profile_data", ea.STATE).withDescription("Dernière réponse à GetProfile"),
        e.text("snapshot_data", ea.STATE).withDescription("Dernier instantané reçu"),
        e.text("sampled_data", ea.STATE).withDescription("Derniers échantillons reçus"),
    ],
    configure: async (device, coordinatorEndpoint) => {
        const endpoint = device.getEndpoint(1);
        await endpoint.read("seMetering", [0x0208, 0x0209]);
        await endpoint.read(1805, [257]); /* cluster dailySchedule 0x070D (new) - attribut currentDeliveredTier 0x0101 */
        await endpoint.read("dailySchedule", [
            "currentAuxiliaryLoadSwitchState",
            "currentTierLabel",
            "linkyPeakPeriodStatus",
            "currentTariffLabel",
            "peakStartTime",
            "peakEndTime",
        ]); /* cluster dailySchedule 0x070D (new) */
        await endpoint.read("dailySchedule", [
            "auxSwitch1Label",
            "auxSwitch2Label",
            "auxSwitch3Label",
            "auxSwitch4Label",
            "auxSwitch5Label",
            "auxSwitch6Label",
            "auxSwitch7Label",
            "auxSwitch8Label",
        ]); /* libellés des contacts secs */
        /* Lectures ajoutées à la demande d'EDF. Elles sont tolérantes à l'échec : un ERL
           qui n'implémente pas ces attributs ne doit pas faire échouer la configuration. */
        try {
            await endpoint.read("seMetering", ["status", "extendedStatus", "siteId", "meterSerialNumber"]);
        } catch (error) {
            logger.warning(`ERL: lecture des états et identifiants du compteur impossible (${error})`, NS);
        }
        try {
            await endpoint.read("haElectricalMeasurement", ["totalActivePower", "activePower", "activePowerPhB", "activePowerPhC"]);
        } catch (error) {
            logger.warning(`ERL: lecture des puissances actives impossible (${error})`, NS);
        }
        try {
            await endpoint.read("seMetering", [
                "currentReactiveSummationQ1",
                "currentReactiveSummationQ2",
                "currentReactiveSummationQ3",
                "currentReactiveSummationQ4",
            ]);
        } catch (error) {
            logger.warning(`ERL: lecture des index d'énergie réactive impossible (${error})`, NS);
        }
        try {
            await endpoint.read("genTime", ["time", "timeStatus", "timeZone", "dstStart", "dstEnd", "dstShift", "standardTime", "localTime", "lastSetTime", "validUntilTime"]);
        } catch (error) {
            logger.warning(`ERL: lecture de l'heure du compteur impossible (${error})`, NS);
        }
        try {
            await endpoint.read("seMeterIdentification", ["companyName", "dataQualityId", "model"]);
        } catch (error) {
            logger.warning(`ERL: lecture de l'identification du compteur impossible (${error})`, NS);
        }
        /* Les puissances maximales ne sont pas reportables : sans cette lecture, elles
           resteraient figées à la valeur relevée lors de l'appairage. */
        try {
            await endpoint.read("seMetering", [
                "currentDayMaxDemandDelivered",
                "currentDayMaxDemandDeliveredTime",
                "currentDayMaxDemandReceived",
                "currentDayMaxDemandReceivedTime",
                "previousDayMaxDemandDelivered",
                "previousDayMaxDemandDeliveredTime",
                "previousDayMaxDemandReceived",
                "previousDayMaxDemandReceivedTime",
            ]);
        } catch (error) {
            logger.warning(`ERL: lecture des puissances maximales impossible (${error})`, NS);
        }
    },
};

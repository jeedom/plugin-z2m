import * as m from "zigbee-herdsman-converters/lib/modernExtend";
import * as fz from "zigbee-herdsman-converters/converters/fromZigbee";
import * as tz from "zigbee-herdsman-converters/converters/toZigbee";
import * as constants from "zigbee-herdsman-converters/lib/constants";
import * as exposes from "zigbee-herdsman-converters/lib/exposes";
import * as reporting from "zigbee-herdsman-converters/lib/reporting";
import * as utils from "zigbee-herdsman-converters/lib/utils";
import {postfixWithEndpointName} from "zigbee-herdsman-converters/lib/utils";
import {getFromLookup, getKey} from "zigbee-herdsman-converters/lib/utils";
import {logger} from "zigbee-herdsman-converters/lib/logger";
import {Zcl, ZSpec} from "zigbee-herdsman";

const e = exposes.presets;
const ea = exposes.access;
const NS = "zhc:fz";

const erlModernExtend = {
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
            commands: {},
            commandsResponse: {},
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
                    if (at === "currentTariffLabel") result[atSnake] = String(msg.data[at]);
                    else if (at === "currentTierLabel") result[atSnake] = String(msg.data[at]);
                    else if (at === "auxSwitch1Label") result[atSnake] = String(msg.data[at]);
                    else if (at === "auxSwitch8Label") result[atSnake] = String(msg.data[at]);
                    else if (at === "auxSwitch2Label") result[atSnake] = String(msg.data[at]);
                    else if (at === "auxSwitch3Label") result[atSnake] = String(msg.data[at]);
                    else if (at === "auxSwitch4Label") result[atSnake] = String(msg.data[at]);
                    else if (at === "auxSwitch5Label") result[atSnake] = String(msg.data[at]);
                    else if (at === "auxSwitch6Label") result[atSnake] = String(msg.data[at]);
                    else if (at === "auxSwitch7Label") result[atSnake] = String(msg.data[at]);
                    else result[atSnake] = msg.data[at];
                }
            }
            return result;
        },
    },
    electricalMeasurement: {
        cluster: "haElectricalMeasurement",
        type: ["readResponse", "attributeReport"],
        convert: (model, msg, publish, options, meta) => {
            const result = {};
            const elements = [
                "measurementType" /* 0x0000, mono ou triphase */,
                "totalApparentPower" /* 0x0306, total apparent power */,
                "rmsVoltage" /* 0x0505, voltage phase A */,
                "rmsCurrent" /* 0x0508, current phase A */,
                "apparentPower" /* 0x050F, apparent power phase A */,
                "rmsVoltagePhB" /* 0x0905, voltage phase B */,
                "rmsCurrentPhB" /* 0x0908, current phase B */,
                "apparentPowerPhB" /* 0x090F, apparent power phase B */,
                "rmsVoltagePhC" /* 0x0A05, voltage phase C */,
                "rmsCurrentPhC" /* 0x0A08, current phase C */,
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

const tzLocal = {
    meterIdGetAvailablePower: {
        key: ["currentAvailablePower"],
        convertGet: async (entity, key, meta) => {
            await entity.read("seMeterIdentification", ["availablePower", "meterTypeId"]);
        },
    },
    meteringGetTotalIndexes: {
        key: ["currentTotalSummDelivered"],
        convertGet: async (entity, key, meta) => {
            await entity.read("seMetering", ["currentSummDelivered", "currentSummReceived"]);
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
    extend: [m.onOff({powerOnBehavior: false}), /* m.electricityMeter({threePhase: true}), */ m.identify(), erlModernExtend.erlAddDailySchedule()],
    fromZigbee: [fzLocal.meter_identification, fzLocal.metering, fzLocal.dailySchedule, fzLocal.electricalMeasurement, fzLocal.dailySchedule],
    toZigbee: [tz.read, tz.write, tz.command, tzLocal.meteringGetTotalIndexes, tzLocal.meteringGet16Indexes, tzLocal.meterIdGetAvailablePower],
    exposes: [
        e.numeric("current_delivered_tier", ea.STATE_GET).withDescription("index en cours"),
        e.text("current_tariff_label", ea.STATE_GET).withDescription("nom offre"),
        e.text("current_tier_label", ea.STATE_GET).withDescription("nom index en cours"),
        e.numeric("current_auxiliary_load_switch_state", ea.STATE_GET).withDescription("position contacts secs"),
        e.numeric("linky_peak_period_status", ea.STATE_GET).withDescription("couleur tempo & pointe mobile"),
        e.numeric("meter_type_id", ea.STATE_GET).withDescription("consommateur ou producteur"),
        e.numeric("service_disconnect_reason", ea.STATE_GET).withDescription("disconnect reason"),
        e.numeric("520", ea.STATE_GET).withDescription("disconnect reason"),
        e.numeric("linky_mode_of_operation", ea.STATE_GET).withDescription("histo ou std"),
        e.numeric("521", ea.STATE_GET).withDescription("histo ou std"),
        e.numeric("current_tier1_summ_delivered", ea.STATE_GET).withUnit("Wh").withDescription("index 1"),
        e.numeric("current_tier2_summ_delivered", ea.STATE_GET).withUnit("Wh").withDescription("index 2"),
        e.numeric("current_summ_delivered", ea.STATE_GET).withUnit("Wh").withDescription("index total"),
        e.numeric("measurement_type", ea.STATE_GET).withDescription("Mono ou tri"),
        e.numeric("available_power", ea.STATE_GET).withUnit("VA").withValueMin(0).withValueMax(36000).withDescription("Puissance souscrite"),
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
        ]); /* cluster dailySchedule 0x070D (new) */
    },
};

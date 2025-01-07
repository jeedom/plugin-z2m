const fz = require('zigbee-herdsman-converters/converters/fromZigbee');
const tz = require('zigbee-herdsman-converters/converters/toZigbee');
const exposes = require('zigbee-herdsman-converters/lib/exposes');
const reporting = require('zigbee-herdsman-converters/lib/reporting');
const constants = require('zigbee-herdsman-converters/lib/constants');
const e = exposes.presets;
const ea = exposes.access;

const fzLocal = {
    danfoss_thermostat: {
        cluster: 'hvacThermostat',
        type: ['attributeReport', 'readResponse'],
        convert: (model, msg, publish, options, meta) => {
            const result = {};
            if (msg.data.hasOwnProperty('danfossFloorMaxSetpoint')) {
                let value = precisionRound(msg.data['danfossFloorMaxSetpoint'], 2) / 100;
                value = value < -250 ? 0 : value;
                result[postfixWithEndpointName('max_floor_setpoint', msg, model, meta)] = value;
            }
            if (msg.data.hasOwnProperty('danfossFloorMinSetpoint')) {
                let value = precisionRound(msg.data['danfossFloorMinSetpoint'], 2) / 100;
                value = value < -250 ? 0 : value;
                result[postfixWithEndpointName('min_floor_setpoint', msg, model, meta)] = value;
            }
            return result;
        },
    }
}

const tzLocal = {
    danfoss_max_floor_setpoint: {
        key: ['max_floor_setpoint'],
        convertSet: async (entity, key, value, meta) => {
            let result;
            if (meta.options.thermostat_unit === 'fahrenheit') {
                result = Math.round(utils.normalizeCelsiusVersionOfFahrenheit(value) * 100);
            } else {
                result = (Math.round((value * 2).toFixed(1)) / 2).toFixed(1) * 100;
            }
            const danfossFloorMaxSetpoint = result;
            await entity.write('hvacThermostat', {danfossFloorMaxSetpoint}, manufacturerOptions.danfoss);
            return {state: {min_floor_setpoint: value}};
        },
        convertGet: async (entity, key, meta) => {
            await entity.read('hvacThermostat', ['danfossFloorMaxSetpoint'], manufacturerOptions.danfoss);
        },
    },
    danfoss_min_floor_setpoint: {
        key: ['min_floor_setpoint'],
        convertSet: async (entity, key, value, meta) => {
            let result;
            if (meta.options.thermostat_unit === 'fahrenheit') {
                result = Math.round(utils.normalizeCelsiusVersionOfFahrenheit(value) * 100);
            } else {
                result = (Math.round((value * 2).toFixed(1)) / 2).toFixed(1) * 100;
            }
            const danfossFloorMinSetpoint = result;
            await entity.write('hvacThermostat', {danfossFloorMinSetpoint}, manufacturerOptions.danfoss);
            return {state: {min_floor_setpoint: value}};
        },
        convertGet: async (entity, key, meta) => {
            await entity.read('hvacThermostat', ['danfossFloorMinSetpoint'], manufacturerOptions.danfoss);
        },
    }
}

module.exports = [
    {
        fingerprint: [
            {modelID: '0x8020', manufacturerName: 'Danfoss'}, // RT24V Display
            {modelID: '0x8021', manufacturerName: 'Danfoss'}, // RT24V Display  Floor sensor
            {modelID: '0x8030', manufacturerName: 'Danfoss'}, // RTbattery Display
            {modelID: '0x8031', manufacturerName: 'Danfoss'}, // RTbattery Display Infrared
            {modelID: '0x8034', manufacturerName: 'Danfoss'}, // RTbattery Dial
            {modelID: '0x8035', manufacturerName: 'Danfoss'}], // RTbattery Dial Infrared
        model: 'Icon',
        vendor: 'Danfoss',
        description: 'Icon floor heating (regulator, Zigbee module & thermostats) - Jeedom',
        fromZigbee: [
            fz.temperature,
            fz.danfoss_icon_regulator,
            fz.danfoss_thermostat,
            fzLocal.danfoss_thermostat,
            fz.danfoss_icon_battery,
            fz.thermostat],
        toZigbee: [
            tz.thermostat_local_temperature,
            tz.thermostat_occupied_heating_setpoint,
            tz.thermostat_system_mode,
            tz.thermostat_running_state,
            tz.thermostat_min_heat_setpoint_limit,
            tz.thermostat_max_heat_setpoint_limit,
            tz.danfoss_output_status,
            tz.danfoss_room_status_code,
            tz.danfoss_system_status_water,
            tz.danfoss_system_status_code,
            tz.danfoss_multimaster_role,
            tzLocal.danfoss_max_floor_setpoint,
            tzLocal.danfoss_min_floor_setpoint
        ],
        meta: {multiEndpoint: true, thermostat: {dontMapPIHeatingDemand: true}},
        // ota: ota.zigbeeOTA,
        endpoint: (device) => {
            return {
                'l1': 1, 'l2': 2, 'l3': 3, 'l4': 4, 'l5': 5,
                'l6': 6, 'l7': 7, 'l8': 8, 'l9': 9, 'l10': 10,
                'l11': 11, 'l12': 12, 'l13': 13, 'l14': 14, 'l15': 15, 'l16': 232,
            };
        },
        exposes: [].concat(((endpointsCount) => {
            const features = [];
            for (let i = 1; i <= endpointsCount; i++) {
                const epName = `l${i}`;
                if (i!=16) {
                    features.push(e.battery().withEndpoint(epName));
                    features.push(e.temperature().withEndpoint(epName));
                    features.push(exposes.climate().withSetpoint('occupied_heating_setpoint', 5, 35, 0.5)
                        .withLocalTemperature().withRunningState(['idle', 'heat']).withSystemMode(['heat']).withEndpoint(epName));
                    features.push(exposes.numeric('abs_min_heat_setpoint_limit', ea.STATE)
                        .withUnit('°C').withEndpoint(epName)
                        .withDescription('Absolute min temperature allowed on the device'));
                    features.push(exposes.numeric('abs_max_heat_setpoint_limit', ea.STATE)
                        .withUnit('°C').withEndpoint(epName)
                        .withDescription('Absolute max temperature allowed on the device'));
                    features.push(exposes.numeric('min_heat_setpoint_limit', ea.ALL)
                        .withValueMin(4).withValueMax(35).withValueStep(0.5).withUnit('°C')
                        .withEndpoint(epName).withDescription('Min temperature limit set on the device'));
                    features.push(exposes.numeric('max_heat_setpoint_limit', ea.ALL)
                        .withValueMin(4).withValueMax(35).withValueStep(0.5).withUnit('°C')
                        .withEndpoint(epName).withDescription('Max temperature limit set on the device'));
                    features.push(exposes.numeric('min_floor_setpoint', ea.ALL)
                        .withValueMin(4).withValueMax(35).withValueStep(0.5).withUnit('°C')
                        .withEndpoint(epName).withDescription('Min temperature limit set on the device'));
                    features.push(exposes.numeric('max_floor_setpoint', ea.ALL)
                        .withValueMin(4).withValueMax(35).withValueStep(0.5).withUnit('°C')
                        .withEndpoint(epName).withDescription('Max temperature limit set on the device'));
                    features.push(exposes.enum('setpoint_change_source', ea.STATE, ['manual', 'schedule', 'externally'])
                        .withEndpoint(epName));
                    features.push(exposes.enum('output_status', ea.STATE_GET, ['inactive', 'active'])
                        .withEndpoint(epName).withDescription('Danfoss Output Status [Active vs Inactive])'));
                    features.push(exposes.enum('room_status_code', ea.STATE_GET, ['no_error', 'missing_rt',
                        'rt_touch_error', 'floor_sensor_short_circuit', 'floor_sensor_disconnected'])
                        .withEndpoint(epName).withDescription('Thermostat status'));
                } else {
                    features.push(exposes.enum('system_status_code', ea.STATE_GET, ['no_error', 'missing_expansion_board',
                        'missing_radio_module', 'missing_command_module', 'missing_master_rail', 'missing_slave_rail_no_1',
                        'missing_slave_rail_no_2', 'pt1000_input_short_circuit', 'pt1000_input_open_circuit',
                        'error_on_one_or_more_output']).withEndpoint('l16').withDescription('Regulator Status'));
                    features.push(exposes.enum('system_status_water', ea.STATE_GET, ['hot_water_flow_in_pipes', 'cool_water_flow_in_pipes'])
                        .withEndpoint('l16').withDescription('Water Status of Regulator'));
                    features.push(exposes.enum('multimaster_role', ea.STATE_GET, ['invalid_unused', 'master', 'slave_1', 'slave_2'])
                        .withEndpoint('l16').withDescription('Regulator role (Master vs Slave)'));
                }
            }

            return features;
        })(16)),
        configure: async (device, coordinatorEndpoint, logger) => {
            const options = {manufacturerCode: 0x1246};

            for (let i = 1; i <= 15; i++) {
                const endpoint = device.getEndpoint(i);
                if (typeof endpoint !== 'undefined') {
                    await reporting.bind(endpoint, coordinatorEndpoint,
                        ['genPowerCfg', 'hvacThermostat', 'hvacUserInterfaceCfg','msTemperatureMeasurement']);
                    await reporting.batteryPercentageRemaining(endpoint,
                        {min: constants.repInterval.HOUR, max: 43200, change: 1});
                    await reporting.thermostatTemperature(endpoint,
                        {min: 0, max: constants.repInterval.MINUTES_10, change: 10});
                    await reporting.thermostatOccupiedHeatingSetpoint(endpoint,
                        {min: 0, max: constants.repInterval.MINUTES_10, change: 10});
                    await reporting.temperature(endpoint,
                        {min: 0, max: constants.repInterval.MINUTES_10, change: 10});

                    await endpoint.configureReporting('hvacThermostat', [{
                        attribute: 'danfossOutputStatus',
                        minimumReportInterval: constants.repInterval.MINUTE,
                        maximumReportInterval: constants.repInterval.MINUTES_10,
                        reportableChange: 1,
                    }], options);
                    await endpoint.configureReporting('hvacThermostat', [{
                        attribute: 'danfossFloorMaxSetpoint',
                        minimumReportInterval: constants.repInterval.MINUTE,
                        maximumReportInterval: constants.repInterval.MINUTES_10,
                        reportableChange: 10,
                    }], options);
                    await endpoint.configureReporting('hvacThermostat', [{
                        attribute: 'danfossFloorMinSetpoint',
                        minimumReportInterval: constants.repInterval.MINUTE,
                        maximumReportInterval: constants.repInterval.MINUTES_10,
                        reportableChange: 10,
                    }], options);

                    // Danfoss Icon Thermostat Specific
                    await endpoint.read('hvacThermostat', [
                        'danfossOutputStatus',
                        'danfossRoomStatusCode','danfossFloorMinSetpoint','danfossFloorMaxSetpoint'], options);

                    // Standard Thermostat
                    await endpoint.read('msTemperatureMeasurement', ['measuredValue']);
                    await endpoint.read('hvacThermostat', ['localTemp']);
                    await endpoint.read('hvacThermostat', ['occupiedHeatingSetpoint']);
                    await endpoint.read('hvacThermostat', ['systemMode']);
                    await endpoint.read('hvacThermostat', ['setpointChangeSource']);
                    await endpoint.read('hvacThermostat', ['absMinHeatSetpointLimit']);
                    await endpoint.read('hvacThermostat', ['absMaxHeatSetpointLimit']);
                    await endpoint.read('hvacThermostat', ['minHeatSetpointLimit']);
                    await endpoint.read('hvacThermostat', ['maxHeatSetpointLimit']);
                    await endpoint.read('genPowerCfg', ['batteryPercentageRemaining']);
                }
            }

            // Danfoss Icon Regulator Specific
            const endpoint232 = device.getEndpoint(232);

            await reporting.bind(endpoint232, coordinatorEndpoint, ['haDiagnostic']);

            await endpoint232.read('haDiagnostic', [
                'danfossSystemStatusCode',
                'danfossSystemStatusWater',
                'danfossMultimasterRole'], options);
        },
    },
];

<?php
/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/

/* * ***************************Includes********************************* */
require_once __DIR__  . '/../../../../core/php/core.inc.php';

class z2m extends eqLogic {
  /*     * *************************Attributs****************************** */

  private static $_cmd_converter = null;
  private static $_action_cmd = array(
    'value_off' => 'off',
    'value_on' => 'on',
    'value_toggle' => 'toggle'
  );

  /*     * ***********************Methode static*************************** */

  public static function firmwareUpdate($_options = array()) {
    config::save('deamonAutoMode', 0, 'z2m');
    log::clear(__CLASS__ . '_firmware');
    $log = log::getPathToLog(__CLASS__ . '_firmware');
    self::deamon_stop();
    if ($_options['sub_controller'] == 'elelabs') {
      if ($_options['firmware'] == 'fix_bootloader') {
        $cmd = 'sudo chmod +x ' . __DIR__ . '/../../resources/misc/ezsp-fix-bootloader.sh;';
        $cmd .= 'sudo ' . __DIR__ . '/../../resources/misc/ezsp-fix-bootloader.sh ' . $_options['port'];
      } else {
        $cmd = 'sudo chmod +x ' . __DIR__ . '/../../resources/misc/update-firmware-elelabs.sh;';
        $cmd .= 'sudo ' . __DIR__ . '/../../resources/misc/update-firmware-elelabs.sh ' . $_options['port'] . ' ' . $_options['firmware'];
      }
      log::add(__CLASS__ . '_firmware', 'info', __('Lancement de la mise à jour du firmware pour : ', __FILE__) . $_options['port'] . ' => ' . $cmd);
    } else if ($_options['sub_controller'] == 'luna') {
      if(file_exists('/dev/ttyLuna-Zigbee')){
          $_options['port'] = '/dev/ttyLuna-Zigbee';
      }else{
          $_options['port'] = '/dev/ttyUSB1';
      }
      $cmd = 'sudo chmod +x ' . __DIR__ . '/../../resources/misc/luna/AmberGwZ3_arm64_debian_V8;';
      $cmd .= 'sudo ' . __DIR__ . '/../../resources/misc/luna/AmberGwZ3_arm64_debian_V8 -p '.$_options['port'].' -b115200 -F '. __DIR__ . '/../../resources/misc/luna/' . $_options['firmware'];
    }else{
      log::add(__CLASS__ . '_firmware', 'alert', __('Pas de mise à jour possible du firmware pour : ', __FILE__) . $_options['port']);
      return;
    }
    log::add(__CLASS__ . '_firmware', 'alert', $cmd);
    shell_exec('sudo kill 9 $(lsof -t ' . $_options['port'] . ') >> ' . $log . ' 2>&1');
    shell_exec($cmd . ' >> ' . $log . ' 2>&1');
    config::save('deamonAutoMode', 0, 'z2m');
    self::deamon_start();
    log::add(__CLASS__ . '_firmware', 'alert', __('Fin de la mise à jour du firmware de la clef', __FILE__));
  }


  public static function createHtmlControl($_name, $_configuration, $_value = '') {
    $min = '';
    $max = '';
    if (isset($_configuration['value_min'])) {
      $min = 'min=' . $_configuration['value_min'];
    }
    if (isset($_configuration['value_max'])) {
      $max = 'max=' . $_configuration['value_max'];
    }
    if (is_array($_configuration['type'])) {
      return '<input type="text" data-name="' . $_name . '" class="form-control valueResult" value="' . $_value . '" />';
    }
    if (isset($_configuration['enum'])) {
      $return = '<select class="form-control valueResult" data-name="' . $_name . '">';
      foreach ($_configuration['enum'] as $enum) {
        if ($enum == $_value) {
          $return .= '<option value="' . $enum . '" selected>' . $enum . '</option>';
        } else {
          $return .= '<option value="' . $enum . '">' . $enum . '</option>';
        }
      }
      $return .= '</select>';
      return $return;
    }
    switch ($_configuration['type']) {
      case 'binary':
        $_value = ($_value != '') ? 'checked' : '';
        return '<input type="checkbox" data-name="' . $_name . '" class="form-control valueResult" ' . $_value . ' />';
      case 'boolean':
        $_value = ($_value != '') ? 'checked' : '';
        return '<input type="checkbox" data-name="' . $_name . '" class="form-control valueResult" ' . $_value . ' />';
      case 'numeric':
        return '<input type="number" data-name="' . $_name . '" class="form-control valueResult" ' . $min . ' ' . $max . ' value="' . $_value . '" />';
      case 'number':
        return '<input type="number" data-name="' . $_name . '" class="form-control valueResult" ' . $min . ' ' . $max . ' value="' . $_value . '" />';
      case 'string':
        return '<input type="text" data-name="' . $_name . '" class="form-control valueResult" value="' . $_value . '" />';
      case 'array':
        return '<input type="text" data-name="' . $_name . '" class="form-control valueResult" value="' . $_value . '" />';
      case 'list':
        return '<input type="text" data-name="' . $_name . '" class="form-control valueResult" value="' . $_value . '" />';
    }
  }

  public static function cronDaily() {
    if(config::byKey('z2m::mode', 'z2m') == 'distant'){
      return;
    }
    shell_exec("ls -1tr " . __DIR__ . "/../../data/backup/*.zip | head -n -10 | xargs -d '\n' rm -f -- >> /dev/null 2>&1");
  }

  public static function cron() {
    foreach (eqLogic::byType('z2m', true) as $eqLogic) {
      $autorefresh = $eqLogic->getConfiguration('autorefresh');
      if ($autorefresh != '') {
        try {
          $c = new Cron\CronExpression(checkAndFixCron($autorefresh), new Cron\FieldFactory);
          if ($c->isDue()) {
            $eqLogic->refreshValue();
          }
        } catch (Exception $exc) {
          log::add('z2m', 'error', __('Expression cron non valide pour ', __FILE__) . $eqLogic->getHumanName() . ' : ' . $autorefresh);
        }
      }
    }
  }

  public static function isRunning() {
    if(config::byKey('z2m::mode', 'z2m') == 'distant'){
      return true;
    }
    if (!empty(system::ps('zigbee2mqtt'))) {
      return true;
    }
    return false;
  }

  public static function deamon_info() {
    if(config::byKey('z2m::mode', 'z2m') == 'distant'){
      $return = array();
      $return['log'] = __CLASS__;
      $return['launchable'] = 'ok';
      $return['state'] = 'ok';
      return $return;
    }
    $return = array();
    $return['log'] = __CLASS__;
    $return['launchable'] = 'ok';
    $return['state'] = 'nok';
    if (self::isRunning()) {
      $return['state'] = 'ok';
    }
    $port = config::byKey('port', __CLASS__);
    if ($port == 'none') {
      $return['launchable'] = 'nok';
      $return['launchable_message'] = __("Le port n'est pas configuré", __FILE__);
    }
    if (!class_exists('mqtt2')) {
      $return['launchable'] = 'nok';
      $return['launchable_message'] = __("Le plugin MQTT Manager n'est pas installé", __FILE__);
    } else {
      if (mqtt2::deamon_info()['state'] != 'ok') {
        $return['launchable'] = 'nok';
        $return['launchable_message'] = __("Le démon MQTT Manager n'est pas démarré", __FILE__);
      }
    }
    return $return;
  }

  public static function configure_z2m_deamon() {
    if(config::byKey('z2m::mode', 'z2m') == 'distant'){
      return;
    }
    if (!class_exists('mqtt2')) {
      throw new Exception(__("Plugin Mqtt Manager (mqtt2) non installé, veuillez l'installer avant de pouvoir continuer", __FILE__));
    }
    self::postConfig_mqtt_topic();
    $mqtt = mqtt2::getFormatedInfos();
    $data_path = dirname(__FILE__) . '/../../data';
    if (!is_dir($data_path)) {
      mkdir($data_path, 0777, true);
    }
    $configuration = yaml_parse_file($data_path . '/configuration.yaml');
    $configuration['permit_join'] = false;

    $configuration['mqtt']['server'] = 'mqtt://' . $mqtt['ip'] . ':';
    $configuration['mqtt']['server'] .= (isset($mqtt['port'])) ? intval($mqtt['port']) : 1883;
    $configuration['mqtt']['user'] = $mqtt['user'];
    $configuration['mqtt']['password'] = $mqtt['password'];
    $configuration['mqtt']['base_topic'] = config::byKey('mqtt::topic', __CLASS__);
    $configuration['mqtt']['include_device_information'] = true;

    $port = config::byKey('port', 'z2m');
    if ($port == 'gateway') {
      $port = 'tcp://' . config::byKey('gateway', 'z2m');
    } else if ($port != 'auto') {
      if(jeedom::getUsbMapping($port) != null){
        $port = jeedom::getUsbMapping($port);
      }
      if($port == '/dev/ttyLuna-Zigbee' && !file_exists('/dev/ttyLuna-Zigbee')){
        $port = '/dev/ttyUSB1';
      }
      exec(system::getCmdSudo() . ' chmod 777 ' . $port . ' 2>&1');
    }else{
      $port = null;
    }
   
    $configuration['serial']['port'] = $port;
    if(isset($configuration['serial']['baudrate'])){
      unset($configuration['serial']['baudrate']);
    }
    if(config::byKey('controller', 'z2m') == 'conbee_3'){
      $configuration['serial']['adapter'] = 'deconz';
      $configuration['serial']['baudrate'] = 115200;
    }elseif(config::byKey('controller', 'z2m') == 'raspbee_2'){
      $configuration['serial']['adapter'] = 'deconz';
      $configuration['serial']['baudrate'] = 38400;
    }elseif (config::byKey('controller', 'z2m') != 'ti') {
      $configuration['serial']['adapter'] = config::byKey('controller', 'z2m');
    }else{
      $configuration['serial']['adapter'] = 'zstack';
    }

    $configuration['frontend']['port'] = intval(config::byKey('z2m_listen_port', 'z2m','8080'));
    $configuration['frontend']['host'] = '0.0.0.0';

    $configuration['advanced']['last_seen'] = 'ISO_8601';

    if(!file_exists($data_path . '/coordinator_backup.json') && !isset($configuration['advanced']['network_key']) && (!isset($configuration['devices']) || count($configuration['devices']) == 0) && !file_exists($data_path . '/database.db') && !file_exists($data_path . '/state.json')){
       $configuration['advanced']['network_key'] = 'GENERATE';
       $configuration['advanced']['pan_id'] = 'GENERATE';
       $configuration['advanced']['ext_pan_id'] = 'GENERATE';
    }

    if (config::byKey('z2m_auth_token', 'z2m', '') == '') {
      config::save('z2m_auth_token', config::genKey(32), 'z2m');
    }
    $configuration['frontend']['auth_token'] = config::byKey('z2m_auth_token', 'z2m');

    $converter_path =  dirname(__FILE__) . '/../config/converters';
    $converters = array();
    foreach (ls($converter_path, '*', false, array('folders', 'quiet')) as $folder) {
      foreach (ls($converter_path . '/' . $folder, '*.js', false, array('files', 'quiet')) as $file) {
        $converters[] = $converter_path . '/' . $folder . $file;
      }
    }
    $configuration['external_converters'] = $converters;

    if (log::convertLogLevel(log::getLogLevel('z2m')) == 'debug') {
      $configuration['advanced']['log_level'] = 'debug';
    } else if (log::convertLogLevel(log::getLogLevel('z2m')) == 'info') {
      $configuration['advanced']['log_level'] = 'info';
    } else if (log::convertLogLevel(log::getLogLevel('z2m')) == 'error' || log::convertLogLevel(log::getLogLevel('z2m')) == 'none') {
      $configuration['advanced']['log_level'] = 'error';
    }
    file_put_contents($data_path . '/configuration.yaml', yaml_emit($configuration));
  }

  public static function deamon_start() {
    if(config::byKey('z2m::mode', 'z2m') == 'distant'){
      return;
    }
    self::deamon_stop();
    self::configure_z2m_deamon();
    $data_path = dirname(__FILE__) . '/../../data';
    $deamon_info = self::deamon_info();
    if ($deamon_info['launchable'] != 'ok') {
      throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
    }
    exec(system::getCmdSudo() .' chown www-data -R "/root/.npm"');
    exec(system::getCmdSudo() .' chmod 777 -R "/root/.npm"');
    $z2m_path = realpath(dirname(__FILE__) . '/../../resources/zigbee2mqtt');
    $cmd = '';
    $cmd .= 'ZIGBEE2MQTT_DATA=' . $data_path;
    if (log::convertLogLevel(log::getLogLevel('z2m')) == 'debug') {
      //$cmd .= ' DEBUG=zigbee-herdsman*';
    }
    $cmd .= ' npm start --prefix ' . $z2m_path;
    log::add(__CLASS__, 'info', __('Démarrage du démon Z2M', __FILE__) . ' : ' . $cmd);
    exec(system::getCmdSudo() . $cmd . ' >> ' . log::getPathToLog('z2md') . ' 2>&1 &');
    $i = 0;
    while ($i < 30) {
      $deamon_info = self::deamon_info();
      if ($deamon_info['state'] == 'ok') {
        break;
      }
      sleep(1);
      $i++;
    }
    if ($i >= 30) {
      log::add(__CLASS__, 'error', __('Impossible de démarrer le démon Zigbee2mqtt, consultez les logs', __FILE__), 'unableStartDeamon');
      return false;
    }
    message::removeAll(__CLASS__, 'unableStartDeamon');
    return true;
  }

  public static function deamon_stop() {
    if(config::byKey('z2m::mode', 'z2m') == 'distant'){
      return;
    }
    log::add(__CLASS__, 'info', __('Arrêt du démon z2m', __FILE__));
    //$cmd = "(ps ax || ps w) | grep -ie 'zigbee2mqtt' | grep -v grep | awk '{print $1}' | xargs " . system::getCmdSudo() . " kill -15 > /dev/null 2>&1";
    $cmd = system::getCmdSudo() . " lsof -t -i:" . config::byKey('z2m_listen_port','z2m','8080') . " | head -n 1 | xargs " . system::getCmdSudo() . " kill -15 > /dev/null 2>&1";
    exec($cmd);
    $i = 0;
    while ($i < 5) {
      $deamon_info = self::deamon_info();
      if ($deamon_info['state'] == 'nok') {
        break;
      }
      sleep(1);
      $i++;
    }
    if ($i >= 5) {
      system::kill('zigbee2mqtt', true);
      $i = 0;
      while ($i < 5) {
        $deamon_info = self::deamon_info();
        if ($deamon_info['state'] == 'nok') {
          break;
        }
        sleep(1);
        $i++;
      }
    }
    system::fuserk(jeedom::getUsbMapping(config::byKey('port', 'z2m')));
    sleep(1);
  }

  public static function postConfig_z2m_mode($_value) {
    $plugin = plugin::byId('z2m');
    if ($_value == 'local') {
      $plugin->dependancy_changeAutoMode(1);
      $plugin->deamon_info(1);
    } else {
      $plugin->dependancy_changeAutoMode(0);
      $plugin->deamon_info(0);
    }
  }

  public static function getRootTopic() {
    return config::byKey('mqtt::topic', __CLASS__, 'z2m');
  }

  public static function convert_to_addr($_addr) {
    return implode(':', str_split(str_replace('0x', '', $_addr), 2));
  }

  public static function convert_from_addr($_addr) {
    return '0x' . str_replace(':', '', $_addr);
  }

  public static function postConfig_mqtt_topic($_value = null) {
    if (!class_exists('mqtt2')) {
      throw new Exception(__("Plugin Mqtt Manager (mqtt2) non installé, veuillez l'installer avant de pouvoir continuer", __FILE__));
    }
    if(method_exists('mqtt2','removePluginTopicByPlugin')){
       mqtt2::removePluginTopicByPlugin(__CLASS__);
    }
    mqtt2::addPluginTopic(__CLASS__, config::byKey('mqtt::topic', __CLASS__));
  }

  public static function postConfig_wanted_z2m_version($_value = null) {
    if($_value == null || trim($_value) == null){
      if(file_exists(__DIR__.'/../../data/wanted_z2m_version')){
        unlink(__DIR__.'/../../data/wanted_z2m_version');
      }
    }else{
      file_put_contents(__DIR__.'/../../data/wanted_z2m_version', $_value);
    }
  }

  
  public function findIeeeAddrRecursive($data) {
      // MQTT Manager ne transmet que les topics mis à jour donc l'appel à la recursivité n'est pas un problème
      $ret = null; // Variable pour stocker le résultat
      foreach ($data as $key => $value) {
          if (is_array($value)) { // Vérifie si la valeur est un tableau
              if(isset($value['device'])) { // Vérifie si la clé 'device' existe dans le tableau
                  log::add('z2m', 'debug', json_encode($data[$key])); // Debug Log
                  if (isset($value['device']['ieeeAddr'] )) { // Vérifie si la clé 'ieeeAddr' existe dans le sous-tableau 'device'
                      $ret =  $data[$key]; // Stocke le sous-tableau actuel dans la variable de résultat
                  }
              }
              if($ret === null) { // Si le résultat est on cherche dans plus loin dans le tableau
                  $ret = self::findIeeeAddrRecursive($value); // Appelle récursivement la fonction avec le sous-tableau actuel pour chercher dedans
              }
          }
      }
      return $ret; // Renvoie le résultat (peut être null si aucun 'ieeeAddr' n'a été trouvé)
  }
  

  public static function handleMqttMessage($_datas) {
    log::add('z2m', 'debug', json_encode($_datas));
    if (!isset($_datas['zigbee2mqtt'])) {
      return;
    }
    foreach ($_datas['zigbee2mqtt'] as $key => $values) {
      if ($key == 'bridge') {
        self::handle_bridge($values);
        continue;
      }
      if (isset($values['device'])) {
        $key = $values['device']['ieeeAddr'];
      } else{
      	// Ajout BeGood 07/08/2023
      	// On recherche IeeeAddr de l'équipement dans les topics enfants 
        $dev = self::findIeeeAddrRecursive($values);
        if ($dev !== null) {
          $key = $dev['device']['ieeeAddr'];
          $values = $dev;          
        }
      }
      $eqLogic = eqLogic::byLogicalId(self::convert_to_addr($key), 'z2m');
      if(!is_object($eqLogic)){
        $eqLogic = eqLogic::byLogicalId('group_' . $key, 'z2m');
      }
      if(!is_object($eqLogic)){
        foreach (self::byType('z2m') as $findEqLogic) {
          if($findEqLogic->getConfiguration('isgroup',0) == 0){
            continue;
          }
          if($findEqLogic->getConfiguration('friendly_name','') == $key){
            $eqLogic = $findEqLogic;
            break;
          }
        }
      }
      if (is_object($eqLogic)) {
        if(isset($values['last_seen']) && $eqLogic->getConfiguration('maxLastSeen',0) > 0 && (strtotime($values['last_seen'])+$eqLogic->getConfiguration('maxLastSeen',0)) < strtotime('now')){
          continue;
        }
        foreach ($values as $logical_id => &$value) {
          if ($value === null) {
            continue;
          }
          if ($logical_id == 'device') {
            continue;
          }
          $raw_value = $value;
          if ($logical_id == 'last_seen') {
            $value = (is_numeric($value)) ? date('Y-m-d H:i:s', intval($value) / 1000) : date('Y-m-d H:i:s', strtotime($value));
          }
          if ($logical_id == 'color' || $logical_id == 'action_color') {
            $bri = (isset($values['brightness'])) ? $values['brightness'] : 255;
            $color = z2mCmd::convertXYToRGB($value['x'], $value['y'], $bri);
            $value = sprintf("#%02x%02x%02x", $color['red'], $color['green'], $color['blue']);
          }
          log::add('z2m', 'debug', $eqLogic->getHumanName() . ' Check for update ' . $logical_id . ' => ' . json_encode($value) . ', raw : ' . json_encode($raw_value));
          $eqLogic->checkAndUpdateCmd($logical_id, $value);
          if ($eqLogic->getConfiguration('multipleEndpoints', 0) == 1) {
            $explode = explode('_', $logical_id);
            log::add('z2m', 'debug', $eqLogic->getHumanName() . ' Searching for Child' . self::convert_to_addr($key) . '|' . end($explode));
            $eqLogicChild = eqLogic::byLogicalId(self::convert_to_addr($key) . '|' . end($explode), 'z2m');
            if (is_object($eqLogicChild)) {
              log::add('z2m', 'debug', $eqLogicChild->getHumanName() . ' Updating Child' . $logical_id . ' => ' . $value);
              $eqLogicChild->checkAndUpdateCmd($logical_id, $value);
              if (explode('|', $logical_id)[0] == 'battery') {
                $eqLogicChild->batteryStatus(round($value));
              }
            }
          } else {
            if (explode('|', $logical_id)[0] == 'battery') {
              $eqLogic->batteryStatus(round($value));
            }
          }
        }
        continue;
      }
    }
  }

  public static function handle_bridge($_datas) {
    if (isset($_datas['event'])) {
      switch ($_datas['event']['type']) {
        case 'device_announce':
          $addr = self::convert_to_addr($_datas['event']['data']['ieee_address']);
          $eqLogic = eqLogic::byLogicalId($addr, 'z2m');
          if(!is_object($eqLogic)){
            event::add('jeedom::alert', array(
              'level' => 'info',
              'page' => 'z2m',
              'ttl' => 60000,
              'message' => __('Péripherique en cours d\'inclusion : ', __FILE__) . $addr,
            ));
          }
          break;
      }
    }
    if (isset($_datas['logging']) && isset($_datas['response'])) {
      switch ($_datas['logging']['level']) {
        case 'info':
          /*event::add('jeedom::alert', array(
            'level' => 'info',
            'page' => 'z2m',
            'message' => $_datas['logging']['message'],
          ));*/
          break;
        case 'error':
          log::add('z2m', 'error', __('Z2M à renvoyé une erreur : ', __FILE__) . $_datas['logging']['message']);
          break;
      }
    }
    if (isset($_datas['response']['status']) && $_datas['response']['status'] != 'ok') {
      log::add('z2m', 'error', __('Z2M à renvoyé une erreur : ', __FILE__) . json_encode($_datas['response']));
    }
    if (isset($_datas['response']['permit_join'])) {
      if ($_datas['response']['permit_join']['data']['value']) {
        event::add('jeedom::alert', array(
          'level' => 'success',
          'page' => 'z2m',
          'message' => __('Mode inclusion actif', __FILE__),
          'ttl' => $_datas['response']['permit_join']['data']['time'] * 1000
        ));
      } else {
        event::add('jeedom::alert', array(
          'level' => 'warning',
          'page' => 'z2m',
          'message' => __('Mode inclusion inactif', __FILE__),
        ));
      }
    }
    if (isset($_datas['response']['networkmap']) && $_datas['response']['networkmap']['status'] == 'ok') {
      if ($_datas['response']['networkmap']['data']['type'] == 'raw') {
        file_put_contents(__DIR__ . '/../../data/devices/networkMap1.json', json_encode($_datas['response']['networkmap']['data']['value']));
      }
    }
    if (isset($_datas['response']['backup']) && $_datas['response']['backup']['status'] == 'ok') {
      file_put_contents(__DIR__ . '/../../data/backup/' . date('Y-m-d H:i:s') . '.zip', base64_decode($_datas['response']['backup']['data']['zip']));
    }
    if (isset($_datas['info'])) {
      file_put_contents(__DIR__ . '/../../data/devices/bridge1.json', json_encode($_datas['info']));
    }
    if (isset($_datas['devices'])) {
      file_put_contents(__DIR__ . '/../../data/devices/devices1.json', json_encode($_datas['devices']));
      foreach ($_datas['devices'] as &$device) {
        if ((!isset($device['model_id']) || $device['model_id'] == '') && isset($device['definition']['model']) &&  isset($device['definition']['vendor'])) {
          $device['model_id'] = $device['definition']['vendor'].' '.$device['definition']['model'];
        }
        if ($device['type'] == 'Coordinator' || !isset($device['model_id']) || $device['model_id'] == '') {
          continue;
        }
        $new = null;
        $addr = self::convert_to_addr($device['ieee_address']);
        $eqLogic = eqLogic::byLogicalId($addr, 'z2m');
        if (!is_object($eqLogic)) {
          $eqLogic = new self();
          $eqLogic->setLogicalId($addr);
          $eqLogic->setName($device['friendly_name']);
          $eqLogic->setIsEnable(1);
          $eqLogic->setEqType_name('z2m');
          $new = true;
        }
        $eqLogic->setConfiguration('manufacturer', isset($device['manufacturer']) ? $device['manufacturer'] : '');
        $eqLogic->setConfiguration('device', $device['model_id']);
        $eqLogic->setConfiguration('device_type', $device['type']);
        if (isset($device['definition']['model'])) {
          $eqLogic->setConfiguration('model', $device['definition']['model']);
        }
        $hasCmdEndpoints = 0;
        foreach ($device['definition']['exposes'] as &$expose) {
          if (isset($expose['features'])) {
            foreach ($expose['features'] as $feature) {
              if (isset($feature['endpoint'])) {
                $hasCmdEndpoints = 1;
                break;
              }
            }
          }
          if (isset($expose['endpoint'])) {
            $hasCmdEndpoints = 1;
            break;
          }
        }
        $eqLogic->setConfiguration('multipleEndpoints', $hasCmdEndpoints);
        $eqLogic->save();

        $cmd = $eqLogic->getCmd('info', 'last_seen');
        if (!is_object($cmd)) {
          $cmd = new z2mCmd();
          $cmd->setLogicalId('last_seen');
          $cmd->setName(__('Dernière communication', __FILE__));
        }
        $cmd->setEqLogic_id($eqLogic->getId());
        $cmd->setType('info');
        $cmd->setSubType('string');
        $cmd->save();

        foreach ($device['definition']['exposes'] as &$expose) {
          if (isset($expose['features'])) {
            $type = isset($expose['type']) ? $expose['type'] : null;
            $property = isset($expose['property']) ? $expose['property'] : null;
            foreach ($expose['features'] as $feature) {
              $eqLogic->createCmd($feature, $type,$property);
            }
            continue;
          }
          if (!isset($expose['name'])) {
            continue;
          }
          $eqLogic->createCmd($expose);
        }
        file_put_contents(__DIR__ . '/../../data/devices/' . $addr . '.json', json_encode($device));
        if ($new !== null) {
          event::add('z2m::includeDevice', $eqLogic->getId());
        }
      }
    }
    if (isset($_datas['groups'])) {
      file_put_contents(__DIR__ . '/../../data/devices/groups1.json', json_encode($_datas['groups']));
      foreach ($_datas['groups'] as $group) {
        $new = null;
        $eqLogic = eqLogic::byLogicalId('group_' . $group['id'], 'z2m');
        if (!is_object($eqLogic)) {
          $eqLogic = new self();
          $eqLogic->setLogicalId('group_' . $group['id']);
          $eqLogic->setName($group['friendly_name']);
          $eqLogic->setIsEnable(1);
          $eqLogic->setEqType_name('z2m');
          $eqLogic->setConfiguration('device', 'group');
          $eqLogic->setConfiguration('isgroup', 1);
          $new = true;
        }
        $eqLogic->setConfiguration('friendly_name', $group['friendly_name']);
        $eqLogic->setConfiguration('group_id', $group['id']);
        $eqLogic->save();
        foreach ($group['scenes'] as $scene) {
          $cmd = $eqLogic->getCmd('action', 'scene_recall::' . $scene['id']);
          if (!is_object($cmd)) {
            $cmd = new z2mCmd();
            $cmd->setLogicalId('scene_recall::' . $scene['id']);
            $cmd->setName($scene['name']);
          }
          $cmd->setEqLogic_id($eqLogic->getId());
          $cmd->setType('action');
          $cmd->setSubType('other');
           try {
            $cmd->save();
          } catch (Exception $e) {
            $cmd->setName($cmd->getName().' '.config::genKey(4));
            $cmd->save();
          }
        }
        file_put_contents(__DIR__ . '/../../data/devices/group_' . $group['id'] . '.json', json_encode($group));
        if ($new !== null) {
          event::add('z2m::includeDevice', $eqLogic->getId());
        }
      }
    }
  }

  public static function getDeviceInfo($_device) {
    $file = __DIR__ . '/../../data/devices/' . $_device . '.json';
    if (!file_exists($file)) {
      return array();
    }
    return json_decode(file_get_contents($file), true);
  }

  public static function ciGlob($pat) {
    $p = '';
    for ($x = 0; $x < strlen($pat); $x++) {
      $c = substr($pat, $x, 1);
      if (preg_match("/[^A-Za-z]/", $c)) {
        $p .= $c;
        continue;
      }
      $a = strtolower($c);
      $b = strtoupper($c);
      $p .= "[{$a}{$b}]";
    }
    return $p;
  }

  public function childCreate($_endpoint) {
    log::add('z2m', 'debug', 'Child Create For : ' . $_endpoint);
    $ieee = $this->getLogicalId();
    $eqLogic = self::byLogicalId($ieee . '|l' . $_endpoint, 'z2m');
    if (!is_object($eqLogic)) {
      $eqLogic = new self();
      $eqLogic->setLogicalId($ieee . '|l' . $_endpoint);
      $eqLogic->setName($this->getName() . '-EP' . $_endpoint);
      $eqLogic->setIsEnable(1);
      $eqLogic->setEqType_name('z2m');
      $eqLogic->setConfiguration('manufacturer', $this->getConfiguration('manufacturer'));
      $eqLogic->setConfiguration('device', $this->getConfiguration('device'));
      $eqLogic->setConfiguration('model', $this->getConfiguration('model'));
      $eqLogic->setConfiguration('multipleEndpoints', 0);
      $eqLogic->setConfiguration('isChild', 1);
      $eqLogic->save();
    }
    $cmd_link = array();
    foreach ($this->getCmd() as $sourceCmd) {
      if ($sourceCmd->getConfiguration('endpoint', 'l0') == 'l' . $_endpoint) {
        $cmdCopy = $eqLogic->getCmd(null, $sourceCmd->getLogicalId());
        if (!is_object($cmdCopy)) {
          $cmdCopy = clone $sourceCmd;
          $cmdCopy->setId('');
          $cmdCopy->setName(str_replace(' l' . $_endpoint, '', $sourceCmd->getName()) . ' ' . $_endpoint);
          $cmdCopy->setEqLogic_id($eqLogic->getId());
          $cmdCopy->save();
          $cmd_link[$sourceCmd->getId()] = $cmdCopy;
        }
      }
    }
    foreach (($this->getCmd()) as $cmd) {
      if (!isset($cmd_link[$cmd->getId()])) {
        continue;
      }
      if ($cmd->getValue() != '' && isset($cmd_link[$cmd->getValue()])) {
        $cmd_link[$cmd->getId()]->setValue($cmd_link[$cmd->getValue()]->getId());
        $cmd_link[$cmd->getId()]->save();
      }
    }
  }

  public function getImgFilePath() {
    $model = str_replace(array('/', ' '), array('-', '-'), $this->getConfiguration('model'));
    if ($this->getConfiguration('isgroup', 0) == 1) {
      return 'plugins/z2m/plugin_info/z2m_icon.png';
    }
    if ($this->getConfiguration('model') == '') {
      return 'plugins/z2m/plugin_info/z2m_icon.png';
    }
    $filename = __DIR__ . '/../../data/img/' . $model . '.jpg';
    if (!file_exists($filename) || filesize($filename) == 0) {
      if (file_exists($filename)) {
        unlink($filename);
      }
      file_put_contents($filename, file_get_contents('https://www.zigbee2mqtt.io/images/devices/' . $model . '.jpg'));
    }
    if (!file_exists($filename)) {
      return 'plugins/z2m/plugin_info/z2m_icon.png';
    }
    return 'plugins/z2m/data/img/' . $model . '.jpg';
  }

  public static function getCmdConf($_infos, $_suffix = null, $_preffix = null,$_father_property = null) {
    if ($_infos['type'] == 'composite' && $_infos['name'] == 'color_xy') {
      return null;
    }
    if (self::$_cmd_converter == null) {
      self::$_cmd_converter = json_decode(file_get_contents(__DIR__ . '/../config/cmd.json'), true);
    }
    $cmd_ref = array();
    $suffix = ($_suffix == null) ? '' : '::' . strtolower($_suffix);
    $preffix = ($_preffix == null) ? '' : strtolower($_preffix) . '::';
    if ($_father_property != null && isset(self::$_cmd_converter[$_father_property.'::'.$preffix . $_infos['name'] . $suffix])) {
      $cmd_ref = self::$_cmd_converter[$preffix . $_infos['name'] . $suffix];
    }else if (isset(self::$_cmd_converter[$preffix . $_infos['name'] . $suffix])) {
      $cmd_ref = self::$_cmd_converter[$preffix . $_infos['name'] . $suffix];
    }
    if (!isset($cmd_ref['name'])) {
      $cmd_ref['name'] = ($_suffix == null) ? $_infos['name'] : $_infos['name'] . ' ' . $_suffix;
      $cmd_ref['name'] = ($_father_property == null) ? $cmd_ref['name'] : $_father_property. ' '. $cmd_ref['name'];
    }
    if (isset($_infos['endpoint'])) {
      $cmd_ref['name'] .= ' ' . $_infos['endpoint'];
    }
    if (!isset($cmd_ref['configuration'])) {
      $cmd_ref['configuration'] = array();
    }
    if (!isset($cmd_ref['configuration']['maxValue']) && isset($_infos['value_max'])) {
      $cmd_ref['configuration']['maxValue'] =  $_infos['value_max'];
    }
    if (!isset($cmd_ref['configuration']['minValue']) && isset($_infos['value_min'])) {
      $cmd_ref['configuration']['minValue'] =  $_infos['value_min'];
    }
    if (!isset($cmd_ref['unite']) && isset($_infos['unit'])) {
      $cmd_ref['unite'] = $_infos['unit'];
    }
    if (!isset($cmd_ref['type'])) {
      $cmd_ref['type'] = 'info';
    }
    if (!isset($cmd_ref['subType'])) {
      switch ($_infos['type']) {
        case 'enum':
          $cmd_ref['subType'] = 'string';
          break;
        case 'text':
          $cmd_ref['subType'] = 'string';
          break;
        default:
          $cmd_ref['subType'] = $_infos['type'];
          break;
      }
      if($cmd_ref['type'] == 'info' && $cmd_ref['subType'] == 'list'){
        $cmd_ref['subType'] = 'string';
      }
    }
    return $cmd_ref;
  }

  /*     * *********************Methode d'instance************************* */
   public function createCmd($_infos, $_type = null,$_father_property = null) {
    $link_cmd_id = null;
    $logical = '';
    if($_father_property != null){
      $logical .=  $_father_property . '::';
    }
    $logical .= $_infos['property'];
    if (isset($_infos['endpoint']) && strpos(strtolower($logical), strtolower($_infos['endpoint'])) === false) {
      $logical .= '_' . $_infos['endpoint'];
    }
    if ($_infos['access'] != 2 && $_infos['access'] != 4 && $_infos['access'] != 6) {
      $cmd_ref = self::getCmdConf($_infos, $_father_property, $_type);
      if (is_array($cmd_ref) && count($cmd_ref) > 0) {
        $cmd = $this->getCmd('info', $logical);
        if (!is_object($cmd)) {
          $cmd = new z2mCmd();
          $cmd->setLogicalId($logical);
          utils::a2o($cmd, $cmd_ref);
        }
        if (isset($_infos['endpoint'])) {
          $cmd->setConfiguration('endpoint', $_infos['endpoint']);
        }
        $cmd->setEqLogic_id($this->getId());
        try {
          $cmd->save();
        } catch (\Throwable $th) { 
          try {
            $cmd->setName($logical);
            $cmd->save();
          } catch (\Throwable $th) {
            log::add('z2m', 'debug', '[createCmd] Can not create cmd ' . json_encode(utils::o2a($cmd)) . ' => ' . $th->getMessage());
          }
        }
        $link_cmd_id = $cmd->getId();
      }
    }

    if ($_infos['access'] == 7 || $_infos['access'] == 3 || $_infos['access'] == 2 || $_infos['access'] == 6) {
      foreach (self::$_action_cmd as $k => $v) {
        if (isset($_infos[$k])) {
          if ($_infos[$k] === false) {
            $logical_id =  $logical . '::false';
          } else  if ($_infos[$k] === true) {
            $logical_id =  $logical . '::true';
          } else {
            $logical_id =  $logical . '::' . $_infos[$k];
          }
          $cmd_ref = self::getCmdConf($_infos, $v, $_type, $_father_property);
          $cmd_ref['type'] = 'action';
          $cmd_ref['subType'] = 'other';
          $cmd = $this->getCmd('action', $logical_id);

          if (!is_object($cmd)) {
            $cmd = new z2mCmd();
            if (isset($_infos['endpoint'])) {
              $cmd->setConfiguration('endpoint', $_infos['endpoint']);
            }
            $cmd->setLogicalId($logical_id);
            utils::a2o($cmd, $cmd_ref);
          }
          $cmd->setEqLogic_id($this->getId());
          $cmd->setValue($link_cmd_id);
          try {
            $cmd->save();
          } catch (\Throwable $th) {
            try {
              $cmd->setName('Action '.$logical);
              $cmd->save();
            } catch (\Throwable $th) {
              log::add('z2m', 'debug', '[createCmd] Can not create cmd ' . json_encode(utils::o2a($cmd)) . ' => ' . $th->getMessage());
            }
          }
        }
      }

      if ($_infos['type'] == 'numeric') {
        $cmd_ref = self::getCmdConf($_infos,'slider', $_type, $_father_property);
        $cmd_ref['type'] = 'action';
        $cmd_ref['subType'] = 'slider';
        $cmd = $this->getCmd('action', $logical . '::#slider#');
        if (!is_object($cmd)) {
          $cmd = new z2mCmd();
          if (isset($_infos['endpoint'])) {
            $cmd->setConfiguration('endpoint', $_infos['endpoint']);
          }
          $cmd->setLogicalId($logical  . '::#slider#');
          utils::a2o($cmd, $cmd_ref);
        }
        $cmd->setEqLogic_id($this->getId());
        $cmd->setValue($link_cmd_id);
        try {
          $cmd->save();
        } catch (\Throwable $th) {
          try {
            $cmd->setName('Action '.$logical);
            $cmd->save();
          } catch (\Throwable $th) {
            log::add('z2m', 'debug', '[createCmd] Can not create cmd ' . json_encode(utils::o2a($cmd)) . ' => ' . $th->getMessage());
          }
        }
      }

      if ($_infos['type'] == 'enum') {
        foreach ($_infos['values'] as $enum) {
          $cmd_ref = self::getCmdConf($_infos, $enum, $_type, $_father_property);
          $cmd_ref['type'] = 'action';
          $cmd_ref['subType'] = 'other';
          $cmd = $this->getCmd('action', $logical . '::' . $enum);
          if (!is_object($cmd)) {
            $cmd = new z2mCmd();
            if (isset($_infos['endpoint'])) {
              $cmd->setConfiguration('endpoint', $_infos['endpoint']);
            }
            $cmd->setLogicalId($logical . '::' . $enum);
            utils::a2o($cmd, $cmd_ref);
          }
          $cmd->setEqLogic_id($this->getId());
          $cmd->setValue($link_cmd_id);
          try {
            $cmd->save();
          } catch (\Throwable $th) {
            try {
              $cmd->setName('Action '.$logical);
              $cmd->save();
            } catch (\Throwable $th) {
              log::add('z2m', 'debug', '[createCmd] Can not create cmd ' . json_encode(utils::o2a($cmd)) . ' => ' . $th->getMessage());
            }
          }
        }
      }
    }
    if ($_infos['type'] == 'composite') {
      switch ($_infos['name']) {
        case 'color_xy':
          $info_color_id = null;
          $cmd = $this->getCmd('info', 'color');
          if (!is_object($cmd)) {
            $cmd = new z2mCmd();
            $cmd->setName('Couleur état');
            if (isset($_infos['endpoint'])) {
              $cmd->setConfiguration('endpoint', $_infos['endpoint']);
              $cmd->setName('Couleur état ' . $_infos['endpoint']);
            }
            $cmd->setLogicalId('color');
          }
          $cmd->setType('info');
          $cmd->setSubType('string');
          $cmd->setconfiguration('color_mode', 'xy');
          $cmd->setGeneric_type('LIGHT_COLOR');
          $cmd->setEqLogic_id($this->getId());
          try {
            $cmd->save();
            $info_color_id = $cmd->getId();
          } catch (\Throwable $th) {
            log::add('z2m', 'debug', '[createCmd] Can not create cmd ' . json_encode(utils::o2a($cmd)) . ' => ' . $th->getMessage());
          }
          $cmd = $this->getCmd('action', $logical);
          if (!is_object($cmd)) {
            $cmd = new z2mCmd();
            $cmd->setName('Couleur');
            if (isset($_infos['endpoint'])) {
              $cmd->setConfiguration('endpoint', $_infos['endpoint']);
              $cmd->setName('Couleur ' . $_infos['endpoint']);
            }
            $cmd->setLogicalId($logical);
          }
          $cmd->setType('action');
          $cmd->setSubType('color');
          $cmd->setGeneric_type('LIGHT_SET_COLOR');
          $cmd->setconfiguration('color_mode', 'xy');
          $cmd->setEqLogic_id($this->getId());
          $cmd->setValue($info_color_id);
          try {
            $cmd->save();
          } catch (\Throwable $th) {
            try {
              $cmd->setName('Action '.$logical);
              $cmd->save();
            } catch (\Throwable $th) {
              log::add('z2m', 'debug', '[createCmd] Can not create cmd ' . json_encode(utils::o2a($cmd)) . ' => ' . $th->getMessage());
            }
          }
          break;
      }
    }
  }

  public function preRemove() {
    if ($this->getConfiguration('isgroup', 0) == 1) {
      $datas = array(
        'id' => $this->getConfiguration('group_id'),
        'force' => true
      );
      if (!class_exists('mqtt2')) {
        throw new Exception(__("Plugin Mqtt Manager (mqtt2) non installé, veuillez l'installer avant de pouvoir continuer", __FILE__));
      }
      mqtt2::publish(z2m::getRootTopic() . '/bridge/request/group/remove', json_encode($datas));
    }
  }

  public function refreshValue(){
      foreach($this->getCmd('info') as $cmd){
          $logicalId =explode('::',$cmd->getLogicalId())[0];
          if(in_array($logicalId,array('linkquality','last_seen'))){
            	continue; 
          }
          $datas = array($logicalId => '');
          log::add('z2m','debug','[execute] '.z2m::getRootTopic() . '/' . z2m::convert_from_addr(explode('|', $this->getLogicalId())[0]) . '/get => '.json_encode($datas));
          mqtt2::publish(z2m::getRootTopic() . '/' . z2m::convert_from_addr(explode('|', $this->getLogicalId())[0]) . '/get', json_encode($datas));
      }
  }

  /*     * **********************Getteur Setteur*************************** */
}

class z2mCmd extends cmd {
  /*     * *************************Attributs****************************** */

  /*     * ***********************Methode static*************************** */

  public static function convertRGBToXY($red, $green, $blue) {
    $normalizedToOne['red'] = $red / 255;
    $normalizedToOne['green'] = $green / 255;
    $normalizedToOne['blue'] = $blue / 255;
    foreach ($normalizedToOne as $key => $normalized) {
      if ($normalized > 0.04045) {
        $color[$key] = pow(($normalized + 0.055) / (1.0 + 0.055), 2.4);
      } else {
        $color[$key] = $normalized / 12.92;
      }
    }
    $xyz['x'] = $color['red'] * 0.664511 + $color['green'] * 0.154324 + $color['blue'] * 0.162028;
    $xyz['y'] = $color['red'] * 0.283881 + $color['green'] * 0.668433 + $color['blue'] * 0.047685;
    $xyz['z'] = $color['red'] * 0.000000 + $color['green'] * 0.072310 + $color['blue'] * 0.986039;
    if (array_sum($xyz) == 0) {
      $x = 0;
      $y = 0;
    } else {
      $x = $xyz['x'] / array_sum($xyz);
      $y = $xyz['y'] / array_sum($xyz);
    }
    return array(
      'x' => $x,
      'y' => $y,
      'bri' => round($xyz['y'] * 255),
    );
  }

  public static function convertXYToRGB($x, $y, $bri = 255) {
    $z = 1.0 - $x - $y;
    $xyz['y'] = $bri / 255;
    $xyz['x'] = ($xyz['y'] / $y) * $x;
    $xyz['z'] = ($xyz['y'] / $y) * $z;
    $color['red'] = $xyz['x'] * 1.656492 - $xyz['y'] * 0.354851 - $xyz['z'] * 0.255038;
    $color['green'] = -$xyz['x'] * 0.707196 + $xyz['y'] * 1.655397 + $xyz['z'] * 0.036152;
    $color['blue'] = $xyz['x'] * 0.051713 - $xyz['y'] * 0.121364 + $xyz['z'] * 1.011530;
    $maxValue = 0;
    foreach ($color as $key => $normalized) {
      if ($normalized <= 0.0031308) {
        $color[$key] = 12.92 * $normalized;
      } else {
        $color[$key] = (1.0 + 0.055) * pow($normalized, 1.0 / 2.4) - 0.055;
      }
      $color[$key] = max(0, $color[$key]);
      if ($maxValue < $color[$key]) {
        $maxValue = $color[$key];
      }
    }
    foreach ($color as $key => $normalized) {
      if ($maxValue > 1) {
        $color[$key] /= $maxValue;
      }
      $color[$key] = round($color[$key] * 255);
    }
    return $color;
  }


  /*     * *********************Methode d'instance************************* */

  public function preSave(){
    if(version_compare(jeedom::version(), '4.4.2') < 0){
      $logicalId = $this->getLogicalId();
      if(strlen($logicalId) > 254){
        if(strpos($logicalId,'%') === false){
          $this->setConfiguration('logicalId',$logicalId);
          $this->setLogicalId(substr($logicalId,0,254).'%');
        }
      }else{
        $this->setConfiguration('logicalId',null);
      }
    }
  }

  // Exécution d'une commande
  public function execute($_options = array()) {
    $eqLogic = $this->getEqLogic();
    if($this->getLogicalId() == 'refresh'){
      $eqLogic->refreshValue();
      return;
    }
    $replace = array();
    switch ($this->getSubType()) {
      case 'slider':
        $replace['#slider#'] = round(floatval($_options['slider']), 2);
        break;
      case 'color':
        list($r, $g, $b) = str_split(str_replace('#', '', $_options['color']), 2);
        $info = self::convertRGBToXY(hexdec($r), hexdec($g), hexdec($b));
        $color = array('x' => $info['x'], 'y' => $info['y']);
        break;
      case 'select':
        $replace['#select#'] = $_options['select'];
        break;
      case 'message':
        $replace['#title#'] = $_options['title'];
        $replace['#message#'] = $_options['message'];
        if ($_options['message'] == '' && $_options['title'] == '') {
          throw new Exception(__('Le message et le sujet ne peuvent pas être vide', __FILE__));
        }
        break;
    }
    if(version_compare(jeedom::version(), '4.4.2') < 0){
      $logicalId = $this->getConfiguration('logicalId',$this->getLogicalId());
    }else{
      $logicalId = $this->getLogicalId();
    }
   
    $subTopic = $this->getConfiguration('subPayload');
    if(strpos($logicalId,'json::') === 0){
      $infos = json_decode(str_replace('json::','',$logicalId));
    }else{
      $infos = explode('::', str_replace(array_keys($replace), $replace, $logicalId));
      foreach($infos as &$info){
        if ($info == 'true') {
            $info = true;
          } else if ($info == 'false') {
            $info = false;
          }elseif(is_numeric($info)){
            $info = floatval($info);
          }
      }
    }
    if ($this->getSubtype() == 'color' && isset($color)) {
      $datas = array('color' =>  $color);
    } else {
      if(strpos($logicalId,'json::') !== 0){
        if(count($infos) == 3){
          $datas = array($infos[0] => array($infos[1] =>  $infos[2]));
        }else{
          $datas = array($infos[0] =>  $infos[1]);
        }
      }
    }
    if(isset($datas['position'])){
      $datas['position'] = round(floatval($datas['position']), 2);
    }
    if ($eqLogic->getConfiguration('isgroup', 0) == 1) {
      log::add('z2m','debug','[execute] '.z2m::getRootTopic() . '/' . $eqLogic->getConfiguration('friendly_name') . '/set'.$subTopic.' => '.json_encode($datas));
      mqtt2::publish(z2m::getRootTopic() . '/' . $eqLogic->getConfiguration('friendly_name') . '/set'.$subTopic, json_encode($datas));
      return;
    }
    log::add('z2m','debug','[execute] '.z2m::getRootTopic() . '/' . z2m::convert_from_addr(explode('|', $eqLogic->getLogicalId())[0]) . '/set'.$subTopic.' => '.json_encode($datas));
    mqtt2::publish(z2m::getRootTopic() . '/' . z2m::convert_from_addr(explode('|', $eqLogic->getLogicalId())[0]) . '/set'.$subTopic, json_encode($datas));
  }

  /*     * **********************Getteur Setteur*************************** */
}

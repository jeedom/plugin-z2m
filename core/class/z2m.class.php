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
    shell_exec("ls -1tr " . __DIR__ . "/../../data/backup/*.zip | head -n -10 | xargs -d '\n' rm -f --");
  }

  public static function isRunning() {
    if (!empty(system::ps('zigbee2mqtt'))) {
      return true;
    }
    return false;
  }

  public static function deamon_info() {
    $return = array();
    $return['log'] = __CLASS__;
    $return['launchable'] = 'ok';
    $return['state'] = 'nok';
    if (self::isRunning()) {
      $return['state'] = 'ok';
    }
    $port = config::byKey('port', __CLASS__);
    $port = jeedom::getUsbMapping($port);
    if (@!file_exists($port)) {
      $return['launchable'] = 'nok';
      $return['launchable_message'] = __('Le port n\'est pas configuré', __FILE__);
    }
    if (!class_exists('mqtt2')) {
      $return['launchable'] = 'nok';
      $return['launchable_message'] = __('Le plugin MQTT Manager n\'est pas installé', __FILE__);
    } else {
      if (mqtt2::deamon_info()['state'] != 'ok') {
        $return['launchable'] = 'nok';
        $return['launchable_message'] = __('Le démon MQTT Manager n\'est pas démarré', __FILE__);
      }
    }
    return $return;
  }

  public static function configure_z2m_deamon() {
    self::postConfig_mqtt_topic();
    $mqtt = mqtt2::getFormatedInfos();
    $z2m_path = realpath(dirname(__FILE__) . '/../../resources/zigbee2mqtt');
    $configuration = yaml_parse_file($z2m_path . '/data/configuration.yaml');
    $configuration['permit_join'] = false;

    $configuration['mqtt']['server'] = 'mqtt://' . $mqtt['ip'];
    $configuration['mqtt']['port'] = (isset($mqtt['port'])) ? intval($mqtt['port']) : 1883;
    $configuration['mqtt']['user'] = $mqtt['user'];
    $configuration['mqtt']['password'] = $mqtt['password'];
    $configuration['mqtt']['base_topic'] = config::byKey('mqtt::topic', __CLASS__, 'z2m');

    $configuration['serial']['port'] = jeedom::getUsbMapping(config::byKey('port', 'z2m'));

    exec(system::getCmdSudo() . ' chmod 777 ' . $configuration['serial']['port'] . ' 2>&1');

    if (config::byKey('controller', 'z2m') != 'ti') {
      $configuration['serial']['adapter'] = config::byKey('controller', 'z2m');
    }

    $configuration['frontend']['port'] = 8080;
    $configuration['frontend']['host'] = '0.0.0.0';

    if (config::byKey('z2m_auth_token', 'z2m', '') == '') {
      config::save('z2m_auth_token', config::genKey(32), 'z2m');
    }
    $configuration['frontend']['auth_token'] = config::byKey('z2m_auth_token', 'z2m');

    file_put_contents($z2m_path . '/data/configuration.yaml', yaml_emit($configuration));
  }

  public static function deamon_start() {
    self::deamon_stop();
    self::configure_z2m_deamon();
    $deamon_info = self::deamon_info();
    if ($deamon_info['launchable'] != 'ok') {
      throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
    }
    $z2m_path = realpath(dirname(__FILE__) . '/../../resources/zigbee2mqtt');
    $cmd = 'npm start --prefix ' . $z2m_path;
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
    log::add(__CLASS__, 'info', __('Arrêt du démon z2m', __FILE__));
    $cmd = "(ps ax || ps w) | grep -ie 'zigbee2mqtt' | grep -v grep | awk '{print $1}' | xargs " . system::getCmdSudo() . " kill -15 > /dev/null 2>&1";
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

  public static function getInstanceTopic($_instanceNumber = 1) {
    return config::byKey('mqtt::topic', __CLASS__, 'z2m');
  }

  public static function convert_to_addr($_addr) {
    return implode(':', str_split(str_replace('0x', '', $_addr), 2));
  }

  public static function convert_from_addr($_addr) {
    return '0x' . str_replace(':', '', $_addr);
  }

  public static function postConfig_mqtt_topic($_value = null) {
    mqtt2::addPluginTopic(__CLASS__, config::byKey('mqtt::topic', __CLASS__, 'z2m'));
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
      $eqLogic = eqLogic::byLogicalId(self::convert_to_addr($key), 'z2m');
      if (is_object($eqLogic)) {
        foreach ($values as $logical_id => &$value) {
          if ($value === null) {
            continue;
          }
          log::add('z2m', 'debug', $eqLogic->getHumanName() . ' Check for update ' . $logical_id . ' => ' . json_encode($value));
          if ($logical_id == 'last_seen') {
            $value = date('Y-m-d H:i:s', $value / 1000);
          }
          $eqLogic->checkAndUpdateCmd($logical_id, $value);
          if ($eqLogic->getConfiguration('multipleEndpoints',0) == 1){
            $explode = explode('_',$logical_id);
            $eqLogicChild = eqLogic::byLogicalId(self::convert_to_addr($key).'|'.end($explode), 'z2m');
            if (is_object($eqLogicChild)) {
              log::add('z2m', 'debug', $eqLogicChild->getHumanName() . ' Updating Child' . $logical_id . ' => ' . $value);
              $eqLogicChild->checkAndUpdateCmd($logical_id, $value);
            }
          }
        }
        continue;
      }
    }
  }

  public static function handle_bridge($_datas, $_instanceNumber = 1) {
    if (isset($_datas['event'])) {
      switch ($_datas['event']['type']) {
        case 'device_announce':
          event::add('jeedom::alert', array(
            'level' => 'info',
            'page' => 'z2m',
            'ttl' => 60000,
            'message' => __('Péripherique en cours d\'inclusion : ', __FILE__) . self::convert_to_addr($_datas['event']['data']['ieee_address']),
          ));
          break;
      }
    }
    if (isset($_datas['logging']) && isset($_datas['response'])) {
      switch ($_datas['logging']['level']) {
        case 'info':
          event::add('jeedom::alert', array(
            'level' => 'info',
            'page' => 'z2m',
            'message' => $_datas['logging']['message'],
          ));
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
        file_put_contents(__DIR__ . '/../../data/devices/networkMap' . $_instanceNumber . '.json', json_encode($_datas['response']['networkmap']['data']['value']));
      }
    }
    if (isset($_datas['response']['backup']) && $_datas['response']['backup']['status'] == 'ok') {
      file_put_contents(__DIR__ . '/../../data/backup/' . date('Y-m-d H:i:s') . '.zip', base64_decode($_datas['response']['backup']['data']['zip']));
    }
    if (isset($_datas['info'])) {
      file_put_contents(__DIR__ . '/../../data/devices/bridge' . $_instanceNumber . '.json', json_encode($_datas['info']));
    }
    if (isset($_datas['devices'])) {
      file_put_contents(__DIR__ . '/../../data/devices/devices' . $_instanceNumber . '.json', json_encode($_datas['devices']));
      foreach ($_datas['devices'] as $device) {
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
        $eqLogic->setConfiguration('manufacturer', $device['manufacturer']);
        $eqLogic->setConfiguration('device', $device['model_id']);
        if (isset($device['definition']['model'])) {
          $eqLogic->setConfiguration('model', $device['definition']['model']);
        }
        $eqLogic->setConfiguration('instance', $_instanceNumber);
        if (isset($device['endpoints']) && count(array_keys($device['endpoints']))>1){
          $eqLogic->setConfiguration('multipleEndpoints', 1);
        } else {
          $eqLogic->setConfiguration('multipleEndpoints', 0);
        }
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
            foreach ($expose['features'] as $feature) {
              $eqLogic->createCmd($feature, $type);
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
      file_put_contents(__DIR__ . '/../../data/devices/groups' . $_instanceNumber . '.json', json_encode($_datas['groups']));
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
        $eqLogic->setConfiguration('instance', $_instanceNumber);
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
          $cmd->save();
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


  public static function getDeamonInstanceDef() {
    $return = array();
    for ($i = 1; $i <= config::byKey('max_instance_number', 'z2m', 1); $i++) {
      $return[$i] = array(
        'id' => $i,
        'enable' => config::byKey('enable_deamon_' . $i, 'z2m', 1),
        'name' => config::byKey('name_deamon_' . $i, 'z2m', __('Démon', __FILE__) . ' ' . $i)
      );
    }
    return $return;
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
      $cmd_link = array();
      foreach($this->getCmd() as $sourceCmd){
        if ($sourceCmd->getConfiguration('endpoint','l0') == 'l'.$_endpoint){
            $cmdCopy = clone $sourceCmd;
            $cmdCopy->setId('');
            $cmdCopy->setName(str_replace(' l'.$_endpoint,'',$sourceCmd->getName()));
            $cmdCopy->setEqLogic_id($eqLogic->getId());
            $cmdCopy->save();
            $cmd_link[$sourceCmd->getId()] = $cmdCopy;
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
  }

  public function getImgFilePath() {
    if ($this->getConfiguration('isgroup', 0) == 1) {
      return 'plugins/z2m/plugin_info/z2m_icon.png';
    }
    if ($this->getConfiguration('model') == '') {
      return 'plugins/z2m/plugin_info/z2m_icon.png';
    }
    $filename = __DIR__ . '/../../data/img/' . $this->getConfiguration('model') . '.jpg';
    if (!file_exists($filename)) {
      file_put_contents($filename, file_get_contents('https://www.zigbee2mqtt.io/images/devices/' . $this->getConfiguration('model') . '.jpg'));
    }
    if (!file_exists($filename)) {
      return 'plugins/z2m/plugin_info/z2m_icon.png';
    }
    return 'plugins/z2m/data/img/' . $this->getConfiguration('model') . '.jpg';
  }

  public static function getCmdConf($_infos, $_suffix = null, $_preffix = null) {
    if (self::$_cmd_converter == null) {
      self::$_cmd_converter = json_decode(file_get_contents(__DIR__ . '/../config/cmd.json'), true);
    }
    $cmd_ref = array();
    $suffix = ($_suffix == null) ? '' : '::' . strtolower($_suffix);
    $preffix = ($_preffix == null) ? '' : strtolower($_preffix) . '::';
    if (isset(self::$_cmd_converter[$preffix . $_infos['name'] . $suffix])) {
      $cmd_ref = self::$_cmd_converter[$preffix . $_infos['name'] . $suffix];
    }
    if (!isset($cmd_ref['name'])) {
      $cmd_ref['name'] = ($_suffix == null) ? $_infos['name'] : $_infos['name'] . ' ' . $_suffix;
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
      $cmd_ref['subType'] = ($_infos['type'] == 'enum') ? 'string' : $_infos['type'];
    }
    return $cmd_ref;
  }

  /*     * *********************Methode d'instance************************* */
   public function createCmd($_infos, $_type = null) {
    $cmd_ref = self::getCmdConf($_infos, null, $_type);
    $logical = $_infos['name'];
    if (isset($_infos['endpoint'])){
        $logical.='_'.$_infos['endpoint'];
    }
    $cmd = $this->getCmd('info', $logical);
    if (!is_object($cmd)) {
      $cmd = new z2mCmd();
      $cmd->setLogicalId($logical);
      utils::a2o($cmd, $cmd_ref);
    }
    if (isset($_infos['endpoint'])){
      $cmd->setConfiguration('endpoint',$_infos['endpoint']);
    }
    $cmd->setEqLogic_id($this->getId());
    $cmd->save();
    $link_cmd_id = $cmd->getId();

    if ($_infos['access'] == 7 || $_infos['access'] == 3) {
      foreach (self::$_action_cmd as $k => $v) {
        if (isset($_infos[$k])) {
          if ($_infos[$k] === false) {
            $logical_id =  $logical . '::false';
          } else  if ($_infos[$k] === true) {
            $logical_id =  $logical . '::true';
          } else {
            $logical_id =  $logical . '::' . $_infos[$k];
          }
          $cmd_ref = self::getCmdConf($_infos, $v, $_type);
          $cmd_ref['type'] = 'action';
          $cmd_ref['subType'] = 'other';
          $cmd = $this->getCmd('action', $logical_id);
          if (!is_object($cmd)) {
            $cmd = new z2mCmd();
            if (isset($_infos['endpoint'])){
              $cmd->setConfiguration('endpoint',$_infos['endpoint']);
            }
            $cmd->setLogicalId($logical_id);
            utils::a2o($cmd, $cmd_ref);
          }
          $cmd->setEqLogic_id($this->getId());
          $cmd->setValue($link_cmd_id);
          $cmd->save();
        }
      }

      if ($_infos['type'] == 'numeric') {
        $cmd_ref = self::getCmdConf($_infos, 'slider', $_type);
        $cmd_ref['type'] = 'action';
        $cmd_ref['subType'] = 'slider';
        $cmd = $this->getCmd('action', $logical . '::#slider#');
        if (!is_object($cmd)) {
          $cmd = new z2mCmd();
          if (isset($_infos['endpoint'])){
            $cmd->setConfiguration('endpoint',$_infos['endpoint']);
          }
          $cmd->setLogicalId($logical  . '::#slider#');
          utils::a2o($cmd, $cmd_ref);
        }
        $cmd->setEqLogic_id($this->getId());
        $cmd->setValue($link_cmd_id);
        $cmd->save();
      }

      if ($_infos['type'] == 'enum') {
        foreach ($_infos['values'] as $enum) {
          $cmd_ref = self::getCmdConf($_infos, $enum, $_type);
          $cmd_ref['type'] = 'action';
          $cmd_ref['subType'] = 'other';
          $cmd = $this->getCmd('action', $logical . '::' . $enum);
          if (!is_object($cmd)) {
            $cmd = new z2mCmd();
            if (isset($_infos['endpoint'])){
              $cmd->setConfiguration('endpoint',$_infos['endpoint']);
            }
            $cmd->setLogicalId($logical . '::' . $enum);
            utils::a2o($cmd, $cmd_ref);
          }
          $cmd->setEqLogic_id($this->getId());
          $cmd->setValue($link_cmd_id);
          $cmd->save();
        }
      }
    }
  }

  public function preRemove() {
    if ($this->getConfiguration('isgroup', 0) == 1) {
      $datas = array(
        'id' => $this->getConfiguration('group_id'),
        'force' => true
      );
      mqtt2::publish(z2m::getInstanceTopic($this->getConfiguration('instance')) . '/bridge/request/group/remove', json_encode($datas));
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

  // Exécution d'une commande
  public function execute($_options = array()) {
    $eqLogic = $this->getEqLogic();
    switch ($this->getSubType()) {
      case 'slider':
        $replace['#slider#'] = round(floatval($_options['slider']), 2);
        break;
      case 'color':
        list($r, $g, $b) = str_split(str_replace('#', '', $_options['color']), 2);
        $info = self::convertRGBToXY(hexdec($r), hexdec($g), hexdec($b));
        $replace['#color#'] = round($info['x'] * 65535) . '::' . round($info['y'] * 65535);
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
    $info = explode('::', str_replace(array_keys($replace), $replace, $this->getLogicalId()));
    if ($info[1] == 'true') {
      $info[1] = true;
    } else if ($info[1] == 'false') {
      $info[1] = false;
    }
    $datas = array($info[0] =>  $info[1]);
    if ($eqLogic->getConfiguration('isgroup', 0) == 1) {
      mqtt2::publish(z2m::getInstanceTopic(init('instance')) . '/' . $eqLogic->getConfiguration('friendly_name') . '/set', json_encode($datas));
      return;
    }
    mqtt2::publish(z2m::getInstanceTopic(init('instance')) . '/' . z2m::convert_from_addr(explode('|',$eqLogic->getLogicalId())[0]) . '/set', json_encode($datas));
  }

  /*     * **********************Getteur Setteur*************************** */
}

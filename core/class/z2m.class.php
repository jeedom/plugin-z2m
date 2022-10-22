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

  /*
  * Permet de définir les possibilités de personnalisation du widget (en cas d'utilisation de la fonction 'toHtml' par exemple)
  * Tableau multidimensionnel - exemple: array('custom' => true, 'custom::layout' => false)
  public static $_widgetPossibility = array();
  */

  /*
  * Permet de crypter/décrypter automatiquement des champs de configuration du plugin
  * Exemple : "param1" & "param2" seront cryptés mais pas "param3"
  public static $_encryptConfigKey = array('param1', 'param2');
  */

  /*     * ***********************Methode static*************************** */

  public static function cronDaily() {
    shell_exec("ls -1tr " . __DIR__ . "/../../data/backup/*.zip | head -n -10 | xargs -d '\n' rm -f --");
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

  public static function postConfig_mqtt_topic($_value) {
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
          log::add('z2m', 'debug', $eqLogic->getHumanName() . ' Check for update ' . $logical_id . ' => ' . $value);
          if ($logical_id == 'last_seen') {
            $value = date('Y-m-d H:i:s', $value / 1000);
          }
          $eqLogic->checkAndUpdateCmd($logical_id, $value);
        }
        continue;
      }
    }
  }

  public static function handle_bridge($_datas, $_instanceNumber = 1) {
    if (isset($_datas['logging']['level']) && $_datas['logging']['level'] == 'error') {
      log::add('z2m', 'error', __('Z2M à renvoyé une erreur : ', __FILE__) . $_datas['logging']['message']);
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
        if ($device['type'] == 'Coordinator') {
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
          $eqLogic->setConfiguration('device', $device['model_id']);
          $new = true;
        }
        $eqLogic->setConfiguration('instance', $_instanceNumber);
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
        foreach ($device['definition']['exposes'] as $expose) {
          if (isset($expose['features'])) {
            foreach ($expose['features'] as $feature) {
              $cmd = $eqLogic->getCmd('info', $feature['name']);
              if (!is_object($cmd)) {
                $cmd = new z2mCmd();
                $cmd->setLogicalId($feature['name']);
                $cmd->setName(__($feature['name'], __FILE__));
              }
              $cmd->setEqLogic_id($eqLogic->getId());
              $cmd->setType('info');
              $cmd->setSubType($feature['type']);
              if ($feature['type'] == 'binary') {
                $cmd->setConfiguration('repeatEventManagement', 'never');
              } else if ($feature['type'] == 'numeric') {
                if (isset($feature['unit'])) {
                  $cmd->setUnite($feature['unit']);
                }
                if (isset($feature['value_max'])) {
                  $cmd->setConfiguration('maxValue', $expose['value_max']);
                }
                if (isset($feature['value_min'])) {
                  $cmd->setConfiguration('minValue', $expose['value_min']);
                }
              }
              $cmd->save();
              $link_cmd_id = $cmd->getId();

              if (isset($feature['value_off'])) {
                $cmd = $eqLogic->getCmd('action', $feature['name'] . '::' . $feature['value_off']);
                if (!is_object($cmd)) {
                  $cmd = new z2mCmd();
                  $cmd->setLogicalId($feature['name'] . '::' . $feature['value_off']);
                  $cmd->setName(__($feature['name'] . ' Off', __FILE__));
                }
                $cmd->setEqLogic_id($eqLogic->getId());
                $cmd->setType('action');
                $cmd->setSubType('other');
                $cmd->setValue($link_cmd_id);
                $cmd->save();
              }

              if (isset($feature['value_on'])) {
                $cmd = $eqLogic->getCmd('action', $feature['name'] . '::' . $feature['value_on']);
                if (!is_object($cmd)) {
                  $cmd = new z2mCmd();
                  $cmd->setLogicalId($feature['name'] . '::' . $feature['value_on']);
                  $cmd->setName(__($feature['name'] . ' On', __FILE__));
                }
                $cmd->setEqLogic_id($eqLogic->getId());
                $cmd->setType('action');
                $cmd->setSubType('other');
                $cmd->setValue($link_cmd_id);
                $cmd->save();
              }

              if (isset($feature['value_toggle'])) {
                $cmd = $eqLogic->getCmd('action', $feature['name'] . '::' . $feature['value_toggle']);
                if (!is_object($cmd)) {
                  $cmd = new z2mCmd();
                  $cmd->setLogicalId($feature['name']  . '::' . $feature['value_toggle']);
                  $cmd->setName(__($feature['name'] . ' Toggle', __FILE__));
                }
                $cmd->setEqLogic_id($eqLogic->getId());
                $cmd->setType('action');
                $cmd->setSubType('other');
                $cmd->setValue($link_cmd_id);
                $cmd->save();
              }

              if ($feature['type'] == 'numeric') {
                $cmd = $eqLogic->getCmd('action', $feature['name'] . '::#slider#');
                if (!is_object($cmd)) {
                  $cmd = new z2mCmd();
                  $cmd->setLogicalId($feature['name']  . '::#slider#');
                  $cmd->setName(__('Configurer ' . $feature['name'], __FILE__));
                }
                $cmd->setEqLogic_id($eqLogic->getId());
                $cmd->setType('action');
                $cmd->setSubType('slider');
                $cmd->setValue($link_cmd_id);
                if (isset($expose['value_max'])) {
                  $cmd->setConfiguration('maxValue', $expose['value_max']);
                }
                if (isset($expose['value_min'])) {
                  $cmd->setConfiguration('minValue', $expose['value_min']);
                }
                $cmd->save();
              }

              if ($feature['type'] == 'enum') {
                foreach ($feature['values'] as $feature_value) {
                  $cmd = $eqLogic->getCmd('action', $feature['name'] . '::' . $feature_value);
                  if (!is_object($cmd)) {
                    $cmd = new z2mCmd();
                    $cmd->setLogicalId($feature['name'] . '::' . $feature_value);
                    $cmd->setName(__($feature_value, __FILE__));
                  }
                  $cmd->setEqLogic_id($eqLogic->getId());
                  $cmd->setType('action');
                  $cmd->setSubType('other');
                  $cmd->setValue($link_cmd_id);
                  $cmd->save();
                }
              }
            }
            continue;
          }

          if (!isset($expose['name'])) {
            continue;
          }
          $cmd = $eqLogic->getCmd('info', $expose['name']);
          if (!is_object($cmd)) {
            $cmd = new z2mCmd();
            $cmd->setLogicalId($expose['name']);
            $cmd->setName(__($expose['name'], __FILE__));
          }
          $cmd->setEqLogic_id($eqLogic->getId());
          $cmd->setType('info');
          $cmd->setSubType($expose['type']);
          if ($expose['type'] == 'binary') {
            $cmd->setConfiguration('repeatEventManagement', 'never');
          } else if ($expose['type'] == 'numeric') {
            if (isset($expose['unit'])) {
              $cmd->setUnite($expose['unit']);
            }
            if (isset($expose['value_max'])) {
              $cmd->setConfiguration('maxValue', $expose['value_max']);
            }
            if (isset($expose['value_min'])) {
              $cmd->setConfiguration('minValue', $expose['value_min']);
            }
          }
          $cmd->save();
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

  public static function getImgFilePath($_device, $_manufacturer = null) {
    if ($_manufacturer != null) {
      if (file_exists(__DIR__ . '/../config/devices/' . $_manufacturer . '/' . $_device . '.png')) {
        return $_manufacturer . '/' . $_device . '.png';
      }
      if (file_exists(__DIR__ . '/../config/devices/' . mb_strtolower($_manufacturer) . '/' . $_device . '.png')) {
        return mb_strtolower($_manufacturer) . '/' . $_device . '.png';
      }
    }
    $device = self::ciGlob($_device);
    foreach (ls(__DIR__ . '/../config/devices', '*', false, array('folders', 'quiet')) as $folder) {
      foreach (ls(__DIR__ . '/../config/devices/' . $folder, $device . '.{jpg,png}', false, array('files', 'quiet')) as $file) {
        return $folder . $file;
      }
    }
    foreach (ls(__DIR__ . '/../config/devices', '*', false, array('folders', 'quiet')) as $folder) {
      foreach (ls(__DIR__ . '/../config/devices/' . $folder, '*.{jpg,png}', false, array('files', 'quiet')) as $file) {
        if (strtolower($_device) . '.png' == strtolower($file)) {
          return $file;
        }
        if (strtolower($_device) . '.jpg' == strtolower($file)) {
          return $file;
        }
      }
    }
    return false;
  }

  /*     * *********************Methode d'instance************************* */

  public function preRemove() {
    if ($this->getConfiguration('isgroup', 0) == 1) {
      $datas = array(
        'id' => $this->getConfiguration('friendly_name'),
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
    $datas = array($info[0] =>  $info[1]);
    if ($eqLogic->getConfiguration('isgroup', 0) == 1) {
      mqtt2::publish(z2m::getInstanceTopic(init('instance')) . '/' . $eqLogic->getConfiguration('friendly_name') . '/set', json_encode($datas));
      return;
    }
    mqtt2::publish(z2m::getInstanceTopic(init('instance')) . '/' . z2m::convert_from_addr($eqLogic->getLogicalId()) . '/set', json_encode($datas));
  }

  /*     * **********************Getteur Setteur*************************** */
}

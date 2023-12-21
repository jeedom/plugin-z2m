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

try {
  require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
  include_file('core', 'authentification', 'php');

  if (!isConnect('admin')) {
    throw new Exception(__('401 - Accès non autorisé', __FILE__));
  }

  ajax::init();

  if (init('action') == 'include') {
    $data = array(
      'value' => true,
      'time' => 180
    );
    if(init('id') != 'all'){
      $data['device'] = init('id');
    }
    mqtt2::publish(z2m::getRootTopic() . '/bridge/request/permit_join', json_encode($data));
    ajax::success();
  }

  if (init('action') == 'publish') {
    mqtt2::publish(z2m::getRootTopic() . init('topic'), init('message', ''));
    ajax::success();
  }

  if (init('action') == 'sync') {
    $devices = json_decode(file_get_contents(__DIR__ . '/../../data/devices/devices1.json'), true);
    z2m::handle_bridge(array('devices' => $devices));
    $groups = json_decode(file_get_contents(__DIR__ . '/../../data/devices/groups1.json'), true);
    z2m::handle_bridge(array('groups' => $groups));
    ajax::success();
  }

  if (init('action') == 'childCreate') {
    $eqLogic = z2m::byId(init('id'));
    if (!is_object($eqLogic)) {
      throw new Exception(__('Z2m eqLogic non trouvé : ', __FILE__) . init('id'));
    }
    $childeqLogic = eqLogic::byLogicalId($eqLogic->getLogicalId() . '|l' . init('endpoint'), 'z2m');
    $eqLogic->childCreate(init('endpoint'));
    ajax::success();
  }

  if (init('action') == 'firmwareUpdate') {
    if (init('port') == 'gateway') {
      $port = 'socket://' . init('gateway');
    } else {
      $port = jeedom::getUsbMapping(init('port'));
    }
    $cron = new cron();
    $cron->setClass('z2m');
    $cron->setFunction('firmwareUpdate');
    $cron->setOption(array('port' => $port, 'sub_controller' => init('sub_controller'), 'firmware' => init('firmware')));
    $cron->setSchedule(cron::convertDateToCron(strtotime('now +1 year')));
    $cron->setOnce(1);
    $cron->save();
    $cron->run();
    ajax::success();
  }

  throw new Exception(__('Aucune méthode correspondante à', __FILE__) . ' : ' . init('action'));
  /*     * *********Catch exeption*************** */
} catch (Exception $e) {
  ajax::error(displayException($e), $e->getCode());
}

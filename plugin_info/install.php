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

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';

function z2m_install() {
  z2m::postConfig_wanted_z2m_version(config::byKey('wanted_z2m_version', 'z2m'));
  $plugin = plugin::byId('z2m');
  if (config::byKey('z2m::mode', 'z2m', 'local') == 'local') {
    $plugin->dependancy_changeAutoMode(1);
    $plugin->deamon_info(1);
  } else {
    $plugin->dependancy_changeAutoMode(0);
    $plugin->deamon_info(0);
  }
}


function z2m_update() {
  if(trim(config::byKey('wanted_z2m_version',  'z2m')) == ''){
     config::save('wanted_z2m_version', '1.42.0', 'z2m');
  }else{
    z2m::postConfig_wanted_z2m_version(config::byKey('wanted_z2m_version',  'z2m'));
  }
  $plugin = plugin::byId('z2m');
  if (config::byKey('z2m::mode', 'z2m', 'local') == 'local') {
    $plugin->dependancy_changeAutoMode(1);
    $plugin->deamon_info(1);
  } else {
    $plugin->dependancy_changeAutoMode(0);
    $plugin->deamon_info(0);
  }
  $devices = json_decode(file_get_contents(__DIR__ . '/../data/devices/devices1.json'), true);
  z2m::handle_bridge(array('devices' => $devices));
  $groups = json_decode(file_get_contents(__DIR__ . '/../data/devices/groups1.json'), true);
  z2m::handle_bridge(array('groups' => $groups));
}


function z2m_remove() {
}

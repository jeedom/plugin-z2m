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
include_file('core', 'authentification', 'php');
if (!isConnect()) {
  include_file('desktop', '404', 'php');
  die();
}
?>
<form class="form-horizontal">
  <fieldset>
    <div class="form-group">
      <label class="col-md-4 control-label">{{Topic racine}}</label>
      <div class="col-md-4">
        <input class="configKey form-control" data-l1key="mqtt::topic" />
      </div>
    </div>
    <div class="form-group">
      <label class="col-md-4 control-label">{{Mode}}</label>
      <div class="col-md-4">
        <select class="configKey form-control" data-l1key="z2m::mode" id="sel_z2mMode">
          <option value="distant">{{Distant}}</option>
          <option value="local">{{Local}}</option>
        </select>
      </div>
    </div>
    <div class="form-group z2m_mode local">
      <label class="col-md-5 control-label">{{Port du contrôleur}}
        <sup><i class="fas fa-question-circle tooltips" title="{{Sélectionner le port du contrôleur Zigbee. Le mode Auto ne fonctionne qu'avec les clés Deconz}}"></i></sup>
      </label>
      <div class="col-md-6">
        <select class="configKey form-control" data-l1key="port">
          <option value="none">{{Aucun}}</option>
          <option value="gateway">{{Passerelle distante}}</option>
          <option value="/dev/ttyS2">{{Atlas}}</option>
          <option value="/dev/ttyUSB1">{{Luna}}</option>
          <?php
          foreach (jeedom::getUsbMapping() as $name => $value) {
            echo '<option value="' . $name . '">' . $name . ' (' . $value . ')</option>';
          }
          foreach (ls('/dev/', 'tty*') as $value) {
            echo '<option value="/dev/' . $value . '">/dev/' . $value . '</option>';
          }
          ?>
        </select>
      </div>
    </div>
    <div class="form-group z2m_mode local">
      <label class="col-md-5 control-label">{{Type de contrôleur}}
        <sup><i class="fas fa-question-circle tooltips" title="{{Sélectionner le type de contrôleur Zigbee à utiliser}}"></i></sup>
      </label>
      <div class="col-md-6">
        <select class="configKey form-control" data-l1key="controller">
          <option value="ti">{{ZNP/TI}}</option>
          <option value="ezsp">{{EZSP (Atlas/Luna)}}</option>
          <option value="deconz">{{Conbee}}</option>
          <option value="zigate">{{Zigate (alpha)}}</option>
        </select>
      </div>
    </div>
  </fieldset>
</form>

<script>
  $('#sel_z2mMode').off('change').on('change', function() {
    $('.z2m_mode').hide();
    if ($(this).value() != '') {
      $('.z2m_mode.' + $(this).value()).show();
    }
  })
</script>
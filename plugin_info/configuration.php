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
	<?php if (class_exists('jMQTT')) {
			echo '<div class="alert alert-warning">{{Le plugin jMQTT est installé, veuillez vérifier la configuration du broker dans le plugin jMQTT et la reporter, si nécessaire, dans le plugin MQTT Manager.}}</div>';
		}
	?>
    <div class="form-group">
      <label class="col-md-4 control-label">{{Topic racine}}</label>
      <div class="col-md-3">
        <input class="configKey form-control" data-l1key="mqtt::topic" />
      </div>
    </div>
    <div class="form-group">
      <label class="col-md-4 control-label">{{Mode}}</label>
      <div class="col-md-3">
        <select class="configKey form-control" data-l1key="z2m::mode" id="sel_z2mMode">
          <option value="distant">{{Distant}}</option>
          <option value="local">{{Local}}</option>
        </select>
      </div>
    </div>
    <div class="form-group z2m_mode distant">
	<div class='alert alert-warning text-center'>{{Cette configuration suppose que vous avez installé vous meme zigbee2mqtt sur une machine deporté (donc pas sur jeedom). Cette configuration est assez rare si vous avez une box jeedom il faut choisir le mode local}}</div>
    </div>
		  
    <div class="form-group z2m_mode local">
      <label class="col-md-4 control-label">{{Port du contrôleur}}
        <sup><i class="fas fa-question-circle tooltips" title="{{Sélectionner le port du contrôleur Zigbee. Le mode Auto ne fonctionne qu'avec les clés Deconz}}"></i></sup>
      </label>
      <div class="col-md-3">
        <select class="configKey form-control" data-l1key="port">
          <option value="none">{{Aucun}}</option>
          <option value="auto">{{Auto}}</option>
          <option value="gateway">{{Passerelle distante}}</option>
          <option value="/dev/ttyS2">{{Atlas}}</option>
          <?php
          foreach (ls('/dev/', 'tty*') as $value) {
            if ($value == "ttyLuna-Zigbee") {
              echo '<option value="/dev/' . $value . '">Luna Zigbee V2</option>';
            } else if ($value == "ttyUSB1") {
              echo '<option value="/dev/' . $value . '">Luna Zigbee Old (/dev/' . $value . ')</option>';
            } else {
              echo '<option value="/dev/' . $value . '">/dev/' . $value . '</option>';
            }
          }
          foreach (jeedom::getUsbMapping() as $name => $value) {
            echo '<option value="' . $name . '">' . $name . ' (' . $value . ')</option>';
          }
          ?>
        </select>
      </div>
    </div>
    <div class="form-group zigbee_portConf gateway" style="display:none;">
      <label class="col-md-4 control-label">{{Passerelle distante}} <sub>(IP:PORT)</sub>
        <sup><i class="fas fa-question-circle tooltips" title="{{Renseigner l'adresse de la passerelle distante}}"></i></sup>
      </label>
      <div class="col-md-3">
        <input class="configKey form-control" data-l1key="gateway" />
      </div>
    </div>
    <div class="form-group z2m_mode local">
      <label class="col-md-4 control-label">{{Type de contrôleur}}
        <sup><i class="fas fa-question-circle tooltips" title="{{Sélectionner le type de contrôleur Zigbee à utiliser}}"></i></sup>
      </label>
      <div class="col-md-3">
        <select class="configKey form-control" data-l1key="controller" id="sel_z2mControllerType">
          <option value="ti">{{ZNP/TI}}</option>
          <option value="ezsp">{{EZSP (Atlas/Luna)}}</option>
          <option value="deconz">{{Deconz/Conbee}}</option>
          <option value="zigate">{{Zigate (alpha)}}</option>
        </select>
      </div>
    </div>
    <div class="form-group z2m_controllerType ezsp">
      <label class="col-md-4 control-label">{{Mise à jour du firmware du contrôleur}}
        <sup><i class="fas fa-question-circle tooltips" title="{{Cliquer sur le bouton pour mettre à jour le firmware du contrôleur. Le démon Zigbee est stoppé durant le processus}}"></i></sup>
      </label>
      <div class="col-md-4">
          <a class="btn btn-warning" id="bt_UpdateFirmware"><i class="fas fa-download"></i> {{Mettre à jour le firmware}}</a>
      </div>
    </div>
    <div class="form-group z2m_mode local">
      <label class="col-md-4 control-label">{{Port d'écoute de Zigbee2mqtt}}</label>
      <div class="col-md-3">
        <input type="number" class="configKey form-control" data-l1key="z2m_listen_port" />
      </div>
    </div>
    <div class="form-group z2m_mode local">
      <label class="col-md-4 control-label">{{Interface z2m}}</label>
      <div class="col-md-1">
        <a target="_blank" href="http://<?php echo network::getNetworkAccess('internal', 'ip').':'.config::byKey('z2m_listen_port', 'z2m', 8080) ?>">{{Ici}}</a>
      </div>
      <label class="col-md-1 control-label">{{Identifiant}}</label>
      <div class="col-md-3">
        <span class="label label-info"><?php echo config::byKey('z2m_auth_token', 'z2m', '') ?></span>
      </div>
    </div>
    <div class="form-group z2m_mode local">
      <label class="col-md-4 control-label">{{Converters (réservé aux utilisateurs avancées)}}
      	<sup><i class="fas fa-question-circle tooltips" title="{{N'oubliez pas de redemarrer le démon après tout changement pour qu'il soit pris en compte}}"></i></sup>
      </label>
      <div class="col-md-3">
        <a class="btn btn-warning" href="index.php?v=d&p=editor&root=plugins/z2m/core/config/converters/custom">{{Editer}}</a>
      </div>
    </div>
     <div class="form-group z2m_mode local">
      <label class="col-md-4 control-label">{{Version voulue (réservé aux utilisateurs avancées)}}
        <sup><i class="fas fa-question-circle tooltips" title="{{Laisser vide pour mettre la derniere disponible}}"></i></sup>
      </label>
      <div class="col-md-3">
        <input class="configKey form-control" data-l1key="wanted_z2m_version" />
      </div>
      <div class="col-md-3">
	<a class="btn btn-info" href="https://github.com/Koenkk/zigbee2mqtt/tags" target="_blank">{{Liste des versions}}</a>
      </div>
    </div>
    <div class="form-group z2m_mode local">
		<label class="col-md-4 control-label">{{Version Zigbee2mqtt}}
			<sup><i class="fas fa-question-circle tooltips" title="{{Version de la librairie Zigbee2mqtt}}"></i></sup>
		</label>
		<div class="col-md-7">
		<?php
		$file = dirname(__FILE__) . '/../resources/zigbee2mqtt/package.json';
		$package = array();
		if (file_exists($file)) {
			$package = json_decode(file_get_contents($file), true);
		}
		if (isset($package['version'])){
			config::save('zigbee2mqttVersion', $package['version'], 'z2m');
		}
		$localVersion = config::byKey('zigbee2mqttVersion', 'z2m', 'N/A');
		$wantedVersion = config::byKey('wantedVersion', 'z2m', '');
		if (version_compare($localVersion, $wantedVersion, '<')) {
			echo '<span class="label label-warning">' . $localVersion . '</span><br>';
			echo "<div class='alert alert-warning text-center'>{{Votre version de zigbee2mqtt n'est pas celle recommandée par le plugin. Vous utilisez actuellement la version }}<code>". $localVersion .'</code>. {{ Le plugin nécessite la version }}<code>'. $wantedVersion .'</code>. {{Veuillez relancer les dépendances pour mettre à jour la librairie. Relancez ensuite le démon pour voir la nouvelle version.}}</div>';
		} else {
			echo '<span class="label label-success">' . $localVersion . '</span><br>';
		}
		?>
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
  $('.configKey[data-l1key="port"]').off('change').on('change', function() {
    $('.zigbee_portConf').hide();
    if ($(this).value() == 'pizigate' || $(this).value() == 'wifizigate' || $(this).value() == 'gateway') {
      $('.zigbee_portConf.' + $(this).value()).show();
    }
  });
  $('#sel_z2mControllerType').off('change').on('change', function() {
    $('.z2m_controllerType').hide();
    if ($(this).value() != '') {
      $('.z2m_controllerType.' + $(this).value()).show();
    }
  })

  $('#bt_UpdateFirmware').off('clic').on('click', function() {
    $('#md_modal').dialog({
      title: "{{Mise à jour du firmware du contrôleur}}"
    }).load('index.php?v=d&plugin=z2m&modal=firmware_update').dialog('open');
  })
</script>

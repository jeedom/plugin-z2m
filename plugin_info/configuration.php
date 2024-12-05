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
          <option value="">{{A configurer}}</option>
          <option value="distant">{{Distant}}</option>
          <option value="local">{{Local}}</option>
        </select>
      </div>
    </div>
    <div class="form-group z2m_mode distant">
	<div class='alert alert-warning text-center'>{{Cette configuration suppose que vous avez installé vous même zigbee2MQTT sur une machine déportée (donc pas sur jeedom). Cette configuration est assez rare si vous avez une box jeedom il faut choisir le mode local}}</div>
    </div>	  
    <div class="form-group z2m_mode local">
      <label class="col-md-4 control-label">{{Port du contrôleur}}
        <sup><i class="fas fa-question-circle tooltips" title="{{Sélectionnez le port du contrôleur Zigbee<br/>Le mode Auto ne fonctionne qu'avec les clés Deconz}}"></i></sup>
      </label>
      <div class="col-md-3">
        <select class="configKey form-control" data-l1key="port">
          <option value="none">{{Aucun}}</option>
          <option value="auto">{{Auto}}</option>
          <option value="gateway">{{Passerelle distante}}</option>
	        <?php 
          if(file_exists('/dev/ttyS2')){
          	echo ' <option value="/dev/ttyS2">{{Atlas (/dev/ttyS2)}}</option>';
          }
	        if(file_exists('/dev/ttyLuna-Zigbee')){
          	echo '<option value="/dev/ttyLuna-Zigbee">{{Luna Zigbee (/dev/ttyLuna-Zigbee)}}</option>';
          }
          foreach (jeedom::getUsbMapping() as $name => $value) {
            if(isset($findPort[$value])){
                continue;
            }
            echo '<option value="' . $value . '">' . $name . ' (' . $value . ')</option>';
          }
          if(file_exists('/dev/ttyAMA0')){
           echo '<option value="/dev/ttyAMA0">/dev/ttyAMA0</option>';
          }
          ?>
        </select>
      </div>
    </div>
    <div class="form-group zigbee_portConf gateway" style="display:none;">
      <label class="col-md-4 control-label">{{Passerelle distante}} <sub>(IP:PORT)</sub>
        <sup><i class="fas fa-question-circle tooltips" title="{{Renseignez l'adresse de la passerelle distante}}"></i></sup>
      </label>
      <div class="col-md-3">
        <input class="configKey form-control" data-l1key="gateway" />
      </div>
    </div>
    <div class="form-group z2m_mode local">
      <label class="col-md-4 control-label">{{Type de contrôleur}}
        <sup><i class="fas fa-question-circle tooltips" title="{{Sélectionnez le type de contrôleur Zigbee à utiliser}}"></i></sup>
      </label>
      <div class="col-md-3">
        <select class="configKey form-control" data-l1key="controller" id="sel_z2mControllerType">
          <option value="ti">{{ZNP/TI}}</option>
          <option value="ezsp">{{EZSP (Atlas/Luna/Smart)}}</option>
	  <option value="ember">{{Ember}}</option>	
          <option value="deconz">{{Deconz/Conbee}}</option>
          <option value="conbee_3">{{Conbee 3}}</option>
          <option value="raspbee_2">{{Raspbee 2}}</option>
          <option value="zigate">{{Zigate (alpha)}}</option>
        </select>
      </div>
    </div>
    <div class="form-group z2m_mode local z2m_controllerType ezsp ember ti">
      <label class="col-md-4 control-label">{{Baudrate}}
      <sup><i class="fas fa-question-circle tooltips" title="{{Reserver aux utilisateurs avancés}}"></i></sup>
      </label>
      <div class="col-md-3">
        <input type="number" class="configKey form-control" data-l1key="baudrate" />
      </div>
    </div>
    <?php if(jeedom::getHardwareName() != 'Luna'){ ?>
    <div class="form-group z2m_controllerType ezsp">
      <label class="col-md-4 control-label">{{Mise à jour du firmware du contrôleur}}
        <sup><i class="fas fa-question-circle tooltips" title="{{Cliquez sur le bouton pour mettre à jour le firmware du contrôleur<br/>Le démon Zigbee est stoppé durant le processus}}"></i></sup>
      </label>
      <div class="col-md-4">
          <a class="btn btn-warning" id="bt_UpdateFirmware"><i class="fas fa-download"></i> {{Mettre à jour le firmware}}</a>
      </div>
    </div>
    <?php } ?>
    <div class="form-group z2m_mode local">
      <label class="col-md-4 control-label">{{Port d'écoute de Zigbee2MQTT}}
      <sup><i class="fas fa-question-circle tooltips" title="{{Port 8080 par défaut}}"></i></sup>
      </label>
      <div class="col-md-3">
        <input type="number" class="configKey form-control" data-l1key="z2m_listen_port" />
      </div>
    </div>
    <div class="form-group z2m_mode local">
      <label class="col-md-4 control-label">{{Accès à la page web z2m}}
      <sup><i class="fas fa-question-circle tooltips" title="{{Cliquez sur ICI pour accèder à l'interface web de z2m}}"></i></sup>
      </label>
      <div class="col-md-1">
        <a target="_blank" href="http://<?php echo network::getNetworkAccess('internal', 'ip').':'.config::byKey('z2m_listen_port', 'z2m', 8080) ?>"><span class="label label-info">ICI</span></a>
      </div>
      </div>
      <div class="form-group z2m_mode local">
      <label class="col-md-4 control-label">{{Identifiant}}
      <sup><i class="fas fa-question-circle tooltips" title="{{Code nécessaire pour l'accès web z2m}}"></i></sup>
      </label>
      <div class="col-md-3">
        <span class="label label-info"><?php echo config::byKey('z2m_auth_token', 'z2m', '') ?></span>
      </div>
    </div>
    <div class="form-group z2m_mode local">
      <label class="col-md-4 control-label">{{Converters (réservé aux utilisateurs avancés)}}
      	<sup><i class="fas fa-question-circle tooltips" title="{{N'oubliez pas de redémarrer le démon après tout changement pour qu'il soit pris en compte}}"></i></sup>
      </label>
      <div class="col-md-3">
        <a class="btn btn-warning" href="index.php?v=d&p=editor&root=plugins/z2m/core/config/converters/custom">{{Editer}}</a>
      </div>
    </div>
     <div class="form-group z2m_mode local">
      <label class="col-md-4 control-label">{{Version voulue (réservé aux utilisateurs avancés)}}
        <sup><i class="fas fa-question-circle tooltips" title="{{Laisser vide pour installer la dernière version disponible <br/>Si vous voulez forcer une installation précise, cliquez sur le bouton &#x2192; Liste des versions <br/> Encodez la version voulue et pensez à sauvegarder avant de relancer les dépendances}}"></i></sup>
      </label>
      <div class="col-md-3">
        <input class="configKey form-control" data-l1key="wanted_z2m_version" />
      </div>
      <div class="col-md-3">
	<a class="btn btn-info" href="https://github.com/Koenkk/zigbee2mqtt/tags" target="_blank">{{Liste des versions}}</a>
      </div>
    </div>
    <div class="form-group z2m_mode local">
		<label class="col-md-4 control-label">{{Version actuelle de Zigbee2MQTT}}
			<sup><i class="fas fa-question-circle tooltips" title="{{Version de la librairie Zigbee2MQTT}}"></i></sup>
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
			echo "<div class='alert alert-warning text-center'>{{Votre version de zigbee2MQTT n'est pas celle recommandée par le plugin. Vous utilisez actuellement la version }}<code>". $localVersion .'</code>. {{ Le plugin nécessite la version }}<code>'. $wantedVersion .'</code>. {{Veuillez relancer les dépendances pour mettre à jour la librairie. Relancez ensuite le démon pour voir la nouvelle version.}}</div>';
		} else {
			echo '<span class="label label-success">' . $localVersion . '</span>';
    }
    $lastV = cache::byKey('z2m::lastZ2mVersion')->getValue();
    if($lastV === null){
      $lastV = file_get_contents('https://raw.githubusercontent.com/Koenkk/zigbee2mqtt/master/package.json', 0, stream_context_create(["http"=>["timeout"=>1]]));
      cache::set('z2m::lastZ2mVersion',$lastV,86400);
    }
    if ($lastV !== false) {
      $V = json_decode($lastV, true);
      if (is_array($V) && json_last_error() == '' && isset($V['version']) && $package['version'] !== $V['version']) {
        $imageData = base64_encode(file_get_contents('https://img.shields.io/github/v/release/koenkk/zigbee2mqtt.svg', 0, stream_context_create(["http"=>["timeout"=>1]])));
        $src = 'data:image/svg+xml;base64,'.$imageData;
        echo '<br/><img src="'.$src .'"/> disponible <small>(Vous devez relancer les dépendances pour mettre à jour)</small>';
      }
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
    if ($(this).value() == '/dev/ttyS2' || $(this).value() == '/dev/ttyLuna-Zigbee'){
	if($('#sel_z2mControllerType').value() != 'ezsp' && $('#sel_z2mControllerType').value() != 'ember'){
		$('#sel_z2mControllerType').value('ezsp');
	}	
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

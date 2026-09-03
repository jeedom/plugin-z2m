<?php
// Déclaration des variables obligatoires
$plugin = plugin::byId('z2m');
sendVarToJS('eqType', $plugin->getId());
$eqLogics = eqLogic::byType($plugin->getId());

foreach ($eqLogics as $eqLogic) {
	$devices[$eqLogic->getLogicalId()] = array(
		'HumanNameFull' => $eqLogic->getHumanName(true),
		'HumanName' => $eqLogic->getHumanName(),
		'id' => $eqLogic->getId(),
		'img' => $eqLogic->getImgFilePath(),
		'device_type' =>  $eqLogic->getConfiguration('device_type','EndDevice'),
		'isgroup' => $eqLogic->getConfiguration('isgroup', 0),
		'isChild' => $eqLogic->getConfiguration('isChild', 0),
		'ieee' => z2m::convert_from_addr(explode('|', $eqLogic->getLogicalId())[0])
	);
	$deviceAttr[$eqLogic->getId()] = array(
		'isgroup' => $eqLogic->getConfiguration('isgroup', 0),
		'multipleEndpoints' => $eqLogic->getConfiguration('multipleEndpoints', 0),
		'isChild' => $eqLogic->getConfiguration('isChild', 0)
	);
}
$devices[0] = array('HumanNameFull' => 'Contrôleur', 'HumanName' => 'Contrôleur', 'id' => 0, 'img' => 'plugins/z2m/core/config/devices/coordinator.png');
sendVarToJS('z2m_devices', $devices);
sendVarToJS('devices_attr', $deviceAttr);
$bridge_infos = z2m::getDeviceInfo('bridge1');
if($bridge_infos['permit_join'] && isset($bridge_infos['permit_join_end'])){
	$ttl = $bridge_infos['permit_join_end'] - time() * 1000;
	event::add('jeedom::alert', array(
          'level' => 'success',
          'page' => 'z2m',
          'message' => __('Mode inclusion actif pendant '. intdiv($ttl, 1000) . 's', __FILE__),
          'ttl' => $ttl
        ));
}
?>

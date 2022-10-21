<?php
/* This file is part of Plugin zigbee for jeedom.
*
* Plugin zigbee for jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Plugin zigbee for jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Plugin zigbee for jeedom. If not, see <http://www.gnu.org/licenses/>.
*/
if (!isConnect('admin')) {
    throw new Exception('401 Unauthorized');
}
$plugin = plugin::byId('z2m');
$infos = z2m::getDeviceInfo('bridge1');
?>
<select class="pull-right form-control" id="sel_networkZigbeeInstance" style="width:250px;">
    <?php
    foreach (z2m::getDeamonInstanceDef() as $zigbee_instance) {
        if ($zigbee_instance['enable'] != 1) {
            continue;
        }
        echo '<option value="' . $zigbee_instance['id'] . '">' . $zigbee_instance['name'] . '</option>';
    }
    ?>
</select>
<ul id="tabs_network" class="nav nav-tabs" data-tabs="tabs">
    <li class="active"><a href="#application_network" data-toggle="tab"><i class="fas fa-tachometer-alt"></i> {{Application}}</a></li>
    <li><a href="#devices_network" data-toggle="tab"><i class="fab fa-codepen"></i> {{Noeuds}} (<?php echo count($infos['config']['devices']) ?>)</a></li>
    <li role="presentation"><a href="#rawBridgeTab" aria-controls="profile" role="tab" data-toggle="tab"><i class="fas fa-list-alt"></i> {{Informations brutes}}</a></li>
</ul>

<div id="network-tab-content" class="tab-content">
    <div class="tab-pane active" id="application_network">
        <br />
        <form class="form-horizontal">
            <fieldset>
                <br>
                <div class="panel panel-primary">
                    <div class="panel-heading">
                        <h4 class="panel-title"><i class="fas fa-info-circle"></i> {{Zigbee2mqtt}}</h4>
                    </div>
                    <div class="panel-body">
                        <p>
                            {{Version :}}
                            <b><span class="label label-default" style="font-size : 1em;"><?php echo $infos['version'] ?></span></b>
                            {{Log :}}
                            <b><span class="label label-default" style="font-size : 1em;"><?php echo $infos['log_level'] ?></span></b>
                            <br />
                            {{Coordinateur :}}
                            <b><span class="label label-default" style="font-size : 1em;"><?php echo $infos['coordinator']['type'] ?></span></b>
                            {{Port :}}
                            <b><span class="label label-default" style="font-size : 1em;"><?php echo $infos['config']['serial']['port'] ?></span></b>
                            <br />
                            {{Interval de verification OTA :}}
                            <b><span class="label label-default" style="font-size : 1em;"><?php echo $infos['config']['ota']['update_check_interval'] ?>s</span></b>
                        </p>
                    </div>
                </div>

                <div class="panel panel-primary">
                    <div class="panel-heading">
                        <h4 class="panel-title"><i class="fas fa-info-circle"></i> {{Réseaux zigbee}}</h4>
                    </div>
                    <div class="panel-body">
                        <p>
                            {{Canal :}}
                            <b><span class="label label-default" style="font-size : 1em;"><?php echo $infos['network']['channel'] ?></span></b>
                            {{Ext pan id :}}
                            <b><span class="label label-default" style="font-size : 1em;"><?php echo $infos['network']['extended_pan_id'] ?></span></b>
                            {{Pan id :}}
                            <b><span class="label label-default" style="font-size : 1em;"><?php echo $infos['network']['pan_id'] ?></span></b>
                        </p>
                    </div>
                </div>


                <div class="panel panel-primary">
                    <div class="panel-heading">
                        <h4 class="panel-title"><i class="fas fa-info-circle"></i> {{MQTT}}</h4>
                    </div>
                    <div class="panel-body">
                        <p>
                            {{Topic :}}
                            <b><span class="label label-default" style="font-size : 1em;"><?php echo $infos['config']['mqtt']['base_topic'] ?></span></b>
                            {{Serveur :}}
                            <b><span class="label label-default" style="font-size : 1em;"><?php echo $infos['config']['mqtt']['server'] ?></span></b>
                            {{Utilisateur :}}
                            <b><span class="label label-default" style="font-size : 1em;"><?php echo $infos['config']['mqtt']['user'] ?></span></b>
                        </p>
                    </div>
                </div>
            </fieldset>
        </form>
    </div>

    <div role="tabpanel" class="tab-pane" id="devices_network">
        <br />
        <table class="table table-bordered table-condensed">
            <thead>
                <th>
                <td>{{ID}}</td>
                <td>{{Nom}}</td>
                </th>
            </thead>
            <tbody>
                <?php
                foreach ($infos['config']['devices'] as $device_id => $device_info) {
                    $eqLogic = eqLogic::byLogicalId(z2m::convert_to_addr($device_id), 'z2m');
                    echo '<tr>';
                    echo '<td>';

                    if ($eqLogic->getConfiguration('device') != "") {
                        if (z2m::getImgFilePath($eqLogic->getConfiguration('device'), $eqLogic->getConfiguration('manufacturer')) !== false && $eqLogic->getConfiguration('ischild', 0) == 0) {
                            echo '<img class="lazy" src="plugins/z2m/core/config/devices/' . z2m::getImgFilePath($eqLogic->getConfiguration('device'), $eqLogic->getConfiguration('manufacturer')) . '" height="40" width="40"/>' . $child;
                        } else if ($eqLogic->getConfiguration('ischild', 0) == 1 && file_exists(dirname(__FILE__) . '/../../core/config/devices/' . $eqLogic->getConfiguration('visual', 'none'))) {
                            echo '<img class="lazy" src="plugins/z2m/core/config/devices/' . $eqLogic->getConfiguration('visual') . '" height="40" width="40"/>' . $child;
                        } else if ($eqLogic->getConfiguration('ischild', 0) == 1 && z2m::getImgFilePath($eqLogic->getConfiguration('device'), $eqLogic->getConfiguration('manufacturer')) !== false) {
                            echo '<img class="lazy" src="plugins/z2m/core/config/devices/' . z2m::getImgFilePath($eqLogic->getConfiguration('device'), $eqLogic->getConfiguration('manufacturer')) . '" height="40" width="40"/>' . $child;
                        } else {
                            echo '<img src="' . $plugin->getPathImgIcon() . '" height="40" width="40"/>' . $child;
                        }
                    } else {
                        echo '<img src="' . $plugin->getPathImgIcon() . '" height="40" width="40"/>' . $child;
                    }
                    echo '</td>';
                    echo '<td>';
                    echo z2m::convert_to_addr($device_id);
                    echo '</td>';
                    echo '<td>';
                    if (is_object($eqLogic)) {
                        echo '<a href="index.php?v=d&p=z2m&m=z2m&id=' . $eqLogic->getId() . '" >' . $eqLogic->getHumanName() . '</a>';
                    }
                    echo '</td>';
                    echo '</tr>';
                }
                ?>
            </tbody>
        </table>
    </div>


    <div role="tabpanel" class="tab-pane" id="rawBridgeTab">
        <pre><?php echo json_encode($infos, JSON_PRETTY_PRINT); ?></pre>
    </div>

</div>
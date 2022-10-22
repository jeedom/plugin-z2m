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
$eqLogic = zigbee::byId(init('id'));
if (!is_object($eqLogic)) {
    throw new \Exception(__('Equipement introuvable : ', __FILE__) . init('id'));
}
$infos = z2m::getDeviceInfo($eqLogic->getLogicalId());
sendVarToJS('z2m_id', $eqLogic->getId());
sendVarToJS('z2m_group_name', $eqLogic->getConfiguration('friendly_name'));

$list_members = array();
foreach (eqLogic::byType('z2m') as $device) {
    if ($device->getConfiguration('isgroup', 0) == 1) {
        continue;
    }
    $device_infos = z2m::getDeviceInfo($device->getLogicalId());
    foreach ($device_infos['endpoints'] as $endpoint_id => $endpoint) {
        foreach ($infos['members'] as $member) {
            if ($member['ieee_address'] == $device_infos['ieee_address'] && $member['endpoint'] == $endpoint_id) {
                continue 2;
            }
        }
        $list_members[$device_infos['ieee_address'] . '/' . $endpoint_id] = $device->getHumanName() . ' ' . $endpoint_id;
    }
}
sendVarToJS('z2m_group_list_member', $list_members);
?>
<ul class="nav nav-tabs" role="tablist">
    <li role="presentation" class="active"><a href="#infoGroupMemberTab" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-info"></i> {{Membre}}</a></li>
    <li role="presentation"><a href="#rawGroupTab" aria-controls="profile" role="tab" data-toggle="tab"><i class="fas fa-list-alt"></i> {{Informations brutes}}</a></li>
</ul>

<div class="tab-content">
    <div role="tabpanel" class="tab-pane active" id="infoGroupMemberTab">
        <br />
        <a class="btn btn-success pull-right" id="bt_addGroupMember"><i class="fas fa-plus"></i> {{Ajouter un membre}}</a>
        <table class="table table-bordered table-condensed">
            <thead>
                <tr>
                    <th>{{IEEE}}</th>
                    <th>{{Nom}}</th>
                    <th>{{Endpoint}}</th>
                    <th>{{Action}}</th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ($infos['members'] as $member) {
                    $eqLogic = eqLogic::byLogicalId(z2m::convert_to_addr($member['ieee_address']), 'z2m');
                    echo '<tr data-device="' . $member['ieee_address'] . '">';
                    echo '<td>';
                    echo z2m::convert_to_addr($member['ieee_address']);
                    echo '</td>';
                    echo '<td>';
                    if (is_object($eqLogic)) {
                        echo $eqLogic->getHumanName();
                    }
                    echo '</td>';
                    echo '<td>';
                    echo $member['endpoint'];
                    echo '</td>';
                    echo '<td>';
                    echo '<a class="btn btn-warning bt_removeGroupMember"><i class="fas fa-trash-alt"></i> {{Supprimer}}</a>';
                    echo '<td>';
                    echo '</tr>';
                }
                ?>
            </tbody>
        </table>
    </div>

    <div role="tabpanel" class="tab-pane" id="rawGroupTab">
        <pre><?php echo json_encode($infos, JSON_PRETTY_PRINT); ?></pre>
    </div>
</div>

<script>
    $('#bt_addGroupMember').off('click').on('click', function() {
        var inputOptions = [];
        for (var i in z2m_group_list_member) {
            inputOptions.push({
                value: i,
                text: z2m_group_list_member[i]
            });
        }
        bootbox.prompt({
            title: "{{Quel équipement et endpoint ajouter au groupe ?}}",
            value: inputOptions[0].value,
            inputType: 'select',
            inputOptions: inputOptions,
            callback: function(member) {
                if (member === null) {
                    return;
                }
                jeedom.z2m.group.addMember({
                    instance: 1,
                    group: z2m_group_name,
                    device: member,
                    error: function(error) {
                        $('#div_alert').showAlert({
                            message: error.message,
                            level: 'danger'
                        });
                    },
                    success: function() {
                        $('#div_alert').showAlert({
                            message: '{{Demande d\'ajout envoyée avec succes.}}',
                            level: 'success'
                        });
                        setTimeout(function() {
                            $('#md_modal').dialog({
                                title: "{{Configuration du groupe}}"
                            }).load('index.php?v=d&plugin=z2m&modal=group&id=' + z2m_id).dialog('open');
                        }, 500)
                    }
                });
            }
        });
    });


    $('.bt_removeGroupMember').off('click').on('click', function() {
        let tr = $(this).closest('tr')
        jeedom.z2m.group.removeMember({
            instance: 1,
            group: z2m_group_name,
            device: tr.attr('data-device'),
            error: function(error) {
                $('#div_alert').showAlert({
                    message: error.message,
                    level: 'danger'
                });
            },
            success: function() {
                $('#div_alert').showAlert({
                    message: '{{Demande de suppression envoyée avec succes.}}',
                    level: 'success'
                });
                setTimeout(function() {
                    $('#md_modal').dialog({
                        title: "{{Configuration du groupe}}"
                    }).load('index.php?v=d&plugin=z2m&modal=group&id=' + z2m_id).dialog('open');
                }, 500)
            }
        });
    });
</script>
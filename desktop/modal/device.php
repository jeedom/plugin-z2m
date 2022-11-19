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
$eqLogic = z2m::byId(init('id'));
if (!is_object($eqLogic)) {
    throw new \Exception(__('Equipement introuvable : ', __FILE__) . init('id'));
}
if ($eqLogic->getConfiguration('isChild',0) == 1){
    $eqLogic = eqLogic::byLogicalId(explode('|',$eqLogic->getLogicalId())[0],'z2m');
}
$devices = eqLogic::byType('z2m');
$infos = z2m::getDeviceInfo($eqLogic->getLogicalId());
$bridge_info = z2m::getDeviceInfo('bridge' . $eqLogic->getConfiguration('instance', 1));
sendVarToJS('z2m_device_id', $eqLogic->getId());
sendVarToJS('z2m_device_instance', $eqLogic->getConfiguration('instance'));
sendVarToJS('z2m_device_ieee', $eqLogic->getLogicalId());
?>
<ul class="nav nav-tabs" role="tablist">
    <li role="presentation" class="active"><a href="#infoNodeTab" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-info"></i> {{Information}}</a></li>
    <li role="presentation"><a href="#configuration" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-info"></i> {{Configuration}}</a></li>
    <li role="presentation"><a href="#binding" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-link"></i> {{Binding}}</a></li>
    <li role="presentation"><a href="#reporting" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-bars"></i> {{Reporting}}</a></li>
    <li role="presentation"><a href="#rawNodeTab" aria-controls="profile" role="tab" data-toggle="tab"><i class="fas fa-list-alt"></i> {{Informations brutes}}</a></li>
    <a class="btn btn-info pull-right" id="bt_refreshDevice"><i class="fas fa-sync"></i></a>
</ul>
<div class="tab-content">
    <div role="tabpanel" class="tab-pane active" id="infoNodeTab">
        <br />
        <form class="form-horizontal">
            <fieldset>
                <br>
                <div class="panel panel-primary">
                    <div class="panel-heading">
                        <h4 class="panel-title"><i class="fas fa-info-circle"></i> {{Informations Noeud}}</h4>
                    </div>
                    <div class="panel-body">
                        <p>
                            {{Nom :}}
                            <b><span class="label label-default" style="font-size : 1em;"><?php echo $eqLogic->getHumanName() ?></span></b>
                            {{Modèle :}}
                            <b><span class="label label-default" style="font-size : 1em;"><?php echo $infos['definition']['model'] ?></span></b>
                            {{Fabricant :}}
                            <b><span class="label label-default" style="font-size : 1em;"><?php echo $infos['manufacturer'] ?></span></b>
                            {{Vendeur :}}
                            <b><span class="label label-default" style="font-size : 1em;"><?php echo $infos['definition']['vendor'] ?></span></b>
                            {{Modèle ID :}}
                            <b><span class="label label-default" style="font-size : 1em;"><?php echo $infos['model_id'] ?></span></b>
                            {{Type :}}
                            <b><span class="label label-default" style="font-size : 1em;"><?php echo $infos['type'] ?></span></b>
                            <br />
                            {{Interview en cours :}}
                            <?php
                            if ($infos['interviewing']) {
                                echo '<b><span class="label label-warning" style="font-size : 1em;">{{Oui}}</span></b>';
                            } else {
                                echo '<b><span class="label label-success" style="font-size : 1em;">{{Non}}</span></b>';
                            }
                            ?>
                            {{Interview complete :}}
                            <?php
                            if ($infos['interview_completed']) {
                                echo '<b><span class="label label-success" style="font-size : 1em;">{{Oui}}</span></b>';
                            } else {
                                echo '<b><span class="label label-danger" style="font-size : 1em;">{{Non}}</span></b>';
                            }
                            ?>
                            <br />
                            {{Alimentation :}}
                            <b><span class="label label-default" style="font-size : 1em;"><?php echo $infos['power_source'] ?></span></b>
                            <br />
                            {{Software :}}
                            <b><span class="label label-default" style="font-size : 1em;"><?php echo $infos['software_build_id'] ?></span></b>
                            {{Support OTA :}}
                            <?php
                            if ($infos['definition']['supports_ota']) {
                                echo '<b><span class="label label-success" style="font-size : 1em;">{{Oui}}</span></b>';
                            } else {
                                echo '<b><span class="label label-info" style="font-size : 1em;">{{Non}}</span></b>';
                            }
                            ?>
                        </p>
                    </div>
                </div>
                <div class="panel panel-primary">
                    <div class="panel-heading">
                        <h4 class="panel-title"><i class="fas fa-info-circle"></i> {{Description}}</h4>
                    </div>
                    <div class="panel-body">
                        <b><span class="label label-default" style="font-size : 1em;"><?php echo $infos['definition']['description'] ?></span></b>
                        </p>
                    </div>
                </div>

                <?php if ($infos['definition']['supports_ota']) { ?>
                    <div class="panel panel-primary">
                        <div class="panel-heading">
                            <h4 class="panel-title"><i class="fas fa-info-circle"></i> {{Mise à jour (OTA)}}</h4>
                        </div>
                        <div class="panel-body">
                            <a class="btn btn-default" id="bt_checkOta"><i class="fas fa-check"></i> {{Verifier}}</a> <a class="btn btn-default" id="bt_updateOta"><i class="fas fa-sync"></i> {{Mettre à jour}}</a>
                        </div>
                    </div>
                <?php } ?>

                <?php
                foreach ($infos['endpoints'] as $endpoint_id => $endpoint) {
                    $endpointArray[] = $endpoint_id;
                    echo  '<div class="panel panel-primary">';
                    echo  '<div class="panel-heading">';
                    echo  '<h4 class="panel-title"><i class="fas fa-map-marker-alt"></i> {{Endpoints}} ' . $endpoint_id;
                    echo  '</h4>';
                    echo  '</div>';
                    echo  '<div class="panel-body">';
                    echo '<p>';
                    echo  '{{Cluster sortant :}}';
                    foreach ($endpoint['clusters']['output'] as $name) {
                        echo ' <span class="label label-info">' . $name . '</span>';
                    }
                    echo '<p>';
                    echo '</p>';
                    echo  '{{Cluster entrant :}}';
                    foreach ($endpoint['clusters']['input'] as $name) {
                        echo ' <span class="label label-primary">' . $name . '</span>';
                    }
                    echo '</p>';
                    echo  '</div>';
                    echo  '</div>';
                }
                ?>
            </fieldset>
        </form>
    </div>

    <div role="tabpanel" class="tab-pane" id="configuration">
        <table class="table table-bordered table-condensed">
            <thead>
                <tr>
                    <th>{{Nom}}</th>
                    <th>{{Valeur}}</th>
                    <th>{{Action}}</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $current_value = $bridge_info['config']['devices'][z2m::convert_from_addr($eqLogic->getLogicalId())];
                foreach ($infos['definition']['options'] as $option) {
                    if ($option['access'] == 1) {
                        continue;
                    }
                    echo '<tr>';
                    $value = (isset($current_value[$option['name']])) ? $current_value[$option['name']] : '';
                    if (is_object($value) || is_array($value)) {
                        $value = json_encode($value);
                    }
                    echo '<td>';
                    echo $option['description'];
                    echo '</td>';
                    echo '<td>';
                    echo z2m::createHtmlControl($option['name'], $option, $value);
                    echo '</td>';
                    echo '<td>';
                    echo '<a class="btn btn-success bt_validateOptions"><i class="fas fa-check"></i> {{Ok}}</a>';
                    echo '</td>';
                    echo '</tr>';
                }
                ?>
            </tbody>
        </table>
    </div>

    <div role="tabpanel" class="tab-pane" id="binding">

        <table class="table table-bordered table-condensed">
            <thead>
                <tr>
                    <th>{{Endpoint source}}</th>
                    <th>{{Cluster}}</th>
                    <th>{{Addr destination}}</th>
                    <th>{{Endpoint destination}}</th>
                    <th>{{Type}}</th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ($infos['endpoints'] as $endpoint_id => $endpoint) {
                    if (!isset($endpoint['bindings'])) {
                        continue;
                    }
                    foreach ($endpoint['bindings'] as $binding) {
                        $device = null;
                        if ($binding['target']['type'] == 'endpoint') {
                            $device = eqLogic::byLogicalId(z2m::convert_to_addr($binding['target']['ieee_address']), 'z2m');
                            $to = $binding['target']['ieee_address'] . '/' . $binding['target']['endpoint'];
                        }
                        if ($binding['target']['type'] == 'group') {
                            $device = eqLogic::byLogicalId('group_' . z2m::convert_to_addr($binding['target']['id']), 'z2m');
                            $to = $device->getConfiguration('friendly_name');
                        }

                        echo '<tr data-cluster="' . $binding['cluster'] . '" data-from="' . $infos['ieee_address'] . '" data-to="' . $to . '">';
                        echo '<td>';
                        echo $endpoint_id;
                        echo '</td>';
                        echo '<td>';
                        echo $binding['cluster'];
                        echo '</td>';
                        echo '<td>';
                        if ($binding['target']['type'] == 'endpoint') {
                            echo $binding['target']['ieee_address'];
                        }
                        if ($binding['target']['type'] == 'group') {
                            echo $binding['target']['id'];
                        }
                        if (is_object($device)) {
                            echo ' / ' . $device->getHumanName();
                        } elseif ($binding['target']['type'] == 'endpoint' && $bridge_info['coordinator']['ieee_address'] == $binding['target']['ieee_address']) {
                            echo ' / {{Coordinateur}}';
                        }
                        echo '</td>';
                        echo '<td>';
                        echo $binding['target']['endpoint'];
                        echo '</td>';
                        echo '<td>';
                        echo $binding['target']['type'];
                        echo '</td>';
                        echo '<td>';
                        echo '<a class="btn btn-warning bt_removeBinding"><i class="fas fa-trash"></i> {{Supprimer}}</a>';
                        echo '</div>';
                        echo '</tr>';
                    }
                }
                ?>
            </tbody>
        </table>
        <form class="form-horizontal">
            <fieldset>
                <legend>{{Ajouter binding}}</legend>
                <div class="form-group">
                    <label class="col-sm-2 control-label">{{Endpoint source}}</label>
                    <div class="col-sm-3">
                        <select class="form-control" id="sel_bindingSourceEndpoint">
                            <option value="-1">{{Aucun}}</option>
                            <?php
                            foreach ($infos['endpoints'] as $endpoint_id => $endpoint) {
                                if ($endpoint_id == 242) {
                                    continue;
                                }
                                echo  '<option value="' . $endpoint_id . '">' . $endpoint_id . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">{{Destination}}</label>
                    <div class="col-sm-3">
                        <select class="form-control" id="sel_bindingTarget">
                            <option value="-1">{{Aucun}}</option>
                            <?php
                            echo  '<option value="Coordinator/1">{{Coordinateur}}</option>';
                            echo '<optgroup label="{{Equipement}}">';
                            foreach ($devices as $device) {
                                if ($eqLogic->getId() == $device->getId() || $device->getConfiguration('isgroup', 0) == 1) {
                                    continue;
                                }
                                $device_infos = z2m::getDeviceInfo($device->getLogicalId());
                                foreach ($device_infos['endpoints'] as $endpoint_id => $endpoint) {
                                    if ($endpoint_id == 242) {
                                        continue;
                                    }
                                    echo  '<option value="' . $device_infos['ieee_address'] . '/' . $endpoint_id . '">' . $device->getHumanName() . ' / endpoint ' . $endpoint_id . '</option>';
                                }
                            }
                            echo '</optgroup>';
                            echo '<optgroup label="{{Groupe}}">';
                            foreach ($devices as $device) {
                                if ($eqLogic->getId() == $device->getId() || $device->getConfiguration('isgroup', 0) == 0) {
                                    continue;
                                }
                                echo  '<option value="' . $device->getConfiguration('friendly_name') . '">' . $device->getHumanName() . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">{{Cluster}}</label>
                    <div class="col-sm-10">
                        <?php
                        foreach ($infos['endpoints'] as $endpoint_id => $endpoint) {
                            echo '<div class="bindingEndpoint ' . $endpoint_id . '" style="display:none;">';
                            foreach ($endpoint['clusters']['output'] as $name) {
                                if (!in_array($name, array('genScenes', 'genOnOff', 'genLevelCtrl', 'lightingColorCtrl', 'closuresWindowCovering'))) {
                                    continue;
                                }
                                echo ' <label class="checkbox-inline"><input type="checkbox" class="deviceAddBindingCluster' . $endpoint_id . '" data-name="' . $name . '" checked>' . $name . '</label></span>';
                            }
                            echo '</div>';
                        }
                        ?>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">{{Ajouter}}</label>
                    <div class="col-sm-10">
                        <a class="btn btn-success" id="bt_deviceAddBinding"><i class="fas fa-check"></i> {{Valider}}</a>
                    </div>
                </div>
            </fieldset>
        </form>
    </div>

    <div role="tabpanel" class="tab-pane" id="reporting">
        <table class="table table-bordered table-condensed">
            <thead>
                <tr>
                    <th>{{Cluster}}</th>
                    <th>{{Attribute}}</th>
                    <th>{{Min report time}}</th>
                    <th>{{Max report time}}</th>
                    <th>{{Report change}}</th>
                    <th>{{Action}}</th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ($infos['endpoints'] as $endpoint_id => $endpoint) {
                    if (!isset($endpoint['configured_reportings'])) {
                        continue;
                    }
                    foreach ($endpoint['configured_reportings'] as $reporting) {
                        echo '<tr data-cluster="' . $reporting['cluster'] . '" data-attribute="' . $reporting['attribute'] . '">';
                        echo '<td>';
                        echo $reporting['cluster'];
                        echo '</td>';
                        echo '<td>';
                        echo $reporting['attribute'];
                        echo '</td>';
                        echo '<td>';
                        echo '<input type="number" class="form-control minReportTime" value="' . $reporting['minimum_report_interval'] . '"/>';
                        echo '</td>';
                        echo '<td>';
                        echo '<input type="number" class="form-control maxReportTime" value="' . $reporting['maximum_report_interval'] . '"/>';
                        echo '</td>';
                        echo '<td>';
                        if (is_array($reporting['reportable_change'])) {
                            $reporting['reportable_change'] = json_encode($reporting['reportable_change']);
                        }
                        echo '<input type="number" class="form-control reportable_change" value="' . $reporting['reportable_change'] . '"/>';
                        echo '</td>';
                        echo '<td>';
                        echo '<a class="btn btn-success bt_validateReporting"><i class="fas fa-check"></i> {{Ok}}</a>';
                        echo '</div>';
                        echo '</tr>';
                    }
                }
                ?>
            </tbody>
        </table>
    </div>

    <div role="tabpanel" class="tab-pane" id="rawNodeTab">
        <pre><?php echo json_encode($infos, JSON_PRETTY_PRINT); ?></pre>
    </div>

</div>

<script>
    $('#bt_refreshDevice').off('click').on('click', function() {
        $('#md_modal').dialog({
            title: "{{Configuration du noeud}}"
        }).load('index.php?v=d&plugin=z2m&modal=device&id=' + z2m_device_id).dialog('open');
    });

    $('#bt_deviceAddBinding').off('click').on('click', function() {
        if ($('#sel_bindingSourceEndpoint').value() == -1) {
            $('#div_alert').showAlert({
                message: '{{Aucun endpoint source de sélectionné}}',
                level: 'danger'
            });
            return;
        }
        if ($('#sel_bindingTarget').value() == -1) {
            $('#div_alert').showAlert({
                message: '{{Aucune destination de sélectionné}}',
                level: 'danger'
            });
            return;
        }
        clusters = []
        $('.deviceAddBindingCluster' + $('#sel_bindingSourceEndpoint').value()).each(function() {
            if ($(this).value() == 1) {
                clusters.push($(this).attr('data-name'));
            }
        });
        if (clusters.length == 0) {
            $('#div_alert').showAlert({
                message: '{{Aucun cluster de sélectionné}}',
                level: 'danger'
            });
            return;
        }
        jeedom.z2m.device.bind({
            instance: z2m_device_instance,
            options: {
                from: jeedom.z2m.utils.convert_from_addr(z2m_device_ieee) + '/' + $('#sel_bindingSourceEndpoint').value(),
                to: $('#sel_bindingTarget').value(),
                cluster: clusters
            },
            error: function(error) {
                $('#div_alert').showAlert({
                    message: error.message,
                    level: 'danger'
                });
            },
            success: function() {
                $('#div_alert').showAlert({
                    message: '{{Demande de d\'ajout du binding envoyée}}',
                    level: 'success'
                });
            }
        });
    });

    $('#sel_bindingSourceEndpoint').off('change').on('change', function() {
        $('.bindingEndpoint').hide();
        if ($(this).value() != '') {
            $('.bindingEndpoint.' + $(this).value()).show();
        }
    })

    $('.bt_removeBinding').off('click').on('click', function() {
        let tr = $(this).closest('tr');
        jeedom.z2m.device.unbind({
            instance: z2m_device_instance,
            options: {
                from: tr.attr('data-from'),
                to: tr.attr('data-to'),
                cluster: tr.attr('data-cluster')
            },
            error: function(error) {
                $('#div_alert').showAlert({
                    message: error.message,
                    level: 'danger'
                });
            },
            success: function() {
                $('#div_alert').showAlert({
                    message: '{{Demande de suppression du binding envoyée}}',
                    level: 'success'
                });
            }
        });
    });

    $('#bt_checkOta').off('click').on('click', function() {
        jeedom.z2m.device.ota_check({
            instance: z2m_device_instance,
            id: jeedom.z2m.utils.convert_from_addr(z2m_device_ieee),
            error: function(error) {
                $('#div_alert').showAlert({
                    message: error.message,
                    level: 'danger'
                });
            },
            success: function() {
                $('#div_alert').showAlert({
                    message: '{{Demande de vérification de mise à jour envoyée}}',
                    level: 'success'
                });
            }
        });
    });

    $('.bt_validateOptions').off('click').on('click', function() {
        let input = $(this).closest('tr').find('.valueResult');
        let options = {};
        if (input.attr('type') == 'checkbox') {
            options[input.attr('data-name')] = (input.value() == '1');
        } else if (input.attr('type') == 'number') {
            options[input.attr('data-name')] = parseInt(input.value());
        } else {
            options[input.attr('data-name')] = input.value();
        }
        if (parseInt(options[input.attr('data-name')]) != NaN) {
            options[input.attr('data-name')] = parseInt(options[input.attr('data-name')]);
        }
        jeedom.z2m.device.setOptions({
            instance: z2m_device_instance,
            options: {
                id: jeedom.z2m.utils.convert_from_addr(z2m_device_ieee),
                options: options
            },
            error: function(error) {
                $('#div_alert').showAlert({
                    message: error.message,
                    level: 'danger'
                });
            },
            success: function() {
                $('#div_alert').showAlert({
                    message: '{{Paramètre envoyé au module}}',
                    level: 'success'
                });
            }
        });
    });

    $('.bt_validateReporting').off('click').on('click', function() {
        let tr = $(this).closest('tr');
        let options = {
            id: jeedom.z2m.utils.convert_from_addr(z2m_device_ieee),
            cluster: tr.attr('data-cluster'),
            attribute: tr.attr('data-attribute'),
            minimum_report_interval: tr.find('.minReportTime').value(),
            maximum_report_interval: tr.find('.maxReportTime').value(),
            reportable_change: tr.find('.reportable_change').value()
        }
        jeedom.z2m.device.configure_reporting({
            instance: z2m_device_instance,
            options: options,
            error: function(error) {
                $('#div_alert').showAlert({
                    message: error.message,
                    level: 'danger'
                });
            },
            success: function() {
                $('#div_alert').showAlert({
                    message: '{{Paramètre envoyé au module}}',
                    level: 'success'
                });
            }
        });
    });
</script>
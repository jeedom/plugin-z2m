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
$bridge_info = z2m::getDeviceInfo('bridge' . $eqLogic->getConfiguration('instance', 1));
sendVarToJS('z2m_device_id', $eqLogic->getId());
sendVarToJS('z2m_device_instance', $eqLogic->getConfiguration('instance'));
sendVarToJS('z2m_device_ieee', $eqLogic->getLogicalId());
?>
<ul class="nav nav-tabs" role="tablist">
    <li role="presentation" class="active"><a href="#infoNodeTab" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-info"></i> {{Information}}</a></li>
    <li role="presentation"><a href="#configuration" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-info"></i> {{Configuration}}</a></li>
    <li role="presentation"><a href="#reporting" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-bars"></i> {{Reporting}}</a></li>
    <li role="presentation"><a href="#rawNodeTab" aria-controls="profile" role="tab" data-toggle="tab"><i class="fas fa-list-alt"></i> {{Informations brutes}}</a></li>
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
        <form class="form-horizontal">
            <fieldset>
                <br>
                <?php
                $current_value = $bridge_info['config']['devices'][z2m::convert_from_addr($eqLogic->getLogicalId())];
                foreach ($infos['definition']['options'] as $option) {
                    if ($option['access'] == 1) {
                        continue;
                    }
                    $default_value = (isset($current_value[$option['name']])) ? $current_value[$option['name']] : '';
                    if (is_object($default_value) || is_array($default_value)) {
                        $default_value = json_encode($default_value);
                    }
                    echo '<div class="form-group">';
                    echo '<label class="col-sm-8 control-label">' . $option['description'];
                    echo '</label>';
                    echo '<div class="col-sm-3">';
                    switch ($option['type']) {
                        case 'numeric':
                            $min = '';
                            $max = '';
                            if (isset($option['value_min'])) {
                                $min = 'min=' . $option['value_min'];
                            }
                            if (isset($option['value_max'])) {
                                $max = 'max=' . $option['value_max'];
                            }
                            echo '<input type="number" data-name="' . $option['name'] . '" class="form-control" ' . $min . ' ' . $max . ' value="' . $default_value . '" />';
                            break;
                        case 'list':
                            echo '<input type="text" data-name="' . $option['name'] . '" class="form-control" value="' . $default_value . '" />';
                            break;
                    }
                    echo '</div>';
                    echo '<div class="col-sm-1">';
                    echo '<a class="btn btn-success bt_validateOptions"><i class="fas fa-check"></i> {{Ok}}</a>';
                    echo '</div>';
                    echo '</div>';
                }
                ?>
            </fieldset>
        </form>
    </div>

    <div role="tabpanel" class="tab-pane" id="reporting">
        <form class="form-horizontal">
            <fieldset>
                <br>
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
                                echo '<input type="number" class="form-control reportable_change" value="' . $reporting['reportable_change'] . '"/>';
                                echo '</td>';
                                echo '<td>';
                                echo '<div class="col-sm-1">';
                                echo '<a class="btn btn-success bt_validateReporting"><i class="fas fa-check"></i> {{Ok}}</a>';
                                echo '</div>';
                                echo '</tr>';
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </fieldset>
        </form>
    </div>

    <div role="tabpanel" class="tab-pane" id="rawNodeTab">
        <pre><?php echo json_encode($infos, JSON_PRETTY_PRINT); ?></pre>
    </div>

</div>

<script>
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
        let input = $(this).parent().parent().find('input');
        let options = {};
        options[input.attr('data-name')] = input.value();
        jeedom.z2m.device.setOptions({
            instance: z2m_device_instance,
            id: jeedom.z2m.utils.convert_from_addr(z2m_device_ieee),
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
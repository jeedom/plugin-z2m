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

$('#bt_includeDeviceByCode').off('click').on('click',function(){
  var inputOptions = [];
  for(var i in z2m_instances){
    if(z2m_instances[i].enable != 1){
      continue;
    }
    inputOptions.push({value : z2m_instances[i].id,text : z2m_instances[i].name});
  }
  bootbox.prompt({
    title: "{{Ajouter un équipement par code sur ?}}",
    value : inputOptions[0].value,
    inputType: 'select',
    inputOptions:inputOptions,
    callback: function (instance_result) {
      if(instance_result === null){
        return;
      }
      bootbox.prompt("{{Code ?}}", function(code){
        jeedom.z2m.bridge.addByCode({
          instance:instance_result,
          code : code,
          error: function (error) {
            $('#div_alert').showAlert({message: error.message, level: 'danger'});
          },
          success: function () {
            $('#div_alert').showAlert({message: '{{Demande d\'ajout de l\'équipement par code envoyée avec succes}}', level: 'success'});
          }
        });
      });
    }
  });
});

$('#bt_addGroup').off('click').on('click',function(){
  var inputOptions = [];
  for(var i in z2m_instances){
    if(z2m_instances[i].enable != 1){
      continue;
    }
    inputOptions.push({value : z2m_instances[i].id,text : z2m_instances[i].name});
  }
  bootbox.prompt({
    title: "{{Ajouter un groupe sur ?}}",
    value : inputOptions[0].value,
    inputType: 'select',
    inputOptions:inputOptions,
    callback: function (instance_result) {
      if(instance_result === null){
        return;
      }
      bootbox.prompt("{{Nom du groupe ?}}", function(group){
        jeedom.z2m.group.add({
          instance:instance_result,
          name : group,
          error: function (error) {
            $('#div_alert').showAlert({message: error.message, level: 'danger'});
          },
          success: function () {
            $('#div_alert').showAlert({message: '{{Demande du groupe envoyée avec succes}}', level: 'success'});
          }
        });
      });
    }
  });
});

$('body').off('z2m::includeDevice').on('z2m::includeDevice', function (_event, _options) {
  if (modifyWithoutSave) {
    $('#div_alert').showAlert({
      message: '{{Un périphérique vient d\'être inclu/exclu. Veuillez réactualiser la page}}',
      level: 'warning'
    });
  } else if (_options != '') {
      window.location.href = 'index.php?v=d&p=z2m&m=z2m&id=' + _options;
  }
});

$('#bt_showZ2mDevice').off('click').on('click', function () {
  if ($('.eqLogicAttr[data-l1key=id]').value() in devices_attr) {
    if (devices_attr[$('.eqLogicAttr[data-l1key=id]').value()]['isgroup']==0) {
      $('#md_modal').dialog({title: "{{Configuration du noeud}}"}).load('index.php?v=d&plugin=z2m&modal=device&id='+$('.eqLogicAttr[data-l1key=id]').value()).dialog('open');
    } else {
      $('#md_modal').dialog({title: "{{Configuration du groupe}}"}).load('index.php?v=d&plugin=z2m&modal=group&id='+$('.eqLogicAttr[data-l1key=id]').value()).dialog('open');
    }
  }
});

$('#bt_z2mNetwork').off('click').on('click', function () {
  $('#md_modal').dialog({title: "{{Configuration du réseaux}}"}).load('index.php?v=d&plugin=z2m&modal=network').dialog('open');
});

function printEqLogic(_eqLogic) {
  $('#img_device').attr("src", $('.eqLogicDisplayCard.active img').attr('src'));
  if ($('.eqLogicAttr[data-l1key=id]').value() in devices_attr){
	console.log(devices_attr[$('.eqLogicAttr[data-l1key=id]').value()])
    if ('multipleEndpoints' in devices_attr[$('.eqLogicAttr[data-l1key=id]').value()] && devices_attr[$('.eqLogicAttr[data-l1key=id]').value()]['multipleEndpoints']==1){
       $('.childCreate').show();
    } else {
       $('.childCreate').hide();
    }
  }
  return _eqLogic;
}

$('.changeIncludeState').off('click').on('click', function () {
  var inputOptions = [];
  for(var i in z2m_instances){
    if(z2m_instances[i].enable != 1){
      continue;
    }
    inputOptions.push({value : z2m_instances[i].id,text : z2m_instances[i].name});
  }
  bootbox.prompt({
    title: "{{Passage en inclusion sur}} ?",
    value : inputOptions[0].value,
    inputType: 'select',
    inputOptions:inputOptions,
    callback: function (instance_result) {
      if(instance_result === null){
        return;
      }
      jeedom.z2m.bridge.include({
        instance:instance_result,
        error: function (error) {
          $('#div_alert').showAlert({message: error.message, level: 'danger'});
        },
        success: function () {
          $('#div_alert').showAlert({message: '{{Lancement du mode inclusion sur }} '+z2m_instances[instance_result].name, level: 'success'});
        }
      });
    }
  });
});


$('#bt_syncEqLogic').off('click').on('click', function () {
  sync();
});

/* Permet la réorganisation des commandes dans l'équipement */
$("#table_cmd").sortable({
  axis: "y",
  cursor: "move",
  items: ".cmd",
  placeholder: "ui-state-highlight",
  tolerance: "intersect",
  forcePlaceholderSize: true
})

/* Fonction permettant l'affichage des commandes dans l'équipement */
function addCmdToTable(_cmd) {
  if (!isset(_cmd)) {
    var _cmd = {configuration: {}};
  }
  if (!isset(_cmd.configuration)) {
    _cmd.configuration = {};
  }
  var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
  tr += '<td>';
  tr += '<div class="row">';
  tr += '<div class="col-sm-6">';
  tr += '<a class="cmdAction btn btn-default btn-sm" data-l1key="chooseIcon"><i class="fa fa-flag"></i> Icône</a>';
  tr += '<span class="cmdAttr" data-l1key="display" data-l2key="icon" style="margin-left : 10px;"></span>';
  tr += '</div>';
  tr += '<div class="col-sm-6">';
  tr += '<input class="cmdAttr form-control input-sm" data-l1key="name">';
  tr += '</div>';
  tr += '</div>';
  tr += '<select class="cmdAttr form-control input-sm" data-l1key="value" style="display : none;margin-top : 5px;" title="La valeur de la commande vaut par défaut la commande">';
  tr += '<option value="">Aucune</option>';
  tr += '</select>';
  tr += '</td>';
  tr += '<td>';
  tr += '<input class="cmdAttr form-control input-sm" data-l1key="id" style="display : none;">';
  tr += '<span class="type" type="' + init(_cmd.type) + '">' + jeedom.cmd.availableType() + '</span>';
  tr += '<span class="subType" subType="' + init(_cmd.subType) + '"></span>';
  tr += '</td>';
  tr += '<td><input class="cmdAttr form-control input-sm" data-l1key="logicalId" value="0" style="width : 70%; display : inline-block;" placeholder="{{Commande}}"><br/>';
  tr += '</td>';
  
  tr += '<td>';
  
  tr += '<input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="returnStateValue" placeholder="{{Valeur retour d\'état}}" style="width:48%;display:inline-block;">';
  tr += '<input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="returnStateTime" placeholder="{{Durée avant retour d\'état (min)}}" style="width:48%;display:inline-block;margin-left:2px;">';
  tr += '<select class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="updateCmdId" style="display : none;" title="Commande d\'information à mettre à jour">';
  tr += '<option value="">Aucune</option>';
  tr += '</select>';
  tr += '</td>';
  tr += '<td>';
  tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="minValue" placeholder="{{Min}}" title="{{Min}}" style="width:30%;display:inline-block;">';
  tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="maxValue" placeholder="{{Max}}" title="{{Max}}" style="width:30%;display:inline-block;">';
  tr += '<input class="cmdAttr form-control input-sm" data-l1key="unite" placeholder="Unité" title="{{Unité}}" style="width:30%;display:inline-block;margin-left:2px;">';
  tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="listValue" placeholder="{{Liste de valeur|texte séparé par ;}}" title="{{Liste}}">';
  tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isVisible" checked/>{{Afficher}}</label></span> ';
  tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isHistorized" checked/>{{Historiser}}</label></span> ';
  tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="display" data-l2key="invertBinary"/>{{Inverser}}</label></span> ';
  tr += '</td>';
  tr += '<td>';
  tr += '<span class="cmdAttr" data-l1key="htmlstate"></span>'; 
  tr += '</td>';
  tr += '<td>';
  if (is_numeric(_cmd.id)) {
    tr += '<a class="btn btn-default btn-xs cmdAction" data-action="configure"><i class="fas fa-cogs"></i></a> ';
    tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fa fa-rss"></i> {{Tester}}</a>';
  }
  tr += '<i class="fas fa-minus-circle pull-right cmdAction cursor" data-action="remove"></i>';
  tr += '</td>';
  tr += '</tr>';
  $('#table_cmd tbody').append(tr);
  var tr = $('#table_cmd tbody tr').last();
  jeedom.eqLogic.buildSelectCmd({
    id: $('.eqLogicAttr[data-l1key=id]').value(),
    filter: {type: 'info'},
    error: function (error) {
      $('#div_alert').showAlert({message: error.message, level: 'danger'});
    },
    success: function (result) {
      tr.find('.cmdAttr[data-l1key=value]').append(result);
      tr.setValues(_cmd, '.cmdAttr');
      jeedom.cmd.changeType(tr, init(_cmd.subType));
    }
  });
}

$('#bt_childCreate').off('click').on('click', function () {
  bootbox.prompt("{{Vous voulez créer un enfant sur quel endpoint ? (attention il ne faut jamais supprimer le device père). Si l'enfant existe il sera mis à jour avec les commandes manquantes.}}", function(endpoint){
    if (endpoint) {
      jeedom.z2m.device.childCreate({
        id : $('.eqLogicAttr[data-l1key=id]').value(),
        endpoint : endpoint,
        error: function (error) {
          $('#div_alert').showAlert({message: error.message, level: 'danger'});
        },
        success: function () {
          $('#div_alert').showAlert({message: '{{Enfant créé avec succès}}', level: 'success'});
          window.location.href = 'index.php?v=d&p=z2m&m=z2m';
        }
      });
    }
  });
});


function sync(){
  $('#div_alert').showAlert({message: '{{Synchronisation en cours}}', level: 'warning'});
  $.ajax({
    type: "POST",
    url: "plugins/z2m/core/ajax/z2m.ajax.php",
    data: {
      action: "sync",
    },
    dataType: 'json',
    global: false,
    error: function (request, status, error) {
      handleAjaxError(request, status, error);
    },
    success: function (data) {
      if (data.state != 'ok') {
        $('#div_alert').showAlert({message: data.result, level: 'danger'});
        return;
      }
      $('#div_alert').showAlert({message: '{{Operation realisee avec succes}}', level: 'success'});
      window.location.reload();
    }
  });
}
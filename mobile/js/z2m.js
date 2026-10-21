"use strict"

$('body').attr('data-page', 'z2m')

$('#searchContainer').hide()

var $bottompanel_routerlist

function initz2mpromptRouter() {
  $bottompanel_routerlist = $('#changeIncludeStateEnable')
  $bottompanel_routerlist.empty()
  $bottompanel_routerlist.append('<a class="ui-bottom-sheet-link ui-btn ui-btn-inline waves-effect waves-button routers" data-id="all"><i class="fas fa-plus" style="color:grey"></i> Tout</a>')
  $bottompanel_routerlist.append('<a class="ui-bottom-sheet-link ui-btn ui-btn-inline waves-effect waves-button routers" data-id="Coordinator"><i class="fas fa-plus" style="color:#a65ba6"></i> Coordinateur</a>')
  for(var i in z2m_devices){
    if(z2m_devices[i].isgroup == 1 || z2m_devices[i].isChild == 1 || z2m_devices[i].device_type != 'Router'){
      continue;
    }
    $bottompanel_routerlist.append('<a class="ui-bottom-sheet-link ui-btn ui-btn-inline waves-effect waves-button routers" data-id=' + z2m_devices[i].ieee + '> <i class="fas fa-plus" style="color:#e5e500"></i> ' + z2m_devices[i].HumanName + '</a>')
  }
  $bottompanel_routerlist.append('<br>')

  $bottompanel_routerlist = $('#changeIncludeStateDisable')
  $bottompanel_routerlist.empty()
  $bottompanel_routerlist.append('<a class="ui-bottom-sheet-link ui-btn ui-btn-inline waves-effect waves-button routers" data-id="all"><i class="fas fa-minus" style="color:grey"></i> Tout</a>')
  $bottompanel_routerlist.append('<a class="ui-bottom-sheet-link ui-btn ui-btn-inline waves-effect waves-button routers" data-id="Coordinator"><i class="fas fa-minus" style="color:#a65ba6"></i> Coordinateur</a>')
  for(var i in z2m_devices){
    if(z2m_devices[i].isgroup == 1 || z2m_devices[i].isChild == 1 || z2m_devices[i].device_type != 'Router'){
      continue;
    }
    $bottompanel_routerlist.append('<a class="ui-bottom-sheet-link ui-btn ui-btn-inline waves-effect waves-button routers" data-id=' + z2m_devices[i].ieee + '> <i class="fas fa-minus" style="color:#e5e500"></i> ' + z2m_devices[i].HumanName + '</a>')
  }
  $bottompanel_routerlist.append('<br>')
}

$('#changeIncludeStateEnable').off('click', '.routers').on('click', '.routers', function(e) {
    jeedom.z2m.bridge.include({
      id: $(this).attr('data-id'),
      mode : 1,
      error: function(error) {
        $('#div_alert').showAlert({message: error.message, level: 'danger'})
      },
      success: function() {
        $('#div_alert').showAlert({message: '{{Lancement du mode inclusion}}', level: 'success'})
        $('#changeIncludeStateEnable').hide()
      }
    })
})

$('#changeIncludeStateDisable').off('click', '.routers').on('click', '.routers', function(e) {
      jeedom.z2m.bridge.include({
        id: $(this).attr('data-id'),
        mode : 0,
        error: function(error) {
          $('#div_alert').showAlert({message: error.message, level: 'danger'})
        },
        success: function() {
          $('#div_alert').showAlert({message: '{{Désactivation du mode inclusion}}', level: 'success'})
          $('#changeIncludeStateDisable').hide()
        }
      })
  })

$('body').off('z2m::includeDevice').on('z2m::includeDevice', function(_event, _options) {
if (modifyWithoutSave) {
  $('#div_alert').showAlert({
    message: '{{Un périphérique vient d\'être inclu/exclu. Veuillez réactualiser la page}}',
    level: 'warning'
  });
} else if (_options != '') {
    window.location.href = 'index.php?v=d&p=z2m&m=z2m&id=' + _options;
}
})
initz2mpromptRouter();

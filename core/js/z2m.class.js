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


jeedom.z2m = function() {};
jeedom.z2m.bridge = function() {};
jeedom.z2m.device = function() {};
jeedom.z2m.utils = function() {};
jeedom.z2m.group = function() {};

jeedom.z2m.utils.promptRouter = function(_text,_callback){
  var inputOptions = [];
  inputOptions.push({value : 'coordinator',text : 'Coordinateur'});
  for(var i in z2m_devices){
    if(z2m_devices[i].isgroup == 1 || z2m_devices[i].isChild == 1 || z2m_devices[i].device_type != 'router'){
      continue;
    }
    inputOptions.push({value : i,text : z2m_devices[i].HumanNameFull});
  }
  bootbox.prompt({
    title: _text,
    value : inputOptions[0].value,
    inputType: 'select',
    inputOptions:inputOptions,
    callback: function (result) {
      if(result === null){
        return;
      }
      _callback(result)
    }
  });
}

jeedom.z2m.utils.convert_to_addr = function(_addr){
  return _addr.replace('0x', '').match(/.{1,2}/g).join(':')
}

jeedom.z2m.utils.convert_from_addr = function(_addr){
  return '0x'+_addr.replaceAll(':', '')
}

jeedom.z2m.firmwareUpdate = function(_params){
  var paramsRequired = ['port','sub_controller','firmware'];
  var paramsSpecifics = {};
  try {
    jeedom.private.checkParamsRequired(_params || {}, paramsRequired);
  } catch (e) {
    (_params.error || paramsSpecifics.error || jeedom.private.default_params.error)(e);
    return;
  }
  var params = $.extend({}, jeedom.private.default_params, paramsSpecifics, _params || {});
  var paramsAJAX = jeedom.private.getParamsAJAX(params);
  paramsAJAX.url = 'plugins/z2m/core/ajax/z2m.ajax.php';
  paramsAJAX.data = {
    action: 'firmwareUpdate',
    port : _params.port,
    sub_controller : _params.sub_controller,
    gateway : _params.gateway,
    firmware : _params.firmware
  };
  $.ajax(paramsAJAX);
}

jeedom.z2m.bridge.include = function(_params){
  var paramsRequired = [];
  var paramsSpecifics = {};
  try {
    jeedom.private.checkParamsRequired(_params || {}, paramsRequired);
  } catch (e) {
    (_params.error || paramsSpecifics.error || jeedom.private.default_params.error)(e);
    return;
  }
  var params = $.extend({}, jeedom.private.default_params, paramsSpecifics, _params || {});
  var paramsAJAX = jeedom.private.getParamsAJAX(params);
  paramsAJAX.url = 'plugins/z2m/core/ajax/z2m.ajax.php';
  paramsAJAX.data = {
    action: 'include',
    id : _params.id,
  };
  $.ajax(paramsAJAX);
}


jeedom.z2m.bridge.options = function(_params){
  var paramsRequired = ['options'];
  var paramsSpecifics = {};
  try {
    jeedom.private.checkParamsRequired(_params || {}, paramsRequired);
  } catch (e) {
    (_params.error || paramsSpecifics.error || jeedom.private.default_params.error)(e);
    return;
  }
  var params = $.extend({}, jeedom.private.default_params, paramsSpecifics, _params || {});
  var paramsAJAX = jeedom.private.getParamsAJAX(params);
  paramsAJAX.url = 'plugins/z2m/core/ajax/z2m.ajax.php';
  paramsAJAX.data = {
    action: 'publish',
    topic : '/bridge/request/options',
    message : JSON.stringify({options:_params.options})
  };
  $.ajax(paramsAJAX);
}

jeedom.z2m.bridge.sync = function(_params){
  var paramsRequired = [];
  var paramsSpecifics = {};
  try {
    jeedom.private.checkParamsRequired(_params || {}, paramsRequired);
  } catch (e) {
    (_params.error || paramsSpecifics.error || jeedom.private.default_params.error)(e);
    return;
  }
  var params = $.extend({}, jeedom.private.default_params, paramsSpecifics, _params || {});
  var paramsAJAX = jeedom.private.getParamsAJAX(params);
  paramsAJAX.url = 'plugins/z2m/core/ajax/z2m.ajax.php';
  paramsAJAX.data = {
    action: 'sync'
  };
  $.ajax(paramsAJAX);
}

jeedom.z2m.bridge.updateNetworkMap = function(_params){
  var paramsRequired = [];
  var paramsSpecifics = {};
  try {
    jeedom.private.checkParamsRequired(_params || {}, paramsRequired);
  } catch (e) {
    (_params.error || paramsSpecifics.error || jeedom.private.default_params.error)(e);
    return;
  }
  var params = $.extend({}, jeedom.private.default_params, paramsSpecifics, _params || {});
  var paramsAJAX = jeedom.private.getParamsAJAX(params);
  paramsAJAX.url = 'plugins/z2m/core/ajax/z2m.ajax.php';
  paramsAJAX.data = {
    action: 'publish',
    topic : '/bridge/request/networkmap',
    message : '{"type": "raw", "routes": false}'
  };
  $.ajax(paramsAJAX);
}

jeedom.z2m.bridge.backup = function(_params){
  var paramsRequired = [];
  var paramsSpecifics = {};
  try {
    jeedom.private.checkParamsRequired(_params || {}, paramsRequired);
  } catch (e) {
    (_params.error || paramsSpecifics.error || jeedom.private.default_params.error)(e);
    return;
  }
  var params = $.extend({}, jeedom.private.default_params, paramsSpecifics, _params || {});
  var paramsAJAX = jeedom.private.getParamsAJAX(params);
  paramsAJAX.url = 'plugins/z2m/core/ajax/z2m.ajax.php';
  paramsAJAX.data = {
    action: 'publish',
    topic : '/bridge/request/backup'
  };
  $.ajax(paramsAJAX);
}

jeedom.z2m.bridge.restart = function(_params){
  var paramsRequired = [];
  var paramsSpecifics = {};
  try {
    jeedom.private.checkParamsRequired(_params || {}, paramsRequired);
  } catch (e) {
    (_params.error || paramsSpecifics.error || jeedom.private.default_params.error)(e);
    return;
  }
  var params = $.extend({}, jeedom.private.default_params, paramsSpecifics, _params || {});
  var paramsAJAX = jeedom.private.getParamsAJAX(params);
  paramsAJAX.url = 'plugins/z2m/core/ajax/z2m.ajax.php';
  paramsAJAX.data = {
    action: 'publish',
    topic : '/bridge/request/restart'
  };
  $.ajax(paramsAJAX);
}

jeedom.z2m.bridge.addByCode = function(_params){
  var paramsRequired = ['code'];
  var paramsSpecifics = {};
  try {
    jeedom.private.checkParamsRequired(_params || {}, paramsRequired);
  } catch (e) {
    (_params.error || paramsSpecifics.error || jeedom.private.default_params.error)(e);
    return;
  }
  var params = $.extend({}, jeedom.private.default_params, paramsSpecifics, _params || {});
  var paramsAJAX = jeedom.private.getParamsAJAX(params);
  paramsAJAX.url = 'plugins/z2m/core/ajax/z2m.ajax.php';
  paramsAJAX.data = {
    action: 'publish',
    topic : '/bridge/request/install_code/add',
    message :  JSON.stringify({value : _params.code})
  };
  $.ajax(paramsAJAX);
}

jeedom.z2m.device.ota_check = function(_params){
  var paramsRequired = ['id'];
  var paramsSpecifics = {};
  try {
    jeedom.private.checkParamsRequired(_params || {}, paramsRequired);
  } catch (e) {
    (_params.error || paramsSpecifics.error || jeedom.private.default_params.error)(e);
    return;
  }
  var params = $.extend({}, jeedom.private.default_params, paramsSpecifics, _params || {});
  var paramsAJAX = jeedom.private.getParamsAJAX(params);
  paramsAJAX.url = 'plugins/z2m/core/ajax/z2m.ajax.php';
  paramsAJAX.data = {
    action: 'publish',
    topic : '/bridge/request/device/ota_update/check',
    message : JSON.stringify({id:_params.id}),
  };
  $.ajax(paramsAJAX);
}

jeedom.z2m.device.ota_update = function(_params){
  var paramsRequired = ['id'];
  var paramsSpecifics = {};
  try {
    jeedom.private.checkParamsRequired(_params || {}, paramsRequired);
  } catch (e) {
    (_params.error || paramsSpecifics.error || jeedom.private.default_params.error)(e);
    return;
  }
  var params = $.extend({}, jeedom.private.default_params, paramsSpecifics, _params || {});
  var paramsAJAX = jeedom.private.getParamsAJAX(params);
  paramsAJAX.url = 'plugins/z2m/core/ajax/z2m.ajax.php';
  paramsAJAX.data = {
    action: 'publish',
    topic : '/bridge/request/device/ota_update/update',
    message : JSON.stringify({id:_params.id}),
  };
  $.ajax(paramsAJAX);
}

jeedom.z2m.device.setOptions = function(_params){
  var paramsRequired = ['options'];
  var paramsSpecifics = {};
  try {
    jeedom.private.checkParamsRequired(_params || {}, paramsRequired);
  } catch (e) {
    (_params.error || paramsSpecifics.error || jeedom.private.default_params.error)(e);
    return;
  }
  var params = $.extend({}, jeedom.private.default_params, paramsSpecifics, _params || {});
  var paramsAJAX = jeedom.private.getParamsAJAX(params);
  paramsAJAX.url = 'plugins/z2m/core/ajax/z2m.ajax.php';
  paramsAJAX.data = {
    action: 'publish',
    topic : '/bridge/request/device/options',
    message : JSON.stringify(_params.options),
  };
  $.ajax(paramsAJAX);
}

jeedom.z2m.device.configure_reporting = function(_params){
  var paramsRequired = ['options'];
  var paramsSpecifics = {};
  try {
    jeedom.private.checkParamsRequired(_params || {}, paramsRequired);
  } catch (e) {
    (_params.error || paramsSpecifics.error || jeedom.private.default_params.error)(e);
    return;
  }
  var params = $.extend({}, jeedom.private.default_params, paramsSpecifics, _params || {});
  var paramsAJAX = jeedom.private.getParamsAJAX(params);
  paramsAJAX.url = 'plugins/z2m/core/ajax/z2m.ajax.php';
  paramsAJAX.data = {
    action: 'publish',
    topic : '/bridge/request/device/configure_reporting',
    message : JSON.stringify(_params.options),
  };
  $.ajax(paramsAJAX);
}

jeedom.z2m.device.bind = function(_params){
  var paramsRequired = ['options'];
  var paramsSpecifics = {};
  try {
    jeedom.private.checkParamsRequired(_params || {}, paramsRequired);
  } catch (e) {
    (_params.error || paramsSpecifics.error || jeedom.private.default_params.error)(e);
    return;
  }
  var params = $.extend({}, jeedom.private.default_params, paramsSpecifics, _params || {});
  var paramsAJAX = jeedom.private.getParamsAJAX(params);
  paramsAJAX.url = 'plugins/z2m/core/ajax/z2m.ajax.php';
  paramsAJAX.data = {
    action: 'publish',
    topic : '/bridge/request/device/bind',
    message : JSON.stringify(_params.options),
  };
  $.ajax(paramsAJAX);
}

jeedom.z2m.device.unbind = function(_params){
  var paramsRequired = ['options'];
  var paramsSpecifics = {};
  try {
    jeedom.private.checkParamsRequired(_params || {}, paramsRequired);
  } catch (e) {
    (_params.error || paramsSpecifics.error || jeedom.private.default_params.error)(e);
    return;
  }
  var params = $.extend({}, jeedom.private.default_params, paramsSpecifics, _params || {});
  var paramsAJAX = jeedom.private.getParamsAJAX(params);
  paramsAJAX.url = 'plugins/z2m/core/ajax/z2m.ajax.php';
  paramsAJAX.data = {
    action: 'publish',
    topic : '/bridge/request/device/unbind',
    message : JSON.stringify(_params.options),
  };
  $.ajax(paramsAJAX);
}

jeedom.z2m.device.remove = function(_params){
  var paramsRequired = ['id'];
  var paramsSpecifics = {};
  try {
    jeedom.private.checkParamsRequired(_params || {}, paramsRequired);
  } catch (e) {
    (_params.error || paramsSpecifics.error || jeedom.private.default_params.error)(e);
    return;
  }
  var params = $.extend({}, jeedom.private.default_params, paramsSpecifics, _params || {});
  var paramsAJAX = jeedom.private.getParamsAJAX(params);
  paramsAJAX.url = 'plugins/z2m/core/ajax/z2m.ajax.php';
  let force = (_params.force) ? (_params.force) : false;
  paramsAJAX.data = {
    action: 'publish',
    topic : '/bridge/request/device/remove',
    message : JSON.stringify({"id":_params.id,force:force})
  };
  $.ajax(paramsAJAX);
}

jeedom.z2m.device.childCreate = function(_params){
  var paramsRequired = ['id','endpoint'];
  var paramsSpecifics = {};
  try {
    jeedom.private.checkParamsRequired(_params || {}, paramsRequired);
  } catch (e) {
    (_params.error || paramsSpecifics.error || jeedom.private.default_params.error)(e);
    return;
  }
  var params = $.extend({}, jeedom.private.default_params, paramsSpecifics, _params || {});
  var paramsAJAX = jeedom.private.getParamsAJAX(params);
  paramsAJAX.url = 'plugins/z2m/core/ajax/z2m.ajax.php';
  paramsAJAX.data = {
    id: _params.id,
    endpoint:_params.endpoint,
    action: 'childCreate'
  };
  $.ajax(paramsAJAX);
}

jeedom.z2m.group.add = function(_params){
  var paramsRequired = ['name'];
  var paramsSpecifics = {};
  try {
    jeedom.private.checkParamsRequired(_params || {}, paramsRequired);
  } catch (e) {
    (_params.error || paramsSpecifics.error || jeedom.private.default_params.error)(e);
    return;
  }
  var params = $.extend({}, jeedom.private.default_params, paramsSpecifics, _params || {});
  var paramsAJAX = jeedom.private.getParamsAJAX(params);
  paramsAJAX.url = 'plugins/z2m/core/ajax/z2m.ajax.php';
  paramsAJAX.data = {
    action: 'publish',
    topic : '/bridge/request/group/add',
    message : JSON.stringify({"friendly_name":_params.name})
  };
  $.ajax(paramsAJAX);
}

jeedom.z2m.group.removeMember = function(_params){
  var paramsRequired = ['group','device'];
  var paramsSpecifics = {};
  try {
    jeedom.private.checkParamsRequired(_params || {}, paramsRequired);
  } catch (e) {
    (_params.error || paramsSpecifics.error || jeedom.private.default_params.error)(e);
    return;
  }
  var params = $.extend({}, jeedom.private.default_params, paramsSpecifics, _params || {});
  var paramsAJAX = jeedom.private.getParamsAJAX(params);
  paramsAJAX.url = 'plugins/z2m/core/ajax/z2m.ajax.php';
  paramsAJAX.data = {
    action: 'publish',
    topic : '/bridge/request/group/members/remove',
    message : JSON.stringify({"group":_params.group,"device" : _params.device})
  };
  $.ajax(paramsAJAX);
}

jeedom.z2m.group.addMember = function(_params){
  var paramsRequired = ['group','device'];
  var paramsSpecifics = {};
  try {
    jeedom.private.checkParamsRequired(_params || {}, paramsRequired);
  } catch (e) {
    (_params.error || paramsSpecifics.error || jeedom.private.default_params.error)(e);
    return;
  }
  var params = $.extend({}, jeedom.private.default_params, paramsSpecifics, _params || {});
  var paramsAJAX = jeedom.private.getParamsAJAX(params);
  paramsAJAX.url = 'plugins/z2m/core/ajax/z2m.ajax.php';
  paramsAJAX.data = {
    action: 'publish',
    topic : '/bridge/request/group/members/add',
    message : JSON.stringify({"group":_params.group,"device" : _params.device})
  };
  $.ajax(paramsAJAX);
}
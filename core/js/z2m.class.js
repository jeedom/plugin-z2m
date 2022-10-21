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

jeedom.z2m.bridge.include = function(_params){
  var paramsRequired = ['instance'];
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
    id : _params.instance,
  };
  $.ajax(paramsAJAX);
}

jeedom.z2m.device.setOptions = function(_params){
  var paramsRequired = ['id','options'];
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
    action: 'setDeviceOptions',
    id : _params.id,
    options : JSON.stringify(_params.options),
  };
  $.ajax(paramsAJAX);
}

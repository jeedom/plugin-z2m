 #!/bin/bash

# This file is part of Plugin zigbee for jeedom.
#
#  Plugin zigbee for jeedom is free software: you can redistribute it and/or modify
#  it under the terms of the GNU General Public License as published by
#  the Free Software Foundation, either version 3 of the License, or
#  (at your option) any later version.
#
#  Plugin zigbee for jeedom is distributed in the hope that it will be useful,
#  but WITHOUT ANY WARRANTY; without even the implied warranty of
#  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
#  GNU General Public License for more details.
#
#  You should have received a copy of the GNU General Public License
#  along with Plugin zigbee for jeedom. If not, see <http://www.gnu.org/licenses/>.

#set -x  # make sure each command is printed in the terminal
set -x
echo "Launch post-install of z2m dependancy"
BASEDIR=$(dirname $(realpath "$0"))

if [ -d "${BASEDIR}/zigbee2mqtt" ]; then
    cd ${BASEDIR}/zigbee2mqtt
    if [ -d "${BASEDIR}/zigbee2mqtt/.git" ]; then
        echo "Update z2m (git)"
        git config --global --add safe.directory ${BASEDIR}/zigbee2mqtt
        git reset --hard HEAD
        git pull
    else
       echo "Not a git folder need to clone z2m"
       cd ..
       rm -rf ${BASEDIR}/zigbee2mqtt
       git clone --depth 1 https://github.com/Koenkk/zigbee2mqtt.git ${BASEDIR}/zigbee2mqtt
       cd ${BASEDIR}/zigbee2mqtt
   fi
   echo "${BASEDIR}/../data/wanted_z2m_version"
   if [ -f "${BASEDIR}/../data/wanted_z2m_version" ]; then
        wanted_z2m_version=$(cat "${BASEDIR}/../data/wanted_z2m_version")
        if [ ! -z "${wanted_z2m_version}" ];then
           echo "Need version : "$wanted_z2m_version
           git checkout tags/$wanted_z2m_version
        fi
   fi
    
    npm ci
    npm run build
else
    mkdir ${BASEDIR}/zigbee2mqtt
    git clone --depth 1 https://github.com/Koenkk/zigbee2mqtt.git ${BASEDIR}/zigbee2mqtt
    cd ${BASEDIR}/zigbee2mqtt

    if [ -f "${BASEDIR}/../data/wanted_z2m_version" ]; then
        $wanted_z2m_version=$(cat "${BASEDIR}/../data/wanted_z2m_version")
        if [ ! -z "${wanted_z2m_version}" ];then
           git checkout tags/$wanted_z2m_version
        fi
    fi
    
    npm ci
    npm run build
fi
chown www-data:www-data -R ${BASEDIR}/zigbee2mqtt

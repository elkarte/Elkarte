#!/bin/bash

set -e
set +x

owner=${USER}
phpversionname="$1"

echo "Preparing PHP-FPM"

# The php-fpm conf file
file="/home/${owner}/.phpenv/versions/${phpversionname}/etc/php-fpm.conf"
cp /home/${owner}/.phpenv/versions/${phpversionname}/etc/php-fpm.conf.default /home/${owner}/.phpenv/versions/${phpversionname}/etc/php-fpm.conf

# Check if we should using www.conf instead
if [[ -f /home/${owner}/.phpenv/versions/${phpversionname}/etc/php-fpm.d/www.conf.default ]]
then
	cp /home/${owner}/.phpenv/versions/${phpversionname}/etc/php-fpm.d/www.conf.default /home/${owner}/.phpenv/versions/${phpversionname}/etc/php-fpm.d/www.conf
	file=/home/${owner}/.phpenv/versions/${phpversionname}/etc/php-fpm.d/www.conf
fi;

# Make any updates to the conf file
sed -e "s,;listen.mode = 0660,listen.mode = 0666,g" --in-place ${file}
# possible other edits
#sed -e "s,listen = 127.0.0.1:9000,listen = /tmp/php${phpversionname:0:1}-fpm.sock,g" --in-place ${file}
#sed -e "s,;listen.owner = nobody,listen.owner = www-data,g" --in-place ${file}
#sed -e "s,;listen.group = nobody,listen.group = www-data,g" --in-place ${file}
#sed -e "s,user = nobody,;user = www-data,g" --in-place ${file}
#sed -e "s,group = nobody,;group = www-data,g" --in-place ${file}
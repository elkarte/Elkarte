#!/bin/bash
#
# "since failure is always an option"
# Perform various actions if things don't go as expected during the test
# currently not used with GHA

set -e
set +x

# Passed params
DB=$1
PHP_VERSION=$2
WEBTESTS=$3
COVERAGE=$4

# Common name
SHORT_DB=${DB%%-*}
SHORT_PHP=${PHP_VERSION:0:3}

# Show the php error log
if [[ -f /var/www/error.log ]]; then cat /var/www/error.log; fi
if [[ -f /var/log/error.log ]]; then cat /var/log/error.log; fi
if [[ -f /var/log/php_errors.log ]]; then cat /var/log/php_errors.log; fi

# Show the apache error log as well
if [[ -f /var/www/apache-error.log ]]; then cat /var/www/apache-error.log; fi
if [[ -f /var/log/apache-error.log ]]; then cat /var/log/apache-error.log; fi
if [[ -f /var/log/apache2/apache-error.log ]]; then cat /var/log/apache2/apache-error.log; fi

# Upload any selenium selfies
if [[ "$COVERAGE" == "true"  && "$WEBTESTS" == "true" ]]
then
	screenshots_dir="/var/www/screenshots"
	screenshots=$(find ${screenshots_dir} -name "*.png" -type f)

	# If we have pics, lets upload them
	if [[ ! -z "$screenshots" ]]
	then
		wget https://raw.githubusercontent.com/tremby/imgur.sh/master/imgur.sh -O /var/www/imgur.sh && chmod +x /var/www/imgur.sh
		echo "********** Failed tests screenshots **********"
		for screenshot in ${screenshots}
		do
			echo ${screenshot}
			/var/www/imgur.sh ${screenshot}
		done
	fi
fi
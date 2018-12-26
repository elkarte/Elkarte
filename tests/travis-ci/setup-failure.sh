#!/bin/bash
#
# "since failure is always an option"
# Perform various actions if things don't go as expected during the test

set -e
set +x

# Passed params from the travis.yml file
DB=$1
TRAVIS_PHP_VERSION=$2
WEBTESTS=$3
COVERAGE=$4

# Common name
SHORT_DB=${DB%%-*}
SHORT_PHP=${TRAVIS_PHP_VERSION:0:3}

# Show the php error log
if [[ -f /var/www/error.log ]]; then cat /var/www/error.log; fi

# Show the apache error log as well
if [[ -f /var/www/apache-error.log ]]; then cat /var/www/apache-error.log; fi

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
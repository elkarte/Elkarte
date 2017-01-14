#!/bin/bash
#
# "since failure is always an option"
# Perform various actions if things don't go as expected during the test

set -e
set -x

DB=$1
SHORT_DB=${DB%%-*}

TRAVIS_PHP_VERSION=$2
SHORT_PHP=${TRAVIS_PHP_VERSION:0:3}

# Show the error log, may be something useful in there
if [ -f /var/www/error.log ]
then
    cat /var/www/error.log
fi

# Upload any selenium selfies
if [ "$SHORT_PHP" == "5.6" -a "$SHORT_DB" == "mysql" ]
then
	screenshots_dir="/var/www/screenshots"
	screenshots=$(find $screenshots_dir -name "*.png" -type f)

	# If we have pics, lets upload them
	if [ ! -z "$screenshots" ]
	then
		wget https://raw.githubusercontent.com/tremby/imgur.sh/master/imgur.sh -O /var/www/imgur.sh && chmod +x /var/www/imgur.sh
		echo "********** Failed tests screenshots **********"
		for screenshot in $screenshots
		do
			/var/www/imgur.sh $screenshot
		done
	fi
fi
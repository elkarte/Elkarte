#!/bin/bash
#
# "since failure is always an option"
# Perform various actions if things don't go as expected during the test

set -e
set -x

DB=$1
SHORT_DB=${DB%-*}

TRAVIS_PHP_VERSION=$2
SHORT_PHP=${TRAVIS_PHP_VERSION:0:3}

# Show the error log, may be something useful in there
if [ -f /var/www/error.log ]
then
    cat /var/www/error.log
fi

# Upload any selenium selfies
if [ "$SHORT_PHP" == "5.4" -a "$SHORT_DB" == "mysqli" ]
then
	screenshots_dir="/var/www/screenshots"
	screenshots=$(find $screenshots_dir -name "*.png" -type f)

	# If we have pics, lets upload them
	if [ ! -z "$screenshots" ]
	then
		wget http://imgur.com/tools/imgurbash.sh -O /var/www/imgurbash.sh && chmod +x /var/www/imgurbash.sh
		echo "********** Failed tests screenshots **********"
		for screenshot in $screenshots
		do
			/var/www/imgurbash.sh $screenshot
		done
	fi
fi
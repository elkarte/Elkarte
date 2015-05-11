#!/bin/bash
#
# Install elkarte to /var/www and setup the database
# If this is a defined coverage run (php5.4+mysql) also
#    - calls the selenium install script
#    - updates php.ini so selenium coverage results are also noted

set -e
set -x

DB=$1
TRAVIS_PHP_VERSION=$2
SHORT_PHP=${TRAVIS_PHP_VERSION:0:3}

# Rename ElkArte test files so they can be used by the install
mv ./Settings.sample.php ./Settings.php
mv ./Settings_bak.sample.php ./Settings_bak.php
mv ./db_last_error.sample.txt ./db_last_error.txt

# Move everything to the www directory so Apache can find it
sudo mv * /var/www/
cd /var/www

# Yes but its a test run
sudo chmod -R 777 /var/www

# Install the right database for this run
if [ "$DB" == "mysqli" ]; then sudo php ./tests/travis-ci/setup_mysql.php; fi
if [ "$DB" == "postgres" ]; then sudo php ./tests/travis-ci/setup_pgsql.php; fi

# Remove the install dir
sudo rm -rf /var/www/install

# Load in phpunit and its dependencies via composer, note we have a lock file in place
# composer is updated in setup-server.sh
if [ "$DB" != "none" ]
then
    composer install --no-interaction --prefer-source --quiet

    # Update the added phpunit files
    sudo chmod -R 777 /var/www/vendor

    # common php.ini updates (if any)
    phpenv config-add /var/www//tests/travis-ci/travis_php.ini

    # If this is a code coverage run, we need to enable selenium and capture its coverage results
    if [ "$SHORT_PHP" == "5.4" -a "$DB" == "mysqli" ]
    then
	    phpenv config-add /var/www//tests/travis-ci/travis_webtest_php.ini
	    sudo ./tests/travis-ci/setup-selenium.sh
    fi
fi
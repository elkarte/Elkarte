#!/bin/bash
#
# Install elkarte to /var/www and setup the database
# If this is a defined coverage run (php5.6+mysql) also
#    - calls the selenium install script
#    - updates php.ini so selenium coverage results are also noted

set -e
set -x

DB=$1
SHORT_DB=${DB%%-*}

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

# Load in phpunit and its dependencies via composer, note we have a lock file in place
# composer is updated in setup-server.sh
if [ "$SHORT_DB" != "none" ]
then
    # Start a memcached service on localhost and on the default port. In order to test against
    # multiple memcached instances we spawn a couple more, so we do that during this before script
    memcached -p 11212 -d
    memcached -p 11213 -d
    composer install --no-interaction --prefer-source --quiet

    # Install the right database for this run
    if [ "$SHORT_DB" == "mysql" ]; then sudo php ./tests/travis-ci/setup_mysql.php; fi
    if [ "$SHORT_DB" == "mariadb" ]; then sudo php ./tests/travis-ci/setup_mysql.php; fi
    if [ "$SHORT_DB" == "postgres" ]; then sudo php ./tests/travis-ci/setup_pgsql.php; fi

    # Remove the install dir
    sudo rm -rf /var/www/install

    # Update the added phpunit files
    sudo chmod -R 777 /var/www/vendor

    # common php.ini updates (if any)
    phpenv config-add /var/www/tests/travis-ci/config.ini
    phpenv config-add /var/www/tests/travis-ci/travis_php.ini

    # If this is a code coverage run, we need to enable selenium and capture its coverage results
    if [ "$SHORT_PHP" == "5.6" -a "$SHORT_DB" == "mysql" ]
    then
        phpenv config-add /var/www/tests/travis-ci/travis_webtest_php.ini
        sudo ./tests/travis-ci/setup-selenium.sh
    fi
fi

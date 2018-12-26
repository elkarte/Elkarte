#!/bin/bash
#
# Install elkarte to /var/www and setup the database

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

# Forcing localhost in hosts file
sudo sed -i '1s/^/127.0.0.1 localhost\n/' /etc/hosts

# Prepares for the installer testing
sudo mkdir /var/www/test
sudo cp -r * /var/www/test/

# Rename ElkArte test files so they can be used by the install
mv ./Settings.sample.php ./Settings.php
mv ./Settings_bak.sample.php ./Settings_bak.php
mv ./db_last_error.sample.txt ./db_last_error.txt

# Move everything to the www directory so Apache can find it
sudo mv * /var/www/
cd /var/www

# Yes but its a test run
sudo chmod -R 777 /var/www

if [[ "$SHORT_DB" != "none" ]]
then
    # Start a memcached service on localhost and on the default port. In order to
    # test against multiple memcached instances we spawn a couple more, so we
    # do that during this before script
    memcached -p 11212 -d
    memcached -p 11213 -d

    # Phpunit and support
    composer install --no-interaction --no-suggest

    # Copy phpunit_coverage.php into the webserver's document root directory.
    if [[ "$COVERAGE" == "true" ]]; then cp /var/www/vendor/phpunit/phpunit-selenium/PHPUnit/Extensions/SeleniumCommon/phpunit_coverage.php /var/www; fi

    TRAVIS_PHP="$(which php)"
    # Install the database for this run
    if [[ "$SHORT_DB" == "mysql" ]]; then sudo ${TRAVIS_PHP} /var/www/tests/travis-ci/setup_mysql.php; fi
    if [[ "$SHORT_DB" == "mariadb" ]]; then sudo ${TRAVIS_PHP} /var/www/tests/travis-ci/setup_mysql.php; fi
    if [[ "$SHORT_DB" == "postgres" ]]; then sudo ${TRAVIS_PHP} /var/www/tests/travis-ci/setup_pgsql.php; fi

    # Remove the install dir
    sudo rm -rf /var/www/install

    # Update the added phpunit files
    sudo chmod -R 777 /var/www/vendor

    # common php.ini updates
    phpenv config-add /var/www/tests/travis-ci/travis_php.ini
fi

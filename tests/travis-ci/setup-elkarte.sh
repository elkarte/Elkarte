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

# Move everything to the www directory so apache can find it
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
# compose is updated in setup-server.sh
composer install --dev --no-interaction --prefer-source

# Update the added phpunit files
sudo chmod -R 777 /var/www/vendor

# If this is a code coverage run, we need to enable selenium and capture its coverage results
if [ "$SHORT_PHP" == "5.4" -a "$DB" == "mysqli" ]
then
	sudo ./tests/travis-ci/setup-selenium.sh
	cp /var/www/vendor/phpunit/phpunit-selenium/PHPUnit/Extensions/SeleniumCommon/*.php /var/www
    echo "auto_prepend_file=/var/www/prepend.php" >> ~/.phpenv/versions/$(phpenv version-name)/etc/conf.d/travis.ini
    echo "auto_append_file=/var/www/append.php" >> ~/.phpenv/versions/$(phpenv version-name)/etc/conf.d/travis.ini
fi
#!/bin/bash
#
# Install elkarte to /var/www and setup the database

set -e
set -x

DB=$1
TRAVIS_PHP_VERSION=$2

# Rename ElkArte test files
mv ./Settings.sample.php ./Settings.php
mv ./Settings_bak.sample.php ./Settings_bak.php
mv ./db_last_error.sample.txt ./db_last_error.txt

# Move it to the www directory so apache can find it
sudo mv * /var/www/
cd /var/www

# Install the right database for this run
if [ "$DB" == "mysqli" ]; then sudo php ./tests/travis-ci/setup_mysql.php; fi
if [ "$DB" == "postgres" ]; then sudo php ./tests/travis-ci/setup_pgsql.php; fi

# Remove the install dir
sudo rm -rf /var/www/install

# fetch the latest composer.phar if you want
# curl -sS https://getcomposer.org/installer | php

# Load in phpunit and dependencies with composer, note we have a lock file in place
sudo php composer.phar install --dev --no-interaction --prefer-source
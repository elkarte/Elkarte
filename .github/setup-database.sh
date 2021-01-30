#!/bin/bash

set -e
set -x

DB=$1

# Setup a Database
if [[ "$DB" == "postgres" ]]
then
    psql -c "DROP DATABASE IF EXISTS elkarte_test;" -U postgres
    psql -c "CREATE DATABASE elkarte_test;" -U postgres
fi

# Rename ElkArte test files so they can be used by the install
mv ./Settings.sample.php ./Settings.php
mv ./Settings_bak.sample.php ./Settings_bak.php
mv ./db_last_error.sample.txt ./db_last_error.txt

# GHA wants 127.0.0.1 not localhost
sudo sed -i "s/localhost/127.0.0.1/g" ./Settings.php

# Install / Prepare the database for this run
if [[ "$DB" == "mysql" ]]; then php .github/SetupMysql.php; fi
if [[ "$DB" == "mariadb" ]]; then php .github/SetupMysql.php; fi
if [[ "$DB" == "postgres" ]]; then php .github/SetupPgsql.php; fi

# Remove the install dir
sudo rm -rf /install

# Copy phpunit_coverage.php into the webserver's document root directory.
cp ./vendor/phpunit/phpunit-selenium/PHPUnit/Extensions/SeleniumCommon/phpunit_coverage.php .
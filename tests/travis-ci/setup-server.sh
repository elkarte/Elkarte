#!/bin/bash
#
# Update the server package listing,
# Install mysql and pgsql
# Install php apache mod
# Install and start apache

set -e
set -x

DB=$1
TRAVIS_PHP_VERSION=$2

# Packages installation / update
sudo apt-get update -qq
sudo apt-get install -y -qq --force-yes apache2 libapache2-mod-php5 php5-mysql php5-pgsql php5-curl

# Apache webserver configuration
sudo sed -i -e "/var/www" /etc/apache2/sites-available/default
sudo a2enmod rewrite
sudo a2enmod actions
sudo a2enmod headers
sudo /etc/init.d/apache2 restart

# Set a database for the install to use
if [ "$DB" == "postgres" ]
then
    psql -c "DROP DATABASE IF EXISTS elkarte_test;" -U postgres
    psql -c "create database elkarte_test;" -U postgres
fi

if [ "$DB" == "mysqli" ]
then
    mysql -e "DROP DATABASE IF EXISTS elkarte_test;" -uroot
    mysql -e "create database IF NOT EXISTS elkarte_test;" -uroot
fi

# Install or Update Composer
composer -v > /dev/null 2>&1
COMPOSER_IS_INSTALLED=$?

if [ $COMPOSER_IS_INSTALLED -ne 0 ]
then
    echo "Installing Composer"
    curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer
else
    echo "Updating Composer"
    composer self-update
fi
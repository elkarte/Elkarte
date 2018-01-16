#!/bin/bash
#
# Update the server package listing
# Install php-fpm Apache mod
# Configure and start Apache
# Install database tables as needed
# Build APCu as needed

set -e
set +x

# Access passed params from Travis.yml
DB=$1
TRAVIS_PHP_VERSION=$2
WEBTESTS=$3
COVERAGE=$4

# Common names
SHORT_DB=${DB%%-*}
SHORT_PHP=${TRAVIS_PHP_VERSION:0:3}

# Packages update
sudo apt-get clean && sudo apt-get update -qq

# Specific version of MySQL ?
if [ "$SHORT_DB" == "mysql" -a "$DB" != "mysql-5.6" ]
then
   # Travis is MySQL 5.6 on ubuntu 14.04
   sudo service mysql stop
   sudo apt-get -qq install python-software-properties > /dev/null
   echo mysql-apt-config mysql-apt-config/select-server select "$DB" | sudo debconf-set-selections
   wget http://dev.mysql.com/get/mysql-apt-config_0.8.6-1_all.deb > /dev/null
   sudo dpkg --install mysql-apt-config_0.8.6-1_all.deb
   sudo apt-get update -qq
   sudo apt-get install -qq -o Dpkg::Options::=--force-confnew mysql-server
   sudo mysql_upgrade
fi

# Install basics for PHP CLI
sudo apt-get -qq install php5-cli php5-mysql php5-pgsql

# Install Apache, mod-FPM and DB support
if [ "$SHORT_DB" == "postgres" ]
then
    sudo apt-get -qq install apache2 libapache2-mod-fastcgi php5-pgsql > /dev/null
elif [ "$SHORT_DB" == "mysql" -o "$SHORT_DB" == "mariadb" ]
then
    sudo apt-get -qq --allow-downgrades install apache2 libapache2-mod-fastcgi php5-mysql > /dev/null
else
    sudo apt-get -qq --allow-downgrades install apache2 libapache2-mod-fastcgi > /dev/null
fi

# Configure and Enable php-fpm
tests/travis-ci/travis-fpm.sh $(phpenv version-name)
/home/$USER/.phpenv/versions/$(phpenv version-name)/sbin/php-fpm

# Setup a database if we are installing
if [ "$SHORT_DB" == "postgres" ]
then
    psql -c "DROP DATABASE IF EXISTS elkarte_test;" -U postgres
    psql -c "create database elkarte_test;" -U postgres
elif [ "$SHORT_DB" == "mysql" -o "$SHORT_DB" == "mariadb" ]
then
    mysql -e "DROP DATABASE IF EXISTS elkarte_test;" -uroot
    mysql -e "create database IF NOT EXISTS elkarte_test;" -uroot
fi

# Setup cache engines for elkarte cache testing
printf "\n"| pecl install -f apcu

# Configure apache modules
sudo a2enmod rewrite actions fastcgi alias

# Update Virtual host template file
sudo cp -f tests/travis-ci/travis-ci-apache /etc/apache2/sites-available/000-default.conf
cd /var/www
sudo sed -e "s?%TRAVIS_BUILD_DIR%?$(pwd)?g" --in-place /etc/apache2/sites-available/000-default.conf

# Stop hostname warning
echo "ServerName localhost" | sudo tee /etc/apache2/conf-available/fqdn.conf
sudo a2enconf fqdn

# Restart Apache
sudo service apache2 restart

# if we are not creating code coverage reports, do not run xdebug
if [ "$COVERAGE" != "true" -o "${TRAVIS_PULL_REQUEST}" == "false" ]; then phpenv config-rm xdebug.ini; fi
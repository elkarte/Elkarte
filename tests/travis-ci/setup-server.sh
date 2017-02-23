#!/bin/bash
#
# Update the server package listing
# Install php Apache mod
# Configure and start Apache
# Install database tables as needed

set -e
set -x

DB=$1
SHORT_DB=${DB%%-*}

TRAVIS_PHP_VERSION=$2
SHORT_PHP=${TRAVIS_PHP_VERSION:0:3}

# Packages update
sudo apt-get update -qq

# Specific version of MySQL ?
if [ "$DB" == "mysql-5.6" -o "$DB" == "mysql-5.7" ]
then
   # Travis is MySQL 5.5 on ubuntu 12.04 ATM
   sudo service mysql stop
   sudo apt-get -qq install python-software-properties > /dev/null
   echo mysql-apt-config mysql-apt-config/select-server select "$DB" | sudo debconf-set-selections
   wget http://dev.mysql.com/get/mysql-apt-config_0.8.2-1_all.deb > /dev/null
   sudo dpkg --install mysql-apt-config_0.8.2-1_all.deb
   sudo apt-get update -qq
   sudo apt-get install -qq -y -o Dpkg::Options::=--force-confnew mysql-server
   sudo mysql_upgrade
fi

# Install Apache, PHP w/DB support if any
if [ "$SHORT_DB" == "postgres" ]
then
    sudo apt-get -qq -y --force-yes install apache2 libapache2-mod-php5 php5-pgsql php5-curl > /dev/null
elif [ "$SHORT_DB" == "mysql" ]
then
    sudo apt-get -qq -y --force-yes install apache2 libapache2-mod-php5 php5-mysql php5-curl > /dev/null
else
    sudo apt-get -qq -y --force-yes install apache2 libapache2-mod-php5 php5-curl > /dev/null
fi

# clean up
sudo apt-get -qq -y autoremove > /dev/null

# Apache webserver configuration
sudo sed -i -e "/var/www" /etc/apache2/sites-available/default
sudo a2enmod rewrite > /dev/null
sudo a2enmod actions > /dev/null
sudo a2enmod headers > /dev/null

# Restart Apache to take effect
sudo /etc/init.d/apache2 restart

# Setup a database if we are installing
if [ "$SHORT_DB" == "postgres" ]
then
    psql -c "DROP DATABASE IF EXISTS elkarte_test;" -U postgres
    psql -c "create database elkarte_test;" -U postgres
elif [ "$SHORT_DB" == "mysql" ]
then
    mysql -e "DROP DATABASE IF EXISTS elkarte_test;" -uroot
    mysql -e "create database IF NOT EXISTS elkarte_test;" -uroot
fi

# Install or Update Composer
if [ "$SHORT_DB" != "none" ]
then
    composer -v > /dev/null 2>&1
    COMPOSER_IS_INSTALLED=$?

    if [ $COMPOSER_IS_INSTALLED -ne 0 ]
    then
        echo "Installing Composer"
        curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer
    fi
fi

# Instal APCu
if [ "$TRAVIS_PHP_VERSION" != "5.3" ]
then
    if [ "$TRAVIS_PHP_VERSION" == "7.0" ]
    then
        printf "\n"| pecl install apcu
    else
        printf "\n"| pecl install channel://pecl.php.net/APCu-4.0.10

        # Install Xcache. Not available for PHP 7 :(.
        wget http://xcache.lighttpd.net/pub/Releases/3.2.0/xcache-3.2.0.tar.gz
        tar xf xcache-3.2.0.tar.gz
        cd xcache-3.2.0
        phpize
        ./configure
        make
        make install
        cd ../
        rm -r xcache-3.2.0
        printf "extension=xcache.so\nxcache.size=64M\nxcache.var_size=16M\nxcache.test=On" > xcache.ini
        phpenv config-add xcache.ini
    fi
else
    printf "extension=apc.so\napc.enabled=1" > 5.3.ini
    phpenv config-add 5.3.ini
fi

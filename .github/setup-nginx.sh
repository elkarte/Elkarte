#!/bin/bash

set -e
set -x

DB=$1
PHP_VERSION=$2
DIR=$(dirname "$0")
USER=$(whoami)
ELKARTE_ROOT_PATH=$(realpath "$DIR/../elkarte")
NGINX_SITE_CONF="/etc/nginx/sites-enabled/default"
NGINX_CONF="/etc/nginx/nginx.conf"
APP_SOCK=$(realpath "$DIR")/php-app.sock
NGINX_PHP_CONF="$DIR/nginx-php.conf"

# Install Nginx
sudo apt-get install nginx -qq > /dev/null
sudo service nginx stop

# PHP-FPM file locations
PHP_FPM_BIN="/usr/sbin/php-fpm$PHP_VERSION"
PHP_FPM_CONF="$DIR/php-fpm.conf"

# Create a basic PHP-FPM conf
echo "
	[global]

	[ci]
	user = $USER
	group = $USER
	listen = $APP_SOCK
	listen.mode = 0666
	pm = static
	pm.max_children = 2

	php_admin_value[memory_limit] = 128M
" > $PHP_FPM_CONF

# Use it
sudo $PHP_FPM_BIN \
	--fpm-config "$DIR/php-fpm.conf"

# Nginx conf, Update basic one from the repo with correct dir and user
sudo sed -i "s/user www-data;/user $USER;/g" $NGINX_CONF
sudo cp "$DIR/../.github/nginx.conf" "$NGINX_SITE_CONF"
sudo sed -i \
	-e "s/example\.com/localhost/g" \
	-e "s|root /path/to/elkarte;|root $ELKARTE_ROOT_PATH;|g" \
	$NGINX_SITE_CONF

# Generate FastCGI configuration for Nginx
echo "
upstream php {
	server unix:$APP_SOCK;
}
" > $NGINX_PHP_CONF

sudo mv "$NGINX_PHP_CONF" /etc/nginx/conf.d/php.conf

# Test for debug output and start
sudo nginx -t
sudo service nginx start
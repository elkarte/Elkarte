#!/bin/bash

set -e
set -x

DB=$1
PHP_VERSION=$2

DIR=$(dirname "$0")
USER=$(whoami)
ELKARTE_ROOT_PATH=$(realpath "$DIR/../elkarte")
APP_SOCK=$(realpath "$DIR")/php-fpm.sock

# set the sock file with user:group
sudo touch "$APP_SOCK"
sudo chown "$USER:$USER" "$APP_SOCK"

# Nginx config file locations
NGINX_SITE_CONF="/etc/nginx/sites-enabled/default"
NGINX_CONF="/etc/nginx/nginx.conf"

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
	listen.owner = $USER
	listen.group = $USER
	pm = static
	pm.max_children = 2
	security.limit_extensions = .php

	php_admin_value[memory_limit] = 128M
" > $PHP_FPM_CONF

# Start PHP-FPM with this config
sudo $PHP_FPM_BIN \
	--fpm-config "$DIR/php-fpm.conf"

# Nginx conf, Update with correct user
sudo sed -i "s/user www-data;/user $USER;/g" $NGINX_CONF

# Nginx default sites enabled conf, update one from the repo with correct site, sock, root
sudo cp "$DIR/../.github/nginx.conf" "$NGINX_SITE_CONF"
sudo sed -i \
	-e "s/example\.com/127.0.0.1/g" \
	-e "s|root /path/to/elkarte;|root $ELKARTE_ROOT_PATH;|g" \
	-e "s|/path/to/socket|$APP_SOCK|g" \
	$NGINX_SITE_CONF

# Test for debug output and start
sudo nginx -t
sudo service nginx start
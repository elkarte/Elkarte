#!/bin/bash

set -e
set -x

DB=$1
PHP_VERSION=$2

# Refresh package list upfront
sudo apt-get update -qq

# Install GNU coreutils and memcached
sudo apt-get install coreutils memcached -qq > /dev/null

# Webserver setup
if [[ "$DB" != "none" ]]
then
  if [[ "$WEBSERVER" != "none" ]]; then .github/setup-nginx.sh $DB $PHP_VERSION; fi

  # Start a memcached service on localhost and the default port so we can test cache engines
  memcached -p 11212 -d
  memcached -p 11213 -d
fi

# Phpunit and support
# composer config --file=composer2.json && composer install --no-interaction --quiet
composer install --no-interaction --quiet

# phpunit-selenium is compatible with phpunit 9.3.x, past that it runs all methods not just test methods
# This combination allows tests to run, but code coverage fails unless we use our version of RemoteCoverage.php
if [[ "$WEBSERVER" != "none" ]]
then
	composer remove phpunit/phpunit phpunit/phpunit-selenium --dev
	composer require phpunit/phpunit:9.3.11 phpunit/phpunit-selenium:9.0.1 --dev --update-with-all-dependencies --ignore-platform-reqs
fi

# Provide a way to return from actions redirectexit & obexit, so we can get results for Unit Test
if [[ "$WEBSERVER" == "none" ]]
then
  sudo sed -i '/global $db_show_debug;/a \\n\tif (defined("PHPUNITBOOTSTRAP") && defined("STDIN")){return $setLocation;}' ./sources/Subs.php
  sudo sed -i '/call_integration_hook('"'"'integrate_exit'"'"', \[$do_footer\]);/a \\n\tif (defined("PHPUNITBOOTSTRAP") && defined("STDIN")){return;}' ./sources/Subs.php
fi
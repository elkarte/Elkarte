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
if [[ "$PHP_VERSION" =~ ^8 ]]
then
	composer remove phpunit/phpunit phpunit/phpunit-selenium --dev --update-with-dependencies
	composer require phpunit/phpunit:^9.0 --dev --update-with-all-dependencies --ignore-platform-reqs
fi

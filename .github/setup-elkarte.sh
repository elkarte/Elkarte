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
  .github/setup-nginx.sh $DB $PHP_VERSION

  # Start a memcached service on localhost and the default port so we can test cache engines
  memcached -p 11212 -d
  memcached -p 11213 -d
fi

# Phpunit and support
composer install --no-interaction --quiet

# Copy phpunit_coverage.php into the webserver's document root directory.
cp ./vendor/phpunit/phpunit-selenium/PHPUnit/Extensions/SeleniumCommon/phpunit_coverage.php .


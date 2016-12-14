#!/bin/bash
#
# If we created test coverage reports, send them to scrutinizer

set -e
set -x

DB=$1
SHORT_DB=${DB%%-*}

TRAVIS_PHP_VERSION=$2
SHORT_PHP=${TRAVIS_PHP_VERSION:0:3}

# Scrutinizer will merge all coverage data...
wget https://scrutinizer-ci.com/ocular.phar
php ocular.phar code-coverage:upload --format=php-clover /tmp/coverage.xml

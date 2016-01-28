#!/bin/bash
#
# If we created test coverage reports, send them to scrutinizer

set -e
set -x

DB=$1
SHORT_DB=${DB%%-*}

TRAVIS_PHP_VERSION=$2
SHORT_PHP=${TRAVIS_PHP_VERSION:0:3}

# We run coverage data only for this combination
if [ "$SHORT_PHP" == "5.6" -a "$SHORT_DB" == "mysql" ]
then
    wget https://scrutinizer-ci.com/ocular.phar
    php ocular.phar code-coverage:upload --format=php-clover /tmp/coverage.xml
fi
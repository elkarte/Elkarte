#!/bin/bash
#
# Run PHPUnit tests,
# if its 5.4+mysql then generate test coverage as well

set -e
set -x

DB=$1
SHORT_DB=${DB%-*}

TRAVIS_PHP_VERSION=$2
SHORT_PHP=${TRAVIS_PHP_VERSION:0:3}

# Run phpunit tests for the site
if [ "$SHORT_PHP" == "5.4" -a "$SHORT_DB" == "mysqli" ]
then
    /var/www/vendor/bin/phpunit --configuration /var/www/tests/travis-ci/phpunit-with-coverage-$SHORT_DB-travis.xml
    /var/www/vendor/bin/phpunit /var/www/tests/travis-ci/BootstrapRunTest.php
elif [ "$SHORT_DB" == "none" ]
then
    /var/www/vendor/bin/phpunit --configuration /var/www/tests/travis-ci/phpunit-basic-travis.xml
else
    /var/www/vendor/bin/phpunit --configuration /var/www/tests/travis-ci/phpunit-$SHORT_DB-travis.xml
    /var/www/vendor/bin/phpunit /var/www/tests/travis-ci/BootstrapRunTest.php
fi
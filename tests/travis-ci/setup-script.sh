#!/bin/bash
#
# Run PHPUnit tests, 
# if its 5.4+mysql then generate test coverage as well

set -e
set -x

DB=$1
TRAVIS_PHP_VERSION=$2
SHORT_PHP=${TRAVIS_PHP_VERSION:0:3}

# Run phpunit tests for the site
if [ "$SHORT_PHP" == "5.4" -a "$DB" == "mysqli" ]
then
    /var/www/vendor/bin/phpunit --configuration /var/www/tests/travis-ci/phpunit-with-coverage-$DB-travis.xml
else
    /var/www/vendor/bin/phpunit --configuration /var/www/tests/travis-ci/phpunit-$DB-travis.xml
fi
/var/www/vendor/bin/phpunit /var/www/tests/travis-ci/BootstrapRunTest.php
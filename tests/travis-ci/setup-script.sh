#!/bin/bash
#
# Run PHPUnit tests,

set -e
set +x

# Access passed params from travis.yml
DB=$1
TRAVIS_PHP_VERSION=$2
WEBTESTS=$3
COVERAGE=$4

# Common names
SHORT_DB=${DB%%-*}
SHORT_PHP=${TRAVIS_PHP_VERSION:0:3}

# If this is a web test run then we need to enable selenium
if [[ "$WEBTESTS" == "true" ]]
then
    phpenv config-add /var/www/tests/travis-ci/travis_webtest_php.ini
    sudo /var/www/tests/travis-ci/setup-selenium.sh
fi

# Build a config string for PHPUnit
COVER="--prepend /tmp/xdebug-filter.php"
WEB=""
if [[ "$COVERAGE" != "true" || "${TRAVIS_PULL_REQUEST}" == "false" ]]; then COVER="--no-coverage"; fi
if [[ "$WEBTESTS" == "true" ]]; then WEB="-with-webtest"; fi
CONFIG="--stderr --verbose --debug --configuration /var/www/tests/travis-ci/phpunit${WEB}-${SHORT_DB}-travis.xml"

# Run PHPUnit test to ensure the DB was correctly installed/populated
#/var/www/vendor/bin/phpunit /var/www/tests/travis-ci/DatabaseTestExt.php --coverage-clover /tmp/dbcoverage.xml;

# Run PHPUnit tests for the site
if [[ "$COVERAGE" == "true" && "$TRAVIS_PULL_REQUEST" != "false" ]]
then
  echo 'Creating Xdebug filter list'
  /var/www/vendor/bin/phpunit --dump-xdebug-filter /tmp/xdebug-filter.php ${CONFIG}
fi

echo 'Running PHPUnit tests'
/var/www/vendor/bin/phpunit ${CONFIG} ${COVER}

# Run validation (lock file)
#if [[ "$SHORT_DB" != "none" ]]; then /var/www/vendor/bin/phpunit /var/www/tests/travis-ci/BootstrapRunTestExt.php; fi

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
if [ "$WEBTESTS" == "true" ]
then
    phpenv config-add /var/www/tests/travis-ci/travis_webtest_php.ini
    sudo /var/www/tests/travis-ci/setup-selenium.sh
fi

# Build a config string for PHPUnit
COVER=""
WEB=""
if [ "$COVERAGE" != "true" -o "${TRAVIS_PULL_REQUEST}" == "false" ]; then COVER="--no-coverage"; fi
if [ "$WEBTESTS" == "true" ]; then WEB="-with-webtest"; fi
CONFIG="--configuration /var/www/tests/travis-ci/phpunit${WEB}-${SHORT_DB}-travis.xml ${COVER}"

# Run PHPUnit tests for the site
/var/www/vendor/bin/phpunit ${CONFIG}

# Run validation (lock file)
if [ "$SHORT_DB" != "none" ]; then /var/www/vendor/bin/phpunit /var/www/tests/travis-ci/BootstrapRunTest.php; fi

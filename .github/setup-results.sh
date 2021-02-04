#!/bin/bash
#
# Setup xdebug filter list, run PHPUNIT tests, send to codecov

set -e
set +x

# Passed params
DB=$1
PHP_VERSION=$2

# Build a config string for PHPUnit
COVER="--prepend /tmp/xdebug-filter.php"
CONFIG="--stderr --verbose --debug --configuration .github/phpunit-${DB}.xml"

# Create xdebug filter list
vendor/bin/phpunit --dump-xdebug-filter /tmp/xdebug-filter.php ${CONFIG}

# Running PHPUnit tests
vendor/bin/phpunit ${CONFIG} ${COVER}

# Agents will merge all coverage data...
if [[ "${GITHUB_EVENT_NAME}" == "pull_request" ]]
then
    bash <(curl -s https://codecov.io/bash) -s "/tmp" -f '*.clover'
fi

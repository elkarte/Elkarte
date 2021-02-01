#!/bin/bash
#
# Setup xdebug filter list, run PHPUNIT tests, send to codecov

set -e
set +x

# Passed params
DB=$1
EVENT=$2 # push or pull_request

# Build a config string for PHPUnit
COVER="--prepend /tmp/xdebug-filter.php"
WEB=""
CONFIG="--stderr --verbose --debug --configuration .github/phpunit${WEB}-${DB}.xml"

# Create xdebug filter list
vendor/bin/phpunit --dump-xdebug-filter /tmp/xdebug-filter.php ${CONFIG}

# Running PHPUnit tests
vendor/bin/phpunit ${CONFIG} ${COVER}

# Agents will merge all coverage data...
if [[ "$EVENT" == "pull_request" ]]
then
  bash <(curl -s https://codecov.io/bash) -f "/tmp/coverage.clover"
fi

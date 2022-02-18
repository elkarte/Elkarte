#!/bin/bash
#
# run PHPUNIT tests, send to codecov

set -e
set +x

# Passed params
DB=$1
PHP_VERSION=$2

# Build a config string for PHPUnit
CONFIG="--verbose --configuration .github/phpunit-${DB}.xml"

# Running PHPUnit tests
vendor/bin/phpunit ${CONFIG}

# Agents will merge all coverage data...
if [[ "${GITHUB_EVENT_NAME}" == "pull_request" ]]
then
    bash <(curl -s https://codecov.io/bash) -s "/tmp" -f '*.clover'
fi

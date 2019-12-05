#!/bin/bash
#
# If we created test coverage reports, send them to the agents

set -e
set +x

# Passed params from the travis.yml file
DB=$1
TRAVIS_PHP_VERSION=$2
WEBTESTS=$3
COVERAGE=$4

# Common name
SHORT_DB=${DB%%-*}
SHORT_PHP=${TRAVIS_PHP_VERSION:0:3}

# Agents will merge all coverage data...
if [[ "$COVERAGE" == "true" && "$TRAVIS_PULL_REQUEST" != "false" ]]
then
  wget https://scrutinizer-ci.com/ocular.phar
  php ocular.phar code-coverage:upload --format=php-clover /tmp/coverage.clover
  bash <(curl -s https://codecov.io/bash) -f "/tmp/coverage.clover"
fi

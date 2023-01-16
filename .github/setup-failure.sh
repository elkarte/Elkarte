#!/bin/bash
#
# "since failure is always an option"
# Perform various actions if things don't go as expected during the test
# currently only used with GHA selenium to always send coverage

set -e
set +x

# Passed params
DB=$1
PHP_VERSION=$2

# Agents will merge all coverage data...
#if [[ "${GITHUB_EVENT_NAME}" == "pull_request" ]]
#then
#    bash <(curl -s https://codecov.io/bash) -s "/tmp" -f '*.clover'
#fi
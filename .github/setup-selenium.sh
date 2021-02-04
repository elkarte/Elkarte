#!/bin/bash
#
# Install selenium server for functional web testing

set -e
set +x

# Access passed params
DB=$1
PHP_VERSION=$2

# Some vars to make this easy to change
SELENIUM_HUB_URL='http://127.0.0.1:4444'
SELENIUM_JAR=/usr/share/selenium/selenium-server-standalone.jar
SELENIUM_DOWNLOAD_URL=https://selenium-release.storage.googleapis.com/3.7/selenium-server-standalone-3.7.1.jar

# Location of geckodriver for use as webdriver in xvfb
GECKODRIVER_DOWNLOAD_URL=https://github.com/mozilla/geckodriver/releases/download/v0.14.0/geckodriver-v0.14.0-linux64.tar.gz
GECKODRIVER_TAR=/tmp/geckodriver.tar.gz

# Location of chromedriver for use as webdriver in xvfb
CHROMEDRIVER_DOWNLOAD_URL=https://chromedriver.storage.googleapis.com/2.9/chromedriver_linux64.zip
CHROMEDRIVER_ZIP=/tmp/chromedriver.zip

# Download Selenium
sudo mkdir -p $(dirname "$SELENIUM_JAR")
sudo wget -nv -O "$SELENIUM_JAR" "$SELENIUM_DOWNLOAD_URL"

# Start Selenium using default HTML driver
export DISPLAY=:99.0
sudo xvfb-run --server-args="-screen 0 1280x1024x24" java -jar "$SELENIUM_JAR" > /tmp/selenium.log &
wget --retry-connrefused --tries=120 --waitretry=3 --output-file=/dev/null "$SELENIUM_HUB_URL/wd/hub/status" -O /dev/null

# Test to see if the selenium server really did start
if [[ ! $? -eq 0 ]]
then
    echo "Selenium Failed"

    # Useful for debugging
    cat /tmp/selenium.log
else
    echo "Selenium Success"

    # Copy phpunit_coverage.php into the webserver's document root directory.
    cp ./vendor/phpunit/phpunit-selenium/PHPUnit/Extensions/SeleniumCommon/phpunit_coverage.php .

    # Run the phpunit selenium tests
    vendor/bin/phpunit --verbose --debug --configuration .github/phpunit-webtest.xml
fi

# Agents will merge all coverage data...
if [[ "${GITHUB_EVENT_NAME}" == "pull_request" ]]
then
    bash <(curl -s https://codecov.io/bash) -s "/tmp" -f '*.clover'
fi
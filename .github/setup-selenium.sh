#!/bin/bash
#
# Install selenium server for functional web testing

set -e
set +x

# Access passed params
DB=$1
PHP_VERSION=$2
WEBTESTS=$3
COVERAGE=$4

# Common names
SHORT_DB=${DB%%-*}
SHORT_PHP=${PHP_VERSION:0:3}

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

# If this is a web test run then we need to enable selenium
if [[ "$WEBTESTS" == "true" ]]
then
	echo "Downloading Selenium Server"
    sudo mkdir -p $(dirname "$SELENIUM_JAR")
    sudo wget -nv -O "$SELENIUM_JAR" "$SELENIUM_DOWNLOAD_URL"

    # Start Selenium
    export DISPLAY=:99.0
    sudo xvfb-run --server-args="-screen 0 1280x1024x24" java -jar "$SELENIUM_JAR" > /tmp/selenium.log &
    wget --retry-connrefused --tries=120 --waitretry=3 --output-file=/dev/null "$SELENIUM_HUB_URL/wd/hub/status" -O /dev/null

    # Test to see if the selenium server really did start
    if [[ ! $? -eq 0 ]]
    then
        echo "Selenium Failed"
    else
        echo "Selenium Success"
    fi

    cat /tmp/selenium.log

    # Setup a directory to hold screenshots of failed tests
    sudo mkdir /var/www/screenshots
    sudo chmod 777 /var/www/screenshots
fi
#!/bin/bash
#
# Install selenium server for functional web testing

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

# Some vars to make this easy to change
WEBTESTS_HUB_URL='http://127.0.0.1:4444'
WEBTESTS_JAR=/usr/share/selenium/selenium-server-standalone.jar
WEBTESTS_DOWNLOAD_URL=http://selenium-release.storage.googleapis.com/3.2/selenium-server-standalone-3.2.0.jar

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
    sudo mkdir -p $(dirname "$WEBTESTS_JAR")
    sudo wget -nv -O "$WEBTESTS_JAR" "$WEBTESTS_DOWNLOAD_URL"

    # Start Selenium
    export DISPLAY=:99.0
    sudo xvfb-run --server-args="-screen 0 1280x1024x24" java -jar "$WEBTESTS_JAR" > /tmp/selenium.log &
    wget --retry-connrefused --tries=120 --waitretry=3 --output-file=/dev/null "$WEBTESTS_HUB_URL/wd/hub/status" -O /dev/null

    # Test to see if the selenium server really did start
    if [[ ! $? -eq 0 ]]
    then
        echo "Selenium Failed"
    else
        echo "Selenium Success"
    fi

    # Setup a directory to hold screenshots of failed tests
    sudo mkdir /var/www/screenshots
    sudo chmod 777 /var/www/screenshots
fi
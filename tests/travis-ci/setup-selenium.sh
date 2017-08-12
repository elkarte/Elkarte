#!/bin/bash
#
# Install selenium server for functional web testing

set -e
set +x

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

# If selenium is not available, get it
if [ ! -f "$WEBTESTS_JAR" ]
then
    sudo mkdir -p $(dirname "$WEBTESTS_JAR")
    sudo wget -nv -O "$WEBTESTS_JAR" "$WEBTESTS_DOWNLOAD_URL"
fi

# Fetch gecko driver for use in selenium
if [ ! -f "/usr/bin/geckodriver" ]
then
    echo "Downloading geckodriver"
    sudo wget -nv -O "$GECKODRIVER_TAR" "$GECKODRIVER_DOWNLOAD_URL"
    sudo tar -xvf "$GECKODRIVER_TAR" -C "/usr/bin/"
fi

# Fetch chrome driver for use in selenium
if [ ! -f "/usr/bin/chromedriver" ]
then
    echo "Downloading chromedriver"
    sudo wget -nv -O "$CHROMEDRIVER_ZIP" "$CHROMEDRIVER_DOWNLOAD_URL"
    sudo unzip "$CHROMEDRIVER_ZIP"
    sudo mv chromedriver /usr/bin
fi

# Start Selenium, select gecko or chrome driver
export DISPLAY=:99.0
sudo xvfb-run java -Dwebdriver.geckodriver.bin=/usr/bin/geckodriver -jar "$WEBTESTS_JAR" > /tmp/selenium.log &
wget --retry-connrefused --tries=120 --waitretry=3 --output-file=/dev/null "$WEBTESTS_HUB_URL/wd/hub/status" -O /dev/null

# Test to see if the selenium server really did start
if [ ! $? -eq 0 ]
then
    echo "Selenium Failed"
else
    echo "Selenium Success"
fi

# Setup a directory to hold screenshots of failed tests
sudo mkdir /var/www/screenshots && chmod 777 /var/www/screenshots
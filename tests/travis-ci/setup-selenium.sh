#!/bin/bash
#
# Install selenium server for functional web testing

set -e
set -x

# some vars to make this easy to change
SELENIUM_HUB_URL='http://127.0.0.1:4444'
SELENIUM_JAR=/usr/share/selenium/selenium-server-standalone.jar
SELENIUM_DOWNLOAD_URL=http://selenium-release.storage.googleapis.com/2.53/selenium-server-standalone-2.53.0.jar

# If selenium is not available, get it
if [ ! -f "$SELENIUM_JAR" ]
then
    sudo mkdir -p $(dirname "$SELENIUM_JAR")
    sudo wget -nv -O "$SELENIUM_JAR" "$SELENIUM_DOWNLOAD_URL"
fi

# Update/Install Fx or chrome
#sudo apt-get install firefox -y --no-install-recommends
wget https://chromedriver.storage.googleapis.com/2.25/chromedriver_linux64.zip && unzip chromedriver_linux64.zip && sudo mv chromedriver /usr/bin

# Start Selenium
export DISPLAY=:99.0
sudo xvfb-run java -Dwebdriver.chromedriver.bin=/usr/bin/chromedriver -jar "$SELENIUM_JAR" > /tmp/selenium.log &
wget --retry-connrefused --tries=120 --waitretry=3 --output-file=/dev/null "$SELENIUM_HUB_URL/wd/hub/status" -O /dev/null

# Test to see if the selenium server really did start
if [ ! $? -eq 0 ]
then
    echo "Selenium Failed"
else
    echo "Selenium Success"
fi

# Setup a directory to hold screenshots of failed tests
sudo mkdir /var/www/screenshots && chmod 777 /var/www/screenshots

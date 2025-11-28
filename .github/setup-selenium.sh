#!/bin/bash

#
# Install selenium server for functional web testing

set -e
set -x

# Some vars to make this easy to change
SELENIUM_HUB_URL='http://127.0.0.1:4444'
SELENIUM_JAR=/usr/share/selenium/selenium-server-standalone.jar
SELENIUM_DOWNLOAD_URL=https://selenium-release.storage.googleapis.com/3.141/selenium-server-standalone-3.141.59.jar

# Location of chromedriver for use as webdriver in xvfb
CHROMEDRIVER_ZIP=/tmp/chromedriver_linux64.zip

# Download Selenium
echo "Downloading Selenium"
sudo mkdir -p $(dirname "$SELENIUM_JAR")
sudo wget -nv -O "$SELENIUM_JAR" "$SELENIUM_DOWNLOAD_URL"
sudo chmod 777 "$SELENIUM_JAR"

# 1. Install dependencies required for Chrome and JSON parsing
sudo apt-get update
sudo apt-get install -y libu2f-udev

# 2. Download Chrome 123
CHROME_VERSION='123.0.6312.58-1'
wget https://mirror.cs.uchicago.edu/google-chrome/pool/main/g/google-chrome-stable/google-chrome-stable_${CHROME_VERSION}_amd64.deb -q

# 3. Install with ALLOW DOWNGRADES
sudo apt-get install -y ./google-chrome-stable_${CHROME_VERSION}_amd64.deb --allow-downgrades

# 4. Download matching ChromeDriver 123
wget -nv -O chromedriver.zip "https://storage.googleapis.com/chrome-for-testing-public/123.0.6312.58/linux64/chromedriver-linux64.zip"
unzip chromedriver.zip
sudo mv chromedriver-linux64/chromedriver /usr/local/bin/chromedriver
sudo chmod +x /usr/local/bin/chromedriver

# Start Selenium using default chosen webdriver
export DISPLAY=:99.0
xvfb-run --server-args="-screen 0, 2560x1440x24" java -Dwebdriver.chrome.driver=/usr/local/bin/chromedriver -jar "$SELENIUM_JAR" > /tmp/selenium.log &
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

    # Copy RemoteCoverage.php back to vendor, this version supports phpunit RawCodeCoverageData
    sudo cp ./tests/RemoteCoverage.php ./vendor/phpunit/phpunit-selenium/PHPUnit/Extensions/SeleniumCommon

    # This keeps triggering in tests for the 2 second rule, lets try to fix that
    sudo sed -i -e "s|spamProtection('login');|//spamProtection('login');|g" ./sources/ElkArte/Controller/Auth.php

    # Run the phpunit selenium tests
    vendor/bin/phpunit --verbose --debug --configuration .github/phpunit-webtest.xml
fi

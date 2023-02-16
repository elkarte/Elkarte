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
SELENIUM_DOWNLOAD_URL=https://selenium-release.storage.googleapis.com/3.141/selenium-server-standalone-3.141.59.jar

# Location of geckodriver for use as webdriver in xvfb
GECKODRIVER_DOWNLOAD_URL=https://github.com/mozilla/geckodriver/releases/download/v0.29.1/geckodriver-v0.29.1-linux64.tar.gz
GECKODRIVER_TAR=/tmp/geckodriver.tar.gz

# Location of chromedriver for use as webdriver in xvfb
CHROMEDRIVER_ZIP=/tmp/chromedriver_linux64.zip

# Download Selenium
echo "Downloading Selenium"
sudo mkdir -p $(dirname "$SELENIUM_JAR")
sudo wget -nv -O "$SELENIUM_JAR" "$SELENIUM_DOWNLOAD_URL"

# Install Fx or Chrome
echo "Installing Browser"
# sudo apt install firefox -y -qq > /dev/null
# Available Chrome Versions
# https://www.ubuntuupdates.org/package/google_chrome/stable/main/base/google-chrome-stable?id=202706
#
CHROME_VERSION='110.0.5481.100-1' # '91.0.4472.114-1'

wget https://dl.google.com/linux/chrome/deb/pool/main/g/google-chrome-stable/google-chrome-stable_${CHROME_VERSION}_amd64.deb -q
sudo dpkg -i google-chrome-stable_${CHROME_VERSION}_amd64.deb

# Download Chrome Driver
echo "Downloading chromedriver"
CHROME_VERSION=$(google-chrome --version | cut -f 3 -d ' ' | cut -d '.' -f 1) \
  && CHROMEDRIVER_RELEASE=$(curl --location --fail --retry 3 https://chromedriver.storage.googleapis.com/LATEST_RELEASE_${CHROME_VERSION}) \
  && wget -nv -O "$CHROMEDRIVER_ZIP" "https://chromedriver.storage.googleapis.com/$CHROMEDRIVER_RELEASE/chromedriver_linux64.zip" \
  && unzip "$CHROMEDRIVER_ZIP" \
  && rm -rf "$CHROMEDRIVER_ZIP" \
  && sudo mv chromedriver /usr/local/bin/chromedriver \
  && sudo chmod +x /usr/local/bin/chromedriver \
  && chromedriver --version

# Download Gecko driver
#echo "Downloading geckodriver"
#wget -nv -O "$GECKODRIVER_TAR" "$GECKODRIVER_DOWNLOAD_URL" \
#  && sudo tar -xvf "$GECKODRIVER_TAR" -C "/usr/local/bin/" \
#  && sudo chmod +x /usr/local/bin/geckodriver \
#  && geckodriver --version

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

    # Run the phpunit selenium tests
    vendor/bin/phpunit --verbose --debug --configuration .github/phpunit-webtest.xml

    # Agents will merge all coverage data...
    if [[ "${GITHUB_EVENT_NAME}" == "pull_request" ]]
    then
        bash <(curl -s https://codecov.io/bash) -s "/tmp" -f '*.clover'
    fi
fi
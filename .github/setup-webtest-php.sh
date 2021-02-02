set -e
set -x

# after repo is fetched and before php starts, we need these coverage
# files in place.
sudo cp ./tests/prepend.php /usr/share/php
sudo cp ./tests/append.php /usr/share/php
sudo cp ./tests/ExitHandler.php /usr/share/php
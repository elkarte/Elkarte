#!/bin/bash
#
# Time to create an ElkArte build
# Command example:
#   sh release_tools/build.sh development 1.1 beta
#   sh release_tools/build.sh development 1.1 beta 2

# First things first: check dependencies
command -v git >/dev/null 2>&1 || { echo >&2 "git is required but it's not installed.  Aborting."; exit 1; }
command -v zip >/dev/null 2>&1 || { echo >&2 "zip is required but it's not installed.  Aborting."; exit 1; }

REPO="http://github.com/ElkArte/ElkArte.git"
BRANCH=$1
VERSION=$2
if [[ $4 && ${4-_} ]]
	then
		SUBVERSION="$3-$4"
	else
		SUBVERSION="$3"
fi

git clone --depth 5 "$REPO" "./$VERSION""_""$SUBVERSION"

cd "./$VERSION""_""$SUBVERSION"

git checkout "$BRANCH"

# @todo run tests!

# All the stuff nobody cares about.
rm -rf ./docs
rm -rf ./release_tools
rm -rf ./tests
rm -rf ./.git
rm -rf ./install/patch*
rm composer.json
rm composer.lock
rm CONTRIBUTING.md
rm license.md
rm phpdoc.yml
rm .gitattributes
rm .gitignore
rm .scrutinizer.yml
rm .travis.yml

zip "ElkArte_v${VERSION//[.]/-}-$SUBVERSION""_install.zip" -r ./
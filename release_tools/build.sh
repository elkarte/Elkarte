#!/bin/bash

#
# Time to create an ElkArte build
# Command example:
#  bash release_tools/build.sh development 2.0 beta
#
# @version 2.0 dev

# First things first: check dependencies
command -v git >/dev/null 2>&1 || { echo >&2 "git is required but it's not installed.  Aborting."; exit 1; }
command -v zip >/dev/null 2>&1 || { echo >&2 "zip is required but it's not installed.  Aborting."; exit 1; }

if [[ ! -z "$5" && "$5" = "sec" ]]
	then
		REPO="git@bitbucket.org:elkartesecurity/elkarte.git"
	else
		REPO="https://github.com/ElkArte/ElkArte.git"
fi

BRANCH=$1
VERSION=$2
if [[ ! -z $4 && ${4-_} ]]
	then
		SUBVERSION="$3-$4"
	else
		SUBVERSION="$3"
fi

git clone "$REPO" "./$VERSION""_""$SUBVERSION"

cd "./$VERSION""_""$SUBVERSION"

git checkout "$BRANCH"

# @todo run tests!

# All the stuff nobody cares about.
rm -rf ./docs
rm -rf ./release_tools
rm -rf ./tests
rm -rf ./.git
rm -rf ./install/patch*
rm -rf ./.github
rm composer.json
rm composer.lock
rm CONTRIBUTING.md
rm license.md
rm phpdoc.yml
rm .gitattributes
rm .gitignore
rm .scrutinizer.yml
rm .travis.yml
rm .jshintignore
rm .jshintrc
rm .stylelintignore
rm .stylelintrc
rm .codecov.yml

zip "../ElkArte_v${VERSION//./-}-${SUBVERSION}_install.zip" -r -q ./
echo "Zip file created."
cd ..
rm -rf "./$VERSION""_""$SUBVERSION"

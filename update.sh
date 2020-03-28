#!/bin/bash

TAG=$1
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"

REQS=""

if [ ! -e /usr/bin/composer ]; then
	echo "Installing composer"
	REQS="composer"
fi

if [ ! -e /usr/bin/jq ]; then
	echo "Installing jq"
	REQS="$REQS jq"
fi

if [ "$REQS" ]; then
	yum -y install $REQS
fi


cd $DIR
mkdir -p $DIR/data

if [ ! -e $DIR/vendor/autoload.php -o ! -e $DIR/vendor/google/apiclient/README.md ]; then
	echo "Updating and installing packages"
	composer install
fi

# Update ourselves
git fetch origin
git checkout $TAG

# Validate our credentials
php $DIR/tools/gencredentials.php

# Generate our sheet if missing
if [ ! -e $DIR/data/sheetid.json ]; then
	php $DIR/tools/gensheet.php
fi


echo "Setup complete"


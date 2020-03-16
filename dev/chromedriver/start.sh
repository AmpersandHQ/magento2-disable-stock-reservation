#!/usr/bin/env bash

DIR_BASE="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

mkdir -p $DIR_BASE/log/
LOGFILE="$DIR_BASE/log/chromedriver.log"
rm -f $LOGFILE
touch $LOGFILE

$DIR_BASE/stop.sh

if [[ "$OSTYPE" == "darwin"* ]]; then
    printf "\033[92mlaunch chromedriver osx\n\033[0m";
    $DIR_BASE/mac64/chromedriver --version;
    $DIR_BASE/mac64/chromedriver --url-base=/wd/hub &> $LOGFILE &
else
    printf "\033[92mlaunch chromedriver linux\n\033[0m";
    $DIR_BASE/linux64/chromedriver --version;
    $DIR_BASE/linux64/chromedriver --url-base=/wd/hub &> $LOGFILE&
fi

sleep 2;
printf "\n";

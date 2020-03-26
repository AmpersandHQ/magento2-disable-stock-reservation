#!/usr/bin/env bash

DIR_BASE="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

$DIR_BASE/../vendor/bin/codecept build -c dev
$DIR_BASE/../vendor/bin/codecept run acceptance -c dev $1 -vvv

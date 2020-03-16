#!/usr/bin/env bash

if pgrep chromedriver; then
    printf "\033[92mKilling running chromedriver\n\033[0m";
    pkill -f chromedriver;
fi

printf "\n";

exit 0;

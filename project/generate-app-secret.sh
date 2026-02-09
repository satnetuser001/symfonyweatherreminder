#!/bin/bash

GREEN="\033[0;32m"
YELLOW="\033[1;33m"
RED="\033[0;31m"
RESET="\033[0m"

if ! command -v php &> /dev/null; then
    echo -e "${RED}Error: php is not installed. Please install it or enter the 'cli' container.${RESET}"
elif [ -f .env.local ]; then
    if grep -q '^APP_SECRET=$' .env.local; then
        SECRET=$(php -r 'echo bin2hex(random_bytes(32));')
        sed -i "s/^APP_SECRET=$/APP_SECRET=$SECRET/" .env.local
        echo -e "${GREEN}APP_SECRET generated and added to .env.local${RESET}"
    else
        echo -e "${YELLOW}APP_SECRET already set in .env.local, nothing changed${RESET}"
    fi
else
    echo -e "${RED}.env.local not found, please create it first${RESET}"
fi

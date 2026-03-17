#!/usr/bin/env bash
#set -o xtrace

SOURCE_PATH="$(dirname "${PWD}")"
SYNC_PATH=${SOURCE_PATH}/test/cardsshield-gateway-paypal
VERSION=$(cat ../info/version.txt)

echo "${SOURCE_PATH}"
echo "${SYNC_PATH}"
echo "${VERSION}"

rsync -avh --delete "${SOURCE_PATH}/" "${SYNC_PATH}/" --exclude .idea --exclude info --exclude .git --exclude .github --exclude test --exclude composer.json --exclude composer.lock
sed -i '' "s/__VERSION__/$VERSION/g" "${SYNC_PATH}/m-ecom-paygate.php"
sed -i '' "s/__VERSION__/$VERSION/g" "${SYNC_PATH}/utils.php"

rm cardsshield-gateway-paypal.zip
zip -r cardsshield-gateway-paypal.zip ./cardsshield-gateway-paypal -x '*/.DS_Store'

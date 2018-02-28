#!/usr/bin/env bash

mkdir -p output

composer install

OC_PATH=../../../../
CORE_INT_TESTS_PATH=tests/acceptance/

OCC=${OC_PATH}occ

SCENARIO_TO_RUN=$1
HIDE_OC_LOGS=$2

# avoid port collision on jenkins - use $EXECUTOR_NUMBER
if [ -z "$EXECUTOR_NUMBER" ]; then
    EXECUTOR_NUMBER=0
fi
PORT=$((8080 + $EXECUTOR_NUMBER))
echo $PORT
php -S localhost:$PORT -t ../../../../ &
PHPPID=$!
echo $PHPPID

export TEST_SERVER_URL="http://localhost:$PORT/ocs/"

#Set up personalized skeleton
$OCC config:system:set skeletondirectory --value="$(pwd)/$OC_PATH""$CORE_INT_TESTS_PATH""skeleton"

#Enable needed app
$OCC app:enable files_external
$OCC app:enable customgroups
$OCC app:enable testing

vendor/bin/behat --strict -f junit -f pretty $SCENARIO_TO_RUN
RESULT=$?

kill $PHPPID

#Disable apps
$OCC app:disable files_external
$OCC app:disable customgroups
$OCC app:enable testing

if [ -z $HIDE_OC_LOGS ]; then
	tail "${OC_PATH}/data/owncloud.log"
fi

echo "runsh: Exit code: $RESULT"
exit $RESULT


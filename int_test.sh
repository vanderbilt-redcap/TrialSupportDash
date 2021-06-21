#!/bin/sh

PATH=/opt/rh/rh-php56/root/usr/bin/:$PATH

cd ~/www/redcap/modules/PassItOn_v1.0.0

unitTestOutput=`phpunit --testsuite=Integration`
if [ ! $? -eq 0 ]; then
    echo "One or more unit tests failed:"
    echo ''
    echo "$unitTestOutput"
fi
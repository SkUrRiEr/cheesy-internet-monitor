#!/bin/bash

PHPAPP="$(dirname "$0")"/store_data.php

if ! /usr/bin/host -W 5 www.google.com 192.168.100.254 >/dev/null 2>&1; then
	php $PHPAPP dns down
else
	php $PHPAPP dns up
fi

if ! /bin/ping -c 4 8.8.8.8 >/dev/null 2>&1; then
	php $PHPAPP conn down
else
	php $PHPAPP conn up
fi

if [ -f /tmp/wait_for_modem.stamp ]; then
	php $PHPAPP reboot start
fi

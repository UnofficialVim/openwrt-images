#!/bin/sh
# Copyright (C) 2015 Ubiquiti Networks

if [ "$#" -lt 1 ]; then
    echo "Usage: $0 username:password [option for chpasswd]"
    exit 1
fi

echo $1 | chpasswd $2
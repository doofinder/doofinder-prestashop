#!/usr/bin/env bash
if [ "$#" -ne 1 ]; then
    echo "Usage: $0 <tag>"
    exit 1
fi

git archive --format=zip -o doofinder-$1.zip --prefix doofinder/ $1

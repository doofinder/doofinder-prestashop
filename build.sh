#!/usr/bin/env bash
if [ "$#" -ne 1 ]; then
    echo "Usage: $0 <tag>"
    exit 1
fi

cd ..

mkdir _build
cp -R doofinder-prestashop _build
mv _build/doofinder-prestashop _build/doofinder

cd _build

rm doofinder/docker-compose.yml
rm doofinder/composer.json
rm -rf doofinder/.git
rm -rf doofinder/.github
rm doofinder/.git*

zip -r doofinder-$1.zip doofinder
mv doofinder-$1.zip ..
cd ..
rm -rf _build
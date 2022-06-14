#!/bin/bash

api_url=https://validator.prestashop.com/api/modules
endpoint="${api_url}?key=${VALIDATOR_API_KEY}&compatibility_1_7=1"

response=$(curl -s -F "archive=@./doofinder-ci.zip" --location --request POST $endpoint)

errors=$(echo $response | jq -r '.Details .results .errors')

if [[ $errors == 0 ]]
then
    echo "Valid!!!"
    exit 0;
else
    echo $response | jq
    echo "Has errors!!!"
    exit 1;
fi
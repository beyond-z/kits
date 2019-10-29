#!/bin/bash

echo "Refreshing wp-content from latest production snapshot"
if aws --version 2> /dev/null; then

  # Note: not using gzip b/c this is additive and once you have the baseline set of uploads, then each subsequent
  # run just syncs to changes
  aws s3 sync 's3://kits-staging-wp-content/uploads/' './wp-content/uploads/' \
    || { echo >&2 "Error: Failed pulling uploads folder from kits-staging-wp-content S3 bucket"; exit 1; }

  aws s3 sync 's3://kits-staging-wp-content/plugins/' './wp-content/plugins/' \
    || { echo >&2 "Error: Failed pulling plugins folder from kits-staging-wp-content S3 bucket"; exit 1; }

else
  echo "Error: Please install 'aws'. E.g."
  echo "   $ pip3 install awscli"
  echo ""
  echo "You must run 'aws configure' after to setup permissions. Enter your IAM Access Token and Secret. Use us-west-1 for the region."
  exit 1;
fi

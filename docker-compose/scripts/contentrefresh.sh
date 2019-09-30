#!/bin/bash

echo "Refreshing uploads content from kits-dev-files S3 bucket"
if aws --version 2> /dev/null; then

  # Note: not using gzip b/c this is additive and once you have the baseline set of uploads, then each subsequent
  # run just syncs to changes
  aws s3 sync 's3://kits-dev-files/uploads/' './wp-content/uploads/'
  if [ $? -ne 0 ]
  then
    echo "Failed pulling uploads folder from kits-dev-files S3 bucket using:"
    exit 1;
  fi

  aws s3 sync 's3://kits-dev-files/plugins/' './wp-content/plugins/'
  if [ $? -ne 0 ]
  then
    echo "Failed pulling plugins folder from kits-dev-files S3 bucket using:"
    exit 1;
  fi

else
  # Install AWS CLI if it's not there
  echo "Error: Please install 'aws'. E.g."
  echo "   $ pip3 install awscli"
  echo ""
  echo "You must run 'aws configure' after to setup permissions. Enter your IAM Access Token and Secret. Use us-west-1 for the region."
  exit 1;
fi

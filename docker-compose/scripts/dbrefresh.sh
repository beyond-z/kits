#!/bin/bash
echo "Refreshing your local dev database from the staging db"

dbfilename='kits_dev_db_dump_latest.sql.gz'
dbfilepathgz="/tmp/$dbfilename"
dbfilename_attendance='kits_dev_attendance_db_dump.sql.gz'
dbfilepathgz_attendance="/tmp/$dbfilename_attendance"

aws s3 --region us-west-1 cp "s3://kits-dev-db-dumps/$dbfilename" $dbfilepathgz
if [ $? -ne 0 ]
then
 echo "Failed downloading s3://kits-dev-db-dumps/$dbfilename"
 echo "Make sure that awscli is installed: pip3 install awscli"
 echo "Also, make sure and run 'aws configure' and put in your Access Key and Secret."
 echo "Lastly, make sure your IAM account is in the Developers group. That's where the policy to access this bucket is defined."
 exit 1;
fi

gunzip < $dbfilepathgz | docker-compose exec -T kitsdb mysql -h kitsdb -u wordpress "-pwordpress" wordpress
if [ $? -ne 0 ]
then
   echo "Error: failed loading the dev database into the dev db. File we tried to load: $dbfilepathgz"
   exit 1;
fi

rm $dbfilepathgz

#aws s3 --region us-west-1 cp "s3://kits-dev-db-dumps/$dbfilename_attendance" $dbfilepathgz_attendance
#if [ $? -ne 0 ]
#then
# echo "Failed downloading s3://kits-dev-db-dumps/$dbfilename_attendance"
# exit 1;
#fi
#
#gunzip < $dbfilepathgz_attendance| docker-compose exec -T kitsdb mysql -h kitsdb -u wordpress "-pwordpress" attendance
#if [ $? -ne 0 ]
#then
#   echo "Error: failed loading the dev database into the dev db. File we tried to load: $dbfilepathgz_attendance"
#   exit 1;
#fi
#
#rm $dbfilepathgz_attendance
#

./docker-compose/scripts/contentrefresh.sh

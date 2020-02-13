#!/bin/bash
echo "Refreshing your local dev database from the staging db"

dbfilename='kits_staging_db_latest.sql.gz'
dbfilename_attendance='kits_staging_attendance_db_latest.sql.gz'

aws s3 cp "s3://kits-staging-db-dumps/$dbfilename" - | gunzip | sed -e "
  s/https:\/\/stagingkits.bebraven.org/http:\/\/kitsweb:3005/g;
  s/stagingsso.bebraven.org/platformweb:3020\/cas/g;
  s/stagingplatform.bebraven.org/platformweb:3020/g;
" | docker-compose exec -T kitsdb mysql -h kitsdb -u wordpress "-pwordpress" wordpress
if [ $? -ne 0 ]
then
 echo "Failed restoring from: s3://kits-staging-db-dumps/$dbfilename"
 echo "Make sure that awscli is installed: pip3 install awscli"
 echo "Also, make sure and run 'aws configure' and put in your Access Key and Secret."
 echo "Lastly, make sure your IAM account is in the Developers group. That's where the policy to access this bucket is defined."
 exit 1;
fi

aws s3 cp "s3://kits-staging-db-dumps/$dbfilename_attendance" - | gunzip \
  | docker-compose exec -T kitsdb mysql -h kitsdb -u wordpress "-pwordpress" braven_attendance
if [ $? -ne 0 ]
then
 echo "Failed restoring from s3://kits-staging-db-dumps/$dbfilename_attendance"
 exit 1;
fi

# In the dev env, I logged into http://kitsweb:3005/wp-login.php?external=wordpress
# Using the local admin account (same as prod) and configured this plugin to point to
# the local SSO server. Then I just copied out the serialized value and am setting it here.
# this is brittle b/c if you change it to something else and add or remove a character without
# handling the serialized character counts and array counts, it will fail to deserialize.
# We should do this using PHP code...
devAuthSettings='a:45:{s:20:"access_who_can_login";s:14:"external_users";s:34:"access_role_receive_pending_emails";s:3:"---";s:34:"access_pending_redirect_to_message";s:0:"";s:34:"access_blocked_redirect_to_message";s:0:"";s:35:"access_email_approved_users_subject";s:0:"";s:32:"access_email_approved_users_body";s:0:"";s:19:"access_who_can_view";s:15:"logged_in_users";s:15:"access_redirect";s:5:"login";s:21:"access_public_warning";s:10:"no_warning";s:26:"access_redirect_to_message";s:28:"Dev Msg: No anonymous access";s:19:"access_default_role";s:10:"subscriber";s:15:"google_clientid";s:0:"";s:19:"google_clientsecret";s:0:"";s:19:"google_hosteddomain";s:0:"";s:3:"cas";s:1:"1";s:16:"cas_custom_label";s:0:"";s:8:"cas_host";s:11:"platformweb";s:8:"cas_port";s:4:"3020";s:8:"cas_path";s:4:"/cas";s:11:"cas_version";s:15:"CAS_VERSION_2_0";s:14:"cas_attr_email";s:0:"";s:19:"cas_attr_first_name";s:0:"";s:18:"cas_attr_last_name";s:0:"";s:14:"cas_auto_login";s:1:"1";s:9:"ldap_host";s:0:"";s:9:"ldap_port";s:0:"";s:16:"ldap_search_base";s:0:"";s:8:"ldap_uid";s:0:"";s:15:"ldap_attr_email";s:0:"";s:9:"ldap_user";s:0:"";s:13:"ldap_password";s:0:"";s:21:"ldap_lostpassword_url";s:0:"";s:20:"ldap_attr_first_name";s:0:"";s:19:"ldap_attr_last_name";s:0:"";s:17:"advanced_lockouts";a:5:{s:10:"attempts_1";s:0:"";s:10:"duration_1";s:0:"";s:10:"attempts_2";s:0:"";s:10:"duration_2";s:0:"";s:14:"reset_duration";s:0:"";}s:22:"advanced_hide_wp_login";s:1:"1";s:19:"advanced_admin_menu";s:3:"top";s:17:"advanced_usermeta";s:0:"";s:34:"access_should_email_approved_users";s:0:"";s:6:"google";s:0:"";s:24:"cas_attr_update_on_login";s:0:"";s:4:"ldap";s:0:"";s:8:"ldap_tls";s:0:"";s:25:"ldap_attr_update_on_login";s:0:"";s:27:"advanced_override_multisite";s:0:"";}'
# OLD SSO box settings
#devAuthSettings='a:45:{s:20:"access_who_can_login";s:14:"external_users";s:34:"access_role_receive_pending_emails";s:3:"---";s:34:"access_pending_redirect_to_message";s:0:"";s:34:"access_blocked_redirect_to_message";s:0:"";s:35:"access_email_approved_users_subject";s:0:"";s:32:"access_email_approved_users_body";s:0:"";s:19:"access_who_can_view";s:15:"logged_in_users";s:15:"access_redirect";s:5:"login";s:21:"access_public_warning";s:10:"no_warning";s:26:"access_redirect_to_message";s:28:"Dev Msg: No anonymous access";s:19:"access_default_role";s:10:"subscriber";s:15:"google_clientid";s:0:"";s:19:"google_clientsecret";s:0:"";s:19:"google_hosteddomain";s:0:"";s:3:"cas";s:1:"1";s:16:"cas_custom_label";s:0:"";s:8:"cas_host";s:6:"ssoweb";s:8:"cas_port";s:4:"3002";s:8:"cas_path";s:0:"";s:11:"cas_version";s:15:"CAS_VERSION_2_0";s:14:"cas_attr_email";s:0:"";s:19:"cas_attr_first_name";s:0:"";s:18:"cas_attr_last_name";s:0:"";s:14:"cas_auto_login";s:1:"1";s:9:"ldap_host";s:0:"";s:9:"ldap_port";s:0:"";s:16:"ldap_search_base";s:0:"";s:8:"ldap_uid";s:0:"";s:15:"ldap_attr_email";s:0:"";s:9:"ldap_user";s:0:"";s:13:"ldap_password";s:0:"";s:21:"ldap_lostpassword_url";s:0:"";s:20:"ldap_attr_first_name";s:0:"";s:19:"ldap_attr_last_name";s:0:"";s:17:"advanced_lockouts";a:5:{s:10:"attempts_1";s:0:"";s:10:"duration_1";s:0:"";s:10:"attempts_2";s:0:"";s:10:"duration_2";s:0:"";s:14:"reset_duration";s:0:"";}s:22:"advanced_hide_wp_login";s:1:"1";s:19:"advanced_admin_menu";s:3:"top";s:17:"advanced_usermeta";s:0:"";s:34:"access_should_email_approved_users";s:0:"";s:6:"google";s:0:"";s:24:"cas_attr_update_on_login";s:0:"";s:4:"ldap";s:0:"";s:8:"ldap_tls";s:0:"";s:25:"ldap_attr_update_on_login";s:0:"";s:27:"advanced_override_multisite";s:0:"";}'
echo "update bz_options set option_value = '$devAuthSettings' where option_name = 'auth_settings';" | docker-compose exec -T kitsdb mysql -h kitsdb -P 3306 -u wordpress -pwordpress wordpress

# Now get the up to date plugins and uploads content.
./docker-compose/scripts/contentrefresh.sh

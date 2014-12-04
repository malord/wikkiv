<?php
	$DB_HOST = "mysql1.example.com";
	$DB_USER = "dbusername";
	$DB_PASS = "dbpassword";
	$DB_NAME = "dbname";
	$DB_PREFIX = "wikkiv_"; // the pages table will be wikkiv_pages
	
	// Set this to FALSE once the website is up and running.
	$INIT_ENABLED = TRUE;
	
	// The users table name is separate, to allow a single login to be used
	// for multiple wikis.
	$USERS_TABLE_NAME = "wikkiv_users";

	// This path on the server...
	$PATH_TO_FILES_ON_SERVER = "/home/example/example.com/files"; 

	// ...corresponds to this path on the domain
	$PATH_TO_FILES_ON_WEB = "/files";

	// You must update htaccess if you change this
	$PAGE_URL_PREFIX = "/wiki/";
	
	$FRONT_PAGE_LINK = "http://example.com/wiki/";
	$SITE_NAME = "My Wiki";

	$_POWERED = "Powered by <a href=\"http://malord.com/wikkiv/\">wikkiv</a>.";
	$DEFAULT_COPYRIGHT = "Your Copyright Here. $_POWERED";
?>

<?php
// We dont allow direct user->browser access to this file...
if ( $_SERVER['REQUEST_METHOD']=='GET' && realpath(__FILE__) == realpath( $_SERVER['SCRIPT_FILENAME'] ) ) {
	header( 'HTTP/1.0 403 Forbidden', TRUE, 403 );
	die( header( 'location: /error.php' ) );
}
 
	// Sets the internal character encoding to UTF-8 for PHP string operations.
	mb_internal_encoding('UTF-8');
	// sets the character encoding for HTTP output to UTF-8
	mb_http_output('UTF-8');
	//  sets the character encoding for regular expression functions to UTF-8
	mb_regex_encoding('UTF-8');

	define('ROOT_PATH', dirname($_SERVER['DOCUMENT_ROOT']));
	define('BASE_DIR', __DIR__);
	
	//Timeout for user login session (minutes)
	define('_DEFAULT_SESSION_TIMEOUT',10080);

	// We salt user passwords stored in database
	define('_USER_PASSWORD_SALT', '###¤)!!!!!!!!!!!!!!!!!!!£$$€€€@£$AAAAAAAA$£@£$€6{[][{6€$£]]))');
	
	$mysql_user 			= "root";
	$mysql_password 		= "";
	$mysql_database_name 	= "dashboard";
	$mysql_server 			= "localhost";
	
	// true/false, do we want to show database errors, dont set true on a live website
	define('_ERROR_REPORTING_MYSQL', false); 

	// Maximum rows per checklist, tasks
	define('_TASKBOARD_TASK_CHECKLIST_MAXIMUM', 15);
	// Maximum labels per board
	define('_TASKBOARD_LABELS_MAXIMUM', 60);
	// Maximum task boards per user
	define('_TASKBOARD_MAXIMUM_BOARDS', 8);
	// Maximum columns per task board
	define('_TASKBOARD_MAXIMUM_COLUMNS', 10);
	
?>
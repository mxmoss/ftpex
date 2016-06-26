<?php 
// php error handling for production servers

// disable display of startup errors
ini_set('display_startup_errors','false');

// disable display of all other errors
ini_set('display_errors','false');

// disable html markup of errors
ini_set('html_errors','false');

// enable logging of errors
ini_set('log_errors','true');

// disable ignoring of repeat errors
ini_set('ignore_repeated_errors','false');

// disable ignoring of unique source errors
ini_set('ignore_repeated_source','false');

// enable logging of php memory leaks
ini_set('report_memleaks','true');

// preserve most recent error via php_errormsg
ini_set('track_errors','true');

// disable formatting of error reference links
ini_set('docref_root','0');

// disable formatting of error reference links
ini_set('docref_ext','0');

// specify path to php error log
//ini_set('error_log','/var/log/php/errors/php_error.log');

// specify recording of all php errors
ini_set('error_reporting',E_ALL);

// disable max error string length
ini_set('log_errors_max_len','0');

?>
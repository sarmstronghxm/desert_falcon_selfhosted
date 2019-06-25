<?php
//
// UPDATE THE FOLOOWING:
//
// Database Settings
$db_host = 'localhost';					// Database host
$db_user = 'hyperx6_temp';			// Database user
$db_pass = 'Dw4118sc!';					// Database password
$db_name = 'hyperx6_temp';			// Database name


// $db_host = 'localhost';					// Database host
// $db_user = 'root';			// Database user
// $db_pass = 'root';					// Database password
// $db_name = 'temp_data';			// Database name

$db_prefix = '';							// Prefix for table names

// Marketo Settings
$mkto_settings = (object)array(
	'mkto_endpoint' =>	'https://309-POJ-654.mktorest.com/rest',
	'mkto_client_id' =>	'8ae0833d-d166-4278-8ea3-b84fb6ed527e',
	'mkto_secret' =>		'2dEE17H93erqjWuDjskwciqyRwAF2EqG'
);

// API Settings
$prefill_settings_x_min = 300;													// # of seconds between checking marketo for changes
$allowed_domain = 'https://preview.formfuse.com';		// Only allow pecific domain to access script







/*
 * !!! DO NOT EDIT BELOW THIS !!!
 */
spl_autoload_register(function ($class_name) {
	include './classes/'. $class_name . '.php';
});

// Connect to and ready database
$db = new DBConn($db_host, $db_user, $db_pass, $db_name);

// Get Marketo API Class
$forms = new GetForms($mkto_settings);

// Start Actions
$actions = new Actions($allowed_domain, $db, $forms, $mkto_settings);

// Set the headers
$actions->setup_headers();

// Do Actions
$actions->check_action();
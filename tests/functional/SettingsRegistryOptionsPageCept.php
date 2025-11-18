<?php

$I = new FunctionalTester( $scenario );

$I->wantTo( 'Test that updating WPGraphQL options via /wp-admin/options.php does not cause fatal errors' );

$I->loginAsAdmin();

// Test with graphql_experiments_settings (the option mentioned in the issue)
$I->amOnAdminPage( '/options.php' );

// Verify we're on the options page
$I->see( 'All Settings' );

// Submit the form with graphql_experiments_settings set to empty string
// This simulates the scenario where a user updates an option via options.php
// and the option value is an empty string, which previously caused a fatal error
// because sanitize_options() expected an array but received a string
$I->submitForm( 'form', [
	'graphql_experiments_settings' => '',
] );

// Verify that no fatal error occurred - the page should load successfully
$I->seeResponseCodeIs( 200 );

// Verify we're still on a valid page (not a fatal error page)
$I->dontSee( 'Fatal error' );
$I->dontSee( 'Uncaught TypeError' );
$I->dontSee( 'sanitize_options(): Argument #1 ($options) must be of type array' );

// Test with graphql_general_settings as well
$I->amOnAdminPage( '/options.php' );
$I->see( 'All Settings' );

// Submit the form with graphql_general_settings set to empty string
$I->submitForm( 'form', [
	'graphql_general_settings' => '',
] );

// Verify that no fatal error occurred
$I->seeResponseCodeIs( 200 );
$I->dontSee( 'Fatal error' );
$I->dontSee( 'Uncaught TypeError' );
$I->dontSee( 'sanitize_options(): Argument #1 ($options) must be of type array' );


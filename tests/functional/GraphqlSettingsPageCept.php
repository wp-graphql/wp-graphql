<?php

$I = new FunctionalTester( $scenario );

$I->wantTo( 'Test GraphQL Settings Page Renders and Saves as Expected' );

$I->loginAsAdmin();

$I->amOnAdminPage( '/admin.php?page=graphql-settings' );

$I->see( 'WPGraphQL General Settings' );

// Verify that the default value is populated
$I->seeOptionIsSelected( 'graphql_general_settings[tracing_user_role]', 'Administrator' );
$I->seeOptionIsSelected( 'graphql_general_settings[query_log_user_role]', 'Administrator' );

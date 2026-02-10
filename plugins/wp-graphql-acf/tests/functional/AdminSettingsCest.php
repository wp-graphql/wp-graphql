<?php

class AdminSettingsCest
{

    public function _before( FunctionalTester $I ) {

        // Import the json file Save and see the selection after form submit
        $json_file = 'acf-export-2023-01-26.json';
        $I->importJson( $json_file );
    }

    public function seeCustomFieldsFieldGroupWpgraphqlHeadersTest( FunctionalTester $I ) {
        $I->loginAsAdmin();

        $I->amOnPage('/wp-admin/edit.php?post_type=acf-field-group');

        // The graphql headings
        $I->see('Graphql Type', "//thead/tr/th[@id='acf-wpgraphql-type']");
        $I->see('Graphql Interfaces', "//thead/tr/th[@id='acf-wpgraphql-interfaces']");
        $I->see('Graphql Locations', "//thead/tr/th[@id='acf-wpgraphql-locations']");
    }

    public function seeCustomFieldsFieldGroupTableTest( FunctionalTester $I ) {

        $I->loginAsAdmin();

        // On field group page, verify see fields
        $I->amOnPage('/wp-admin/edit.php?post_type=acf-field-group');

        // Grab the first field group name
        $I->see( 'Foo Name' );

        // Grab the wpgraphql type name
        $I->see( 'FooGraphql' );
    }
}

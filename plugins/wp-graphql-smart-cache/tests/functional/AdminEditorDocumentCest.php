<?php

/**
 * Test the graphql saved document admin page
 */

class AdminEditorDocumentCest {

	public function _before( FunctionalTester $I ) {
		// Enable the show-in-ui for these tests.  This allows testing of the admin editor page for our post type.
		$I->haveOptionInDatabase( 'graphql_persisted_queries_section', [ 'editor_display' => 'on' ] );
	}

	public function _after( FunctionalTester $I ) {
		$I->dontHaveOptionInDatabase( 'graphql_persisted_queries_section'  );

		// Clean up any saved query documents
		$I->dontHavePostInDatabase( [ 'post_type' => 'graphql_document' ] );
		$I->dontHaveTermInDatabase( [ 'taxonomy' => 'graphql_query_alias'] );
	}

	/**
	 * Test http request to /{$taxonomy_name}/{$value}
	 * When taxonomy registered, the public/public_queryable value:
	 *   true - the WP 404 page
	 *   false - the hello world page
	 */
	public function postTypeShouldNotBePublicQueryableTest( FunctionalTester $I ) {

		// Create a query in the admin editor
		$I->loginAsAdmin();
		$I->amOnPage( '/wp-admin/post-new.php?post_type=graphql_document');

		// Save and see the selection after form submit
		$I->fillField( "//input[@name='post_title']", 'test-query-foo');
		$I->fillField( 'content', '{ __typename }');
		$I->selectOption("form input[name='graphql_query_grant']", 'allow');
		$I->fillField( 'graphql_query_maxage', '200');
		$I->click('//input[@id="publish"]');
		$I->seeInField(['name' => 'graphql_query_maxage'], '200');

		$I->amOnPage( '/wp-admin/edit-tags.php?taxonomy=graphql_document_grant&post_type=graphql_document' );
		$I->see( 'allow' );

		// saved document should not be visible
		$I->amOnPage( "/graphql_document/test-query-foo/" );
		$I->dontSee('__typename');

		// WordPress shows the homepage template for taxonomies that are public=>false
		// this is similar to a 404, but the WP way of handling it for this situation
		// so if we see the home template, we can be sure the private taxonomy isn't being publicly
		// exposed
		$I->seeElement( "//body[contains(@class,'home')]" );

		$I->amOnPage( "/wp-sitemap-posts-graphql_document-1.xml");
		$I->seeElement( "//body[contains(@class,'error404')]" );
		$I->dontSee('XML Sitemap');

		// query alias should not be visible
		$I->amOnPage( "/graphql_query_alias/test-document-foo-bar/" );
		$I->dontSee('Alias Name: test-query-foo');
		$I->seeElement( "//body[contains(@class,'home')]" );

		$I->amOnPage( "/wp-sitemap-taxonomies-graphql_query_alias-1.xml");

		$I->seeElement( "//body[contains(@class,'error404')]" );
		$I->dontSee('XML Sitemap');

		// allow/deny grant should not be visible
		$I->amOnPage( "/graphql_document_grant/allow/" );
		$I->dontSee('Allow/Deny: allow');
		//  tax-graphql_document_grant
		$I->dontSeeElement( "//body[contains(@class,'tax-graphql_document_grant')]" );
		$I->seeElement( "//body[contains(@class,'home')]" );

		$I->amOnPage( "wp-sitemap-taxonomies-graphql_document_grant-1.xml");

		$I->seeElement( "//body[contains(@class,'error404')]" );
		$I->dontSee('XML Sitemap');

		// max age should not be visible
		$I->amOnPage( "/graphql_document_http_maxage/200/" );

		$I->dontSee('Max-Age Header: 200');
		$I->dontSeeElement( "//body[contains(@class,'tax-graphql_document_http_maxage')]" );
		$I->seeElement( "//body[contains(@class,'home')]" );

		$I->amOnPage( "wp-sitemap-taxonomies-graphql_document_http_maxage-1.xml");

		$I->seeElement( "//body[contains(@class,'error404')]" );
		$I->dontSee('XML Sitemap');
	}

	public function createNewQueryWithoutErrorWhenSaveDraftSavesAsDraftTest( FunctionalTester $I ) {
		$post_title = 'test-post';
		$normalized_query_string = "{\n  __typename\n}\n";

		// Create a new query in the admin editor
		$I->loginAsAdmin();
		$I->amOnPage( '/wp-admin/post-new.php?post_type=graphql_document');

		// Add title should trigger auto-draft, but not save document
		$I->fillField( "//input[@name='post_title']", $post_title );
		$I->fillField( 'content', '{ __typename }');

		// This might cause auto-draft but not save
		$I->dontSeePostInDatabase( ['post_title' => $post_title] );

		// Save draft button.
		$I->click('Save Draft');

		$I->seePostInDatabase( [
			'post_title'  => $post_title,
			'post_status' => 'draft',
			'post_content' => $normalized_query_string,
		]);

		$I->seeElement('//*[@id="message"]');
		$I->see('Post draft updated.', '//*[@id="message"]');
		$I->see('Publish immediately'); // does not have a publish date
	}

	public function createNewQueryWithEmptyContentWhenSaveDraftSavesAsDraftTest( FunctionalTester $I ) {
		$post_title = 'test-post';

		// Create a new query in the admin editor
		$I->loginAsAdmin();
		$I->amOnPage( '/wp-admin/post-new.php?post_type=graphql_document');

		// Add title should trigger auto-draft, but not save document
		$I->fillField( "//input[@name='post_title']", $post_title );

		$I->dontSeePostInDatabase( ['post_title' => $post_title] );

		// Save draft button. No content in form
		$I->click('Save Draft');

		$I->seePostInDatabase( [
			'post_title'  => $post_title,
			'post_status' => 'draft',
			'post_content' => '',
		]);

		$I->seeElement('//*[@id="message"]');
		$I->see('Post draft updated.', '//*[@id="message"]');
		$I->see('Publish immediately'); // does not have a publish date
	}

	public function createNewQueryWithEmptyContentWhenPublishSavesAsDraftTest( FunctionalTester $I ) {
		$post_title = 'test-post';

		// Create a new query in the admin editor
		$I->loginAsAdmin();
		$I->amOnPage( '/wp-admin/post-new.php?post_type=graphql_document');

		// Add title should trigger auto-draft, but not save document
		$I->fillField( "//input[@name='post_title']", $post_title );
		$I->fillField( 'content', '');

		$I->dontSeePostInDatabase( ['post_title' => $post_title] );

		// Publish post
		$I->click('#publish');

		// Because of error form (empty content), saves as draft
		$I->seePostInDatabase( [
			'post_title'  => $post_title,
			'post_status' => 'draft',
			'post_content' => '',
		]);

		$I->dontSeeElement('//*[@id="message"]');
		$I->dontSee('Post draft updated.');
		$I->dontSee('Post published.');
		$I->see('Publish immediately'); // does not have a publish date
	}

	public function createNewQueryWithInvalidContentWhenPublishSavesAsDraftTest( FunctionalTester $I ) {
		$post_title = 'test-post';

		// Create a new query in the admin editor
		$I->loginAsAdmin();
		$I->amOnPage( '/wp-admin/post-new.php?post_type=graphql_document');

		// Add title should trigger auto-draft, but not save document
		$I->fillField( "//input[@name='post_title']", $post_title );
		$I->fillField( 'content', '{ __typename broken');

		// This should cause auto-draft but not save
		$I->dontSeePostInDatabase( ['post_title' => $post_title] );

		// Publish post
		$I->click('#publish');

		$I->seePostInDatabase( [
			'post_title'  => $post_title,
			'post_status' => 'draft',
			'post_content' => '{ __typename broken',
		]);

		$I->dontSeeElement('//*[@id="message"]');
		$I->dontSee('Post draft updated.');
		$I->dontSee('Post published.');
		$I->see('Publish immediately'); // does not have publish date
	}

	public function createNewQueryWithoutErrorWhenPublishSavesAsPublishedTest( FunctionalTester $I ) {
		$post_title = 'test-post';
		$normalized_query_string = "{\n  __typename\n}\n";

		// Create a new query in the admin editor
		$I->loginAsAdmin();
		$I->amOnPage( '/wp-admin/post-new.php?post_type=graphql_document');

		// // Add title should trigger auto-draft, but not save document
		$I->fillField( "//input[@name='post_title']", $post_title );
		$I->fillField( 'content', '{ __typename }');

		// // This might cause auto-draft but not save
		$I->dontSeePostInDatabase( ['post_title' => $post_title] );

		// Publish post
		$I->click('#publish');

		$I->seePostInDatabase( [
			'post_title'  => $post_title,
			'post_status' => 'publish',
			'post_content' => $normalized_query_string,
		]);

		$I->seeElement('//*[@id="message"]');
		$I->see('Post published.', '//*[@id="message"]');
		$I->dontSee('Publish immediately'); // has publish date
	}

	public function haveDraftQueryWithInvalidQueryWhenPublishSavesAsDraftTest( FunctionalTester $I ) {
		$post_title = 'test-post';
		$normalized_query_string = "{\n  __typename\n}\n";

		// Create a new query in the admin editor
		$I->loginAsAdmin();
		$I->amOnPage( '/wp-admin/post-new.php?post_type=graphql_document');

		// Add title should trigger auto-draft, but not save document
		$I->fillField( "//input[@name='post_title']", $post_title );
		$I->fillField( 'content', '{ __typename }');

		// Save draft button.
		$I->click('Save Draft');

		$I->seePostInDatabase( [
			'post_title'  => $post_title,
			'post_status' => 'draft',
			'post_content' => $normalized_query_string,
		]);

		// invalid query
		$I->fillField( 'content', '{ __typename broken');

		// // Publish post button
		$I->click('#publish');

		$I->seePostInDatabase( [
			'post_title'  => $post_title,
			'post_status' => 'draft',
			'post_content' => '{ __typename broken',
		]);

		$I->dontSeeElement('//*[@id="message"]');
		$I->dontSee('Post draft updated.');
		$I->dontSee('Post published.');
		$I->see('Publish immediately'); // does not have publish date
	}

	public function haveDraftQueryWithValidQueryWhenPublishSavesAsPublishedTest( FunctionalTester $I ) {
		$post_title = 'test-post';
		$normalized_query_string = "{\n  __typename\n}\n";

		// Create a new query in the admin editor
		$I->loginAsAdmin();
		$I->amOnPage( '/wp-admin/post-new.php?post_type=graphql_document');

		// Add title should trigger auto-draft, but not save document
		$I->fillField( "//input[@name='post_title']", $post_title );
		$I->fillField( 'content', '{ __typename }');

		// Save draft button.
		$I->click('Save Draft');

		$I->seePostInDatabase( [
			'post_title'  => $post_title,
			'post_status' => 'draft',
			'post_content' => $normalized_query_string,
		]);

		// invalid query
		$I->fillField( 'content', '{ posts { edges { node { id } } } }');

		// // Publish post button
		$I->click('#publish');

		$I->seePostInDatabase( [
			'post_title'  => $post_title,
			'post_status' => 'publish',
			'post_content' => "{\n  posts {\n    edges {\n      node {\n        id\n      }\n    }\n  }\n}\n",
		]);

		$I->seeElement('//*[@id="message"]');
		$I->see('Post published.', '//*[@id="message"]');
		$I->dontSee('Publish immediately'); // has publish date
	}

	public function havePublishedQueryWhenSaveDraftWithInvalidQuerySavesAsDraftTest( FunctionalTester $I ) {
		$post_title = 'test-post';
		$normalized_query_string = "{\n  __typename\n}\n";

		// Create a new query in the admin editor
		$I->loginAsAdmin();
		$I->amOnPage( '/wp-admin/post-new.php?post_type=graphql_document');

		// Add title should trigger auto-draft, but not save document
		$I->fillField( "//input[@name='post_title']", $post_title );
		$I->fillField( 'content', '{ __typename }');

		// Publish post
		$I->click('#publish');

		$I->seePostInDatabase( [
			'post_title'  => $post_title,
			'post_status' => 'publish',
			'post_content' => $normalized_query_string,
		]);

		// invalid query
		$I->fillField( 'content', '{ __typename broken');

		// Change to draft status
		$I->selectOption('#post_status', 'Draft');

		// // Publish post button
		$I->click('#publish');

		$I->seePostInDatabase( [
			'post_title'  => $post_title,
			'post_status' => 'draft',
			'post_content' => '{ __typename broken',
		]);

		$I->dontSeeElement('//*[@id="message"]');
		$I->dontSee('Post published.');
		$I->dontSee('Post updated.');
		$I->dontSee('Post saved.');
		$I->dontSee('Publish immediately'); // has date because already published
	}

	public function havePublishedQueryWhenSaveDraftWithValidQuerySavesAsDraftTest( FunctionalTester $I ) {
		$post_title = 'test-post';
		$normalized_query_string = "{\n  __typename\n}\n";

		// Create a new query in the admin editor
		$I->loginAsAdmin();
		$I->amOnPage( '/wp-admin/post-new.php?post_type=graphql_document');

		// Add title should trigger auto-draft, but not save document
		$I->fillField( "//input[@name='post_title']", $post_title );
		$I->fillField( 'content', '{ __typename }');

		// Publish post
		$I->click('#publish');

		$I->seePostInDatabase( [
			'post_title'  => $post_title,
			'post_status' => 'publish',
			'post_content' => $normalized_query_string,
		]);

		// invalid query
		$I->fillField( 'content', '{ posts { edges { node { id } } } }');

		// Change to draft status
		$I->selectOption('#post_status', 'Draft');

		// // Publish post button
		$I->click('#publish');

		$I->seePostInDatabase( [
			'post_title'  => $post_title,
			'post_status' => 'draft',
			'post_content' => "{\n  posts {\n    edges {\n      node {\n        id\n      }\n    }\n  }\n}\n",
		]);

		$I->seeElement('//*[@id="message"]');
		$I->see('Post draft updated.', '//*[@id="message"]');
		$I->dontSee('Publish immediately'); // has date because already published
	}

	public function havePublishedQueryWithInvalidQueryWhenPublishItShowsPreviousQueryContentTest( FunctionalTester $I ) {
		$post_title = 'test-post';
		$original_query = '{ __typename }';
		$normalized_query_string = "{\n  __typename\n}\n";

		// Create a new query in the admin editor
		$I->loginAsAdmin();
		$I->amOnPage( '/wp-admin/post-new.php?post_type=graphql_document');

		// Add title should trigger auto-draft, but not save document
		$I->fillField( "//input[@name='post_title']", $post_title);
		$I->fillField( 'content', $original_query);

		// Publish post
		$I->click('#publish');

		$I->seePostInDatabase( [
			'post_title'  => $post_title,
			'post_status' => 'publish',
			'post_content' => $normalized_query_string,
		]);

		// invalid query
		$I->fillField( 'content', '{ __typename broken');

		// // Publish post button
		$I->click('#publish');

		// Does not save/overwrite the working query string with broken one.
		// Leaves as published
		$I->seePostInDatabase( [
			'post_title'  => $post_title,
			'post_status' => 'publish',
			'post_content' => $normalized_query_string,
		]);

		$I->dontSeeElement('//*[@id="message"]');
		$I->dontSee('Post published.');
		$I->dontSee('Post updated.');
		$I->dontSee('Post saved.');
		$I->dontSee('Publish immediately'); // has date because already published
	}

	public function createNewQueryWithInvalidContentThenTrashItTest( FunctionalTester $I ) {
		$post_title = 'test-post';

		// Create a new query in the admin editor
		$I->loginAsAdmin();
		$I->amOnPage( '/wp-admin/post-new.php?post_type=graphql_document');

		// Add title should trigger auto-draft, but not save document
		$I->fillField( "//input[@name='post_title']", $post_title );
		$I->fillField( 'content', '{ __typename broken');

		// Save draft button.
		$I->click('Save Draft');

		$I->seePostInDatabase( [
			'post_title'  => $post_title,
			'post_status' => 'draft',
			'post_content' => '{ __typename broken',
		]);

		// should see our admin error
		$I->seeElement('//*[@id="plugin-message"]');
		$I->see('Invalid graphql query string "{ __typename broken"', '//*[@id="plugin-message"]');

		$I->click('Move to Trash');

		$I->seePostInDatabase( [
			'post_title'  => $post_title,
			'post_status' => 'trash',
			'post_content' => '{ __typename broken',
		]);

		// should not see our admin error
		$I->dontSeeElement('//*[@id="plugin-message"]');
		$I->dontSee('Invalid graphql query string "{ __typename broken"', '//*[@id="plugin-message"]');

		// Go to list of saved documents in trash. Restore to 'untrash' it.
		$I->amOnPage('/wp-admin/edit.php?post_status=trash&post_type=graphql_document');
		$I->click('Restore');

		$I->seePostInDatabase( [
			'post_title'  => $post_title,
			'post_status' => 'draft',
			'post_content' => '{ __typename broken',
		]);

		// should not see our admin error
		$I->dontSeeElement('//*[@id="plugin-message"]');
		$I->dontSee('Invalid graphql query string "{ __typename broken"', '//*[@id="plugin-message"]');
	}
}

<?php

$I = new FunctionalTester( $scenario );
$I->wantTo( 'Get nav menu with menu items' );

// Clear posts table.
$I->dontHavePostInDatabase([], true);

// Adding menus requires a theme.
$I->useTheme( 'twentyseventeen' );

// Create a menu. Note that haveMenuInDatabase returns an array of the term ID
// and the term taxonomy ID.
$menu_ids = $I->haveMenuInDatabase( 'test-menu', 'test-location' );
$menu_id = intval( $menu_ids[0] );

// Keep track of created menu items and posts.
$menu_item_ids = [];
$post_ids = [];
$count = 10;

// Create some Post menu items.
for ( $x = 1; $x <= $count; $x++ ) {
	$post_id = $I->havePostInDatabase(
		[
			'post_type'    => 'post',
			'post_title'   => "test post {$x}",
			'post_content' => 'test content',
		]
	);

	$post_ids[] = $post_id;

	$menu_item_ids[] = $I->haveMenuItemInDatabase(
		'test-menu',
		"Test menu item ${x}",
		null,
		[
			'object'    => 'post',
			'object_id' => $post_id,
			'type'      => 'post_type',
		]
	);
}

// Set the content-type so we get a proper response from the API.
$I->haveHttpHeader( 'Content-Type', 'application/json' );

// Query for the menu.
$I->sendPOST( 'http://wpgraphql.test/graphql', json_encode( [
	'query' => '
	{
		menus( where: { id: ' . $menu_id . ' } ){
			edges {
				node {
					menuId
					menuItems {
						edges {
							node {
								menuItemId
								connectedObject {
									... on Post {
										postId
									}
								}
							}
						}
					}
				}
			}
		}
	}'
] ) );

// Check response.
$I->seeResponseCodeIs( 200 );
$I->seeResponseIsJson();
$response       = $I->grabResponse();
$response_array = json_decode( $response, true );

// The query is valid and has no errors.
$I->assertArrayNotHasKey( 'errors', $response_array );

// The response is properly returning data as expected.
$I->assertArrayHasKey( 'data', $response_array );

// The correct menu is returned.
$I->assertEquals( 1, count( $response_array['data']['menus']['edges'] ) );
$I->assertEquals( $menu_id, $response_array['data']['menus']['edges'][0]['node']['menuId'] );

// The correct number of menu items are returned.
$I->assertEquals( $count, count( $response_array['data']['menus']['edges'][0]['node']['menuItems']['edges'] ) );

// The returned menu items and connected posts have the expected IDs.
foreach( $response_array['data']['menus']['edges'][0]['node']['menuItems']['edges'] as $menu_item ) {
	$I->assertTrue( in_array( $menu_item['node']['menuItemId'], $menu_item_ids, true ) );
	$I->assertTrue( in_array( $menu_item['node']['connectedObject']['postId'], $post_ids, true ) );
}

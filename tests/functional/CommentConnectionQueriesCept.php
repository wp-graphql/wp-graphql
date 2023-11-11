<?php

$I = new FunctionalTester( $scenario );
$I->wantTo( 'Test Comment Connection Queries' );

$admin_id = $I->haveUserInDatabase( 'admin', 'administrator' );

$comment_post_id = $I->havePostInDatabase(
	[
		'post_type'    => 'post',
		'post_status'  => 'publish',
		'post_title'   => 'test post',
		'post_content' => 'test post with comments',
		'post_author'  => $admin_id,
	]
);

$comment_ids = [];
for ( $i = 1; $i <= 20; $i++ ) {
	$date          = date( 'Y-m-d H:i:s', strtotime( "-1 day +{$i} minutes" ) );
	$comment_ids[] = $I->haveCommentInDatabase(
		$comment_post_id,
		[
			'comment_content' => 'comment ' . $i,
			'comment_date'    => $date,
		]
	);
}

// reverse the array to have the comment ids in order of newest to oldest
$comment_ids = array_reverse( $comment_ids );

// assert that 20 comments were created
$I->assertCount( 20, $comment_ids, '20 comments were created' );

// define the query we'll use for paginated requests
function getQuery() {
	return '
	query commentsQuery($first:Int $last:Int $after:String $before:String $where:RootQueryToCommentConnectionWhereArgs ){
		comments( first:$first last:$last after:$after before:$before where:$where ) {
			pageInfo {
				hasNextPage
				hasPreviousPage
				startCursor
				endCursor
			}
			edges {
				cursor
				node {
					id
					databaseId
					content
					date
				}
			}
			nodes {
				databaseId
			}
		}
	}
	';
}

// query page 1
$I->haveHttpHeader( 'Content-Type', 'application/json' );
$I->sendPost(
	'http://localhost/graphql',
	json_encode(
		[
			'query'     => getQuery(),
			'variables' => [
				'first' => 2,
			],
		]
	)
);

// Assert the query was a success
$I->seeResponseCodeIs( 200 );
$I->seeResponseIsJson();

// get the response and convert it to a PHP array
$response       = $I->grabResponse();
$response_array = json_decode( $response, true );

$I->assertCount( 2, $response_array['data']['comments']['edges'], 'There should be 2 comments in the response' );

// page 1 query should have comments 1 and 2 from the $comment_ids array
$I->assertSame( $comment_ids[0], $response_array['data']['comments']['nodes'][0]['databaseId'], 'page 1 has comment_id #1' );
$I->assertSame( $comment_ids[1], $response_array['data']['comments']['nodes'][1]['databaseId'], 'page 1 has comment_id #2' );

// send query for page 2
$I->sendPost(
	'http://localhost/graphql',
	json_encode(
		[
			'query'     => getQuery(),
			'variables' => [
				'first' => 2,
				'after' => $response_array['data']['comments']['pageInfo']['endCursor'],
			],
		]
	)
);

$I->seeResponseCodeIs( 200 );
$I->seeResponseIsJson();
$page_2_response       = $I->grabResponse();
$page_2_response_array = json_decode( $page_2_response, true );

$I->assertCount( 2, $page_2_response_array['data']['comments']['edges'], 'page 2 has 2 comments' );

// page 2 query should have comments 3 and 4 (array index 2,3) from the $comment_ids array
$I->assertSame( $comment_ids[2], $page_2_response_array['data']['comments']['nodes'][0]['databaseId'], 'page 2 has comment #3' );
$I->assertSame( $comment_ids[3], $page_2_response_array['data']['comments']['nodes'][1]['databaseId'], 'page 2 has comment #4' );


// send query for page 3
$I->sendPost(
	'http://localhost/graphql',
	json_encode(
		[
			'query'     => getQuery(),
			'variables' => [
				'first' => 2,
				'after' => $page_2_response_array['data']['comments']['pageInfo']['endCursor'],
			],
		]
	)
);

$I->seeResponseCodeIs( 200 );
$I->seeResponseIsJson();
$page_3_response       = $I->grabResponse();
$page_3_response_array = json_decode( $page_3_response, true );

$I->assertCount( 2, $page_3_response_array['data']['comments']['edges'], 'page 2 has 2 comments' );

// page 3 query should have comments 3 and 4 (array index 4,5) from the $comment_ids array
$I->assertSame( $comment_ids[4], $page_3_response_array['data']['comments']['nodes'][0]['databaseId'], 'page 2 has comment #5' );
$I->assertSame( $comment_ids[5], $page_3_response_array['data']['comments']['nodes'][1]['databaseId'], 'page 2 has comment #6' );

<?php
namespace DFM\WPGraphQL\Queries;

use DFM\WPGraphQL\Queries\PostQuery;
use DFM\WPGraphQL\Types\AttachmentsType;
use Youshido\GraphQL\Execution\ResolveInfo;

class AttachmentQuery extends PostQuery {

	public function getName() {
		return __( 'attachments', 'wp-graphql' );
	}

	public function getType() {
		return new AttachmentsType();
	}

	public function getDescription() {
		return __( 'Retrieve a list of attachments', 'dfm-graphql-endpoints' );
	}

	public function resolve( $value, array $args, ResolveInfo $info ) {

		// Set the default $query_args
		$query_args = [
			'post_type' => 'attachment',
			'posts_per_page' => 10,
			'post_status' => array( 'any' )
		];

		// Combine the defaults with the passed args
		$query_args = wp_parse_args( $args, $query_args );

		// Make sure the per_page has a max of 100
		// as we don't want to overload things
		$query_args['posts_per_page'] = ( ! empty( $args['per_page'] ) && 100 >= ( $args['per_page'] ) ) ? $args['per_page'] : $query_args['posts_per_page'];

		// Clean up the unneeded $query_args
		unset( $query_args['per_page'] );

		// Run the Query
		$attachments = new \WP_Query( $query_args );

		return $attachments;

	}

}
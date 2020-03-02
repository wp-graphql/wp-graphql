<?php
namespace WPGraphQL\Data\Connection;

use WPGraphQL\Model\Term;

class TaxonomyConnectionResolver extends AbstractConnectionResolver {

	public function get_query_args() {

		if ( $this->source instanceof Term ) {
			$query_args['name'] = $this->source->taxonomyName;
		}

		$query_args['show_in_graphql'] = true;
		return $query_args;
	}

	public function is_valid_offset( $offset ) {
		// TODO: Implement is_valid_offset() method.
	}

	public function should_execute() {
		return true;
	}

	public function get_items() {
		return $this->get_query();
	}

	public function get_query() {
		return get_taxonomies( $this->get_query_args() );
	}

}

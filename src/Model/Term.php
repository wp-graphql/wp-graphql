<?php

namespace WPGraphQL\Model;

use GraphQLRelay\Relay;

/**
 * Class Term - Models data for Terms
 *
 * @property string       $id
 * @property int          $term_id
 * @property int          $count
 * @property string       $description
 * @property string       $name
 * @property string       $slug
 * @property int          $termGroupId
 * @property int          $termTaxonomyId
 * @property string       $taxonomyName
 * @property string       $link
 * @property int          $parentId
 * @property array        $ancestors
 *
 * @package WPGraphQL\Model
 */
class Term extends Model {

	/**
	 * Stores the incoming WP_Term object
	 *
	 * @var \WP_Term $data
	 * @access protected
	 */
	protected $data;

	/**
	 * Stores the taxonomy object for the term being modeled
	 *
	 * @var null|\WP_Taxonomy $taxonomy_object
	 * @access protected
	 */
	protected $taxonomy_object;

	/**
	 * Term constructor.
	 *
	 * @param \WP_Term $term The incoming WP_Term object that needs modeling
	 *
	 * @access public
	 * @return void
	 * @throws \Exception
	 */
	public function __construct( \WP_Term $term ) {
		$this->data            = $term;
		$this->taxonomy_object = get_taxonomy( $term->taxonomy );
		parent::__construct();
	}

	/**
	 * Initializes the Term object
	 *
	 * @access protected
	 * @return void
	 */
	protected function init() {

		if ( empty( $this->fields ) ) {

			$this->fields = [
				'id'             => function() {
					return ( ! empty( $this->data->taxonomy ) && ! empty( $this->data->term_id ) ) ? Relay::toGlobalId( $this->data->taxonomy, $this->data->term_id ) : null;
				},
				'term_id'        => function() {
					return ( ! empty( $this->data->term_id ) ) ? absint( $this->data->term_id ) : null;
				},
				'count'          => function() {
					return ! empty( $this->data->count ) ? absint( $this->data->count ) : null;
				},
				'description'    => function() {
					return ! empty( $this->data->description ) ? $this->data->description : null;
				},
				'name'           => function() {
					return ! empty( $this->data->name ) ? $this->data->name : null;
				},
				'slug'           => function() {
					return ! empty( $this->data->slug ) ? $this->data->slug : null;
				},
				'termGroupId'    => function() {
					return ! empty( $this->data->term_group ) ? absint( $this->data->term_group ) : null;
				},
				'termTaxonomyId' => function() {
					return ! empty( $this->data->term_taxonomy_id ) ? absint( $this->data->term_taxonomy_id ) : null;
				},
				'taxonomyName'   => function() {
					return ! empty( $this->taxonomy_object->name ) ? $this->taxonomy_object->name : null;
				},
				'link'           => function() {
					$link = get_term_link( $this->data->term_id );
					return ( ! is_wp_error( $link ) ) ? $link : null;
				},
				'parentId'       => function() {
					return ! empty( $this->data->parent ) ? $this->data->parent : null;
				},
			];

			if ( isset( $this->taxonomy_object ) && isset( $this->taxonomy_object->graphql_single_name ) ) {
				$type_id                  = $this->taxonomy_object->graphql_single_name . 'Id';
				$this->fields[ $type_id ] = absint( $this->data->term_id );
			};

		}

	}

}

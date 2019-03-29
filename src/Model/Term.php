<?php

namespace WPGraphQL\Model;


use GraphQLRelay\Relay;
use WPGraphQL\Data\DataSource;

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
	 * @var \WP_Term $term
	 * @access protected
	 */
	protected $term;

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
		$this->term = $term;
		$this->taxonomy_object = get_taxonomy( $term->taxonomy );
		parent::__construct( 'TermObject', $term );
		$this->init();
	}

	/**
	 * Initializes the Term object
	 *
	 * @access public
	 * @return void
	 */
	public function init() {

		if ( empty( $this->fields ) ) {
			$this->fields = [
				'id' => function() {
					return ( ! empty( $this->term->taxonomy ) && ! empty( $this->term->term_id ) ) ? Relay::toGlobalId( $this->term->taxonomy, $this->term->term_id ) : null;
				},
				'term_id' => function() {
					return ( ! empty( $this->term->term_id ) ) ? absint( $this->term->term_id ) : null;
				},
				'count' => function() {
					return ! empty( $this->term->count ) ? absint( $this->term->count ) : null;
				},
				'description' => function() {
					return ! empty( $this->term->description ) ? $this->term->description : null;
				},
				'name' => function() {
					return ! empty( $this->term->name ) ? $this->term->name : null;
				},
				'slug' => function() {
					return ! empty( $this->term->slug ) ? $this->term->slug : null;
				},
				'termGroupId' => function() {
					return ! empty( $this->term->term_group ) ? absint( $this->term->term_group ) : null;
				},
				'termTaxonomyId' => function() {
					return ! empty( $this->term->term_taxonomy_id ) ? absint( $this->term->term_taxonomy_id ) : null;
				},
				'taxonomyName' => function() {
					return ! empty( $this->taxonomy_object->name ) ? $this->taxonomy_object->name : null;
				},
				'link' => function() {
					$link = get_term_link( $this->term->term_id );
					return ( ! is_wp_error( $link ) ) ? $link : null;
				},
				'parentId' => function() {
					return ! empty( $this->term->parent ) ? $this->term->parent : null;
				}
			];

			if ( isset( $this->taxonomy_object ) && isset( $this->taxonomy_object->graphql_single_name ) ) {
				$type_id                 = $this->taxonomy_object->graphql_single_name . 'Id';
				$this->fields[ $type_id ] = absint( $this->term->term_id );
			};

			parent::prepare_fields();

		}

	}

}

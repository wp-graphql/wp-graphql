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
 * @property \WP_Taxonomy $taxonomy
 * @property string       $link
 * @property \WP_Term     $parent
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
	 * Stores the fields for the object
	 *
	 * @var null|array $fields
	 * @access protected
	 */
	protected $fields;

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
	 * @param \WP_Term          $term   The incoming WP_Term object that needs modeling
	 * @param null|string|array $filter The field or fields to build in the modeled object. You can
	 *                                  pass null to build all of the fields, a string to only
	 *                                  build an object with one field, or an array of field keys
	 *                                  to build an object with those keys and their respective
	 *                                  values.
	 *
	 * @access public
	 * @return void
	 * @throws \Exception
	 */
	public function __construct( \WP_Term $term, $filter = null ) {

		if ( empty( $term ) ) {
			throw new \Exception( __( 'An empty WP_Term object was used to initialize this object', 'wp-graphql' ) );
		}

		$this->term = $term;
		$this->taxonomy_object = get_taxonomy( $term->taxonomy );
		parent::__construct( 'TermObject', $term );

		$this->init( $filter );

	}

	/**
	 * Initializes the Term object
	 *
	 * @param null|string|array $fields The field or fields to build in the modeled object. You can
	 *                                  pass null to build all of the fields, a string to only
	 *                                  build an object with one field, or an array of field keys
	 *                                  to build an object with those keys and their respective
	 *                                  values.
	 *
	 * @access public
	 * @return void
	 */
	public function init( $fields = null ) {

		if ( null === $this->fields ) {
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
				'taxonomy' => function() {
					return ! empty( $this->taxonomy_object ) ? $this->taxonomy_object : null;
				},
				'link' => function() {
					$link = get_term_link( $this->term->term_id );
					return ( ! is_wp_error( $link ) ) ? $link : null;
				}
			];

			if ( isset( $this->taxonomy_object ) && isset( $this->taxonomy_object->graphql_single_name ) ) {
				$type_id                 = $this->taxonomy_object->graphql_single_name . 'Id';
				$this->fields[ $type_id ] = absint( $this->term->term_id );
			};

			if ( ! empty( $this->taxonomy_object->hierarchical ) && true === $this->taxonomy_object->hierarchical ) {

				$this->fields['parent'] = function() {
					return ! empty( $this->term->parent ) ? DataSource::resolve_term_object( $this->term->parent, $this->term->taxonomy ) : null;
				};

				$this->fields['ancestors'] = function() {
					$ancestors    = [];
					$ancestor_ids = get_ancestors( $this->term->term_id, $this->term->taxonomy );
					if ( ! empty( $ancestor_ids ) ) {
						foreach ( $ancestor_ids as $ancestor_id ) {
							$ancestors[] = DataSource::resolve_term_object( $ancestor_id, $this->term->taxonomy );
						}
					}

					return ! empty( $ancestors ) ? $ancestors : null;
				};

			}
		}

		parent::prepare_fields( $this->fields, $fields );
	}

}

<?php

namespace WPGraphQL\Model;

use GraphQLRelay\Relay;
use WP_Taxonomy;
use WP_Term;

/**
 * Class Term - Models data for Terms
 *
 * @property ?int    $count
 * @property ?int    $databaseId
 * @property ?string $description
 * @property ?string $id
 * @property ?string $link
 * @property ?string $name
 * @property ?string $parentId
 * @property ?int    $parentDatabaseId
 * @property ?string $slug
 * @property ?string $taxonomyName
 * @property ?int    $termGroupId
 * @property ?int    $termTaxonomyId
 *
 * Aliases:
 * @property ?int     $term_id
 *
 * @package WPGraphQL\Model
 *
 * @extends \WPGraphQL\Model\Model<\WP_Term>
 */
class Term extends Model {
	/**
	 * Stores the taxonomy object for the term being modeled
	 *
	 * @var \WP_Taxonomy|null $taxonomy_object
	 */
	protected $taxonomy_object;

	/**
	 * The global Post instance
	 *
	 * @var \WP_Post
	 */
	protected $global_post;

	/**
	 * Term constructor.
	 *
	 * @param \WP_Term $term The incoming WP_Term object that needs modeling
	 *
	 * @return void
	 */
	public function __construct( WP_Term $term ) {
		$this->data            = $term;
		$taxonomy              = get_taxonomy( $term->taxonomy );
		$this->taxonomy_object = $taxonomy instanceof WP_Taxonomy ? $taxonomy : null;
		parent::__construct();
	}

	/**
	 * {@inheritDoc}
	 */
	public function setup() {
		global $wp_query, $post;

		/**
		 * Store the global post before overriding
		 */
		$this->global_post = $post;

		if ( $this->data instanceof WP_Term ) {

			/**
			 * Reset global post
			 */
			$GLOBALS['post'] = get_post( 0 ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride

			/**
			 * Parse the query to tell WordPress
			 * how to setup global state
			 */
			if ( 'category' === $this->data->taxonomy ) {
				$wp_query->parse_query(
					[
						'category_name' => $this->data->slug,
					]
				);
			} elseif ( 'post_tag' === $this->data->taxonomy ) {
				$wp_query->parse_query(
					[
						'tag' => $this->data->slug,
					]
				);
			}

			$wp_query->queried_object    = get_term( $this->data->term_id, $this->data->taxonomy );
			$wp_query->queried_object_id = $this->data->term_id;
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function tear_down() {
		$GLOBALS['post'] = $this->global_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		wp_reset_postdata();
	}

	/**
	 * {@inheritDoc}
	 */
	protected function init() {
		if ( empty( $this->fields ) ) {
			$this->fields = [
				'count'                    => function () {
					return ! empty( $this->data->count ) ? absint( $this->data->count ) : null;
				},
				'databaseId'               => function () {
					return ( ! empty( $this->data->term_id ) ) ? absint( $this->data->term_id ) : null;
				},
				'description'              => function () {
					return ! empty( $this->data->description ) ? $this->html_entity_decode( $this->data->description, 'description' ) : null;
				},
				'enqueuedScriptsQueue'     => static function () {
					global $wp_scripts;
					$wp_scripts->reset();
					do_action( 'wp_enqueue_scripts' );
					$queue = $wp_scripts->queue;
					$wp_scripts->reset();
					$wp_scripts->queue = [];

					return $queue;
				},
				'enqueuedStylesheetsQueue' => static function () {
					global $wp_styles;
					do_action( 'wp_enqueue_scripts' );
					$queue = $wp_styles->queue;
					$wp_styles->reset();
					$wp_styles->queue = [];

					return $queue;
				},
				'id'                       => function () {
					return ( ! empty( $this->data->taxonomy ) && ! empty( $this->databaseId ) ) ? Relay::toGlobalId( 'term', (string) $this->databaseId ) : null;
				},
				'link'                     => function () {
					$link = get_term_link( $this->data->term_id );

					return ! is_wp_error( $link ) ? $link : null;
				},
				'name'                     => function () {
					return ! empty( $this->data->name ) ? $this->html_entity_decode( $this->data->name, 'name', true ) : null;
				},
				'parentDatabaseId'         => function () {
					return ! empty( $this->data->parent ) ? $this->data->parent : null;
				},
				'parentId'                 => function () {
					return ! empty( $this->parentDatabaseId ) ? Relay::toGlobalId( 'term', (string) $this->parentDatabaseId ) : null;
				},
				'slug'                     => function () {
					return ! empty( $this->data->slug ) ? urldecode( $this->data->slug ) : null;
				},
				'taxonomyName'             => function () {
					return ! empty( $this->taxonomy_object->name ) ? $this->taxonomy_object->name : null;
				},
				'termGroupId'              => function () {
					return ! empty( $this->data->term_group ) ? absint( $this->data->term_group ) : null;
				},
				'termTaxonomyId'           => function () {
					return ! empty( $this->data->term_taxonomy_id ) ? absint( $this->data->term_taxonomy_id ) : null;
				},
				'uri'                      => function () {
					$link = $this->link;

					$maybe_url = isset( $link ) ? wp_parse_url( $link ) : null;

					// If wp_parse_url() returned false, we can assume it's been filtered and just return the link value.
					if ( false === $maybe_url ) {
						return $link;
					}

					// Replace the home_url in the link in order to return a relative uri.
					// For subdirectory multisites, this replaces the home_url which includes the subdirectory.
					return ! empty( $link ) ? str_ireplace( home_url(), '', $link ) : null;
				},

				// Aliases.
				'term_id'                  => function () {
					return $this->databaseId;
				},
			];

			// Deprecated.
			if ( isset( $this->taxonomy_object, $this->taxonomy_object->graphql_single_name ) ) {
				$type_id                  = $this->taxonomy_object->graphql_single_name . 'Id';
				$this->fields[ $type_id ] = absint( $this->data->term_id );
			}
		}
	}
}

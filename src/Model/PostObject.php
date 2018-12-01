<?php

namespace WPGraphQL\Model;

use GraphQLRelay\Relay;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Types;

/**
 * Class PostObject
 *
 * This is the model layer for PostObjects. This is the central source of truth for resolving a
 * PostObject.
 *
 * If the current viewer has or doesn't have access to view the PostObject, that's determined here.
 *
 * Any conversions from a standard \WP_Post to the shape needed to resolve in the API happens here
 * as well.
 *
 * @package WPGraphQL\Model
 */
class PostObject {

	/**
	 * @var \WP_Post
	 */
	protected $post;

	/**
	 *
	 */
	protected $post_type_object;

	/**
	 * @var bool
	 */
	protected $is_private = false;

	/**
	 * @var bool
	 */
	protected $is_restricted = false;

	/**
	 * PostObject constructor.
	 *
	 * @param \WP_Post|null $post_object The Post Object from the database
	 *
	 * @return \WP_Post
	 */
	public function __construct( \WP_Post $post_object = null ) {

		/**
		 * If no \WP_Post object is passed, return null
		 */
		if ( empty( $post_object ) ) {
			return null;
		}

		/**
		 * Set the context of the PostObject
		 */
		$this->post             = $post_object;
		$this->post_type_object = isset( $post_object->post_type ) ? get_post_type_object( $post_object->post_type ) : null;

		if ( 'nav_menu_item' === $this->post->post_type ) {
			$this->post = wp_setup_nav_menu_item( $post_object );
		}

		/**
		 * Run permissions checks on the post
		 */
		$this->check_permissions();

		/**
		 * Return the post object
		 */
		$prepared = $this->get_instance();

		return $prepared;

	}

	/**
	 * Check the permissions of the current user against the PostObject being resolved to determine
	 * whether the post should be considered Public, Private or Restricted.
	 *
	 * Public: return fields as is
	 * Private: return null, as if the post never existed
	 * Restricted: return partial fields, as the user has access to know the post exists, but they
	 * can't access all the fields
	 *
	 * @return void
	 */
	protected function check_permissions() {

		/**
		 * If the post_type isn't (not registered) or is not allowed in WPGraphQL,
		 * mark the post as private
		 */
		if ( empty( $this->post_type_object ) || ! in_array( $this->post_type_object->name, \WPGraphQL::$allowed_post_types, true ) ) {
			$this->is_private = true;
		}

		/**
		 * If the current user is the author of the post, it's not restricted
		 * or private;
		 */
		if ( (int) $this->post->post_author === (int) get_current_user_id() || $this->post->post_status === 'publish' ) {
			$this->is_restricted = false;
			$this->is_private    = false;
			/**
			 * If the current user is NOT the author of the post
			 */
		} else {

			/**
			 * Determine permissions based on post_status
			 */
			switch ( $this->post->post_status ) {
				/**
				 * Users cannot access private posts they are not the author of
				 */
				case 'private':
					$this->is_private = true;
					break;
				/**
				 * Users must have access to edit_others_posts to view
				 */
				case 'trash':
					if ( ! current_user_can( $this->post_type_object->cap->edit_posts, $this->post->ID ) ) {
						$this->is_restricted = true;
					}
					break;
				case 'draft':
					if ( ! current_user_can( $this->post_type_object->cap->edit_others_posts, $this->post->ID ) ) {
						$this->is_restricted = true;
					}
					break;
				default:
					break;
			}

			switch ( $this->post->post_type ) {
				case 'nav_menu_item':
					$this->is_private = false;
					$this->is_restricted = false;
					break;
				case 'revision':
					$parent               = get_post( (int) $this->post->post_parent );
					$parent_post_type_obj = get_post_type_object( $parent->post_type );
					if ( ! current_user_can( $parent_post_type_obj->cap->edit_post, $parent->ID ) ) {
						$this->is_private = true;
					}
					break;
				default:
					break;
			}

			// Check how to handle this. . .we need a way for password protected posts
			// to accept a password argument to view the post
			if ( ! empty( $this->post->post_password ) ) {
				if ( ! current_user_can( $this->post_type_object->cap->edit_others_posts, $this->post->ID ) ) {
					$this->is_restricted = true;
				}
			}
		}


		/**
		 *
		 */
		do_action( 'graphql_post_object_check_permissions', $this );


	}

	/**
	 * Get the instance of the Post Object to return the the resolvers.
	 *
	 * @return null|\WP_Post
	 */
	public function get_instance() {

		/**
		 * If the post is private, null it
		 */
		if ( true === $this->is_private ) {
			return null;
		}


		/**
		 * Setup the PostData that makes up a PostObject
		 */
		$post_fields = [
			'ID'            => $this->post->ID,
			'post_author'   => ! empty( $this->post->post_author ) ? $this->post->post_author : null,
			'id'            => function ( $source ) {
				return ( ! empty( $this->post->post_type ) && ! empty( $this->post->ID ) ) ? Relay::toGlobalId( $this->post->post_type, $this->post->ID ) : null;
			},
			'post_type'     => isset( $this->post->post_type ) ? $this->post->post_type : null,
			'ancestors'     => function ( $source, $args ) {
				$ancestors    = [];
				$types        = ! empty( $args['types'] ) ? $args['types'] : [ $this->post->post_type ];
				$ancestor_ids = get_ancestors( $this->post->ID, $this->post->post_type );
				if ( ! empty( $ancestor_ids ) ) {
					foreach ( $ancestor_ids as $ancestor_id ) {
						$ancestor_obj = get_post( $ancestor_id );
						if ( in_array( $ancestor_obj->post_type, $types, true ) ) {
							$ancestors[] = DataSource::resolve_post_object( $ancestor_obj->ID, $ancestor_obj->post_type );
						}
					}
				}

				return ! empty( $ancestors ) ? $ancestors : null;
			},
			'author'        => function () {
				$id     = $this->post->post_author;
				$author = isset( $id ) ? DataSource::resolve_user( (int) $id ) : null;

				return $author;
			},
			'date'          => function () {
				return ! empty( $this->post->post_date ) && '0000-00-00 00:00:00' !== $this->post->post_date ? $this->post->post_date : null;
			},
			'dateGmt'       => function () {
				return ! empty( $this->post->post_date_gmt ) ? Types::prepare_date_response( $this->post->post_date_gmt ) : null;
			},
			'content'       => function ( $source, $args ) {
				$content = ! empty( $this->post->post_content ) ? $this->post->post_content : null;

				// If the raw format is requested, don't apply any filters.
				if ( isset( $args['format'] ) && 'raw' === $args['format'] ) {
					return ! empty( $content ) ? $content : null;
				}

				return ! empty( $content ) ? apply_filters( 'the_content', $content ) : null;
			},
			'title'         => function ( $source, $args ) {
				$id    = ! empty( $this->post->ID ) ? $this->post->ID : null;
				$title = ! empty( $this->post->post_title ) ? $this->post->post_title : null;

				// If the raw format is requested, don't apply any filters.
				if ( isset( $args['format'] ) && 'raw' === $args['format'] ) {
					return $title;
				}

				return apply_filters( 'the_title', $title, $id );
			},
			'excerpt'       => function ( $source, $args ) {
				$excerpt = ! empty( $this->post->post_excerpt ) ? $this->post->post_excerpt : null;

				// If the raw format is requested, don't apply any filters.
				if ( isset( $args['format'] ) && 'raw' === $args['format'] ) {
					return $excerpt;
				}

				$excerpt = apply_filters( 'get_the_excerpt', $excerpt, $this->post );

				return apply_filters( 'the_excerpt', $excerpt );
			},
			'post_status'   => ! empty( $this->post->post_status ) ? $this->post->post_status : null,
			'status'        => function () {
				return ! empty( $this->post->post_status ) ? $this->post->post_status : null;
			},
			'commentStatus' => function () {
				return ! empty( $this->post->comment_status ) ? $this->post->comment_status : null;
			},
			'pingStatus'    => function () {
				return ! empty( $this->post->ping_status ) ? $this->post->ping_status : null;
			},
			'slug'          => function () {
				return ! empty( $this->post->post_name ) ? $this->post->post_name : null;
			},
			'toPing'        => function () {
				return ! empty( $this->post->to_ping ) && is_array( $this->post->to_ping ) ? implode( ',', (array) $this->post->to_ping ) : null;
			},
			'pinged'        => function () {
				return ! empty( $this->post->pinged ) && is_array( $this->post->pinged ) ? implode( ',', (array) $this->post->pinged ) : null;
			},
			'modified'      => function () {
				return ! empty( $this->post->post_modified ) && '0000-00-00 00:00:00' !== $this->post->post_modified ? $this->post->post_modified : null;
			},
			'modifiedGmt'   => function () {
				return ! empty( $this->post->post_modified_gmt ) ? Types::prepare_date_response( $this->post->post_modified_gmt ) : null;
			},
			'parent'        => function () {
				$parent_post = ! empty( $this->post->post_parent ) ? get_post( $this->post->post_parent ) : null;

				return isset( $parent_post->ID ) && isset( $parent_post->post_type ) ? DataSource::resolve_post_object( $parent_post->ID, $parent_post->post_type ) : $parent_post;
			},
			'editLast'      => function () {
				$edit_last = get_post_meta( $this->post->ID, '_edit_last', true );

				return ! empty( $edit_last ) ? DataSource::resolve_user( absint( $edit_last ) ) : null;
			},
			'editLock'      => function () {
				$edit_lock       = get_post_meta( $this->post->ID, '_edit_lock', true );
				$edit_lock_parts = explode( ':', $edit_lock );

				return ! empty( $edit_lock_parts ) ? $edit_lock_parts : null;
			},
			'enclosure'     => function () {
				$enclosure = get_post_meta( $this->post->ID, 'enclosure', true );

				return ! empty( $enclosure ) ? $enclosure : null;
			},
			'guid'          => function () {
				return ! empty( $this->post->guid ) ? $this->post->guid : null;
			},
			'menuOrder'     => function () {
				return ! empty( $this->post->menu_order ) ? absint( $this->post->menu_order ) : null;
			},
			'link'          => function () {
				$link = get_permalink( $this->post->ID );

				return ! empty( $link ) ? $link : null;
			},
			'uri'           => function () {
				$uri = get_page_uri( $this->post->ID );

				return ! empty( $uri ) ? $uri : null;
			},
			'terms'         => function ( $source, $args, $context, $info ) {

				/**
				 * If the $arg for taxonomies is populated, use it as the $allowed_taxonomies
				 * otherwise use the default $allowed_taxonomies passed down
				 */
				$taxonomies = [];
				if ( ! empty( $args['taxonomies'] ) && is_array( $args['taxonomies'] ) ) {
					$taxonomies = $args['taxonomies'];
				} else {
					$connected_taxonomies = get_object_taxonomies( $this->post, 'names' );
					foreach ( $connected_taxonomies as $taxonomy ) {
						if ( in_array( $taxonomy, \WPGraphQL::$allowed_taxonomies ) ) {
							$taxonomies[] = $taxonomy;
						}
					}
				}

				$tax_terms = [];
				if ( ! empty( $taxonomies ) ) {

					$term_query = new \WP_Term_Query( [
						'taxonomy'   => $taxonomies,
						'object_ids' => $this->post->ID,
					] );

					$tax_terms = $term_query->get_terms();

				}

				return ! empty( $tax_terms ) && is_array( $tax_terms ) ? $tax_terms : null;
			},
			'termNames'     => function ( $source, $args, $context, $info ) {

				/**
				 * If the $arg for taxonomies is populated, use it as the $allowed_taxonomies
				 * otherwise use the default $allowed_taxonomies passed down
				 */
				$taxonomies = [];
				if ( ! empty( $args['taxonomies'] ) && is_array( $args['taxonomies'] ) ) {
					$taxonomies = $args['taxonomies'];
				} else {
					$connected_taxonomies = get_object_taxonomies( $this->post, 'names' );
					foreach ( $connected_taxonomies as $taxonomy ) {
						if ( in_array( $taxonomy, \WPGraphQL::$allowed_taxonomies ) ) {
							$taxonomies[] = $taxonomy;
						}
					}
				}

				$tax_terms = [];
				if ( ! empty( $taxonomies ) ) {

					$term_query = new \WP_Term_Query( [
						'taxonomy'   => $taxonomies,
						'object_ids' => [ $this->post->ID ],
					] );

					$tax_terms = $term_query->get_terms();

				}
				$term_names = ! empty( $tax_terms ) && is_array( $tax_terms ) ? wp_list_pluck( $tax_terms, 'name' ) : [];

				return ! empty( $term_names ) ? $term_names : null;
			},
			'termSlugs'     => function ( $source, $args, $context, $info ) {

				/**
				 * If the $arg for taxonomies is populated, use it as the $allowed_taxonomies
				 * otherwise use the default $allowed_taxonomies passed down
				 */
				$taxonomies = [];
				if ( ! empty( $args['taxonomies'] ) && is_array( $args['taxonomies'] ) ) {
					$taxonomies = $args['taxonomies'];
				} else {
					$connected_taxonomies = get_object_taxonomies( $this->post, 'names' );
					foreach ( $connected_taxonomies as $taxonomy ) {
						if ( in_array( $taxonomy, \WPGraphQL::$allowed_taxonomies ) ) {
							$taxonomies[] = $taxonomy;
						}
					}
				}

				$tax_terms = [];
				if ( ! empty( $taxonomies ) ) {

					$term_query = new \WP_Term_Query( [
						'taxonomy'   => $taxonomies,
						'object_ids' => [ $this->post->ID ],
					] );

					$tax_terms = $term_query->get_terms();

				}
				$term_slugs = ! empty( $tax_terms ) && is_array( $tax_terms ) ? wp_list_pluck( $tax_terms, 'slug' ) : [];

				return ! empty( $term_slugs ) ? $term_slugs : null;
			},
			'isRestricted'  => function () {
				return true === $this->is_restricted ? true : false;
			},
			'commentCount'  => function () {
				return ! empty( $this->post->comment_count ) ? absint( $this->post->comment_count ) : null;
			},
			'featuredImage' => function () {
				$thumbnail_id = get_post_thumbnail_id( $this->post->ID );

				return ! empty( $thumbnail_id ) ? DataSource::resolve_post_object( $thumbnail_id, 'attachment' ) : null;
			},
		];

		if ( 'attachment' === $this->post->post_type ) {
			$post_fields['caption'] = function () {
				$caption = apply_filters( 'the_excerpt', apply_filters( 'get_the_excerpt', $this->post->post_excerpt, $this->post ) );

				return ! empty( $caption ) ? $caption : null;
			};

			$post_fields['altText'] = function () {
				return get_post_meta( $this->post->ID, '_wp_attachment_image_alt', true );
			};

			$post_fields['description'] = function () {
				return ! empty( $this->post->post_content ) ? apply_filters( 'the_content', $this->post->post_content ) : null;
			};

			$post_fields['mediaType'] = function () {
				return wp_attachment_is_image( $this->post->ID ) ? 'image' : 'file';
			};

			$post_fields['sourceUrl'] = function () {
				return wp_get_attachment_url( $this->post->ID );
			};

			$post_fields['mimeType'] = function () {
				return ! empty( $this->post->post_mime_type ) ? $this->post->post_mime_type : null;
			};

			$post_fields['mediaDetails'] = function () {
				$media_details = wp_get_attachment_metadata( $this->post->ID );

				if ( ! empty( $media_details ) ) {
					$media_details['ID'] = $this->post->ID;

					return $media_details;
				}

				return null;
			};

		}

		if ( isset( $this->post_type_object ) && isset( $this->post_type_object->graphql_single_name ) ) {
			$type_id = $this->post_type_object->graphql_single_name . 'Id';
			$post_fields[ $type_id ] = absint( $this->post->ID );
		};

		/**
		 * If the post is restricted, filter out all fields other than those allowed
		 */
		if ( $this->is_restricted ) {
			$allowed_fields = apply_filters( 'graphql_restricted_post_object_allowed_fields', [
				'id',
				'title',
				'slug',
				'post_type',
				'status',
				'post_status',
				'isRestricted'
			] );

			$post_fields = array_intersect_key( $post_fields, array_flip( $allowed_fields ) );

		}

		/**
		 * Filter the $post_fields.
		 *
		 * This filter can be used to modify what data is returned by the PostObject model.
		 *
		 * For example, you could add new fields to return, override fields to return in a different way, etc.
		 *
		 * It's important that fields field
		 */
		$post_fields = apply_filters( 'graphql_post_object_fields', $post_fields, $this );

		/**
		 * Use the $post_fields to prepare the PostObject
		 */
		return $this->get_prepared_post( $post_fields );
	}

	/**
	 * For Backward compatibility sake we need to return an instance of a \WP_Post here, so
	 * we create a new instance, then set the fields based on the prepared post_data
	 *
	 * @param array $post_data The Post Data
	 *
	 * @return \WP_Post
	 */
	protected function get_prepared_post( $post_data ) {

		/**
		 * Create a new object
		 */
		$object = new \stdClass();

		/**
		 * Create a new \WP_Post object
		 */
		$post_object = new \WP_Post( $object );

		/**
		 * Apply the post_data to the \WP_Post
		 */
		if ( ! empty( $post_data ) && is_array( $post_data ) ) {
			foreach ( $post_data as $key => $value ) {
				$post_object->{$key} = $value;
			}
		}

		/**
		 * Return the prepared $post_object
		 */
		return $post_object;

	}

}
<?php

namespace WPGraphQL\Model;


use GraphQLRelay\Relay;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Types;

class Post extends Model {

	protected $post;

	protected $post_type_object;

	public $fields = [];

	public function __construct( \WP_Post $post, $filter = null ) {

		if ( empty( $post ) ) {
			throw new \Exception( __( 'An empty WP_Post object was used to initialize this object', 'wp-graphql' ) );
		}

		$this->post = $post;
		$this->post_type_object = isset( $post->post_type ) ? get_post_type_object( $post->post_type ) : null;

		$allowed_restricted_fields = [
			'id',
			'titleRendered',
			'slug',
			'post_type',
			'status',
			'post_status',
			'isRestricted',
			'isPrivate',
			'isPublic',
		];

		$allowed_restricted_fields[] = $post->post_type . 'Id';

		$restricted_cap = $this->get_restricted_cap();

		add_filter( 'graphql_data_is_private', [ $this, 'is_private' ], 1, 3 );

		parent::__construct( 'PostObject', $post, $restricted_cap, $allowed_restricted_fields, $post->post_author );

		$this->init( $filter );

	}

	protected function get_restricted_cap() {

		if ( ! empty( $this->post->post_password ) ) {
			return $this->post_type_object->cap->edit_others_posts;
		}

		switch ( $this->post->post_status ) {
			case 'trash':
				$cap = $this->post_type_object->cap->edit_posts;
				break;
			case 'draft':
				$cap = $this->post_type_object->cap->edit_others_posts;
				break;
			default:
				$cap = '';
				break;
		}

		return $cap;

	}

	public function is_private( $private, $model_name, $data ) {

		if ( 'PostObject' !== $model_name ) {
			return $private;
		}

		if ( ( true === $this->owner_matches_current_user() || 'publish' === $data->post_status ) && 'revision' !== $data->post_type ) {
			return false;
		}

		/**
		 * If the post_type isn't (not registered) or is not allowed in WPGraphQL,
		 * mark the post as private
		 */
		if ( empty( $this->post_type_object ) || empty( $this->post_type_object->name ) || ! in_array( $this->post_type_object->name, \WPGraphQL::$allowed_post_types, true ) ) {
			return true;
		}

		if ( 'private' === $data->post_status && ! current_user_can( $this->post_type_object->cap->read_private_posts ) ) {
			return true;
		}

		if ( 'revision' === $data->post_type ) {
			$parent               = get_post( (int) $data->post_parent );
			$parent_post_type_obj = get_post_type_object( $parent->post_type );
			if ( ! current_user_can( $parent_post_type_obj->cap->edit_post, $parent->ID ) ) {
				return true;
			}
		}

		return $private;

	}

	public function init( $filter = null ) {

		if ( 'private' === parent::get_visibility() ) {
			return null;
		}

		if ( empty( $this->fields ) ) {
			$this->fields = [
				'ID' => function() {
					return $this->post->ID;
				},
				'post_author'   => function() {
					return ! empty( $this->post->post_author ) ? $this->post->post_author : null;
				},
				'id'            => function () {
					return ( ! empty( $this->post->post_type ) && ! empty( $this->post->ID ) ) ? Relay::toGlobalId( $this->post->post_type, $this->post->ID ) : null;
				},
				'post_type'     => function() {
					return isset( $this->post->post_type ) ? $this->post->post_type : null;
				},
				'ancestors'     => function () {
					$ancestor_ids = get_ancestors( $this->post->ID, $this->post->post_type );
					return ( ! empty( $ancestor_ids ) ) ? $ancestor_ids : null;
				},
				'author'        => function () {
					$id     = $this->post->post_author;
					$author = isset( $id ) ? DataSource::resolve_user( absint( $this->post->post_author ) ) : null;
					return $author;
				},
				'date'          => function () {
					return ! empty( $this->post->post_date ) && '0000-00-00 00:00:00' !== $this->post->post_date ? $this->post->post_date : null;
				},
				'dateGmt'       => function () {
					return ! empty( $this->post->post_date_gmt ) ? Types::prepare_date_response( $this->post->post_date_gmt ) : null;
				},
				'contentRendered' => function() {
					$content = ! empty( $this->post->post_content ) ? $this->post->post_content : null;
					return ! empty( $content ) ? apply_filters( 'the_content', $content ) : null;
				},
				'contentRaw' => function() {
					return ! empty( $this->post->post_content ) ? $this->post->post_content : null;
				},
				'titleRendered' => function() {
					$id    = ! empty( $this->post->ID ) ? $this->post->ID : null;
					$title = ! empty( $this->post->post_title ) ? $this->post->post_title : null;
					return apply_filters( 'the_title', $title, $id );
				},
				'titleRaw' => function() {
					return ! empty( $this->post->post_title ) ? $this->post->post_title : null;
				},
				'excerptRendered' => function() {
					$excerpt = ! empty( $this->post->post_excerpt ) ? $this->post->post_excerpt : null;
					$excerpt = apply_filters( 'get_the_excerpt', $excerpt, $this->post );
					return apply_filters( 'the_excerpt', $excerpt );
				},
				'excerptRaw' => function() {
					return ! empty( $this->post->post_excerpt ) ? $this->post->post_excerpt : null;
				},
				'post_status'   => function() {
					return ! empty( $this->post->post_status ) ? $this->post->post_status : null;
				},
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
				'commentCount'  => function () {
					return ! empty( $this->post->comment_count ) ? absint( $this->post->comment_count ) : null;
				},
				'featuredImage' => function () {
					$thumbnail_id = get_post_thumbnail_id( $this->post->ID );
					return ! empty( $thumbnail_id ) ? DataSource::resolve_post_object( $thumbnail_id, 'attachment' ) : null;
				},
			];

			if ( 'attachment' === $this->post->post_type ) {
				$attachment_fields = [
					'caption' => function() {
						$caption = apply_filters( 'the_excerpt', apply_filters( 'get_the_excerpt', $this->post->post_excerpt, $this->post ) );
						return ! empty( $caption ) ? $caption : null;
					},
					'altText' => function() {
						return get_post_meta( $this->post->ID, '_wp_attachment_image_alt', true );
					},
					'description' => function() {
						return ! empty( $this->post->post_content ) ? apply_filters( 'the_content', $this->post->post_content ) : null;
					},
					'mediaType' => function() {
						return wp_attachment_is_image( $this->post->ID ) ? 'image' : 'file';
					},
					'sourceUrl' => function() {
						return wp_get_attachment_url( $this->post->ID );
					},
					'mimeType' => function() {
						return ! empty( $this->post->post_mime_type ) ? $this->post->post_mime_type : null;
					},
					'mediaDetails' => function() {
						$media_details = wp_get_attachment_metadata( $this->post->ID );
						if ( ! empty( $media_details ) ) {
							$media_details['ID'] = $this->post->ID;
							return $media_details;
						}
						return null;
					}
				];

				$this->fields = array_merge( $this->fields, $attachment_fields );
			}

			/**
			 * Set the {post_type}Id field to the Model.
			 */
			if ( isset( $this->post_type_object ) && isset( $this->post_type_object->graphql_single_name ) ) {
				$type_id                 = $this->post_type_object->graphql_single_name . 'Id';
				$this->fields[ $type_id ] = absint( $this->post->ID );
			};

		}

		$this->fields = parent::prepare_fields( $this->fields, $filter );

	}

}

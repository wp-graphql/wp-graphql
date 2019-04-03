<?php

namespace WPGraphQL\Model;


use GraphQLRelay\Relay;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Types;

/**
 * Class Post - Models data for the Post object type
 *
 * @property int    $ID
 * @property string $post_author
 * @property string $id
 * @property string $post_type
 * @property string $authorId
 * @property string $date
 * @property string $dateGmt
 * @property string $contentRendered
 * @property string $contentRaw
 * @property string $titleRendered
 * @property string $titleRaw
 * @property string $excerptRendered
 * @property string $excerptRaw
 * @property string $post_status
 * @property string $status
 * @property string $commentStatus
 * @property string $pingStatus
 * @property string $slug
 * @property string $toPing
 * @property string $pinged
 * @property string $modified
 * @property string $modifiedGmt
 * @property int    $parentId
 * @property int    $editLastId
 * @property array  $editLock
 * @property string $enclosure
 * @property string $guid
 * @property int    $menuOrder
 * @property string $link
 * @property string $uri
 * @property int    $commentCount
 * @property int    $featuredImageId
 *
 * @property string $captionRaw
 * @property string $captionRendered
 * @property string $altText
 * @property string $descriptionRaw
 * @property string $descriptionRendered
 * @property string $mediaType
 * @property string $sourceUrl
 * @property string $mimeType
 * @property array  $mediaDetails
 *
 * @package WPGraphQL\Model
 */
class Post extends Model {

	/**
	 * Stores the incoming post data
	 *
	 * @var \WP_Post $post
	 * @access protected
	 */
	protected $post;

	/**
	 * Stores the incoming post type object for the post being modeled
	 *
	 * @var null|\WP_Post_Type $post_type_object
	 * @access protected
	 */
	protected $post_type_object;

	/**
	 * Post constructor.
	 *
	 * @param \WP_Post $post The incoming WP_Post object that needs modeling
	 *
	 * @access public
	 * @return void
	 * @throws \Exception
	 */
	public function __construct( \WP_Post $post ) {

		$this->post = $post;
		$this->post_type_object = isset( $post->post_type ) ? get_post_type_object( $post->post_type ) : null;

		/**
		 * Set the resolving post to the global $post. That way any filters that
		 * might be applied when resolving fields can rely on global post and
		 * post data being set up.
		 */
		$GLOBALS['post'] = $this->post;
		setup_postdata( $this->post );

		/**
		 * Mimic core functionality for templates, as seen here:
		 * https://github.com/WordPress/WordPress/blob/6fd8080e7ee7599b36d4528f72a8ced612130b8c/wp-includes/template-loader.php#L56
		 */
		if ( 'attachment' === $this->post->post_type ) {
			remove_filter( 'the_content', 'prepend_attachment' );
		}

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

		$allowed_restricted_fields[] = $this->post_type_object->graphql_single_name . 'Id';

		$restricted_cap = $this->get_restricted_cap();

		if ( ! has_filter( 'graphql_data_is_private', [ $this, 'is_private' ] ) ) {
			add_filter( 'graphql_data_is_private', [ $this, 'is_private' ], 1, 3 );
		}

		parent::__construct( $post, $restricted_cap, $allowed_restricted_fields, $post->post_author );
		$this->init();

	}

	/**
	 * Retrieve the cap to check if the data should be restricted for the post
	 *
	 * @access protected
	 * @return string
	 */
	protected function get_restricted_cap() {

		if ( ! empty( $this->post->post_password ) ) {
			return $this->post_type_object->cap->edit_others_posts;
		}

		switch ( $this->post->post_status ) {
			case 'trash':
				$cap = $this->post_type_object->cap->edit_posts;
				break;
			case 'draft':
			case 'future':
			case 'pending':
				$cap = $this->post_type_object->cap->edit_others_posts;
				break;
			default:
				$cap = '';
				break;
		}

		return $cap;

	}

	/**
	 * Callback for the graphql_data_is_private filter to determine if the post should be
	 * considered private
	 *
	 * @param bool   $private    True or False value if the data should be private
	 * @param string $model_name Name of the model for the data currently being modeled
	 * @param mixed  $data       The Data currently being modeled
	 *
	 * @access public
	 * @return bool
	 */
	public function is_private( $private, $model_name, $data ) {

		if ( $this->get_model_name() !== $model_name ) {
			return $private;
		}

		/**
		 * Media Items (attachments) are all public. Once uploaded to the media library
		 * they are exposed with a public URL on the site.
		 *
		 * The WP REST API sets media items to private if they don't have a `post_parent` set, but
		 * this has broken production apps, because media items can be uploaded directly to the
		 * media library and published as a featured image, published inline within content, or
		 * within a Gutenberg block, etc, but then a consumer tries to ask for data of a published
		 * image and REST returns nothing because the media item is treated as private.
		 *
		 * Currently, we're treating all media items as public because there's nothing explicit in
		 * how WP Core handles privacy of media library items. By default they're publicly exposed.
		 */
		if ( 'attachment' === $data->post_type ) {
			return false;
		}

		/**
		 * Published content is public, not private
		 */
		if ( 'publish' === $data->post_status ) {
			return false;
		}

		/**
		 * If the status is NOT publish and the user does NOT have capabilities to edit posts,
		 * consider the post private.
		 */
		if ( ! current_user_can( $this->post_type_object->cap->edit_posts ) ) {
			return true;
		}

		/**
		 * If the owner of the content is the current user
		 */
		if ( ( true === $this->owner_matches_current_user() ) && 'revision' !== $data->post_type ) {
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

		if ( 'revision' === $data->post_type || 'auto-draft' === $data->post_status ) {
			$parent               = get_post( (int) $data->post_parent );
			$parent_post_type_obj = get_post_type_object( $parent->post_type );

			if ( 'private' === $parent->post_status ) {
				$cap = $parent_post_type_obj->cap->read_private_posts;
			} else {
				$cap = $parent_post_type_obj->cap->edit_post;
			}

			if ( ! current_user_can( $cap, $parent->ID ) ) {
				return true;
			}
		}

		return $private;

	}

	/**
	 * Initialize the Post object
	 *
	 * @access public
	 * @return void
	 */
	public function init() {

		if ( 'private' === parent::get_visibility() ) {
			return null;
		}

		if ( empty( $this->fields ) ) {

			$this->fields[ $this->post_type_object->graphql_single_name . 'Id' ] = function() {
				return absint( $this->post->ID );
			};

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
				'authorId'        => function () {
					return isset( $this->post->post_author ) ? $this->post->post_author : null;
				},
				'date'          => function () {
					return ! empty( $this->post->post_date ) && '0000-00-00 00:00:00' !== $this->post->post_date ? $this->post->post_date : null;
				},
				'dateGmt'       => function () {
					return ! empty( $this->post->post_date_gmt ) ? Types::prepare_date_response( $this->post->post_date_gmt ) : null;
				},
				'contentRendered' => function() {
					setup_postdata( $this->post );
					$content = ! empty( $this->post->post_content ) ? $this->post->post_content : null;
					return ! empty( $content ) ? apply_filters( 'the_content', $content ) : null;
				},
				'contentRaw' => [
					'callback' => function() {
						return ! empty( $this->post->post_content ) ? $this->post->post_content : null;
					},
					'capability' => $this->post_type_object->cap->edit_posts
				],
				'titleRendered' => function() {
					setup_postdata( $this->post );
					$id    = ! empty( $this->post->ID ) ? $this->post->ID : null;
					$title = ! empty( $this->post->post_title ) ? $this->post->post_title : null;
					return apply_filters( 'the_title', $title, $id );
				},
				'titleRaw' => [
					'callback' => function() {
						return ! empty( $this->post->post_title ) ? $this->post->post_title : null;
					},
					'capability' => $this->post_type_object->cap->edit_posts,
				],
				'excerptRendered' => function() {
					setup_postdata( $this->post );
					$excerpt = ! empty( $this->post->post_excerpt ) ? $this->post->post_excerpt : null;
					$excerpt = apply_filters( 'get_the_excerpt', $excerpt, $this->post );
					return apply_filters( 'the_excerpt', $excerpt );
				},
				'excerptRaw' => [
					'callback' => function() {
						return ! empty( $this->post->post_excerpt ) ? $this->post->post_excerpt : null;
					},
					'capability' => $this->post_type_object->cap->edit_posts,
				],
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
				'parentId'        => function () {
					return ! empty( $this->post->post_parent ) ? absint( $this->post->post_parent ) : null;
				},
				'editLastId'      => function () {
					$edit_last = get_post_meta( $this->post->ID, '_edit_last', true );
					return ! empty( $edit_last ) ? absint( $edit_last ) : null;
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
				'featuredImageId' => function () {
					$thumbnail_id = get_post_thumbnail_id( $this->post->ID );
					return ! empty( $thumbnail_id ) ? absint( $thumbnail_id ) : null;
				},
				'password' => [
					'callback' => function() {
						return ! empty( $this->post->post_password ) ? $this->post->post_password : null;
					},
					'capability' => $this->post_type_object->cap->edit_others_posts,
				]
			];

			if ( 'attachment' === $this->post->post_type ) {
				$attachment_fields = [
					'captionRendered' => function() {
						setup_postdata( $this->post );
						$caption = apply_filters( 'the_excerpt', apply_filters( 'get_the_excerpt', $this->post->post_excerpt, $this->post ) );
						return ! empty( $caption ) ? $caption : null;
					},
					'captionRaw' => [
						'callback' => function() {
							return ! empty( $this->post->post_excerpt ) ? $this->post->post_excerpt : null;
						},
						'capability' => $this->post_type_object->cap->edit_posts,
					],
					'altText' => function() {
						return get_post_meta( $this->post->ID, '_wp_attachment_image_alt', true );
					},
					'descriptionRendered' => function() {
						setup_postdata( $this->post );
						return ! empty( $this->post->post_content ) ? apply_filters( 'the_content', $this->post->post_content ) : null;
					},
					'descriptionRaw' => [
						'callback' => function() {
							return ! empty( $this->post->post_content ) ? $this->post->post_content : null;
						},
						'capability' => $this->post_type_object->cap->edit_posts,
					],
					'mediaType' => function() {
						return wp_attachment_is_image( $this->post->ID ) ? 'image' : 'file';
					},
					'sourceUrl' => function( $size = 'full' ) {
						if ( ! empty( $size ) ) {
							$image_src = wp_get_attachment_image_src( $this->post->ID, $size );

							if ( ! empty( $image_src ) ) {
								return $image_src[0];
							}
						}

						return wp_get_attachment_image_src( $this->post->ID, $size );
					},
					'sourceUrlsBySize' => function() {
						$sizes = get_intermediate_image_sizes();
						$urls = [];
						if ( ! empty( $sizes ) && is_array( $sizes ) ) {
							foreach( $sizes as $size ) {
								$urls[ $size ] = wp_get_attachment_image_src( $this->post->ID, $size )[0];
							}
						}
						return $urls;
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
				$this->fields[ $type_id ] = function() {
					return absint( $this->post->ID );
				};
			};

			parent::prepare_fields();

		}

	}

}

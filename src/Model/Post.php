<?php
/**
 * Model - PostObject
 *
 * @package WPGraphQL\Model
 */

namespace WPGraphQL\Model;

use GraphQLRelay\Relay;
use WPGraphQL\Types;

/**
 * Class Post - Models data for the Post object type
 *
 * @property int     $ID
 * @property string  $post_author
 * @property string  $id
 * @property string  $post_type
 * @property string  $authorId
 * @property string  $date
 * @property string  $dateGmt
 * @property string  $contentRendered
 * @property string  $contentRaw
 * @property string  $titleRendered
 * @property string  $titleRaw
 * @property string  $excerptRendered
 * @property string  $excerptRaw
 * @property string  $post_status
 * @property string  $status
 * @property string  $commentStatus
 * @property string  $pingStatus
 * @property string  $slug
 * @property boolean $isFrontPage
 * @property string  $toPing
 * @property string  $pinged
 * @property string  $modified
 * @property string  $modifiedGmt
 * @property int     $parentId
 * @property int     $editLastId
 * @property array   $editLock
 * @property string  $enclosure
 * @property string  $guid
 * @property int     $menuOrder
 * @property string  $link
 * @property string  $uri
 * @property int     $commentCount
 * @property int     $featuredImageId
 * @property string  $pageTemplate
 *
 * @property string  $captionRaw
 * @property string  $captionRendered
 * @property string  $altText
 * @property string  $descriptionRaw
 * @property string  $descriptionRendered
 * @property string  $mediaType
 * @property string  $sourceUrl
 * @property string  $mimeType
 * @property array   $mediaDetails
 *
 * @package WPGraphQL\Model
 */
class Post extends Model {

	/**
	 * Stores the incoming post data
	 *
	 * @var \WP_Post $data
	 * @access protected
	 */
	protected $data;

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
	 * @param \WP_Post $post The incoming WP_Post object that needs modeling.
	 *
	 * @access public
	 * @throws \Exception
	 * @return void
	 */
	public function __construct( \WP_Post $post ) {

		/**
		 * Set the data as the Post object
		 */
		$this->data             = $post;
		$this->post_type_object = isset( $post->post_type ) ? get_post_type_object( $post->post_type ) : null;

		/**
		 * If the post type is 'revision', we need to get the post_type_object
		 * of the parent post type to determine capabilities from
		 */
		if ( 'revision' === $post->post_type && ! empty( $post->post_parent ) ) {
			$parent                 = get_post( absint( $post->post_parent ) );
			$this->post_type_object = get_post_type_object( $parent->post_type );
		}

		/**
		 * Mimic core functionality for templates, as seen here:
		 * https://github.com/WordPress/WordPress/blob/6fd8080e7ee7599b36d4528f72a8ced612130b8c/wp-includes/template-loader.php#L56
		 */
		if ( 'attachment' === $this->data->post_type ) {
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
		];

		$allowed_restricted_fields[] = $this->post_type_object->graphql_single_name . 'Id';

		$restricted_cap = $this->get_restricted_cap();

		parent::__construct( $restricted_cap, $allowed_restricted_fields, $post->post_author );

	}

	/**
	 * Setup the global data for the model to have proper context when resolving
	 */
	public function setup() {
		/**
		 * Set the resolving post to the global $post. That way any filters that
		 * might be applied when resolving fields can rely on global post and
		 * post data being set up.
		 */
		if ( $this->data ) {
			$GLOBALS['post'] = $this->data;
			setup_postdata( $this->data );
		}
	}

	/**
	 * Retrieve the cap to check if the data should be restricted for the post
	 *
	 * @access protected
	 * @return string
	 */
	protected function get_restricted_cap() {
		if ( ! empty( $this->data->post_password ) ) {
			return $this->post_type_object->cap->edit_others_posts;
		}

		switch ( $this->data->post_status ) {
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
	 * Determine if the model is private
	 *
	 * @return bool
	 */
	protected function is_private() {

		/**
		 * If the post is of post_type "revision", we need to access the parent of the Post
		 * so that we can check access rights of the parent post. Revision access is inherit
		 * to the Parent it is a revision of.
		 */
		if ( 'revision' === $this->data->post_type ) {

			// Get the post
			$parent_post = get_post( $this->data->post_parent );

			// If the parent post doesn't exist, the revision should be considered private
			if ( ! $parent_post instanceof \WP_Post ) {
				return true;
			}

			// Determine if the revision is private using capabilities relative to the parent
			return $this->is_post_private( $parent_post );

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
		if ( 'attachment' === $this->data->post_type ) {
			return false;
		}

		/**
		 * Published content is public, not private
		 */
		if ( 'publish' === $this->data->post_status ) {
			return false;
		}

		return $this->is_post_private( $this->data );
	}

	/**
	 * Method for determining if the data should be considered private or not
	 *
	 * @param \WP_Post $post_object The object of the post we need to verify permissions for
	 *
	 * @access protected
	 * @return bool
	 */
	protected function is_post_private( $post_object = null ) {

		$post_type_object = $this->post_type_object;

		if ( empty( $post_object ) ) {
			$post_object = $this->data;
		}

		if ( empty( $post_object ) ) {
			return true;
		}

		/**
		 * If the status is NOT publish and the user does NOT have capabilities to edit posts,
		 * consider the post private.
		 */
		if ( ! current_user_can( $post_type_object->cap->edit_posts ) ) {
			return true;
		}

		/**
		 * If the owner of the content is the current user
		 */
		if ( ( true === $this->owner_matches_current_user() ) && 'revision' !== $post_object->post_type ) {
			return false;
		}

		/**
		 * If the post_type isn't (not registered) or is not allowed in WPGraphQL,
		 * mark the post as private
		 */

		if ( empty( $post_type_object ) || empty( $post_type_object->name ) || ! in_array( $post_type_object->name, \WPGraphQL::get_allowed_post_types(), true ) ) {
			return true;
		}

		if ( 'private' === $this->data->post_status && ! current_user_can( $post_type_object->cap->read_private_posts ) ) {
			return true;
		}

		if ( 'revision' === $this->data->post_type || 'auto-draft' === $this->data->post_status ) {
			$parent               = get_post( (int) $this->data->post_parent );
			$parent_post_type_obj = $post_type_object;

			if ( 'private' === $parent->post_status ) {
				$cap = $parent_post_type_obj->cap->read_private_posts;
			} else {
				$cap = $parent_post_type_obj->cap->edit_post;
			}

			if ( ! current_user_can( $cap, $parent->ID ) ) {
				return true;
			}
		}

		return false;

	}

	/**
	 * Initialize the Post object
	 *
	 * @access protected
	 * @return void
	 */
	protected function init() {

		if ( empty( $this->fields ) ) {

			$this->fields[ $this->post_type_object->graphql_single_name . 'Id' ] = function() {
				return absint( $this->data->ID );
			};

			$this->fields = [
				'ID'              => function() {
					return $this->data->ID;
				},
				'post_author'     => function() {
					return ! empty( $this->data->post_author ) ? $this->data->post_author : null;
				},
				'id'              => function() {
					return ( ! empty( $this->data->post_type ) && ! empty( $this->data->ID ) ) ? Relay::toGlobalId( $this->data->post_type, $this->data->ID ) : null;
				},
				'post_type'       => function() {
					return isset( $this->data->post_type ) ? $this->data->post_type : null;
				},
				'authorId'        => function() {
					return isset( $this->data->post_author ) ? $this->data->post_author : null;
				},
				'date'            => function() {
					return ! empty( $this->data->post_date ) && '0000-00-00 00:00:00' !== $this->data->post_date ? Types::prepare_date_response( null, $this->data->post_date ) : null;
				},
				'dateGmt'         => function() {
					return ! empty( $this->data->post_date_gmt ) ? Types::prepare_date_response( $this->data->post_date_gmt ) : null;
				},
				'contentRendered' => function() {
					setup_postdata( $this->data );
					$content = ! empty( $this->data->post_content ) ? $this->data->post_content : null;

					return ! empty( $content ) ? apply_filters( 'the_content', $content ) : null;
				},
				'pageTemplate'    => function() {
					$slug = get_page_template_slug( $this->data->ID );

					return ! empty( $slug ) ? $slug : null;
				},
				'contentRaw'      => [
					'callback'   => function() {
						return ! empty( $this->data->post_content ) ? $this->data->post_content : null;
					},
					'capability' => $this->post_type_object->cap->edit_posts,
				],
				'titleRendered'   => function() {
					setup_postdata( $this->data );
					$id    = ! empty( $this->data->ID ) ? $this->data->ID : null;
					$title = ! empty( $this->data->post_title ) ? $this->data->post_title : null;

					return apply_filters( 'the_title', $title, $id );
				},
				'titleRaw'        => [
					'callback'   => function() {
						return ! empty( $this->data->post_title ) ? $this->data->post_title : null;
					},
					'capability' => $this->post_type_object->cap->edit_posts,
				],
				'excerptRendered' => function() {
					setup_postdata( $this->data );
					$excerpt = ! empty( $this->data->post_excerpt ) ? $this->data->post_excerpt : null;
					$excerpt = apply_filters( 'get_the_excerpt', $excerpt, $this->data );

					return apply_filters( 'the_excerpt', $excerpt );
				},
				'excerptRaw'      => [
					'callback'   => function() {
						return ! empty( $this->data->post_excerpt ) ? $this->data->post_excerpt : null;
					},
					'capability' => $this->post_type_object->cap->edit_posts,
				],
				'post_status'     => function() {
					return ! empty( $this->data->post_status ) ? $this->data->post_status : null;
				},
				'status'          => function() {
					return ! empty( $this->data->post_status ) ? $this->data->post_status : null;
				},
				'commentStatus'   => function() {
					return ! empty( $this->data->comment_status ) ? $this->data->comment_status : null;
				},
				'pingStatus'      => function() {
					return ! empty( $this->data->ping_status ) ? $this->data->ping_status : null;
				},
				'slug'            => function() {
					return ! empty( $this->data->post_name ) ? $this->data->post_name : null;
				},
				'isFrontPage'     => function() {
					if ( 'page' !== $this->data->post_type || 'page' !== get_option( 'show_on_front' ) ) {
						return false;
					}
					if ( absint( get_option( 'page_on_front', 0 ) ) === $this->data->ID ) {
						return true;
					}

					return false;
				},
				'toPing'          => function() {
					return ! empty( $this->data->to_ping ) && is_array( $this->data->to_ping ) ? implode( ',', (array) $this->data->to_ping ) : null;
				},
				'pinged'          => function() {
					return ! empty( $this->data->pinged ) && is_array( $this->data->pinged ) ? implode( ',', (array) $this->data->pinged ) : null;
				},
				'modified'        => function() {
					return ! empty( $this->data->post_modified ) && '0000-00-00 00:00:00' !== $this->data->post_modified ? $this->data->post_modified : null;
				},
				'modifiedGmt'     => function() {
					return ! empty( $this->data->post_modified_gmt ) ? Types::prepare_date_response( $this->data->post_modified_gmt ) : null;
				},
				'parentId'        => function() {
					return ! empty( $this->data->post_parent ) ? absint( $this->data->post_parent ) : null;
				},
				'editLastId'      => function() {
					$edit_last = get_post_meta( $this->data->ID, '_edit_last', true );

					return ! empty( $edit_last ) ? absint( $edit_last ) : null;
				},
				'editLock'        => function() {
					$edit_lock       = get_post_meta( $this->data->ID, '_edit_lock', true );
					$edit_lock_parts = explode( ':', $edit_lock );

					return ! empty( $edit_lock_parts ) ? $edit_lock_parts : null;
				},
				'enclosure'       => function() {
					$enclosure = get_post_meta( $this->data->ID, 'enclosure', true );

					return ! empty( $enclosure ) ? $enclosure : null;
				},
				'guid'            => function() {
					return ! empty( $this->data->guid ) ? $this->data->guid : null;
				},
				'menuOrder'       => function() {
					return ! empty( $this->data->menu_order ) ? absint( $this->data->menu_order ) : null;
				},
				'link'            => function() {
					$link = get_permalink( $this->data->ID );

					return ! empty( $link ) ? $link : null;
				},
				'uri'             => function() {
					$uri = get_permalink( $this->data->ID );

					if ( true === $this->isFrontPage ) {
						return '/';
					}

					return ! empty( $uri ) ? ltrim( str_ireplace( home_url(), '', $uri ), '/' ) : null;
				},
				'commentCount'    => function() {
					return ! empty( $this->data->comment_count ) ? absint( $this->data->comment_count ) : null;
				},
				'featuredImageId' => function() {
					$thumbnail_id = get_post_thumbnail_id( $this->data->ID );

					return ! empty( $thumbnail_id ) ? absint( $thumbnail_id ) : null;
				},
				'password'        => [
					'callback'   => function() {
						return ! empty( $this->data->post_password ) ? $this->data->post_password : null;
					},
					'capability' => $this->post_type_object->cap->edit_others_posts,
				],
			];

			if ( 'attachment' === $this->data->post_type ) {
				$attachment_fields = [
					'captionRendered'     => function() {
						setup_postdata( $this->data );
						$caption = apply_filters( 'the_excerpt', apply_filters( 'get_the_excerpt', $this->data->post_excerpt, $this->data ) );

						return ! empty( $caption ) ? $caption : null;
					},
					'captionRaw'          => [
						'callback'   => function() {
							return ! empty( $this->data->post_excerpt ) ? $this->data->post_excerpt : null;
						},
						'capability' => $this->post_type_object->cap->edit_posts,
					],
					'altText'             => function() {
						return get_post_meta( $this->data->ID, '_wp_attachment_image_alt', true );
					},
					'descriptionRendered' => function() {
						setup_postdata( $this->data );

						return ! empty( $this->data->post_content ) ? apply_filters( 'the_content', $this->data->post_content ) : null;
					},
					'descriptionRaw'      => [
						'callback'   => function() {
							return ! empty( $this->data->post_content ) ? $this->data->post_content : null;
						},
						'capability' => $this->post_type_object->cap->edit_posts,
					],
					'mediaType'           => function() {
						return wp_attachment_is_image( $this->data->ID ) ? 'image' : 'file';
					},
					'mediaItemUrl'        => function() {
						return wp_get_attachment_url( $this->data->ID );
					},
					'sourceUrl'           => function() {
						$source_url = wp_get_attachment_image_src( $this->data->ID, 'full' );

						return isset( $source_url[0] ) ? $source_url[0] : null;
					},
					'sourceUrlsBySize'    => function() {
						$sizes = get_intermediate_image_sizes();
						$urls  = [];
						if ( ! empty( $sizes ) && is_array( $sizes ) ) {
							foreach ( $sizes as $size ) {
								$urls[ $size ] = wp_get_attachment_image_src( $this->data->ID, $size )[0];
							}
						}

						return $urls;
					},
					'mimeType'            => function() {
						return ! empty( $this->data->post_mime_type ) ? $this->data->post_mime_type : null;
					},
					'mediaDetails'        => function() {
						$media_details = wp_get_attachment_metadata( $this->data->ID );
						if ( ! empty( $media_details ) ) {
							$media_details['ID'] = $this->data->ID;

							return $media_details;
						}

						return null;
					},
				];

				$this->fields = array_merge( $this->fields, $attachment_fields );
			}

			/**
			 * Set the {post_type}Id field to the Model.
			 */
			if ( isset( $this->post_type_object ) && isset( $this->post_type_object->graphql_single_name ) ) {
				$type_id                  = $this->post_type_object->graphql_single_name . 'Id';
				$this->fields[ $type_id ] = function() {
					return absint( $this->data->ID );
				};
			};

		}

	}

}

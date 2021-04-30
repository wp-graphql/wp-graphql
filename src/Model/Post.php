<?php
/**
 * Model - PostObject
 *
 * @package WPGraphQL\Model
 */

namespace WPGraphQL\Model;

use Exception;
use GraphQLRelay\Relay;
use WP_Post;
use WP_Post_Type;
use WP_Query;
use WPGraphQL\Utils\Utils;

/**
 * Class Post - Models data for the Post object type
 *
 * @property int     $ID
 * @property string  $post_author
 * @property string  $id
 * @property string  $post_type
 * @property string  $authorId
 * @property string  $authorDatabaseId
 * @property int     $databaseId
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
 * @property array   $template
 * @property boolean $isFrontPage
 * @property boolean $isPrivacyPage
 * @property boolean $isPostsPage
 * @property boolean $isPreview
 * @property boolean $isRevision
 * @property boolean $isSticky
 * @property string  $toPing
 * @property string  $pinged
 * @property string  $modified
 * @property string  $modifiedGmt
 * @property string  $parentId
 * @property int     $parentDatabaseId
 * @property int     $editLastId
 * @property array   $editLock
 * @property string  $enclosure
 * @property string  $guid
 * @property int     $menuOrder
 * @property string  $link
 * @property string  $uri
 * @property int     $commentCount
 * @property string  $featuredImageId
 * @property int     $featuredImageDatabaseId
 * @property string  $pageTemplate
 * @property int     $previewRevisionDatabaseId
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
	 * @var WP_Post $data
	 */
	protected $data;

	/**
	 * Store the global post to reset during model tear down
	 *
	 * @var WP_Post
	 */
	protected $global_post;

	/**
	 * Stores the incoming post type object for the post being modeled
	 *
	 * @var null|WP_Post_Type $post_type_object
	 */
	protected $post_type_object;

	/**
	 * Store the instance of the WP_Query
	 *
	 * @var WP_Query
	 */
	protected $wp_query;

	/**
	 * Post constructor.
	 *
	 * @param WP_Post $post The incoming WP_Post object that needs modeling.
	 *
	 * @return void
	 * @throws Exception
	 */
	public function __construct( WP_Post $post ) {

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
			$parent = get_post( absint( $post->post_parent ) );
			if ( ! empty( $parent ) ) {
				$this->post_type_object = get_post_type_object( $parent->post_type );
			}
		}

		/**
		 * Mimic core functionality for templates, as seen here:
		 * https://github.com/WordPress/WordPress/blob/6fd8080e7ee7599b36d4528f72a8ced612130b8c/wp-includes/template-loader.php#L56
		 */
		if ( 'attachment' === $this->data->post_type ) {
			remove_filter( 'the_content', 'prepend_attachment' );
		}

		$allowed_restricted_fields = [
			'databaseId',
			'enqueuedScriptsQueue',
			'enqueuedStylesheetsQueue',
			'id',
			'isRestricted',
			'link',
			'post_status',
			'post_type',
			'slug',
			'status',
			'titleRendered',
			'uri',
			'isPostsPage',
			'isFrontPage',
			'isPrivacyPage',
		];

		if ( isset( $this->post_type_object->graphql_single_name ) ) {
			$allowed_restricted_fields[] = $this->post_type_object->graphql_single_name . 'Id';
		}

		$restricted_cap = $this->get_restricted_cap();

		parent::__construct( $restricted_cap, $allowed_restricted_fields, (int) $post->post_author );

	}

	/**
	 * Setup the global data for the model to have proper context when resolving
	 *
	 * @return void
	 */
	public function setup() {

		global $wp_query, $post;

		/**
		 * Store the global post before overriding
		 */
		$this->global_post = $post;

		/**
		 * Set the resolving post to the global $post. That way any filters that
		 * might be applied when resolving fields can rely on global post and
		 * post data being set up.
		 */
		if ( ! empty( $this->data ) ) {

			$id        = $this->data->ID;
			$post_type = $this->data->post_type;
			$post_name = $this->data->post_name;
			$data      = $this->data;

			if ( 'revision' === $this->data->post_type ) {
				$id     = $this->data->post_parent;
				$parent = get_post( $this->data->post_parent );
				if ( empty( $parent ) ) {
					$this->fields = [];
					return;
				}
				$post_type = $parent->post_type;
				$post_name = $parent->post_name;
				$data      = $parent;
			}

			/**
			 * Clear out existing postdata
			 */
			$wp_query->reset_postdata();

			/**
			 * Parse the query to tell WordPress how to
			 * setup global state
			 */
			if ( 'post' === $post_type ) {
				$wp_query->parse_query(
					[
						'page' => '',
						'p'    => $id,
					]
				);
			} elseif ( 'page' === $post_type ) {
				$wp_query->parse_query(
					[
						'page'     => '',
						'pagename' => $post_name,
					]
				);
			} elseif ( 'attachment' === $post_type ) {
				$wp_query->parse_query(
					[
						'attachment' => $post_name,
					]
				);
			} else {
				$wp_query->parse_query(
					[
						$post_type  => $post_name,
						'post_type' => $post_type,
						'name'      => $post_name,
					]
				);
			}

			$wp_query->setup_postdata( $data );
			$GLOBALS['post']             = $data;
			$wp_query->queried_object    = get_post( $this->data->ID );
			$wp_query->queried_object_id = $this->data->ID;

		}
	}

	/**
	 * Retrieve the cap to check if the data should be restricted for the post
	 *
	 * @return string
	 */
	protected function get_restricted_cap() {
		if ( ! empty( $this->data->post_password ) ) {
			return isset( $this->post_type_object->cap->edit_others_posts ) ? $this->post_type_object->cap->edit_others_posts : 'edit_others_posts';
		}

		switch ( $this->data->post_status ) {
			case 'trash':
				$cap = isset( $this->post_type_object->cap->edit_posts ) ? $this->post_type_object->cap->edit_posts : 'edit_posts';
				break;
			case 'draft':
			case 'future':
			case 'pending':
				$cap = isset( $this->post_type_object->cap->edit_others_posts ) ? $this->post_type_object->cap->edit_others_posts : 'edit_others_posts';
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
	public function is_private() {

		/**
		 * If the post is of post_type "revision", we need to access the parent of the Post
		 * so that we can check access rights of the parent post. Revision access is inherit
		 * to the Parent it is a revision of.
		 */
		if ( isset( $this->data->post_type ) && 'revision' === $this->data->post_type ) {

			// Get the post
			$parent_post = get_post( $this->data->post_parent );

			// If the parent post doesn't exist, the revision should be considered private
			if ( ! $parent_post instanceof WP_Post ) {
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
	 * @param WP_Post $post_object The object of the post we need to verify permissions for
	 *
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
		if ( ! isset( $post_type_object->cap->edit_posts ) || ! current_user_can( $post_type_object->cap->edit_posts ) ) {
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

		if ( 'private' === $this->data->post_status && ( ! isset( $post_type_object->cap->read_private_posts ) || ! current_user_can( $post_type_object->cap->read_private_posts ) ) ) {
			return true;
		}

		if ( 'revision' === $this->data->post_type || 'auto-draft' === $this->data->post_status ) {
			$parent = get_post( (int) $this->data->post_parent );

			if ( empty( $parent ) ) {
				return true;
			}

			$parent_post_type_obj = $post_type_object;

			if ( empty( $parent_post_type_obj ) ) {
				return true;
			}

			if ( 'private' === $parent->post_status ) {
				$cap = isset( $parent_post_type_obj->cap->read_private_posts ) ? $parent_post_type_obj->cap->read_private_posts : 'read_private_posts';
			} else {
				$cap = isset( $parent_post_type_obj->cap->edit_post ) ? $parent_post_type_obj->cap->edit_post : 'edit_post';
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
	 * @return void
	 */
	protected function init() {

		if ( empty( $this->fields ) ) {

			$this->fields = [
				'ID'                        => function() {
					return $this->data->ID;
				},
				'post_author'               => function() {
					if ( $this->isPreview ) {
						$parent_post = get_post( $this->parentDatabaseId );
						if ( empty( $parent_post ) ) {
							return null;
						}

						return (int) $parent_post->post_author;
					}

					return ! empty( $this->data->post_author ) ? $this->data->post_author : null;
				},
				'id'                        => function() {
					return ( ! empty( $this->data->post_type ) && ! empty( $this->databaseId ) ) ? Relay::toGlobalId( 'post', (string) $this->databaseId ) : null;
				},
				'databaseId'                => function() {
					return isset( $this->data->ID ) ? absint( $this->data->ID ) : null;
				},
				'post_type'                 => function() {
					return isset( $this->data->post_type ) ? $this->data->post_type : null;
				},
				'authorId'                  => function() {

					if ( true === $this->isPreview ) {
						$parent_post = get_post( $this->data->post_parent );
						if ( empty( $parent_post ) ) {
							return null;
						}
						$id = (int) $parent_post->post_author;

					} else {
						$id = isset( $this->data->post_author ) ? (int) $this->data->post_author : null;
					}

					return Relay::toGlobalId( 'user', (string) $id );
				},
				'authorDatabaseId'          => function() {
					if ( true === $this->isPreview ) {
						$parent_post = get_post( $this->data->post_parent );
						if ( empty( $parent_post ) ) {
							return null;
						}

						return $parent_post->post_author;
					}

					return isset( $this->data->post_author ) ? $this->data->post_author : null;

				},
				'date'                      => function() {
					return ! empty( $this->data->post_date ) && '0000-00-00 00:00:00' !== $this->data->post_date ? Utils::prepare_date_response( $this->data->post_date_gmt, $this->data->post_date ) : null;
				},
				'dateGmt'                   => function() {
					return ! empty( $this->data->post_date_gmt ) ? Utils::prepare_date_response( $this->data->post_date_gmt ) : null;
				},
				'contentRendered'           => function() {
					$content = ! empty( $this->data->post_content ) ? $this->data->post_content : null;

					return ! empty( $content ) ? $this->html_entity_decode( apply_filters( 'the_content', $content ), 'contentRendered', false ) : null;
				},
				'pageTemplate'              => function() {
					$slug = get_page_template_slug( $this->data->ID );

					return ! empty( $slug ) ? $slug : null;
				},
				'contentRaw'                => [
					'callback'   => function() {
						return ! empty( $this->data->post_content ) ? $this->data->post_content : null;
					},
					'capability' => isset( $this->post_type_object->cap->edit_posts ) ? $this->post_type_object->cap->edit_posts : 'edit_posts',
				],
				'titleRendered'             => function() {
					$id    = ! empty( $this->data->ID ) ? $this->data->ID : null;
					$title = ! empty( $this->data->post_title ) ? $this->data->post_title : null;

					return $this->html_entity_decode( apply_filters( 'the_title', $title, $id ), 'titleRendered', true );
				},
				'titleRaw'                  => [
					'callback'   => function() {
						return ! empty( $this->data->post_title ) ? $this->data->post_title : null;
					},
					'capability' => isset( $this->post_type_object->cap->edit_posts ) ? $this->post_type_object->cap->edit_posts : 'edit_posts',
				],
				'excerptRendered'           => function() {
					$excerpt = ! empty( $this->data->post_excerpt ) ? $this->data->post_excerpt : null;
					$excerpt = apply_filters( 'get_the_excerpt', $excerpt, $this->data );

					return $this->html_entity_decode( apply_filters( 'the_excerpt', $excerpt ), 'excerptRendered' );
				},
				'excerptRaw'                => [
					'callback'   => function() {
						return ! empty( $this->data->post_excerpt ) ? $this->data->post_excerpt : null;
					},
					'capability' => isset( $this->post_type_object->cap->edit_posts ) ? $this->post_type_object->cap->edit_posts : 'edit_posts',
				],
				'post_status'               => function() {
					return ! empty( $this->data->post_status ) ? $this->data->post_status : null;
				},
				'status'                    => function() {
					return ! empty( $this->data->post_status ) ? $this->data->post_status : null;
				},
				'commentStatus'             => function() {
					return ! empty( $this->data->comment_status ) ? $this->data->comment_status : null;
				},
				'pingStatus'                => function() {
					return ! empty( $this->data->ping_status ) ? $this->data->ping_status : null;
				},
				'slug'                      => function() {
					return ! empty( $this->data->post_name ) ? $this->data->post_name : null;
				},
				'template'                  => function() {

					$registered_templates = wp_get_theme()->get_post_templates();

					$template = [
						'__typename'   => 'DefaultTemplate',
						'templateName' => 'Default',
					];

					if ( true === $this->isPreview ) {

						$parent_post = get_post( $this->parentDatabaseId );

						if ( empty( $parent_post ) ) {
							return $template;
						}

						$post_type = $parent_post->post_type;

						if ( ! isset( $registered_templates[ $post_type ] ) ) {
							return $template;
						}
						$set_template  = get_post_meta( $this->parentDatabaseId, '_wp_page_template', true );
						$template_name = get_page_template_slug( $this->parentDatabaseId );

						if ( empty( $set_template ) ) {
							$set_template = get_post_meta( $this->data->ID, '_wp_page_template', true );
						}

						if ( empty( $template_name ) ) {
							$template_name = get_page_template_slug( $this->data->ID );
						}

						$template_name = ! empty( $template_name ) ? $template_name : 'Default';

					} else {
						if ( ! isset( $registered_templates[ $this->data->post_type ] ) ) {
							return $template;
						}
						$post_type     = $this->data->post_type;
						$set_template  = get_post_meta( $this->data->ID, '_wp_page_template', true );
						$template_name = get_page_template_slug( $this->data->ID );

						$template_name = ! empty( $template_name ) ? $template_name : 'Default';
					}

					if ( ! empty( $template_name ) && ! empty( $registered_templates[ $post_type ][ $set_template ] ) ) {
						$name          = ucwords( $registered_templates[ $post_type ][ $set_template ] );
						$replaced_name = preg_replace( '/[^\w]/', '', $name );

						if ( ! empty( $replaced_name ) ) {
							$name = $replaced_name;
						}
						if ( preg_match( '/^\d/', $name ) || false === strpos( strtolower( $name ), 'template' ) ) {
							$name = 'Template_' . $name;
						}

						$template = [
							'__typename'   => $name,
							'templateName' => ucwords( $registered_templates[ $post_type ][ $set_template ] ),
						];
					}

					return $template;
				},
				'isFrontPage'               => function() {
					if ( 'page' !== $this->data->post_type || 'page' !== get_option( 'show_on_front' ) ) {
						return false;
					}
					if ( absint( get_option( 'page_on_front', 0 ) ) === $this->data->ID ) {
						return true;
					}

					return false;
				},
				'isPrivacyPage'             => function() {
					if ( 'page' !== $this->data->post_type ) {
						return false;
					}
					if ( absint( get_option( 'wp_page_for_privacy_policy', 0 ) ) === $this->data->ID ) {
						return true;
					}

					return false;
				},
				'isPostsPage'               => function() {
					if ( 'page' !== $this->data->post_type ) {
						return false;
					}
					if ( 'posts' !== get_option( 'show_on_front', 'posts' ) && absint( get_option( 'page_for_posts', 0 ) ) === $this->data->ID ) {
						return true;
					}

					return false;
				},
				'toPing'                    => function() {
					$to_ping = get_to_ping( $this->databaseId );

					return ! empty( $to_ping ) ? implode( ',', (array) $to_ping ) : null;
				},
				'pinged'                    => function() {
					$punged = get_pung( $this->databaseId );

					return ! empty( $punged ) ? implode( ',', (array) $punged ) : null;
				},
				'modified'                  => function() {
					return ! empty( $this->data->post_modified ) && '0000-00-00 00:00:00' !== $this->data->post_modified ? Utils::prepare_date_response( $this->data->post_modified ) : null;
				},
				'modifiedGmt'               => function() {
					return ! empty( $this->data->post_modified_gmt ) ? Utils::prepare_date_response( $this->data->post_modified_gmt ) : null;
				},
				'parentId'                  => function() {
					return ( ! empty( $this->data->post_type ) && ! empty( $this->data->post_parent ) ) ? Relay::toGlobalId( 'post', (string) $this->data->post_parent ) : null;
				},
				'parentDatabaseId'          => function() {
					return ! empty( $this->data->post_parent ) ? absint( $this->data->post_parent ) : null;
				},
				'editLastId'                => function() {
					$edit_last = get_post_meta( $this->data->ID, '_edit_last', true );

					return ! empty( $edit_last ) ? absint( $edit_last ) : null;
				},
				'editLock'                  => function() {

					require_once ABSPATH . 'wp-admin/includes/post.php';
					if ( ! wp_check_post_lock( $this->data->ID ) ) {
						return null;
					}

					$edit_lock       = get_post_meta( $this->data->ID, '_edit_lock', true );
					$edit_lock_parts = explode( ':', $edit_lock );

					return ! empty( $edit_lock_parts ) ? $edit_lock_parts : null;
				},
				'enclosure'                 => function() {
					$enclosure = get_post_meta( $this->data->ID, 'enclosure', true );

					return ! empty( $enclosure ) ? $enclosure : null;
				},
				'guid'                      => function() {
					return ! empty( $this->data->guid ) ? $this->data->guid : null;
				},
				'menuOrder'                 => function() {
					return ! empty( $this->data->menu_order ) ? absint( $this->data->menu_order ) : null;
				},
				'link'                      => function() {
					$link = get_permalink( $this->data->ID );

					if ( $this->isPreview ) {
						$link = get_preview_post_link( $this->parentDatabaseId );
					} elseif ( $this->isRevision ) {
						$link = get_permalink( $this->data->ID );
					}

					return ! empty( $link ) ? $link : null;
				},
				'uri'                       => function() {
					$uri = $this->link;

					if ( true === $this->isFrontPage ) {
						return '/';
					}

					return ! empty( $uri ) ? str_ireplace( home_url(), '', $uri ) : null;
				},
				'commentCount'              => function() {
					return ! empty( $this->data->comment_count ) ? absint( $this->data->comment_count ) : null;
				},
				'featuredImageId'           => function() {
					return ! empty( $this->featuredImageDatabaseId ) ? Relay::toGlobalId( 'post', (string) $this->featuredImageDatabaseId ) : null;
				},
				'featuredImageDatabaseId'   => function() {

					if ( $this->isRevision ) {
						$id = $this->parentDatabaseId;
					} else {
						$id = $this->data->ID;
					}

					$thumbnail_id = get_post_thumbnail_id( $id );

					return ! empty( $thumbnail_id ) ? absint( $thumbnail_id ) : null;
				},
				'password'                  => [
					'callback'   => function() {
						return ! empty( $this->data->post_password ) ? $this->data->post_password : null;
					},
					'capability' => isset( $this->post_type_object->cap->edit_others_posts ) ?: 'edit_others_posts',
				],
				'enqueuedScriptsQueue'      => function() {
					global $wp_scripts;
					do_action( 'wp_enqueue_scripts' );
					$queue = $wp_scripts->queue;
					$wp_scripts->reset();
					$wp_scripts->queue = [];

					return $queue;
				},
				'enqueuedStylesheetsQueue'  => function() {
					global $wp_styles;
					do_action( 'wp_enqueue_scripts' );
					$queue = $wp_styles->queue;
					$wp_styles->reset();
					$wp_styles->queue = [];

					return $queue;
				},
				'isRevision'                => function() {
					return 'revision' === $this->data->post_type;
				},
				'previewRevisionDatabaseId' => [
					'callback'   => function() {
						$revisions = wp_get_post_revisions(
							$this->data->ID,
							[
								'posts_per_page' => 1,
								'fields'         => 'ids',
								'check_enabled'  => false,
							]
						);

						return is_array( $revisions ) && ! empty( $revisions ) ? array_values( $revisions )[0] : null;
					},
					'capability' => isset( $this->post_type_object->cap->edit_posts ) ? $this->post_type_object->cap->edit_posts : 'edit_posts',
				],
				'previewRevisionId'         => function() {
					return ! empty( $this->previewRevisionDatabaseId ) ? Relay::toGlobalId( 'post', (string) $this->previewRevisionDatabaseId ) : null;
				},
				'isPreview'                 => function() {
					if ( $this->isRevision ) {
						$revisions = wp_get_post_revisions(
							$this->parentDatabaseId,
							[
								'posts_per_page' => 1,
								'fields'         => 'ids',
								'check_enabled'  => false,
							]
						);

						if ( in_array( $this->data->ID, array_values( $revisions ), true ) ) {
							return true;
						}
					}

					return false;
				},
				'isSticky'                  => function() {
					return is_sticky( $this->databaseId );
				},
			];

			if ( 'attachment' === $this->data->post_type ) {
				$attachment_fields = [
					'captionRendered'     => function() {
						$caption = apply_filters( 'the_excerpt', apply_filters( 'get_the_excerpt', $this->data->post_excerpt, $this->data ) );

						return ! empty( $caption ) ? $caption : null;
					},
					'captionRaw'          => [
						'callback'   => function() {
							return ! empty( $this->data->post_excerpt ) ? $this->data->post_excerpt : null;
						},
						'capability' => isset( $this->post_type_object->cap->edit_posts ) ? $this->post_type_object->cap->edit_posts : 'edit_posts',
					],
					'altText'             => function() {
						return get_post_meta( $this->data->ID, '_wp_attachment_image_alt', true );
					},
					'descriptionRendered' => function() {
						return ! empty( $this->data->post_content ) ? apply_filters( 'the_content', $this->data->post_content ) : null;
					},
					'descriptionRaw'      => [
						'callback'   => function() {
							return ! empty( $this->data->post_content ) ? $this->data->post_content : null;
						},
						'capability' => isset( $this->post_type_object->cap->edit_posts ) ? $this->post_type_object->cap->edit_posts : 'edit_posts',
					],
					'mediaType'           => function() {
						return wp_attachment_is_image( $this->data->ID ) ? 'image' : 'file';
					},
					'mediaItemUrl'        => function() {
						return wp_get_attachment_url( $this->data->ID );
					},
					'sourceUrl'           => function() {
						$source_url = wp_get_attachment_image_src( $this->data->ID, 'full' );

						return ! empty( $source_url ) && isset( $source_url[0] ) ? $source_url[0] : null;
					},
					'sourceUrlsBySize'    => function() {
						$sizes = get_intermediate_image_sizes();
						$urls  = [];
						if ( ! empty( $sizes ) && is_array( $sizes ) ) {
							foreach ( $sizes as $size ) {
								$img_src       = wp_get_attachment_image_src( $this->data->ID, $size );
								$urls[ $size ] = ! empty( $img_src ) && isset( $img_src[0] ) ? $img_src[0] : null;
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

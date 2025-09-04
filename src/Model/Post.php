<?php
/**
 * Model - PostObject
 *
 * @package WPGraphQL\Model
 */

namespace WPGraphQL\Model;

use GraphQLRelay\Relay;
use WPGraphQL\Utils\Utils;
use WP_Post;

/**
 * Class Post - Models data for the Post object type
 *
 * @property ?int          $authorDatabaseId
 * @property ?string       $authorId
 * @property int           $commentCount
 * @property ?string       $commentStatus
 * @property ?string       $contentRaw
 * @property ?string       $contentRendered
 * @property ?int          $databaseId
 * @property ?string       $date
 * @property ?string       $dateGmt
 * @property ?int          $editLastId
 * @property string[]|null $editLock
 * @property ?string       $enclosure
 * @property ?string       $excerptRaw
 * @property ?string       $excerptRendered
 * @property ?int          $featuredImageDatabaseId
 * @property ?string       $featuredImageId
 * @property ?string       $guid
 * @property bool          $hasPassword
 * @property ?string       $id
 * @property bool          $isFrontPage
 * @property bool          $isPostsPage
 * @property bool          $isPreview
 * @property bool          $isPrivacyPage
 * @property bool          $isRevision
 * @property bool          $isSticky
 * @property ?string       $link
 * @property ?int          $menuOrder
 * @property ?string       $modified
 * @property ?string       $modifiedGmt
 * @property ?string       $pageTemplate
 * @property ?int          $parentDatabaseId
 * @property ?string       $parentId
 * @property ?string       $password
 * @property ?string       $pinged
 * @property ?string       $pingStatus
 * @property ?string       $post_type
 * @property int           $previewRevisionDatabaseId
 * @property ?string       $slug
 * @property ?string       $status
 * @property array{
 *  __typename: string,
 *  templateName: string
 * }                       $template
 * @property ?string       $titleRaw
 * @property ?string       $titleRendered
 * @property ?string       $toPing
 * @property ?string       $uri
 *
 * Attachment specific fields:
 * @property string|null              $altText
 * @property string|null              $captionRaw
 * @property string|null              $captionRendered
 * @property string|null              $descriptionRaw
 * @property string|null              $descriptionRendered
 * @property array<string,mixed>|null $mediaDetails
 * @property string|null              $mediaItemUrl
 * @property string|null              $mediaType
 * @property string|null              $mimeType
 * @property string|null              $sourceUrl
 *
 * Aliases:
 * @property ?int    $ID
 * @property ?int    $post_author
 * @property ?string $post_status
 *
 * @extends \WPGraphQL\Model\Model<\WP_Post>
 */
class Post extends Model {
	/**
	 * Store the global post to reset during model tear down
	 *
	 * @var \WP_Post
	 */
	protected $global_post;

	/**
	 * Stores the incoming post type object for the post being modeled
	 *
	 * @var \WP_Post_Type|null $post_type_object
	 */
	protected $post_type_object;

	/**
	 * Store the instance of the WP_Query
	 *
	 * @var \WP_Query
	 */
	protected $wp_query;

	/**
	 * Stores the resolved image `sourceUrl`s keyed by size.
	 *
	 * This is used to prevent multiple calls to `wp_get_attachment_image_src`.
	 *
	 * If no source URL is found for a size, the value will be `null`.
	 *
	 * @var array<string,?string>
	 */
	protected $source_urls_by_size = [];

	/**
	 * Post constructor.
	 *
	 * @param \WP_Post $post The incoming WP_Post object that needs modeling.
	 *
	 * @return void
	 */
	public function __construct( WP_Post $post ) {

		/**
		 * Set the data as the Post object
		 */
		$this->data             = $post;
		$this->post_type_object = get_post_type_object( $post->post_type );

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
			'hasPassword',
			'id',
			'isFrontPage',
			'isPostsPage',
			'isPrivacyPage',
			'isRestricted',
			'link',
			'post_status',
			'post_type',
			'slug',
			'status',
			'titleRendered',
			'uri',
		];

		if ( isset( $this->post_type_object->graphql_single_name ) ) {
			$allowed_restricted_fields[] = $this->post_type_object->graphql_single_name . 'Id';
		}

		$restricted_cap = $this->get_restricted_cap();

		parent::__construct( $restricted_cap, $allowed_restricted_fields, (int) $post->post_author );
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

		/**
		 * Set the resolving post to the global $post. That way any filters that
		 * might be applied when resolving fields can rely on global post and
		 * post data being set up.
		 */
		if ( $this->data instanceof WP_Post ) {
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
			$GLOBALS['post']             = $data; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
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
	 * {@inheritDoc}
	 */
	public function is_private() {

		/**
		 * If the post is of post_type "revision", we need to access the parent of the Post
		 * so that we can check access rights of the parent post. Revision access is inherit
		 * to the Parent it is a revision of.
		 */
		if ( 'revision' === $this->data->post_type ) {

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
		if ( 'publish' === $this->data->post_status && $this->post_type_object && ( true === $this->post_type_object->public || true === $this->post_type_object->publicly_queryable ) ) {
			return false;
		}

		return $this->is_post_private( $this->data );
	}

	/**
	 * Method for determining if the data should be considered private or not
	 *
	 * @param \WP_Post $post_object The object of the post we need to verify permissions for
	 *
	 * @return bool
	 */
	protected function is_post_private( $post_object = null ) {
		$post_type_object = $this->post_type_object;

		if ( ! $post_type_object ) {
			return true;
		}

		if ( ! $post_object ) {
			$post_object = $this->data;
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

		if ( empty( $post_type_object->name ) || ! in_array( $post_type_object->name, \WPGraphQL::get_allowed_post_types(), true ) ) {
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
	 * {@inheritDoc}
	 */
	protected function init() {
		if ( empty( $this->fields ) ) {
			$this->fields = [
				'authorDatabaseId'          => function () {
					if ( true === $this->isPreview ) {
						$parent_post = get_post( $this->data->post_parent );

						return ! empty( $parent_post->post_author ) ? (int) $parent_post->post_author : null;
					}

					return ! empty( $this->data->post_author ) ? (int) $this->data->post_author : null;
				},
				'authorId'                  => function () {
					return ! empty( $this->authorDatabaseId ) ? Relay::toGlobalId( 'user', (string) $this->authorDatabaseId ) : null;
				},
				'commentCount'              => function () {
					return ! empty( $this->data->comment_count ) ? absint( $this->data->comment_count ) : 0;
				},
				'commentStatus'             => function () {
					return ! empty( $this->data->comment_status ) ? $this->data->comment_status : null;
				},
				'contentRaw'                => [
					'callback'   => function () {
						return ! empty( $this->data->post_content ) ? $this->data->post_content : null;
					},
					'capability' => isset( $this->post_type_object->cap->edit_posts ) ? $this->post_type_object->cap->edit_posts : 'edit_posts',
				],
				'contentRendered'           => function () {
					$content = ! empty( $this->data->post_content ) ? $this->data->post_content : null;

					return ! empty( $content ) ? $this->html_entity_decode( apply_filters( 'the_content', $content ), 'contentRendered', false ) : null;
				},
				'databaseId'                => function () {
					return ! empty( $this->data->ID ) ? absint( $this->data->ID ) : null;
				},
				'date'                      => function () {
					return ! empty( $this->data->post_date ) && '0000-00-00 00:00:00' !== $this->data->post_date ? Utils::prepare_date_response( $this->data->post_date_gmt, $this->data->post_date ) : null;
				},
				'dateGmt'                   => function () {
					return ! empty( $this->data->post_date_gmt ) ? Utils::prepare_date_response( $this->data->post_date_gmt ) : null;
				},
				'editLastId'                => function () {
					$edit_last = get_post_meta( $this->data->ID, '_edit_last', true );

					return ! empty( $edit_last ) ? absint( $edit_last ) : null;
				},
				'editLock'                  => function () {
					if ( ! function_exists( 'wp_check_post_lock' ) ) {
						// @phpstan-ignore requireOnce.fileNotFound
						require_once ABSPATH . 'wp-admin/includes/post.php';
					}

					if ( ! wp_check_post_lock( $this->data->ID ) ) {
						return null;
					}

					$edit_lock       = get_post_meta( $this->data->ID, '_edit_lock', true );
					$edit_lock_parts = ! empty( $edit_lock ) ? explode( ':', $edit_lock ) : null;

					return ! empty( $edit_lock_parts ) ? $edit_lock_parts : null;
				},
				'enclosure'                 => function () {
					$enclosure = get_post_meta( $this->data->ID, 'enclosure', true );

					return ! empty( $enclosure ) ? $enclosure : null;
				},
				'enqueuedScriptsQueue'      => static function () {
					global $wp_scripts;
					do_action( 'wp_enqueue_scripts' );
					$queue = $wp_scripts->queue;
					$wp_scripts->reset();
					$wp_scripts->queue = [];

					return $queue;
				},
				'enqueuedStylesheetsQueue'  => static function () {
					global $wp_styles;
					do_action( 'wp_enqueue_scripts' );
					$queue = $wp_styles->queue;
					$wp_styles->reset();
					$wp_styles->queue = [];

					return $queue;
				},
				'excerptRaw'                => [
					'callback'   => function () {
						return ! empty( $this->data->post_excerpt ) ? $this->data->post_excerpt : null;
					},
					'capability' => isset( $this->post_type_object->cap->edit_posts ) ? $this->post_type_object->cap->edit_posts : 'edit_posts',
				],
				'excerptRendered'           => function () {
					$excerpt = ! empty( $this->data->post_excerpt ) ? $this->data->post_excerpt : '';
					$excerpt = apply_filters( 'get_the_excerpt', $excerpt, $this->data );

					return $this->html_entity_decode( apply_filters( 'the_excerpt', $excerpt ), 'excerptRendered' );
				},
				'featuredImageDatabaseId'   => function () {
					if ( $this->isRevision ) {
						$id = $this->parentDatabaseId;
					} else {
						$id = $this->data->ID;
					}

					$thumbnail_id = get_post_thumbnail_id( $id );

					return ! empty( $thumbnail_id ) ? absint( $thumbnail_id ) : null;
				},
				'featuredImageId'           => function () {
					return ! empty( $this->featuredImageDatabaseId ) ? Relay::toGlobalId( 'post', (string) $this->featuredImageDatabaseId ) : null;
				},
				'guid'                      => function () {
					return ! empty( $this->data->guid ) ? $this->data->guid : null;
				},
				'hasPassword'               => function () {
					return ! empty( $this->data->post_password );
				},
				'id'                        => function () {
					return ! empty( $this->data->post_type && ! empty( $this->databaseId ) ) ? Relay::toGlobalId( 'post', (string) $this->databaseId ) : null;
				},
				'isFrontPage'               => function () {
					if ( 'page' !== $this->data->post_type || 'page' !== get_option( 'show_on_front' ) ) {
						return false;
					}
					if ( absint( get_option( 'page_on_front', 0 ) ) === $this->data->ID ) {
						return true;
					}

					return false;
				},
				'isPostsPage'               => function () {
					if ( 'page' !== $this->data->post_type ) {
						return false;
					}
					if ( 'posts' !== get_option( 'show_on_front', 'posts' ) && absint( get_option( 'page_for_posts', 0 ) ) === $this->data->ID ) {
						return true;
					}

					return false;
				},
				'isPreview'                 => function () {
					if ( $this->isRevision ) {
						$revisions = wp_get_post_revisions(
							$this->data->post_parent,
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

					if ( ! post_type_supports( $this->data->post_type, 'revisions' ) && 'draft' === $this->data->post_status ) {
						return true;
					}

					return false;
				},
				'isPrivacyPage'             => function () {
					if ( 'page' !== $this->data->post_type ) {
						return false;
					}
					if ( absint( get_option( 'wp_page_for_privacy_policy', 0 ) ) === $this->data->ID ) {
						return true;
					}

					return false;
				},
				'isRevision'                => function () {
					return 'revision' === $this->data->post_type;
				},
				'isSticky'                  => function () {
					return is_sticky( $this->data->ID );
				},
				'link'                      => function () {
					$link = get_permalink( $this->data->ID );

					if ( $this->isPreview ) {
						$link = get_preview_post_link( $this->parentDatabaseId );
					} elseif ( $this->isRevision ) {
						$link = get_permalink( $this->data->ID );
					}

					return ! empty( $link ) ? urldecode( $link ) : null;
				},
				'menuOrder'                 => function () {
					return ! empty( $this->data->menu_order ) ? absint( $this->data->menu_order ) : null;
				},
				'modified'                  => function () {
					return ! empty( $this->data->post_modified ) && '0000-00-00 00:00:00' !== $this->data->post_modified ? Utils::prepare_date_response( $this->data->post_modified ) : null;
				},
				'modifiedGmt'               => function () {
					return ! empty( $this->data->post_modified_gmt ) ? Utils::prepare_date_response( $this->data->post_modified_gmt ) : null;
				},
				'pageTemplate'              => function () {
					$slug = get_page_template_slug( $this->data->ID );

					return ! empty( $slug ) ? $slug : null;
				},
				'parentDatabaseId'          => function () {
					return ! empty( $this->data->post_parent ) ? absint( $this->data->post_parent ) : null;
				},
				'parentId'                  => function () {
					return ( ! empty( $this->data->post_type ) && ! empty( $this->parentDatabaseId ) ) ? Relay::toGlobalId( 'post', (string) $this->parentDatabaseId ) : null;
				},
				'password'                  => function () {
					return ! empty( $this->data->post_password ) ? $this->data->post_password : null;
				},
				'pinged'                    => function () {
					$punged = get_pung( $this->data->ID );

					return empty( $punged ) ? null : implode( ',', (array) $punged );
				},
				'pingStatus'                => function () {
					return ! empty( $this->data->ping_status ) ? $this->data->ping_status : null;
				},
				'post_type'                 => function () {
					return ! empty( $this->data->post_type ) ? $this->data->post_type : null;
				},
				'previewRevisionDatabaseId' => [
					'callback'   => function () {
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
				'previewRevisionId'         => function () {
					return ! empty( $this->previewRevisionDatabaseId ) ? Relay::toGlobalId( 'post', (string) $this->previewRevisionDatabaseId ) : null;
				},
				'slug'                      => function () {
					return ! empty( $this->data->post_name ) ? urldecode( $this->data->post_name ) : null;
				},
				'status'                    => function () {
					return ! empty( $this->data->post_status ) ? $this->data->post_status : null;
				},
				'template'                  => function () {
					$registered_templates = wp_get_theme()->get_page_templates( null, $this->data->post_type );

					$template = [
						'__typename'   => 'DefaultTemplate',
						'templateName' => 'Default',
					];

					if ( true === $this->isPreview ) {
						$parent_post = get_post( $this->parentDatabaseId );

						if ( empty( $parent_post ) ) {
							return $template;
						}

						/** @var \WP_Post $parent_post */
						$registered_templates = wp_get_theme()->get_page_templates( $parent_post );

						if ( empty( $registered_templates ) ) {
							return $template;
						}
						$set_template  = get_post_meta( $parent_post->ID, '_wp_page_template', true );
						$template_name = get_page_template_slug( $parent_post->ID );

						if ( empty( $set_template ) ) {
							$set_template = get_post_meta( $this->data->ID, '_wp_page_template', true );
						}

						if ( empty( $template_name ) ) {
							$template_name = get_page_template_slug( $this->data->ID );
						}

						$template_name = ! empty( $template_name ) ? $template_name : 'Default';
					} else {
						if ( empty( $registered_templates ) ) {
							return $template;
						}

						$set_template  = get_post_meta( $this->data->ID, '_wp_page_template', true );
						$template_name = get_page_template_slug( $this->data->ID );

						$template_name = ! empty( $template_name ) ? $template_name : 'Default';
					}

					if ( ! empty( $registered_templates[ $set_template ] ) ) {
						$name = Utils::format_type_name_for_wp_template( $registered_templates[ $set_template ], $set_template );

						// If the name is empty, fallback to DefaultTemplate
						if ( empty( $name ) ) {
							$name = 'DefaultTemplate';
						}

						$template = [
							'__typename'   => $name,
							'templateName' => ucwords( $registered_templates[ $set_template ] ),
						];
					}

					return $template;
				},
				'titleRaw'                  => [
					'callback'   => function () {
						return ! empty( $this->data->post_title ) ? $this->data->post_title : null;
					},
					'capability' => isset( $this->post_type_object->cap->edit_posts ) ? $this->post_type_object->cap->edit_posts : 'edit_posts',
				],
				'titleRendered'             => function () {
					$id    = ! empty( $this->data->ID ) ? $this->data->ID : null;
					$title = ! empty( $this->data->post_title ) ? $this->data->post_title : '';

					$processedTitle = ! empty( $title ) ? $this->html_entity_decode( apply_filters( 'the_title', $title, $id ), 'titleRendered', true ) : '';

					return empty( $processedTitle ) ? null : $processedTitle;
				},
				'toPing'                    => function () {
					$to_ping = get_to_ping( $this->data->ID );

					return ! empty( $to_ping ) ? implode( ',', (array) $to_ping ) : null;
				},
				'uri'                       => function () {
					$uri = $this->link;

					if ( true === $this->isFrontPage ) {
						return '/';
					}

					// if the page is set as the posts page
					// the page node itself is not identifiable
					// by URI. Instead, the uri would return the
					// Post content type as that uri
					// represents the blog archive instead of a page
					if ( true === $this->isPostsPage ) {
						return null;
					}

					return ! empty( $uri ) ? str_ireplace( home_url(), '', $uri ) : null;
				},

				// Aliases.
				'ID'                        => function () {
					return $this->databaseId;
				},
				'post_author'               => function () {
					return $this->authorDatabaseId;
				},
				'post_status'               => function () {
					return $this->status;
				},
			];

			if ( 'attachment' === $this->data->post_type ) {
				$attachment_fields = [
					'altText'             => function () {
						return get_post_meta( $this->data->ID, '_wp_attachment_image_alt', true );
					},
					'captionRaw'          => [
						'callback'   => function () {
							return ! empty( $this->data->post_excerpt ) ? $this->data->post_excerpt : null;
						},
						'capability' => isset( $this->post_type_object->cap->edit_posts ) ? $this->post_type_object->cap->edit_posts : 'edit_posts',
					],
					'captionRendered'     => function () {
						$caption = apply_filters( 'the_excerpt', apply_filters( 'get_the_excerpt', $this->data->post_excerpt, $this->data ) );

						return ! empty( $caption ) ? $caption : null;
					},
					'descriptionRaw'      => [
						'callback'   => function () {
							return ! empty( $this->data->post_content ) ? $this->data->post_content : null;
						},
						'capability' => isset( $this->post_type_object->cap->edit_posts ) ? $this->post_type_object->cap->edit_posts : 'edit_posts',
					],
					'descriptionRendered' => function () {
						return ! empty( $this->data->post_content ) ? apply_filters( 'the_content', $this->data->post_content ) : null;
					},
					'mediaDetails'        => function () {
						$media_details = wp_get_attachment_metadata( $this->data->ID );
						if ( ! empty( $media_details ) ) {
							$media_details['ID'] = $this->data->ID;

							return $media_details;
						}

						return null;
					},
					'mediaItemUrl'        => function () {
						return wp_get_attachment_url( $this->data->ID ) ?: null;
					},
					'mediaType'           => function () {
						return wp_attachment_is_image( $this->data->ID ) ? 'image' : 'file';
					},
					'mimeType'            => function () {
						return ! empty( $this->data->post_mime_type ) ? $this->data->post_mime_type : null;
					},
					'sourceUrl'           => function () {
						return $this->get_source_url_by_size( 'full' );
					},
					'sourceUrlsBySize'    => function () {
						_doing_it_wrong(
							__METHOD__,
							'`sourceUrlsBySize` is deprecated. Use the `sourceUrlBySize` callable instead.',
							'1.29.1'
						);

						/**
						 * This returns an empty array on the VIP Go platform.
						 */
						$sizes = get_intermediate_image_sizes(); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.get_intermediate_image_sizes_get_intermediate_image_sizes
						$urls  = [];
						if ( ! empty( $sizes ) && is_array( $sizes ) ) {
							foreach ( $sizes as $size ) {
								$urls[ $size ] = $this->get_source_url_by_size( $size );
							}
						}

						return $urls;
					},
				];

				$this->fields = array_merge( $this->fields, $attachment_fields );
			}

			// Deprecated.
			if ( isset( $this->post_type_object ) && isset( $this->post_type_object->graphql_single_name ) ) {
				$type_id                  = $this->post_type_object->graphql_single_name . 'Id';
				$this->fields[ $type_id ] = function () {
					return absint( $this->data->ID );
				};
			}
		}
	}

	/**
	 * Gets the source URL for an image attachment by size.
	 *
	 * This method caches the source URL for a given size to prevent multiple calls to `wp_get_attachment_image_src`.
	 *
	 * @param ?string $size The size of the image to get the source URL for. `full` by default.
	 */
	public function get_source_url_by_size( ?string $size = 'full' ): ?string {
		// If size is not set, default to 'full'.
		if ( ! $size ) {
			$size = 'full';
		}

		// Resolve the source URL for the size if it hasn't been resolved yet.
		if ( ! array_key_exists( $size, $this->source_urls_by_size ) ) {
			$src = wp_get_attachment_image_src( $this->data->ID, $size );

			$this->source_urls_by_size[ $size ] = ! empty( $src ) ? $src[0] : null;
		}

		return $this->source_urls_by_size[ $size ];
	}
}

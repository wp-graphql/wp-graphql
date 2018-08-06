<?php

namespace WPGraphQL\Type\Widget;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Type\PostObject\Connection\PostObjectConnectionDefinition;
use WPGraphQL\Type\WPObjectType;
use WPGraphQL\Types;

/**
 * Acts as a registry and factory for WidgetTypes.
 *
 * @since   0.0.31
 * @package WPGraphQL
 */

class WidgetTypes {

  /**
	 * Stores the widget type objects
	 *
	 * @var array $types
	 * @since  0.0.31
	 * @access private
	 */
  private static $types;

  /**
   * Retrieves widget type objects
   *
   * @param string $type_name - Name of widget type
   * @return WPObjectType
   */
  public static function __callStatic( string $type_name, array $args = [] ) {

    /**
     * Initialize widget types;
     */
    if( null === self::$types ) self::$types = array();
    
    /**
     * Retrieve unloaded default widget types
     */
    $class_name = __CLASS__;
    $type_func = "{$type_name}_config";
    if( is_callable( "{$class_name}::{$type_func}" ) && ! self::loaded( $type_name ) ) {
      self::$types[ $type_name ] = new WPObjectType( self::$type_func() );
    }


    /**
     * Filter for adding custom widget types
     */
    self::$types = apply_filters( "graphql_{$type_name}_widget_type", self::$types, $type_name );

    /**
     * Check if type already loaded
     */
    $type =& self::$types[ $type_name ];
    if( self::loaded( $type_name ) ) return $type;
  }

  /**
   * Checks if widget type is loaded
   *
   * @param string $type_name - Name of widget type
   * @return boolean
   */
  private static function loaded( string $type_name ) {
    return isset( self::$types[ $type_name ] ) && is_object( self::$types[ $type_name ] );
  }

  public static function get_types() {
    return [
      self::meta(),
    ];
  }

  /**
   * Defines fields shared by all widgets
   *
   * @param array $fields - Widget type fields definition
   * @return array
   */
  private static function fields( $fields = [] ) {

    $fields = array(
      Types::widget()->getField( 'id' ),
      Types::widget()->getField( 'widgetId' ),
      Types::widget()->getField( 'name' )
    );

    return $fields;
  }

  /**
   * Defines interfaces shared by all widgets
   *
   * @param array $interfaces - Widget type interface definition
   * @return array
   */
  private static function interfaces( array $interfaces = array() ) {
   
    return function () use ( $interfaces ) {
      
      return array_merge(
        $interfaces,
        [
          Types::widget(),
          WPObjectType::node_interface()
        ]
      );

    };

  }

  /**
   * Defines a generic title field
   *
   * @return array
   */
  public static function title_field() {
    return array(
      'type' => Types::string(),
      'description' => __( 'Display name of widget', 'wp-graphql' ),
      'resolve' => self::resolve_field( 'title', '' ),
    );
  }

  /**
   * Defines a generic resolver function
   *
   * @return callable
   */
  public static function resolve_field( string $key, $default = null ) {

    return function( array $widget, $args, AppContext $context, ResolveInfo $info ) use ( $key, $default ) {
      return ( ! empty( $widget[ $key ] ) ) ? $widget[ $key ] : $default;
    };

  }

  /**
   * Defines Archives widget type
   *
   * @since 0.0.31
   * @return array
   */
  public static function archives_config() {
    return [
			'name'        => 'ArchivesWidget',
			'description' => __( 'An archives widget object', 'wp-graphql' ),
			'fields'      => self::fields(
        array(
          'title'     => self::title_field(),
          'count'     => [
            'type'        => Types::boolean(),
            'description' => __( 'Show posts count', 'wp-graphql' ),
            'resolve'     => self::resolve_field( 'count', false )
          ],
          'dropdown'  => [
            'type'        => Types::boolean(),
            'description' => __( 'Display as dropdown', 'wp-graphql' ),
            'resolve'     => self::resolve_field( 'dropdown', false )
          ]
        )
      ),
			'interfaces'  => self::interfaces(),
		];
  }

  /**
   * Defines Audio widget type
   *
   * @since 0.0.31
   * @return array
   */
  public static function media_audio_config() {
    return [
			'name'        => 'AudioWidget',
			'description' => __( 'An audio widget object', 'wp-graphql' ),
			'fields'      => self::fields(
        array(
          'title' => self::title_field(),
          'audio' => [
            'type'        => Types::post_object( 'attachment' ),
            'description' => __( 'Widget audio file data object', 'wp-graphql' ),
            'resolve'     => function( array $widget ) {
              return ( ! empty( $widget[ 'attachment_id' ] ) ) ?
                DataSource::resolve_post_object( absint( $widget[ 'attachment_id' ] ) ) : null;
            }
          ]
        )
      ),
			'interfaces'  => self::interfaces(),
		];
  }

  /**
   * Defines Calendar widget type
   *
   * @since 0.0.31
   * @return array
   */
  public static function calendar_config() {
    return [
			'name' => 'CalenderWidget',
			'description' => __( 'A calendar widget object', 'wp-graphql' ),
			'fields' => self::fields(
        array(
          'title' => self::title_field(),
        )
      ),
			'interfaces' => self::interfaces(),
		];
  }

  /**
   * Defines Categories widget type
   *
   * @since 0.0.31
   * @return array
   */
  public static function categories_config() {
    return [
			'name' => 'CategoriesWidget',
			'description' => __( 'A categories widget object', 'wp-graphql' ),
			'fields' => self::fields(
        array(
          'title'     => self::title_field(),
          'count'     => [
            'type'        => Types::boolean(),
            'description' => __( 'Show posts count', 'wp-graphql' ),
            'resolve'     => self::resolve_field( 'count', false )
          ],
          'dropdown'  => [
            'type'        => Types::boolean(),
            'description' => __( 'Display as dropdown', 'wp-graphql' ),
            'resolve'     => self::resolve_field( 'dropdown', false )
          ],
          'hierarchical'  => [
            'type'        => Types::boolean(),
            'description' => __( 'Show hierachy', 'wp-graphql' ),
            'resolve'     => self::resolve_field( 'hierarchical', false )
          ]
        )
      ),
			'interfaces' => self::interfaces(),
		];
  }

  /**
   * Defines Custom HTML widget type
   *
   * @since 0.0.31
   * @return array
   */
  public static function custom_html_config() {
    return [
			'name' => 'CustomHTMLWidget',
			'description' => __( 'A custom html widget object', 'wp-graphql' ),
			'fields' => self::fields(
        array(
          'title'     => self::title_field(),
          'content'     => [
            'type'        => Types::string(),
            'description' => __( 'Content of custom html widget', 'wp-graphql' ),
            'resolve'     => self::resolve_field( 'content', '' )
          ],
        )
      ),
			'interfaces' => self::interfaces(),
		];
  }

  /**
   * Defines Gallery widget type
   *
   * @since 0.0.31
   * @return array
   */
  public static function gallery_config() {
    return [
			'name' => 'GalleryWidget',
			'description' => __( 'A gallery widget object', 'wp-graphql' ),
			'fields' => self::fields(
        array(
          'title'   => self::title_field(),
          'columns' => [
            'type'        => Types::int(),
            'description' => __( 'Number of columns in gallery showcase', 'wp-graphql' ),
            'resolve'     => self::resolve_field( 'columns', 3 ),
          ],
          'size' => [
            'type'        => self::gallery_size_enum(),
            'description' => __( 'Display size of gallery images', 'wp-graphql' ),
            'resolve'     => function( array $widget ) {

            },
          ],
          'linkType' => [
            'type'        => self::gallery_link_enum(),
            'description' => __( 'Link types of gallery images', 'wp-graphql'),
            'resolve'     => function( array $widget ) {

            },
          ],
          'orderbyRandom' => [
            'type'        => self::boolean(),
            'description' => __( 'Random Order', 'wp-graphql'),
            'resolve'     => self::resolve_field( 'orderby_random', false ),
          ],
          'images' => PostObjectConnectionDefinition::connection( 'attachment ')
        )
      ),
			'interfaces' => self::interfaces(),
		];
  }

  /**
   * Defines Image widget type
   *
   * @since 0.0.31
   * @return array
   */
  public static function image_config() {
    return [
			'name' => 'ImageWidget',
			'description' => __( 'A image widget object', 'wp-graphql' ),
			'fields' => self::fields(
        array(
          'title' => self::title_field(),
          'image' => [
            'type'        => Types::post_object( 'attachment' ),
            'description' => __( 'Widget audio file data object', 'wp-graphql' ),
            'resolve'     => function( array $widget ) {
              return ( ! empty( $widget[ 'attachment_id' ] ) ) ?
                DataSource::resolve_post_object( absint( $widget[ 'attachment_id' ] ) ) : null;
            }
          ]
        )
      ),
			'interfaces' => self::interfaces(),
		];
  }

  /**
   * Defines Meta widget type
   *
   * @since 0.0.31
   * @return array
   */
  public static function meta_config() {
    return [
			'name' => 'MetaWidget',
			'description' => __( 'A meta widget object', 'wp-graphql' ),
			'fields' => self::fields(
        array(
          'title' => self::title_field(),
        )
      ),
			'interfaces' => self::interfaces(),
		];
  }

  /**
   * Defines Nav Menu widget type
   *
   * @since 0.0.31
   * @return array
   */
  public static function nav_menu_config() {
    return [
			'name' => 'NavMenuWidget',
			'description' => __( 'A navigation menu widget object', 'wp-graphql' ),
			'fields' => self::fields(
        array(
          'title' => self::title_field(),
          'menu' => [
            'type'        => Types::menu(),
            'description' => __( 'Widget navigation menu', 'wp-graphql' ),
            'resolve'     => function( array $widget ) {
              return ( ! empty( $widget[ 'nav_menu' ] ) ) ?
                DataSource::resolve_term_object( absint( $widget[ 'nav_menu' ] ), 'nav_menu' ) : null;
            }
          ]
        )
      ),
			'interfaces' => self::interfaces(),
		];
  }

  /**
   * Defines Pages widget type
   *
   * @since 0.0.31
   * @return array
   */
  public static function pages_config() {
    return [
			'name' => 'PagesWidget',
			'description' => __( 'A pages widget object', 'wp-graphql' ),
			'fields' => self::fields(
        array(
          'title'   => self::title_field(),
          'sortby' => [
            'type'        => self::pages_sort_enum(),
            'description' => __( 'Sort style of widget', 'wp-graphql' ),
            'resolve'     => function( array $widget ) {

            }
          ],
          'exclude' => [
            'type'        => Types::list_of( Types::int() ),
            'description' => __( 'WP ID of pages excluding from widget display', 'wp-graphql' ),
            'resolve'     => function( array $widget ) {
              return ( ! empty( $widget[ 'exclude' ] ) ) ? explode(',', $widget[ 'exclude' ] ) : null;
            }
          ],
        )
      ),
			'interfaces' => self::interfaces(),
		];
  }

  /**
   * Defines Recent Comments widget type
   *
   * @since 0.0.31
   * @return array
   */
  public static function recent_comments_config() {
    return [
			'name' => 'RecentCommentsWidget',
			'description' => __( 'A recent comments widget object', 'wp-graphql' ),
			'fields' => self::fields(
        array(
          'title'   => self::title_field(),
          'commentsPerDisplay' => [
            'type'        => Types::int(),
            'description' => __( 'Number of comments to display at one time', 'wp-graphql' ),
            'resolve'     => self::resolve_field( 'number', 5 ),
          ],
        )
      ),
			'interfaces' => self::interfaces(),
		];
  }

  /**
   * Defines Recent Posts widget type
   *
   * @since 0.0.31
   * @return array
   */
  public static function recent_posts_config() {
    return [
			'name' => 'RecentPostsWidget',
			'description' => __( 'A recent posts widget object', 'wp-graphql' ),
			'fields' => self::fields(
        array(
          'title'   => self::title_field(),
          'postsPerDisplay' => [
            'type'        => Types::int(),
            'description' => __( 'Number of posts to display at one time', 'wp-graphql' ),
            'resolve'     => self::resolve_field( 'number', 5 ),
          ],
          'showDate'     => [
            'type'        => Types::boolean(),
            'description' => __( 'Show post date', 'wp-graphql' ),
            'resolve'     => self::resolve_field( 'show_date', false )
          ],
        )
      ),
			'interfaces' => self::interfaces(),
		];
  }

  /**
   * Defines RSS widget type
   *
   * @since 0.0.31
   * @return array
   */
  public static function rss_config() {
    return [
			'name' => 'RSSWidget',
			'description' => __( 'A rss feed widget object', 'wp-graphql' ),
			'fields' => self::fields(array()),
			'interfaces' => self::interfaces(),
		];
  }

  /**
   * Defines Search widget type
   *
   * @since 0.0.31
   * @return array
   */
  public static function search_config() {
    return[
			'name' => 'SearchWidget',
			'description' => __( 'A search widget object', 'wp-graphql' ),
			'fields' => self::fields(array()),
			'interfaces' => self::interfaces(),
		];
  }

  /**
   * Defines Tag Cloud widget type
   *
   * @since 0.0.31
   * @return array
   */
  public static function tag_cloud_config() {
    return [
			'name' => 'TagCloudWidget',
			'description' => __( 'A tag cloud widget object', 'wp-graphql' ),
			'fields' => self::fields(array()),
			'interfaces' => self::interfaces(),
		];
  }

  /**
   * Defines Text widget type
   *
   * @since 0.0.31
   * @return array
   */
  public static function text_config() {
    return [
			'name' => 'TextWidget',
			'description' => __( 'A text widget object', 'wp-graphql' ),
			'fields' => self::fields(array()),
			'interfaces' => self::interfaces(),
		];
  }

  /**
   * Defines Video widget type
   *
   * @since 0.0.31
   * @return array
   */
  public static function video_config() {
    return [
			'name' => 'VideoWidget',
			'description' => __( 'A video widget object', 'wp-graphql' ),
			'fields' => self::fields(array()),
			'interfaces' => self::interfaces(),
		];
  }

}
<?php

namespace WPGraphQL\Type\Widget;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Type\PostObject\Connection\PostObjectConnectionDefinition;
use WPGraphQL\Type\WPEnumType;
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
  public static function __callStatic( $type_name, array $args = [null] ) {
    /**
     * Initialize widget types;
     */
    if( null === self::$types ) self::$types = array();
    
    /**
     * Check if default type configuration exists and load it
     */
    $type_name = str_replace('-', '_', $type_name);
    $type_func = "{$type_name}_config";
    if( method_exists( __CLASS__, $type_func ) && ! self::loaded( $type_name ) ) {
      self::$types[ $type_name ] = new WPObjectType( self::$type_func() );
    }
    
    /**
     * If no default config exist use base configuration
     */
    elseif( ! method_exists( __CLASS__, $type_func ) && ! self::loaded( $type_name ) ) {
      self::$types[ $type_name ] = new WPObjectType( self::_config( $type_name, $args[0] ) );
    }

    /**
     * Filter for providing a specific widget type
     */
    self::$types = apply_filters( "graphql_{$type_name}_widget_type", self::$types, $type_name );

    /**
     * Check if type already loaded
     */
    $type = self::$types[ $type_name ];
    if( self::loaded( $type_name ) ) return $type;
  }

  /**
   * Checks if widget type is loaded
   *
   * @param string $type_name - Name of widget type
   * @return boolean
   */
  private static function loaded( $type_name ) {
    return isset( self::$types[ $type_name ] ) && self::$types[ $type_name ] instanceof WPObjectType;
  }

  /**
   * Get widget type listing for invisible types
   *
   * @return array
   */
  public static function get_types() {
    /**
     * Get active widget types
     */
    $widgets = DataSource::get_active_widget_types();
    
    /**
     * Initialize return array and default enum types
     */
    $types = [
      self::image_size_enum(),
      self::link_to_enum(),
      self::preload_enum(),
      self::sortby_enum(),
    ];

    /**
     * Loop through active widget types and create widget type
     */
    foreach( $widgets as $type_name => $data ) {
      $types[] = self::$type_name( $data );
    }
    return $types;
  }

  /**
   * Prepares WPObjectType config array
   *
   * @param string $type_name - object type name
   * @param array $fields - array of object type field definitions
   * @param array $interfaces - array of object type interface definitions
   * @param string $description - object type description
   * @return array - WPObjectType config
   */
  private static function create_type_config( $type_name, $fields, $interfaces, $description = '' ) {
    return [
			'name'        => $type_name,
			'description' => $description,
			'fields'      => self::prepare_fields( $fields, $type_name ),
			'interfaces'  => self::prepare_interfaces( $interfaces, $type_name ),
		];
  }

  /**
   * Prepares WPObjectType config array from data array
   *
   * @param string $type_name
   * @param array $data
   * @return void
   */
  private static function _config( $type_name, $data ) {
    
    if ( null === $data ) return null;
    
    $description = ( ! empty( $data['widget_description'] ) ) ? $data['widget_description'] : '';
    unset( $data['widget_description'] );

    /**
     * Create fields array
     */
    $fields = [];

    /**
     * Loop through widget data settings
     */
    foreach( $data as $key => $value ) {
      /**
       * Create WPGraphQL-friendly field name
       */
      $field_key = lcfirst( str_replace( ['_', '-'], '', ucwords( $key, '_-' ) ) );

      /**
       * Get field type and default value
       */
      switch( gettype( $value ) ) {
        case 'boolean':
          $field_type = Types::boolean();
          $field_default = false;
          break;
        
        case 'integer':
          $field_type = Types::int();
          $field_default = 0;
          break;

        case 'double':
          $field_type = Types::float();
          $field_default = 0;
          break;

        default: 
          $field_type = Types::string();
          $field_default = '';
      } 

      /**
       * create field definition
       */
      $fields[ $field_key ] = [
        'type'        => $field_type,
        'resolve'     => self::resolve_field( $key, $field_default )
      ];
    }

    return self::create_type_config( $type_name, $fields, [], $description );
  }

  /**
   * Filters fields array and adds parent fields
   *
   * @param array $fields - widget type fields definition
   * @param string $type_name - widget type name
   * @return array
   */
  private static function prepare_fields( $fields, $type_name ) {

    /**
     * Filter once with lowercase, once with uppercase for Back Compat.
     */
    $lc_type_name = lcfirst( $type_name );
    $uc_type_name = ucfirst( $type_name );

    /**
     * Filter the fields with the typename explicitly in the filter name
     *
     * This is useful for more targeted filtering, and is applied after the general filter, to allow for
     * more specific overrides
     *
     * @param array $fields The array of fields for the object config
     */
    $fields = apply_filters( "graphql_{$lc_type_name}_fields", $fields );

    /**
     * Filter the fields with the typename explicitly in the filter name
     *
     * This is useful for more targeted filtering, and is applied after the general filter, to allow for
     * more specific overrides
     *
     * @param array $fields The array of fields for the object config
     */
    $fields = apply_filters( "graphql_{$uc_type_name}_fields", $fields );

    /**
     * This sorts the fields alphabetically by the key, which is super handy for making the schema readable,
     * as it ensures it's not output in just random order
     */
    ksort( $fields );

    return function () use ( $fields ) {
      return array_merge( Types::widget()->getFields(), $fields  );
    };

  }

  /**
   * Defines interfaces shared by all widgets
   *
   * @param array $interfaces - Widget type interface definition
   * @return array
   */
  private static function prepare_interfaces( $interfaces, $type_name ) {

    /**
     * Filter once with lowercase, once with uppercase for Back Compat.
     */
    $lc_type_name = lcfirst( $type_name );
    $uc_type_name = ucfirst( $type_name );

    /**
     * Filter the interfaces with the typename explicitly in the filter name
     *
     * This is useful for more targeted filtering, and is applied after the general filter, to allow for
     * more specific overrides
     *
     * @param array $interfaces The array of intefaces for the object config
     */
    $interfaces = apply_filters( "graphql_{$lc_type_name}_interfaces", $interfaces );

    /**
     * Filter the interfaces with the typename explicitly in the filter name
     *
     * This is useful for more targeted filtering, and is applied after the general filter, to allow for
     * more specific overrides
     *
     * @param array $interfaces The array of interfaces for the object config
     */
    $interfaces = apply_filters( "graphql_{$uc_type_name}_interfaces", $interfaces );
   
    return function () use ( $interfaces ) {
      return array_merge( [ Types::widget(), WPObjectType::node_interface() ], $interfaces );
    };

  }

  /**
   * Defines a generic resolver function
   *
   * @return callable
   */
  public static function resolve_field( $key, $default = null ) {

    return function( array $widget, $args, AppContext $context, ResolveInfo $info ) use ( $key, $default ) {
      return ( ! empty( $widget[ $key ] ) ) ? $widget[ $key ] : $default;
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
   * Stores image size EnumType used by gallery and image widgets
   *
   * @var EnumType
   */
  private static $image_size_enum;

  /**
   * Stores link destination-type EnumType used by gallery and image widgets
   *
   * @var EnumType
   */
  private static $link_to_enum;

  /**
   * Stores preload EnumType used by video and audio widgets
   *
   * @var EnumType
   */
  private static $preload_enum;

  /**
   * Stores sortby EnumType used by pages widget
   *
   * @var EnumType
   */
  private static $sortby_enum;

  /**
   * Defines and register image_size enumeration type
   *
   * @return EnumType
   */
  public static function image_size_enum() {
    return self::$image_size_enum ?: self::$image_size_enum = new WPEnumType(
      array(
        'name' => 'ImageSizeEnum',
        'description' => __( 'Size of image', 'wp-graphql' ),
        'values' => array( 'THUMBNAIL', 'MEDIUM', 'LARGE', 'FULLSIZE' )
      )
    );
  }  

  /**
   * Defines and register link to enumeration type
   *
   * @return WPEnumType
   */
  public static function link_to_enum() {
    return self::$link_to_enum ?: self::$link_to_enum = new WPEnumType(
      array(
        'name' => 'LinkToEnum',
        'description' => __( 'Destination type of link', 'wp-graphql' ),
        'values' => array( 'NONE', 'POST', 'FILE', 'CUSTOM' )
      )
    );
  }

  /**
   * Defines and register preload enumeration type
   *
   * @return WPEnumType
   */
  public static function preload_enum() {
    return self::$preload_enum ?: self::$preload_enum = new WPEnumType(
      array(
        'name' => 'PreloadEnum',
        'description' => __( 'Preload type of media widget', 'wp-graphql' ),
        'values' => array( 'AUTO', 'METADATA', 'NONE' )
      )
    );
  }

  /**
   * Defines and register sort order enumeration type
   *
   * @return WPEnumType
   */
  public static function sortby_enum() {
    return self::$sortby_enum ?: self::$sortby_enum = new WPEnumType(
      array(
        'name' => 'SortByEnum',
        'description' => __( 'Sorting order of widget resource type', 'wp-graphql' ),
        'values' => array( 'MENU_ORDER', 'POST_TITLE', 'ID' )
      )
    );
  }

  /**
   * Defines Archives widget type
   *
   * @since 0.0.31
   * @return array
   */
  public static function archives_config() {
    $type_name = 'ArchivesWidget';
    $description = __( 'An archives widget object', 'wp-graphql' );
    $fields = [
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
    ];

    return self::create_type_config( $type_name, $fields, [], $description );
  }

  /**
   * Defines Audio widget type
   *
   * @since 0.0.31
   * @return array
   */
  public static function media_audio_config() {
    $type_name = 'AudioWidget';
    $description = __( 'An audio widget object', 'wp-graphql' );
    $fields = [
      'title' => self::title_field(),
      'audio' => [
        'type'        => Types::post_object( 'attachment' ),
        'description' => __( 'Widget audio file data object', 'wp-graphql' ),
        'resolve'     => function( array $widget ) {
          return ( ! empty( $widget[ 'attachment_id' ] ) ) ?
            DataSource::resolve_post_object( absint( $widget[ 'attachment_id' ] ) ) : null;
        }
      ],
      'preload' => [
        'type'        => self::preload_enum(),
        'description' => __( 'Sort style of widget', 'wp-graphql' ),
        'resolve'     => function( array $widget ) {
          return ( ! empty( $widget[ 'preload' ] ) ) ? strtoupper( $widget[ 'preload' ] ) : 'METADATA';
        }
      ],
      'loop'     => [
        'type'        => Types::boolean(),
        'description' => __( 'Play repeatly', 'wp-graphql' ),
        'resolve'     => self::resolve_field( 'loop', false )
      ],
    ];

    return self::create_type_config($type_name, $fields, [], $description );
  }

  /**
   * Defines Calendar widget type
   *
   * @since 0.0.31
   * @return array
   */
  public static function calendar_config() {
    $type_name = 'CalenderWidget';
		$description = __( 'A calendar widget object', 'wp-graphql' );
    $fields = [ 'title' => self::title_field() ];
    
		return self::create_type_config($type_name, $fields, [], $description );
  }

  /**
   * Defines Categories widget type
   *
   * @since 0.0.31
   * @return array
   */
  public static function categories_config() {
    $type_name = 'CategoriesWidget';
		$description = __( 'A categories widget object', 'wp-graphql' );
		$fields = [
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
    ];

    return self::create_type_config($type_name, $fields, [], $description );
  }

  /**
   * Defines Custom HTML widget type
   *
   * @since 0.0.31
   * @return array
   */
  public static function custom_html_config() {
    $type_name = 'CustomHTMLWidget';
		$description = __( 'A custom html widget object', 'wp-graphql' );
		$fields = [
      'title'     => self::title_field(),
      'content'     => [
        'type'        => Types::string(),
        'description' => __( 'Content of custom html widget', 'wp-graphql' ),
        'resolve'     => self::resolve_field( 'content', '' )
      ],
    ];

    return self::create_type_config($type_name, $fields, [], $description );
  }

  /**
   * Defines Gallery widget type
   *
   * @since 0.0.31
   * @return array
   */
  public static function media_gallery_config() {
    $type_name = 'GalleryWidget';
		$description = __( 'A gallery widget object', 'wp-graphql' );
		$fields = [
      'title'   => self::title_field(),
      'columns' => [
        'type'        => Types::int(),
        'description' => __( 'Number of columns in gallery showcase', 'wp-graphql' ),
        'resolve'     => self::resolve_field( 'columns', 3 ),
      ],
      'size' => [
        'type'        => self::image_size_enum(),
        'description' => __( 'Display size of gallery images', 'wp-graphql' ),
        'resolve'     => function( array $widget ) {
          return ( ! empty( $widget[ 'size' ] ) ) ? strtoupper( $widget[ 'size' ] ) : 'THUMBNAIL';
        },
      ],
      'linkType' => [
        'type'        => self::link_to_enum(),
        'description' => __( 'Link types of gallery images', 'wp-graphql'),
        'resolve'     => function( array $widget ) {
          return ( ! empty( $widget[ 'link_type' ] ) ) ? strtoupper( $widget[ 'link_type' ] ) : 'NONE';
        },
      ],
      'orderbyRandom' => [
        'type'        => Types::boolean(),
        'description' => __( 'Random Order', 'wp-graphql'),
        'resolve'     => self::resolve_field( 'orderby_random', false ),
      ],
      'images' => PostObjectConnectionDefinition::connection( DataSource::resolve_post_type( 'attachment' ), 'GalleryWidget' )
    ];

    return self::create_type_config($type_name, $fields, [], $description );
  }

  /**
   * Defines Image widget type
   *
   * @since 0.0.31
   * @return array
   */
  public static function media_image_config() {
    $type_name = 'ImageWidget';
		$description = __( 'A image widget object', 'wp-graphql' );
		$fields = [
      'title' => self::title_field(),
      'image' => [
        'type'        => Types::post_object( 'attachment' ),
        'description' => __( 'Widget audio file data object', 'wp-graphql' ),
        'resolve'     => function( array $widget ) {
          return ( ! empty( $widget[ 'attachment_id' ] ) ) ?
            DataSource::resolve_post_object( absint( $widget[ 'attachment_id' ] ) ) : null;
        }
      ],
      'linkType' => [
        'type'        => self::link_to_enum(),
        'description' => __( 'Link types of images', 'wp-graphql'),
        'resolve'     => function( array $widget ) {
          return ( ! empty( $widget[ 'link_type' ] ) ) ? strtoupper( $widget[ 'link_type' ] ) : 'NONE';
        },
      ],
      'linkUrl' => [
        'type'        => Types::string(),
        'description' => __( 'Url of image link', 'wp-graphql' ),
        'resolve'     => self::resolve_field( 'link_url', '' ),
      ],
    ];

    return self::create_type_config($type_name, $fields, [], $description );
  }

  /**
   * Defines Meta widget type
   *
   * @since 0.0.31
   * @return array
   */
  public static function meta_config() {
    $type_name = 'MetaWidget';
		$description = __( 'A meta widget object', 'wp-graphql' );
    $fields = [ 'title' => self::title_field() ];
    
    return self::create_type_config($type_name, $fields, [], $description );
  }

  /**
   * Defines Nav Menu widget type
   *
   * @since 0.0.31
   * @return array
   */
  public static function nav_menu_config() {
    $type_name = 'NavMenuWidget';
		$description = __( 'A navigation menu widget object', 'wp-graphql' );
		$fields = [
      'title' => self::title_field(),
      'menu' => [
        'type'        => Types::menu(),
        'description' => __( 'Widget navigation menu', 'wp-graphql' ),
        'resolve'     => function( array $widget ) {
          return ( ! empty( $widget[ 'nav_menu' ] ) ) ?
            DataSource::resolve_term_object( absint( $widget[ 'nav_menu' ] ), 'nav_menu' ) : null;
        }
      ]
    ];

    return self::create_type_config($type_name, $fields, [], $description );
  }

  /**
   * Defines Pages widget type
   *
   * @since 0.0.31
   * @return array
   */
  public static function pages_config() {
    $type_name = 'PagesWidget';
		$description = __( 'A pages widget object', 'wp-graphql' );
		$fields = [
      'title'   => self::title_field(),
      'sortby' => [
        'type'        => self::sortby_enum(),
        'description' => __( 'Sort style of widget', 'wp-graphql' ),
        'resolve'     => function( array $widget ) {
          return ( ! empty( $widget[ 'sortby' ] ) ) ? strtoupper( $widget[ 'sortby' ] ) : 'MENU_ORDER';
        }
      ],
      'exclude' => [
        'type'        => Types::list_of( Types::int() ),
        'description' => __( 'WP ID of pages excluding from widget display', 'wp-graphql' ),
        'resolve'     => function( array $widget ) {
          return ( ! empty( $widget[ 'exclude' ] ) ) ? explode(',', $widget[ 'exclude' ] ) : null;
        }
      ],
    ];
    
    return self::create_type_config($type_name, $fields, [], $description );
  }

  /**
   * Defines Recent Comments widget type
   *
   * @since 0.0.31
   * @return array
   */
  public static function recent_comments_config() {
    $type_name = 'RecentCommentsWidget';
		$description = __( 'A recent comments widget object', 'wp-graphql' );
		$fields = [
      'title'   => self::title_field(),
      'commentsPerDisplay' => [
        'type'        => Types::int(),
        'description' => __( 'Number of comments to display at one time', 'wp-graphql' ),
        'resolve'     => self::resolve_field( 'number', 5 ),
      ],
    ];
    
    return self::create_type_config($type_name, $fields, [], $description );
  }

  /**
   * Defines Recent Posts widget type
   *
   * @since 0.0.31
   * @return array
   */
  public static function recent_posts_config() {
    $type_name = 'RecentPostsWidget';
		$description = __( 'A recent posts widget object', 'wp-graphql' );
		$fields = [
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
    ];
    
    return self::create_type_config($type_name, $fields, [], $description );
  }

  /**
   * Defines RSS widget type
   *
   * @since 0.0.31
   * @return array
   */
  public static function rss_config() {
    $type_name = 'RSSWidget';
		$description = __( 'A rss feed widget object', 'wp-graphql' );
		$fields = [
      'title'   => self::title_field(),
      'url' => [
        'type'        => Types::string(),
        'description' => __( 'Url of RSS/Atom feed', 'wp-graphql' ),
        'resolve'     => self::resolve_field( 'url', '' ),
      ],
      'itemsPerDisplay' => [
        'type'        => Types::int(),
        'description' => __( 'Number of items to display at one time', 'wp-graphql' ),
        'resolve'     => self::resolve_field( 'item', 10 ),
      ],
      'error'     => [
        'type'        => Types::boolean(),
        'description' => __( 'RSS url invalid', 'wp-graphql' ),
        'resolve'     => self::resolve_field( 'error', false )
      ],
      'showSummary'     => [
        'type'        => Types::boolean(),
        'description' => __( 'Show item summary', 'wp-graphql' ),
        'resolve'     => self::resolve_field( 'show_summary', false )
      ],
      'showAuthor'     => [
        'type'        => Types::boolean(),
        'description' => __( 'Show item author', 'wp-graphql' ),
        'resolve'     => self::resolve_field( 'show_author', false )
      ],
      'showDate'     => [
        'type'        => Types::boolean(),
        'description' => __( 'Show item date', 'wp-graphql' ),
        'resolve'     => self::resolve_field( 'show_date', true )
      ],
    ];
    
    return self::create_type_config($type_name, $fields, [], $description );
  }

  /**
   * Defines Search widget type
   *
   * @since 0.0.31
   * @return array
   */
  public static function search_config() {
    $type_name = 'SearchWidget';
		$description = __( 'A search widget object', 'wp-graphql' );
		$fields = [ 'title'   => self::title_field() ];
    
    return self::create_type_config($type_name, $fields, [], $description );
  }

  /**
   * Defines Tag Cloud widget type
   *
   * @since 0.0.31
   * @return array
   */
  public static function tag_cloud_config() {
    $type_name = 'TagCloudWidget';
		$description = __( 'A tag cloud widget object', 'wp-graphql' );
		$fields = [
      'title'     => self::title_field(),
      'showCount' => [
        'type'        => Types::boolean(),
        'description' => __( 'Show tag count', 'wp-graphql' ),
        'resolve'     => self::resolve_field( 'count', true )
      ],
      'taxonomy'  => [
        'type'        => Types::taxonomy(),
        'description' => __( 'Widget taxonomy type', 'wp-graphql' ),
        'resolve'     => function( array $widget ) {
          return ( ! empty( $widget[ 'taxonomy' ] ) ) ? strtoupper( $widget[ 'taxonomy' ] ) : 'POST_TAG';
        }
      ],
    ];
    
    return self::create_type_config($type_name, $fields, [], $description );
  }

  /**
   * Defines Text widget type
   *
   * @since 0.0.31
   * @return array
   */
  public static function text_config() {
    $type_name = 'TextWidget';
		$description = __( 'A text widget object', 'wp-graphql' );
		$fields = [
      'title'   => self::title_field(),
      'text' => [
        'type'        => Types::string(),
        'description' => __( 'Text content of widget', 'wp-graphql' ),
        'resolve'     => self::resolve_field( 'text', '' ),
      ],
      'filterText'     => [
        'type'        => Types::boolean(),
        'description' => __( 'Filter text content', 'wp-graphql' ),
        'resolve'     => self::resolve_field( 'filter', true )
      ],
      'visual'     => [
        'type'        => Types::boolean(),
        'resolve'     => self::resolve_field( 'visual', true )
      ]
    ];
    
    return self::create_type_config($type_name, $fields, [], $description );
  }

  /**
   * Defines Video widget type
   *
   * @since 0.0.31
   * @return array
   */
  public static function media_video_config() {
    $type_name = 'VideoWidget';
		$description = __( 'A video widget object', 'wp-graphql' );
		$fields = [
      'title' => self::title_field(),
      'video' => [
        'type'        => Types::post_object( 'attachment' ),
        'description' => __( 'Widget video file data object', 'wp-graphql' ),
        'resolve'     => function( array $widget ) {
          return ( ! empty( $widget[ 'attachment_id' ] ) ) ?
            DataSource::resolve_post_object( absint( $widget[ 'attachment_id' ] ) ) : null;
        }
      ],
      'preload' => [
        'type'        => self::preload_enum(),
        'description' => __( 'Sort style of widget', 'wp-graphql' ),
        'resolve'     => function( array $widget ) {
          return ( ! empty( $widget[ 'preload' ] ) ) ? strtoupper( $widget[ 'preload' ] ) : 'METADATA';
        }
      ],
      'loop'     => [
        'type'        => Types::boolean(),
        'description' => __( 'Play repeatly', 'wp-graphql' ),
        'resolve'     => self::resolve_field( 'loop', false )
      ],
    ];
    
    return self::create_type_config($type_name, $fields, [], $description );
  }

}
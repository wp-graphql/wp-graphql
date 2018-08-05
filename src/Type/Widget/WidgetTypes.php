<?php

namespace WPGraphQL\Type\Widget;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
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
  private static $types = [];
  
  private static $interface;

  /**
   * Retrieves widget types objects
   *
   * @param string $name
   * @return void
   */
  public static function __callStatic( string $name, array $args ) {
    
    if( isset( self::$types[ $name ] ) ) {
      return self::$types[ $name ];
    }
    $config = "{$name}_config";
    if( is_callable( "WPGraphQL\Type\Widget\WidgetTypes::{$config}" ) ) {
      self::$types[ $name ] = new WPObjectType( self::$config() );
      return self::$types[ $name ];
    }
    if( ! empty( $args[0] ) && is_array( $args[0] ) ) {
      self::$types[ $name ] = new WPObjectType( $args[0] );
      return self::$types[ $name ];
    }
    return null;

  }

  private static function interface() {
    return self::$interface ?: self::$interface = Types::widget();
  }

  private static function fields( $fields = [] ) {
    $interface = self::interface();
    
    $fields += [
      $interface->getField('id'),
      $interface->getField('widgetId'),
      $interface->getField('name'),
    ];

    return $fields;
  }

  private static function interfaces( $interfaces = []) {
   

    $interfaces += [ 
      self::interface(),
    ];

    return $interfaces;
  }

  /**
   * Defines a generic resolver function
   *
   * @return callable
   */
  public static function resolve( $key, $default = null ) {

    return function( array $widget, $args, AppContext $context, ResolveInfo $info ) use ($key) {
      return ( ! empty( $widget[ $key ] ) ) ? $widget[ $key ] : $default;
    };

  }

  public static function resolve_type( $name ) {
    return self::$name();
  }

  public static function archives_config() {
    return [
			'name' => 'ArchivesWidget',
			'description' => __( 'An archives widget object', 'wp-graphql' ),
			'fields' => self::fields(array()),
			'interfaces' => self::interfaces(),
		];
  }

  public static function audio_config() {
    return [
			'name' => 'AudioWidget',
			'description' => __( 'An audio widget object', 'wp-graphql' ),
			'fields' => self::fields(array()),
			'interfaces' => self::interfaces(),
		];
  }

  public static function calendar_config() {
    return [
			'name' => 'CalenderWidget',
			'description' => __( 'A calendar widget object', 'wp-graphql' ),
			'fields' => self::fields(array()),
			'interfaces' => self::interfaces(),
		];
  }

  public static function categories_config() {
    return [
			'name' => 'CategoriesWidget',
			'description' => __( 'A categories widget object', 'wp-graphql' ),
			'fields' => self::fields(array()),
			'interfaces' => self::interfaces(),
		];
  }

  public static function custom_html_config() {
    return [
			'name' => 'CustomHTMLWidget',
			'description' => __( 'A custom html widget object', 'wp-graphql' ),
			'fields' => self::fields(array()),
			'interfaces' => self::interfaces(),
		];
  }

  public static function gallery_config() {
    return [
			'name' => 'GalleryWidget',
			'description' => __( 'A gallery widget object', 'wp-graphql' ),
			'fields' => self::fields(array()),
			'interfaces' => self::interfaces(),
		];
  }

  public static function image_config() {
    return [
			'name' => 'ImageWidget',
			'description' => __( 'A image widget object', 'wp-graphql' ),
			'fields' => self::fields(array()),
			'interfaces' => self::interfaces(),
		];
  }

  public static function meta_config() {
    return [
			'name' => 'MetaWidget',
			'description' => __( 'A meta widget object', 'wp-graphql' ),
			'fields' => self::fields(array()),
			'interfaces' => self::interfaces(),
		];
  }

  public static function nav_menu_config() {
    return [
			'name' => 'NavMenuWidget',
			'description' => __( 'A navigation menu widget object', 'wp-graphql' ),
			'fields' => self::fields(array()),
			'interfaces' => self::interfaces(),
		];
  }

  public static function pages_config() {
    return [
			'name' => 'PagesWidget',
			'description' => __( 'A pages widget object', 'wp-graphql' ),
			'fields' => self::fields(array()),
			'interfaces' => self::interfaces(),
		];
  }

  public static function recent_comments_config() {
    return [
			'name' => 'RecentCommentsWidget',
			'description' => __( 'A recent comments widget object', 'wp-graphql' ),
			'fields' => self::fields(array()),
			'interfaces' => self::interfaces(),
		];
  }

  public static function recent_posts_config() {
    return [
			'name' => 'RecentPostsWidget',
			'description' => __( 'A recent posts widget object', 'wp-graphql' ),
			'fields' => self::fields(array()),
			'interfaces' => self::interfaces(),
		];
  }

  public static function rss_config() {
    return [
			'name' => 'RSSWidget',
			'description' => __( 'A rss feed widget object', 'wp-graphql' ),
			'fields' => self::fields(array()),
			'interfaces' => self::interfaces(),
		];
  }

  public static function search_config() {
    return[
			'name' => 'SearchWidget',
			'description' => __( 'A search widget object', 'wp-graphql' ),
			'fields' => self::fields(array()),
			'interfaces' => self::interfaces(),
		];
  }

  public static function tag_cloud_config() {
    return [
			'name' => 'TagCloudWidget',
			'description' => __( 'A tag cloud widget object', 'wp-graphql' ),
			'fields' => self::fields(array()),
			'interfaces' => self::interfaces(),
		];
  }

  public static function text_config() {
    return [
			'name' => 'TextWidget',
			'description' => __( 'A text widget object', 'wp-graphql' ),
			'fields' => self::fields(array()),
			'interfaces' => self::interfaces(),
		];
  }

  public static function video_config() {
    return [
			'name' => 'VideoWidget',
			'description' => __( 'A video widget object', 'wp-graphql' ),
			'fields' => self::fields(array()),
			'interfaces' => self::interfaces(),
		];
  }

}
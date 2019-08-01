<?php

namespace WPGraphQL;

use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\Type;
use WPGraphQL\Type\Menu;
use WPGraphQL\Type\PostObject;
use WPGraphQL\Type\RootMutationType;
use WPGraphQL\Type\RootQueryType;
use WPGraphQL\Type\Settings;
use WPGraphQL\Type\TermObject;
use WPGraphQL\Type\WPEnumType;
use WPGraphQL\Type\WPObjectType;
use WPGraphQL\Type\WPUnionType;

/**
 * Class Types - Acts as a registry and factory for Types.
 *
 * Each "type" is static ensuring that it will only be instantiated once and can be re-used
 * throughout the system. The types that are "dynamic" (such as post_types, taxonomies, etc)
 * are added as a sub-property to the Types class based on their unique identifier, and are
 * therefore only instantiated once as well.
 *
 * @since   0.0.5
 * @package WPGraphQL
 */
class Types {

	/**
	 * Stores the avatar type object
	 *
	 * @var WPObjectType object $avatar
	 * @since  0.5.0
	 * @access private
	 */
	private static $avatar;

	/**
	 * Stores the comment type object
	 *
	 * @var WPObjectType object $comment
	 * @since  0.5.0
	 * @access private
	 */
	private static $comment;

	/**
	 * Stores the comment author type object
	 *
	 * @var WPObjectType object $comment_author
	 * @since  0.0.21
	 * @access private
	 */
	private static $comment_author;

	/**
	 * Stores the comment author union type config
	 *
	 * @var WPUnionType object $comment_author_union
	 * @since  0.0.21
	 * @access private
	 */
	private static $comment_author_union;

	/**
	 * Stores the EditLock definition
	 *
	 * @var WPObjectType object $edit_lock
	 * @access private
	 */
	private static $edit_lock;

	/**
	 * Stores the mime type enum object
	 *
	 * @var WPEnumType object $mime_type_enum
	 * @since  0.5.0
	 * @access private
	 */
	private static $mime_type_enum;

	/**
	 * Stores the menu location enum type
	 *
	 * @var WPEnumType object $menu_location_enum
	 * @since  0.0.29
	 * @access private
	 */
	private static $menu_location_enum;

	/**
	 * Stores the plugin type object
	 *
	 * @var WPObjectType $plugin
	 * @since  0.5.0
	 * @access private
	 */
	private static $plugin;

	/**
	 * Stores the post object type
	 *
	 * @var PostObject $post_object
	 * @since  0.5.0
	 * @access private
	 */
	private static $post_object;

	/**
	 * Stores the post object union type config
	 *
	 * @var WPUnionType object $post_object_union
	 * @since  0.0.6
	 * @access private
	 */
	private static $post_object_union;

	/**
	 * Stores the post object field format enum type object
	 *
	 * @var WPEnumType object $post_object_field_format_enum
	 * @since  0.0.18
	 * @access private
	 */
	private static $post_object_field_format_enum;

	/**
	 * Stores the post status enum type object
	 *
	 * @var WPEnumType object $post_status_enum
	 * @since  0.0.5
	 * @access private
	 */
	private static $post_status_enum;

	/**
	 * Stores the media item (attachment) status enum type object
	 *
	 * @var WPEnumType object $media_item_status_enum
	 * @access private
	 */
	private static $media_item_status_enum;

	/**
	 * Stores the media item (attachment) size enum type object
	 *
	 * @var WPEnumType object $media_item_size_enum
	 * @access private
	 */
	private static $media_item_size_enum;

	/**
	 * Stores the post type enum type object
	 *
	 * @var WPEnumType object $post_type_enum
	 * @since  0.5.0
	 * @access private
	 */
	private static $post_type_enum;

	/**
	 * Stores the post type type object
	 *
	 * @var WPObjectType object $post_type
	 * @since  0.5.0
	 * @access private
	 */
	private static $post_type;

	/**
	 * Stores the relation enum type object
	 *
	 * @var WPEnumType object $relation_enum
	 * @since  0.5.0
	 * @access private
	 */
	private static $relation_enum;

	/**
	 * Stores the menu type
	 *
	 * @var WPObjectType object $menu
	 * @since  0.0.29
	 * @access private
	 */
	private static $menu;

	/**
	 * Stores the menu item type
	 *
	 * @var WPObjectType object $menu_item
	 * @since  0.0.29
	 * @access private
	 */
	private static $menu_item;

	/**
	 * Stores the menu item object union type
	 *
	 * @var WPUnionType object $menu_item_object_union
	 * @since  0.0.29
	 * @access private
	 */
	private static $menu_item_object_union;

	/**
	 * Stores the root mutation type object
	 *
	 * @var RootMutationType object $root_mutation
	 * @since  0.0.6
	 * @access private
	 */
	private static $root_mutation;

	/**
	 * Stores the root query type object
	 *
	 * @var RootQueryType object $root_query
	 * @since  0.5.0
	 * @access private
	 */
	private static $root_query;

	/**
	 * Stores the setting object type
	 *
	 * @var WPObjectType object $setting
	 * @access private
	 */
	private static $setting;

	/**
	 * Stores the settings object type
	 *
	 * @var WPObjectType object $settings
	 * @access private
	 */
	private static $settings;

	/**
	 * Stores the taxonomy type object
	 *
	 * @var WPObjectType object $taxonomy
	 * @since  0.5.0
	 * @access private
	 */
	private static $taxonomy;

	/**
	 * Stores the taxonomy enum type object
	 *
	 * @var WPEnumType object $taxonomy_enum
	 * @since  0.5.0
	 * @access private
	 */
	private static $taxonomy_enum;

	/**
	 * Stores the term type object
	 *
	 * @var WPObjectType object $term_object
	 * @since  0.5.0
	 * @access private
	 */
	private static $term_object;

	/**
	 * Stores the term object union definition
	 *
	 * @var WPUnionType object $term_object_union
	 * @access private
	 */
	private static $term_object_union;

	/**
	 * Stores the theme type object
	 *
	 * @var WPObjectType object $theme
	 * @since  0.5.0
	 * @access private
	 */
	private static $theme;

	/**
	 * Stores the user type object
	 *
	 * @var WPObjectType object $user
	 * @since  0.5.0
	 * @access private
	 */
	private static $user;

	/**
	 * Stores the user role type object
	 *
	 * @var WPObjectType object $user_role
	 * @since 0.0.30
	 * @access private
	 */
	private static $user_role;

	/**
	 * This returns the definition for the AvatarType
	 *
	 * @return WPObjectType object
	 * @since  0.0.5
	 * @access public
	 */
	public static function avatar() {
		return self::$avatar ? : ( self::$avatar = TypeRegistry::get_type( 'Avatar' ) );
	}

	/**
	 * This returns the definition for the CommentType
	 *
	 * @return WPObjectType object
	 * @since  0.0.5
	 * @access public
	 */
	public static function comment() {
		return self::$comment ? : ( self::$comment = TypeRegistry::get_type( 'Comment' ) );
	}

	/**
	 * This returns the definition for the CommentAuthorType
	 *
	 * @return WPObjectType object
	 * @since  0.0.21
	 * @access public
	 */
	public static function comment_author() {
		return self::$comment_author ? : ( self::$comment_author = TypeRegistry::get_type( 'CommentAuthor' ) );
	}

	/**
	 * This returns the definition for the PostObjectUnionType
	 *
	 * @return WPUnionType object
	 * @since  0.0.21
	 * @access public
	 */
	public static function comment_author_union() {
		return self::$comment_author_union ? : ( self::$comment_author_union = TypeRegistry::get_type( 'CommentAuthorUnion' ) );
	}

	/**
	 * This returns the definition for the MenuItemObjectUnionType
	 *
	 * @return WPUnionType object
	 * @since  0.0.29
	 * @access public
	 */
	public static function menu_item_object_union() {
		return self::$menu_item_object_union ? : ( self::$menu_item_object_union = TypeRegistry::get_type( 'MenuItemObjectUnion' ) );
	}

	/**
	 * This returns the definition for the EditLock type
	 *
	 * @return WPObjectType object
	 * @access public
	 */
	public static function edit_lock() {
		return self::$edit_lock ? : ( self::$edit_lock = TypeRegistry::get_type( 'EditLock' ) );
	}

	/**
	 * This returns the definition for the MimeTypeEnumType
	 *
	 * @return WPEnumType object
	 * @since  0.0.5
	 * @access public
	 */
	public static function mime_type_enum() {
		return self::$mime_type_enum ? : ( self::$mime_type_enum = TypeRegistry::get_type( 'MimeTypeEnum' ) );
	}

	/**
	 * This returns the definition for the SettingType
	 *
	 * @param string $setting_type
	 *
	 * @return Settings object
	 * @access public
	 */
	public static function setting( $setting_type ) {

		if ( null === self::$setting ) {
			self::$setting = [];
		}

		if ( empty( self::$setting[ $setting_type ] ) ) {

			self::$setting[ $setting_type ] = TypeRegistry::get_type( $setting_type . 'Settings' );
		}

		return ! empty( self::$setting[ $setting_type ] ) ? self::$setting[ $setting_type ] : null;

	}

	/**
	 * This returns the definition for the SettingsType
	 *
	 * @return WPObjectType object
	 * @access public
	 */
	public static function settings() {
		return self::$settings ? : ( self::$settings = TypeRegistry::get_type( 'Settings' ) );
	}

	/**
	 * This returns the definition for the PluginType
	 *
	 * @return WPObjectType object
	 * @since  0.0.5
	 * @access public
	 */
	public static function plugin() {
		return self::$plugin ? : ( self::$plugin = TypeRegistry::get_type( 'Plugin' ) );
	}

	/**
	 * This returns the definition for the PostObjectType
	 *
	 * @param string $post_type Name of the post type you want to retrieve the PostObjectType for
	 *
	 * @return WPObjectType object
	 * @since  0.0.5
	 * @access public
	 */
	public static function post_object( $post_type ) {

		if ( null === self::$post_object ) {
			self::$post_object = [];
		}

		if ( empty( self::$post_object[ $post_type ] ) ) {
			$post_type_object                = get_post_type_object( $post_type );
			self::$post_object[ $post_type ] = TypeRegistry::get_type( $post_type_object->graphql_single_name );
		}

		return ! empty( self::$post_object[ $post_type ] ) ? self::$post_object[ $post_type ] : null;

	}

	/**
	 * This returns the definition for the PostObjectUnionType
	 *
	 * @return WPUnionType object
	 * @since  0.0.5
	 * @access public
	 */
	public static function post_object_union() {
		return self::$post_object_union ? : ( self::$post_object_union = TypeRegistry::get_type( 'PostObjectUnion' ) );
	}

	/**
	 * This returns the definition for the PostObjectFieldFormatEnumType
	 *
	 * @return WPEnumType object
	 * @since  0.1.18
	 * @access public
	 */
	public static function post_object_field_format_enum() {
		return self::$post_object_field_format_enum ? : ( self::$post_object_field_format_enum = TypeRegistry::get_type( 'PostObjectFieldFormatEnum' ) );
	}

	/**
	 * This returns the definition for the PostStatusEnumType
	 *
	 * @return WPEnumType object
	 * @since  0.0.5
	 * @access public
	 */
	public static function post_status_enum() {
		return self::$post_status_enum ? : ( self::$post_status_enum = TypeRegistry::get_type( 'PostStatusEnum' ) );
	}

	/**
	 * This returns the definition for the MediaItemStatusEnumType
	 *
	 * @return WPEnumType object
	 * @access public
	 */
	public static function media_item_status_enum() {
		return self::$media_item_status_enum ? : ( self::$media_item_status_enum = TypeRegistry::get_type( 'MediaItemStatusEnum' ) );
	}

	/**
	 * This returns the definition for the MediaItemSizeEnumType
	 *
	 * @return WPEnumType object
	 * @access public
	 */
	public static function media_item_size_enum() {
		return self::$media_item_size_enum ? : ( self::$media_item_size_enum = TypeRegistry::get_type( 'MediaItemSizeEnum' ) );
	}

	/**
	 * This returns the definition for the MenuLocationEnumType
	 *
	 * @return WPEnumType object
	 * @since  0.0.29
	 * @access public
	 */
	public static function menu_location_enum() {
		return self::$menu_location_enum ? : ( self::$menu_location_enum = TypeRegistry::get_type( 'MenuLocationEnum' ) );
	}

	/**
	 * This returns the definition for the PostStatusEnumType
	 *
	 * @return WPEnumType object
	 * @since  0.0.5
	 * @access public
	 */
	public static function post_type_enum() {
		return self::$post_type_enum ? : ( self::$post_type_enum = TypeRegistry::get_type( 'PostTypeEnum' ) );
	}

	/**
	 * This returns the definition for the PostTypeType
	 *
	 * @return WPObjectType object
	 * @since  0.0.5
	 * @access public
	 */
	public static function post_type() {
		return self::$post_type ? : ( self::$post_type = TypeRegistry::get_type( 'PostType' ) );
	}

	/**
	 * This returns the definition for the RelationEnum
	 *
	 * @return WPEnumType object
	 * @since  0.0.5
	 * @access public
	 */
	public static function relation_enum() {
		return self::$relation_enum ? : ( self::$relation_enum = TypeRegistry::get_type( 'RelationEnum' ) );
	}

	/**
	 * This returns the definition for the Menu
	 *
	 * @return WPObjectType object
	 * @since  0.0.29
	 * @access public
	 */
	public static function menu() {
		return self::$menu ? : ( self::$menu = TypeRegistry::get_type( 'Menu' ) );
	}

	/**
	 * This returns the definition for the MenuItemType
	 *
	 * @return WPObjectType object
	 * @since  0.0.29
	 * @access public
	 */
	public static function menu_item() {
		return self::$menu_item ? : ( self::$menu_item = TypeRegistry::get_type( 'MenuItem' ) );
	}

	/**
	 * This returns the definition for the RootMutationType
	 *
	 * @return RootMutationType object
	 * @since  0.0.8
	 * @access public
	 */
	public static function root_mutation() {
		return self::$root_mutation ? : ( self::$root_mutation = TypeRegistry::get_type( 'RootMutation' ) );
	}


	/**
	 * This returns the definition for the RootQueryType
	 *
	 * @return RootQueryType object
	 * @since  0.0.5
	 * @access public
	 */
	public static function root_query() {
		return self::$root_query ? : ( self::$root_query = TypeRegistry::get_type( 'RootQuery' ) );
	}

	/**
	 * This returns the definition for the TaxonomyType
	 *
	 * @return WPObjectType object
	 * @since  0.0.5
	 * @access public
	 */
	public static function taxonomy() {
		return self::$taxonomy ? : ( self::$taxonomy = TypeRegistry::get_type( 'Taxonomy' ) );
	}

	/**
	 * This returns the definition for the TaxonomyEnumType
	 *
	 * @return WPEnumType object
	 * @since  0.0.5
	 * @access public
	 */
	public static function taxonomy_enum() {
		return self::$taxonomy_enum ? : ( self::$taxonomy_enum = TypeRegistry::get_type( 'TaxonomyEnum' ) );
	}

	/**
	 * This returns the definition for the TermObjectType
	 *
	 * @param string $taxonomy Name of the taxonomy you want to get the TermObjectType for
	 *
	 * @return TermObject object
	 * @since  0.0.5
	 * @access public
	 */
	public static function term_object( $taxonomy ) {

		if ( null === self::$term_object ) {
			self::$term_object = [];
		}

		if ( empty( self::$term_object[ $taxonomy ] ) ) {
			$taxonomy_object                = get_taxonomy( $taxonomy );
			self::$term_object[ $taxonomy ] = TypeRegistry::get_type( $taxonomy_object->graphql_single_name );
		}

		return ! empty( self::$term_object[ $taxonomy ] ) ? self::$term_object[ $taxonomy ] : null;

	}

	/**
	 * This returns the definition for the termObjectUnionType
	 *
	 * @return WPUnionType object
	 * @access public
	 */
	public static function term_object_union() {
		return self::$term_object_union ? : ( self::$term_object_union = TypeRegistry::get_type( 'TermObjectUnion' ) );
	}

	/**
	 * This returns the definition for the ThemeType
	 *
	 * @return WPObjectType object
	 * @since  0.0.5
	 * @access public
	 */
	public static function theme() {
		return self::$theme ? : ( self::$theme = TypeRegistry::get_type( 'Theme' ) );
	}

	/**
	 * This returns the definition for the UserType
	 *
	 * @return WPObjectType object
	 * @since  0.0.5
	 * @access public
	 */
	public static function user() {
		return self::$user ? : ( self::$user = TypeRegistry::get_type( 'User' ) );
	}

	/**
	 * Returns the definition for the UserRoleType
	 *
	 * @return WPObjectType
	 * @since 0.0.30
	 * @access public
	 */
	public static function user_role() {
		return self::$user_role ? : ( self::$user_role = TypeRegistry::get_type( 'UserRole' ) );
	}

	/**
	 * This is a wrapper for the GraphQL type to give a consistent experience
	 *
	 * @return \GraphQL\Type\Definition\BooleanType
	 * @since  0.0.5
	 * @access public
	 */
	public static function boolean() {
		return Type::boolean();
	}

	/**
	 * This is a wrapper for the GraphQL type to give a consistent experience
	 *
	 * @return \GraphQL\Type\Definition\FloatType
	 * @since  0.0.5
	 * @access public
	 */
	public static function float() {
		return Type::float();
	}

	/**
	 * This is a wrapper for the GraphQL type to give a consistent experience
	 *
	 * @return \GraphQL\Type\Definition\idType
	 * @since  0.0.5
	 * @access public
	 */
	public static function id() {
		return Type::id();
	}

	/**
	 * This is a wrapper for the GraphQL type to give a consistent experience
	 *
	 * @return \GraphQL\Type\Definition\IntType
	 * @since  0.0.5
	 * @access public
	 */
	public static function int() {
		return Type::int();
	}

	/**
	 * This is a wrapper for the GraphQL type to give a consistent experience
	 *
	 * @return \GraphQL\Type\Definition\StringType
	 * @since  0.0.5
	 * @access public
	 */
	public static function string() {
		return Type::string();
	}

	/**
	 * This is a wrapper for the GraphQL type to give a consistent experience
	 *
	 * @param callable $type instance of GraphQL\Type\Definition\Type or callable returning instance
	 *                       of that class
	 *
	 * @return \GraphQL\Type\Definition\ListOfType
	 * @since  0.0.5
	 * @access public
	 */
	public static function list_of( $type ) {
		return new ListOfType( $type );
	}

	/**
	 * This is a wrapper for the GraphQL type to give a consistent experience
	 *
	 * @param callable $type instance of GraphQL\Type\Definition\Type or callable returning instance
	 *                       of that class
	 *
	 * @return \GraphQL\Type\Definition\NonNull
	 * @since  0.0.5
	 * @access public
	 * @throws \Exception
	 */
	public static function non_null( $type ) {
		return new NonNull( $type );
	}

	/**
	 * Resolve the type on the individual setting field
	 * for the settingsType
	 *
	 * @param $type
	 * @access public
	 *
	 * @return \GraphQL\Type\Definition\BooleanType|\GraphQL\Type\Definition\FloatType|\GraphQL\Type\Definition\IntType|\GraphQL\Type\Definition\StringType
	 */
	public static function get_type( $type ) {
		return TypeRegistry::get_type( $type );
	}

	/**
	 * Maps new input query args and sanitizes the input
	 *
	 * @param array $args The raw query args from the GraphQL query
	 * @param array $map  The mapping of where each of the args should go
	 *
	 * @since  0.5.0
	 * @return array
	 * @access public
	 */
	public static function map_input( $args, $map ) {

		if ( ! is_array( $args ) || ! is_array( $map ) ) {
			return array();
		}

		$query_args = [];

		foreach ( $args as $arg => $value ) {

			if ( is_array( $value ) && ! empty( $value ) ) {
				$value = array_map(
					function( $value ) {
						if ( is_string( $value ) ) {
							  $value = sanitize_text_field( $value );
						}

							return $value;
					},
					$value
				);
			} elseif ( is_string( $value ) ) {
				$value = sanitize_text_field( $value );
			}

			if ( array_key_exists( $arg, $map ) ) {
				$query_args[ $map[ $arg ] ] = $value;
			} else {
				$query_args[ $arg ] = $value;
			}
		}

		return $query_args;

	}

	/**
	 * Checks the post_date_gmt or modified_gmt and prepare any post or
	 * modified date for single post output.
	 *
	 * @since 4.7.0
	 *
	 * @param string      $date_gmt GMT publication time.
	 * @param string|null $date     Optional. Local publication time. Default null.
	 * @return string|null ISO8601/RFC3339 formatted datetime.
	 */
	public static function prepare_date_response( $date_gmt, $date = null ) {
		// Use the date if passed.
		if ( isset( $date ) ) {
			return mysql_to_rfc3339( $date );
		}
		// Return null if $date_gmt is empty/zeros.
		if ( '0000-00-00 00:00:00' === $date_gmt ) {
			return null;
		}
		// Return the formatted datetime.
		return mysql_to_rfc3339( $date_gmt );
	}

}

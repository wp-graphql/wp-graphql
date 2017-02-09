<?php
namespace WPGraphQL;

use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\Type;
use WPGraphQL\Type\RootQueryType;
use WPGraphQL\Type\CommentType;
use WPGraphQL\Type\OptionType;
use WPGraphQL\Type\PluginType;
use WPGraphQL\Type\PostObjectType;
use WPGraphQL\Type\PostTypeType;
use WPGraphQL\Type\ShortcodeType;
use WPGraphQL\Type\TaxonomyType;
use WPGraphQL\Type\TermObjectType;
use WPGraphQL\Type\ThemeType;
use WPGraphQL\Type\UserType;

/**
 * Class Types
 *
 * Acts as a registry and factory for Types.
 *
 * @package WPGraphQL
 */
class Types {

	private static $root_query;
	private static $wp_comment;
	private static $wp_option;
	private static $wp_plugin;
	private static $wp_post;
	private static $wp_post_type;
	private static $wp_shortcode;
	private static $wp_taxonomy;
	private static $wp_term;
	private static $wp_theme;
	private static $wp_user;

	public static function root_query() {
		return self::$root_query ?: ( self::$root_query = new RootQueryType() );
	}

	public static function wp_comment() {
		return self::$wp_comment ?: ( self::$wp_comment = new CommentType() );
	}

	public static function wp_option() {
		return self::$wp_option ?: ( self::$wp_option = new OptionType() );
	}

	public static function wp_plugin() {
		return self::$wp_plugin ?: ( self::$wp_plugin = new PluginType() );
	}

	public static function wp_post( $post_type ) {
		return self::$wp_post->{ $post_type } ?: ( self::$wp_post->{ $post_type } = new PostObjectType( $post_type ) );
	}

	public static function wp_post_type() {
		return self::$wp_post_type ?: ( self::$wp_post_type = new PostTypeType() );
	}

	public static function wp_shortcode() {
		return self::$wp_shortcode ?: ( self::$wp_shortcode = new ShortcodeType() );
	}

	public static function wp_taxonomy() {
		return self::$wp_taxonomy ?: ( self::$wp_taxonomy = new TaxonomyType() );
	}

	public static function wp_term( $taxonomy ) {
		return self::$wp_term->{ $taxonomy } ?: ( self::$wp_term->{ $taxonomy } = new TermObjectType( $taxonomy ) );
	}

	public static function wp_theme() {
		return self::$wp_theme ?: ( self::$wp_theme = new ThemeType() );
	}

	public static function wp_user() {
		return self::$wp_user ?: ( self::$wp_user = new UserType() );
	}

	public static function boolean() {
		return Type::boolean();
	}
	/**
	 * @return \GraphQL\Type\Definition\FloatType
	 */
	public static function float() {
		return Type::float();
	}
	/**
	 * @return \GraphQL\Type\Definition\IDType
	 */
	public static function id() {
		return Type::id();
	}
	/**
	 * @return \GraphQL\Type\Definition\IntType
	 */
	public static function int() {
		return Type::int();
	}
	/**
	 * @return \GraphQL\Type\Definition\StringType
	 */
	public static function string() {
		return Type::string();
	}
	/**
	 * @param Type $type
	 * @return ListOfType
	 */
	public static function list_of( $type ) {
		return new ListOfType( $type );
	}
	/**
	 * @param Type $type
	 * @return NonNull
	 */
	public static function non_null( $type ) {
		return new NonNull( $type );
	}

}

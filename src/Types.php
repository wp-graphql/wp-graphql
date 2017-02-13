<?php
namespace WPGraphQL;

use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\Type;
use WPGraphQL\Type\AvatarType;
use WPGraphQL\Type\DateQueryType;
use WPGraphQL\Type\Enum\MimeTypeEnumType;
use WPGraphQL\Type\Enum\PostObjectOrderbyEnumType;
use WPGraphQL\Type\Enum\PostStatusEnumType;
use WPGraphQL\Type\Enum\RelationEnum;
use WPGraphQL\Type\Enum\TaxonomyEnumType;
use WPGraphQL\Type\Enum\TaxQueryFieldEnumType;
use WPGraphQL\Type\Enum\TaxQueryOperatorEnumType;
use WPGraphQL\Type\MetaQueryType;
use WPGraphQL\Type\PostObjectQueryArgsType;
use WPGraphQL\Type\RootQueryType;
use WPGraphQL\Type\CommentType;
use WPGraphQL\Type\PluginType;
use WPGraphQL\Type\PostObjectType;
use WPGraphQL\Type\PostTypeType;
use WPGraphQL\Type\ShortcodeType;
use WPGraphQL\Type\TaxonomyType;
use WPGraphQL\Type\TaxQueryType;
use WPGraphQL\Type\TermObjectType;
use WPGraphQL\Type\ThemeType;
use WPGraphQL\Type\UserType;

/**
 * Class Types
 *
 * Acts as a registry and factory for Types.
 *
 * Each "type" is static ensuring that it will only be instantiated once and can be re-used throughout the system. The
 * types that are "dynamic" (such as post_types, taxonomies, etc) are added as a sub-property to the Types class
 * based on their unique identifier, and are therefore only instantiated once as well.
 *
 * @since 0.0.5
 * @package WPGraphQL
 */
class Types {

	private static $avatar;
	private static $comment;
	private static $date_query;
	private static $meta_query;
	private static $mime_type_enum;
	private static $plugin;
	private static $post_object;
	private static $post_object_orderby_enum;
	private static $post_object_query_args;
	private static $post_status_enum;
	private static $post_type;
	private static $relation_enum;
	private static $root_query;
	private static $shortcode;
	private static $taxonomy;
	private static $taxonomy_enum;
	private static $tax_query;
	private static $tax_query_field_enum;
	private static $tax_query_operator_enum;
	private static $term_object;
	private static $theme;
	private static $user;

	/**
	 * avatar
	 * This returns the definition for the AvatarType
	 * @return AvatarType
	 * @since 0.0.5
	 */
	public static function avatar() {
		return self::$avatar ?: ( self::$avatar = new AvatarType() );
	}

	/**
	 * comment
	 * This returns the definition for the CommentType
	 * @return CommentType
	 * @since 0.0.5
	 */
	public static function comment() {
		return self::$comment ?: ( self::$comment = new CommentType() );
	}

	/**
	 * date_query
	 * This returns the definition for the dateQueryType
	 * @return dateQueryType
	 * @since 0.0.5
	 */
	public static function date_query() {
		return self::$date_query ?: ( self::$date_query = new DateQueryType() );
	}

	/**
	 * meta_query
	 * This returns the definition for the MetaQueryType
	 * @return MetaQueryType
	 * @since 0.0.5
	 */
	public static function meta_query() {
		return self::$meta_query ?: ( self::$meta_query = new MetaQueryType() );
	}

	/**
	 * mime_type_enum
	 * This returns the definition for the MimeTypeEnumType
	 * @return MimeTypeEnumType
	 * @since 0.0.5
	 */
	public static function mime_type_enum() {
		return self::$mime_type_enum ?: ( self::$mime_type_enum = new MimeTypeEnumType() );
	}

	/**
	 * plugin
	 * This returns the definition for the PluginType
	 * @return PluginType
	 * @since 0.0.5
	 */
	public static function plugin() {
		return self::$plugin ?: ( self::$plugin = new PluginType() );
	}

	/**
	 * post_object
	 * This returns the definition for the PostObjectType
	 * @return PostObjectType
	 * @param $post_type string
	 * @since 0.0.5
	 */
	public static function post_object( $post_type ) {
		return self::$post_object->{ $post_type } ?: ( self::$post_object->{ $post_type } = new PostObjectType( $post_type ) );
	}

	/**
	 * post_status_enum
	 * This returns the definition for the PostStatusEnumType
	 * @return PostStatusEnumType
	 * @since 0.0.5
	 */
	public static function post_status_enum() {
		return self::$post_status_enum ?: ( self::$post_status_enum = new PostStatusEnumType() );
	}

	/**
	 * post_object_orderby_enum
	 * This returns the definition for the PostObjectOrderbyEnumType
	 * @return PostObjectOrderbyEnumType
	 * @since 0.0.5
	 */
	public static function post_object_orderby_enum() {
		return self::$post_object_orderby_enum ?: ( self::$post_object_orderby_enum = new PostObjectOrderbyEnumType() );
	}

	/**
	 * post_object_query_args
	 * This returns the definition for the PostObjectQueryArgsType
	 * @return PostObjectQueryArgsType
	 * @since 0.0.5
	 */
	public static function post_object_query_args() {
		return self::$post_object_query_args ?: ( self::$post_object_query_args = new PostObjectQueryArgsType() );
	}

	/**
	 * post_type
	 * This returns the definition for the PostTypeType
	 * @return PostTypeType
	 * @since 0.0.5
	 */
	public static function post_type() {
		return self::$post_type ?: ( self::$post_type = new PostTypeType() );
	}

	/**
	 * relation_enum
	 * This returns the definition for the RelationEnum
	 * @return RelationEnum
	 * @since 0.0.5
	 */
	public static function relation_enum() {
		return self::$relation_enum ?: ( self::$relation_enum = new RelationEnum() );
	}

	/**
	 * root_query
	 * This returns the definition for the RootQueryType
	 * @return RootQueryType
	 * @since 0.0.5
	 */
	public static function root_query() {
		return self::$root_query ?: ( self::$root_query = new RootQueryType() );
	}

	/**
	 * shortcode
	 * This returns the definition for the ShortcodeType
	 * @return ShortcodeType
	 * @since 0.0.5
	 */
	public static function shortcode() {
		return self::$shortcode ?: ( self::$shortcode = new ShortcodeType() );
	}

	/**
	 * taxonomy
	 * This returns the definition for the TaxonomyType
	 * @return TaxonomyType
	 * @since 0.0.5
	 */
	public static function taxonomy() {
		return self::$taxonomy ?: ( self::$taxonomy = new TaxonomyType() );
	}

	/**
	 * taxonomy_enum
	 * This returns the definition for the TaxonomyEnumType
	 * @return TaxonomyEnumType
	 * @since 0.0.5
	 */
	public static function taxonomy_enum() {
		return self::$taxonomy_enum ?: ( self::$taxonomy_enum = new TaxonomyEnumType() );
	}

	/**
	 * tax_query
	 * This returns the definition for the TaxQueryType
	 * @return TaxQueryType
	 * @since 0.0.5
	 */
	public static function tax_query() {
		return self::$tax_query ?: ( self::$tax_query = new TaxQueryType() );
	}

	/**
	 * tax_query_field_enum
	 * This returns the definition for the TaxQueryFieldEnumType
	 * @return TaxQueryFieldEnumType
	 * @since 0.0.5
	 */
	public static function tax_query_field_enum() {
		return self::$tax_query_field_enum ?: ( self::$tax_query_field_enum = new TaxQueryFieldEnumType() );
	}

	/**
	 * tax_query_operator_enum
	 * This returns the definition for the TaxQueryOperatorEnumType
	 * @return TaxQueryOperatorEnumType
	 * @since 0.0.5
	 */
	public static function tax_query_operator_enum() {
		return self::$tax_query_operator_enum ?: ( self::$tax_query_operator_enum = new TaxQueryOperatorEnumType() );
	}

	/**
	 * term_object
	 * This returns the definition for the TermObjectType
	 * @return TermObjectType
	 * @param string $taxonomy
	 * @since 0.0.5
	 */
	public static function term_object( $taxonomy ) {
		return self::$term_object->{ $taxonomy } ?: ( self::$term_object->{ $taxonomy } = new TermObjectType( $taxonomy ) );
	}

	/**
	 * theme
	 * This returns the definition for the ThemeType
	 * @return ThemeType
	 * @since 0.0.5
	 */
	public static function theme() {
		return self::$theme ?: ( self::$theme = new ThemeType() );
	}

	/**
	 * user
	 * This returns the definition for the UserType
	 * @return UserType
	 * @since 0.0.5
	 */
	public static function user() {
		return self::$user ?: ( self::$user = new UserType() );
	}

	/**
	 * boolean
	 * This is a wrapper for the GraphQL type to give a consistent experience
	 * @return \GraphQL\Type\Definition\BooleanType
	 * @since 0.0.5
	 */
	public static function boolean() {
		return Type::boolean();
	}

	/**
	 * float
	 * This is a wrapper for the GraphQL type to give a consistent experience
	 * @return \GraphQL\Type\Definition\FloatType
	 * @since 0.0.5
	 */
	public static function float() {
		return Type::float();
	}

	/**
	 * id
	 * This is a wrapper for the GraphQL type to give a consistent experience
	 * @return \GraphQL\Type\Definition\idType
	 * @since 0.0.5
	 */
	public static function id() {
		return Type::id();
	}

	/**
	 * int
	 * This is a wrapper for the GraphQL type to give a consistent experience
	 * @return \GraphQL\Type\Definition\int
	 * @since 0.0.5
	 */
	public static function int() {
		return Type::int();
	}

	/**
	 * string
	 * This is a wrapper for the GraphQL type to give a consistent experience
	 * @return \GraphQL\Type\Definition\string
	 * @since 0.0.5
	 */
	public static function string() {
		return Type::string();
	}

	/**
	 * list_of
	 * This is a wrapper for the GraphQL type to give a consistent experience
	 * @return \GraphQL\Type\Definition\list_of
	 * @since 0.0.5
	 */
	public static function list_of( $type ) {
		return new ListOfType( $type );
	}

	/**
	 * non_null
	 * This is a wrapper for the GraphQL type to give a consistent experience
	 * @return \GraphQL\Type\Definition\non_null
	 * @since 0.0.5
	 */
	public static function non_null( $type ) {
		return new NonNull( $type );
	}

}

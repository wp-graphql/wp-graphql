<?php

namespace WPGraphQL\Acf;

use WPGraphQL\Acf\FieldType\ButtonGroup;
use WPGraphQL\Acf\FieldType\Checkbox;
use WPGraphQL\Acf\FieldType\CloneField;
use WPGraphQL\Acf\FieldType\ColorPicker;
use WPGraphQL\Acf\FieldType\DatePicker;
use WPGraphQL\Acf\FieldType\DateTimePicker;
use WPGraphQL\Acf\FieldType\Email;
use WPGraphQL\Acf\FieldType\File;
use WPGraphQL\Acf\FieldType\FlexibleContent;
use WPGraphQL\Acf\FieldType\Gallery;
use WPGraphQL\Acf\FieldType\GoogleMap;
use WPGraphQL\Acf\FieldType\Group;
use WPGraphQL\Acf\FieldType\Image;
use WPGraphQL\Acf\FieldType\Link;
use WPGraphQL\Acf\FieldType\Number;
use WPGraphQL\Acf\FieldType\Oembed;
use WPGraphQL\Acf\FieldType\PageLink;
use WPGraphQL\Acf\FieldType\Password;
use WPGraphQL\Acf\FieldType\PostObject;
use WPGraphQL\Acf\FieldType\Radio;
use WPGraphQL\Acf\FieldType\Range;
use WPGraphQL\Acf\FieldType\Relationship;
use WPGraphQL\Acf\FieldType\Repeater;
use WPGraphQL\Acf\FieldType\Select;
use WPGraphQL\Acf\FieldType\Taxonomy;
use WPGraphQL\Acf\FieldType\Text;
use WPGraphQL\Acf\FieldType\Textarea;
use WPGraphQL\Acf\FieldType\TimePicker;
use WPGraphQL\Acf\FieldType\TrueFalse;
use WPGraphQL\Acf\FieldType\Url;
use WPGraphQL\Acf\FieldType\User;
use WPGraphQL\Acf\FieldType\Wysiwyg;

class FieldTypeRegistry {

	/**
	 * @var array<mixed>
	 */
	protected $registered_field_types = [];

	/**
	 * Constructor.
	 */
	public function __construct() {

		// Register supported ACF Field Types
		$this->register_acf_field_types();

		// Initialize the Field Type Registry
		do_action( 'wpgraphql/acf/registry_init', $this );

		// Initialize the Field Type Registry
		do_action( 'wpgraphql/acf/register_field_types', $this );
	}


	/**
	 * Register ACF Field Types
	 */
	protected function register_acf_field_types(): void {

		// This field type is added support some legacy features of ACF versions lower than v6.1
		if ( ! defined( 'ACF_MAJOR_VERSION' ) || version_compare( ACF_MAJOR_VERSION, '6.1', '<=' ) ) {
			register_graphql_acf_field_type( '<6.1' );
		}

		ButtonGroup::register_field_type();
		Checkbox::register_field_type();
		CloneField::register_field_type();
		ColorPicker::register_field_type();
		DatePicker::register_field_type();
		DateTimePicker::register_field_type();
		Number::register_field_type();
		Email::register_field_type();
		File::register_field_type();
		FlexibleContent::register_field_type();
		Image::register_field_type();
		Gallery::register_field_type();
		GoogleMap::register_field_type();
		Group::register_field_type();
		Link::register_field_type();
		Oembed::register_field_type();
		PageLink::register_field_type();
		Password::register_field_type();
		PostObject::register_field_type();
		Radio::register_field_type();
		Range::register_field_type();
		Relationship::register_field_type();
		Repeater::register_field_type();
		Select::register_field_type();
		Taxonomy::register_field_type();
		Text::register_field_type();
		Textarea::register_field_type();
		TimePicker::register_field_type();
		TrueFalse::register_field_type();
		Url::register_field_type();
		User::register_field_type();
		Wysiwyg::register_field_type();
	}

	/**
	 * Return the registered field types, names and config in an associative array.
	 *
	 * @return array<mixed>
	 */
	public function get_registered_field_types(): array {
		return apply_filters( 'wpgraphql/acf/get_registered_field_types', $this->registered_field_types );
	}

	/**
	 * Return an array of the names of the registered field types
	 *
	 * @return array<string>
	 */
	public function get_registered_field_type_names(): array {
		return array_keys( $this->get_registered_field_types() );
	}

	/**
	 * Given an acf field type (i.e. text, textarea, etc) return the config for mapping
	 * the field type to GraphQL
	 *
	 * @param string $acf_field_type The type of field to get the config for
	 */
	public function get_field_type( string $acf_field_type ): ?AcfGraphQLFieldType {
		return $this->registered_field_types[ $acf_field_type ] ?? null;
	}

	/**
	 * Register an ACF Field Type
	 *
	 * @param string                $acf_field_type The name of the ACF Field Type to map to the GraphQL Schema
	 * @param array<mixed>|callable $config Config for mapping the ACF Field Type to the GraphQL Schema
	 */
	public function register_field_type( string $acf_field_type, $config = [] ): AcfGraphQLFieldType {
		if ( isset( $this->registered_field_types[ $acf_field_type ] ) ) {
			return $this->registered_field_types[ $acf_field_type ];
		}

		$this->registered_field_types[ $acf_field_type ] = new AcfGraphQLFieldType( $acf_field_type, $config );

		return $this->registered_field_types[ $acf_field_type ];
	}
}

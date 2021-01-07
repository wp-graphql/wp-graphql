<?php

namespace WPGraphQL\Data\Cursor;

/**
 * Generic class for building AND&OR operators for cursor based paginators
 */
class CursorBuilder {

	/**
	 * The field by which the cursor should order the results
	 *
	 * @var array
	 */
	public $fields;

	/**
	 * Default comparison operator. < or >
	 *
	 * @var string
	 */
	public $compare;

	/**
	 * CursorBuilder constructor.
	 *
	 * @param string $compare
	 *
	 * @return void
	 */
	public function __construct( $compare = '>' ) {
		$this->compare = $compare;
		$this->fields  = [];
	}

	/**
	 * Add ordering field. The order you call this method matters. First field
	 * will be the primary field and latter ones will be used if the primary
	 * field has duplicate values
	 *
	 * @param string           $key           database column
	 * @param mixed|string|int $value         value from the current cursor
	 * @param string           $type          type cast
	 * @param string           $order         custom order
	 * @param PostObjectCursor $object_cursor The PostObjectCursor class
	 *
	 * @return void
	 */
	public function add_field( string $key, $value, string $type = null, string $order = null, PostObjectCursor $object_cursor = null ) {

		/**
		 * Filters the field used for ordering when cursors are used for pagination
		 *
		 * @param array                   $field          The field key, value, type and order
		 * @param CursorBuilder           $cursor_builder The CursorBuilder class
		 * @param null | PostObjectCursor $object_cursor  The PostObjectCursor class
		 */
		$field = apply_filters(
			'graphql_cursor_ordering_field',
			[
				'key'   => esc_sql( $key ),
				'value' => esc_sql( $value ),
				'type'  => ! empty( $type ) ? esc_sql( $type ) : '',
				'order' => ! empty( $order ) ? esc_sql( $order ) : '',
			],
			$this,
			$object_cursor
		);

		// Bail if the filtered field comes back empty
		if ( empty( $field ) ) {
			return;
		}

		// Bail if the filtered field doesn't come back as an array
		if ( ! is_array( $field ) ) {
			return;
		}

		$escaped_field = [];

		// Escape the filtered array
		foreach ( $field as $key => $value ) {
			$escaped_field[ $key ] = esc_sql( $value );
		}

		$this->fields[] = $escaped_field;
	}

	/**
	 * Returns true at least one ordering field has been added
	 *
	 * @return boolean
	 */
	public function has_fields() {
		return count( $this->fields ) > 0;
	}

	/**
	 * Generate the final SQL string to be appended to WHERE clause
	 *
	 * @param mixed|array|null $fields
	 *
	 * @return string
	 */
	public function to_sql( $fields = null ) {
		if ( null === $fields ) {
			$fields = $this->fields;
		}

		if ( count( $fields ) === 0 ) {
			return '';
		}

		$field = $fields[0];

		$key   = $field['key'];
		$value = $field['value'];
		$type  = $field['type'];
		$order = $field['order'];

		$compare = $this->compare;

		if ( $order ) {
			$compare = 'DESC' === $order ? '<' : '>';
		}

		if ( 'ID' !== $type ) {
			$cast = $this->get_cast_for_type( $type );
			if ( 'CHAR' === $cast ) {
				$value = "'$value'";
			} elseif ( $cast ) {
				$key   = "CAST( $key as $cast )";
				$value = "CAST( '$value' as $cast )";
			}
		}

		if ( count( $fields ) === 1 ) {
			return " {$key} {$compare} {$value}";
		}

		$nest = $this->to_sql( \array_slice( $fields, 1 ) );

		return " {$key} {$compare}= {$value} AND ( {$key} {$compare} {$value} OR ( {$nest} ) ) ";
	}


	/**
	 * Copied from
	 * https://github.com/WordPress/WordPress/blob/c4f8bc468db56baa2a3bf917c99cdfd17c3391ce/wp-includes/class-wp-meta-query.php#L272-L296
	 *
	 * It's an instance method. No way to call it without creating the instance?
	 *
	 * Return the appropriate alias for the given meta type if applicable.
	 *
	 * @param string $type MySQL type to cast meta_value.
	 *
	 * @return string MySQL type.
	 */
	public function get_cast_for_type( $type = '' ) {
		if ( empty( $type ) ) {
			return 'CHAR';
		}
		$meta_type = strtoupper( $type );
		if ( ! preg_match( '/^(?:BINARY|CHAR|DATE|DATETIME|SIGNED|UNSIGNED|TIME|NUMERIC(?:\(\d+(?:,\s?\d+)?\))?|DECIMAL(?:\(\d+(?:,\s?\d+)?\))?)$/', $meta_type ) ) {
			return 'CHAR';
		}
		if ( 'NUMERIC' === $meta_type ) {
			$meta_type = 'SIGNED';
		}

		return $meta_type;
	}
}

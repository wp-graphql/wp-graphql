<?php

namespace WPGraphQL\Data\Cursor;

/**
 * Generic class for building AND&OR operators for cursor based paginators
 */
class CursorBuilder {

	/**
	 * The field by which the cursor should order the results
	 */
	public $fields;

	/**
	 * Default comparison operator. < or >
	 */
	public $compare = null;

	public function __construct( $compare = '>' ) {
		$this->compare = $compare;
		$this->fields  = [];
	}

	/**
	 * Add ordering field. The order you call this method matters. First field
	 * will be the primary field and latters ones will be used if the primary
	 * field has duplicate values
	 *
	 * @param string $key   database colum
	 * @param string $value value from the current cursor
	 * @param string $type  type cast
	 * @param string $order custom order
	 */
	public function add_field( $key, $value, $type = null, $order = null ) {
		/**
		 * This only input for variables which are used in the SQL generation. So
		 * escape them here.
		 */
		$this->fields[] = [
			'key'   => esc_sql( $key ),
			'value' => esc_sql( $value ),
			'type'  => esc_sql( $type ),
			'order' => esc_sql( $order ),
		];
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
	 * Generate the final SQL string to be appended to WHERE claise
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
		if ( 'NUMERIC' == $meta_type ) {
			$meta_type = 'SIGNED';
		}

		return $meta_type;
	}
}

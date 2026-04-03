<?php
/**
 * Database schema tests.
 *
 * @package WPGraphQL\PQC\Tests\WPUnit
 */

use WPGraphQL\PQC\Database\Schema;

/**
 * Class SchemaTest
 */
class SchemaTest extends \Codeception\TestCase\WPTestCase {

	/**
	 * ensure_schema is idempotent and leaves all tables present.
	 */
	public function test_ensure_schema_creates_tables(): void {
		Schema::ensure_schema();
		$this->assertTrue( Schema::table_exists(), 'PQC tables should exist after ensure_schema()' );

		Schema::ensure_schema();
		$this->assertTrue( Schema::table_exists(), 'Second ensure_schema() should be a no-op failure-wise' );
	}
}

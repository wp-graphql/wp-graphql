<?php

/**
 * Tests to ensure we don't add functions that might conflict with the old plugin's namespaced public methods
 */
class NamespaceTest extends \Codeception\TestCase\WPTestCase {


	public function testPublicMethodsDoNotExist() {

		// here we are going to assert that a method we know exists, exists (sanity check)
		$this->assertTrue( method_exists( '\WPGraphQL\Acf\Registry', 'get_type_registry' ) );

		// Here we're going to assert hat some methods that existed in the old plugin do not exist in this plugin
		// with the intent that any extending codebases possibly calling
		$this->assertFalse( class_exists( '\WPGraphQL\Acf\Acf' ) );
		$this->assertFalse( method_exists( '\WPGraphQL\Acf\Acf', 'instance' ) );
		$this->assertFalse( method_exists( '\WPGraphQL\Acf\Acf', '__clone' ) );
		$this->assertFalse( method_exists( '\WPGraphQL\Acf\Acf', '__wakeup' ) );

		$this->assertFalse( class_exists( '\WPGraphQL\Acf\ACF_Settings' ) );
		$this->assertFalse( class_exists( '\WPGraphQL\Acf\Config' ) );
		$this->assertFalse( class_exists( '\WPGraphQL\Acf\LocationRules' ) );

	}


}

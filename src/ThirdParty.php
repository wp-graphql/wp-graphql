<?php
namespace WPGraphQL\Acf;

class ThirdParty {

	/**
	 * Initialize support for 3rd party libraries / plugins
	 */
	public function init(): void {

		// initialize support for ACF Extended
		$acfe = new ThirdParty\AcfExtended\AcfExtended();
		$acfe->init();

		// Initialize support for WPGraphQL Smart Cache
		$smart_cache = new ThirdParty\WPGraphQLSmartCache\WPGraphQLSmartCache();
		$smart_cache->init();

		// Initialize support for WPGraphQL Content Blocks
		$content_blocks = new ThirdParty\WPGraphQLContentBlocks\WPGraphQLContentBlocks();
		$content_blocks->init();
	}
}

<?php
namespace Helper;

// here you can define custom actions
// all public methods declared in helper class will be available in $I

class Utils extends \Codeception\Module
{
	/**
	 * Get WPBrowser module
	 * @return \lucatume\WPBrowser\Module\WPBrowser
	 */
	protected function getWPBrowser()
	{
		return $this->getModule(\lucatume\WPBrowser\Module\WPBrowser::class);
	}

	/**
	 * Get WPDb module
	 * @return \lucatume\WPBrowser\Module\WPDb
	 */
	protected function getWPDb()
	{
		return $this->getModule(\lucatume\WPBrowser\Module\WPDb::class);
	}

    public function importJson( $json_file ) {
        // Import the json file Save and see the selection after form submit
        $this->getWPBrowser()->loginAsAdmin();
        $this->getWPBrowser()->amOnPage('/wp-admin/edit.php?post_type=acf-field-group&page=acf-tools');
        $this->getWPBrowser()->attachFile('//input[@id="acf_import_file"]', $json_file );
        $this->getWPBrowser()->submitForm('//*[@id="acf-admin-tool-import"]//*[@type="submit"]', [], 'Choose File');
    }

	/**
	 * Get the post ID of a field group by its title
	 * 
	 * @param string $title The field group title
	 * @return int|false The post ID or false if not found
	 */
	public function getFieldGroupPostId( $title ) {
		try {
			$post_id = $this->getWPDb()->grabFromDatabase(
				$this->getWPDb()->grabPrefixedTableNameFor( 'posts' ),
				'ID',
				[
					'post_title' => $title,
					'post_type' => 'acf-field-group',
					'post_status' => 'publish'
				]
			);
			return $post_id ? (int) $post_id : false;
		} catch ( \Exception $e ) {
			// If query fails, return false (fallback to slower navigation)
			return false;
		}
	}
	
	/**
	 * @return bool
	 * @throws \Codeception\Exception\ModuleException
	 */
	public function haveAcfProActive(): bool {
		$this->getWPBrowser()->loginAsAdmin();
		$this->getWPBrowser()->amOnPluginsPage();
		$active_plugins = $this->getWPDb()->grabOptionFromDatabase( 'active_plugins' );

		return in_array( 'advanced-custom-fields-pro/acf.php', $active_plugins, true  );

	}
}

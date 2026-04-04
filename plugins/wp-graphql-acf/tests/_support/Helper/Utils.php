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
	 * Get the post ID of a field group by its ACF key (more reliable than title)
	 * 
	 * @param string $key The field group key (e.g., 'group_63d2bb751c4f6')
	 * @return int|false The post ID or false if not found
	 */
	public function getFieldGroupPostIdByKey( $key ) {
		try {
			// ACF stores the field group key in the post_name field
			// This is the most reliable way to find the correct field group
			$post_id = $this->getWPDb()->grabFromDatabase(
				$this->getWPDb()->grabPrefixedTableNameFor( 'posts' ),
				'ID',
				[
					'post_name' => $key,
					'post_type' => 'acf-field-group',
					'post_status' => 'publish'
				]
			);
			if ( $post_id ) {
				return (int) $post_id;
			}
			
			// Fallback: Try postmeta (less reliable, but some ACF versions might use it)
			$possible_keys = [
				'acf_field_group_key',
				'_acf_field_group_key',
				'field_group_key',
				'_field_group_key',
			];
			
			foreach ( $possible_keys as $meta_key ) {
				try {
					$post_id = $this->getWPDb()->grabFromDatabase(
						$this->getWPDb()->grabPrefixedTableNameFor( 'postmeta' ),
						'post_id',
						[
							'meta_key' => $meta_key,
							'meta_value' => $key
						]
					);
					if ( $post_id ) {
						return (int) $post_id;
					}
				} catch ( \Exception $e ) {
					// Try next meta_key
					continue;
				}
			}
			
			return false;
		} catch ( \Exception $e ) {
			// If query fails, return false (fallback to title lookup)
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

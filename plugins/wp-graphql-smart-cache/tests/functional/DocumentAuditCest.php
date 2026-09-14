<?php
/**
 * Admin audit of automatically registered documents: the notice, the list views
 * reachable without the display setting, the settings panel, and the purge link.
 */
class DocumentAuditCest {

	public function _before( FunctionalTester $I ) {
		$I->dontHavePostInDatabase( [ 'post_type' => 'graphql_document' ] );
		$I->dontHaveTermInDatabase( [ 'taxonomy' => 'graphql_query_alias' ] );
		$I->dontHaveOptionInDatabase( 'graphql_persisted_queries_section' );
		$I->dontHaveOptionInDatabase( 'wpgraphql_smart_cache_document_audit_notice_dismissed' );
		$I->dontHaveTransientInDatabase( 'wpgraphql_smart_cache_document_audit_counts' );
	}

	private function haveSquattedDocument( FunctionalTester $I, string $alias, string $title = 'Squatted Document' ): int {
		$query = "{\n  posts {\n    nodes {\n      title\n    }\n  }\n}\n";
		$hash  = hash( 'sha256', $query );
		$I->haveTermInDatabase( $hash, 'graphql_query_alias' );
		$I->haveTermInDatabase( $alias, 'graphql_query_alias' );
		return $I->havePostInDatabase( [
			'post_type'    => 'graphql_document',
			'post_status'  => 'publish',
			'post_author'  => 0,
			'post_title'   => $title,
			'post_name'    => $hash,
			'post_content' => $query,
			'tax_input'    => [ 'graphql_query_alias' => [ $hash, $alias ] ],
		] );
	}

	public function adminSeesNoticeAndCanReviewWithoutEnablingTheDisplaySettingTest( FunctionalTester $I ) {
		$I->wantTo( 'See the audit notice and reach the filtered document list while the admin display setting is off' );

		$this->haveSquattedDocument( $I, 'homepage-posts' );

		$I->loginAsAdmin();
		$I->amOnAdminPage( 'index.php' );
		$I->see( 'carries an alias name that was not derived from its hash' );
		$I->click( 'Review documents' );

		$I->seeInCurrentUrl( 'post_type=graphql_document' );
		$I->seeInCurrentUrl( 'graphql_document_audit=unverified' );
		$I->see( 'Squatted Document' );
		$I->see( 'Unverified alias' );
		$I->see( 'homepage-posts' );
	}

	public function purgeFromSettingsPanelDeletesAutomaticDocumentsTest( FunctionalTester $I ) {
		$I->wantTo( 'Delete automatically registered documents from the settings panel' );

		$this->haveSquattedDocument( $I, 'homepage-posts' );

		$I->loginAsAdmin();
		$I->amOnAdminPage( 'admin.php?page=graphql-settings' );
		$I->see( 'Automatically Registered Documents' );
		$I->see( 'Delete automatically registered documents' );

		$I->click( 'Delete automatically registered documents' );

		$I->seeInCurrentUrl( 'graphql_document_audit_purged=1' );
		$I->see( '1 automatically registered GraphQL document was deleted.' );
		$I->dontSeePostInDatabase( [ 'post_type' => 'graphql_document' ] );
		$I->dontSeeTermInDatabase( [ 'name' => 'homepage-posts' ] );
	}

	public function purgeRequiresManageOptionsTest( FunctionalTester $I ) {
		$I->wantTo( 'Refuse the purge for a user without manage_options' );

		$this->haveSquattedDocument( $I, 'homepage-posts' );
		$I->haveUserInDatabase( 'subscriber_audit', 'subscriber', [ 'user_pass' => 'password' ] );

		$I->loginAs( 'subscriber_audit', 'password' );
		$I->amOnAdminPage( 'admin-post.php?action=wpgraphql_smart_cache_purge_automatic_documents' );
		$I->dontSee( 'was deleted' );
		$I->seePostInDatabase( [ 'post_type' => 'graphql_document', 'post_title' => 'Squatted Document' ] );
	}
}

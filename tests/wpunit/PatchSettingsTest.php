<?php

class PatchSettingsTest extends \Codeception\TestCase\WPTestCase
{
	public $subscriber;
	public $author;
	public $editor;
	public $admin;

	public function setUp()
	{
		// before
		parent::setUp();

		$this->subscriber = $this->factory->user->create( [
			'role' => 'subscriber',
		] );

		$this->author = $this->factory->user->create( [
			'role' => 'author',
		] );

		$this->editor = $this->factory->user->create( [
			'role' => 'editor',
		] );

		$this->admin = $this->factory->user->create( [
			'role' => 'administrator',
		] );
	}

	public function tearDown()
	{
		// your tear down methods here

		// then
		parent::tearDown();
	}

	// tests
	public function testPatchSettingsQuery()
	{
		$mock_options = [
			'default_pingback_flag' => 1,
			'default_comment_status' => 'open',
			'comments_notify' => 1,
			'moderation_notify' => 1,
			'comment_moderation' => true,
			'require_name_email' => 1,
			'comment_whitelist' => 1,
			'comment_max_links' => 2,
			'moderation_keys' => 'Yep\nKey\nList',
			'blacklist_keys' => 'Yep\nKey\nList',
			'show_avatars' => 1,
			'avatar_rating' => 'G',
			'avatar_default' => 'mystery',
			'close_comments_for_old_posts' => true,
			'close_comments_days_old' => 14,
			'thread_comments' => 1,
			'thread_comments_depth' => 5,
			'page_comments' => true,
			'default_comments_page' => 'newest',
			'comment_order' => 'asc',
			'comment_registration' => true,
			'show_comments_cookies_opt_in' => false,
			'thumbnail_size_w' => 150,
			'thumbnail_size_h' => 150,
			'thumbnail_crop' => 1,
			'medium_size_w' => 300,
			'medium_size_h' => 300,
			'large_size_w' => 1024,
			'large_size_h' => 1024,
			'posts_per_rss' => 10,
			'rss_use_excerpt' => true,
			'show_on_front' => 'posts',
			'page_on_front' => '1',
			'page_for_posts' => '2',
			'blog_public' => true,
			'default_email_category' => 1,
			'default_link_category' => 2,
			'permalink_structure' => '/archives/%post_id%',
			'category_base' => 'kate',
			'tag_base' => 'boggy',
			'wp_page_for_privacy_policy' => '2'
		];

		foreach ( $mock_options as $mock_option_key => $mock_value ) {
			update_option( $mock_option_key, $mock_value );
		}
		
		$query  = "
			query {
				discussionSettings{
					defaultPingbackFlag
					defaultCommentStatus
					commentsNotify
					moderationNotify
					commentModeration
					requireNameEmail
					commentWhitelist
					commentMaxLinks
					moderationKeys
					blacklistKeys
					showAvatars
					avatarRating
					avatarDefault
					closeCommentsForOldPosts
					closeCommentsDaysOld
					threadComments
					threadCommentsDepth
					pageComments
					defaultCommentsPage
					commentOrder
					commentRegistration
					showCommentsCookiesOptIn
				}
				mediaSettings {
					thumbnailSizeWidth
					thumbnailSizeHeight
					thumbnailCrop
					mediumSizeWidth
					mediumSizeHeight
					largeSizeWidth
					largeSizeHeight
				}
				permalinkSettings{
					structure
					categoryBase
					tagBase
				}
				privacySettings{
					page
				}
				readingSettings {
					postsPerRss
					rssUseExcerpt
					showOnFront
					pageOnFront
					pageForPosts
					blogPublic
				}
				writingSettings {
					defaultEmailCategory
					defaultLinkCategory
				}
			}
		";
		$actual = do_graphql_request( $query );

		$expected = array(
			'data' => array(
				'discussionSettings' => array(
					'defaultPingbackFlag' => 1,
					'defaultCommentStatus' => 'open',
					'commentsNotify' => 1,
					'moderationNotify' => 1,
					'commentModeration' => true,
					'requireNameEmail' => 1,
					'commentWhitelist' => 1,
					'commentMaxLinks' => 2,
					'moderationKeys' => 'Yep\nKey\nList',
					'blacklistKeys' => 'Yep\nKey\nList',
					'showAvatars' => 1,
					'avatarRating' => 'G',
					'avatarDefault' => 'mystery',
					'closeCommentsForOldPosts' => true,
					'closeCommentsDaysOld' => 14,
					'threadComments' => 1,
					'threadCommentsDepth' => 5,
					'pageComments' => true,
					'defaultCommentsPage' => 'newest',
					'commentOrder' => 'asc',
					'commentRegistration' => true,
					'showCommentsCookiesOptIn' => null,
				),
				'mediaSettings' => array(
					'thumbnailSizeWidth' => 150,
					'thumbnailSizeHeight' => 150,
					'thumbnailCrop' => 1,
					'mediumSizeWidth' => 300,
					'mediumSizeHeight' => 300,
					'largeSizeWidth' => 1024,
					'largeSizeHeight' => 1024,
				),
				'permalinkSettings' => array(
					'structure' => '/archives/%post_id%',
					'categoryBase' => 'kate',
					'tagBase' => 'boggy'
				),
				'privacySettings' => array(
					'page' => '2'
				),
				'readingSettings' => array(
					'postsPerRss' => 10,
					'rssUseExcerpt' => '1',
					'showOnFront' => 'posts',
					'pageOnFront' => '1',
					'pageForPosts' => '2',
					'blogPublic' => true,
				),
				'writingSettings' => array(
					'defaultEmailCategory' => 1,
					'defaultLinkCategory' => 2,
				)
			)
		);

		/**
		 * use --debug flag to view
		 */
		\Codeception\Util\Debug::debug( $actual );

		/**
		 * Compare the actual output vs the expected output
		 */
		$this->assertEquals( $actual, $expected );
	}

	public function testPatchSettingsMutation()
	{
		$updateSettingsInput = array(
			'input' => array(
				'clientMutationId'                              => 'someId',
				'discussionSettingsDefaultPingbackFlag'         => true,
				'discussionSettingsDefaultCommentStatus'        => 'close',
				'discussionSettingsCommentsNotify'              => true,
				'discussionSettingsModerationNotify'            => true,
				'discussionSettingsCommentModeration'           => true,
				'discussionSettingsRequireNameEmail'            => true,
				'discussionSettingsCommentWhitelist'            => true,
				'discussionSettingsCommentMaxLinks'             => 4,
				'discussionSettingsModerationKeys'              => '',
				'discussionSettingsBlacklistKeys'               => '',
				'discussionSettingsShowAvatars'                 => false,
				'discussionSettingsAvatarRating'                => 'X',
				'discussionSettingsAvatarDefault'               => 'blank',
				'discussionSettingsCloseCommentsForOldPosts'    => false,
				'discussionSettingsCloseCommentsDaysOld'        => 7,
				'discussionSettingsThreadComments'              => true,
				'discussionSettingsThreadCommentsDepth'         => 2,
				'discussionSettingsPageComments'                => true,
				'discussionSettingsDefaultCommentsPage'         => 'oldest', 
				'discussionSettingsCommentOrder'                => 'desc',
				'discussionSettingsCommentRegistration'         => false,
				'discussionSettingsShowCommentsCookiesOptIn'    => false,
				'mediaSettingsThumbnailSizeWidth'               => 256,
				'mediaSettingsThumbnailSizeHeight'              => 256,
				'mediaSettingsThumbnailCrop'                    => true,
				'mediaSettingsMediumSizeWidth'                  => 512,
				'mediaSettingsMediumSizeHeight'                 => 512,
				'mediaSettingsLargeSizeWidth'                   => 1024,
				'mediaSettingsLargeSizeHeight'                  => 1024,
				'permalinkSettingsStructure'                    => '/%year%/%monthnum%/%postname%/',
				'permalinkSettingsCategoryBase'                 => 'louie',
				'permalinkSettingsTagBase'                      => 'duck',
				'privacySettingsPage'                           => '1',
				'readingSettingsPostsPerRss'                    => 5,
				'readingSettingsRssUseExcerpt'                  => '0',
				'readingSettingsShowOnFront'                    => 'page',
				'readingSettingsPageOnFront'                    => '2',
				'readingSettingsPageForPosts'                   => '1',
				'readingSettingsBlogPublic'                     => false,
				'writingSettingsDefaultEmailCategory'           => 2,
				'writingSettingsDefaultLinkCategory'            => 1
			)
		);

		$mutation = '
			mutation updateSettings( $input: UpdateSettingsInput! ) {
				updateSettings( input: $input ) {
					clientMutationId
					allSettings{
						discussionSettingsDefaultPingbackFlag
						discussionSettingsDefaultCommentStatus
						discussionSettingsCommentsNotify
						discussionSettingsModerationNotify
						discussionSettingsCommentModeration
						discussionSettingsRequireNameEmail
						discussionSettingsCommentWhitelist
						discussionSettingsCommentMaxLinks
						discussionSettingsModerationKeys
						discussionSettingsBlacklistKeys
						discussionSettingsShowAvatars
						discussionSettingsAvatarRating
						discussionSettingsAvatarDefault
						discussionSettingsCloseCommentsForOldPosts
						discussionSettingsCloseCommentsDaysOld
						discussionSettingsThreadComments
						discussionSettingsThreadCommentsDepth
						discussionSettingsPageComments
						discussionSettingsDefaultCommentsPage
						discussionSettingsCommentOrder
						discussionSettingsCommentRegistration
						discussionSettingsShowCommentsCookiesOptIn
						mediaSettingsThumbnailSizeWidth
						mediaSettingsThumbnailSizeHeight
						mediaSettingsThumbnailCrop
						mediaSettingsMediumSizeWidth
						mediaSettingsMediumSizeHeight
						mediaSettingsLargeSizeWidth
						mediaSettingsLargeSizeHeight
						permalinkSettingsStructure
						permalinkSettingsCategoryBase
						permalinkSettingsTagBase
						privacySettingsPage
						readingSettingsPostsPerRss
						readingSettingsRssUseExcerpt
						readingSettingsShowOnFront
						readingSettingsPageOnFront
						readingSettingsPageForPosts
						readingSettingsBlogPublic
						writingSettingsDefaultEmailCategory
						writingSettingsDefaultLinkCategory
					}
				}
			}
		';

		/**
		 * Set the current user as the subscriber so we can test, and expect to fail
		 */
		wp_set_current_user( $this->subscriber );

		$actual = do_graphql_request( $mutation, 'updateSettings', $updateSettingsInput );

		/**
		 * use --debug flag to view
		 */
		\Codeception\Util\Debug::debug( $actual );

		$this->assertArrayHasKey( 'errors', $actual );

		/**
		 * Set the current user as the admin so we can test, and expect to pass
		 */
		wp_set_current_user( $this->admin );
		
		$actual = do_graphql_request( $mutation, 'updateSettings', $updateSettingsInput );

		$expected = array(
			'data' => array(
				'updateSettings' => array(
					'clientMutationId' => 'someId',
					'allSettings' => array(
						'discussionSettingsDefaultPingbackFlag' => true,
						'discussionSettingsDefaultCommentStatus' => 'close',
						'discussionSettingsCommentsNotify' => true,
						'discussionSettingsModerationNotify' => true,
						'discussionSettingsCommentModeration' => true,
						'discussionSettingsRequireNameEmail' => true,
						'discussionSettingsCommentWhitelist' => true,
						'discussionSettingsCommentMaxLinks' => 4,
						'discussionSettingsModerationKeys' => '',
						'discussionSettingsBlacklistKeys' => '',
						'discussionSettingsShowAvatars' => false,
						'discussionSettingsAvatarRating' => 'X',
						'discussionSettingsAvatarDefault' => 'blank',
						'discussionSettingsCloseCommentsForOldPosts' => false,
						'discussionSettingsCloseCommentsDaysOld' => 7,
						'discussionSettingsThreadComments' => true,
						'discussionSettingsThreadCommentsDepth' => 2,
						'discussionSettingsPageComments' => true,
						'discussionSettingsDefaultCommentsPage' => 'oldest', 
						'discussionSettingsCommentOrder' => 'desc',
						'discussionSettingsCommentRegistration' => false,
						'discussionSettingsShowCommentsCookiesOptIn' => false,
						'mediaSettingsThumbnailSizeWidth' => 256,
						'mediaSettingsThumbnailSizeHeight' => 256,
						'mediaSettingsThumbnailCrop' => true,
						'mediaSettingsMediumSizeWidth' => 512,
						'mediaSettingsMediumSizeHeight' => 512,
						'mediaSettingsLargeSizeWidth' => 1024,
						'mediaSettingsLargeSizeHeight' => 1024,
						'permalinkSettingsStructure' => '/%year%/%monthnum%/%postname%/',
						'permalinkSettingsCategoryBase' => 'louie',
						'permalinkSettingsTagBase' => 'duck',
						'privacySettingsPage' => '1',
						'readingSettingsPostsPerRss' => 5,
						'readingSettingsRssUseExcerpt' => '',
						'readingSettingsShowOnFront' => 'page',
						'readingSettingsPageOnFront' => '2',
						'readingSettingsPageForPosts' => '1',
						'readingSettingsBlogPublic' => false,
						'writingSettingsDefaultEmailCategory' => 2,
						'writingSettingsDefaultLinkCategory' => 1
					)
				)
			)
		);

		/**
		 * use --debug flag to view
		 */
		\Codeception\Util\Debug::debug( $actual );

		/**
		 * Compare the actual output vs the expected output
		 */
		$this->assertEquals( $actual, $expected );
	}
}
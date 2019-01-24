<?php

class PatchSettingsTest extends \Codeception\TestCase\WPTestCase
{

    public function setUp()
    {
        // before
        parent::setUp();

        // your set up methods here
    }

    public function tearDown()
    {
        // your tear down methods here

        // then
        parent::tearDown();
    }

    // tests
    public function testSettingsPatch()
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
                    thumbnailSizeW,
                    thumbnailSizeH,
                    thumbnailCrop
                    mediumSizeW
                    mediumSizeH
                    largeSizeW
                    largeSizeH
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
                    'thumbnailSizeW' => 150,
                    'thumbnailSizeH' => 150,
                    'thumbnailCrop' => 1,
                    'mediumSizeW' => 300,
                    'mediumSizeH' => 300,
                    'largeSizeW' => 1024,
                    'largeSizeH' => 1024,
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

}
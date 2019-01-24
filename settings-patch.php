<?php
	/**
	 * Registers WordPress options not loaded by Settings API by default 
	 */
	function patch_whitelist_options() {
		$whitelist_options = array(
			'discussion' => array(
				'default_pingback_flag' => array(
					'show_in_rest' => true,
					'type' => 'boolean',
					'description' => __( 'Attempt to notify any blogs linked to from the article', 'wp-graphql' )
				),
				'comments_notify' => array(
					'show_in_rest' => true,
					'type' => 'boolean',
					'description' => __( 'Anyone posts a comment', 'wp-graphql' )
				),
				'moderation_notify' => array(
					'show_in_rest' => true,
					'type' => 'boolean',
					'description' => __( 'A comment is held for moderation', 'wp-graphql' )
				),
				'comment_moderation' => array(
					'show_in_rest' => true,
					'type' => 'boolean',
					'description' => __( 'Comment must be manually approved', 'wp-graphql' )
				),
				'require_name_email' => array(
					'show_in_rest' => true,
					'type' => 'boolean',
					'description' => __( 'Comment author must fill out name and email', 'wp-graphql' )
				),
				'comment_whitelist' => array(
					'show_in_rest' => true,
					'type' => 'boolean',
					'description' => __( 'Comment author must have a previously approved comment', 'wp-graphql' )
				),
				'comment_max_links' => array(
					'show_in_rest' => true,
					'type' => 'integer',
					'description' => __( 'The number of links necessary for a comment to be queued. (For protection against spam comments)', 'wp-graphql' )
				),
				'moderation_keys' => array(
					'show_in_rest' => true,
					'type' => 'string',
					'description' => __( 'When a comment contains any of these words in its content, name, URL, email, or IP address, it will be held in the moderation queue. One word or IP address per line. It will match inside words, so “press” will match “WordPress”.', 'wp-graphql' )
				),
				'blacklist_keys' => array(
					'show_in_rest' => true,
					'type' => 'string',
					'description' => __( 'When a comment contains any of these words in its content, name, URL, email, or IP address, it will be put in the trash. One word or IP address per line. It will match inside words, so “press” will match “WordPress”.', 'wp-graphql' )
				),
				'show_avatars' => array(
					'show_in_rest' => true,
					'type' => 'boolean',
					'description' => __( 'Show Avatars', 'wp-graphql' )
				),
				'avatar_rating' => array(
					'show_in_rest' => array(
						'schema' => array(
							'enum' => array( 'G', 'PG', 'R', 'X' ),
						),
					),
					'type' => 'string',
					'description' => __( 'Avatar content rating', 'wp-graphql' )
				),
				'avatar_default' => array(
					'show_in_rest' => array(
						'schema' => array(
							'enum' => array(
								'mystery',
								'blank',
								'gravatar_default',
								'identicon',
								'wavatar',
								'monsterid',
								'retro'
							),
						),
					),
					'type' => 'string',
					'description' => __( 'For users without a custom avatar of their own, you can either display a generic logo or a generated one based on their email address', 'wp-graphql' )
				),
				'close_comments_for_old_posts' => array(
					'show_in_rest' => true,
					'type' => 'boolean',
					'description' => __( 'Automatically close comments on articles older than a number of days', 'wp-graphql' )
				),
				'close_comments_days_old' => array(
					'show_in_rest' => true,
					'type' => 'integer',
					'description' => __( 'The number of days required for automatically closing the comments on an article', 'wp-graphql' )
				),
				'thread_comments' => array(
					'show_in_rest' => true,
					'type' => 'boolean',
					'description' => __( 'Enable threaded (nested) comments', 'wp-graphql' ),
					'default' => false
				),
				'thread_comments_depth' => array(
					'show_in_rest' => true,
					'type' => 'integer',
					'description' => __( 'Maximum level of comment thread depth', 'wp-graphql' )
				),
				'page_comments' => array(
					'show_in_rest' => true,
					'type' => 'boolean',
					'description' => __( 'Break comments into pages', 'wp-graphql' ),
					'default' => false
				),
				'comments_per_page' => array(
					'show_in_rest' => true,
					'type' => 'integer',
					'description' => __( 'Number of top level comments per page', 'wp-graphql' )
				),
				'default_comments_page' => array(
					'show_in_rest' => array(
						'schema' => array(
							'enum' => array( 'newest', 'oldest' ),
						),
					),
					'type' => 'string',
					'description' => __( 'Comment page displayed by default', 'wp-graphql' )
				),
				'comment_order' => array(
					'show_in_rest' => array(
						'schema' => array(
							'enum' => array( 'asc', 'desc' ),
						),
					),
					'type' => 'string',
					'description' => __( 'Order comments are displayed in', 'wp-graphql' )
				),
				'comment_registration' => array(
					'show_in_rest' => true,
					'type' => 'boolean',
					'description' => __( 'Users must be registered and logged in to comment', 'wp-graphql' ),
					'default' => false
				),
				'show_comments_cookies_opt_in' => array(
					'show_in_rest' => true,
					'type' => 'boolean',
					'description' => __( 'Show comments cookies opt-in checkbox', 'wp-graphql' )
				),
			),
			'media' => array(
				'thumbnail_size_w' => array(
					'show_in_rest' => true,
					'type' => 'integer',
					'description' => __( 'Maximum width for thumbnail-sized content in pixels to use when adding an image to the Media Library', 'wp-graphql' )
				),
				'thumbnail_size_h' => array(
					'show_in_rest' => true,
					'type' => 'integer',
					'description' => __( 'Maximum height for thumbnail-sized content in pixels to use when adding an image to the Media Library', 'wp-graphql' )
				),
				'thumbnail_crop' => array(
					'show_in_rest' => true,
					'type' => 'boolean',
					'description' => __( 'Crop thumbnail to exact dimensions (normally thumbnails are proportional)', 'wp-graphql' )
				),
				'medium_size_w' => array(
					'show_in_rest' => true,
					'type' => 'integer',
					'description' => __( 'Maximum width for medium-sized content in pixels to use when adding an image to the Media Library', 'wp-graphql' )
				),
				'medium_size_h' => array(
					'show_in_rest' => true,
					'type' => 'integer',
					'description' => __( 'Maximum height for medium-sized content in pixels to use when adding an image to the Media Library', 'wp-graphql' )
				),
				'large_size_w' => array(
					'show_in_rest' => true,
					'type' => 'integer',
					'description' => __( 'Maximum width for large-sized content in pixels to use when adding an image to the Media Library', 'wp-graphql' )
				),
				'large_size_h' => array(
					'show_in_rest' => true,
					'type' => 'integer',
					'description' => __( 'Maximum height for large-sized content in pixels to use when adding an image to the Media Library', 'wp-graphql' )
				),
				/**
				 * TODO: Get more info on these three options
				 */
				// 'image_default_size' => array(
				// 	'show_in_rest',
				// 	'type',
				// 	'description'
				// ),
				// 'image_default_align' => array(
				// 	'show_in_rest',
				// 	'type',
				// 	'description'
				// ),
				// 'image_default_link_type' => array(
				// 	'show_in_rest',
				// 	'type',
				// 	'description'
				// ),
				'uploads_use_yearmonth_folders' => array(
					'show_in_rest' => true,
					'type' => 'boolean',
					'description' => __( 'Organize my uploads into month- and year-based folders', 'wp-graphql' )
				)
			),
			'permalink' => array(
				'permalink_structure' => array(
					'show_in_rest' => array(
						'name' => 'structure'	
					),
					'type' => 'string',
					'description' => __( 'Custom URL structures use for posts', 'wp-graphql' )
				),
				'category_base' => array(
					'show_in_rest' => true,
					'type' => 'string',
					'description' => __( 'Base Category URLs', 'wp-graphql' )
				),
				'tag_base' => array(
					'show_in_rest' => true,
					'type' => 'string',
					'description' => __( 'Base Tag URLs', 'wp-graphql' )
				),
			),
			'privacy' => array(
				'wp_page_for_privacy_policy' => array(
					'show_in_rest' => array(
						'name' => 'page'
					),
					'type' => 'string',
					'description' => __( 'WP Page ID of Privacy Policy page', 'wp-graphql' )
				),
			),
			'reading'    => array(
				'posts_per_rss' => array(
					'show_in_rest' => true,
					'type' => 'integer',
					'description' => __( 'Number of most recent syndication feeds shown per page', 'wp-graphql' )
				),
				'rss_use_excerpt' => array(
					'show_in_rest' => array(
						'schema' => array(
							'enum' => array( '0', '1' ),
						),
					),
					'type' => 'string',
					'description' => __( 'For each article in a feed, show "full text (0)" or "summary (1)"', 'wp-graphql' )
				),
				'show_on_front' => array(
					'show_in_rest' => array(
						'schema' => array(
							'enum' => array( 'posts', 'page' ),
						),
					),
					'type' => 'string',
					'description' => __( 'Your homepage displays', 'wp-graphql' )
				),
				'page_on_front' => array(
					'show_in_rest' => true,
					'type' => 'string',
					'description' => __( 'WP Page ID of homepage', 'wp-graphql' )
				),
				'page_for_posts' => array(
					'show_in_rest' => true,
					'type' => 'string',
					'description' => __( 'WP Page ID of blog page', 'wp-graphql' )
				),
				'blog_public' => array(
					'show_in_rest' => true,
					'type' => 'boolean',
					'description' => __( 'Discourage search engines from indexing this site', 'wp-graphql' )
				),
			),
			'writing'    => array(
				'default_email_category' => array(
					'show_in_rest' => true,
					'type' => 'integer',
					'description' => __( 'WP ID of default mail category', 'wp-graphql' )
				),
				'default_link_category' => array(
					'show_in_rest' => true,
					'type' => 'integer',
					'description' => __( 'WP ID of default link category', 'wp-graphql' )
				),
			),
		);

		/**
		 * Loop through settings.
		 */
		foreach( $whitelist_options as $group_name => $group_settings ) {
			foreach( $group_settings as $setting_name => $setting_args ) {
				register_setting( $group_name, $setting_name, $setting_args );
			}
		}
		
	}

	add_action( 'graphql_init', 'patch_whitelist_options', 1 );
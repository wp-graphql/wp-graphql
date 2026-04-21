<!--
Migrated from WordPress (CodeSnippet).
Edit freely. Re-run migrate with --force=true to overwrite this file.
-->
---
title: "Add WP Admin Color Schemes to WPGraphQL"
wordpressUri: "/recipes/add-wp-admin-color-schemes-to-wpgraphql/"
wordpressId: "2437"
group: "WP Admin"
summary: "The following example code allows you to add WP Admin Color Schemes data to WPGraphQL. add_action( 'graphql_register_types', function() { $mock_colors = ' { \"fresh\":{ \"name\":\"Default\", \"url\":false, \"colors\":[ \"#222\", \"#3…"
---

The following example code allows you to add WP Admin Color Schemes data to WPGraphQL.

```
add_action( 'graphql_register_types', function() {

	$mock_colors = '
		{
		   "fresh":{
		      "name":"Default",
		      "url":false,
		      "colors":[
		         "#222",
		         "#333",
		         "#0073aa",
		         "#00a0d2"
		      ],
		      "icon_colors":{
		         "base":"#a0a5aa",
		         "focus":"#00a0d2",
		         "current":"#fff"
		      }
		   },
		   "light":{
		      "name":"Light",
		      "url":"http://acf2.local/wp-admin/css/colors/light/colors.min.css",
		      "colors":[
		         "#e5e5e5",
		         "#999",
		         "#d64e07",
		         "#04a4cc"
		      ],
		      "icon_colors":{
		         "base":"#999",
		         "focus":"#ccc",
		         "current":"#ccc"
		      }
		   },
		   "blue":{
		      "name":"Blue",
		      "url":"http://acf2.local/wp-admin/css/colors/blue/colors.min.css",
		      "colors":[
		         "#096484",
		         "#4796b3",
		         "#52accc",
		         "#74B6CE"
		      ],
		      "icon_colors":{
		         "base":"#e5f8ff",
		         "focus":"#fff",
		         "current":"#fff"
		      }
		   },
		   "midnight":{
		      "name":"Midnight",
		      "url":"http://acf2.local/wp-admin/css/colors/midnight/colors.min.css",
		      "colors":[
		         "#25282b",
		         "#363b3f",
		         "#69a8bb",
		         "#e14d43"
		      ],
		      "icon_colors":{
		         "base":"#f1f2f3",
		         "focus":"#fff",
		         "current":"#fff"
		      }
		   },
		   "sunrise":{
		      "name":"Sunrise",
		      "url":"http://acf2.local/wp-admin/css/colors/sunrise/colors.min.css",
		      "colors":[
		         "#b43c38",
		         "#cf4944",
		         "#dd823b",
		         "#ccaf0b"
		      ],
		      "icon_colors":{
		         "base":"#f3f1f1",
		         "focus":"#fff",
		         "current":"#fff"
		      }
		   },
		   "ectoplasm":{
		      "name":"Ectoplasm",
		      "url":"http://acf2.local/wp-admin/css/colors/ectoplasm/colors.min.css",
		      "colors":[
		         "#413256",
		         "#523f6d",
		         "#a3b745",
		         "#d46f15"
		      ],
		      "icon_colors":{
		         "base":"#ece6f6",
		         "focus":"#fff",
		         "current":"#fff"
		      }
		   },
		   "ocean":{
		      "name":"Ocean",
		      "url":"http://acf2.local/wp-admin/css/colors/ocean/colors.min.css",
		      "colors":[
		         "#627c83",
		         "#738e96",
		         "#9ebaa0",
		         "#aa9d88"
		      ],
		      "icon_colors":{
		         "base":"#f2fcff",
		         "focus":"#fff",
		         "current":"#fff"
		      }
		   },
		   "coffee":{
		      "name":"Coffee",
		      "url":"http://acf2.local/wp-admin/css/colors/coffee/colors.min.css",
		      "colors":[
		         "#46403c",
		         "#59524c",
		         "#c7a589",
		         "#9ea476"
		      ],
		      "icon_colors":{
		         "base":"#f3f2f1",
		         "focus":"#fff",
		         "current":"#fff"
		      }
		   }
		}
	';

	register_graphql_object_type( 'AdminIconColor', [
		'fields' => [
			'base'    => [
				'type' => 'String'
			],
			'focus'   => [
				'type' => 'String',
			],
			'current' => [
				'type' => 'String',
			],
		],
	] );

	register_graphql_object_type( 'AdminColorPalette', [
		'description' => __( 'todo', 'wp-graphql' ),
		'fields' => [
			'primary' => [
				'type' => 'String',
				'resolve' => function( $colors ) {
					return $colors[0];
				}
			],
			'secondary' => [
				'type' => 'String',
				'resolve' => function( $colors ) {
					return $colors[1];
				}
			],
			'tertiary' => [
				'type' => 'String',
				'resolve' => function( $colors ) {
					return $colors[2];
				}
			],
			'quaternary' => [
				'type' => 'String',
				'resolve' => function( $colors ) {
					return $colors[3];
				}
			],
		],
	]);

	register_graphql_object_type( 'AdminColorScheme', [
		'fields' => [
			'name'           => [
				'type'        => 'String',
				'description' => __( 'todo', 'wp-graphql' ),
			],
			'colors' => [
				'type' => 'AdminColorPalette',
				'description' => __( 'todo', 'wp-graphql' ),
			],
			'colorsList'     => [
				'type'        => [
					'list_of' => 'String',
				],
				'description' => __( 'todo', 'wp-graphql' ),
				'resolve' => function( $admin_colors ) {
					return ! empty( $admin_colors->colors ) ? $admin_colors->colors : null;
				},
			],
			'iconColorsList' => [
				'type' => [
					'list_of' => 'String',
				],
				'resolve' => function($admin_colors  ) {

					$colors = [];
					if ( ! empty( $admin_colors->icon_colors ) ) {
						foreach ( $admin_colors->icon_colors as $key => $color ) {
							$colors[] = $color;
						}
					}

					return ! empty( $colors ) ? $colors : null;
				},

			],
			'iconColors'     => [
				'type' => 'AdminIconColor',
				'resolve' => function($admin_colors  ) {
					return ! empty( $admin_colors->icon_colors ) ? $admin_colors->icon_colors : null;
				},
			]
		]
	] );

	register_graphql_field( 'User', 'selectedAdminColorScheme', [
		'type' => 'AdminColorScheme',
		'resolve' => function( \WPGraphQL\Model\User $user ) use ( $mock_colors ) {
			$encoded = json_decode( $mock_colors );

			$colors = [];

			foreach ($encoded as $key => $value) {
				$value->key = $key;
				$colors[$key] = $value;
			}

			$color = get_user_meta( $user->userId, 'admin_color', true );
			return isset( $colors[ $color ] ) ? $colors[ $color ] : $colors[ 'fresh' ];
		}
	] );

	register_graphql_field( 'RootQuery', 'allAdminColorSchemes', [
		'description' => __( 'todo', 'wp-graphql' ),
		'type'        => [
			'list_of' => 'AdminColorScheme',
		],
		'resolve'     => function() use ( $mock_colors ) {

			$encoded = json_decode( $mock_colors );

			$colors = [];

			foreach ($encoded as $key => $value) {
				$value->key = $key;
				$colors[$key] = $value;
			}

			return $colors;

			// NEED TO FIGURE OUT HOW TO GET THESE VALUES WHEN WE'RE NOT IN THE ADMIN
//			require_once( ABSPATH . 'wp-admin/includes/misc.php' );
//			require_once( ABSPATH . 'wp-includes/general-template.php' );
//			global $_wp_admin_css_colors;
//
//			wp_send_json( $_wp_admin_css_colors );
		}
	] );

} );
```

<?php
/**
 * Registry of WPGraphQL Extensions
 *
 * @todo This will eventually be replaced with a server registry.
 *
 * @package WPGraphQL\Admin\Extensions
 * @since 1.30.0
 */

namespace WPGraphQL\Admin\Extensions;

/**
 * Class Registry
 *
 * phpcs:disable -- For phpstan type hinting
 * @phpstan-type ExtensionAuthor array{
 *  name: string,
 *  homepage?: string,
 * }
 * @phpstan-type Extension array{
 *  name: non-empty-string,
 *  description: non-empty-string,
 *  plugin_url: non-empty-string,
 *  support_url: non-empty-string,
 *  documentation_url: non-empty-string,
 *  repo_url?: string,
 *  author: ExtensionAuthor,
 * }
 * phpcs:enable
 */
final class Registry {
	/**
	 * Gets the registry of WPGraphQL Extensions.
	 *
	 * @see docs/submit-extensions.md for more information on how to submit an extension.
	 *
	 * Fields:
	 * - name: Required. The name of the extension.
	 * - description: Required. A description of the extension.
	 * - plugin_url: Required. The URL to the plugin.
	 * - repo_url: Optional. The URL to the repository for the plugin.
	 * - support_url: Required. The URL to the support page for the plugin.
	 * - documentation_url: Required. The URL to the documentation for the plugin.
	 * - author: Required. An array with the following fields:
	 *   - name: Required. The name of the author.
	 *   - homepage: Optional. The URL to the author's homepage.
	 *
	 * Array keys are solely used to sort the array and prevent merge conflicts when diffing. They should be unique.
	 *
	 * @return array<string,Extension>
	 */
	public static function get_extensions(): array {
		return [
			'wp-graphql/wpgraphql-ide'           => [
				'name'              => 'WPGraphQL IDE',
				'description'       => 'GraphQL IDE for WPGraphQL',
				'documentation_url' => 'https://github.com/wp-graphql/wpgraphql-ide',
				'plugin_url'        => 'https://wordpress.org/plugins/wpgraphql-ide/',
				'support_url'       => 'https://github.com/wp-graphql/wpgraphql-ide/issues/new/choose',
				'author'            => [
					'name'     => 'WPGraphQL',
					'homepage' => 'https://wpgraphql.com',
				],
			],
			'wp-graphql/wp-graphql-smart-cache'  => [
				'name'              => 'WPGraphQL Smart Cache',
				'description'       => 'A smart cache for WPGraphQL that caches only the data you need.',
				'documentation_url' => 'https://github.com/wp-graphql/wp-graphql-smart-cache',
				'plugin_url'        => 'https://wordpress.org/plugins/wp-graphql-smart-cache/',
				'support_url'       => 'https://github.com/wp-graphql/wp-graphql-smart-cache/issues/new/choose',
				'author'            => [
					'name'     => 'WPGraphQL',
					'homepage' => 'https://wpgraphql.com',
				],
			],
			'wp-graphql/wpgraphql-acf'           => [
				'name'              => 'WPGraphQL for Advanced Custom Fields',
				'description'       => 'WPGraphQL for ACF is a FREE, open source WordPress plugin that exposes ACF Field Groups and Fields to the WPGraphQL Schema, enabling powerful decoupled solutions with modern frontends.',
				'documentation_url' => 'https://acf.wpgraphql.com/',
				'plugin_url'        => 'https://wordpress.org/plugins/wpgraphql-acf/',
				'support_url'       => 'https://github.com/wp-graphql/wpgraphql-acf/issues/new/choose',
				'author'            => [
					'name'     => 'WPGraphQL',
					'homepage' => 'https://wpgraphql.com',
				],
			],
			'ashhitch/wp-graphql-yoast-seo'      => [
				'name'              => 'WPGraphQL Yoast SEO Addon',
				'description'       => 'This plugin enables Yoast SEO Support for WPGraphQL',
				'documentation_url' => 'https://github.com/ashhitch/wp-graphql-yoast-seo',
				'plugin_url'        => 'https://wordpress.org/plugins/add-wpgraphql-seo/',
				'support_url'       => 'https://github.com/wp-graphql/wpgraphql-acf/issues/new/choose',
				'author'            => [
					'name'     => 'Ash Hitchcock',
					'homepage' => 'https://www.ashleyhitchcock.com/',
				],
			],
			'axepress/wp-graphql-gravity-forms'  => [
				'name'              => 'WPGraphQL for Gravity Forms',
				'description'       => 'Adds Gravity Forms support to WPGraphQL',
				'documentation_url' => 'https://github.com/axewp/wp-graphql-gravity-forms',
				'plugin_url'        => 'https://github.com/axewp/wp-graphql-gravity-forms',
				'support_url'       => 'https://github.com/axewp/wp-graphql-gravity-forms/issues/new/choose',
				'author'            => [
					'name'     => 'AxePress Development',
					'homepage' => 'https://axepress.dev',
				],
			],
			'axepress/wp-graphql-headless-login' => [
				'name'              => 'Headless Login for WPGraphQL',
				'description'       => 'A WordPress plugin that provides headless login and authentication for WPGraphQL, supporting traditional passwords, OAuth2/OIDC, JWT, cookies, header management, and more.',
				'documentation_url' => 'https://github.com/axewp/wp-graphql-headless-login',
				'plugin_url'        => 'https://github.com/axewp/wp-graphql-headless-login',
				'support_url'       => 'https://github.com/axewp/wp-graphql-headless-login/issues/new/choose',
				'author'            => [
					'name'     => 'AxePress Development',
					'homepage' => 'https://axepress.dev',
				],
			],
			'axepress/wp-graphql-rank-math'      => [
				'name'              => 'WPGraphQL for Rank Math SEO',
				'description'       => 'Adds Rank Math SEO support to WPGraphQL',
				'documentation_url' => 'https://github.com/axewp/wp-graphql-rank-math',
				'plugin_url'        => 'https://github.com/axewp/wp-graphql-rank-math',
				'support_url'       => 'https://github.com/axewp/wp-graphql-rank-math/issues',
				'author'            => [
					'name'     => 'AxePress Development',
					'homepage' => 'https://axepress.dev',
				],
			],
		];
	}
}

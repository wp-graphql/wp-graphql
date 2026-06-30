# Changelog

## [2.2.2](https://github.com/wp-graphql/wp-graphql/compare/wp-graphql-smart-cache/v2.2.1...wp-graphql-smart-cache/v2.2.2) (2026-06-29)


### Bug Fixes

* **deps-dev:** bump guzzlehttp/guzzle from 7.10.0 to 7.12.1 in /plugins/wp-graphql-smart-cache ([#3985](https://github.com/wp-graphql/wp-graphql/issues/3985)) ([6699b8f](https://github.com/wp-graphql/wp-graphql/commit/6699b8ffd317de212d67076408be466684b4167d))
* **deps-dev:** bump guzzlehttp/psr7 from 2.11.0 to 2.12.1 in /plugins/wp-graphql-smart-cache ([#3987](https://github.com/wp-graphql/wp-graphql/issues/3987)) ([cb57143](https://github.com/wp-graphql/wp-graphql/commit/cb571436ffebd933fb86bb91ad31c36d0d37f486))
* **deps-dev:** bump guzzlehttp/psr7 from 2.8.0 to 2.11.0 in /plugins/wp-graphql-smart-cache ([#3927](https://github.com/wp-graphql/wp-graphql/issues/3927)) ([f21d46f](https://github.com/wp-graphql/wp-graphql/commit/f21d46fe516cfd63d58dd2febc47012d292819ce))
* support WordPress 7.0 in the integration test matrix ([#3960](https://github.com/wp-graphql/wp-graphql/issues/3960)) ([391e7d3](https://github.com/wp-graphql/wp-graphql/commit/391e7d3fa02085f1905e87c2091bb025885dc6b6))

## [2.2.1](https://github.com/wp-graphql/wp-graphql/compare/wp-graphql-smart-cache/v2.2.0...wp-graphql-smart-cache/v2.2.1) (2026-06-04)


### Bug Fixes

* **deps-dev:** bump symfony/dom-crawler from 5.4.48 to 5.4.52 in /plugins/wp-graphql-smart-cache ([#3856](https://github.com/wp-graphql/wp-graphql/issues/3856)) ([a76bc01](https://github.com/wp-graphql/wp-graphql/commit/a76bc01a42b97505368ca2bae85db40fbc32241e))
* **deps-dev:** bump symfony/yaml from 5.4.45 to 5.4.53 in /plugins/wp-graphql-smart-cache ([#3860](https://github.com/wp-graphql/wp-graphql/issues/3860)) ([bcd0c0d](https://github.com/wp-graphql/wp-graphql/commit/bcd0c0d8f4d0f0c92debcc781eb1bcd3c465305d))
* guard null content when regenerating document hash on updateGraphqlDocument ([#3879](https://github.com/wp-graphql/wp-graphql/issues/3879)) ([95c732b](https://github.com/wp-graphql/wp-graphql/commit/95c732b5f305a541aeb94952d508fae1ac26f566))

## [2.2.0](https://github.com/wp-graphql/wp-graphql/compare/wp-graphql-smart-cache/v2.1.0...wp-graphql-smart-cache/v2.2.0) (2026-05-08)


### New Features

* support on-demand revalidation via graphql_purge ([#3810](https://github.com/wp-graphql/wp-graphql/issues/3810)) ([6c1d176](https://github.com/wp-graphql/wp-graphql/commit/6c1d17685d2d20428d41d67debe911e09e8597ea))

## [2.1.0](https://github.com/wp-graphql/wp-graphql/compare/wp-graphql-smart-cache/v2.0.1...wp-graphql-smart-cache/v2.1.0) (2026-04-23)


### New Features

* import WPGraphQL IDE into monorepo ([#3542](https://github.com/wp-graphql/wp-graphql/issues/3542)) ([e7c1e33](https://github.com/wp-graphql/wp-graphql/commit/e7c1e336ee82e8fe020ca5d6052fa9d330185387))
* **telemetry:** mirror Appsero insights to telemetry.wpgraphql.com ([#3785](https://github.com/wp-graphql/wp-graphql/issues/3785)) ([bd0c310](https://github.com/wp-graphql/wp-graphql/commit/bd0c310147b7129a74dc9619d11fdf8d3f0d1975))


### Bug Fixes

* resolve post by percent-encoded slug/URI when post_name is stored encoded ([#3582](https://github.com/wp-graphql/wp-graphql/issues/3582)) ([#3611](https://github.com/wp-graphql/wp-graphql/issues/3611)) ([a473d9b](https://github.com/wp-graphql/wp-graphql/commit/a473d9b9e6dc1bdf4350f6ea5f6847b769d42ea5))

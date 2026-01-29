# Development

In this document you will find information about how to develop for and contribute to the WPGraphQL Smart Cache plugin.

> **Note:** WPGraphQL Smart Cache is now part of the [WPGraphQL monorepo](https://github.com/wp-graphql/wp-graphql). For general contributing guidelines, PR requirements, and development workflow, please see the [main Contributing guide](https://github.com/wp-graphql/wp-graphql/blob/main/docs/CONTRIBUTING.md) in the monorepo root.

- [Build](#plugin-build)
- [WordPress App](#wordpress-app)
- [WordPress Tests](#wordpess-tests)
- [Network Cache](#network-cache)


We recommend using Docker for local development. With the instructions below, you can use Docker to build the app in an isolated environment with a local running WordPress + WPGraphQL. You can also use Docker to run the test suites.

> **Monorepo Development:** When developing in the monorepo, the plugin is located at `plugins/wp-graphql-smart-cache/`. You can use the monorepo's `wp-env` setup (see [Development Setup](https://github.com/wp-graphql/wp-graphql/blob/main/docs/DEVELOPMENT.md)) or use the Docker setup described below for plugin-specific testing.

## Plugin Build

Use one of the following commands to build the plugin source and it's dependencies. Do this at least once after initial checkout or after updating composer.json.

    composer install --optimize-autoloader

    composer update --optimize-autoloader

One option is to use a docker image to run php/composer:

    docker run -v $PWD:/app composer install --optimize-autoloader

## WordPress App

This section describes how to setup and run this plugin, WP and the wp-graphql plugin locally with docker. It requires building the images (see above) at least once, which can take a few moments the first time.

### Build

Use one of the following commands to build the local images for the app and testing.

#### docker-compose

Build all images in the docker compose configuration. Requires having built your own wp-graphql local images.

    WP_VERSION=6.3 PHP_VERSION=8.2 docker-compose build --build-arg WP_VERSION=6.3 --build-arg PHP_VERSION=8.2

Build fresh docker image without cache by adding `--no-cache`.

    WP_VERSION=6.3 PHP_VERSION=8.2 docker-compose build --build-arg WP_VERSION=6.3 --build-arg PHP_VERSION=8.2 --no-cache

Build using wp-graphql image from docker hub registry, instead of building your own wp-graphql image.

    WP_VERSION=6.3 PHP_VERSION=8.2 docker-compose build --build-arg WP_VERSION=6.3 --build-arg PHP_VERSION=8.2 --build-arg DOCKER_REGISTRY=ghcr.io/wp-graphql/

#### docker

Use this command if you want to build a specific image. If you ran the docker-compose command above, this is not necessary.

    docker build -f docker/Dockerfile -t wp-graphql-smart-cache:latest-wp6.3-php8.2 --build-arg WP_VERSION=6.3 --build-arg PHP_VERSION=8.2 .

### Run

Use one of the following to start the WP app with the plugin installed and running. After running, navigate to the app in a web browser at http://localhost:8091/

    docker compose up app

This is an example of specifying the WP and PHP version for the wp-graphql images.

    WP_VERSION=6.3 PHP_VERSION=8.2 docker compose up app

### Shell

Use one of the following if you want to access the WP app with bash command shell.

    docker-compose run app bash

    WP_VERSION=6.3 PHP_VERSION=8.2 docker-compose run app bash

### Stop

Use this command to stop the running app and database.

    docker-compose stop

### Use a local wp-graphql plugin

If you have a copy of the wp-graphql plugin you'd like to use in the running app, Add this to volumes section in docker-compose.yml.

      - './local-wp-graphql:/var/www/html/wp-content/plugins/wp-graphql'

## WordPress Tests

Use this section to run the plugin test suites.

### Build

Use one of the following commands to build the test docker image.

#### docker-compose

If you ran the docker-compose build command, above, this step is not necessary and you should already have the build docker image, skip to run.

#### docker

    WP_VERSION=6.3 PHP_VERSION=8.2 docker build -f docker/Dockerfile.testing -t wp-graphql-smart-cache-testing:latest-wp5.7.2-php7.4 --build-arg WP_VERSION=6.3 --build-arg PHP_VERSION=8.2 --build-arg DOCKER_REGISTRY=ghcr.io/wp-graphql/ .

    docker build -f docker/Dockerfile.testing -t wp-graphql-smart-cache-testing:latest-wp5.7.2-php7.4 --build-arg WP_VERSION=6.3 --build-arg PHP_VERSION=8.2 --build-arg DOCKER_REGISTRY=ghcr.io/wp-graphql/ .

### Run

Use one of these commands to run the test suites.

    WP_VERSION=6.3 PHP_VERSION=8.2 docker-compose run testing

    docker-compose run testing

Use the DEBUG environment variable to see the codeception debug during tests.

    WP_VERSION=6.3 PHP_VERSION=8.2 docker-compose run -e DEBUG=1 testing

### Shell

Use one of the following if you want to access the WP testing app with bash command shell.

    docker-compose run --entrypoint bash testing

This is an example of specifying the WP and PHP version for the wp-graphql images.

    WP_VERSION=6.3 PHP_VERSION=8.2 docker-compose run --entrypoint bash testing

## Network Cache

Use these steps to run a network cache (varnish) to similate the caching behavior of graphql requests and invalidation during content updates.

    WP_VERSION=6.1 PHP_VERSION=8.1 docker compose up varnish

The varnish app starts and listens at http://localhost:8081/ for requests.

Test a graphql query to load post #1 "Hello World" at this 'http://localhost:8081/graphql?query={ post(id: "1", idType: DATABASE_ID) { title } }'

### Cache Requests

Send graphql request to http://localhost:8081/graphql end point. You will see caching headers in the response.

Initial return will miss the cache and access the WordPress backend.

```
X-Cache: Miss
```

Subsequent requests that have cached content will return that without accessing the backend WordPress.

```
X-Cache: HIT: 1
```

Also note the correspeonding Graphql specific headers in the response.

```
X-Graphql-Query-Id:
X-Graphql-Url:
```

### Purge Cache

Add this code to your WordPress to handle invalidation with the running varnish server.

```
add_action(
    'graphql_purge',
    function ( $purge_keys, $event = 'event', $url = '' ) {
        $headers =
        $response = wp_remote_post(
            'http://varnish',
            [
                'method' => 'PURGE_GRAPHQL',
                'headers' => [
                    'GraphQL-Purge-Keys' => $purge_keys,
                    'GraphQL-URL'        => graphql_get_endpoint_url(),
                ],
            ],
        );
        error_log( "GRAPHQL_PURGE $url $purge_keys ". $response['body'] );
    },
    10,
    3
);
```

Run some graphql requests and verify seeing the cached response. Change content (mutation or Wp-Admin) and verify what was invalidated (purged) from cache.

If using WP-Admin to change content, login at http://localhost:8091 in a different browser or incognito from where you are testing cached graphql queries.

After data is invalidate, your previously cache request will access the WP backend for fresh data and show `X-Cache: Miss` in the response headers.

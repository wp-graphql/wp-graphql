## Recommended Version

For the most stable and performant experience, it's recommended that you use the _most recent_ version of the plugin. You can [see the latest releases here](https://github.com/wp-graphql/wp-graphql/releases).

Of course, as new features are in development, feel free to check out the latest `develop` branch or check out any other feature or release.

## Download / Clone the Plugin

WPGraphQL is available on Github: [https://github.com/wp-graphql/wp-graphql](https://github.com/wp-graphql/wp-graphql)

You can download the plugin or clone the plugin from [Github](https://github.com/wp-graphql/wp-graphql).

Add the downloaded/cloned plugin to your WordPress plugin directory. On a typical WordPress install, this is located at `/wp-content/plugins`

!!! note
    The plugin directory should be `wp-graphql` and not something like `wp-graphql-master` or `wp-graphql-develop`

## Activate the Plugin

Once the plugin is in the WordPress plugins directory, it can be activated by clicking "Activate" on the plugin screen, or via WP CLI `wp plugin activate wp-graphql`

### Verify the Endpoint Works

The most common use of WPGraphQL is as an API endpoint that can be accessed via HTTP requests \([although it can be used without remote HTTP requests as well](#using-the-plugin-without-the-graphql-endpoint)\)

In order for the `/graphql` endpoint to work, you must have [pretty permalinks](https://codex.wordpress.org/Using_Permalinks) enabled and any permalink structure _other_ than the default WordPress permalink structure.

Once the plugin is active, your site should have a `yoursite.com/graphql` endpoint and you the expected response is a JSON payload like so:

```json
{
    "errors": [
        {
            "message": "GraphQL requests must be a POST or GET Request with a valid query",
            "category": "user"
        }
    ]
}
```

If you see anything else, such as your site's 404 page, you may need to [flush permalinks](#flush-permalinks).

### Flush Permalinks

Activating the plugin _should_ cause the permalinks to flush. Occasionally, this doesn't work. If you have a permalink structure other than the default WordPress structure, and the plugin is active but nothing shows at your site's `/graphql` endpoint, try to manually flush your permalinks by:

* From your WordPress dashboard, visit the _**Settings &gt; Permalinks**_ page. Just visiting the page should flush the permalinks

or

* Using WP CLI run `wp rewrite flush`

### Using the Plugin without the /graphql Endpoint

WPGraphQL can be used from the context of WordPress PHP and doesn't require HTTP requests to be used. You can completely remove the `/graphql` endpoint that the plugin provides so that the API is not available publicly in _any way_ but still use GraphQL in your plugin and theme code by using:

```php
do_graphql_request()
```

[Learn more about using WPGraphQL in PHP, without making HTTP requests](/tutorials/use-graphql-in-php-without-http-request.md).


---
uri: "/docs/use-with-php/"
title: "Use with PHP"
---

While WPGraphQL is commonly used for headless WordPress setups via HTTP requests, you can also execute GraphQL queries directly in PHP within your WordPress themes and plugins. This can be particularly useful for:

- Building blocks and shortcodes
- Creating template parts
- Processing data in background jobs
- Integrating with other WordPress plugins
- Building admin interfaces

## Basic Usage

### The graphql Function

The modern function for executing GraphQL queries in PHP is `graphql()`:

```php
graphql( array $request_data = [], bool $return_request = false );

// The $request_data array accepts:
[
    'query' => string,           // The GraphQL query
    'variables' => array,        // Optional variables
    'operation_name' => string,  // Optional operation name
]
```

### Simple Example

Here's a basic example fetching a post by ID:

```php
$result = graphql([
    'query' => '
        query GetPost($id: ID!) {
            post(id: $id, idType: DATABASE_ID) {
                title
                date
                content
            }
        }
    ',
    'variables' => [
        'id' => get_the_ID()
    ],
    'operation_name' => 'GetPost'
]);
```

## Common Use Cases

### Building a Shortcode

Create a shortcode that displays a list of posts with specific criteria:

```php
add_shortcode('recent_posts_list', function($atts) {
    // Parse attributes
    $args = shortcode_atts([
        'category' => '',
        'count' => 5
    ], $atts);
    
    $result = graphql([
        'query' => '
            query RecentPosts($count: Int!, $category: String) {
                posts(
                    first: $count,
                    where: {
                        categoryName: $category
                    }
                ) {
                    nodes {
                        title
                        excerpt
                        uri
                    }
                }
            }
        ',
        'variables' => [
            'count' => absint($args['count']),
            'category' => $args['category']
        ],
        'operation_name' => 'RecentPosts'
    ]);
    
    // Build output
    $output = '<ul class="recent-posts">';
    foreach ($result['data']['posts']['nodes'] as $post) {
        $output .= sprintf(
            '<li><a href="%s">%s</a>%s</li>',
            esc_url($post['uri']),
            esc_html($post['title']),
            wp_kses_post($post['excerpt'])
        );
    }
    $output .= '</ul>';
    
    return $output;
});
```

### Template Parts

Using GraphQL in template parts for consistent data fetching:

```php
function get_author_bio_data($author_id) {
    return graphql([
        'query' => '
            query AuthorBio($id: ID!) {
                user(id: $id, idType: DATABASE_ID) {
                    name
                    description
                    avatar {
                        url
                    }
                    posts {
                        pageInfo {
                            total
                        }
                    }
                    social: userFields {
                        twitter
                        linkedin
                    }
                }
            }
        ',
        'variables' => [
            'id' => $author_id
        ],
        'operation_name' => 'AuthorBio'
    ]);
}
```

### Block Editor Integration

Register a dynamic block that uses GraphQL:

```php
register_block_type('my-plugin/related-posts', [
    'render_callback' => function($attributes) {
        $result = graphql([
            'query' => '
                query RelatedPosts($postId: ID!, $count: Int!) {
                    post(id: $postId, idType: DATABASE_ID) {
                        related: relatedPosts(first: $count) {
                            nodes {
                                title
                                uri
                                featuredImage {
                                    node {
                                        sourceUrl
                                    }
                                }
                            }
                        }
                    }
                }
            ',
            'variables' => [
                'postId' => get_the_ID(),
                'count' => $attributes['count'] ?? 3
            ],
            'operation_name' => 'RelatedPosts'
        ]);
        
        // Render block output...
    }
]);
```

### Caching

Consider caching GraphQL results for better performance:

```php
function get_cached_graphql_data($query, $variables = [], $operation_name = '') {
    $cache_key = 'graphql_' . md5($query . serialize($variables) . $operation_name);
    
    $cached = wp_cache_get($cache_key);
    if ($cached !== false) {
        return $cached;
    }
    
    $result = graphql([
        'query' => $query,
        'variables' => $variables,
        'operation_name' => $operation_name
    ]);
    
    wp_cache_set($cache_key, $result, '', HOUR_IN_SECONDS);
    
    return $result;
}
```

## Best Practices

### Error Handling

Always handle potential errors in your GraphQL responses:

```php
$result = graphql([
    'query' => $query
]);

if (isset($result['errors'])) {
    // Handle errors appropriately
    error_log('GraphQL Error: ' . print_r($result['errors'], true));
    return ''; // Or fallback content
}
```

### Security

> [!IMPORTANT]
> Always validate and sanitize variables before using them in queries, especially when dealing with user input.

```php
// Sanitize input before using in query
$safe_post_type = sanitize_text_field($_GET['post_type'] ?? 'post');
$safe_count = absint($_GET['count'] ?? 10);
```

### Performance Considerations

> [!TIP]
> - Request only the fields you need
> - Use field selection based on context
> - Consider implementing caching for repeated queries
> - Monitor query complexity

## Debugging

When developing, you can enable debug mode to get more detailed error information:

```php
define('GRAPHQL_DEBUG', true);
```

You can also use the Query Monitor plugin which works with WPGraphQL Query Logging to add log data to WPGraphQL queries.

## Further Reading

- [GraphQL Queries Guide](/docs/graphql-queries/)
- [Performance Guide](/docs/performance/)
- [Debugging Guide](/docs/debugging/)

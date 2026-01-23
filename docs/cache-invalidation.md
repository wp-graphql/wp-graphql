# Cache Invalidation

One of the primary features of the WPGraphQL Smart Cache plugin is the cache invalidation.

Unlike RESTful APIs where each endpoint is related to a specific resource type, GraphQL Queries can be constructed in nearly infinite ways, and can contain resources of many types.

Because of the flexibility that GraphQL offers, caching and invalidating caches can be tricky.

This is where WPGraphQL Smart Cache really shines.

When a GraphQL request is executed against the WPGraphQL endpoint, the query is analyzed at run time to determine:

- The operation name of the query
- The ID of the query (a hash of the query document string)
- What types of nodes were asked for as a list
- The individual nodes resolved by the query

The results of the GraphQL query are cached and "tagged" with this meta data.

When [relevant events](#tracked-events) occur in WordPress, WPGraphQL Smart Cache emits a `purge` action to purge cached documents with the key(s) called in the purge action.

## Invalidation Strategy

Below are details about what events trigger purging of the cache, and what tags will be purged in response to the events.

### Publish Events

When something in WordPress transitions from not being publicly visible to becoming publicly visible, this will emit a purge event for "lists" of the type.

For example, publishing a new post will call `purge( 'list:post' )`.

Creating a new draft post, however, will not emit a `purge` action, as a draft post is not a publicly visible entity. When the draft post is published, it will emit the event.

Similarly, Creating a user will not emit a `purge` action, as a user with no published content is not considered a publicly visible entity.

However, a User with no published content publishing their first post as the assigned author will call `purge( 'list:user' )`, as the user will now transition from a private entity (a non-published author) to a public entity (a published author).

### Update Events

When a publicly visible entity (such as a published post) is updated, this will emit a `purge( $node_id )` event _and_ a `purge( 'skipped:$type_name )` event.

Thus, any queries that have been tagged with the id of the node, or with `skipped:$type_name`, will be purged.

### Delete Events

When something public is made not-public (converting a published post to draft, or trashing a published post, etc), this will emit a `purge( $node_id )` event _and_ a `purge( 'skipped:$type_name )` event.

Thus, any queries that have been tagged with the id of the node, or with `skipped:$type_name`, will be purged.

### Hold up... What's the deal with the `skipped$type_name` thing?

Because of [header length limitations](https://nodejs.org/en/blog/vulnerability/november-2018-security-releases/#denial-of-service-with-large-http-headers-cve-2018-12121), queries that return an excessive number of nodes might have the headers truncated and replaced with a generic `skipped:$type_name` header.

For example, if I queried for 500 posts, 500 pages, and 500 users, that would likely cause a header overlfow. This would likely lead to the following headers being returned, instead of each individual node ID that was resolved: `skipped:post`, `skipped:page`, `skipped:user`.

When a cache is tagged with `skipped:$type_name` it will be purged more often than if it were tagged with the specific node id(s), as editing _any_ node of that type will evict this cache, instead of evicting only when a relevant node in the response has been edited.

## Tracked Events

WPGraphQL Smart Cache tracks events related to all the core WordPress data types, with the current exception of Options.

### Posts, Pages, Custom Post Types

- published
- updated
- deleted
- meta changes

### Categories, Tags, Custom Taxonomies

- created
- assigned to post
- unassigned to post
- deleted
- meta changes

### Users

- created
- assigned as author of a post
- deleted
- author re-assigned

### Media

- uploaded
- updated
- deleted

### Comments

- inserted
- transition status

### Settings / Options

Currently WPGraphQL Smart Cache does not track events related to updating settings.

There is a lot of nuance to consider. You can read more about this here: [https://github.com/wp-graphql/wp-graphql-smart-cache/issues/158](https://github.com/wp-graphql/wp-graphql-smart-cache/issues/158)

----

## 👉 Up Next:

- [Network Cache](./network-cache.md)
- [Object Cache](./object-cache.md)
- [Persisted Queries](./persisted-queries.md)

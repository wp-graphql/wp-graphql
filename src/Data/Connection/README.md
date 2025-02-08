# Developer Docs: AbstractConnectionResolver

A Connection Resolver is responsible for resolving a ([Relay spec](https://relay.dev/graphql/connections.htm)-compliant) connection for a given GraphQL field.

The connection resolver:
1. maps the GraphQL arguments to the underlying database query,
2. executes the database query,
3. instantiates the GraphQL model for each result and caches it in the DataLoader,
4. populates the connection edges and page info,
5. and finally returns the connection data to populate the connected GraphQL type.

As WordPress stores and fetches object types differently for each type, a separate connection resolver is used for each type. Developers can create their own connection resolvers by extending the `WPGraphQL\Data\Connection\AbstractConnectionResolver` class, or one of the existing child classes (e.g. extending `WPGraphQL\Data\Connection\PostObjectConnectionResolver` for Custom Post Type-specific resolver).

Before we get into the details of how to create your own connection resolver, let's take a look at the lifecycle of a connection resolver in more detail.

## Lifecycle

As follows is an example action of registering a custom WPGraphQL connection:

```php
add_action( 'graphql_register_types', function() {
	// Register your custom connection.
	register_graphql_connection( [
		'fromType'      => $my_from_type,
		'fromFieldName' => $my_field_name,
		// other supported args...

		// The `resolve` callback is where the lifecycle magic happens 👇
		'resolve'       => function( $source, array $args, \WPGraphQL\AppContext $context, \GraphQL\Type\Definition\ResolveInfo $info ) {
			// Create a new instance of the connection resolver.
			$resolver = new \WPGraphQL\Data\Connection\MyCustomConnectionResolver( $source, $args, $context, $info );

			// Modify the resolver properties before the connection is executed.
			$resolver->set_query_arg( 'post_type', 'post' );
			$resolver->one_to_one();

			// If the connection uses a query class (e.g. `WP_Query`) we can overload that.
			$resolver->set_query_class( 'WP_Meta_Query' );

			// Return the connection data.
			return $resolver->get_connection();
		},
	] );
} );

```

### 1. Class instantiation
The first step to using a Connection Resolver is to instantiate it. The connection resolver is instantiated in the `resolve` callback of the `register_graphql_connection` function. The `resolve` callback is passed the `$source` node, an `array` of GraphQL `$args`, the `$context`, and `$info` arguments, which are then passed to the connection resolver constructor.

```php
// Create a new instance of the connection resolver.
$resolver = new \WPGraphQL\Data\Connection\MyCustomConnectionResolver( $source, $args, $context, $info );
```

The constructor then sets and prepares the following instance properties (in order):

1. `$this->source` - The source object that the connection is coming from. This can be retrieved from the resolver instance using `$resolver->get_source()`.
2. `$this->unfiltered_args` - The GraphQL args passed to the connection resolver, before they've been filtered by the `graphql_connection_args` filter. This can be retrieved from the resolver instance using `$resolver->get_unfiltered_args()`.
3. `$this->context` - The context object that the connection is coming from. This can be retrieved from the resolver instance using `$resolver->get_context()`.
4. `$this->info` - The info object that the connection is coming from. This can be retrieved from the resolver instance using `$resolver->get_info()`.
5. `$this->should_execute` - This is set to `false` _only if_ the `get_pre_should_execute( $source, $args, $context, $info )` method returns `false`, and is used to short circuit the connection resolver. If it returns `true`, then the `should_execute()` method will be run later in the lifecycle.
6. `$this->loader` - The DataLoader instance used by the connection resolver. This can be retrieved from the resolver instance using `$resolver->get_loader()`.
7. `$this->args` - The GraphQL args passed to the connection resolver, after they've been filtered by the `graphql_connection_args` filter. This can be retrieved from the resolver instance using `$resolver->get_args()`.
8. `$this->query_amount` - The number of items to query for the connection. This can be retrieved from the resolver instance using `$resolver->get_query_amount()`.
9. `$this->query_args` - The query args that will be used to query the database, after they've been filtered by the `graphql_connection_query_args` filter. This can be retrieved from the resolver instance using `$resolver->get_query_args()`.
10. `$this->query_class` - The query class (e.g. `WP_Query`) that will be used to query the database, after it has been filtered by the `graphql_connection_query_class` filter. This can be retrieved from the resolver instance using `$resolver->get_query_class()`. **Note:** This should return `null` if the connection resolver does not use a query class.

### 2. (Optional) Modify the resolver properties
In some cases, you may want to modify the resolver properties before the connection is executed. This allows you to reuse the same connection resolver for multiple fields, and modify the query args or connection type based on the field that is being resolved.

> [!NOTE]
> Whenever possible, it's better to use the `$args` passed to the connection resolver constructor to modify the query args, rather than modifying the query args directly using the setter methods.
> This ensures that the connection resolver has a more predictable lifecycle reduces the likelihood of unexpected behavior and conflicts.

```php
// Modify the resolver properties before the connection is executed.
$resolver->set_query_arg( 'post_type', 'post' );
$resolver->set_query_class( 'WP_Meta_Query' );
$resolver->one_to_one();
```

The `AbstractConnectionResolver` class provides three methods for modifying the resolver properties:

- `public function set_query_arg( $key, $value )`
  This method allows you to set a query arg for the connection resolver. This is useful for setting a custom query arg or modifying an existing one that isn't handled by the AbstractConnectionResolver's `prepare_query_args()` method.

- `public function set_query_class( $class )`
  This method allows you to overload the query class (e.g. `WP_Query`) that the connection resolver uses to query the database in the `$resolver->query()` method. This is useful if the data your connection is querying requires a specialized query class (e.g. `WP_Meta_Query`, `WP_Term_Query`, WooCommerce's `WC_Query`, etc ). **Note:** Not all connection resolvers use a query class, and using this method on a connection resolver that doesn't use a query class will throw an error.

- `public function one_to_one()`
  This method allows you to set the connection type to `one-to-one`. If set then the connection resolver will only return the first item in the connection.

### 3. Execute the connection
Once the Connection Resolver has been instantiated and the properties have been set, the connection is executed by calling the `get_connection()` method.

```php
// Return the connection data.
return $resolver->get_connection();
```

When `get_connection()` is called, the connection resolver will first execute the query and get the IDs of the results, and then return a `Deferred` function to populate the connection data, such as the `nodes`, `edges`, and `pageInfo`.

Lets drill down even further into the `get_connection()` method to see what happens when it's called.

#### 3A. - Executing the Query.

The first thing that happens in `get_connection()` is that the `execute_and_get_ids()` method is called. This method:

1. Checks if the pre-execution hook (`get_pre_should_execute( $source, $args, $context, $info )`) returns false. If it does, then the connection resolver will short circuit and return an empty array. If it returns true, then the `should_execute()` method will be called.

2. If `should_execute()` returns true, then the connection will:

	1. Execute the `query()` method, which uses the `$query_args` to query the database and fetch the results.

		Note that we overfetch our query results by 1, to determine if there are more pages in the connection.

		If the query requires the use of a `$query_class`, then `is_valid_query_class()` will be called to ensure that the query class is compatible with this method.

	2. Get the IDs of the query results (and sorts them for pagination if necessary) from the executed query, using the `get_ids_from_query()` method.

	3. Apply the Relay-spec pagination args, to slice the results to the correct page, using the `apply_cursors_to_ids()` method.

	4. Buffer the results in the DataLoader for deferred resolution.

#### 3B. - (Defer) loading the connection data.
Once the query has been executed and the IDs have been buffered in the DataLoader, the connection resolver will return a `Deferred` function to populate the connection data.

Inside the `Deferred` callback, the connection resolver will:

1. Fetch the full object data for the query results from the DataLoader.

2. Generate the nodes:

	1. First, we trim our overfetched results to the correct `query_amount`, using the `get_ids_for_nodes()` method.

	2. Then we loop through the IDs, using `get_node_by_id()` to get the full GraphQL Model for the object.

3. Use the nodes to generate the edge data, such as the `cursor`, `node`, `source`, and `connection`.

4. Generate the connection data.

   If the connection is a 'one-to-one' connection, only the `edge` data for the first node will be returned. Otherwise, the connection will include the `nodes`, `edges`, and the `pageInfo` used for paginating results will be generated and returned.

## Interacting with the Connection Resolver instance.

The `AbstractConnectionResolver` class provides a number of setter and getter methods that can be used to interact with the instance properties.

These methods are often essential to extending the resolver within a WordPress filter callback. As such, care must be taken to ensure that the getter methods are called in the correct order (ideally _after_ the requested parameters have been set on the Resolver instance), to ensure the lifecycle of the resolver isn't altered in a way that would break expected behavior.

The following "getter" methods are available on the `AbstractConnectionResolver` class:

- `$instance->get_source() : mixed`

   Gets the source object passed to the connection.

- `$instance->get_context() : \WPGraphQL\AppContext`

   Gets the AppContext instance passed to the connection.

- `$instance->get_info() : \GraphQL\Type\Definition\ResolveInfo`

   Gets the ResolveInfo instance passed to the connection.

- `$instance->get_unfiltered_args() : array`

   Gets the GraphQL args passed to the connection, _before_ the resolver applies any filters or modifications.

   All the above methods are available and can be called safely at _any point_ in the resolver's lifecycle.

- `$instance->get_loader() : \WPGraphQL\Data\Loader`
   Gets the DataLoader instance used to cache and resolve the connection node data.

   Can be called any time _after_ the `graphql_connection_loader_name` filter has been applied.

- `$instance->get_args() : array`

   Gets the GraphQL args passed to the connection, _after_ the resolver applies any filters or modifications.

   Can be called any time _after_ the `graphql_connection_args` filter has been applied.

- `$instance->get_query_amount() : int`

   Gets the number of items to query for the connection.

   Can be called any time _after_ the `graphql_connection_amount_requested` filter has been applied.

- `$instance->get_query_args() : array`

   Gets the query args used to query the database for the connection.

   Can be called any time _after_ the `graphql_connection_query_args` filter has been applied.

- `$instance->get_query_class() : ?string`

   Gets the query class used by the `$instance->query()` method to query the database for the connection. Should return null if no query class is used.

   Can be called any time _after_ the `graphql_connection_query_class` filter has been applied.

- `$instance->get_should_execute() : bool`

   Gets whether or not the connection should execute.

   Can be called any time _after_ the `graphql_connection_should_execute` filter has been applied.

- `$instance->get_query() : mixed` Gets the executed query for the connection.

   Can be called any time _after_ the `graphql_connection_query` filter has been applied.

- `$instance->get_ids() : array`

   Gets the IDs of the query results for the connection.

   Can be called any time _after_ the `graphql_connection_ids` filter has been applied.

- `$instance->get_nodes() : array`

   Gets the nodes (GraphQL models) for the connection results.

   Can be called any time _after_ the `graphql_connection_nodes` filter has been applied.

- `$instance->get_edges() : array`

   Gets the array of edge data for the connection results.

   Can be called any time _after_ the `graphql_connection_edges` filter has been applied.

- `$instance->get_page_info() : array`

   Gets the pageInfo data for the connection results.

   Can be called any time _after_ the `graphql_connection_page_info` filter has been applied.

## Extending a connection resolver with WordPress Filters.

The `AbstractConnectionResolver` class provides a number of filters that can be used to modify the behavior of an existing Connection Resolver. They are listed below in the order that they are executed.

> [!NOTE]
> For advanced use cases, you will likely want to access properties already set on the `AbstractConnectionResolver` instance, such as the `$query_args` or `$args`. As such, it is recommended to pay attention to the order in which the filters are executed, as some filters may be executed before the properties are set, leading to unexpected behavior.

- `apply_filters( 'graphql_connection_pre_should_execute', bool $should_execute, $source, array $args, \WPGraphQL\AppContext $context, \GraphQL\Type\Definition\ResolveInfo $info )`

   Filters whether or not the query should execute, BEFORE any data is fetched or altered. This is evaluated based solely on the values passed to the constructor, before any data is fetched or altered, and is useful for short-circuiting the Connection Resolver before any heavy logic is executed.

   For more in-depth checks, use the `graphql_connection_should_execute` filter instead.

- `apply_filters( 'graphql_connection_loader_name', string $loader_name, \WPGraphQL\Data\Connection\AbstractConnectionResolver $resolver )`

   Filters the `name` (array key) of the registered DataLoader to use for the connection. This is useful if you want to use a custom DataLoader for a connection (e.g. for a custom post type using `PostObjectConnectionResolver`).

- `apply_filters( 'graphql_connection_args', array $args, \WPGraphQL\Data\Connection\AbstractConnectionResolver $resolver )`

   Filters the `$args` passed to the connection resolver, before they are mapped to the `$query_args` that will be used to execute the query.

- `apply_filters( 'graphql_connection_max_query_amount', int $max_query_amount, $source, array $args, \WPGraphQL\AppContext $context, \GraphQL\Type\Definition\ResolveInfo $info )`

   Filters the maximum number of items that should be queried. Defaults to 100.

- `apply_filters( 'graphql_connection_amount_requested', int $amount_requested, \WPGraphQL\Data\Connection\AbstractConnectionResolver $resolver )`

   Filters the number of items requested by the connection, e.g. the `first` or `last` argument. Defaults to 10.

- `apply_filters( 'graphql_connection_query_args', array $query_args, \WPGraphQL\Data\Connection\AbstractConnectionResolver $resolver )`

   Filters the `$query_args` that will be used to execute the query. This is useful for modifying the query args before they are executed, or for handling custom connection args that aren’t mapped by the AbstractConnectionResolver's `prepare_query_args()` method.

- `apply_filters( 'graphql_connection_query_class', ?string $query_class, \WPGraphQL\Data\Connection\AbstractConnectionResolver $resolver )`

   Filters the `$query_class` that will be used to execute the query. This is useful for replacing the default query class (e.g. `WP_Query`) with a custom one (e.g. `WP_Term_Query` or WooCommerce's `WC_Query`).

- `apply_filters( 'graphql_connection_should_execute', bool $should_execute, \WPGraphQL\Data\Connection\AbstractConnectionResolver $resolver )`

   Filters whether or not the resolver should return any data, _after_ the `$query_args` have been mapped, but before the query is executed.

   You can short-circuit the connection resolver earlier in the lifecycle by using the `graphql_connection_pre_should_execute` filter instead.

- `apply_filters( 'graphql_connection_pre_get_query', \WP_Query $query, \WPGraphQL\Data\Connection\AbstractConnectionResolver $resolver )`

   When this filter returns anything but false, it will be used as the resolved query, and the default query execution will be skipped.

   Useful for replacing the default query (e.g. `WP_Query()`) with a custom one (e.g. `WP_Term_Query()` or even a query to an external datasource such as a REST API.)

- `apply_filters( 'graphql_connection_query', \WP_Query $query, \WPGraphQL\Data\Connection\AbstractConnectionResolver $resolver )`

   Filters the `$query` after it has been executed (either by the `graphql_connection_pre_get_query` filter or the default `$this->query()` method).

-  `apply_filters( 'graphql_connection_ids', array $ids, \WPGraphQL\Data\Connection\AbstractConnectionResolver $resolver )`

   Filters the IDs of the results returned from the query. This is useful for modifying the IDs before they are buffered in the DataLoader, or for modifying the IDs before they are used to generate the nodes.

   The resulting array of IDs should be sorted in the order expected from the GraphQL connection, and sliced to the correct amount of items to return plus 1 (we overfetch to determine if there are more pages in the connection).

- `apply_filters( 'graphql_connection_nodes', array $nodes, \WPGraphQL\Data\Connection\AbstractConnectionResolver $resolver )`

   Filters the nodes returned by the connection resolver.

   This is useful for modifying the nodes before they are used to generate the edges and connection data.

- `apply_filters( 'graphql_connection_edge', array $edges, \WPGraphQL\Data\Connection\AbstractConnectionResolver $resolver )`

   Filters the data returned by the connection resolver for the individual edge.

   This is useful for modifying the edge data, or passing custom data that can be used to resolve edge fields.

- `apply_filters( 'graphql_connection_edges', array $edges, \WPGraphQL\Data\Connection\AbstractConnectionResolver $resolver )`

   Filters the edges returned by the connection resolver.

   This is useful for modifying the edges before they are used to generate the connection data.

- `apply_filters( 'graphql_connection_page_info', array $page_info, \WPGraphQL\Data\Connection\AbstractConnectionResolver $resolver )`

   Filters the pageInfo that is returned by the connection resolver.

   This filter is useful for modifying the pageInfo data, or resolving custom pageInfo fields.

- `apply_filters( 'graphql_connection', array $connection, \WPGraphQL\Data\Connection\AbstractConnectionResolver $resolver )`

   Filters the connection data returned by the connection resolver.

   This is useful for providing additional data other than the `edges`, `nodes`, and `pageInfo` that can be used by the connection.

## Creating a Custom Connection Resolver

You can create a custom connection resolver by extending the `AbstractConnectionResolver` (or one of the existing child classes) class and overloading the methods you need to customize.

Below is a list of some of the more common `AbstractConnectionResolver` methods that you may need to implement in your child class.

(For real-world examples, see the [other connection resolvers included with WPGraphQL](./)).

- `protected function loader_name() : string` (Required)

   Sets the name (array key) of the DataLoader to use for the connection. E.g. `post`, `comment`, `user`, etc.

- `protected function prepare_query_args( array $args ) : array` (Required)

   Maps the `$args` passed to the connection to the `$query_args` used to execute the query.

- `public function get_ids_from_query() : array` (Required)

   Returns an array of IDs from the processed query. Each Query class in WordPress and potential datasource handles this differently, so this handles getting the items into a uniform array of items.

- `public function is_valid_offset( $offset ) : bool` (Required)

   Determines whether a provided offset (i.e. the item it corresponds to) exists. This is equivalent to checking if the WordPress object for a given ID exists.

- `protected function prepare_args( array $args ) : array`

   Prepares the `$args` passed to the connection, before they are mapped to the `$query_args` used to execute the query.

- `protected function query_class() : ?string`

   Sets the class used by the `query()` method to execute the query. Should return `null` if the `query()` method does not rely on a `$query_class`.

- `protected function is_valid_query_class( string $query_class ) : bool`

   Determines whether the `$query_class` set in the Connection Resolver is compatible with the `query()` method. This is ignored if the `query()` method does not rely on a `$query_class`.

- `protected function query( array $query_args ) : mixed`

   Executes the query. Usually this will return an instance of the `$query_class`, but it can return anything that can be used to generate the nodes for the connection. This **must** be overridden if the Resolver does not use a `$query_class`.

- `protected function get_pre_should_execute( $source, array $args, \WPGraphQL\AppContext $context, \GraphQL\Type\Definition\ResolveInfo $info ) : bool`

   Determines whether the connection query should be executed before any heavy logic runs. Use this hook to short-circuit execution when appropriate.

- `protected function should_execute() : bool`

   Used to determine whether or not the connection query should be executed (after mapping `$query_args`). This method is only run if the pre-execution hook passes.

- `public function get_offset_for_cursor( string $cursor = null )`

   Returns the array offset for a given connection item cursor. This is used to convert the list of items from the query so that it follows the `first` or `last` argument correctly.

   Connections that use a string-based offset should override this method.

- `protected function is_valid_model( $model ) : bool`

   Validates the GraphQL model returned from the DataLoader. By default, this ensures that the model’s fields are set appropriately, but your GraphQL implementation might have different requirements.

## Implementation Tips

- **Avoid overloading `::get_*()`** methods in your child class. Instead, use the methods they wrap (e.g. use `loader_name()` instead of `get_loader_name()`, `prepare_args()` instead of `get_args()`, etc).

- **Call `::get_*()`** methods on the instance rather than accessing properties directly.

- Before creating a new connection resolver, check if there is an existing connection resolver that can be **extended via a filter** to meet your needs.

- When resolving a connection, it’s best to **modify the GraphQL `$args`** before they are passed to the connection resolver constructor rather than modifying the underlying WordPress query args directly using the setter methods.

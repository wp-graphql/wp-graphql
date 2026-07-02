---
uri: "/docs/interfaces/"
title: "Interfaces"
---

GraphQL interfaces are abstract types that define a set of fields that implementing types must include. In WPGraphQL, interfaces allow you to create reusable field definitions that can be shared across multiple types.

## What are Interfaces?

An interface is like a contract that defines what fields a type must have. Any type that implements an interface must include all of the interface's fields, though they can add additional fields of their own.

## Registering an Interface

You can register a custom interface using the `register_graphql_interface_type()` function:

```php
add_action( 'graphql_register_types', function() {
  register_graphql_interface_type( 'MyInterface', [
    'fields' => [
      'sharedField' => [
        'type' => 'String',
        'description' => 'A field shared by all types implementing this interface',
      ],
    ],
  ] );
} );
```

## Implementing an Interface

Object types can implement one or more interfaces using the `interfaces` key:

```php
add_action( 'graphql_register_types', function() {
  register_graphql_object_type( 'MyType', [
    'interfaces' => [ 'MyInterface' ],
    'fields' => [
      'sharedField' => [
        'type' => 'String',
        'resolve' => function() {
          return 'value';
        },
      ],
      'customField' => [
        'type' => 'String',
        'description' => 'A field specific to this type',
      ],
    ],
  ] );
} );
```

## Interface Field Inheritance

When a type implements an interface, it automatically inherits all fields from that interface. You don't need to redefine interface fields unless you want to:

- Override the resolver
- Add additional arguments
- Provide a more specific type (that implements the interface's type)

### Inheriting Fields Without Redefining

If you don't need to customize an interface field, you can simply implement the interface and the field will be inherited automatically:

```php
register_graphql_object_type( 'SimpleType', [
  'interfaces' => [ 'MyInterface' ],
  'fields' => [
    // sharedField is automatically inherited from MyInterface
    'otherField' => [
      'type' => 'String',
    ],
  ],
] );
```

### Overriding Interface Field Resolvers

You can override the resolver for an interface field while keeping the same type and arguments:

```php
register_graphql_object_type( 'CustomType', [
  'interfaces' => [ 'MyInterface' ],
  'fields' => [
    'sharedField' => [
      'type' => 'String',
      'resolve' => function( $source ) {
        // Custom resolver logic
        return 'custom value';
      },
    ],
  ],
] );
```

### Narrowing Interface Field Types

You can narrow an interface field's return type to a more specific type that implements the interface's type. This is useful when an interface field returns a union or interface type:

```php
// Register an interface with a field that returns ContentNode
register_graphql_interface_type( 'NodeWithContent', [
  'fields' => [
    'content' => [
      'type' => 'ContentNode',
      'description' => 'Content from the node',
    ],
  ],
] );

// Implement the interface and narrow the type to Post
register_graphql_object_type( 'PostWithContent', [
  'interfaces' => [ 'NodeWithContent' ],
  'fields' => [
    'content' => [
      'type' => 'Post', // Narrowed from ContentNode to Post
    ],
  ],
] );
```

You can also use `register_graphql_field()` to narrow interface field types:

```php
add_action( 'graphql_register_types', function() {
  register_graphql_field( 'Post', 'content', [
    'type' => 'Post', // Narrow the ContentNode type to Post
  ] );
} );
```

### Compatible Override Rules

WPGraphQL allows an inherited interface field to be overridden when the override is **structurally compatible** with the interface's declared field type. If the override is not compatible, WPGraphQL leaves the inherited field in place and emits a `DUPLICATE_FIELD` debug message (visible in the response's `extensions.debug` when `GRAPHQL_DEBUG` is enabled).

An override is compatible when **both** of the following are true:

1. **Wrappers match exactly.** If the interface's field is declared as `[ list_of => 'ContentNode' ]`, the override must also be `list_of` wrapping its base type — same for `non_null` and any nested combination of the two.
2. **The base type is compatible.** Either the override's base type has the same name as the interface's base type, **or** the interface's base type is itself an interface and the override's base type implements that interface.

If either rule is violated, the override is rejected with a `DUPLICATE_FIELD` debug message and the original interface field is preserved on the type.

### Narrowing Wrapped Interface Field Types

Wrappers (`list_of`, `non_null`) must match exactly between the interface declaration and the override. You can narrow the inner type, but you can't change the wrappers themselves.

```php
// Interface declares a list of an interface type
register_graphql_interface_type( 'NodeWithChoices', [
  'fields' => [
    'choices' => [
      'type' => [ 'list_of' => 'ChoiceInterface' ],
    ],
  ],
] );

// Override on Post narrows the inner type to a concrete implementing type.
// Same `list_of` wrapper, narrower base. Allowed.
register_graphql_field( 'Post', 'choices', [
  'type' => [ 'list_of' => 'RadioChoice' ], // RadioChoice implements ChoiceInterface
] );

// This override would be rejected (DUPLICATE_FIELD) because the wrappers
// don't match: the interface declares `list_of` but the override drops it.
register_graphql_field( 'Post', 'choices', [
  'type' => 'RadioChoice',
] );
```

### Same-Type Overrides

Overriding a field whose type is the same type being registered is supported and recursion-safe. For example, narrowing a `ContentNode`-typed field on `Post` to type `Post` works without triggering infinite recursion during schema construction.

```php
register_graphql_field( 'Post', 'related', [
  'type' => 'Post', // Safe even though Post is still being constructed
] );
```

This was previously a source of infinite-loop errors; see [issue #3540](https://github.com/wp-graphql/wp-graphql/issues/3540) for background.

### Behavioral Change Note

Prior to WPGraphQL `@since x-release-please-version`, some duplicate-field overrides were permitted inconsistently based on type-loading order. Overrides are now consistently accepted only when verifiably compatible by the rules above; otherwise `DUPLICATE_FIELD` is emitted. Extensions that relied on the older permissive behavior may need to adjust to declare wrappers and base types that match the inherited interface field.

This is documented as a correctness fix rather than a breaking change, because the new behavior aligns with valid GraphQL schema rules.

## Interface Field Argument Merging

When interfaces implement other interfaces, or when object types implement interfaces, field arguments are automatically merged. This allows you to build up arguments across an inheritance chain.

### Merging Arguments Across Interface Inheritance

When a child interface implements a parent interface and both define the same field with different arguments, the arguments are merged:

```php
// Parent interface with one argument
register_graphql_interface_type( 'ParentInterface', [
  'fields' => [
    'inheritedField' => [
      'type' => 'String',
      'args' => [
        'parentArg' => [
          'type' => 'String',
          'description' => 'Argument from parent interface',
        ],
      ],
    ],
  ],
] );

// Child interface that adds another argument
register_graphql_interface_type( 'ChildInterface', [
  'interfaces' => [ 'ParentInterface' ],
  'fields' => [
    'inheritedField' => [
      'type' => 'String',
      'args' => [
        'childArg' => [
          'type' => 'String',
          'description' => 'Argument from child interface',
        ],
      ],
    ],
  ],
] );
```

When `ChildInterface` is registered, the `inheritedField` will have both `parentArg` and `childArg` available.

### Merging Arguments from Object Types

Object types that implement interfaces can also add their own arguments to interface fields:

```php
register_graphql_object_type( 'MyObject', [
  'interfaces' => [ 'ChildInterface' ],
  'fields' => [
    'inheritedField' => [
      'type' => 'String',
      'args' => [
        'objectArg' => [
          'type' => 'String',
          'description' => 'Argument from object type',
        ],
      ],
      'resolve' => function( $source, $args ) {
        // All three arguments are available: parentArg, childArg, and objectArg
        return $args['parentArg'] . ' ' . $args['childArg'] . ' ' . $args['objectArg'];
      },
    ],
  ],
] );
```

The `inheritedField` on `MyObject` will have all three arguments: `parentArg`, `childArg`, and `objectArg`.

### Argument Type Compatibility

When merging arguments, WPGraphQL ensures type compatibility. If the same argument name is defined with different types in the inheritance chain, a debug message will be logged and the argument will not be merged:

```php
// This will log a debug message if parentArg has a different type
register_graphql_object_type( 'IncompatibleType', [
  'interfaces' => [ 'ParentInterface' ],
  'fields' => [
    'inheritedField' => [
      'type' => 'String',
      'args' => [
        'parentArg' => [
          'type' => 'Int', // Different type - will cause a debug message
        ],
      ],
    ],
  ],
] );
```

## Interfaces Implementing Interfaces

Interfaces can implement other interfaces, creating an inheritance chain:

```php
register_graphql_interface_type( 'BaseInterface', [
  'fields' => [
    'baseField' => [
      'type' => 'String',
    ],
  ],
] );

register_graphql_interface_type( 'ExtendedInterface', [
  'interfaces' => [ 'BaseInterface' ],
  'fields' => [
    'extendedField' => [
      'type' => 'String',
    ],
  ],
] );

register_graphql_object_type( 'MyType', [
  'interfaces' => [ 'ExtendedInterface' ],
  'fields' => [
    'customField' => [
      'type' => 'String',
    ],
  ],
] );
```

`MyType` will have all three fields: `baseField` (from `BaseInterface`), `extendedField` (from `ExtendedInterface`), and `customField` (from the type itself).

## Querying Interface Fields

When querying types that implement interfaces, you can use inline fragments to access type-specific fields:

```graphql
query {
  myType {
    # Fields from the interface
    sharedField
    
    # Type-specific fields
    customField
    
    # Using inline fragments for type narrowing
    ... on MyType {
      customField
    }
  }
}
```

## Common Use Cases

### Creating Reusable Field Definitions

Interfaces are perfect for defining fields that should be available on multiple types:

```php
register_graphql_interface_type( 'Timestamped', [
  'fields' => [
    'createdAt' => [
      'type' => 'String',
      'resolve' => function( $source ) {
        return get_post_time( 'c', false, $source->ID );
      },
    ],
    'updatedAt' => [
      'type' => 'String',
      'resolve' => function( $source ) {
        return get_post_modified_time( 'c', false, $source->ID );
      },
    ],
  ],
] );
```

### Polymorphic Queries

Interfaces enable polymorphic queries where you can query different types through a common interface:

```graphql
query {
  nodes(ids: ["...", "..."]) {
    ... on Post {
      title
    }
    ... on Page {
      title
    }
    ... on CustomPostType {
      title
    }
  }
}
```

## Best Practices

1. **Use interfaces for shared behavior**: If multiple types share common fields, define them in an interface
2. **Keep interfaces focused**: Each interface should represent a single concept or capability
3. **Document your interfaces**: Provide clear descriptions for interface fields
4. **Test argument merging**: When using argument merging, test that all expected arguments are available
5. **Ensure type compatibility**: When overriding interface field arguments, ensure types are compatible

## See Also

- [GraphQL Resolvers](/docs/graphql-resolvers/) - Learn about field resolvers
- [Custom Post Types](/docs/custom-post-types/) - Register custom post types with interfaces
- [Custom Types](/docs/custom-types/) - Create custom object types

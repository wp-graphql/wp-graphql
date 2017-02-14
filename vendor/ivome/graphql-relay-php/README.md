# Relay Library for graphql-php

This is a library to allow the easy creation of Relay-compliant servers using
the [graphql-php](https://github.com/webonyx/graphql-php) reference implementation
of a GraphQL server.

[![Build Status](https://travis-ci.org/ivome/graphql-relay-php.svg?branch=master)](https://travis-ci.org/ivome/graphql-relay-php)
[![Coverage Status](https://coveralls.io/repos/github/ivome/graphql-relay-php/badge.svg?branch=master)](https://coveralls.io/github/ivome/graphql-relay-php?branch=master)

*Note: The code is a port of the original [graphql-relay js implementation](https://github.com/graphql/graphql-relay-js)
from Facebook* (With some minor PHP related adjustments and extensions)

## Current Status: 

The basic functionality with the helper functions is in place along with the tests. Only the asynchronous functionality
was not yet ported due to the limitations of PHP. 
See also discussions [here](https://github.com/ivome/graphql-relay-php/issues/1) and [here](https://github.com/webonyx/graphql-php/issues/42)

## Getting Started

A basic understanding of GraphQL and of the graphql-php implementation is needed
to provide context for this library.

An overview of GraphQL in general is available in the
[README](https://github.com/facebook/graphql/blob/master/README.md) for the
[Specification for GraphQL](https://github.com/facebook/graphql).

This library is designed to work with the 
[graphql-php](https://github.com/webonyx/graphql-php) reference implementation
of a GraphQL server.

An overview of the functionality that a Relay-compliant GraphQL server should
provide is in the [GraphQL Relay Specification](https://facebook.github.io/relay/docs/graphql-relay-specification.html)
on the [Relay website](https://facebook.github.io/relay/). That overview
describes a simple set of examples that exist as [tests](tests) in this
repository. A good way to get started with this repository is to walk through
that documentation and the corresponding tests in this library together.

## Using Relay Library for graphql-php

Install this repository via composer: 

```sh
composer require ivome/graphql-relay-php
```

When building a schema for [graphql-php](https://github.com/webonyx/graphql-php),
the provided library functions can be used to simplify the creation of Relay
patterns.

### Connections 

Helper functions are provided for both building the GraphQL types
for connections and for implementing the `resolve` method for fields
returning those types.

 - `Relay::connectionArgs` returns the arguments that fields should provide when they
return a connection type that supports bidirectional pagination.
 - `Relay::forwardConnectionArgs` returns the arguments that fields should provide
when they return a connection type that only supports forward pagination.
 - `Relay::backwardConnectionArgs` returns the arguments that fields should provide
when they return a connection type that only supports backward pagination.
 - `Relay::connectionDefinitions` returns a `connectionType` and its associated
`edgeType`, given a node type.
 - `Relay::edgeType` returns a new `edgeType`
 - `Relay::connectionType` returns a new `connectionType`
 - `Relay::connectionFromArray` is a helper method that takes an array and the
arguments from `connectionArgs`, does pagination and filtering, and returns
an object in the shape expected by a `connectionType`'s `resolve` function.
 - `Relay::cursorForObjectInConnection` is a helper method that takes an array and a
member object, and returns a cursor for use in the mutation payload.

An example usage of these methods from the [test schema](tests/StarWarsSchema.php):

```php
$shipConnection = Relay::connectionDefinitions([
    'nodeType' => $shipType
]);

// this could also be written as
//
// $shipEdge = Relay::edgeType([
//     'nodeType' => $shipType
// ]);
// $shipConnection = Relay::connectionType([
//     'nodeType' => $shipType,
//     'edgeType' => $shipEdge
// ]);

$factionType = new ObjectType([
    'name' => 'Faction',
    'description' => 'A faction in the Star Wars saga',
    'fields' => function() use ($shipConnection) {
        return [
            'id' => Relay::globalIdField(),
            'name' => [
                'type' => Type::string(),
                'description' => 'The name of the faction.'
            ],
            'ships' => [
                'type' => $shipConnection['connectionType'],
                'description' => 'The ships used by the faction.',
                'args' => Relay::connectionArgs(),
                'resolve' => function($faction, $args) {
                    // Map IDs from faction back to ships
                    $data = array_map(function($id) {
                        return StarWarsData::getShip($id);
                    }, $faction['ships']);
                    return Relay::connectionFromArray($data, $args);
                }
            ]
        ];
    },
    'interfaces' => [$nodeDefinition['nodeInterface']]
]);
```

This shows adding a `ships` field to the `Faction` object that is a connection.
It uses `connectionDefinitions({nodeType: shipType})` to create the connection
type, adds `connectionArgs` as arguments on this function, and then implements
the resolve function by passing the array of ships and the arguments to
`connectionFromArray`.

### Object Identification

Helper functions are provided for both building the GraphQL types
for nodes and for implementing global IDs around local IDs.

 - `Relay::nodeDefinitions` returns the `Node` interface that objects can implement,
and returns the `node` root field to include on the query type. To implement
this, it takes a function to resolve an ID to an object, and to determine
the type of a given object.
 - `Relay::toGlobalId` takes a type name and an ID specific to that type name,
and returns a "global ID" that is unique among all types.
 - `Relay::fromGlobalId` takes the "global ID" created by `toGlobalID`, and returns
the type name and ID used to create it.
 - `Relay::globalIdField` creates the configuration for an `id` field on a node.
 - `Relay::pluralIdentifyingRootField` creates a field that accepts a list of
non-ID identifiers (like a username) and maps then to their corresponding
objects.

An example usage of these methods from the [test schema](tests/StarWarsSchema.php):

```php
$nodeDefinition = Relay::nodeDefinitions(
    // The ID fetcher definition
    function ($globalId) {
        $idComponents = Relay::fromGlobalId($globalId);
        if ($idComponents['type'] === 'Faction'){
            return StarWarsData::getFaction($idComponents['id']);
        } else if ($idComponents['type'] === 'Ship'){
            return StarWarsData::getShip($idComponents['id']);
        } else {
            return null;
        }
    },
    // Type resolver
    function ($object) {
        return isset($object['ships']) ? self::getFactionType() : self::getShipType();
    }
);

$factionType = new ObjectType([
    'name' => 'Faction',
    'description' => 'A faction in the Star Wars saga',
    'fields' => function() use ($shipConnection) {
        return [
            'id' => Relay::globalIdField(),
            'name' => [
                'type' => Type::string(),
                'description' => 'The name of the faction.'
            ],
            'ships' => [
                'type' => $shipConnection['connectionType'],
                'description' => 'The ships used by the faction.',
                'args' => Relay::connectionArgs(),
                'resolve' => function($faction, $args) {
                    // Map IDs from faction back to ships
                    $data = array_map(function($id) {
                        return StarWarsData::getShip($id);
                    }, $faction['ships']);
                    return Relay::connectionFromArray($data, $args);
                }
            ]
        ];
    },
    'interfaces' => [$nodeDefinition['nodeInterface']]
]);

$queryType = new ObjectType([
    'name' => 'Query',
    'fields' => function () use ($nodeDefinition) {
        return [
            'node' => $nodeDefinition['nodeField']
        ];
    },
]);
```

This uses `Relay::nodeDefinitions` to construct the `Node` interface and the `node`
field; it uses `fromGlobalId` to resolve the IDs passed in in the implementation
of the function mapping ID to object. It then uses the `Relay::globalIdField` method to
create the `id` field on `Faction`, which also ensures implements the
`nodeInterface`. Finally, it adds the `node` field to the query type, using the
`nodeField` returned by `Relay::nodeDefinitions`.

### Mutations

A helper function is provided for building mutations with
single inputs and client mutation IDs.

 - `Relay::mutationWithClientMutationId` takes a name, input fields, output fields,
and a mutation method to map from the input fields to the output fields,
performing the mutation along the way. It then creates and returns a field
configuration that can be used as a top-level field on the mutation type.

An example usage of these methods from the [test schema](tests/StarWarsSchema.php):

```php
$shipMutation = Relay::mutationWithClientMutationId([
    'name' => 'IntroduceShip',
    'inputFields' => [
        'shipName' => [
            'type' => Type::nonNull(Type::string())
        ],
        'factionId' => [
            'type' => Type::nonNull(Type::id())
        ]
    ],
    'outputFields' => [
        'ship' => [
            'type' => $shipType,
            'resolve' => function ($payload) {
                return StarWarsData::getShip($payload['shipId']);
            }
        ],
        'faction' => [
            'type' => $factionType,
            'resolve' => function ($payload) {
                return StarWarsData::getFaction($payload['factionId']);
            }
        ]
    ],
    'mutateAndGetPayload' => function ($input) {
        $newShip = StarWarsData::createShip($input['shipName'], $input['factionId']);
        return [
            'shipId' => $newShip['id'],
            'factionId' => $input['factionId']
        ];
    }
]);

$mutationType = new ObjectType([
    'name' => 'Mutation',
    'fields' => function () use ($shipMutation) {
        return [
            'introduceShip' => $shipMutation
        ];
    }
]);
```

This code creates a mutation named `IntroduceShip`, which takes a faction
ID and a ship name as input. It outputs the `Faction` and the `Ship` in
question. `mutateAndGetPayload` then gets an object with a property for
each input field, performs the mutation by constructing the new ship, then
returns an object that will be resolved by the output fields.

Our mutation type then creates the `introduceShip` field using the return
value of `Relay::mutationWithClientMutationId`.

## Contributing

After cloning this repo, ensure dependencies are installed by running:

```sh
composer install
```

After developing, the full test suite can be evaluated by running:

```sh
bin/phpunit tests
```

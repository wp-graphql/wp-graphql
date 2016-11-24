# GraphQL

[![Join the chat at https://gitter.im/Youshido/GraphQL](https://badges.gitter.im/Youshido/GraphQL.svg)](https://gitter.im/Youshido/GraphQL?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)
[![Build Status](https://travis-ci.org/Youshido/GraphQL.svg?branch=master)](http://travis-ci.org/Youshido/GraphQL)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/Youshido/GraphQL/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/Youshido/GraphQL/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/Youshido/GraphQL/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/Youshido/GraphQL/?branch=master)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/8b8ab2a2-32fb-4298-a986-b75ca523c7c9/mini.png)](https://insight.sensiolabs.com/projects/8b8ab2a2-32fb-4298-a986-b75ca523c7c9)

This is a pure PHP realization of the GraphQL protocol based on the working draft of the official GraphQL Specification located on http://facebook.github.io/graphql/.

GraphQL is a modern replacement of the almost obsolete REST approach to present API. It's been almost 16 years since the REST idea was found in 2000 by Roy Fielding. With all credit to everything we accomplished using REST it's time to change for something better.
GraphQL advanced in many ways and has fundamental improvements over the old good REST:

 - self-checks embedded on the ground level of your backend architecture
 - reusable API for different client versions and devices, i.e. no more need in maintaining "/v1" and "/v2"
 - a complete new level of distinguishing of the backend and frontend logic
 - easily generated documentation and incredibly intuitive way to explore created API
 - once your architecture is complete – most client-based changes does not require backend modifications

It could be hard to believe but give it a try and you'll be rewarded with much better architecture and so much easier to support code.

> Current package is and will be trying to be kept up to date with the latest revision of the official GraphQL Specification which is now of April 2016.

> Symfony bundle is available by the link – [http://github.com/Youshido/GraphqlBundle](http://github.com/Youshido/GraphqlBundle)

> If you have any questions or suggestions – let's talk on [GraphQL Gitter channel](https://gitter.im/Youshido/GraphQL)

## Table of Contents

* [Getting Started](#getting-started)
* [Installation](#installation)
* [Example – Creating Blog Schema](#tutorial--creating-blog-schema)
  * [Inline approach](#inline-approach)
  * [Object Oriented approach](#object-oriented-approach)
  * [Choosing approach for your project](#choosing-approach-for-your-project)
* [Query Documents](#query-documents)
* [Type System](#type-system)
  * [Scalar Types](#scalar-types)
  * [Objects](#objects)
  * [Interfaces](#interfaces)
  * [Enums](#enums)
  * [Unions](#unions)
  * [Lists](#lists)
  * [Input Objects](#input-objects)
  * [Non-Null](#non-null)
* [Building your schema](#building-your-schema)
  * [Abstract type classes](#abstract-type-classes)
  * [Mutation helper class](#mutation-helper-class)
* [Useful information](#useful-information)
  * [GraphiQL tool](#graphiql-tool)

## Getting Started

You should be better off starting with some examples and "Star Wars" become a somewhat "Hello world" for the GraphQL implementations.
If you're looking just for that – you can get it via this link – [Star Wars example](https://github.com/Youshido/GraphQL/tree/master/Tests/StarWars).
On the other hand, we prepared a step-by-step guide for those who wants to get up to speed bit by bit.

### Installation

Install GraphQL package using composer. If you're not familiar with it, you should check out their [manual](https://getcomposer.org/doc/00-intro.md).
Add the following package to your `composer.json` (or simply create a new file with this content):

 ```
 {
     "require": {
         "youshido/graphql": "^1.2"
     }
 }
 ```
Run `composer update`.

Alternatively you can run the following commands:
```sh
mkdir graphql-test && cd graphql-test
composer init -n
composer require youshido/graphql
```

Now you're ready to create your `GraphQL Schema` and check if everything works fine.
Your first GraphQL app will be able to receive `currentTime` request and response with a formatted time string.
> you can find this example in the examples directory – [01_sandbox](https://github.com/Youshido/GraphQL/examples/01_sandbox).

Create an `index.php` file with the following content:
```php
<?php
namespace Sandbox;

use Youshido\GraphQL\Execution\Processor;
use Youshido\GraphQL\Schema\Schema;
use Youshido\GraphQL\Type\Object\ObjectType;
use Youshido\GraphQL\Type\Scalar\StringType;

require_once 'vendor/autoload.php';

$processor = new Processor(new Schema([
    'query' => new ObjectType([
        'name' => 'RootQueryType',
        'fields' => [
            'currentTime' => [
                'type' => new StringType(),
                'resolve' => function() {
                    return date('Y-m-d H:ia');
                }
            ]
        ]
    ])
]));

$processor->processPayload('{ currentTime }');
echo json_encode($processor->getResponseData()) . "\n";
```

You can now execute `php index.php` and get a response with your current time:
 ```js
 {
    data: { currentTime: "2016-05-01 19:27pm" }
 }
 ```
Just like that, you have created a `GraphQL Schema` with a `field` `currentTime` of type `String` and `resolver` for it. Don't worry if you don't know what the `field`, `type` and `resolver` mean here, you'll learn along the way.

If you're having any troubles – here're some troubleshooting points:
* check that you have the latest composer version (`composer self-update`)
* make sure your `index.php` file has been created in the same directory that you have `vendor` folder in (presumably it's `graphql-test` folder)
* last but not least, check that you have php-cli installed and running and it's version >= 5.5 (`php -v`)

Also, you can always check if script from the [examples folder](https://github.com/Youshido/GraphQL/examples/01_sandbox) work.

## Tutorial – Creating Blog Schema

For our learning example we'll architect a GraphQL Schema for a Blog.
You'll probably be using our package along with your favorite framework (we have a Symfony version [here](http://github.com/Youshido/GraphqlBundle)), but for the purpose of this tutorial we're keeping it all examples as plain php code.
>> (Complete example of the Blog schema available by the following link https://github.com/Youshido/GraphQL/examples/02_Blog)

Our Blog will have `Users` who can write `Posts` and leave `Comments`. Also, there will be a `LikePost` operation that could be performed by anyone.
Let's start with `Post`. Take a look at the query that returns `title` and `summary` of the latest Post:
>> GraphQL query is a simple text query structured very much similar to the json format.
 ```
 latestPost {
     title,
     summary
 }
 ```

Supposedly server should reply with a relevant json response:
 ```js
 {
    data: {
        latestPost: {
            title: "This is a post title",
            summary: "This is a post summary"
        }
    }
 }
 ```
It looks very simple and straight forward, so let's go ahead and write code that can handle this request.

### Creating Post schema

We'll take a quick look on different approaches you can use to define your schema.
Each of them has it's own pros and cons, inline approach might seem to be easier and faster when object oriented gives you more flexibility and freedom as your project grows.
You should definitely use OOP approach every time you can reuse the type you're creating.

We're going to create `RootQueryType` with one field `latestPost`.
Every `GraphQL Field` has a `type`(e.g. String, Int, Boolean) and it could be of a different `kind`(e.g. Scalar, Enum, List). You can read more about it in the [official documentation](https://facebook.github.io/graphql/#sec-Type-System), but for now you can think of `field of a type` like about `instance of a class`.

#### Inline approach

You can create `inline-index.php` file in your project folder and paste the following code there

**inline-index.php**
```php
<?php
namespace InlineSchema;

use Youshido\GraphQL\Execution\Processor;
use Youshido\GraphQL\Schema\Schema;
use Youshido\GraphQL\Type\Object\ObjectType;
use Youshido\GraphQL\Type\Scalar\StringType;

// including autoloader
require_once __DIR__ . '/vendor/autoload.php';

// instantiating Processor and setting the schema
$processor = new Processor(new Schema([
    'query' => new ObjectType([
        // root query by convention has a name RootQueryType
        'name'   => 'RootQueryType',
        'fields' => [
            'latestPost' => [
                'type'    => new ObjectType([ // Post type is being created as ObjectType
                    'name'    => 'Post', // name of our type – "Post"
                    'fields'  => [
                        'title'   => new StringType(),  // defining "title" field, type - String
                        'summary' => new StringType(),  // defining "summary" field, type - String
                    ],
                ]),
                'resolve' => function () {          // resolver for latestPost field
                    return [                        // for now it returns a static array with data
                        "title"   => "New approach in API has been revealed",
                        "summary" => "In two words - GraphQL Rocks!",
                    ];
                }
            ]
        ]
    ])
]));

// creating payload and running it through processor
$payload = '{ latestPost { title, summary } }';
$processor->processPayload($payload);
// displaying result
echo json_encode($processor->getResponseData()) . "\n";
```

To check if everything is working – execute index.php: `php index.php`
You should see response as the json encoded object `latestPost` inside the `data` section:
 ```js
 {
    data: {
        latestPost: {
            title: "New approach in API has been revealed",
            summary: "In two words - GraphQL Rocks!"
        }
    }
 }
 ```

> Try to play with the code by removing one field from the request or by changing the resolve function.

#### Object oriented approach

It's a common situation when you need to use the same custom type in different places, so we're going to create a separate class for the `PostType` and use it in our `GraphQL Schema`.
To keep everything structured we're going to put this and all our future classes into the `Schema` folder.

Create a file `Schema/PostType.php` and put the following code in there:
```php
<?php
namespace Examples\Blog\Schema;

use Youshido\GraphQL\Type\Object\AbstractObjectType;
use Youshido\GraphQL\Type\Scalar\StringType;

class PostType extends AbstractObjectType   // extending abstract Object type
{

    public function build($config)  // implementing an abstract function where you build your type
    {
        $config
            ->addField('title', new StringType())       // defining "title" field of type String
            ->addField('summary', new StringType());    // defining "summary" field of type String
    }

    public function getName()
    {
        return "Post";  // if you don't do getName – className without "Type" will be used
    }

}
```

Now let's create the main entry point for this example – `index.php`:
```php
<?php

namespace Examples\Blog;

use Examples\Blog\Schema\PostType;
use Youshido\GraphQL\Execution\Processor;
use Youshido\GraphQL\Schema\Schema;
use Youshido\GraphQL\Type\Object\ObjectType;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/Schema/PostType.php';       // including PostType definition

$rootQueryType = new ObjectType([
    'name' => 'RootQueryType',
    'fields' => [
        'latestPost' => [
            'type'    => new PostType(),
            'resolve' => function ($source, $args, $info)
            {
                return [
                    "title"   => "New approach in API has been revealed",
                    "summary" => "In two words - GraphQL Rocks!",
                ];
            }
        ]
    ]
]);

$processor = new Processor(new Schema([
    'query' => $rootQueryType
]));
$payload = '{ latestPost { title, summary } }';

$processor->processPayload($payload);
echo json_encode($processor->getResponseData()) . "\n";
```

Ensure everything is working properly by running `php index.php`. You should see the same response you saw for the inline approach.

Next step would be to create a separate class for the latestPostField by extending `AbstractField` class:
**Schema/LatestPostField.php**
```php
<?php

namespace Examples\Blog\Schema;

use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Field\AbstractField;

class LatestPostField extends AbstractField
{
    public function getType()
    {
        return new PostType();
    }

    public function resolve($value, array $args, ResolveInfo $info)
    {
        return [
            "title"   => "New approach in API has been revealed",
            "summary" => "In two words - GraphQL Rocks!",
        ];
    }
}
```
And now we can update our `index.php`:
```php
<?php

namespace Examples\Blog;

use Examples\Blog\Schema\LatestPostField;
use Youshido\GraphQL\Execution\Processor;
use Youshido\GraphQL\Schema\Schema;
use Youshido\GraphQL\Type\Object\ObjectType;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/Schema/PostType.php';       // including PostType definition
require_once __DIR__ . '/Schema/LatestPostField.php';

$rootQueryType = new ObjectType([
    'name' => 'RootQueryType',
    'fields' => [
        new LatestPostField()
    ]
]);

$processor = new Processor(new Schema([
    'query' => $rootQueryType
]));
$payload = '{ latestPost { title, summary } }';

$processor->processPayload($payload);
echo json_encode($processor->getResponseData()) . "\n";
```

### Choosing approach for your project

We would recommend to stick to object oriented approach for the several reasons (that matter the most for the GraphQL specifically):
 - makes your `Types` reusable
 - adds an ability to refactor your schema using IDEs
 - autocomplete to help you avoid typos
 - much easier to navigate through your Schema when project grows

 With that being said, we use inline approach a lot to explore and bootstrap ideas or to develop simple fields/resolver that are going to be used in one place only.
 With the inline approach you can be fast and agile in creating mock-data server to test your frontend or mobile client.

> **Use valid Names**  
> We highly recommend to get familiar with the [official GraphQL Specification](https://facebook.github.io/graphql/#sec-Language.Query-Document)
> Remember that valid identifier in GraphQL should follow the pattern `/[_A-Za-z][_0-9A-Za-z]*/`.
> That means any identifier should consist of a latin letter, underscore, or a digit and cannot start with a digit.
> **Names are case sensitive**

We'll continue to work on the Blog Schema to explore all essentials details of developing GraphQL server.

## Query Documents

In GraphQL terms – query document describe a complete request received by GraphQL service.
It contains list of *Operations* and *Fragments*. Both are fully supported by our PHP library.
There are two types of *Operations* in GraphQL:
- *Query* – a read only request that is not supposed to do any changes on the server
- *Mutation* – a request that changes(mutate) data on the server followed by a data fetch

You've already seen examples of `Query` with `latestPost` and `currentTime`, so let's define a simple Mutation that will provide API to *Like* the Post.
Here's sample request and response of `likePost` mutation:

*request*
```
mutation {
  likePost(id: 5)
}
```
*response*
```js
{
  data: { likePost: 2 }
}
```
> Any Operation has a response type and in this case the likePost mutation type is `Int`

Note, that the response type of this mutation is a scalar `Int`.
Of course in real life you'll more likely have a response of type `Post` for such mutation, but we're going to implement code for a simple example above and even keep it inside `index.php`:

```php
<?php

namespace Examples\Blog;

use Examples\Blog\Schema\LatestPostField;
use Youshido\GraphQL\Execution\Processor;
use Youshido\GraphQL\Schema\Schema;
use Youshido\GraphQL\Type\NonNullType;
use Youshido\GraphQL\Type\Object\ObjectType;
use Youshido\GraphQL\Type\Scalar\IntType;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/Schema/PostType.php';       // including PostType definition
require_once __DIR__ . '/Schema/LatestPostField.php';

$rootQueryType = new ObjectType([
    'name'   => 'RootQueryType',
    'fields' => [
        new LatestPostField()
    ]
]);

$rootMutationType = new ObjectType([
    'name'   => 'RootMutationType',
    'fields' => [
        // defining likePost mutation field
        'likePost' => [
            // we specify the output type – simple Int, since it doesn't have a structure
            'type'    => new IntType(),
            // we need a post ID and we set it to be required Int
            'args'    => [
                'id' => new NonNullType(new IntType())
            ],
            // simple resolve function that always returns 2
            'resolve' => function () {
                return 2;
            },
        ]
    ]
]);

$processor = new Processor(new Schema([
    'query'    => $rootQueryType,
    'mutation' => $rootMutationType
]));
$payload   = 'mutation { likePost(id: 5) }';

$processor->processPayload($payload);
echo json_encode($processor->getResponseData()) . "\n";
```

Run `php index.php`, you should see a valid response:
```js
{"data":{"likePost":2}}
```

Now, let's make our `likePost` mutation to return the whole `Post` as a result.
First, we'll add `likeCount` field to the `PostType`:
```php
<?php
namespace Examples\Blog\Schema;

use Youshido\GraphQL\Type\Object\AbstractObjectType;
use Youshido\GraphQL\Type\Scalar\IntType;
use Youshido\GraphQL\Type\Scalar\StringType;

class PostType extends AbstractObjectType
{

    public function build($config)
    {
        // you can define fields in a single addFields call instead of chaining multiple addField()
        $config->addFields([
            'title'      => new StringType(),
            'summary'    => new StringType(),
            'likesCount' => new IntType()
        ]);
    }

    // Since our class named by a convention, we can remove getName() method
}
```
Secondly, modify `resolve` function in `LatestPostField`:
```php
public function resolve($value, array $args, ResolveInfo $info)
{
    return [
        "title"      => "New approach in API has been revealed",
        "summary"    => "In two words - GraphQL Rocks!",
        "likesCount" => 2
    ];
}
```

Lastly, we're going to change `Mutation Type` from `IntType` to `PostType` and update the `resolve` function to be complaint with the the new type and update the request:
```php
<?php
// ...
$rootMutationType = new ObjectType([
    'name'   => 'RootMutationType',
    'fields' => [
        'likePost' => [
            'type'    => new PostType(),
            'args'    => [
                'id' => new NonNullType(new IntType())
            ],
            'resolve' => function () {
                return [
                    'title'     => 'New approach in API has been revealed',
                    'summary'   => 'In two words - GraphQL Rocks!',
                    'likesCount' => 2
                ];
            },
        ]
    ]
]);
// ...
$payload   = 'mutation { likePost(id: 5) { title, likesCount } }';
//...
```

Execute `php index.php`, you should see `title` and `likesCount` in response. We can now try to use `id: 5` that we're passing as a parameter to our mutation:
```php
$rootMutationType = new ObjectType([
    'name'   => 'RootMutationType',
    'fields' => [
        'likePost' => [
            'type'    => new PostType(),
            'args'    => [
                'id' => new NonNullType(new IntType())
            ],
            'resolve' => function ($source, $args, $resolveInfo) {
                return [
                    'title'      => 'Title for the post #' . $args['id'], // we can be sure that $args['id'] is always set
                    'summary'    => 'In two words - GraphQL Rocks!',
                    'likesCount' => 2
                ];
            },
        ]
    ]
]);
```

Now you have a basic understanding of how queries and mutations are structured and ready to move on to the details of the GraphQL Type System and PHP-specific features of the GraphQL server architecture.

## Type System

*Type* is an atom of definition in GraphQL Schema. Every field, object, or argument has a type. GraphQL is a strongly typed language.
There are `system types` and `custom types` defined specifically for the application, in our app we'll have custom  types  `Post`, `User`, `Comment`, etc. Your custom types are usually built on top of GraphQL system types.

### Scalar Types

List of GraphQL Scalar types:
- Int
- Float
- String
- Boolean
- Id (serialized as String per [spec](https://facebook.github.io/graphql/#sec-ID))

In addition, we implemented some types that might be useful and which we're considering to be scalar as well:
- Timestamp
- DateTimeTz (» RFC 2822 formatted date with TimeZone)
>> Date and DateTime are deprecated and will be remove. We're going to provide an easy solution how to replace them in your project

If you will ever need to define a new Scalar type, you can do that by extending from the `AbstractScalarType` class.
> usage of scalar types will be shown in combination with other types down here

### Objects

Every entity in your business logic will probably have a class that represents it's type. That class must be either extended from the `AbstractObjectType` or created as an instance of `ObjectType`.
In our blog example we used `ObjectType` to create an inline `PostType` and extended `AbstractObjectType` to create a `PostType` class in the object oriented approach.

Let's take a closer look at the structure of `PostType` and see what parameters we can configure for each field.

```php
<?php
namespace Examples\Blog\Schema;

use Youshido\GraphQL\Type\Object\AbstractObjectType;
use Youshido\GraphQL\Type\Scalar\BooleanType;
use Youshido\GraphQL\Type\Scalar\IntType;
use Youshido\GraphQL\Type\Scalar\StringType;

class PostType extends AbstractObjectType
{

    public function build($config)
    {
        // you can define fields in a single addFields call instead of chaining multiple addField()
        $config->addFields([
            'title'      => [
                'type' => new StringType(),
                'description'       => 'This field contains a post title',
                'isDeprecated'      => true,
                'deprecationReason' => 'field title is now deprecated',
                'args'              => [
                    'truncate' => new BooleanType()
                ],
                'resolve'           => function ($source, $args) {
                    return (!empty($args['truncate'])) ? explode(' ', $source['title'])[0] . '...' : $source['title'];
                }
            ],
            'summary'    => new StringType(),
            'likesCount' => new IntType()
        ]);
    }
}
```
Now you can change `index.php` to perform requests like these:
```php
$payload   = 'mutation { likePost(id: 5) { title(truncate: true), likesCount } }';
```
As you can see we now have argument `id` for the mutation and another argument `truncate` for the field `title` inside `PostTitle`. We can use it everywhere that `PostType` is being used.

### Interfaces

GraphQL supports `Interfaces`. You can define Interface and use it as a `Type` of an item in the `List`, or use Interface to make sure that specific objects certainly have fields you need.
Each `InterfaceType` has to have at least one defined field and `resolveType` function. That function will be used to determine what exact `Type` will be returned by GraphQL resolver.
Let's create a `ContentBlockInterface` that can represent a piece of content for the web page that have a `title` and a `summary` (just like our post earlier).
```php
<?php
/**
 * ContentBlockInterface.php
 */
namespace Examples\Blog\Schema;

use Youshido\GraphQL\Type\InterfaceType\AbstractInterfaceType;
use Youshido\GraphQL\Type\NonNullType;
use Youshido\GraphQL\Type\Scalar\StringType;

class ContentBlockInterface extends AbstractInterfaceType
{
    public function build($config)
    {
        $config->addField('title', new NonNullType(new StringType()));
        $config->addField('summary', new StringType());
    }

    public function resolveType($object) {
        // since there's only one type right now this interface will always resolve PostType
        return new PostType();
    }
}
```

Most often you'll be using only the `build` method to define fields and that need to be implemented.
In order to associate this Interface to the `PostType` we have to override it's `getInterfaces` method:
```php
<?php
/**
* PostType.php
*/
namespace Examples\Blog\Schema;

use Youshido\GraphQL\Type\Object\AbstractObjectType;
use Youshido\GraphQL\Type\Scalar\IntType;
use Youshido\GraphQL\Type\Scalar\StringType;

class PostType extends AbstractObjectType
{

    public function build($config)
    {
        $config->addFields([
            'title'      => new StringType(),
            'summary'    => new StringType(),
            'likesCount' => new IntType()
        ]);
    }

    public function getInterfaces()
    {
        return [new ContentBlockInterface()];
    }
}

```
As you might have noticed there's no `getName` method in both Interface and Type classes – that's a simplified approach available when you want to have your name exactly the same as the class name without the `Type` at the end.

If you run the script as it is right now – `php index.php`, you should get an error:
```js
{"errors":[{"message":"Implementation of ContentBlockInterface is invalid for the field title"}]}
```
You've got this error because the `title` field definition in the `PostType` is different from the one described in the `ContentBlockInterface`.
To fix it we have to declare fields that exist in the `Interface` with the same names and types.
We already have `title` but it's a nullable field so we have to change it by adding a non-null wrapper – `new NonNullType(new StringType())`.
You can check the result by executing index.php script again, you should get the usual response.

For the convenience we also created `$config->applyInterface()` method that could be inside `build()`:
```php
<?php
/**
 * PostType.php
 */
namespace Examples\Blog\Schema;

use Youshido\GraphQL\Type\Object\AbstractObjectType;
use Youshido\GraphQL\Type\Scalar\IntType;

class PostType extends AbstractObjectType
{

    public function build($config)
    {
        $config->applyInterface(new ContentBlockInterface());
        $config->addFields([
            'likesCount' => new IntType()
        ]);
    }

    public function getInterfaces()
    {
        return [new ContentBlockInterface()];
    }
}
```

### Enums

GraphQL Enums are the variation on the Scalar type, which represents one of the predefined values.
Enums serialize as a string: the name of the represented value but can be associated with a numeric (as an example) value.

To show you how Enums work we're going to create a new class - `PostStatus`:
```php
<?php
/**
 * PostStatus.php
 */
namespace Examples\Blog\Schema;

use Youshido\GraphQL\Type\Enum\AbstractEnumType;

class PostStatus extends AbstractEnumType
{
    public function getValues()
    {
        return [
            [
                'value' => 0,
                'name'  => 'DRAFT',
            ],
            [
                'value' => 1,
                'name'  => 'PUBLISHED',
            ]
        ];
    }
}
```
Now, add a status field to the `PostType`:
```php
<?php
/**
 * PostType.php
 */
namespace Examples\Blog\Schema;

use Youshido\GraphQL\Type\NonNullType;
use Youshido\GraphQL\Type\Object\AbstractObjectType;
use Youshido\GraphQL\Type\Scalar\IntType;
use Youshido\GraphQL\Type\Scalar\StringType;

class PostType extends AbstractObjectType
{

    public function build($config)
    {
        $config->addFields([
            'title'      => new NonNullType(new StringType()),
            'summary'    => new StringType(),
            'likesCount' => new IntType(),
            'status'     => new PostStatus()
        ]);
    }

    public function getInterfaces()
    {
        return [new ContentBlockInterface()];
    }
}
```
and update the resolve function inside latestPost field:
```php
<?php

namespace Examples\Blog\Schema;

use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Field\AbstractField;

class LatestPostField extends AbstractField
{
    public function getType()
    {
        return new PostType();
    }

    public function resolve($value, array $args, ResolveInfo $info)
    {
        return [
            "title"      => "New approach in API has been revealed",
            "summary"    => "In two words - GraphQL Rocks!",
            "status"     => 1,
            "likesCount" => 2
        ];
    }
}
```

Request the `status` field in your query:
```php
$payload  = '{ latestPost { title, status, likeCount } }';
```
You should get a result similar to the following:
```js
{"data":{"latestPost":{"title":"New approach in API has been revealed","status":"PUBLISHED"}}}
```

### Unions

GraphQL Unions represent an object type that could be resolved as one of a specified GraphQL Object types.
To get you an idea of what this is we're going to create a new query field that will return a list of unions (and get to the `ListType` after it).
> You can consider Union as a combined type that is needed mostly when you want to have a list of different objects

Imaging that you have a page and you need to get all content blocks for this page. Let content block be either `Post` or `Banner`.
Create a `BannerType`:
```php
<?php
/**
 * BannerType.php
 */
namespace Examples\Blog\Schema;

use Youshido\GraphQL\Type\Object\AbstractObjectType;
use Youshido\GraphQL\Type\Scalar\StringType;

class BannerType extends AbstractObjectType
{
    public function build($config)
    {
        $config
            ->addField('title', new StringType())
            ->addField('imageLink', new StringType());
    }
}
```
Now let's combine the `Banner` type and the `Post` type to create a `ContentBlockUnion` that will extend an `AbstractUnionType`.
Each `UnionType` needs to define a list of types it unites by implementing the `getTypes` method and the `resolveType` method to resolve object that will be returned for each instance of the `Union`.
```php
<?php
/**
 * ContentBlockUnion.php
 */

namespace Examples\Blog\Schema;

use Youshido\GraphQL\Type\Union\AbstractUnionType;

class ContentBlockUnion extends AbstractUnionType
{
    public function getTypes()
    {
        return [new PostType(), new BannerType()];
    }

    public function resolveType($object)
    {
        // we simple look if there's a "post" inside the object id that it's a PostType otherwise it's a BannerType
        return empty($object['id']) ? null : (strpos($object['id'], 'post') !== false ? new PostType() : new BannerType());
    }
}
```

We're also going to create a simple `DataProvider` that will give us test data to operate with:
```php
<?php
/**
 * DataProvider.php
 */
namespace Examples\Blog\Schema;

class DataProvider
{
    public static function getPost($id)
    {
        return [
            "id"        => "post-" . $id,
            "title"     => "Post " . $id . " title",
            "summary"   => "This new GraphQL library for PHP works really well",
            "status"    => 1,
            "likeCount" => 2
        ];
    }

    public static function getBanner($id)
    {
        return [
            'id'        => "banner-" . $id,
            'title'     => "Banner " . $id,
            'imageLink' => "banner" . $id . ".jpg"
        ];
    }
}
```

Now, we're ready to update our Schema and include `ContentBlockUnion` into it.
As we're getting our schema bigger we'd like to extract it to a separate file as well:
```php
<?php
/**
 * BlogSchema.php
 */
namespace Examples\Blog\Schema;

use Youshido\GraphQL\Config\Schema\SchemaConfig;
use Youshido\GraphQL\Schema\AbstractSchema;
use Youshido\GraphQL\Type\ListType\ListType;

class BlogSchema extends AbstractSchema
{
    public function build(SchemaConfig $config)
    {
        $config->getQuery()->addFields([
            new LatestPostField(),
            'randomBanner'     => [
                'type'    => new BannerType(),
                'resolve' => function () {
                    return DataProvider::getBanner(rand(1, 10));
                }
            ],
            'pageContentUnion' => [
                'type'    => new ListType(new ContentBlockUnion()),
                'resolve' => function () {
                    return [DataProvider::getPost(1), DataProvider::getBanner(1)];
                }
            ]
        ]);
        $config->getMutation()->addFields([
            new LikePostField()
        ]);
    }

}
```
Having this separate schema file you should update your `index.php` to look like this:
```php
<?php

namespace Examples\Blog;

use Examples\Blog\Schema\BlogSchema;
use Youshido\GraphQL\Execution\Processor;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/Schema/PostType.php';
require_once __DIR__ . '/Schema/LatestPostField.php';
require_once __DIR__ . '/Schema/ContentBlockInterface.php';
require_once __DIR__ . '/Schema/PostStatus.php';
require_once __DIR__ . '/Schema/LikePostField.php';
require_once __DIR__ . '/Schema/BlogSchema.php';
require_once __DIR__ . '/Schema/ContentBlockUnion.php';
require_once __DIR__ . '/Schema/BannerType.php';
require_once __DIR__ . '/Schema/DataProvider.php';

$processor = new Processor(new BlogSchema());
$payload  = '{ pageContentUnion { ... on Post { title } ... on Banner { title, imageLink } } }';


$processor->processPayload($payload);
echo json_encode($processor->getResponseData()) . "\n";
```

Due to the GraphQL syntax you have to specify fields for each type of object you're getting in the union request, if you're not familiar with it read more at [official documentation(https://facebook.github.io/graphql/#sec-Unions)]
If everything was done right you should see the following response:
```js
{"data":{"pageContentUnion":[
  {"title":"Post 1 title"},
  {"title":"Banner 1","imageLink":"banner1.jpg"}
]}}
```
Also, you might want to check out how to use [GraphiQL tool](#graphiql-tool) to get a better visualization of what you're doing here.

### Lists

As you've seen in the previous example `ListType` is used to create a list of any items that are or extend GraphQL type.
List type can be also created by using `InterfaceType` as an item which gives you flexibility in defining your schema.
Let's go ahead and add `ListType` field to our BlogSchema.
```php
<?php
/**
 * BlogSchema.php
 */
namespace Examples\Blog\Schema;

use Youshido\GraphQL\Config\Schema\SchemaConfig;
use Youshido\GraphQL\Schema\AbstractSchema;
use Youshido\GraphQL\Type\ListType\ListType;

class BlogSchema extends AbstractSchema
{
    public function build(SchemaConfig $config)
    {
        $config->getQuery()->addFields([
            new LatestPostField(),
            'randomBanner'     => [
                'type'    => new BannerType(),
                'resolve' => function () {
                    return DataProvider::getBanner(rand(1, 10));
                }
            ],
            'pageContentUnion' => [
                'type'    => new ListType(new ContentBlockUnion()),
                'resolve' => function () {
                    return [DataProvider::getPost(1), DataProvider::getBanner(1)];
                }
            ],
            'pageContentInterface' => [
                'type'    => new ListType(new ContentBlockInterface()),
                'resolve' => function () {
                    return [DataProvider::getPost(2), DataProvider::getBanner(3)];
                }
            ]
        ]);
        $config->getMutation()->addFields([
            new LikePostField()
        ]);
    }

}
```

We've added a `pageContentInterface` field that have a `ListType` of `ContentBlockInterface`.  
Resolve function returns list which consists of one `Post` and one `Banner`.
To test it we'll modify our payload to the following one:
```php
<?php
$payload  = '{ pageContentInterface { title} }';
```
Be aware, because `BannerType` doesn't implement `ContentBlockInterface` you would get an error:
```js
{ "errors": [ "message": "Type Banner does not implement ContentBlockInterface" } ]}
```
To fix this we just need to add `ContentBlockInterface` by implementing `getInterfaces` method and adding the proper field definitions to our `BannerType`:
```php
<?php
/**
 * BannerType.php
 */

namespace Examples\Blog\Schema;

use Youshido\GraphQL\Type\Config\TypeConfigInterface;
use Youshido\GraphQL\Type\NonNullType;
use Youshido\GraphQL\Type\Object\AbstractObjectType;
use Youshido\GraphQL\Type\Scalar\StringType;

class BannerType extends AbstractObjectType
{
    public function build($config)
    {
        $config
            ->addField('title', new NonNullType(new StringType()))
            ->addField('summary', new StringType())
            ->addField('imageLink', new StringType());
    }

    public function getInterfaces()
    {
        return [new ContentBlockInterface()];
    }
}
```
Send the request again and you'll get a nice response with titles of the both Post and Banner:
```js
{
  "data": {
    "pageContentInterface":[
      {"title":"Post 2 title"},
      {"title":"Banner 3"}
    ]
  }
}
```

### Input Objects
So far we've been working mostly on the requests that does not require you to send any kind of data other than a simple `Int`, but in real life you'll have a lot of requests (mutations) where you'll be sending to server all kind of forms – login, registration, create post and so on.
In order to properly handle and validate that data GraphQL type system provides an `InputObjectType` class.
> By default all the `Scalar` types are inputs but if you want to have a single more complicated input type you need to extend an `InputObjectType`.

Let's develop a `PostInputType` that could be used to create a new Post in our system.
```php
<?php
/**
 * PostInputType.php
 */

namespace Examples\Blog\Schema;

use Youshido\GraphQL\Type\Config\InputTypeConfigInterface;
use Youshido\GraphQL\Type\NonNullType;
use Youshido\GraphQL\Type\InputObject\AbstractInputObjectType;
use Youshido\GraphQL\Type\Scalar\StringType;

class PostInputType extends AbstractInputObjectType
{

    public function build($config)
    {
        $config
            ->addField('title', new NonNullType(new StringType()))
            ->addField('summary', new StringType());
    }

}
```

This `InputType` could be used to create a new mutation (we can do it in the `BlogSchema::build` for testing):
```php
<?php
// BlogSchema->build() method
$config->getMutation()->addFields([
    'likePost'   => new LikePost(),
    'createPost' => [
        'type'   => new PostType(),
        'args' => [
            'post'   => new PostInputType(),
            'author' => new StringType()
        ],
        'resolve' => function($value, array $args, ResolveInfo $info) {
            // code for creating a new post goes here
            // we simple use our DataProvider for now
            $post = DataProvider::getPost(10);
            if (!empty($args['post']['title'])) $post['title'] = $args['post']['title'];
            return $post;
        }
    ]
]);
```

Try to execute the following mutation so you can see the result:
```
mutation {
  createPost(author: "Alex", post: {title: "Hey, this is my new post", summary: "my post" }) {
    title
  }
}
```
result:
```js
{"data":{"createPost":{"title":"Hey, this is my new post"}}}
```
> The best way to see the result of your queries/mutations and to inspect the Schema is to use a [GraphiQL tool](#graphiql-tool)

### Non Null

`NonNullType` is really simple to use – consider it as a wrapper that can insure that your field / argument is required and being passed to the resolve function.
We have used `NonNullType` couple of times already so we'll just show you useful methods that that could be called on `NonNullType` objects:
- `getNullableType()`
- `getNamedType()`

These two can return you a type that was wrapped up in the `NonNullType` so you can get it's fields, arguments or name.

## Building your schema

It's always a good idea to give your heads up about any possible errors as soon as possible, better on the development stage.
For this purpose specifically we made a lot of Abstract classes that will force you to implement the right methods to reduce amount of errors or if you're lucky enough – to have no errors at all.

### Abstract type classes
If you want to implement a new type consider extending the following classes:
* AbstractType
* AbstractScalarType
* AbstractObjectType
* AbstractMutationObjectType
* AbstractInputObjectType
* AbstractInterfaceType
* AbstractEnumType
* AbstractListType
* AbstractUnionType
* AbstractSchemaType

### Mutation helper class
You can create a mutation by extending `AbstractObjectType` or by creating a new field of `ObjectType` inside your `Schema::build` method.
It is crucial for the class to have a `getType` method returning the actual OutputType of your mutation but it couldn't be implemented as abstract method, so we created a wrapper class called `AbstractMutationObjectType`.
This abstract class can help you to not forget about `OutputType` by forcing you to implement a method `getOutputType` that will eventually be used by internal `getType` method.

## Useful information

This section will be updating on a regular basis with the useful links and references that might help you to quicker become a better GraphQL developer.

### GraphiQL Tool
To improve our testing experience even more we suggest to start using GraphiQL client, that's included in our examples. It's a JavaScript GraphQL Schema Explorer.
To use it – run the `server.sh` from the `examples/02_blog/` folder and open the `examples/GraphiQL/index.html` file in your browser.
You'll see a nice looking editor that has an autocomplete function and contains all information about your current Schema on the right side in the Docs sidebar:
![GraphiQL Interface](https://raw.githubusercontent.com/Youshido/GraphQL/master/examples/GraphiQL/screenshot.png)

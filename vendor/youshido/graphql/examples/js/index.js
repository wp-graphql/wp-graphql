import {
    graphql,
    GraphQLSchema,
    GraphQLObjectType,
    GraphQLString,
    GraphQLInt,
    GraphQLEnumType,
    GraphQLInterfaceType,
    GraphQLUnionType,
    GraphQLList,
    GraphQLNonNull
} from 'graphql';

import {
    connectionArgs,
    backwardConnectionArgs,
    forwardConnectionArgs,

    connectionDefinitions,
    connectionFromArray,
    connectionFromArraySlice,
    connectionFromPromisedArray,
    connectionFromPromisedArraySlice,

    cursorForObjectInConnection,
    cursorToOffset,
    getOffsetWithDefault,
    offsetToCursor,
    mutationWithClientMutationId,
    nodeDefinitions,
    pluralIdentifyingRootField,

    fromGlobalId,
    globalIdField,
    toGlobalId
} from 'graphql-relay'

var graphqlHTTP = require('express-graphql');
var express     = require('express');

const DataProvider = {
    getBanner: _id => {
        return {id: "banner-" + _id, title: "Banner " + _id, imageLink: "banner" + _id + ".jpg"};
    },
    getPost: _id => {
        return {
            id: "post-" + _id,
            title: "Post " + _id + " title from the postType class",
            summary: "This new GraphQL library for PHP works really well",
            status: 1,
            likeCount: 2
        }
    }
};

const contentBlockInterface = new GraphQLInterfaceType({
    name: 'ContentBlockInterface',
    fields: () => ({
        title: {type: new GraphQLNonNull(GraphQLString)},
        summary: {type: GraphQLString}
    }),
    resolveType: object => {
        return object.id.indexOf('post') != -1 ? postType : bannerType;
    }
});

const postStatus = new GraphQLEnumType({
    name: 'PostStatus',
    values: {
        DRAFT: {value: 0},
        PUBLISHED: {value: 1}
    }
});

const postType = new GraphQLObjectType({
    name: 'Post',
    fields: {
        id: {type: new GraphQLNonNull(GraphQLString)},
        title: {type: new GraphQLNonNull(GraphQLString)},
        summary: {type: GraphQLString},
        status: {type: postStatus},
        likeCount: {type: GraphQLInt}
    },
    interfaces: [contentBlockInterface]
});

const bannerType = new GraphQLObjectType({
    name: 'Banner',
    fields: {
        id: {type: new GraphQLNonNull(GraphQLString)},
        title: {
            type: new GraphQLNonNull(GraphQLString),
            resolve: function (context, value, info) {
                return context['title'];

            }
        },
        summary: {type: GraphQLString},
        imageLink: {type: GraphQLString}
    },
    interfaces: [contentBlockInterface]
});

const contentBlockUnion = new GraphQLUnionType({
    name: 'ContentBlockUnion',
    types: [postType, bannerType],
    resolveType: object => {
        return object.id.indexOf('post') != -1 ? postType : bannerType;
    }
});

var {connectionType: StringConnection} = connectionDefinitions({nodeType: contentBlockUnion});

var {nodeInterface, nodeField} = nodeDefinitions(
    (globalId) => {
        var {type, id} = fromGlobalId(globalId);
        return {
            type: type,
            id: id
        };
    },
    (obj) => {
        return factionType;
    }
);

var factionType = new GraphQLObjectType({
    name: 'Faction',
    fields: () => ({
        id: globalIdField()
    }),
    interfaces: [nodeInterface]
});

const blogSchema = new GraphQLSchema({
    query: new GraphQLObjectType({
        name: 'RootQueryType',
        fields: {
            node: nodeField,
            faction: {
                type: factionType
            },
            connectionArgs: {
                args: connectionArgs,
                type: GraphQLString
            },
            forwardConnectionArgs: {
                args: forwardConnectionArgs,
                type: GraphQLString
            },
            backwardConnectionArgs: {
                args: backwardConnectionArgs,
                type: GraphQLString
            },
            connectionDefinitionTest: {
                args: connectionArgs,
                type: StringConnection
            },
            latestPost: {
                type: postType,
                resolve: () => {
                    return DataProvider.getPost(1)
                }
            },
            randomBanner: {
                type: bannerType,
                resolve: () => {
                    return DataProvider.getBanner(Math.floor((Math.random() * 10) + 1))
                }
            },
            pageContentUnion: {
                type: new GraphQLList(contentBlockUnion),
                resolve: () => {
                    return [DataProvider.getPost(2), DataProvider.getBanner(3)];
                }
            },
            pageContentInterface: {
                type: new GraphQLList(contentBlockInterface),
                resolve: () => {
                    return [DataProvider.getPost(2), DataProvider.getBanner(3)];
                }
            },
            scalarList: {
                type: new GraphQLList(new GraphQLObjectType({
                    name: 'scalarObject',
                    fields: {
                        id: {type: GraphQLInt},
                        cost: {type: GraphQLInt}
                    }
                })),
                args: {
                    count: {type: GraphQLInt, defaultValue: "12"}
                },
                resolve: (source, args) => {
                    console.log(args['count'], (args['count'] === "12"));
                    return [
                        {
                            id: 1,
                            cost: 2
                        },
                        {
                            id: 2,
                            cost: [123, 55]
                        }
                    ]
                }

            }
        }
    }),
    mutation: new GraphQLObjectType({
        name: 'RootMutationType',
        fields: {
            likePost: {
                type: postType,
                args: {
                    id: {type: GraphQLInt}
                },
                resolve: () => {
                    return DataProvider.getPost(1)
                }
            }
        }
    })
});

var query = '{ scalarList(count: 12) { id, cost } }';
graphql(blogSchema, query).then(result => {
    console.log(JSON.stringify(result, null, 5));
    process.exit(0);
}).catch(res => {
    console.log('error', res);
});


var app = express();

app.use('/graphql', graphqlHTTP({schema: blogSchema, graphiql: true}));
app.listen(8080);
console.log('Started on http://localhost:8080/');

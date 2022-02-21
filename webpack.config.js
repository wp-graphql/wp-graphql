const defaults = require("@wordpress/scripts/config/webpack.config");
const path = require("path");

module.exports = {
    ...defaults,
    entry: {
        index: path.resolve(process.cwd(), "packages/wpgraphiql", "index.js"),
        app: path.resolve(process.cwd(), "packages/app", "index.js"),
        // codeExporter: path.resolve(process.cwd(), "src/code-exporter", "index.js"),
        graphiqlQueryComposer: path.resolve(
            process.cwd(),
            "packages/graphiql-query-composer",
            "index.js"
        ),
        graphiqlAuthSwitch: path.resolve(
            process.cwd(),
            "packages/graphiql-auth-switch",
            "index.js"
        ),
        graphiqlFullscreenToggle: path.resolve(
            process.cwd(),
            "packages/graphiql-fullscreen-toggle",
            "index.js"
        ),
        // documentTabs: path.resolve(process.cwd(), "src/document-tabs", "index.js"),
    },
    externals: {
        react: "React",
        "react-dom": "ReactDOM",
        wpGraphiQL: "wpGraphiQL",
        graphql: "wpGraphiQL.GraphQL",
    },
};

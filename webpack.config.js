const defaults = require("@wordpress/scripts/config/webpack.config");
const path = require("path");

module.exports = {
  ...defaults,
  entry: {
    index: path.resolve(process.cwd(), "graphiql", "index.js"),
    app: path.resolve(process.cwd(), "graphiql", "App.js"),
    graphiqlQueryComposer: path.resolve(
      process.cwd(),
      "graphiql/extensions/graphiql-query-composer",
      "index.js"
    ),
    graphiqlAuthSwitch: path.resolve(
      process.cwd(),
      "graphiql/extensions/graphiql-auth-switch",
      "index.js"
    ),
    graphiqlFullscreenToggle: path.resolve(
      process.cwd(),
      "graphiql/extensions/graphiql-fullscreen-toggle",
      "index.js"
    ),
  },
  externals: {
    react: "React",
    "react-dom": "ReactDOM",
    wpGraphiQL: "wpGraphiQL",
    graphql: "wpGraphiQL.GraphQL",
  },
};

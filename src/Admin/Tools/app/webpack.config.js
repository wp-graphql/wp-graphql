const defaults = require("@wordpress/scripts/config/webpack.config");
const path = require("path");

module.exports = {
  ...defaults,
  entry: {
    index: path.resolve(process.cwd(), "src", "index.js"),
    app: path.resolve(process.cwd(), "src", "App.js"),
    // codeExporter: path.resolve(process.cwd(), "src/code-exporter", "index.js"),
    graphiqlQueryComposer: path.resolve(
      process.cwd(),
      "src/extensions/graphiql-query-composer",
      "index.js"
    ),
    graphiqlAuthSwitch: path.resolve(
      process.cwd(),
      "src/extensions/graphiql-auth-switch",
      "index.js"
    ),
    graphiqlFullscreenToggle: path.resolve(
      process.cwd(),
      "src/extensions/graphiql-fullscreen-toggle",
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

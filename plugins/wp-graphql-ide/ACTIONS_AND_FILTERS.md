# Actions & Filters

-> [Original documentation](https://www.wpgraphql.com/docs/customizing-wpgraphiql)

## PHP Actions

- `wpgraphql_ide_enqueue_script` ([enqueue_graphiql_extension](https://www.wpgraphql.com/docs/customizing-wpgraphiql#enqueue_graphiql_extension))

## PHP Filters

- `wpgraphql_ide_capability_required`
- `wpgraphql_ide_context`
- `wpgraphql_ide_external_fragments` ([graphiql_external_fragments](https://www.wpgraphql.com/docs/customizing-wpgraphiql#graphiql_external_fragments))

## JavaScript Actions

- `wpgraphql-ide.init`
- `wpgraphql-ide.rendered` ([graphiql_rendered](https://www.wpgraphql.com/docs/customizing-wpgraphiql#graphiql_rendered))
- `wpgraphql-ide.destroyed`
- `wpgraphql-ide.afterRegisterToolbarButton`
- `wpgraphql-ide.registerToolbarButtonError`
- `wpgraphql-ide.afterRegisterActivityBarPanel`
- `wpgraphql-ide.registerActivityBarPanelError`

## JavaScript Filters

TBD

## Legacy Hooks

Not all actions/filters were ported over from the legacy WPGraphQL IDE.

- [`graphiql_toolbar_before_buttons`](https://www.wpgraphql.com/docs/customizing-wpgraphiql#graphiql_toolbar_before_buttons)
- [`graphiql_toolbar_after_buttons`](https://www.wpgraphql.com/docs/customizing-wpgraphiql#graphiql_toolbar_before_buttons)

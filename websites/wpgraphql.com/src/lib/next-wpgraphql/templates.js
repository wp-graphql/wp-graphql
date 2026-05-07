let _registry = null
let _layout = null

export function configure({ templates, Layout } = {}) {
  _registry = templates ?? {}
  _layout = Layout ?? { queries: {} }
}

export function getRegistry() {
  if (!_registry) {
    throw new Error("next-wpgraphql: configure({ templates, Layout }) was never called")
  }
  return { templates: _registry, Layout: _layout }
}

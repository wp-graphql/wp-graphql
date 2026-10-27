# content-blocks: responses compared with `main`

11 queries. Against `main`, **node-context**: 8 identical, 3 changed only in the test probe fields, 0 with changed extension values. **original-pr** (the first version of #4575, for comparison): 6 identical, 4 probe only, 1 with changed extension values.

Probe fields (`debugGlobals`, `debugNodeGlobals`, `probeGlobals` and the like) are test-only fields from `probe/` that report the current object at a point in the query; changes there are listed as "probe only". For each changed extension value, the cell shows the value on `main` → the value on that build. Every node-context change was checked against the node the field is selected under: it's now that node's value, where on `main` it came from a different node.

Each build's responses were captured twice and matched. The responses are in `main/`, `original-pr/` and `node-context/`; the queries are in `queries/content-blocks/`, and the fixture IDs they used are in `../content.ids`.

| Query | node-context | original-pr |
| --- | --- | --- |
| `cb-a-single-loop-siblings` | identical | identical |
| `cb-b-list-three` | identical | `data.posts.nodes[0].editorBlocks[0].renderedHtml`: "<h2 class=\"wp-block-post-title\">[ext-matrix] Host P1</... → "<h2 class=\"wp-block-post-title\">[ext-matrix] Third P3<...<br>`data.posts.nodes[0].editorBlocks[1].renderedHtml`: "<div class=\"taxonomy-category wp-block-post-terms\"><a ... → "<div class=\"taxonomy-category wp-block-post-terms\"><a ...<br>`data.posts.nodes[0].editorBlocks[1].terms.nodes[0].name`: "[ext-matrix] Host Cat" → "[ext-matrix] Cat Three"<br>…and 8 more |
| `cb-c-nested-category-posts` | identical | identical |
| `cb-d-loop-then-terms` | identical | identical |
| `cb-d2-title-first-then-loop` | identical | identical |
| `cb-e-root-probe-after-loop-node` | probe only | probe only |
| `cb-e2-root-probe-after-plain-node` | probe only | probe only |
| `cb-f-query-attributes-then-terms` | identical | identical |
| `cb-g-sibling-node-after-loop-node` | probe only | probe only |
| `cb-h-flat-default` | identical | identical |
| `cb-i-node-probe-around-loop` | identical | probe only |

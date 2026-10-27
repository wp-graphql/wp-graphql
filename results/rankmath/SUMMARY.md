# rankmath: responses compared with `main`

10 queries. Against `main`, **node-context**: 8 identical, 2 changed only in the test probe fields, 0 with changed extension values. **original-pr** (the first version of #4575, for comparison): 7 identical, 3 probe only, 0 with changed extension values.

Probe fields (`debugGlobals`, `debugNodeGlobals`, `probeGlobals` and the like) are test-only fields from `probe/` that report the current object at a point in the query; changes there are listed as "probe only". For each changed extension value, the cell shows the value on `main` → the value on that build. Every node-context change was checked against the node the field is selected under: it's now that node's value, where on `main` it came from a different node.

Each build's responses were captured twice and matched. The responses are in `main/`, `original-pr/` and `node-context/`; the queries are in `queries/rankmath/`, and the fixture IDs they used are in `../rankmath.ids`.

| Query | node-context | original-pr |
| --- | --- | --- |
| `rm-a-single-all-fields` | identical | identical |
| `rm-b-list-three` | identical | identical |
| `rm-c-nested-author-terms-parent` | identical | identical |
| `rm-d1-jsonld-before-fullhead` | identical | identical |
| `rm-d2-fullhead-before-jsonld` | identical | identical |
| `rm-d3-seo-before-after-model-fields` | identical | identical |
| `rm-d4-list-jsonld-before-fullhead` | identical | identical |
| `rm-e-root-after-node` | probe only | probe only |
| `rm-f-sibling-after-seo-probe` | probe only | probe only |
| `rm-g-term-seo-under-post-then-post-field` | identical | probe only |

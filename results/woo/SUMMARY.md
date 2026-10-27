# woo: responses compared with `main`

10 queries. Against `main`, **node-context**: 6 identical, 4 changed only in the test probe fields, 0 with changed extension values. **original-pr** (the first version of #4575, for comparison): 0 identical, 10 probe only, 0 with changed extension values.

Probe fields (`debugGlobals`, `debugNodeGlobals`, `probeGlobals` and the like) are test-only fields from `probe/` that report the current object at a point in the query; changes there are listed as "probe only". For each changed extension value, the cell shows the value on `main` → the value on that build. Every node-context change was checked against the node the field is selected under: it's now that node's value, where on `main` it came from a different node.

Each build's responses were captured twice and matched. The responses are in `main/`, `original-pr/` and `node-context/`; the queries are in `queries/woo/`, and the fixture IDs they used are in `../woo.ids`.

| Query | node-context | original-pr |
| --- | --- | --- |
| `woo-q1-single-product` | identical | probe only |
| `woo-q2-list-three` | identical | probe only |
| `woo-q3-nested-variations-upsell` | identical | probe only |
| `woo-q4a-description-then-content` | probe only | probe only |
| `woo-q4b-content-then-description` | probe only | probe only |
| `woo-q4c-products-loop-then-content` | identical | probe only |
| `woo-q5-root-after-product` | probe only | probe only |
| `woo-q6-mutation-add-to-cart` | identical | probe only |
| `woo-q7-sibling-products-after-shortcode` | probe only | probe only |
| `woo-q8-variations-then-outer-price` | identical | probe only |

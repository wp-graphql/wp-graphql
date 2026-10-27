# polylang: responses compared with `main`

6 queries. Against `main`, **node-context**: 5 identical, 1 changed only in the test probe fields, 0 with changed extension values. **original-pr** (the first version of #4575, for comparison): 5 identical, 1 probe only, 0 with changed extension values.

Probe fields (`debugGlobals`, `debugNodeGlobals`, `probeGlobals` and the like) are test-only fields from `probe/` that report the current object at a point in the query; changes there are listed as "probe only". For each changed extension value, the cell shows the value on `main` → the value on that build. Every node-context change was checked against the node the field is selected under: it's now that node's value, where on `main` it came from a different node.

Each build's responses were captured twice and matched. The responses are in `main/`, `original-pr/` and `node-context/`; the queries are in `queries/polylang/`, and the fixture IDs they used are in `../polylang.ids`.

| Query | node-context | original-pr |
| --- | --- | --- |
| `pll-a-single-language-translations` | identical | identical |
| `pll-b-list-nine` | identical | identical |
| `pll-b2-language-filters` | identical | identical |
| `pll-c-nested-translation-then-parent` | identical | identical |
| `pll-d-before-after-model-fields` | identical | identical |
| `pll-e-root-after-node` | probe only | probe only |

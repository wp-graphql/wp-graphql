# yoast: responses compared with `main`

21 queries. Against `main`, **node-context**: 19 identical, 1 changed only in the test probe fields, 1 with changed extension values. **original-pr** (the first version of #4575, for comparison): 13 identical, 0 probe only, 8 with changed extension values.

Probe fields (`debugGlobals`, `debugNodeGlobals`, `probeGlobals` and the like) are test-only fields from `probe/` that report the current object at a point in the query; changes there are listed as "probe only". For each changed extension value, the cell shows the value on `main` → the value on that build. Every node-context change was checked against the node the field is selected under: it's now that node's value, where on `main` it came from a different node.

Each build's responses were captured twice and matched. The responses are in `main/`, `original-pr/` and `node-context/`; the queries are in `queries/yoast/`, and the fixture IDs they used are in `../yoast.ids`.

| Query | node-context | original-pr |
| --- | --- | --- |
| `yoast-a-single-post` | identical | `data.post.seo.breadcrumbs[1].text`: "[ext-matrix] Blog" → "[ext-matrix] Alice Post"<br>`data.post.seo.breadcrumbs[1].url`: "http://localhost:8888/ext-matrix-blog/" → "http://localhost:8888/ext-matrix-alice-post/"<br>`data.post.seo.breadcrumbs[2].text`: "[ext-matrix] Alice Post" → null<br>…and 3 more |
| `yoast-b-list-mixed-content` | identical | `data.contentNodes.nodes[0].seo.breadcrumbs[1].text`: "[ext-matrix] Blog" → "[ext-matrix] Alice Post"<br>`data.contentNodes.nodes[0].seo.breadcrumbs[2].text`: "[ext-matrix] Alice Post" → null<br>`data.contentNodes.nodes[0].seo.schema.raw`: "{\"@context\":\"https://schema.org\",\"@graph\":[{\"@typ... → "{\"@context\":\"https://schema.org\",\"@graph\":[{\"@typ...<br>…and 6 more |
| `yoast-c1-user-posts-and-author-under-post` | identical | `data.user.posts.nodes[0].seo.schema.raw`: "{\"@context\":\"https://schema.org\",\"@graph\":[{\"@typ... → "{\"@context\":\"https://schema.org\",\"@graph\":[{\"@typ... |
| `yoast-c2-custom-tax-under-cpt` | identical | identical |
| `yoast-d1-seo-before-model-fields` | identical | `data.post.seo.schema.raw`: "{\"@context\":\"https://schema.org\",\"@graph\":[{\"@typ... → "{\"@context\":\"https://schema.org\",\"@graph\":[{\"@typ... |
| `yoast-d2-seo-after-model-fields` | identical | `data.post.seo.schema.raw`: "{\"@context\":\"https://schema.org\",\"@graph\":[{\"@typ... → "{\"@context\":\"https://schema.org\",\"@graph\":[{\"@typ... |
| `yoast-d3-root-seo-before-node` | identical | identical |
| `yoast-e1-root-seo-after-post-authordata` | identical | identical |
| `yoast-e2-root-seo-after-other-cpt` | identical | identical |
| `yoast-e3-root-archive-schema-after-post` | identical | identical |
| `yoast-e4-root-archive-after-front-page` | identical | identical |
| `yoast-e5-root-after-mediaitem` | `data.genres.nodes[0].seo.canonical`: "http://localhost:8888/graphql/" → "http://localhost:8888/genre/extmx-fantasy/"<br>`data.genres.nodes[0].seo.opengraphUrl`: "http://localhost:8888/graphql/" → "http://localhost:8888/genre/extmx-fantasy/" | `data.genres.nodes[0].seo.canonical`: "http://localhost:8888/graphql/" → "http://localhost:8888/genre/extmx-fantasy/"<br>`data.genres.nodes[0].seo.opengraphUrl`: "http://localhost:8888/graphql/" → "http://localhost:8888/genre/extmx-fantasy/" |
| `yoast-e6-pageinfo-seo-nodes-first` | identical | identical |
| `yoast-e7-pageinfo-seo-first` | identical | identical |
| `yoast-f-root-seo-before-post-schema` | identical | identical |
| `yoast-g-post-fullhead-image-and-probe` | probe only | `data.one.seo.fullHead`: "<!-- This site is optimized with the Yoast SEO plugin v2... → "<!-- This site is optimized with the Yoast SEO plugin v2...<br>`data.one.seo.schema.raw`: "{\"@context\":\"https://schema.org\",\"@graph\":[{\"@typ... → "{\"@context\":\"https://schema.org\",\"@graph\":[{\"@typ...<br>`data.two.seo.fullHead`: "<!-- This site is optimized with the Yoast SEO plugin v2... → "<!-- This site is optimized with the Yoast SEO plugin v2...<br>…and 1 more |
| `yoast-h-product-like-list-fullhead` | identical | `data.contentNodes.nodes[0].seo.fullHead`: "<!-- This site is optimized with the Yoast SEO plugin v2... → "<!-- This site is optimized with the Yoast SEO plugin v2...<br>`data.contentNodes.nodes[1].seo.fullHead`: "<!-- This site is optimized with the Yoast SEO plugin v2... → "<!-- This site is optimized with the Yoast SEO plugin v2...<br>`data.contentNodes.nodes[2].seo.fullHead`: "<!-- This site is optimized with the Yoast SEO plugin v2... → "<!-- This site is optimized with the Yoast SEO plugin v2... |
| `yoast-i-batch-root-seo-after-post` | identical | identical |
| `yoast-j-batch-root-seo-after-movie` | identical | identical |
| `yoast-k-batch-root-seo-after-front-page` | identical | identical |
| `yoast-l-batch-root-seo-after-mediaitem` | identical | identical |

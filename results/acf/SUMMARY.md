# acf: responses compared with `main`

13 queries. Against `main`, **node-context**: 3 identical, 0 changed only in the test probe fields, 10 with changed extension values. **original-pr** (the first version of #4575, for comparison): 12 identical, 1 probe only, 0 with changed extension values.

Probe fields (`debugGlobals`, `debugNodeGlobals`, `probeGlobals` and the like) are test-only fields from `probe/` that report the current object at a point in the query; changes there are listed as "probe only". For each changed extension value, the cell shows the value on `main` → the value on that build. Every node-context change was checked against the node the field is selected under: it's now that node's value, where on `main` it came from a different node.

Each build's responses were captured twice and matched. The responses are in `main/`, `original-pr/` and `node-context/`; the queries are in `queries/acf/`, and the fixture IDs they used are in `../content.ids`.

| Query | node-context | original-pr |
| --- | --- | --- |
| `acf-a-single-post-gallery` | identical | identical |
| `acf-b-list-three-posts` | `data.posts.nodes[0].extMatrixStory.body`: "<p>Post 490</p>\n" → "<p>Post 490</p>\n<div id='gallery-1' class='gallery gall...<br>`data.posts.nodes[0].extMatrixStory.rows[0].rowBody`: "" → "<div id='gallery-2' class='gallery galleryid-490 gallery...<br>`data.posts.nodes[1].extMatrixStory.body`: "<p>Post 491</p>\n" → "<p>Post 491</p>\n<div id='gallery-3' class='gallery gall...<br>…and 1 more | identical |
| `acf-c1-term-then-nested-posts` | `data.category.posts.nodes[0].extMatrixStory.body`: "<p>Post 490</p>\n<div id='gallery-2' class='gallery gall... → "<p>Post 490</p>\n<div id='gallery-2' class='gallery gall... | identical |
| `acf-c1b-nested-posts-then-term-field` | `data.category.posts.nodes[0].extMatrixStory.body`: "<p>Post 490</p>\n<div id='gallery-2' class='gallery gall... → "<p>Post 490</p>\n<div id='gallery-2' class='gallery gall... | identical |
| `acf-c2-user-under-post` | identical | identical |
| `acf-d-field-before-after-model-fields` | identical | identical |
| `acf-e1-options-after-media` | `data.extMatrixSiteSettings.extMatrixSiteFields.body`: "<p>Options</p>\n" → "<p>Options</p>\n<div id='gallery-1' class='gallery galle... | identical |
| `acf-e2-options-after-post` | `data.extMatrixSiteSettings.extMatrixSiteFields.body`: "<p>Options</p>\n<div id='gallery-1' class='gallery galle... → "<p>Options</p>\n<div id='gallery-1' class='gallery galle... | identical |
| `acf-e2rev-options-before-post` | `data.extMatrixSiteSettings.extMatrixSiteFields.body`: "<p>Options</p>\n<div id='gallery-1' class='gallery galle... → "<p>Options</p>\n<div id='gallery-1' class='gallery galle... | identical |
| `acf-e3-term-root-after-post` | `data.category.extMatrixTerm.body`: "<div id='gallery-1' class='gallery galleryid-491 gallery... → "<div id='gallery-1' class='gallery galleryid-0 gallery-c... | identical |
| `acf-e4-user-root-after-post` | `data.user.extMatrixUser.body`: "<div id='gallery-1' class='gallery galleryid-491 gallery... → "<div id='gallery-1' class='gallery galleryid-0 gallery-c... | identical |
| `acf-e5-options-after-post-list` | `data.extMatrixSiteSettings.extMatrixSiteFields.body`: "<p>Options</p>\n<div id='gallery-1' class='gallery galle... → "<p>Options</p>\n<div id='gallery-1' class='gallery galle... | identical |
| `acf-e6-term-root-after-post-p1` | `data.category.extMatrixTerm.body`: "<div id='gallery-1' class='gallery galleryid-490 gallery... → "<div id='gallery-1' class='gallery galleryid-0 gallery-c... | probe only |

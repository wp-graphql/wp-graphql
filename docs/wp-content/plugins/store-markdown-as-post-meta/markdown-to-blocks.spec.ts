import { blocks2markdown, markdownToBlocks } from './markdown-to-blocks';
import {
	ensureDOMPpolyfill,
	ensureCoreBlocksRegistered,
} from '../convert-data-formats/converters';
import { parse, serialize, createBlock } from '@wordpress/blocks';

describe('blocks2markdown', () => {
	beforeAll(async () => {
		await ensureDOMPpolyfill();
		await ensureCoreBlocksRegistered();
	});
	it('should leave two blank lines after HTML blocks', () => {
		const parsed = parse(`
<!-- wp:html -->
<img src="https://user-images.githubusercontent.com/3068563/108868727-428db880-75d5-11eb-84a9-2c0b749a22ad.png" alt="NVDA options with Speech viewer enabled" width="640">
<!-- /wp:html -->

<!-- wp:paragraph -->
<p>While in the Gutenberg editor, with NVDA activated, you can press &lt;kbd>Insert+F7&lt;/kbd> to open the Elements List where you can find elements grouped by their types, such as links, headings, form fields, buttons and landmarks.</p>
<!-- /wp:paragraph -->
        `);
		const blocks = blocks2markdown(parsed);
		expect(blocks)
			.toEqual(`<img src="https://user-images.githubusercontent.com/3068563/108868727-428db880-75d5-11eb-84a9-2c0b749a22ad.png" alt="NVDA options with Speech viewer enabled" width="640">

While in the Gutenberg editor, with NVDA activated, you can press <kbd>Insert+F7</kbd> to open the Elements List where you can find elements grouped by their types, such as links, headings, form fields, buttons and landmarks.

`);
	});

	it('should store unserializable blocks as fenced code snippets', () => {
		const parsed = parse(`
        <!-- wp:columns -->
        <div class="wp-block-columns"><!-- wp:column -->
        <div class="wp-block-column"><!-- wp:paragraph -->
        <p>I'm a paragraph</p>
        <!-- /wp:paragraph --></div>
        <!-- /wp:column -->
        
        <!-- wp:column -->
        <div class="wp-block-column"><!-- wp:quote -->
        <blockquote class="wp-block-quote"><!-- wp:paragraph -->
        <p>I'm a quote!</p>
        <!-- /wp:paragraph --></blockquote>
        <!-- /wp:quote --></div>
        <!-- /wp:column --></div>
        <!-- /wp:columns -->
        `);
		const blocks = blocks2markdown(parsed);
		expect(blocks).toEqual(
			'```block\n' +
				`<!-- wp:columns -->\n<div class=\"wp-block-columns\"><!-- wp:column -->\n` +
				`<div class=\"wp-block-column\"><!-- wp:paragraph -->\n<p>I'm a paragraph</p>\n` +
				`<!-- /wp:paragraph --></div>\n<!-- /wp:column -->\n\n<!-- wp:column -->\n` +
				`<div class=\"wp-block-column\"><!-- wp:quote -->\n<blockquote class=\"wp-block-quote\">` +
				`<!-- wp:paragraph -->\n<p>I'm a quote!</p>\n<!-- /wp:paragraph --></blockquote>\n` +
				`<!-- /wp:quote --></div>\n<!-- /wp:column --></div>\n<!-- /wp:columns -->\n` +
				'```\n\n'
		);
	});

	it('should serialize a table block without body rows as a markdown table', () => {
		const parsed = parse(
			`<!-- wp:table -->
        <figure class="wp-block-table"><table><thead><tr><td><em>Header left</em></td><td>Header right</td></tr></thead></table></figure>
<!-- /wp:table -->`,
            {
                // @TODO: figure out why the block parser complains about table block validation
				__unstableSkipMigrationLogs: true,
			}
        );
		const markdown = blocks2markdown(parsed);
		expect(markdown).toEqual(
			[
				'| *Header left* | Header right |',
				'| ------------- | ------------ |',
			].join('\n') + '\n\n'
		);
	});

	it('should serialize a table block without an explicit thead row as a markdown table', () => {
		const parsed = parse(
			`<!-- wp:table -->
        <figure class="wp-block-table"><table><tbody><tr><td><em>Header left</em></td><td>Header right</td></tr><tr><td>row 1 col 1</td><td>row 1 col 2</td></tr><tr><td>row 2 col 1</td><td>row 2 col 2</td></tr></tbody></table></figure>
<!-- /wp:table -->`,
            {
                // @TODO: figure out why the block parser complains about table block validation
				__unstableSkipMigrationLogs: true,
			}
        );
		const markdown = blocks2markdown(parsed);
		expect(markdown).toEqual(
			[
				'| *Header left* | Header right |',
				'| ------------- | ------------ |',
				'| row 1 col 1   | row 1 col 2  |',
				'| row 2 col 1   | row 2 col 2  |',
			].join('\n') + '\n\n'
		);
	});

	it('should escape special markdown characters inside paragraphs', () => {
		const parsed = parse(
			`<!-- wp:paragraph -->
			<p>In the classic editor, notices hooked onto the admin_notices* action* can render whatever HTML they'd like.</p>
			<!-- /wp:paragraph -->`
		);
		const markdown = blocks2markdown(parsed);
		expect(markdown).toEqual(
			'In the classic editor, notices hooked onto the admin\\_notices\\* action\\* can render whatever HTML they\'d like.\n\n'
		);
	});
	
	it('should not escape special characters inside inline code formats', () => {
		const parsed = parse(
			`<!-- wp:paragraph -->
			<p>In the classic editor, notices hooked onto the <code>admin_notices*</code> action can render whatever HTML they'd like.</p>
			<!-- /wp:paragraph -->`
		);
		const markdown = blocks2markdown(parsed);
		expect(markdown).toEqual(
			'In the classic editor, notices hooked onto the `admin_notices*` action can render whatever HTML they\'d like.\n\n'
		);
	});

    const createBlocks = (blocks: any) =>
        blocks.map((block: any) =>
            createBlock(
                block.name,
                block.attributes,
                block.innerBlocks ? createBlocks(block.innerBlocks) : []
            )
		);
	
	const testCases = [
		{
			description: 'should preserve tables',
			blocks: `<!-- wp:table {"hasFixedLayout":false} -->
<figure class="wp-block-table"><table><thead><tr><th><em>Header left</em></th><th>Header right</th></tr></thead><tbody><tr><td>row 1 col 1</td><td>row 1 col 2</td></tr><tr><td>row 2 col 1</td><td>row 2 col 2</td></tr></tbody></table></figure>
<!-- /wp:table -->`,
			markdown: [
				'| *Header left* | Header right |',
				'| ------------- | ------------ |',
				'| row 1 col 1   | row 1 col 2  |',
				'| row 2 col 1   | row 2 col 2  |',
			].join('\n') + '\n\n'
		},
		{
			description: 'should preserve inline code formats',
			blocks: `<!-- wp:paragraph -->
<p>In the classic editor, notices hooked onto the <code>admin_notices*</code> action can render whatever HTML they'd like.</p>
<!-- /wp:paragraph -->`,
			markdown: 'In the classic editor, notices hooked onto the `admin_notices*` action can render whatever HTML they\'d like.\n\n'
		},
		{
			description: 'should preserve block code formats',
			blocks: `<!-- wp:code -->
<pre class="wp-block-code"><code>In the classic editor, notices hooked onto the admin_notices*</code></pre>
<!-- /wp:code -->`,
			markdown: '```\nIn the classic editor, notices hooked onto the admin_notices*\n```\n\n'
		},
		{
			description: 'should preserve inline images',
			blocks: `<!-- wp:paragraph -->
<p>Inline image <img src="https://example.com/image.png" alt="Alt text"> After image</p>
<!-- /wp:paragraph -->`,
			markdown: `Inline image ![Alt text](https://example.com/image.png) After image\n\n`
		},
		{
			description: 'should preserve block images with alt text',
			blocks: `<!-- wp:image -->
<figure class="wp-block-image"><img src="https://example.com/image.png" alt="Alt text"/></figure>
<!-- /wp:image -->`,
			markdown: `![Alt text](https://example.com/image.png)\n\n`
		},
		{
			description: 'should preserve block images without alt text or title',
			blocks: `<!-- wp:image -->
<figure class="wp-block-image"><img src="https://example.com/image.png" alt=""/></figure>
<!-- /wp:image -->`,
			markdown: `![](https://example.com/image.png)\n\n`
		},
		{
			description: 'should preserve block images with alt text containing special characters',
			blocks: `<!-- wp:image -->
<figure class="wp-block-image"><img src="https://example.com/image.png" alt="Alt *text"/></figure>
<!-- /wp:image -->`,
			markdown: `![Alt \\*text](https://example.com/image.png)\n\n`
		},
		{
			description: 'should not escape special characters inside block code formats',
			blocks: `<!-- wp:code -->
<pre class="wp-block-code"><code>In the classic editor, notices hooked onto the admin_notices*</code></pre>
<!-- /wp:code -->`,
			markdown: '```\nIn the classic editor, notices hooked onto the admin_notices*\n```\n\n'
		},
		{
			description: 'should preserve newlines at the end of code blocks',
			blocks: `<!-- wp:code -->
<pre class="wp-block-code"><code>In the classic editor, notices hooked onto the admin_notices*\n\n</code></pre>
<!-- /wp:code -->`,
			markdown: '```\nIn the classic editor, notices hooked onto the admin_notices*\n\n\n```\n\n'
		},
		{
			description: 'should preserve special characters inside block code formats',
			blocks: `<!-- wp:code -->
<pre class="wp-block-code"><code>In the classic editor, \nnotices &lt;p> hooked onto the admin_notices*</code></pre>
<!-- /wp:code -->`,
			markdown: '```\nIn the classic editor, \nnotices <p> hooked onto the admin_notices*\n```\n\n'
		},
		{
			description: 'should escape special markdown characters inside paragraphs',
			blocks: `<!-- wp:paragraph -->
<p>In the classic editor, notices hooked onto the admin_notices* action* can render whatever HTML they'd like.</p>
<!-- /wp:paragraph -->`,
			markdown: 'In the classic editor, notices hooked onto the admin\\_notices\\* action\\* can render whatever HTML they\'d like.\n\n'
		},
		{
			description: 'should preserve block quotes',
			blocks: `<!-- wp:quote -->
<blockquote class="wp-block-quote"><!-- wp:paragraph -->
<p>Block quote</p>
<!-- /wp:paragraph --></blockquote>
<!-- /wp:quote -->`,
			markdown: '> Block quote\n> \n> \n\n'
		},
		{
			description: 'should preserve double newline between images and paragraphs',
			blocks: `<!-- wp:paragraph -->
<p>Producing an equivalent "Post draft updated" notice would require code like below.</p>
<!-- /wp:paragraph -->

<!-- wp:image -->
<figure class="wp-block-image"><img src="https://raw.githubusercontent.com/WordPress/gutenberg/HEAD/docs/how-to-guides/notices/classic-editor-notice.png" alt="Post draft updated in the classic editor"/></figure>
<!-- /wp:image -->

<!-- wp:paragraph -->
<p>Producing an equivalent "Post draft updated" notice would require code like above.</p>
<!-- /wp:paragraph -->`,
			markdown: `Producing an equivalent "Post draft updated" notice would require code like below.

![Post draft updated in the classic editor](https://raw.githubusercontent.com/WordPress/gutenberg/HEAD/docs/how-to-guides/notices/classic-editor-notice.png)

Producing an equivalent "Post draft updated" notice would require code like above.\n\n`
		}
	]

	for (const { description, blocks, markdown } of testCases) {
		it('blocks -> markdown – ' + description, () => {
			const parsed = parse(blocks);
			const result = blocks2markdown(parsed);
			expect(result).toEqual(markdown);
		});
		it('markdown -> blocks – ' + description, () => {
			const result = serialize(createBlocks(markdownToBlocks(markdown)));
			expect(result).toEqual(blocks);
		});
	}
});

describe('markdownToBlocks', () => {
	beforeAll(async () => {
		await ensureDOMPpolyfill();
	});

	it('should transform a markdown page', () => {
		const blocks = markdownToBlocks(`
# Block Editor Handbook

Welcome to the Block Editor Handbook.

The [**Block Editor**](https://wordpress.org/gutenberg/) is a modern paradigm for WordPress site building and publishing. It uses a modular system of **blocks** to compose and format content and is designed to create rich and flexible layouts for websites and digital products.

The Block Editor consists of several primary elements, as shown in the following figure:

![Quick view of the Block Editor](https://raw.githubusercontent.com/WordPress/gutenberg/trunk/docs/assets/overview-block-editor-2023.png)

The elements highlighted are:

1. **Inserter:** A panel for inserting blocks into the content canvas
2. **Content canvas:** The content editor, which holds content created with blocks
3. **Settings Panel** A panel for configuring a block’s settings when selected or the settings of the post

        `);
		expect(blocks).toMatchSnapshot();
	});

	it('should transform a markdown table', () => {
		const blocks = markdownToBlocks(`
Generally speaking, [the following labels](https://github.com/WordPress/gutenberg/labels) are very useful:

| Label                      | Reason                                                                                    |
| -------------------------- | ----------------------------------------------------------------------------------------- |
| \`[Type] Bug\`               | When an intended feature is broken.                                                       |
| \`[Type] Enhancement\`       | When someone is suggesting an enhancement to a current feature.                           |
| \`[Type] Help Request\`      | When someone is asking for general help with setup/implementation.                        |
| \`Needs Technical Feedback\` | When you see new features or API changes proposed.                                        |
| \`Needs More Info\`          | When it’s not clear what the issue is or it would help to provide additional details.     |
| \`Needs Testing\`            | When a new issue needs to be confirmed or old bugs seem like they are no longer relevant. |

[NVDA](https://www.nvaccess.org/about-nvda/) is a free screen reader for Windows.
        `);
		expect(blocks).toMatchSnapshot();
	});

	it('should parse a simple markdown table', () => {
		const blocks = markdownToBlocks(
			[`| Label |`, `| ----- |`, `| Value |`].join('\n')
		);
		expect(blocks).toMatchSnapshot();
	});

	it('should parse formats in a simple markdown table', () => {
		const blocks = markdownToBlocks(
			[`| **Label** |`, `| ----- |`, `| V **a*l*u** e |`].join('\n')
		);
		expect(blocks).toMatchSnapshot();
	});

	it('should preserve img alt text in inline images', () => {
		const blocks = markdownToBlocks(
			`Inline image ![Alt text](https://example.com/image.png "Image Title")`
		);
		expect(blocks).toEqual([
			{
				name: 'core/paragraph',
				attributes: {
					content: `Inline image <img src="https://example.com/image.png" title="Image Title" alt="Alt text">`
				},
				innerBlocks: [],
			},
		]);
	});

	it('should preserve img alt text and caption in block images', () => {
		const blocks = markdownToBlocks(
			`![Alt text](https://example.com/image.png "Image Title")`
		);
		expect(blocks).toEqual([
			{
				name: 'core/image',
				attributes: {
					url: 'https://example.com/image.png',
					alt: 'Alt text',
					caption: 'Image Title'
				},
				innerBlocks: [],
			},
		]);
	});
});

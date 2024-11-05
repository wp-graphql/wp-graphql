/**
 * Convert between Markdown and WordPress Blocks.
 *
 * Depends on setting the `commonmark` global, an
 * exercise left up to the reader.
 */

const commonmark = await import('./commonmark.js');
const { installTableExtension } = await import('./markdown-table-parser.ts');

/**
 * Matches Jekyll-style front-matter at the start of a Markdown document.
 *
 * @see https://github.com/jekyll/jekyll/blob/1484c6d6a41196dcaa25daca9ed1f8c32083ff10/lib/jekyll/document.rb
 *
 * @type {RegExp}
 */
const frontMatterPattern = /---\s*\n(.*?)\n?(?:---|\.\.\.)\s*\n/sy;

function escapeSpecialChars(text) {
	// @TODO escape ~
	return text.replace(/([_*`\\])/g, '\\$1');
}

export function htmlToMarkdown(html, options = {}) {
	if (!('escapeMarkdown' in options)) options['escapeMarkdown'] = true;

	const node = document.createElement('div');
	node.innerHTML = html;
	if (!node) return '';

	let result = '';

	function traverse(node, options) {
		if (node.nodeType === Node.TEXT_NODE) {
			if (options?.escapeMarkdown) {
				result += escapeSpecialChars(node.textContent);
			} else {
				result += node.textContent;
			}
		} else if (node.nodeType === Node.ELEMENT_NODE) {
			switch (node.tagName.toLowerCase()) {
				case 'b':
				case 'strong':
					result += '**';
					traverseChildren(node, options);
					result += '**';
					break;
				case 'i':
				case 'em':
					result += '*';
					traverseChildren(node, options);
					result += '*';
					break;
				case 'u':
					result += '_';
					traverseChildren(node, options);
					result += '_';
					break;
				case 'code':
					result += '`';
					traverseChildren(node, {
						...options,
						escapeMarkdown: false,
					});
					result += '`';
					break;
				case 'a':
					result += '[';
					traverseChildren(node, options);
					result += `](${node.getAttribute('href')})`;
					break;
				case 'img':
					result += `![${node.getAttribute(
						'alt'
					)}](${node.getAttribute('src')})`;
					break;
				default:
					traverseChildren(node, options);
					break;
			}
		}
	}

	function traverseChildren(node, options) {
		node.childNodes.forEach((node) => traverse(node, options));
	}

	traverse(node, options);
	return result;
}

const blockToMarkdown = (state, block) => {
	/**
	 * Convert a number to Roman Numerals.
	 *
	 * @cite https://stackoverflow.com/a/9083076/486538
	 */
	const romanize = (num) => {
		const digits = String(+num).split('');
		const key = [
			'',
			'C',
			'CC',
			'CCC',
			'CD',
			'D',
			'DC',
			'DCC',
			'DCCC',
			'CM',
			'',
			'X',
			'XX',
			'XXX',
			'XL',
			'L',
			'LX',
			'LXX',
			'LXXX',
			'XC',
			'',
			'I',
			'II',
			'III',
			'IV',
			'V',
			'VI',
			'VII',
			'VIII',
			'IX',
		];
		let roman = '';
		let i = 3;
		while (i--) {
			roman = (key[+digits.pop() + i * 10] || '') + roman;
		}
		return Array(+digits.join('') + 1).join('M') + roman;
	};

	/**
	 * Indents a string for the current depth.
	 *
	 *  - Leaves blank lines alone.
	 *
	 * @param {string} s multi-line content to indent.
	 */
	const indent = (s) => {
		if (0 === state.indent.length) {
			return s;
		}

		const indent = state.indent.join('');

		let at = 0;
		let last = 0;
		let out = '';
		while (at < s.length) {
			const nextAt = s.indexOf('\n', at);

			// No more newlines? Return rest of string, indented.
			if (-1 === nextAt) {
				out += indent + s.slice(at);
				break;
			}

			// Leave successive newlines without indentation.
			if (nextAt === last + 1) {
				out += '\n';
				at++;
				last = at;
				continue;
			}

			out += indent + s.slice(at, nextAt + 1);
			at = nextAt + 1;
			last = at;
		}

		return out;
	};

	switch (block.name) {
		case 'core/quote':
			const content = blocksToMarkdown(state, block.innerBlocks);
			// @todo this probably fails on nested quotes - handle that.
			return (
				content
					.split('\n')
					.map((l) => `> ${l}`)
					.join('\n') + '\n\n'
			);

		case 'core/code':
			const code = htmlToMarkdown(block.attributes.content, {
				escapeMarkdown: false,
			});
			const languageSpec = block.attributes.meta?.language || '';
			return `\`\`\`${languageSpec}\n${code}\n\`\`\`\n\n`;

		case 'core/html':
			return `${block.attributes.content}\n\n`;

		case 'core/image':
			return `![${htmlToMarkdown(block.attributes.alt)}](${
				block.attributes.url
			})\n\n`;

		case 'core/heading':
			return (
				'#'.repeat(block.attributes.level) +
				' ' +
				htmlToMarkdown(block.attributes.content) +
				'\n\n'
			);

		case 'core/list':
			state.listStyle.push({
				style: block.attributes.ordered
					? block.attributes.type || 'decimal'
					: '-',
				count: block.attributes.start || 1,
			});
			const list = blocksToMarkdown(state, block.innerBlocks);
			state.listStyle.pop();
			return `${list}\n\n`;

		case 'core/table':
			const cellValueToMarkdown = (cell) =>
				htmlToMarkdown(cell.content).replaceAll('|', '\\|');

            let headRow, bodyRows;
            if (block.attributes.head.length) {
                headRow = block.attributes.head[0].cells;
                bodyRows = block.attributes.body;
            } else {
                headRow = block.attributes.body[0].cells;
                bodyRows = block.attributes.body.slice(1);
            }

			const headAlignments = headRow.map((cell) => cell.align);
			const head = headRow.map(cellValueToMarkdown);
			const body = bodyRows.map((row) =>
				row.cells.map(cellValueToMarkdown)
			);

			const colWidths = [head, ...body].reduce((acc, cells) => {
				cells.forEach((cell, i) => {
					acc[i] = Math.max(acc[i] || 0, cell.length);
				});
				return acc;
			}, []);

			const delimiters: any[] = [];
			for (let i = 0; i < headAlignments.length; i++) {
				const leftColon =
					headAlignments[i] === 'left' ||
					headAlignments[i] === 'center'
						? ':'
						: '';
				const rightColon =
					headAlignments[i] === 'right' ||
					headAlignments[i] === 'center'
						? ':'
						: '';
				const nbColons = (leftColon ? 1 : 0) + (rightColon ? 1 : 0);
				const dashes = '-'.repeat(Math.max(colWidths[i] - nbColons, 1));
				delimiters.push(leftColon + dashes + rightColon);
			}
			const formatRow = (cells) => {
				return (
					'| ' +
					cells
						.map((cell, i) => cell.padEnd(colWidths[i]))
						.join(' | ') +
					' |'
				);
			};

			return (
				[
					formatRow(head),
					formatRow(delimiters),
					...body.map(formatRow),
				].join('\n') + '\n\n'
			);

		case 'core/list-item':
			if (0 === state.listStyle.length) {
				return '';
			}

			const item = state.listStyle[state.listStyle.length - 1];
			const bullet = (() => {
				switch (item.style) {
					case '-':
						return '-';

					case 'decimal':
						return `${item.count.toString()}.`;

					case 'upper-alpha': {
						let count = item.count;
						let bullet = '';
						while (count >= 1) {
							bullet =
								'ABCDEFGHIJKLMNOPQRSTUVWXYZ'[(count - 1) % 26] +
								bullet;
							count /= 26;
						}
						return `${bullet}.`;
					}

					case 'lower-alpha': {
						let count = item.count;
						let bullet = '';
						while (count >= 1) {
							bullet =
								'abcdefghijklmnopqrstuvwxyz'[(count - 1) % 26] +
								bullet;
							count /= 26;
						}
						return `${bullet}.`;
					}

					case 'upper-roman':
						return romanize(item.count) + '.';

					case 'lower-roman':
						return romanize(item.count).toLowerCase();

					default:
						return `${item.count.toString()}.`;
				}
			})();

			item.count++;
			const bulletIndent = ' '.repeat(bullet.length + 1);

			// This hits sibling items and it shouldn't.
			const [firstLine, restLines] = htmlToMarkdown(
				block.attributes.content
			).split('\n', 1);
			if (0 === block.innerBlocks.length) {
				let out = `${state.indent.join('')}${bullet} ${firstLine}`;
				state.indent.push(bulletIndent);
				if (restLines) {
					out += indent(restLines);
				}
				state.indent.pop();
				return out + '\n';
			}
			state.indent.push(bulletIndent);
			const innerContent = indent(
				`${restLines ? `${restLines}\n` : ''}${blocksToMarkdown(
					state,
					block.innerBlocks
				)}`
			);
			state.indent.pop();
			return `${state.indent.join(
				''
			)}${bullet} ${firstLine}\n${innerContent}\n`;

		case 'core/paragraph':
			return htmlToMarkdown(block.attributes.content) + '\n\n';

		case 'core/separator':
			return '\n---\n\n';

		default:
			return (
				'```block\n' +
				escapeSpecialChars(window.wp.blocks.serialize(block)) +
				'\n```\n\n'
			);
	}
};

/**
 * Converts a list of blocks into a Markdown string.
 *
 * @param {object} state Parser state.
 * @param {object[]} blocks Blocks to convert.
 * @returns {string} Markdown output.
 */
const blocksToMarkdown = (state, blocks) => {
	return blocks.map((block) => blockToMarkdown(state, block)).join('');
};

export const blocks2markdown = (blocks) => {
	const state = {
		indent: [],
		listStyle: [],
	};

	return blocksToMarkdown(state, blocks || []);
};

function WpBlocksRenderer(options) {
	this.options = options;
}

const escapeHTML = (s) =>
	s.replace(/[<&>'"]/g, (m) => {
		switch (m[0]) {
			case '<':
				return '&lt;';
			case '>':
				return '&gt;';
			case '&':
				return '&amp;';
			case '"':
				return '&quot;';
			case "'":
				return '&apos;';
		}
	});

function render(ast) {
	var blocks = {
		name: 'root',
		attributes: {},
		innerBlocks: [],
	};
	var event, lastNode;
	var walker = ast.walker();

	while ((event = walker.next())) {
		lastNode = event.node;
	}

	// Walk the blocks
	if (lastNode.type !== 'document') {
		throw new Error('Expected a document node');
	}

	nodeToBlock(blocks, lastNode.firstChild);

	return blocks.innerBlocks;
}

const nodeToBlock = (parentBlock, node) => {
	const add = (block) => {
		parentBlock.innerBlocks.push(block);
	};

	let block: any = {
		name: '',
		attributes: {},
		innerBlocks: [],
	};

	let skipChildren = false;

	/**
	 * @see ../blocks.js
	 */
	switch (node.type || null) {
		// Nothing to store here. It's a container.
		case 'document':
			// @todo Should this "break" instead?
			return;

		case 'image':
			// @todo If there's formatting, grab it from the children.
			block.name = 'core/image';
			block.attributes.url = node._destination;
			if (node._description) {
				block.attributes.alt = node._description;
			}
			if (node._title) {
				block.attributes.title = node._title;
			}
			break;

		case 'list':
			block.name = 'core/list';
			block.attributes.ordered = node._listData.type === 'ordered';
			if (node._listData.start && node._listData.start !== 1) {
				block.attributes.start = node._listData.start;
			}
			break;

		case 'block_quote':
			block.name = 'core/quote';
			break;

		case 'table':
			block.name = 'core/table';
			block.attributes.head = [];
			block.attributes.body = [];
			block.attributes.foot = [];
			block.attributes.caption = '';
			block.attributes.hasFixedLayout = false;

			let rowNode = node.firstChild;
			if (rowNode.type === 'table_head_row') {
				const cells: any = [];
				let thNode = rowNode.firstChild;
				while (thNode) {
					cells.push({
						content: inlineBlocksToHTML('', thNode.firstChild),
						tag: 'th',
					});
					thNode = thNode.next;
				}
				block.attributes.head.push({ cells });
				rowNode = rowNode.next;
			}

			while (rowNode) {
				const cells: any = [];
				let tdNode = rowNode.firstChild;
				while (tdNode) {
					cells.push({
						content: inlineBlocksToHTML('', tdNode.firstChild),
						tag: 'td',
					});
					tdNode = tdNode.next;
				}
				block.attributes.body.push({ cells });
				rowNode = rowNode.next;
			}

			skipChildren = true;
			break;

		case 'item': {
			// @todo WordPress' list block doesn't support inner blocks.
			block.name = 'core/list-item';
			// There's a paragraph wrapping the list content.
			let innerNode = node.firstChild;
			while (innerNode) {
				switch (innerNode.type) {
					case 'paragraph':
						block.attributes.content = inlineBlocksToHTML(
							'',
							innerNode.firstChild
						);
						break;

					case 'list':
						nodeToBlock(block, innerNode);
						break;

					default:
						console.log(innerNode);
				}

				innerNode = innerNode.next;
			}
			skipChildren = true;
			break;
		}

		case 'heading':
			block.name = 'core/heading';
			// Content forms nodes starting with .firstChild -> .next -> .next
			block.attributes.level = node.level;
			block.attributes.content = inlineBlocksToHTML('', node.firstChild);
			skipChildren = true;
			break;

		case 'thematic_break':
			block.name = 'core/separator';
			break;

		case 'code_block':
			block.name = 'core/code';
			if ('string' === typeof node.info && '' !== node.info) {
				block.attributes.meta = {
					language: node.info.replace(/[ \t\r\n\f].*/, ''),
				};
			}

			// Allow storing arbitrary blocks.
			if (block.attributes?.meta?.language === 'block') {
				console.log('node.literal', node.literal);
				try {
					block = wp.blocks.parse(node.literal)[0];
					break;
				} catch (e) {
					// Log error and proceed as if it was a regular code block.
					console.error(e);
				}
			}

			block.attributes.content = escapeHTML(
				node.literal.replace(/\n$/, '')
			).replace(/\n/g, '<br>');
			break;

		case 'html_block':
			block.name = 'core/html';
			block.attributes.content = node.literal;
			break;

		case 'paragraph':
			// @todo Handle inline HTML, which should be an HTML block.
			if (
				node.firstChild &&
				node.firstChild.type === 'image' &&
				!node.firstChild.next
			) {
				// @todo If there's formatting, grab it from the children.
				const image = node.firstChild;
				block.name = 'core/image';
				block.attributes.url = image._destination;
				if (image._title && '' !== image._title) {
					block.attributes.caption = image._title;
				}
				if (image.firstChild) {
					block.attributes.alt = inlineBlocksToHTML(
						'',
						image.firstChild
					);
				} else if (image._description && '' !== image._description) {
					block.attributes.alt = image._description;
				}
				skipChildren = true;
				break;
			}

			block.name = 'core/paragraph';
			block.attributes.content = inlineBlocksToHTML('', node.firstChild);
			skipChildren = true;
			break;

		default:
			console.log(node);
	}

	if (block.name !== '') {
		add(block);
	}

	if (!skipChildren && node.firstChild) {
		nodeToBlock(block, node.firstChild);
	}

	if (node.next) {
		nodeToBlock(parentBlock, node.next);
	}
};

const inlineBlocksToHTML = (html, node) => {
	if (!node) {
		return html;
	}

	const add = (s) => (html += s);
	const surround = (before, after) =>
		add(before + inlineBlocksToHTML('', node.firstChild) + after);

	const addTag = (tag, tagAttrs = undefined) => {
		const attrs = tagAttrs
			? ' ' +
			  Object.entries(tagAttrs)
					.filter(([, value]) => value !== null)
					.map(([name, value]) => `${name}="${value}"`)
					.join(' ')
			: '';
		if ('img' === tag) {
			return add(`<${tag}${attrs}>`);
		}
		surround(`<${tag}${attrs}>`, `</${tag}>`);
	};

	switch (node.type) {
		case 'code':
			add(`<code>${escapeHTML(node.literal)}</code>`);
			break;

		case 'emph':
			addTag('em');
			break;

		case 'html_inline':
			add(escapeHTML(node.literal));
			break;

		case 'image':
			// @todo If there's formatting, grab it from the children.
			addTag('img', {
				src: node._destination,
				title: node._title || null,
				alt:
					inlineBlocksToHTML('', node.firstChild) ||
					node._description ||
					null,
			});
			break;

		case 'link':
			addTag('a', {
				href: node._destination,
				title: node._title || null,
			});
			break;

		case 'softbreak':
			add('<br>');
			break;

		case 'strong':
			addTag('strong');
			break;

		case 'text':
			add(node.literal);
			break;

		default:
			console.log(node);
	}

	if (node.next) {
		return inlineBlocksToHTML(html, node.next);
	}

	return html;
};

WpBlocksRenderer.prototype = Object.create(commonmark.Renderer.prototype);

WpBlocksRenderer.prototype.render = render;
WpBlocksRenderer.prototype.esc = (s) => s;

export const markdownToBlocks = (input) => {
	const frontMatterMatch = frontMatterPattern.exec(input);
	const foundFrontMatter = null !== frontMatterMatch;
	const frontMatter = foundFrontMatter ? frontMatterMatch[1] : null;
	const markdownDocument = foundFrontMatter
		? input.slice(frontMatterMatch[0].length)
		: input;
	frontMatterPattern.lastIndex = 0;

	const parser = new commonmark.Parser();
	installTableExtension(parser, commonmark.Node);
	const ast = parser.parse(markdownDocument);
	const blockRenderer = new WpBlocksRenderer({ sourcepos: true });

	return blockRenderer.render(ast);
};

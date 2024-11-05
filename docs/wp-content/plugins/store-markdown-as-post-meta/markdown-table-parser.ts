/**
 * ## Markdown table parser
 *
 * Implements a parser for GitHub-flavored Markdown tables that
 * tries to be compliant with the GitHub Markdown renderer.
 *
 * ### Compliance with the specification
 *
 * Tables are not part of the CommonMark specification.
 *
 * The GitHub Flavored Markdown specification roughly defines the
 * table syntax, but it does not provide a formal grammar for tables:
 *
 * https://github.github.com/gfm/#tables-extension-
 *
 * The behavior of the GitHub Markdown renderer is
 * the best reference we have.
 *
 * This below implementation was created by testing various parsing
 * scenarios in the GitHub Markdown renderer and then reproducing the same
 * behaviors. See the unit tests for specific examples.
 *
 * ### Parsing tables
 *
 * When a markdown document is processed, it's treated as high-level blocks, like:
 *
 * * paragraphs
 * * lists
 * * code blocks
 * * tables
 *
 * Within a table block, headers and rows are processed before any other
 * sub-syntaxes. In each of the examples below, the pipe symbol ends
 * the table cell and is not a part of the inline format.
 *
 * This is fortunate, because we can extract all the tables from the current
 * text node upfront without worrying about their interactions with other inline
 * formats like links, code blocks, or HTML tags.
 *
 * ```md
 * | site | description |
 * | --- | ----------- |
 * | <a href="https://wordpress.org?a|b">wp.org</a> | Official WordPress site |
 * ```
 *
 * ```md
 * | site | description |
 * | --- | ----------- |
 * | <a|b>wp.org</a|b> | Official WordPress site |
 * ```
 *
 * ```md
 * | site | description |
 * | --- | ----------- |
 * | [wp.org](https://wordpress.org?a|b) | Official WordPress site |
 * ```
 *
 * ```md
 * | site | description |
 * | --- | ----------- |
 * | `https://wordpress.org?a|b` | baz |
 * ```
 *
 * @see markdown-table-parser.spec.ts
 */

// Extending commonmark.js

const Start = {
	/* no match */
	noMatch: 0,

	/* matched container, keep going */
	matchedContainer: 1,

	/* matched leaf, no more block starts */
	matchedLeaf: 2,
} as const;

type StartResult = (typeof Start)[keyof typeof Start];

const Continue = {
	matched: 0,
	failedToMatch: 1,
	blockFinished: 2,
} as const;
type ContinueResult = (typeof Continue)[keyof typeof Continue];

export type BlockParser = {
	type: string;
	starts(parser: CommonMarkParser): StartResult;
	continue(parser: CommonMarkParser, container: any): ContinueResult;
	finalize(parser: CommonMarkParser, container: any): void;
	canContain(tag: string): boolean;
	acceptsLines: boolean;
};
export type CommonMarkParser = {
	indented: boolean;
	currentLine: string;
	nextNonspace: number;
	offset: number;
	advanceNextNonspace(): void;
	advanceOffset(count: number, columns: boolean): void;
	closeUnmatchedBlocks(): void;
	addChild<T>(type: string, startOffset: number): T;
	backtrack_table_lines?: string[];
	lineNumber: number;
	lastLineLength?: number;
	finalize<T>(container: T, lineNumber: number): void;
	processInlines(block: any): void;
	blocks: Record<string, any>;
	blockStarts: BlockParser['starts'][];
	isMaybeSpecial?: (line: string) => boolean;
	shouldInlineParse?: (tag: string) => boolean;
};

export type CommonMarkTableContainer = {
	_backtrack_table_lines: string[];
	_header: string[];
	_alignments?: ColumnAlignment[];
	_rows?: string[][];
};

export const TableHeadRowBlock: BlockParser = {
	type: 'table_head_row',
	starts: () => Start.noMatch,
	continue: () => Continue.failedToMatch,
	finalize: () => {},
	canContain: (tag) => tag === TableHeadCellBlock.type,
	acceptsLines: false,
};

export const TableHeadCellBlock: BlockParser = {
	type: 'table_head_cell',
	starts: () => Start.noMatch,
	continue: () => Continue.failedToMatch,
	finalize: () => {},
	canContain: () => false,
	acceptsLines: false,
};

export const TableBodyRowBlock: BlockParser = {
	type: 'table_body_row',
	starts: () => Start.noMatch,
	continue: () => Continue.failedToMatch,
	finalize: () => {},
	canContain: (tag) => tag === TableBodyCellBlock.type,
	acceptsLines: false,
};

export const TableBodyCellBlock: BlockParser = {
	type: 'table_body_cell',
	starts: () => Start.noMatch,
	continue: () => Continue.failedToMatch,
	finalize: () => {},
	canContain: () => false,
	acceptsLines: false,
};

export const TableBlock: BlockParser = {
	type: 'table',
	starts: (parser: CommonMarkParser) => {
        const header = parseTableRow(parser.currentLine, 0);
		if (null === header) {
			return Start.noMatch;
		}

		parser.closeUnmatchedBlocks();

		const container = parser.addChild<CommonMarkTableContainer>(
			TableBlock.type,
			parser.nextNonspace
		);
		container._backtrack_table_lines = [parser.currentLine];
		container._header = header.cells;
		container._rows = [];
		parser.advanceOffset(parser.currentLine.length, false);
		return Start.matchedContainer;
	},
	continue: function (
		parser: CommonMarkParser,
		container: CommonMarkTableContainer
	) {
		if (!container._alignments) {
			const alignments = parseDelimiters(parser.currentLine);
			if (null === alignments) {
				// Not a table – let's backtrack and re-consume the data.
				parser.lastLineLength = parser.currentLine.length;
				parser.finalize(container, parser.lineNumber);

				// @TODO: re-consume the data
				return Continue.blockFinished;
			}
			container._alignments = alignments.alignments;
			parser.advanceOffset(parser.currentLine.length, false);
			return Continue.matched;
		}

        const row = parseTableRow(parser.currentLine, 0);
		if (null === row) {
			// closing the table – we got a line that is not a table row
			return Continue.failedToMatch;
		}

		container._rows!.push(row.cells);
		parser.advanceOffset(parser.currentLine.length, false);
		return Continue.matched;
	},
	finalize: function (
		parser: CommonMarkParser,
		container: CommonMarkTableContainer
    ) {
		// Create the header row
		parser.addChild<any>(TableHeadRowBlock.type, -1);
		for (const header of container._header) {
			// Cells are implicitly appended to the row by the parser
			const cell = parser.addChild<any>(TableHeadCellBlock.type, -1);
			cell._string_content = header;
		}

        // Create the content rows
		for (const row of container._rows!) {
			// Row is implicitly appended to the table by the parser
			parser.addChild<any>(TableBodyRowBlock.type, -1);
			for (const cell of row) {
				const cellBlock = parser.addChild<any>(
					TableBodyCellBlock.type,
					-1
				);
				cellBlock._string_content = cell;
			}
		}
	},
	canContain: function (tag) {
		return tag === TableBodyRowBlock.type || tag === TableHeadRowBlock.type;
	},
	acceptsLines: true,
};

export function installTableExtension(
	parser: CommonMarkParser,
	NodeClass: any
) {
	const originalDescriptor = Object.getOwnPropertyDescriptor(
		NodeClass.prototype,
		'isContainer'
	);
	const originalIsContainer =
		originalDescriptor && (originalDescriptor.get as any);
	Object.defineProperty(NodeClass.prototype, 'isContainer', {
        get: function () {
			return (
				originalIsContainer.call(this) ||
				this.type === TableBlock.type ||
				this.type === TableBodyRowBlock.type ||
				this.type === TableBodyCellBlock.type ||
				this.type === TableHeadRowBlock.type ||
				this.type === TableHeadCellBlock.type
			);
		},
	});
	parser.shouldInlineParse = function (tag: string) {
        return (
            tag === TableHeadCellBlock.type ||
            tag === TableBodyCellBlock.type
        );
	};
	parser.isMaybeSpecial = function (line: string) {
		return line.startsWith('|');
	};
	installBlockParser(parser, TableBlock);
	installBlockParser(parser, TableBodyRowBlock);
	installBlockParser(parser, TableBodyCellBlock);
	installBlockParser(parser, TableHeadRowBlock);
	installBlockParser(parser, TableHeadCellBlock);
}

function installBlockParser(
	parser: CommonMarkParser,
	blockParser: BlockParser
) {
	parser.blockStarts.unshift(blockParser.starts);
	parser.blocks[blockParser.type] = blockParser;
}

// ------- generic table parsing functions ---------

type ColumnAlignment = 'left' | 'right' | 'center' | null;

/**
 * Extracts markdown tables from a document.
 *
 * This generator function will yield two types of objects:
 *
 * - `literal` objects, which contain substring of the original document
 *    text that is not part of a table. Any markdown formats, HTML tags,
 *    repeated whitespaces and so on will remain unchanged.
 * - `table` objects, which contain the parsed table data. The text content
 *    of the table cells is trimmed of any leading or trailing whitespace,
 *    but otherwise remains unchanged.
 *
 * @param markdown
 */
export function* extractTablesFromDocument(markdown: string) {
	let at = 0;

	let textStartsAt = at;
	let nextLineAt;
	while (true) {
		if (at >= markdown.length) {
			const text = markdown.substring(textStartsAt, at);
			if (text.length > 0) {
				yield {
					type: 'literal',
					text,
				};
			}
			break;
		}
		nextLineAt = markdown.indexOf('\n', at);
		if (nextLineAt === -1) {
			nextLineAt = markdown.length;
		}

		// Skip the line – there's no table to parse.
		// We know, because tables must start with a pipe character.
		if (markdown[at] !== '|') {
			at = nextLineAt + 1;
			continue;
		}

		let tableStartsAt = at;

		// This could be a start of a table
		const header = parseTableRow(markdown, at);
		if (null === header) {
			// Not a table – let's consume the data as text and move on.
			at = nextLineAt + 1;
			continue;
		}
		at = header.endOffset + 1;

		const alignments = parseDelimiters(markdown, at);
		if (null === alignments) {
			// Not a table – let's consume the data as text and move on.
			at = nextLineAt + 1;
			continue;
		}
		at = alignments.endOffset + 1;

		// At this point we have a table. We don't know if it has any rows yet,
		// but it is a table.

		// If we were parsing text, let's yield it now.
		if (textStartsAt !== tableStartsAt) {
			const text = markdown.substring(textStartsAt, tableStartsAt);
			if (text.length > 0) {
				yield {
					type: 'literal',
					text,
				};
			}
		}

		// Parse the table rows.
		const rows: string[][] = [];
		while (true) {
			const row = parseTableRow(markdown, at);
			if (null === row) {
				break;
			}
			rows.push(row.cells);
			at = row.endOffset + 1;
		}

		yield {
			type: 'table',
			headers: header.cells,
			alignments: alignments.alignments,
			rows,
		};

		textStartsAt = at;
	}
}

/**
 * > The delimiter row consists of cells whose only content are hyphens (-),
 * > and optionally, a leading or trailing colon (:), or both, to indicate left,
 * > right, or center alignment respectively.
 *
 * @see https://github.github.com/gfm/#delimiter-row
 */
export function parseTableRow(markdown: string, at: number = 0) {
	const cells: string[] = [];

	let lineEnd = markdown.indexOf('\n', at);
	if (lineEnd === -1) {
		lineEnd = markdown.length;
	}

	// The row is indented, bale.
	if (at < lineEnd && markdown[at] === ' ') {
		return null;
	}

    let pipes = 0;
	if (markdown[at] === '|') {
		// The first cell may optionally start with a pipe character.
		// Let's skip the pipe character and all the whitespace after it.
        ++at;
        ++pipes;
		while (at < lineEnd && markdown[at] === ' ') {
			++at;
		}
	}

	if (at >= lineEnd) {
		return null;
	}

	let textStart = at;
	let textNodes: string[] = [];
	while (true) {
		if (at >= lineEnd) {
			// Row's end - let's finish
            // Store the contents if at least one pipe character was found
            if (pipes > 0) {
                textNodes.push(markdown.substring(textStart, at));
                cells.push(textNodes.join('').trim());
            }
			textNodes = [];
			break;
		} else if (markdown[at] === '|') {
            // Cell's end – store the contents
            ++pipes;
			textNodes.push(markdown.substring(textStart, at));
			cells.push(textNodes.join('').trim());
			textNodes = [];
			++at;
			textStart = at;

			// Skip all the whitespace
			while (at < lineEnd && markdown[at] === ' ') {
				++at;
			}

			if (at === lineEnd) {
				// Row ends before the next cell starts, we're done
				break;
			}
		} else if (markdown[at] === '\\') {
			// Escape sequence – skip the next character
			textNodes.push(markdown.substring(textStart, at));
			textStart = at + 1;
			at += 2;
		} else {
			++at;
		}
	}

	// Must have at least one cell
	if (cells.length === 0) {
		return null;
	}

	return {
		cells,
		endOffset: at,
	};
}

/**
 * > The delimiter row consists of cells whose only content are hyphens (-),
 * > and optionally, a leading or trailing colon (:), or both, to indicate left,
 * > right, or center alignment respectively.
 *
 * @see https://github.github.com/gfm/#delimiter-row
 */
export function parseDelimiters(markdown: string, at: number = 0) {
	const alignments: ColumnAlignment[] = [];
	function pushAlignment(leftColon, rightColon) {
		alignments.push(
			leftColon && rightColon
				? 'center'
				: leftColon
				? 'left'
				: rightColon
				? 'right'
				: null
		);
	}

	let lineEnd = markdown.indexOf('\n', at);
	if (lineEnd === -1) {
		lineEnd = markdown.length;
	}

	// Skip the initial whitespace
	while (at < lineEnd && markdown[at] === ' ') {
		++at;
	}

	if (markdown[at] === '|') {
		// The first cell may optionally start with a pipe character.

		// Let's skip the pipe character and all the whitespace after it.
		++at;
		while (at < lineEnd && markdown[at] === ' ') {
			++at;
		}
	} else if (at !== 0) {
		// This row is indented, but the first cell doesn't start with a pipe character.
		// This isn't allowed, let's bale out.
		return null;
	}

	while (at < lineEnd) {
		let leftColon = false;
		let rightColon = false;

		if (markdown[at] === ':') {
			// We just started the first cell with a colon for alignment
			leftColon = true;
			++at;

			// A colon must be followed by at least one hyphen
			if (markdown[at] !== '-') {
				return null;
			}
		} else if (markdown[at] === '-') {
			// We just started the first cell. Let's not move the pointer
			// and let the next while() loop consume the hyphens.
		} else {
			return null;
		}

		// Skip all the hyphens
		let hyphens = 0;
		while (at < lineEnd && markdown[at] === '-') {
			++at;
			++hyphens;
		}

		// Can't have a cell without hyphens
		if (hyphens === 0) {
			return null;
		}

		// Finished parsing the row
		if (at >= lineEnd) {
			pushAlignment(leftColon, rightColon);
			break;
		}

		// Consume the optional right colon.
		if (markdown[at] === ':') {
			rightColon = true;
			++at;
		}

		pushAlignment(leftColon, rightColon);

		// Skip all the whitespace
		while (at < lineEnd && markdown[at] === ' ') {
			++at;
		}

		if (markdown[at] === '|') {
			// We just finished parsing the cell, skip the pipe character and all the whitespace.
			++at;
			while (at < lineEnd && markdown[at] === ' ') {
				++at;
			}
		} else if (at === lineEnd) {
			// We just finished parsing the entire row.
			break;
		} else {
			// Unexpected character, let's bale.
			return null;
		}
	}

	// Must have at least one alignment
	if (alignments.length === 0) {
		return null;
	}

	return {
		alignments,
		endOffset: at,
	};
}

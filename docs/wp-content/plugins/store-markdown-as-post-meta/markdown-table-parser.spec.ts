import {
	extractTablesFromDocument,
	parseDelimiters,
	parseTableRow,
} from './markdown-table-parser';

describe('extractTablesFromDocument', () => {
	it('should parse a paragraph with only a table', () => {
		const generator = extractTablesFromDocument(`| Header 1 | Header 2 |
| -------- | -------- |
| Row 1    | Row 2    |
`);
		expect(Array.from(generator)).toEqual([
			{
				type: 'table',
				headers: ['Header 1', 'Header 2'],
				alignments: [null, null],
				rows: [['Row 1', 'Row 2']],
			},
		]);
	});
	it('should parse a paragraph with trailing text and a table', () => {
		const generator =
			extractTablesFromDocument(`The open source publishing platform of choice for millions of 
websites worldwide—from creators and small businesses to enterprises.

| Header 1 | Header 2 |
| -------- | -------- |
| Row 1    | Row 2    |

The open source publishing platform of choice for millions of 
websites worldwide—from creators and small businesses to enterprises.
`);
		expect(Array.from(generator)).toEqual([
			{
				type: 'literal',
				text: 'The open source publishing platform of choice for millions of \nwebsites worldwide—from creators and small businesses to enterprises.\n\n',
			},
			{
				type: 'table',
				headers: ['Header 1', 'Header 2'],
				alignments: [null, null],
				rows: [['Row 1', 'Row 2']],
			},
			{
				type: 'literal',
				text: '\nThe open source publishing platform of choice for millions of \nwebsites worldwide—from creators and small businesses to enterprises.\n',
			},
		]);
	});

	it('should parse a table with invalid rows', () => {
		const generator = extractTablesFromDocument(`
| Header 1 | Header 2 |
| -------- | -------- |
| Row 1    | Row 2    |
    | Row 1    | Row 2    |
| Row 1    | Row 2    |
`);
		expect(Array.from(generator)).toEqual([
			{
				text: '\n',
				type: 'literal',
			},
			{
				type: 'table',
				headers: ['Header 1', 'Header 2'],
				alignments: [null, null],
				rows: [['Row 1', 'Row 2']],
			},
			{
				type: 'literal',
				text: `    | Row 1    | Row 2    |\n| Row 1    | Row 2    |\n`,
			},
		]);
	});
});

describe('parseTableRow', () => {
	it('should parse a well-formed row', () => {
		const parsed = parseTableRow(`| -- -- | ---- |`, 0);
		expect(parsed?.cells).toEqual(['-- --', '----']);
	});

	it('should parse a row with textual contents', () => {
		const parsed = parseTableRow(
			`| this is left column | and right column |`,
			0
		);
		expect(parsed?.cells).toEqual([
			'this is left column',
			'and right column',
		]);
	});

	it('should treat escaped pipes as text', () => {
		const parsed = parseTableRow(`|le\\|ft|right|`, 0);
		expect(parsed?.cells).toEqual(['le|ft', 'right']);
	});

	it('should parse a row with ample whitespace inside columns', () => {
		const parsed = parseTableRow(`|       left       |right|`, 0);
		expect(parsed?.cells).toEqual(['left', 'right']);
	});

	it('should parse a row with no whitespace inside columns', () => {
		const parsed = parseTableRow(`|left|right|`, 0);
		expect(parsed?.cells).toEqual(['left', 'right']);
    });
    
	it('should parse a row with no pipe characters', () => {
		const parsed = parseTableRow(`left`, 0);
		expect(parsed).toEqual(null);
	});

	it('should fail to parse an all-whitespace line ending with string end', () => {
		const parsed = parseTableRow(` `, 0);
		expect(parsed).toEqual(null);
	});

	it('should fail to parse an all-whitespace line ending with newline', () => {
		const parsed = parseTableRow(`\n`, 0);
		expect(parsed).toEqual(null);
	});

	it('should fail to parse an indented row (with trailing pipes)', () => {
		const parsed = parseTableRow(`   | -- -- | ---- |`, 0);
		expect(parsed).toEqual(null);
	});

	it('should fail to parse an indented row (without the trailing pipe)', () => {
		const parsed = parseTableRow(`   -- -- | ---- |`, 0);
		expect(parsed).toEqual(null);
	});
});

describe('parseDelimiters', () => {
	describe.each([
		[false, false],
		[true, false],
		[false, true],
		[true, true],
	])('(left pipe: %j, right pipe: %jd)', (leftPipe, rightPipe) => {
		const lp = leftPipe ? '|' : '';
		const rp = rightPipe ? '|' : '';

		it('should parse a delimiter row', () => {
			const parsed = parseDelimiters(`${lp}--------|--------${rp}\n`, 0);
			expect(parsed?.alignments).toEqual([null, null]);
		});

		it('should parse a delimiter row with no newline at the end', () => {
			const parsed = parseDelimiters(`${lp}--------|--------${rp}`, 0);
			expect(parsed?.alignments).toEqual([null, null]);
		});

		it('should parse a delimiter row with all types of alignment-denoting colons', () => {
			const parsed = parseDelimiters(
				`${lp}:----:|:----|----:|-----${rp}`,
				0
			);
			expect(parsed?.alignments).toEqual([
				'center',
				'left',
				'right',
				null,
			]);
		});

		it('should parse a delimiter row with all types of alignment-denoting colons and only a single hyphen', () => {
			const parsed = parseDelimiters(`${lp}:-:|:-|-:|-${rp}`, 0);
			expect(parsed?.alignments).toEqual([
				'center',
				'left',
				'right',
				null,
			]);
		});

		describe('invalid colon placement', () => {
			it('should fail to parse a delimiter row if a cell contains a single colon', () => {
				const parsed = parseDelimiters(`${lp}:|---${rp}\n`, 0);
				expect(parsed).toEqual(null);
			});
			it('should fail to parse a delimiter row if a cell contains a double colon', () => {
				const parsed = parseDelimiters(`${lp}::|---${rp}\n`, 0);
				expect(parsed).toEqual(null);
			});
		});

		it('should fail to parse a delimiter row with garbage at the end', () => {
			const parsed = parseDelimiters(`${lp}::|---${rp}\n`, 0);
			expect(parsed).toEqual(null);
		});
	});

	describe('indented rows', () => {
		it('should allow indentation if the data starts with a pipe', () => {
			const parsed = parseDelimiters(`  |---|---\n`, 0);
			expect(parsed?.alignments).toEqual([null, null]);
		});
		it('should bale on indentation when the data does not start with a pipe', () => {
			const parsed = parseDelimiters(`  ---|---\n`, 0);
			expect(parsed).toEqual(null);
		});
	});

	it('should parse a delimiter row with ample whitespace', () => {
		const parsed = parseDelimiters(
			`|     -------    |   --------     |`,
			0
		);
		expect(parsed?.alignments).toEqual([null, null]);
	});

	describe('should bale when unexpected characters are found', () => {
		it('whitespace', () => {
			const parsed = parseDelimiters(`| -- -- | ---- |`, 0);
			expect(parsed).toEqual(null);
		});

		it('character', () => {
			const parsed = parseDelimiters(`| --a-- | ---- |`, 0);
			expect(parsed).toEqual(null);
		});

		it('colon', () => {
			const parsed = parseDelimiters(`| --:-- | ---- |`, 0);
			expect(parsed).toEqual(null);
		});

		it('pipe', () => {
			const parsed = parseDelimiters(`| --:-- | ---- ||`, 0);
			expect(parsed).toEqual(null);
		});
	});

	describe('should bale when unsupported syntax is found', () => {
		it('zero-width cells', () => {
			const parsed = parseDelimiters(`|| ---- |`, 0);
			expect(parsed).toEqual(null);
		});
		it('just a single, middle pipe', () => {
			const parsed = parseDelimiters(`|`, 0);
			expect(parsed).toEqual(null);
		});
		it('middle pipe surrounded by whitespace', () => {
			const parsed = parseDelimiters(` | `, 0);
			expect(parsed).toEqual(null);
		});
		it('trailing pipes, whitespace-only cells', () => {
			const parsed = parseDelimiters(`| | |`, 0);
			expect(parsed).toEqual(null);
		});
	});
});

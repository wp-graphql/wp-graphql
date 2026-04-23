import React from 'react';
import { render, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';
import { getIntrospectionQuery } from 'graphql';
import { useSchema } from '../../../../src/hooks/useSchema';

// Mock @wordpress/data.
let mockSchema;
let mockSetSchema;
jest.mock('@wordpress/data', () => ({
	useSelect: (fn) => fn(() => ({ schema: () => mockSchema })),
	useDispatch: () => ({ setSchema: mockSetSchema }),
}));

// Minimal introspection result (valid shape).
const makeIntrospectionResult = () => ({
	data: {
		__schema: {
			queryType: { name: 'Query' },
			mutationType: null,
			subscriptionType: null,
			types: [
				{
					kind: 'OBJECT',
					name: 'Query',
					description: null,
					fields: [
						{
							name: 'hello',
							description: null,
							args: [],
							type: {
								kind: 'SCALAR',
								name: 'String',
								ofType: null,
							},
							isDeprecated: false,
							deprecationReason: null,
						},
					],
					inputFields: null,
					interfaces: [],
					enumValues: null,
					possibleTypes: null,
				},
				{
					kind: 'SCALAR',
					name: 'String',
					description: null,
					fields: null,
					inputFields: null,
					interfaces: null,
					enumValues: null,
					possibleTypes: null,
				},
			],
			directives: [],
		},
	},
});

// Harness component to exercise the hook.
function Harness({ fetcher }) {
	const { schema, isLoading } = useSchema(fetcher);
	return (
		<div>
			<span data-testid="loading">{isLoading ? 'yes' : 'no'}</span>
			<span data-testid="schema">{schema ? 'loaded' : 'empty'}</span>
		</div>
	);
}

describe('useSchema', () => {
	beforeEach(() => {
		mockSchema = undefined;
		mockSetSchema = jest.fn();
	});

	test('fetches introspection query on mount when schema is undefined', async () => {
		const fetcher = jest.fn().mockResolvedValue(makeIntrospectionResult());
		render(<Harness fetcher={fetcher} />);

		await waitFor(() => {
			expect(fetcher).toHaveBeenCalledWith({
				query: getIntrospectionQuery(),
			});
		});
	});

	test('calls setSchema with built client schema on successful introspection', async () => {
		const fetcher = jest.fn().mockResolvedValue(makeIntrospectionResult());
		render(<Harness fetcher={fetcher} />);

		await waitFor(() => {
			expect(mockSetSchema).toHaveBeenCalled();
		});
		const schema = mockSetSchema.mock.calls[0][0];
		expect(schema).toBeDefined();
		expect(schema.getQueryType().name).toBe('Query');
	});

	test('does not refetch when schema is already set', () => {
		mockSchema = { fake: 'schema' };
		const fetcher = jest.fn();
		render(<Harness fetcher={fetcher} />);
		expect(fetcher).not.toHaveBeenCalled();
	});
});

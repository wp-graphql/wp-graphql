import { useSelect, useDispatch } from '@wordpress/data';
import ExplorerWrapper from './ExplorerWrapper';
import '../style.css';
import ArrowOpen from './ArrowOpen';
import ArrowClosed from './ArrowClosed';
import { checkboxUnchecked, checkboxChecked } from './Checkbox';

const colors = {
	keyword: 'hsl(var(--color-primary))',
	def: 'hsl(var(--color-tertiary))',
	property: 'hsl(var(--color-info))',
	qualifier: 'hsl(var(--color-secondary))',
	attribute: 'hsl(var(--color-tertiary))',
	number: 'hsl(var(--color-success))',
	string: 'hsl(var(--color-warning))',
	builtin: 'hsl(var(--color-success))',
	string2: 'hsl(var(--color-secondary))',
	variable: 'hsl(var(--color-secondary))',
	atom: 'hsl(var(--color-tertiary))',
};

const styles = {
	buttonStyle: {
		backgroundColor: 'transparent',
		border: 'none',
		color: 'hsla(var(--color-neutral), var(--alpha-secondary, 0.6))',
		cursor: 'pointer',
		fontSize: '1em',
	},
	explorerActionsStyle: {
		padding: 'var(--px-8) var(--px-4)',
	},
	actionButtonStyle: {
		backgroundColor: 'transparent',
		border: 'none',
		color: 'hsla(var(--color-neutral), var(--alpha-secondary, 0.6))',
		cursor: 'pointer',
		fontSize: '1em',
	},
};

export const QueryComposer = (props) => {
	const schema = useSelect((select) => select('wpgraphql-ide/app').schema());

	const query = useSelect((select) => select('wpgraphql-ide/app').getQuery());

	const { setQuery } = useDispatch('wpgraphql-ide/app');

	return (
		<>
			<ExplorerWrapper
				{...props}
				schema={schema}
				query={query}
				explorerIsOpen
				colors={colors}
				arrowOpen={ArrowOpen}
				arrowClosed={ArrowClosed}
				checkboxUnchecked={checkboxUnchecked}
				checkboxChecked={checkboxChecked}
				styles={styles}
				title={'Query Composer'}
				onEdit={(newQuery) => {
					setQuery(newQuery);
				}}
			/>
		</>
	);
};

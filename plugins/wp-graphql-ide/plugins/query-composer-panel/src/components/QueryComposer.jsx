import { useSelect, useDispatch } from '@wordpress/data';
import ExplorerWrapper from './ExplorerWrapper';
import '../style.css';
import ArrowOpen from './ArrowOpen';
import ArrowClosed from './ArrowClosed';
import { checkboxUnchecked, checkboxChecked } from './Checkbox';

// Palette mirrors the editor's syntax-highlight tokens so the composer reads
// like the GraphQL document on the right. Values resolve from the
// `--gql-color-*` CSS variables defined on `#wpgraphql-ide-app`, so updating
// one place themes the whole IDE.
const colors = {
	keyword: 'var(--gql-color-keyword)',
	def: 'var(--gql-color-operation-name)',
	property: 'var(--gql-color-field)',
	qualifier: 'var(--gql-color-argument)',
	attribute: 'var(--gql-color-argument)',
	number: 'var(--gql-color-number)',
	string: 'var(--gql-color-string)',
	builtin: 'var(--gql-color-bool)',
	string2: 'var(--gql-color-enum)',
	variable: 'var(--gql-color-variable)',
	atom: 'var(--gql-color-enum)',
};

const styles = {
	buttonStyle: {},
	explorerActionsStyle: {},
	actionButtonStyle: {},
};

export const QueryComposer = (props) => {
	const schema = useSelect((select) => select('wpgraphql-ide/app').schema());

	const query = useSelect((select) => select('wpgraphql-ide/app').getQuery());

	const cursorOffset = useSelect((select) =>
		select('wpgraphql-ide/app').getCursorOffset()
	);

	const { setQuery } = useDispatch('wpgraphql-ide/app');

	return (
		<ExplorerWrapper
			{...props}
			schema={schema}
			query={query}
			cursorOffset={cursorOffset}
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
	);
};

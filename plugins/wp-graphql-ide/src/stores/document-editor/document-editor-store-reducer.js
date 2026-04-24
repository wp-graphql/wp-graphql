const initialState = {
	buttons: {},
	documents: {},
	openTabs: [],
	activeTab: null,
};

const reducer = (state = initialState, action) => {
	switch (action.type) {
		case 'REGISTER_BUTTON': {
			if (action.name in state.buttons) {
				console.warn({
					message: `The "${action.name}" button already exists. Name must be unique.`,
					existingButton: state.buttons[action.name],
					duplicateButton: action.config,
				});
				return state;
			}

			return {
				...state,
				buttons: {
					...state.buttons,
					[action.name]: {
						config: action.config,
						priority: action.priority || 10,
					},
				},
			};
		}

		case 'SET_DOCUMENTS': {
			const documents = {};
			for (const doc of action.documents) {
				documents[String(doc.id)] = doc;
			}
			return { ...state, documents };
		}

		case 'CREATE_IN_MEMORY_TAB': {
			const tempDoc = {
				id: action.tempId,
				title: action.title,
				query: '',
				variables: '',
				headers: '',
				dirty: false,
			};
			return {
				...state,
				documents: {
					...state.documents,
					[action.tempId]: tempDoc,
				},
				openTabs: [...state.openTabs, action.tempId],
				activeTab: action.tempId,
			};
		}

		case 'UPDATE_DOCUMENT_ID': {
			const docs = { ...state.documents };
			delete docs[action.oldId];
			docs[String(action.newId)] = action.document;

			const newTabs = state.openTabs.map((id) =>
				id === action.oldId ? String(action.newId) : id
			);
			const newActive =
				state.activeTab === action.oldId
					? String(action.newId)
					: state.activeTab;

			return {
				...state,
				documents: docs,
				openTabs: newTabs,
				activeTab: newActive,
			};
		}

		case 'UPDATE_DOCUMENT':
			return {
				...state,
				documents: {
					...state.documents,
					[String(action.document.id)]: {
						...state.documents[String(action.document.id)],
						...action.document,
					},
				},
			};

		case 'REMOVE_DOCUMENT': {
			const next = { ...state.documents };
			delete next[String(action.id)];
			return { ...state, documents: next };
		}

		case 'SET_DOCUMENT_DIRTY':
			if (!state.documents[String(action.id)]) {
				return state;
			}
			return {
				...state,
				documents: {
					...state.documents,
					[String(action.id)]: {
						...state.documents[String(action.id)],
						dirty: action.dirty,
					},
				},
			};

		case 'SET_DOCUMENT_RESPONSE':
			if (!state.documents[String(action.id)]) {
				return state;
			}
			return {
				...state,
				documents: {
					...state.documents,
					[String(action.id)]: {
						...state.documents[String(action.id)],
						lastResponse: action.response,
					},
				},
			};

		case 'SET_OPEN_TABS':
			return { ...state, openTabs: action.tabIds };

		case 'OPEN_TAB':
			if (state.openTabs.includes(action.tabId)) {
				return state;
			}
			return {
				...state,
				openTabs: [...state.openTabs, action.tabId],
			};

		case 'CLOSE_TAB':
			return {
				...state,
				openTabs: state.openTabs.filter((id) => id !== action.tabId),
				activeTab:
					state.activeTab === action.tabId
						? state.openTabs.find((id) => id !== action.tabId) ||
							null
						: state.activeTab,
			};

		case 'SET_ACTIVE_TAB':
			return { ...state, activeTab: action.tabId };

		default:
			return state;
	}
};

export default reducer;

const initialState = {
	buttons: {},
	documents: {},
	// Numeric-string object keys are normalised to numeric ascending
	// order by the JS engine, which clobbers insertion order. Track
	// document order separately so reorder actions actually stick.
	documentIds: [],
	openTabs: [],
	activeTab: null,
	tabTypes: {},
	topbarActions: {},
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
			const documentIds = [];
			for (const doc of action.documents) {
				const key = String(doc.id);
				documents[key] = doc;
				documentIds.push(key);
			}
			return { ...state, documents, documentIds };
		}

		case 'REORDER_DOCUMENTS': {
			const known = new Set(state.documentIds);
			const ordered = [];
			for (const id of action.ids) {
				const key = String(id);
				if (known.has(key)) {
					ordered.push(key);
					known.delete(key);
				}
			}
			// Append any IDs not in the new order (e.g. temp drafts).
			for (const key of state.documentIds) {
				if (known.has(key)) {
					ordered.push(key);
				}
			}
			return { ...state, documentIds: ordered };
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
				documentIds: [...state.documentIds, action.tempId],
				openTabs: [
					...state.openTabs,
					{ id: action.tempId, type: 'query-editor' },
				],
				activeTab: action.tempId,
			};
		}

		case 'UPDATE_DOCUMENT_ID': {
			const docs = { ...state.documents };
			delete docs[action.oldId];
			docs[String(action.newId)] = action.document;

			const documentIds = state.documentIds.map((id) =>
				id === action.oldId ? String(action.newId) : id
			);

			const newTabs = state.openTabs.map((tab) =>
				tab.id === action.oldId
					? { ...tab, id: String(action.newId) }
					: tab
			);
			const newActive =
				state.activeTab === action.oldId
					? String(action.newId)
					: state.activeTab;

			return {
				...state,
				documents: docs,
				documentIds,
				openTabs: newTabs,
				activeTab: newActive,
			};
		}

		case 'UPDATE_DOCUMENT': {
			// Also append to documentIds so virtual workspace-tab documents
			// (registered via openWorkspaceTab → UPDATE_DOCUMENT) show up in
			// `getDocuments()` and the IDE tab strip can render them. For
			// real documents already in documentIds this is a no-op.
			const key = String(action.document.id);
			return {
				...state,
				documents: {
					...state.documents,
					[key]: {
						...state.documents[key],
						...action.document,
					},
				},
				documentIds: state.documentIds.includes(key)
					? state.documentIds
					: [...state.documentIds, key],
			};
		}

		case 'REMOVE_DOCUMENT': {
			const key = String(action.id);
			const next = { ...state.documents };
			delete next[key];
			return {
				...state,
				documents: next,
				documentIds: state.documentIds.filter((id) => id !== key),
			};
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
			return {
				...state,
				openTabs: action.tabIds.map((t) =>
					typeof t === 'string' ? { id: t, type: 'query-editor' } : t
				),
			};

		case 'OPEN_TAB':
			if (state.openTabs.some((tab) => tab.id === action.tabId)) {
				return state;
			}
			return {
				...state,
				openTabs: [
					...state.openTabs,
					{
						id: action.tabId,
						type: action.tabType || 'query-editor',
					},
				],
			};

		case 'CLOSE_TAB':
			return {
				...state,
				openTabs: state.openTabs.filter(
					(tab) => tab.id !== action.tabId
				),
				activeTab:
					state.activeTab === action.tabId
						? state.openTabs.find((tab) => tab.id !== action.tabId)
								?.id || null
						: state.activeTab,
			};

		case 'SET_ACTIVE_TAB':
			return { ...state, activeTab: action.tabId };

		case 'REGISTER_TAB_TYPE':
			return {
				...state,
				tabTypes: {
					...state.tabTypes,
					[action.name]: {
						title: action.config.title,
						content: action.config.content,
						icon: action.config.icon || null,
					},
				},
			};

		case 'REGISTER_TOPBAR_ACTION':
			return {
				...state,
				topbarActions: {
					...state.topbarActions,
					[action.name]: {
						title: action.config.title,
						icon: action.config.icon,
						tabType: action.config.tabType,
						tabId: action.config.tabId || action.config.tabType,
						priority: action.priority || 10,
					},
				},
			};

		default:
			return state;
	}
};

export default reducer;

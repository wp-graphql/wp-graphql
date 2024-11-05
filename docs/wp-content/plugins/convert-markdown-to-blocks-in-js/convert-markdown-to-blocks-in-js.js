(async function () {
	if (window.location.pathname !== '/wp-admin/post-new.php') {
		window.location = '/wp-admin/post-new.php?post_type=page';
		return;
	}

	function endLoading() {
		document.body.classList.remove('playground-markdown-loading');
	}

	if (
		!window.playgroundMarkdown.markdown ||
		!window.playgroundMarkdown.markdown.length
	) {
		document.addEventListener('DOMContentLoaded', endLoading);
		return;
	} else {
		document.addEventListener('DOMContentLoaded', () => {
			document.body.classList.add('playground-markdown-loading');
		});
	}

	window.addEventListener('beforeunload', (event) => {
		event.stopImmediatePropagation();
	});

	const { markdownToBlocks } = await import(
		'../store-markdown-as-post-meta/markdown-to-blocks.js'
	);

	// Wait until core blocks are registered
	while (!wp.blocks.getBlockTypes().length) {
		await new Promise((resolve) => setTimeout(resolve, 100));
	}

	const createBlocks = (blocks) =>
		blocks.map((block) =>
			wp.blocks.createBlock(
				block.name,
				block.attributes,
				block.innerBlocks ? createBlocks(block.innerBlocks) : []
			)
		);

	const pagesWithBlockMarkup = [];
	for (const file of window.playgroundMarkdown.markdown) {
		const blocks = createBlocks(markdownToBlocks(file.md));
		pagesWithBlockMarkup.push({
			...file,
			blockhtml: wp.blocks.serialize(blocks),
		});
	}

	const response = await window.wp.apiFetch({
		path: '/wp/v2/markdown-bulk-import',
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce': wpApiSettings.nonce,
		},
		data: {
			pages: pagesWithBlockMarkup,
		},
	});
	try {
		console.log(await response.text());
	} catch {}

	// Redirect to the page we were on before the import process started
	let returnTo = new URL(window.location.href).searchParams.get(
		'markdown_import_return_to'
	);
	while (true) {
		let search;
		try {
			search = new URL(returnTo).searchParams;
		} catch (e) {
			break;
		}
		if (!search.has('markdown_import_return_to')) {
			break;
		}
		returnTo = search.get('markdown_import_return_to');
	}

	if (!returnTo) {
		returnTo = '/wp-admin/edit.php?post_type=page';
	}

	window.location.href = returnTo;
})();

import HelpCard from './HelpCard';

export const helpCards = [
	{
		title: 'Getting Started',
		description:
			'In the Getting Started section of the WPGraphQL website you will find resources to learn about GraphQL, WordPress, how they work together, and more.',
		linkText: 'Get Started with WPGraphQL',
		linkUrl: 'https://www.wpgraphql.com/docs/intro-to-graphql/',
	},
	{
		title: 'Beginner Guides',
		description:
			'The Beginner guides go over specific topics such as GraphQL, WordPress, tools and techniques to interact with GraphQL APIs and more.',
		linkText: 'Beginner Guides',
		linkUrl: 'https://www.wpgraphql.com/docs/introduction/',
	},
	{
		title: 'Using WPGraphQL',
		description:
			'Learn how WPGraphQL exposes WordPress data to the Graph, and shows how you can interact with this data using GraphQL.',
		linkText: 'Using WPGraphQL',
		linkUrl: 'https://www.wpgraphql.com/docs/posts-and-pages/',
	},
	{
		title: 'Advanced Concepts',
		description:
			'Learn about concepts such as "connections", "edges", "nodes", what is an application data graph?" and more',
		linkText: 'Advanced Concepts',
		linkUrl: 'https://www.wpgraphql.com/docs/wpgraphql-concepts/',
	},
	{
		title: 'Recipes',
		description:
			'Here you will find snippets of code you can use to customize WPGraphQL. Most snippets are PHP and intended to be included in your theme or plugin.',
		linkText: 'Recipes',
		linkUrl: 'https://www.wpgraphql.com/recipes',
	},
	{
		title: 'Actions',
		description:
			'Here you will find an index of the WordPress "actions" that are used in the WPGraphQL codebase. Actions can be used to customize behaviors.',
		linkText: 'Actions',
		linkUrl: 'https://www.wpgraphql.com/actions',
	},
	{
		title: 'Filters',
		description:
			'Here you will find an index of the WordPress "filters" that are used in the WPGraphQL codebase. Filters are used to customize the Schema and more.',
		linkText: 'Filters',
		linkUrl: 'https://www.wpgraphql.com/filters',
	},
	{
		title: 'Functions',
		description:
			'Here you will find functions that can be used to customize the WPGraphQL Schema. Learn how to register GraphQL "fields", "types", and more.',
		linkText: 'Functions',
		linkUrl: 'https://www.wpgraphql.com/functions',
	},
	{
		title: 'Blog',
		description:
			'Keep up to date with the latest news and updates from the WPGraphQL team.',
		linkText: 'Blog',
		linkUrl: 'https://www.wpgraphql.com/Blog',
	},
	{
		title: 'Extensions',
		description:
			'Browse the list of extensions that are available to extend WPGraphQL to work with other popular WordPress plugins.',
		linkText: 'View Extensions',
		linkUrl: 'https://www.wpgraphql.com/Extensions',
	},
	{
		title: 'Join us in Discord',
		description:
			'Join the WPGraphQL Community in Discord where you can ask questions, show off projects and help other WPGraphQL users. Join us today!',
		linkText: 'Join us in Discord',
		linkUrl: 'https://discord.gg/AGVBqqyaUY',
	},
];

export const HelpPanel = () => {
	return (
		<div className="wpgraphql-ide-help-panel">
			<div className="graphiql-doc-explorer-title">Help</div>
			{helpCards.map((card, i) => {
				return <HelpCard key={i} card={card} />;
			})}
		</div>
	);
};

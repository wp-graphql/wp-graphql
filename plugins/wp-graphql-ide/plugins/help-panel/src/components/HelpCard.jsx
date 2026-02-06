const HelpCard = ({ card }) => {
	const { title, description, linkUrl, linkText } = card;
	return (
		<div
			className="wpgraphql-ide-help-card"
			style={{
				backgroundColor: `hsla(var(--color-neutral), var(--alpha-background-light))`,
				borderRadius: `calc(var(--border-radius-12) + var(--px-8))`,
				padding: `var(--px-20)`,
				marginTop: `var(--px-20)`,
			}}
		>
			<div
				className="wpgraphql-ide-help-card-title"
				style={{
					color: `hsla(var(--color-neutral), 1)`,
					fontFamily: `var(--font-family)`,
					fontSize: `var(--font-size-h4)`,
				}}
			>
				{title}
			</div>
			<p className="wpgraphql-ide-help-card-description">{description}</p>
			<a
				className="wpgraphql-ide-help-card-link"
				href={linkUrl}
				target="_blank"
				rel="noreferrer"
			>
				{linkText}
			</a>
		</div>
	);
};

export default HelpCard;

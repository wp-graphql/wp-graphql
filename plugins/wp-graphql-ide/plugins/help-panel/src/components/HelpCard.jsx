const HelpCard = ({ card }) => {
	const { title, description, linkUrl, linkText } = card;
	return (
		<div className="wpgraphql-ide-help-card">
			<p className="wpgraphql-ide-help-card-desc">{description}</p>
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

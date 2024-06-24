import { render } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Card, CardBody, CardHeader } from '@wordpress/components';

/**
 * Extensions component to display the list of WPGraphQL extensions
 *
 * @return {JSX.Element} The Extensions component
 */
const Extensions = () => {
	const { extensions } = window.wpgraphqlExtensions;

	return (
		<div className="wpgraphql-extensions">
			{ extensions.map( ( extension ) => (
				<Card key={ extension.id }>
					<CardHeader>
						<h3>{ extension.name }</h3>
					</CardHeader>
					<CardBody>
						<p>{ extension.description }</p>
						<Button variant='primary'>{ __( 'Install', 'wp-graphql' ) }</Button>
					</CardBody>
				</Card>
			) ) }
		</div>
	);
};

document.addEventListener( 'DOMContentLoaded', () => {
	const container = document.getElementById( 'wpgraphql-extensions' );
	if ( container ) {
		render( <Extensions />, container );
	}
} );

import { render, useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Card, CardBody, CardHeader } from '@wordpress/components';

/**
 * Extensions component to display the list of WPGraphQL extensions
 *
 * @return {JSX.Element} The Extensions component
 */
const Extensions = () => {
	const [ extensions, setExtensions ] = useState( [] );
	const [ isLoading, setIsLoading ] = useState( true );

	useEffect( () => {
		const query = `
			query {
				wpgraphqlExtensions {
					extensions {
						id
						name
						description
					}
				}
			}
		`;

		fetch( wpgraphqlExtensions.graphqlUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': wpgraphqlExtensions.nonce,
			},
			body: JSON.stringify({ query }),
		} )
			.then( ( response ) => response.json() )
			.then( ( data ) => {
				setExtensions( data.data.wpgraphqlExtensions.extensions );
				setIsLoading( false );
			} );
	}, [] );

	if ( isLoading ) {
		return <p>{ __( 'Loading...', 'wp-graphql' ) }</p>;
	}

	return (
		<div className="wpgraphql-extensions">
			{ extensions.map( ( extension ) => (
				<Card key={ extension.id }>
					<CardHeader>
						<h3>{ extension.name }</h3>
					</CardHeader>
					<CardBody>
						<p>{ extension.description }</p>
						<Button isPrimary>{ __( 'Install', 'wp-graphql' ) }</Button>
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

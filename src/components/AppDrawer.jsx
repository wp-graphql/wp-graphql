import React from 'react';
import { Drawer as VaulDrawer } from 'vaul';
import { useDispatch, useSelect } from '@wordpress/data';

export function AppDrawer( { children, buttonLabel } ) {
	const isDrawerOpen = useSelect( ( select ) => {
		return select( 'wpgraphql-ide/app' ).isDrawerOpen();
	} );

	const { setDrawerOpen } = useDispatch( 'wpgraphql-ide/app' );

	return (
		<div className="AppDrawerRoot">
			<VaulDrawer.Root
				dismissible={ false }
				closeThreshold={ 1 }
				shouldScaleBackground={ false }
				open={ isDrawerOpen }
				onOpenChange={ setDrawerOpen }
			>
				<VaulDrawer.Trigger className="AppDrawerButton">
					<span className="ab-icon"></span>
					{ buttonLabel }
				</VaulDrawer.Trigger>
				<VaulDrawer.Portal>
					<VaulDrawer.Content>{ children }</VaulDrawer.Content>
					<VaulDrawer.Overlay />
				</VaulDrawer.Portal>
			</VaulDrawer.Root>
		</div>
	);
}

import React from 'react';
import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';
import { Logo } from '../../../src/components/Logo';

describe( 'Logo Component', () => {
	test( 'renders an SVG element', () => {
		render( <Logo data-testid="007" /> );
		const svgElement = screen.getByTestId( '007' );
		expect( svgElement ).toBeInTheDocument();
		expect( svgElement ).toHaveAttribute( 'viewBox', '0 0 400 400' );
	} );
} );

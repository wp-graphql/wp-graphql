<?php

namespace WPGraphQL\Experimental;

use WPGraphQL\Experimental\ExperimentRegistry;

/**
 * Class Extensions
 *
 * Handles adding active experiments to GraphQL response extensions.
 *
 * @package WPGraphQL\Experimental
 * @since 2.3.8
 */
class Extensions {

	/**
	 * Initialize the extensions functionality.
	 */
	public function init(): void {
		add_filter(
			'graphql_request_results',
			[
				$this,
				'add_experiments_to_response_extensions',
			],
			10,
			5
		);
	}

	/**
	 * Add active experiments to GraphQL response extensions.
	 *
	 * @param mixed|array<string,mixed>|object $response       The response of the GraphQL Request being executed
	 * @param \WPGraphQL\WPSchema              $schema         The WPGraphQL Schema
	 * @param string|null                      $operation_name The operation name being executed
	 * @param string|null                      $request        The GraphQL Request being made
	 * @param array<string,mixed>|null         $variables      The variables sent with the request
	 *
	 * @return mixed|array<string,mixed>|object
	 */
	public function add_experiments_to_response_extensions( $response, $schema, ?string $operation_name, ?string $request, ?array $variables ) {
		// Only show experiments in extensions when GraphQL debugging is enabled
		$should = \WPGraphQL::debug();

		/**
		 * Filter whether experiments should be shown in GraphQL response extensions.
		 *
		 * @param bool                              $should         Whether experiments should be displayed in the Extensions output. Defaults to true if GraphQL debugging is enabled.
		 * @param mixed|array<string,mixed>|object $response       The response of the WPGraphQL Request being executed
		 * @param \WPGraphQL\WPSchema               $schema         The WPGraphQL Schema
		 * @param string|null                       $operation_name The operation name being executed
		 * @param string|null                       $request        The GraphQL Request being made
		 * @param array<string,mixed>|null          $variables      The variables sent with the request
		 */
		$should_show_experiments_in_extensions = apply_filters(
			'graphql_should_show_experiments_in_extensions',
			$should,
			$response,
			$schema,
			$operation_name,
			$request,
			$variables
		);

		// If experiments extensions are disabled, return unmodified response
		if ( false === $should_show_experiments_in_extensions ) {
			return $response;
		}

		// Get active experiments
		$active_experiments = ExperimentRegistry::get_active_experiments();

		// If no experiments are active, don't add anything to extensions
		if ( empty( $active_experiments ) ) {
			return $response;
		}

		// Extract just the experiment slugs for the extensions
		$experiment_slugs = array_keys( $active_experiments );

		// Add experiments to response extensions
		if ( ! empty( $response ) ) {
			if ( is_array( $response ) ) {
				$response['extensions']['experiments'] = $experiment_slugs;
			} elseif ( is_object( $response ) ) {
				// @phpstan-ignore-next-line
				$response->extensions['experiments'] = $experiment_slugs;
			}
		}

		return $response;
	}
}

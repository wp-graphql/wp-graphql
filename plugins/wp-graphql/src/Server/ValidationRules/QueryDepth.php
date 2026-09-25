<?php

namespace WPGraphQL\Server\ValidationRules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FragmentSpreadNode;
use GraphQL\Language\AST\InlineFragmentNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Validator\Rules\QuerySecurityRule;
use WPGraphQL\Utils\Utils;

/**
 * Class QueryDepth
 *
 * @package WPGraphQL\Server\ValidationRules
 */
class QueryDepth extends QuerySecurityRule {

	/**
	 * The max query depth used when none has been saved.
	 */
	public const DEFAULT_MAX_QUERY_DEPTH = 15;

	/**
	 * The max query depth allowed.
	 */
	private int $maxQueryDepth;

	/**
	 * The max query depth for this request after filtering. 0 means no limit.
	 */
	private ?int $effectiveMaxQueryDepth = null;

	/**
	 * QueryDepth constructor.
	 */
	public function __construct() {
		$max_query_depth = absint( get_graphql_setting( 'query_depth_max', self::DEFAULT_MAX_QUERY_DEPTH ) );
		$this->setMaxQueryDepth( $max_query_depth > 0 ? $max_query_depth : self::DEFAULT_MAX_QUERY_DEPTH );
	}

	/**
	 * {@inheritDoc}
	 */
	public function getVisitor( \GraphQL\Validator\QueryValidationContext $context ): array {
		return $this->invokeIfNeeded(
			$context,
			[
				NodeKind::OPERATION_DEFINITION => [
					'leave' => function ( Node $node ) use ( $context ): void {
						if ( ! $node instanceof OperationDefinitionNode ) {
							return;
						}

						// Introspection has a fixed shape defined by the GraphQL spec, and access to it is
						// controlled separately, so operations that only ask for introspection aren't limited.
						if ( Utils::is_introspection_only_operation( $node, $this->getFragments() ) ) {
							return;
						}

						$maxDepth        = $this->fieldDepth( $node );
						$allowedMaxDepth = $this->get_effective_max_query_depth();

						if ( $maxDepth <= $allowedMaxDepth ) {
							return;
						}

						$context->reportError(
							new Error( $this->errorMessage( $allowedMaxDepth, $maxDepth ) )
						);
					},
				],
			]
		);
	}

	/**
	 * Determine field depth
	 *
	 * @param \GraphQL\Language\AST\Node $node The node being analyzed
	 * @param int                        $depth The depth of the field. Default is 0
	 * @param int                        $maxDepth The max depth allowed. Default is 0
	 */
	private function fieldDepth( Node $node, int $depth = 0, int $maxDepth = 0 ): int {
		if ( isset( $node->selectionSet ) && $node->selectionSet instanceof SelectionSetNode ) {
			foreach ( $node->selectionSet->selections as $childNode ) {
				$maxDepth = $this->nodeDepth( $childNode, $depth, $maxDepth );
			}
		}

		return $maxDepth;
	}

	/**
	 * Determine node depth
	 *
	 * @param \GraphQL\Language\AST\Node $node The node being analyzed in the operation
	 * @param int                        $depth The depth of the operation
	 * @param int                        $maxDepth The Max Depth of the operation
	 */
	private function nodeDepth( Node $node, int $depth, int $maxDepth ): int {
		switch ( true ) {
			case $node instanceof FieldNode:
				// node has children?
				if ( isset( $node->selectionSet ) ) {
					// update maxDepth if needed
					if ( $depth > $maxDepth ) {
						$maxDepth = $depth;
					}
					$maxDepth = $this->fieldDepth( $node, $depth + 1, $maxDepth );
				}
				break;

			case $node instanceof InlineFragmentNode:
				// node has children?
				$maxDepth = $this->fieldDepth( $node, $depth, $maxDepth );
				break;

			case $node instanceof FragmentSpreadNode:
				$fragment = $this->getFragment( $node );

				if ( null !== $fragment ) {
					$maxDepth = $this->fieldDepth( $fragment, $depth, $maxDepth );
				}
				break;
		}

		return $maxDepth;
	}

	/**
	 * Return the maxQueryDepth allowed
	 *
	 * @return int
	 */
	public function getMaxQueryDepth() {
		return $this->maxQueryDepth;
	}

	/**
	 * Set max query depth. If equal to 0 no check is done. Must be greater or equal to 0.
	 *
	 * @param int $maxQueryDepth The max query depth to allow for GraphQL operations
	 *
	 * @return void
	 */
	public function setMaxQueryDepth( int $maxQueryDepth ) {
		$this->checkIfGreaterOrEqualToZero( 'maxQueryDepth', $maxQueryDepth );

		$this->maxQueryDepth          = $maxQueryDepth;
		$this->effectiveMaxQueryDepth = null;
	}

	/**
	 * Return the max query depth error message
	 *
	 * @param int $max The max number of levels to allow in GraphQL operation
	 * @param int $count The number of levels in the current operation
	 *
	 * @return string
	 */
	public function errorMessage( $max, $count ) {
		return sprintf( 'The server administrator has limited the max query depth to %d, but the requested query has %d levels.', $max, $count );
	}

	/**
	 * Returns the max query depth for the current request.
	 *
	 * Starts from the saved settings (the Max Depth setting when Query Depth Limiting is
	 * enabled, 0 when it's not) and applies the `graphql_query_depth_max` filter.
	 *
	 * @return int The max query depth. 0 means no limit.
	 */
	protected function get_effective_max_query_depth(): int {
		if ( null !== $this->effectiveMaxQueryDepth ) {
			return $this->effectiveMaxQueryDepth;
		}

		$enabled   = get_graphql_setting( 'query_depth_enabled', 'off' );
		$max_depth = 'on' === $enabled ? $this->getMaxQueryDepth() : 0;

		/**
		 * Filters the max query depth allowed for the current request.
		 *
		 * The value passed in comes from the settings: the Max Depth setting when Query Depth
		 * Limiting is enabled, or 0 when it's disabled. Return 0 to allow any depth, or a positive
		 * integer to limit it, which also applies the limit when the setting is disabled. A
		 * non-numeric return value is ignored and the value from the settings is used.
		 * Operations that only query introspection (`__schema`, `__type`) are never limited.
		 *
		 * Use this to give trusted users a different limit than everyone else. Base that decision on a
		 * capability (for example `current_user_can( 'manage_options' )`), not only on whether the
		 * user is logged in, since sites with open registration let anyone create an account.
		 *
		 * @param int $max_depth The max query depth. 0 means no limit.
		 *
		 * @hookGroup request-lifecycle
		 * @since x-release-please-version
		 */
		$filtered_max_depth = apply_filters( 'graphql_query_depth_max', $max_depth );

		// A non-numeric value keeps the configured limit. Zero or a negative number means no limit.
		$this->effectiveMaxQueryDepth = is_numeric( $filtered_max_depth ) ? max( 0, (int) $filtered_max_depth ) : $max_depth;

		return $this->effectiveMaxQueryDepth;
	}

	/**
	 * Determine whether the rule should be enabled
	 */
	protected function isEnabled(): bool {
		return 1 <= $this->get_effective_max_query_depth();
	}
}

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

/**
 * Class QueryDepth
 *
 * @package WPGraphQL\Server\ValidationRules
 */
class QueryDepth extends QuerySecurityRule {

	/**
	 * The max query depth allowed.
	 */
	private int $maxQueryDepth;

	/**
	 * QueryDepth constructor.
	 */
	public function __construct() {
		$max_query_depth = get_graphql_setting( 'query_depth_max', 10 );
		$max_query_depth = absint( $max_query_depth ) ?? 10;
		$this->setMaxQueryDepth( $max_query_depth );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param \GraphQL\Validator\QueryValidationContext $context
	 *
	 * @return array<string,array<string,callable(\GraphQL\Language\AST\Node): (\GraphQL\Language\VisitorOperation|void|false|null)>|(callable(\GraphQL\Language\AST\Node): (\GraphQL\Language\VisitorOperation|void|false|null))>
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

						$maxDepth = $this->fieldDepth( $node );

						if ( $maxDepth <= $this->getMaxQueryDepth() ) {
							return;
						}

						$context->reportError(
							new Error( $this->errorMessage( $this->getMaxQueryDepth(), $maxDepth ) )
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

		$this->maxQueryDepth = $maxQueryDepth;
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
	 * Determine whether the rule should be enabled
	 */
	protected function isEnabled(): bool {
		$is_enabled = false;

		$enabled = get_graphql_setting( 'query_depth_enabled', 'off' );

		if ( 'on' === $enabled && absint( $this->getMaxQueryDepth() ) && 1 <= $this->getMaxQueryDepth() ) {
			$is_enabled = true;
		}

		return $is_enabled;
	}
}

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
use GraphQL\Validator\ValidationContext;
use function sprintf;

/**
 * Class QueryDepth
 *
 * @package WPGraphQL\Server\ValidationRules
 */
class QueryDepth extends QuerySecurityRule {

	/**
	 * @var int
	 */
	private $maxQueryDepth;

	/**
	 * QueryDepth constructor.
	 */
	public function __construct() {
		$max_query_depth = get_graphql_setting( 'query_depth_max', 10 );
		$max_query_depth = absint( $max_query_depth ) ?? 10;
		$this->setMaxQueryDepth( $max_query_depth );
	}

	/**
	 * @param \GraphQL\Validator\ValidationContext $context
	 *
	 * @return callable[]|mixed[]
	 */
	public function getVisitor( ValidationContext $context ) {
		return $this->invokeIfNeeded(
			$context,
			// @phpstan-ignore-next-line
			[
				NodeKind::OPERATION_DEFINITION => [
					'leave' => function ( OperationDefinitionNode $operationDefinition ) use ( $context ): void {
						$maxDepth = $this->fieldDepth( $operationDefinition );

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
	 * @param mixed $node The node being analyzed
	 * @param int $depth The depth of the field
	 * @param int $maxDepth The max depth allowed
	 *
	 * @return int|mixed
	 */
	private function fieldDepth( $node, $depth = 0, $maxDepth = 0 ) {
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
	 * @param int  $depth The depth of the operation
	 * @param int  $maxDepth The Max Depth of the operation
	 *
	 * @return int|mixed
	 */
	private function nodeDepth( Node $node, $depth = 0, $maxDepth = 0 ) {
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

		$this->maxQueryDepth = (int) $maxQueryDepth;
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
	 *
	 * @return bool
	 */
	protected function isEnabled() {
		$is_enabled = false;

		$enabled = get_graphql_setting( 'query_depth_enabled', 'off' );

		if ( 'on' === $enabled && absint( $this->getMaxQueryDepth() ) && 1 <= $this->getMaxQueryDepth() ) {
			$is_enabled = true;
		}

		return $is_enabled;
	}
}

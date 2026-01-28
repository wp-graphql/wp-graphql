<?php

namespace WPGraphQL\SmartCache\ValidationRules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Validator\Rules\ValidationRule;
use GraphQL\Validator\QueryValidationContext;

use WPGraphQL\SmartCache\Document;
use WPGraphQL\SmartCache\Document\Grant;
use WPGraphQL\SmartCache\Utils;

/**
 * Class AllowOrDenyQuery
 *
 * @package WPGraphQL\SmartCache\Rules
 */
class AllowDenyQueryDocument extends ValidationRule {

	/**
	 * @var string
	 */
	private $access_setting;

	/**
	 * AllowDenyQueryDocument constructor.
	 *
	 * @param string $setting
	 * @return void
	 */
	public function __construct( $setting ) {
		$this->access_setting = $setting;
	}

	/**
	 * Returns structure suitable for GraphQL\Language\Visitor
	 *
	 * @see \GraphQL\Language\Visitor
	 *
	 * @return array
	 */
	public function getVisitor( QueryValidationContext $context ): array {
		return [
			NodeKind::DOCUMENT => function ( DocumentNode $node ) use ( $context ) {
				// We are here because the global graphql setting is not public. Meaning allow or deny
				// certain queries.

				// Check if the query document is persisted
				// Get post using the normalized hash of the query string
				$hash = Utils::generateHash( $context->getDocument() );

				// Look up the persisted query
				$post = Utils::getPostByTermName( $hash, Document::TYPE_NAME, Document::ALIAS_TAXONOMY_NAME );

				// If set to allow only specific queries, must be explicitely allowed.
				// If set to deny some queries, only deny if persisted and explicitely denied.
				if ( Grant::GLOBAL_DENIED === $this->access_setting ) {
					// If this query is not persisted do not block it.
					if ( ! $post ) {
						return;
					}

					// When the allow/deny setting denies some queries, see if this query is denied
					if ( Grant::DENY === Grant::getQueryGrantSetting( $post->ID ) ) {
						$context->reportError(
							new Error(
								self::deniedDocumentMessage(),
								$node
							)
						);
					}
				} elseif ( Grant::GLOBAL_ALLOWED === $this->access_setting ) {
					// When the allow/deny setting only allows certain queries, verify this query is allowed
					// If this query is not persisted do not allow.
					if ( ! $post ) {
						$context->reportError(
							new Error(
								self::notFoundDocumentMessage(),
								$node
							)
						);
					} elseif ( Grant::ALLOW !== Grant::getQueryGrantSetting( $post->ID ) ) {
						$context->reportError(
							new Error(
								self::deniedDocumentMessage(),
								$node
							)
						);
					}
				}
			},
		];
	}

	/**
	 * @return string
	 */
	public static function deniedDocumentMessage() {
		return __( 'This query document has been blocked.', 'wp-graphql-smart-cache' );
	}

	/**
	 * @return string
	 */
	public static function notFoundDocumentMessage() {
		return __( 'Not Found. Only pre-defined queries are allowed.', 'wp-graphql-smart-cache' );
	}

}

<?php
namespace WPGraphQL\Data\Loader;

use WPGraphQL\Model\User;

/**
 * Class UserLoader
 *
 * @package WPGraphQL\Data\Loader
 */
class UserLoader extends AbstractDataLoader {

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed|\WP_User $entry The User object
	 *
	 * @return ?\WPGraphQL\Model\User
	 * @throws \Exception
	 */
	protected function get_model( $entry, $key ) {
		if ( $entry instanceof \WP_User ) {
			return new User( $entry );
		} else {
			return null;
		}
	}

	/**
	 * Normalize a loader key to a WordPress user database ID.
	 *
	 * Only non-empty digit-only strings and positive integers are accepted so values
	 * such as "0) OR …" cannot pass `absint`-based checks elsewhere and reach SQL.
	 *
	 * @param mixed $key Loader key (typically an integer or numeric string).
	 *
	 * @return int|null Positive user ID, or null if the key is not a valid ID.
	 */
	private function parse_user_database_id( $key ): ?int {
		if ( is_int( $key ) ) {
			return $key > 0 ? $key : null;
		}

		if ( is_string( $key ) ) {
			if ( '' === $key || ! ctype_digit( $key ) ) {
				return null;
			}
			$id = absint( $key );

			return $id > 0 ? $id : null;
		}

		return null;
	}

	/**
	 * The data loader always returns a user object if it exists, but we need to
	 * separately determine whether the user should be considered private. The
	 * WordPress frontend does not expose authors without published posts, so our
	 * privacy model follows that same convention.
	 *
	 * Example return format for input "[ 1, 2 ]":
	 *
	 * [
	 *   2 => true,  // User 2 is public (has published posts)
	 * ]
	 *
	 * In this example, user 1 is not public (has no published posts) and is
	 * omitted from the returned array.
	 *
	 * @param int[] $keys Array of author IDs (int).
	 *
	 * @return array<int,bool> Associative array of author IDs (int) to boolean.
	 */
	public function get_public_users( array $keys ) {
		$sanitized_keys = [];
		foreach ( $keys as $key ) {
			$id = $this->parse_user_database_id( $key );
			if ( null !== $id ) {
				$sanitized_keys[] = $id;
			}
		}
		$sanitized_keys = array_values( array_unique( $sanitized_keys ) );

		if ( empty( $sanitized_keys ) ) {
			return [];
		}

		$keys = $sanitized_keys;

		// Get public post types that are set to show in GraphQL
		// as public users are determined by whether they've published
		// content in one of these post types
		$post_types = \WPGraphQL::get_allowed_post_types(
			'names',
			[
				'public' => true,
			]
		);

		/**
		 * Exclude revisions and attachments, since neither ever receive the
		 * "publish" post status.
		 */
		unset( $post_types['revision'], $post_types['attachment'] );

		/**
		 * Only retrieve public posts by the provided author IDs. Also,
		 * get_posts_by_author_sql only accepts a single author ID, so we'll need to
		 * add our own IN statement.
		 */
		$author_id   = null;
		$public_only = true;

		$where = get_posts_by_author_sql( $post_types, true, $author_id, $public_only );
		$ids   = implode( ', ', $keys );

		global $wpdb;

		$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT DISTINCT $wpdb->users.ID FROM $wpdb->posts INNER JOIN $wpdb->users ON post_author = $wpdb->users.ID $where AND post_author IN ( %1\$s ) ORDER BY FIELD( $wpdb->users.ID, %2\$s)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder,WordPressVIPMinimum.Variables.RestrictedVariables.user_meta__wpdb__users
				$ids,
				$ids
			)
		);

		/**
		 * Empty results or error.
		 */
		if ( ! is_array( $results ) ) {
			return [];
		}

		/**
		 * Reduce to an associative array that can be easily consumed.
		 */
		return array_reduce(
			$results,
			static function ( $carry, $result ) {
				$carry[ (int) $result->ID ] = true;
				return $carry;
			},
			[]
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int[] $keys
	 *
	 * @return array<int,\WP_User|null>
	 */
	public function loadKeys( array $keys ) {
		if ( empty( $keys ) ) {
			return $keys;
		}

		$key_to_id = [];
		foreach ( $keys as $key ) {
			$key_to_id[ $key ] = $this->parse_user_database_id( $key );
		}

		$valid_ids = array_values(
			array_unique(
				array_filter(
					array_values( $key_to_id ),
					static function ( $id ) {
						return null !== $id;
					}
				)
			)
		);

		if ( empty( $valid_ids ) ) {
			return array_fill_keys( $keys, null );
		}

		/**
		 * Prepare the args for the query. We're provided a specific
		 * set of IDs, so we want to query as efficiently as possible with
		 * as little overhead as possible. We don't want to return post counts,
		 * we don't want to include sticky posts, and we want to limit the query
		 * to the count of the keys provided. We don't care about the order since we
		 * will reorder them ourselves to match the order of the provided keys.
		 */
		$args = [
			'include'     => $valid_ids,
			'number'      => count( $valid_ids ),
			'count_total' => false,
			'fields'      => 'all_with_meta',
		];

		/**
		 * Query for the users and get the results
		 */
		$query = new \WP_User_Query( $args );
		$query->get_results();

		/**
		 * Determine which of the users are public (have published posts).
		 */
		$public_users = $this->get_public_users( $valid_ids );

		/**
		 * Loop over the keys and reduce to an associative array, providing the
		 * WP_User instance (if found) or null. This ensures that the returned array
		 * has the same keys that were provided and in the same order.
		 */
		return array_reduce(
			$keys,
			static function ( $carry, $key ) use ( $public_users, $key_to_id ) {
				$user_id = $key_to_id[ $key ] ?? null;

				if ( null === $user_id ) {
					$carry[ $key ] = null;
					return $carry;
				}

				$user = get_user_by( 'id', $user_id ); // Cached via previous WP_User_Query.

				if ( $user instanceof \WP_User ) {
					/**
					 * Set a property on the user that can be accessed by the User model.
					 */
					// @phpstan-ignore-next-line
					$user->is_private = ! isset( $public_users[ $user_id ] );

					$carry[ $key ] = $user;
				} else {
					$carry[ $key ] = null;
				}

				return $carry;
			},
			[]
		);
	}
}

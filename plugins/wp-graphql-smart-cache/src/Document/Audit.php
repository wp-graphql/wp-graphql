<?php
/**
 * Audit and cleanup of automatically registered query documents.
 *
 * @package Wp_Graphql_Smart_Cache
 */

namespace WPGraphQL\SmartCache\Document;

use WPGraphQL\SmartCache\Document;

/**
 * Surfaces the documents the automatic persisted query path stored, flags alias
 * names on them that were not derived from the document hash, and lets an
 * administrator remove them from the settings screen, the document list, or
 * WP-CLI. Removing automatically registered documents is safe: a client that
 * still uses one re-sends the full query on its next request and the document
 * is registered again under its verified hash.
 */
class Audit {

	const PURGE_ACTION     = 'wpgraphql_smart_cache_purge_automatic_documents';
	const NONCE_ACTION     = 'wpgraphql_smart_cache_document_audit';
	const VIEW_QUERY_VAR   = 'graphql_document_audit';
	const VIEW_AUTOMATIC   = 'automatic';
	const VIEW_UNVERIFIED  = 'unverified';
	const NOTICE_DISMISSED = 'wpgraphql_smart_cache_document_audit_notice_dismissed';
	const COUNTS_TRANSIENT = 'wpgraphql_smart_cache_document_audit_counts';
	const RESULT_QUERY_VAR = 'graphql_document_audit_purged';
	const BATCH_SIZE       = 100;
	const MAX_SECONDS      = 20;

	/**
	 * @return void
	 */
	public function init() {
		add_action( 'graphql_register_settings', [ $this, 'register_settings_panel' ] );
		add_action( 'admin_post_' . self::PURGE_ACTION, [ $this, 'handle_purge_request' ] );
		add_action( 'admin_notices', [ $this, 'render_admin_notice' ] );
		add_action( 'admin_init', [ $this, 'handle_notice_dismissal' ] );

		add_filter( sprintf( 'views_edit-%s', Document::TYPE_NAME ), [ $this, 'add_list_table_views' ] );
		add_filter( 'posts_where', [ $this, 'filter_list_table_query' ], 10, 2 );
		add_filter( 'post_column_taxonomy_links', [ $this, 'flag_unverified_alias_links' ], 10, 3 );

		add_action( 'save_post_' . Document::TYPE_NAME, [ $this, 'clear_counts_cache' ] );
		add_action( 'deleted_post', [ $this, 'clear_counts_cache' ] );
		add_action( 'set_object_terms', [ $this, 'clear_counts_cache' ] );

		if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( '\WP_CLI' ) ) {
			\WP_CLI::add_command( 'graphql smart-cache documents', DocumentsCommand::class );
		}
	}

	/**
	 * Whether an alias name is a SHA-256 hex digest, i.e. derived from a document.
	 *
	 * @param string $name The alias term name.
	 * @return bool
	 */
	public static function is_hash_alias( $name ) {
		return 1 === preg_match( '/^[0-9a-f]{64}$/', $name );
	}

	/**
	 * Whether a document was registered by the automatic persisted query path.
	 *
	 * Documents registered after the source marker was introduced carry it in
	 * meta. Older ones are recognised by having no author, which is what an
	 * unauthenticated registration produced; documents an authorized user created
	 * through the editor or the graphqlDocument mutations always carry an author.
	 *
	 * @param int $post_id The document post ID.
	 * @return bool
	 */
	public static function is_automatic( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || Document::TYPE_NAME !== $post->post_type ) {
			return false;
		}

		if ( Document::SOURCE_AUTOMATIC === get_post_meta( $post->ID, Document::SOURCE_META_KEY, true ) ) {
			return true;
		}

		return 0 === (int) $post->post_author;
	}

	/**
	 * Whether an administrator has curated a document by granting or denying it,
	 * or placing it in a group. Curated documents are kept by the purge.
	 *
	 * @param int $post_id The document post ID.
	 * @return bool
	 */
	public static function is_curated( $post_id ) {
		foreach ( [ Grant::TAXONOMY_NAME, Group::TAXONOMY_NAME ] as $taxonomy ) {
			$terms = get_the_terms( $post_id, $taxonomy );
			if ( is_array( $terms ) && ! empty( $terms ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Alias names on a document that were not derived from a document hash.
	 *
	 * @param int $post_id The document post ID.
	 * @return string[]
	 */
	public static function get_unverified_aliases( $post_id ) {
		$terms = get_the_terms( $post_id, Document::ALIAS_TAXONOMY_NAME );
		if ( ! is_array( $terms ) ) {
			return [];
		}
		$names = [];
		foreach ( $terms as $term ) {
			if ( ! self::is_hash_alias( $term->name ) ) {
				$names[] = $term->name;
			}
		}
		return $names;
	}

	/**
	 * SQL fragment selecting automatically registered documents.
	 *
	 * @return string
	 */
	private static function automatic_where_sql() {
		global $wpdb;
		return $wpdb->prepare(
			"( {$wpdb->posts}.post_author = 0 OR {$wpdb->posts}.ID IN ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s ) )",
			Document::SOURCE_META_KEY,
			Document::SOURCE_AUTOMATIC
		);
	}

	/**
	 * SQL fragment selecting documents carrying an alias not derived from a hash.
	 *
	 * @return string
	 */
	private static function unverified_where_sql() {
		global $wpdb;
		return $wpdb->prepare(
			"{$wpdb->posts}.ID IN ( SELECT tr.object_id FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id WHERE tt.taxonomy = %s AND t.name NOT REGEXP '^[0-9a-f]{64}$' )",
			Document::ALIAS_TAXONOMY_NAME
		);
	}

	/**
	 * IDs of automatically registered documents, optionally only those carrying
	 * an alias not derived from a hash.
	 *
	 * @param bool $unverified_only Limit to documents with an unverified alias.
	 * @param int  $limit           Maximum number of IDs to return; 0 for all.
	 * @return int[]
	 */
	public static function get_automatic_document_ids( $unverified_only = false, $limit = 0 ) {
		global $wpdb;

		$where = self::automatic_where_sql();
		if ( $unverified_only ) {
			$where .= ' AND ' . self::unverified_where_sql();
		}

		$sql = $wpdb->prepare(
			"SELECT {$wpdb->posts}.ID FROM {$wpdb->posts} WHERE {$wpdb->posts}.post_type = %s AND {$wpdb->posts}.post_status NOT IN ( 'trash', 'auto-draft' )",
			Document::TYPE_NAME
		) . ' AND ' . $where . " ORDER BY {$wpdb->posts}.ID ASC";

		if ( $limit > 0 ) {
			$sql .= $wpdb->prepare( ' LIMIT %d', $limit );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- fragments are prepared above; audit query over a private post type with no caching layer.
		$ids = $wpdb->get_col( $sql );

		return array_map( 'intval', $ids );
	}

	/**
	 * Counts shown on the settings panel and in the notice. Cached briefly since
	 * the notice can render on any admin screen.
	 *
	 * @return array{automatic:int, unverified:int}
	 */
	public static function get_counts() {
		$cached = get_transient( self::COUNTS_TRANSIENT );
		if ( is_array( $cached ) && isset( $cached['automatic'], $cached['unverified'] ) ) {
			return $cached;
		}

		$counts = [
			'automatic'  => count( self::get_automatic_document_ids( false ) ),
			'unverified' => count( self::get_automatic_document_ids( true ) ),
		];
		set_transient( self::COUNTS_TRANSIENT, $counts, 5 * MINUTE_IN_SECONDS );

		return $counts;
	}

	/**
	 * @return void
	 */
	public function clear_counts_cache() {
		delete_transient( self::COUNTS_TRANSIENT );
	}

	/**
	 * Delete automatically registered documents.
	 *
	 * @param bool $include_curated Also delete documents an administrator granted, denied, or grouped.
	 * @param int  $batch_size      Documents to consider per pass.
	 * @param int  $max_seconds     Stop after this many seconds and report what remains.
	 * @return array{deleted:int, skipped:int, remaining:int}
	 */
	public static function purge( $include_curated = false, $batch_size = self::BATCH_SIZE, $max_seconds = self::MAX_SECONDS ) {
		$started = time();
		$deleted = 0;
		$skipped = [];

		while ( true ) {
			$ids = array_values( array_diff( self::get_automatic_document_ids( false, $batch_size + count( $skipped ) ), $skipped ) );
			if ( empty( $ids ) ) {
				break;
			}

			foreach ( $ids as $id ) {
				if ( ! $include_curated && self::is_curated( $id ) ) {
					$skipped[] = $id;
					continue;
				}
				if ( wp_delete_post( $id, true ) ) {
					++$deleted;
				} else {
					$skipped[] = $id;
				}
			}

			if ( ( time() - $started ) >= $max_seconds ) {
				break;
			}
		}

		delete_transient( self::COUNTS_TRANSIENT );

		$remaining = count( array_diff( self::get_automatic_document_ids( false ), $skipped ) );

		return [
			'deleted'   => $deleted,
			'skipped'   => count( $skipped ),
			'remaining' => $remaining,
		];
	}

	/**
	 * URL of the document list filtered to one of the audit views.
	 *
	 * @param string $view One of VIEW_AUTOMATIC or VIEW_UNVERIFIED.
	 * @return string
	 */
	public static function get_list_url( $view ) {
		return add_query_arg(
			[
				'post_type'          => Document::TYPE_NAME,
				self::VIEW_QUERY_VAR => $view,
			],
			admin_url( 'edit.php' )
		);
	}

	/**
	 * Nonce-protected URL that starts (or continues) a purge.
	 *
	 * @param bool $include_curated Also delete curated documents.
	 * @return string
	 */
	public static function get_purge_url( $include_curated = false ) {
		$args = [ 'action' => self::PURGE_ACTION ];
		if ( $include_curated ) {
			$args['include_curated'] = '1';
		}
		return wp_nonce_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ), self::NONCE_ACTION );
	}

	/**
	 * URL of the Saved Queries tab on the GraphQL settings screen.
	 *
	 * @return string
	 */
	public static function get_settings_url() {
		return admin_url( 'admin.php?page=graphql-settings#graphql_persisted_queries_section' );
	}

	/**
	 * Register the audit panel on the Saved Queries settings tab.
	 *
	 * @return void
	 */
	public function register_settings_panel() {
		register_graphql_settings_field(
			'graphql_persisted_queries_section',
			[
				'name'     => 'document_audit',
				'type'     => 'custom',
				'callback' => [ $this, 'render_settings_panel' ],
			]
		);
	}

	/**
	 * @param array<string,mixed> $args Field args (unused).
	 * @return void
	 */
	public function render_settings_panel( array $args ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$counts = self::get_counts();
		?>
		<h2><?php esc_html_e( 'Automatically Registered Documents', 'wp-graphql-smart-cache' ); ?></h2>
		<p><?php esc_html_e( 'Documents registered by the automatic persisted query flow are marked as such; ones stored by earlier versions are recognised by having no author. Alias names on those documents that were not derived from the document hash could only have been claimed before this version required it, so review them. Deleting automatically registered documents is safe: a client that still uses one re-sends the full query on its next request and the document is registered again under its verified hash.', 'wp-graphql-smart-cache' ); ?></p>
		<table class="widefat striped" style="max-width: 40em;">
			<tbody>
				<tr>
					<td><?php esc_html_e( 'Automatically registered documents', 'wp-graphql-smart-cache' ); ?></td>
					<td><strong><?php echo esc_html( number_format_i18n( $counts['automatic'] ) ); ?></strong></td>
					<td><a href="<?php echo esc_url( self::get_list_url( self::VIEW_AUTOMATIC ) ); ?>"><?php esc_html_e( 'Review', 'wp-graphql-smart-cache' ); ?></a></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Of those, carrying an unverified alias name', 'wp-graphql-smart-cache' ); ?></td>
					<td><strong><?php echo esc_html( number_format_i18n( $counts['unverified'] ) ); ?></strong></td>
					<td><a href="<?php echo esc_url( self::get_list_url( self::VIEW_UNVERIFIED ) ); ?>"><?php esc_html_e( 'Review', 'wp-graphql-smart-cache' ); ?></a></td>
				</tr>
			</tbody>
		</table>
		<p>
			<a class="button button-secondary" href="<?php echo esc_url( self::get_purge_url() ); ?>" onclick="return window.confirm( <?php echo esc_js( (string) wp_json_encode( __( 'Delete all automatically registered documents? Documents you have granted, denied, or grouped are kept.', 'wp-graphql-smart-cache' ) ) ); ?> );"><?php esc_html_e( 'Delete automatically registered documents', 'wp-graphql-smart-cache' ); ?></a>
		</p>
		<p class="description"><?php esc_html_e( 'Documents you have granted, denied, or placed in a group are kept. The same audit is available from WP-CLI: wp graphql smart-cache documents audit', 'wp-graphql-smart-cache' ); ?></p>
		<?php
	}

	/**
	 * Run the purge from the settings panel link, continuing across requests on
	 * large sites, then return to the settings tab with a summary.
	 *
	 * @return void
	 */
	public function handle_purge_request() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'wp-graphql-smart-cache' ), '', [ 'response' => 403 ] );
		}
		check_admin_referer( self::NONCE_ACTION );

		$include_curated = ! empty( $_GET['include_curated'] );
		$carried         = isset( $_GET['deleted'] ) ? absint( $_GET['deleted'] ) : 0;

		$result = self::purge( $include_curated );
		$total  = $carried + $result['deleted'];

		if ( $result['remaining'] > 0 && $result['deleted'] > 0 ) {
			// More to do: continue in a fresh request rather than risk a timeout.
			$next = add_query_arg( 'deleted', $total, self::get_purge_url( $include_curated ) );
			wp_safe_redirect( $next );
			exit;
		}

		update_option( self::NOTICE_DISMISSED, '1' );
		wp_safe_redirect( add_query_arg( self::RESULT_QUERY_VAR, $total, self::get_settings_url() ) );
		exit;
	}

	/**
	 * Dismiss the audit notice.
	 *
	 * @return void
	 */
	public function handle_notice_dismissal() {
		if ( ! isset( $_GET['wpgraphql_smart_cache_dismiss_document_audit'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( self::NONCE_ACTION );
		update_option( self::NOTICE_DISMISSED, '1' );
		wp_safe_redirect( remove_query_arg( [ 'wpgraphql_smart_cache_dismiss_document_audit', '_wpnonce' ] ) );
		exit;
	}

	/**
	 * Tell administrators about unverified alias names until they act on them.
	 *
	 * @return void
	 */
	public function render_admin_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display of a redirect result.
		if ( isset( $_GET[ self::RESULT_QUERY_VAR ] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$deleted = absint( $_GET[ self::RESULT_QUERY_VAR ] );
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html(
					sprintf(
						// translators: %s: number of documents deleted.
						_n( '%s automatically registered GraphQL document was deleted.', '%s automatically registered GraphQL documents were deleted.', $deleted, 'wp-graphql-smart-cache' ),
						number_format_i18n( $deleted )
					)
				)
			);
			return;
		}

		if ( '1' === get_option( self::NOTICE_DISMISSED ) ) {
			return;
		}

		$counts = self::get_counts();
		if ( $counts['unverified'] < 1 ) {
			return;
		}

		$dismiss_url = wp_nonce_url( add_query_arg( 'wpgraphql_smart_cache_dismiss_document_audit', '1' ), self::NONCE_ACTION );
		?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'WPGraphQL Smart Cache:', 'wp-graphql-smart-cache' ); ?></strong>
				<?php
				echo esc_html(
					sprintf(
						// translators: %s: number of documents.
						_n( '%s automatically registered GraphQL document carries an alias name that was not derived from its hash. Earlier versions let any request claim such an alias, so review these documents and delete the ones you do not recognize.', '%s automatically registered GraphQL documents carry alias names that were not derived from their hash. Earlier versions let any request claim such an alias, so review these documents and delete the ones you do not recognize.', $counts['unverified'], 'wp-graphql-smart-cache' ),
						number_format_i18n( $counts['unverified'] )
					)
				);
				?>
			</p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( self::get_list_url( self::VIEW_UNVERIFIED ) ); ?>"><?php esc_html_e( 'Review documents', 'wp-graphql-smart-cache' ); ?></a>
				<a class="button" href="<?php echo esc_url( self::get_settings_url() ); ?>"><?php esc_html_e( 'Open the audit panel', 'wp-graphql-smart-cache' ); ?></a>
				<a href="<?php echo esc_url( $dismiss_url ); ?>"><?php esc_html_e( 'Dismiss', 'wp-graphql-smart-cache' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Add "Automatic" and "Unverified alias" views to the document list.
	 *
	 * @param array<string,string> $views Existing view links.
	 * @return array<string,string>
	 */
	public function add_list_table_views( $views ) {
		$counts = self::get_counts();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter.
		$current = isset( $_GET[ self::VIEW_QUERY_VAR ] ) ? sanitize_key( $_GET[ self::VIEW_QUERY_VAR ] ) : '';

		$views[ self::VIEW_AUTOMATIC ]  = sprintf(
			'<a href="%s"%s>%s <span class="count">(%s)</span></a>',
			esc_url( self::get_list_url( self::VIEW_AUTOMATIC ) ),
			self::VIEW_AUTOMATIC === $current ? ' class="current" aria-current="page"' : '',
			esc_html__( 'Automatic', 'wp-graphql-smart-cache' ),
			esc_html( number_format_i18n( $counts['automatic'] ) )
		);
		$views[ self::VIEW_UNVERIFIED ] = sprintf(
			'<a href="%s"%s>%s <span class="count">(%s)</span></a>',
			esc_url( self::get_list_url( self::VIEW_UNVERIFIED ) ),
			self::VIEW_UNVERIFIED === $current ? ' class="current" aria-current="page"' : '',
			esc_html__( 'Unverified alias', 'wp-graphql-smart-cache' ),
			esc_html( number_format_i18n( $counts['unverified'] ) )
		);

		return $views;
	}

	/**
	 * Restrict the admin document list to the selected audit view.
	 *
	 * @param string    $where The WHERE clause.
	 * @param \WP_Query $query The query.
	 * @return string
	 */
	public function filter_list_table_query( $where, $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || Document::TYPE_NAME !== $query->get( 'post_type' ) ) {
			return $where;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter.
		$view = isset( $_GET[ self::VIEW_QUERY_VAR ] ) ? sanitize_key( $_GET[ self::VIEW_QUERY_VAR ] ) : '';
		if ( self::VIEW_AUTOMATIC === $view ) {
			$where .= ' AND ' . self::automatic_where_sql();
		} elseif ( self::VIEW_UNVERIFIED === $view ) {
			$where .= ' AND ' . self::automatic_where_sql() . ' AND ' . self::unverified_where_sql();
		}

		return $where;
	}

	/**
	 * Mark unverified alias names in the document list's alias column.
	 *
	 * @param string[]   $term_links Rendered term links.
	 * @param string     $taxonomy   The taxonomy the column shows.
	 * @param \WP_Term[] $terms      The terms.
	 * @return string[]
	 */
	public function flag_unverified_alias_links( $term_links, $taxonomy, $terms ) {
		if ( Document::ALIAS_TAXONOMY_NAME !== $taxonomy ) {
			return $term_links;
		}

		$post_id = get_the_ID();
		if ( ! $post_id || ! self::is_automatic( $post_id ) ) {
			return $term_links;
		}

		foreach ( $terms as $index => $term ) {
			if ( isset( $term_links[ $index ] ) && ! self::is_hash_alias( $term->name ) ) {
				$term_links[ $index ] .= sprintf(
					' <span class="dashicons dashicons-warning" style="color:#d63638;" title="%1$s"></span><span class="screen-reader-text">%1$s</span>',
					esc_attr__( 'Unverified alias: not derived from the document hash', 'wp-graphql-smart-cache' )
				);
			}
		}

		return $term_links;
	}
}

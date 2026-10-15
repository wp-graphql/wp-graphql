<?php

namespace WPGraphQL\Mutation;

use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Data\MediaItemMutation;
use WPGraphQL\Utils\Utils;

class MediaItemCreate {
	/**
	 * Registers the MediaItemCreate mutation.
	 *
	 * @return void
	 * @throws \Exception
	 */
	public static function register_mutation() {
		register_graphql_mutation(
			'createMediaItem',
			[
				'inputFields'         => self::get_input_fields(),
				'outputFields'        => self::get_output_fields(),
				'mutateAndGetPayload' => self::mutate_and_get_payload(),
			]
		);
	}

	/**
	 * Defines the mutation input field configuration.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function get_input_fields() {
		return [
			'altText'       => [
				'type'        => 'String',
				'description' => static function () {
					return __( 'Alternative text to display when mediaItem is not displayed', 'wp-graphql' );
				},
			],
			'authorId'      => [
				'type'        => 'ID',
				'description' => static function () {
					return __( 'The userId to assign as the author of the mediaItem', 'wp-graphql' );
				},
			],
			'caption'       => [
				'type'        => 'String',
				'description' => static function () {
					return __( 'The caption for the mediaItem', 'wp-graphql' );
				},
			],
			'commentStatus' => [
				'type'        => 'String',
				'description' => static function () {
					return __( 'The comment status for the mediaItem', 'wp-graphql' );
				},
			],
			'date'          => [
				'type'        => 'String',
				'description' => static function () {
					return __( 'The date of the mediaItem', 'wp-graphql' );
				},
			],
			'dateGmt'       => [
				'type'        => 'String',
				'description' => static function () {
					return __( 'The date (in GMT zone) of the mediaItem', 'wp-graphql' );
				},
			],
			'description'   => [
				'type'        => 'String',
				'description' => static function () {
					return __( 'Description of the mediaItem', 'wp-graphql' );
				},
			],
			'filePath'      => [
				'type'        => 'String',
				'description' => static function () {
					return __( 'The file name of the mediaItem', 'wp-graphql' );
				},
			],
			'fileType'      => [
				'type'        => 'MimeTypeEnum',
				'description' => static function () {
					return __( 'The file type of the mediaItem', 'wp-graphql' );
				},
			],
			'slug'          => [
				'type'        => 'String',
				'description' => static function () {
					return __( 'The slug of the mediaItem', 'wp-graphql' );
				},
			],
			'status'        => [
				'type'        => 'MediaItemStatusEnum',
				'description' => static function () {
					return __( 'The status of the mediaItem', 'wp-graphql' );
				},
			],
			'title'         => [
				'type'        => 'String',
				'description' => static function () {
					return __( 'The title of the mediaItem', 'wp-graphql' );
				},
			],
			'pingStatus'    => [
				'type'        => 'String',
				'description' => static function () {
					return __( 'The ping status for the mediaItem', 'wp-graphql' );
				},
			],
			'parentId'      => [
				'type'        => 'ID',
				'description' => static function () {
					return __( 'The ID of the parent object', 'wp-graphql' );
				},
			],
		];
	}

	/**
	 * Defines the mutation output field configuration.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function get_output_fields() {
		return [
			'mediaItem' => [
				'type'        => 'MediaItem',
				'description' => static function () {
					return __( 'The MediaItem object mutation type.', 'wp-graphql' );
				},
				'resolve'     => static function ( $payload, $args, AppContext $context ) {
					if ( empty( $payload['postObjectId'] ) || ! absint( $payload['postObjectId'] ) ) {
						return null;
					}

					return $context->get_loader( 'post' )->load_deferred( $payload['postObjectId'] );
				},
			],
		];
	}

	/**
	 * Defines the mutation data modification closure.
	 *
	 * @return callable(array<string,mixed>$input,\WPGraphQL\AppContext $context,\GraphQL\Type\Definition\ResolveInfo $info):array<string,mixed>
	 */
	public static function mutate_and_get_payload() {
		return static function ( $input, AppContext $context, ResolveInfo $info ) {
			/**
			 * Stop now if a user isn't allowed to upload a mediaItem
			 */
			if ( ! current_user_can( 'upload_files' ) ) {
				throw new UserError( esc_html__( 'Sorry, you are not allowed to upload mediaItems', 'wp-graphql' ) );
			}

			$post_type_object = get_post_type_object( 'attachment' );
			if ( empty( $post_type_object ) ) {
				throw new UserError( esc_html__( 'The Media Item could not be created', 'wp-graphql' ) );
			}

			/**
			 * If the mediaItem being created is being assigned to another user that's not the current user, make sure
			 * the current user has permission to edit others mediaItems
			 */
			if ( ! empty( $input['authorId'] ) ) {
				// Ensure authorId is a valid databaseId.
				$input['authorId'] = Utils::get_database_id_from_id( $input['authorId'] );

				// Bail if can't edit other users' attachments.
				if ( get_current_user_id() !== $input['authorId'] && ( ! isset( $post_type_object->cap->edit_others_posts ) || ! current_user_can( $post_type_object->cap->edit_others_posts ) ) ) {
					throw new UserError( esc_html__( 'Sorry, you are not allowed to create mediaItems as this user', 'wp-graphql' ) );
				}
			}

			// REST parity: reject `revision` and `attachment` as parent types.
			// WP_REST_Attachments_Controller::create_item() rejects these at the
			// top of the handler. Mirroring that here so the failure happens
			// before any file is downloaded.
			if ( ! empty( $input['parentId'] ) ) {
				$parent_database_id = Utils::get_database_id_from_id( $input['parentId'] );
				if ( $parent_database_id ) {
					$parent_post_type = get_post_type( (int) $parent_database_id );
					if ( in_array( $parent_post_type, [ 'revision', 'attachment' ], true ) ) {
						throw new UserError( esc_html__( 'Invalid parent type.', 'wp-graphql' ) );
					}
				}
			}

			/**
			 * Set the file name, whether it's a local file or from a URL.
			 * Then set the url for the uploaded file
			 */
			$file_name           = basename( $input['filePath'] );
			$uploaded_file_url   = (string) $input['filePath'];
			$sanitized_file_path = sanitize_file_name( $input['filePath'] );

			// Check that the filetype is allowed
			$check_file = wp_check_filetype( $sanitized_file_path );

			// wp_http_validate_url() blocks 127/10/0/172.16-31/192.168 by the
			// resolved IP but not RFC 3927 link-local (169.254/16), which
			// exposes cloud instance metadata (169.254.169.254). Resolve the
			// host and reject any request that lands on a non-public address, so
			// a DNS name (or a decimal/octal/hex encoding of an address) that
			// maps to an internal host cannot be used to reach it.

			// if the file doesn't pass the check, throw an error
			if ( ! $check_file['ext'] || ! $check_file['type'] || ! wp_http_validate_url( $uploaded_file_url ) || ! self::is_safe_remote_url( $uploaded_file_url ) ) {
				// translators: %s is the file path.
				throw new UserError( esc_html( sprintf( __( 'Invalid filePath "%s"', 'wp-graphql' ), $input['filePath'] ) ) );
			}

			$protocol = wp_parse_url( $input['filePath'], PHP_URL_SCHEME );

			// prevent the filePath from being submitted with a non-allowed protocols
			$allowed_protocols = [ 'https', 'http' ];

			/**
			 * Filter the allowed protocols for the mutation
			 *
			 * @param string[]                             $allowed_protocols The allowed protocols for filePaths to be submitted
			 * @param mixed                                $protocol          The current protocol of the filePath
			 * @param array<string,mixed>                  $input             The input of the current mutation
			 * @param \WPGraphQL\AppContext                $context           The context of the current request
			 * @param \GraphQL\Type\Definition\ResolveInfo $info              The ResolveInfo of the current field
			 *
			 * @hookGroup models
			 * @since 0.0.5
			 */
			$allowed_protocols = apply_filters( 'graphql_media_item_create_allowed_protocols', $allowed_protocols, $protocol, $input, $context, $info );

			if ( ! in_array( $protocol, $allowed_protocols, true ) ) {
				throw new UserError(
					esc_html(
						sprintf(
							// translators: %1$s is the protocol, %2$s is the list of allowed protocols.
							__( 'Invalid protocol. "%1$s". Only "%2$s" allowed.', 'wp-graphql' ),
							$protocol,
							implode( '", "', $allowed_protocols )
						)
					)
				);
			}

			/**
			 * Require the file.php file from wp-admin. This file includes the
			 * download_url and wp_handle_sideload methods.
			 */
			require_once ABSPATH . 'wp-admin/includes/file.php';

			/**
			 * Ensure we have a valid URL before attempting download
			 */
			if ( empty( $uploaded_file_url ) ) {
				throw new UserError( esc_html__( 'Sorry, the file could not be uploaded', 'wp-graphql' ) );
			}

			/**
			 * URL data for the mediaItem, timeout value is the default, see:
			 * https://developer.wordpress.org/reference/functions/download_url/
			 */
			$timeout_seconds = 300;

			// download_url() follows redirects. wp_safe_remote_get() re-validates
			// each hop, but only through wp_http_validate_url(), which does not
			// cover every range is_safe_remote_url() rejects. Re-validate every
			// redirect target with the same guard so a public URL cannot be used
			// to redirect the server onto an internal address.
			add_action( 'requests-requests.before_redirect', [ self::class, 'reject_unsafe_redirect' ] );

			// finally guarantees the guard is removed on every exit path,
			// including the WP < 6.2 case where reject_unsafe_redirect() throws a
			// fatal Error (the Requests\Exception class does not exist) rather
			// than a WP_Error, which would otherwise leave the guard registered
			// on the worker for the rest of the process.
			try {
				$temp_file = download_url( $uploaded_file_url, $timeout_seconds );
			} finally {
				remove_action( 'requests-requests.before_redirect', [ self::class, 'reject_unsafe_redirect' ] );
			}

			/**
			 * Handle the error from download_url if it occurs
			 */
			if ( is_wp_error( $temp_file ) ) {
				throw new UserError( esc_html__( 'Sorry, the URL for this file is invalid, it must be a valid URL', 'wp-graphql' ) );
			}

			// REST parity: enforce multisite file-size and quota limits.
			// Mirrors WP_REST_Attachments_Controller::check_upload_size().
			$size_error = self::check_multisite_upload_size( $temp_file );
			if ( null !== $size_error ) {
				wp_delete_file( $temp_file );
				throw new UserError( esc_html( $size_error ) );
			}

			/**
			 * Build the file data for side loading
			 */
			$file_data = [
				'name'     => $file_name,
				'type'     => ! empty( $input['fileType'] ) ? $input['fileType'] : wp_check_filetype( $temp_file ),
				'tmp_name' => $temp_file,
				'error'    => 0,
				'size'     => (int) filesize( $temp_file ),
			];

			/**
			 * Tells WordPress to not look for the POST form fields that would normally be present as
			 * we downloaded the file from a remote server, so there will be no form fields
			 * The default is true
			 */
			$overrides = [
				'test_form' => false,
			];

			/**
			 * Insert the mediaItem and retrieve it's data
			 */
			$file = wp_handle_sideload( $file_data, $overrides );

			/**
			 * Handle the error from wp_handle_sideload if it occurs
			 */
			if ( ! empty( $file['error'] ) || ! isset( $file['file'] ) ) {
				throw new UserError( esc_html__( 'Sorry, the URL for this file is invalid, it must be a path to the mediaItem file', 'wp-graphql' ) );
			}

			// REST parity: reject image types the server cannot generate
			// sub-sizes for. Mirrors the wp_image_editor_supports() check in
			// WP_REST_Attachments_Controller::create_item_permissions_check()
			// (added in WP 6.8). The wp_prevent_unsupported_mime_type_uploads
			// filter mirrors core, so site owners can opt out the same way.
			$detected_type = $file['type'];
			if (
				apply_filters( 'wp_prevent_unsupported_mime_type_uploads', true, $detected_type )
				&& 0 === strpos( $detected_type, 'image/' )
				&& 'image/svg+xml' !== $detected_type
				&& ! wp_image_editor_supports( [ 'mime_type' => $detected_type ] )
			) {
				if ( ! empty( $file['file'] ) ) {
					wp_delete_file( $file['file'] );
				}
				throw new UserError( esc_html__( 'The web server cannot generate responsive image sizes for this image. Convert it to JPEG or PNG before uploading.', 'wp-graphql' ) );
			}

			/**
			 * Insert the mediaItem object and get the ID
			 */
			$media_item_args = MediaItemMutation::prepare_media_item( $input, $post_type_object, 'createMediaItem', $file );

			/**
			 * Get the post parent and if it's not set, set it to 0
			 */
			$attachment_parent_id = ! empty( $media_item_args['post_parent'] ) ? $media_item_args['post_parent'] : 0;

			/**
			 * Stop now if a user isn't allowed to edit the parent post
			 */
			$parent = get_post( $attachment_parent_id );

			if ( null !== $parent ) {
				$post_parent_type = get_post_type_object( $parent->post_type );

				if ( empty( $post_parent_type ) ) {
					throw new UserError( esc_html__( 'The parent of the Media Item is of an invalid type', 'wp-graphql' ) );
				}

				if ( 'attachment' !== $post_parent_type->name && ( ! isset( $post_parent_type->cap->edit_post ) || ! current_user_can( $post_parent_type->cap->edit_post, $attachment_parent_id ) ) ) {
					throw new UserError( esc_html__( 'Sorry, you are not allowed to upload mediaItems assigned to this parent node', 'wp-graphql' ) );
				}
			}

			/**
			 * Insert the mediaItem
			 *
			 * Required Argument defaults are set in the main MediaItemMutation.php if they aren't set
			 * by the user during input, they are:
			 * post_title (pulled from file if not entered)
			 * post_content (empty string if not entered)
			 * post_status (inherit if not entered)
			 * post_mime_type (pulled from the file if not entered in the mutation)
			 */
			$attachment_id = wp_insert_attachment( $media_item_args, $file['file'], $attachment_parent_id, true );

			if ( is_wp_error( $attachment_id ) ) {
				$error_message = $attachment_id->get_error_message();
				if ( ! empty( $error_message ) ) {
					throw new UserError( esc_html( $error_message ) );
				}

				throw new UserError( esc_html__( 'The media item failed to create but no error was provided', 'wp-graphql' ) );
			}

			/**
			 * Check if the wp_generate_attachment_metadata method exists and include it if not.
			 */
			require_once ABSPATH . 'wp-admin/includes/image.php';

			/**
			 * Generate and update the mediaItem's metadata.
			 * If we make it this far the file and attachment
			 * have been validated and we will not receive any errors
			 */
			$attachment_data = wp_generate_attachment_metadata( $attachment_id, $file['file'] );
			wp_update_attachment_metadata( $attachment_id, $attachment_data );

			/**
			 * Update alt text postmeta for mediaItem
			 */
			MediaItemMutation::update_additional_media_item_data( $attachment_id, $input, $post_type_object, 'createMediaItem', $context, $info );

			return [
				'postObjectId' => $attachment_id,
			];
		};
	}

	/**
	 * Mirrors WP_REST_Attachments_Controller::check_upload_size() for the
	 * multisite quota and per-file size limit checks. Returns null when the
	 * file is within all limits, or a translated error message string when
	 * one of the limits is exceeded.
	 *
	 * No-ops on single-site installs, where these site-option-driven limits
	 * do not apply.
	 *
	 * @param string $file_path Path to the downloaded temp file.
	 * @return string|null Error message if size exceeds limits, null otherwise.
	 */
	private static function check_multisite_upload_size( $file_path ) {
		if ( ! is_multisite() || get_site_option( 'upload_space_check_disabled' ) ) {
			return null;
		}

		$file_size  = (int) filesize( $file_path );
		$space_left = (int) get_upload_space_available();

		if ( $space_left < $file_size ) {
			return sprintf(
				// translators: %s is the required disk space in kilobytes.
				__( 'Not enough space to upload. %s KB needed.', 'wp-graphql' ),
				number_format( ( $file_size - $space_left ) / KB_IN_BYTES )
			);
		}

		$max_kb = (int) get_site_option( 'fileupload_maxk', 1500 );
		if ( $file_size > KB_IN_BYTES * $max_kb ) {
			return sprintf(
				// translators: %s is the maximum allowed file size in kilobytes.
				__( 'This file is too big. Files must be less than %s KB in size.', 'wp-graphql' ),
				$max_kb
			);
		}

		require_once ABSPATH . 'wp-admin/includes/ms.php';
		if ( upload_is_user_over_quota( false ) ) {
			return __( 'You have used your space quota. Please delete files before uploading.', 'wp-graphql' );
		}

		return null;
	}

	/**
	 * Rejects a redirect whose target is not safe for the server to fetch.
	 *
	 * Registered on the Requests before_redirect hook while a media file is
	 * downloaded, so every hop of a redirect chain is validated with the same
	 * host resolution as the initial URL. Throwing aborts the request; WP_Http
	 * converts the exception into a WP_Error, which download_url() returns and
	 * the caller surfaces as an invalid filePath.
	 *
	 * Public because WordPress must be able to invoke it as a hook callback; it
	 * is not part of the extension API.
	 *
	 * @internal
	 *
	 * @param mixed $location The URL the response is redirecting to.
	 *
	 * @return void
	 * @throws \WpOrg\Requests\Exception When the redirect target is not publicly routable.
	 */
	public static function reject_unsafe_redirect( $location ) {
		if ( ! is_string( $location ) || self::is_safe_remote_url( $location ) ) {
			return;
		}

		// Abort the redirect. On WP 6.2+ WP_Http catches WpOrg\Requests\Exception
		// and turns it into a WP_Error, so download_url() cleans up and returns
		// that error. On WP < 6.2 the class is unavailable and this surfaces as a
		// hard failure, which still fails closed: the upload is aborted before
		// the server can be redirected onto an internal address.
		throw new \WpOrg\Requests\Exception(
			esc_html__( 'A redirect to a non-public address was blocked.', 'wp-graphql' ),
			'wpgraphql_media_item_unsafe_redirect'
		);
	}

	/**
	 * Determines whether a remote URL is safe for the server to fetch.
	 *
	 * Resolves the host and returns false when the host does not resolve or any
	 * resolved address is not publicly routable (loopback, private, link-local,
	 * or otherwise reserved). Because the check runs against the resolved
	 * address rather than the host text, a DNS name, or a decimal/octal/hex
	 * encoding of an address, that maps to an internal host such as the
	 * 169.254.169.254 cloud-metadata endpoint is rejected.
	 *
	 * Both IPv4 (A) and IPv6 (AAAA) records are resolved and every address is
	 * validated. download_url() delegates to curl, which may connect over IPv6
	 * even when a host also advertises a public IPv4 address, so validating the
	 * IPv4 result alone would let a dual-stack host with a public A record and
	 * an internal AAAA record (e.g. an IPv6 cloud-metadata endpoint) through.
	 *
	 * @param string $url The URL whose host should be validated.
	 */
	private static function is_safe_remote_url( string $url ): bool {
		$host = wp_parse_url( $url, PHP_URL_HOST );

		if ( ! is_string( $host ) || '' === $host ) {
			return false;
		}

		// Unwrap an IPv6 literal, e.g. "[::1]" becomes "::1".
		$host = trim( $host, '[]' );

		// IP literals are checked directly. Anything else is resolved, which
		// also normalizes numeric host encodings (e.g. "2852039166") to a
		// dotted-quad address.
		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			$addresses = [ $host ];
		} else {
			$addresses = self::resolve_host_addresses( $host );
		}

		if ( empty( $addresses ) ) {
			return false;
		}

		foreach ( $addresses as $address ) {
			if ( ! self::is_public_ip( $address ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Resolves a host name to every IPv4 and IPv6 address it advertises.
	 *
	 * The gethostbynamel() built-in returns A (IPv4) records only. AAAA (IPv6)
	 * records are resolved separately via dns_get_record() so a dual-stack host
	 * cannot hide an internal IPv6 address behind a public IPv4 address.
	 *
	 * Both lookups fail closed. Resolving nothing yields no addresses, and
	 * is_safe_remote_url() rejects a host that resolves to nothing. An AAAA lookup
	 * that errors is treated the same way, rather than as a host with no IPv6, so a
	 * transient resolver failure cannot leave the IPv6 side unchecked while the A
	 * records vouch for the host.
	 *
	 * One limit worth naming: where dns_get_record() is unavailable, usually because
	 * it is in disable_functions, there is no way to read AAAA records at all, and
	 * only the A records are checked. Failing closed there would take the feature
	 * away on those hosts entirely, so it proceeds. A host in that configuration is
	 * relying on gethostbynamel() alone.
	 *
	 * @param string $host The host name to resolve.
	 *
	 * @return string[] The resolved IPv4 and IPv6 addresses, empty if none.
	 */
	private static function resolve_host_addresses( string $host ): array {
		$addresses = [];

		$ipv4 = gethostbynamel( $host );
		if ( is_array( $ipv4 ) ) {
			$addresses = $ipv4;
		}

		if ( function_exists( 'dns_get_record' ) ) {
			$ipv6 = self::aaaa_addresses_from_records( dns_get_record( $host, DNS_AAAA ) );

			// A lookup that failed cannot be treated as a host with no IPv6, so the
			// whole host is refused. See aaaa_addresses_from_records().
			if ( null === $ipv6 ) {
				return [];
			}

			$addresses = array_merge( $addresses, $ipv6 );
		}

		return $addresses;
	}

	/**
	 * Reads the IPv6 addresses out of a DNS_AAAA answer, telling a failed lookup apart
	 * from a host that simply has no IPv6.
	 *
	 * The dns_get_record() built-in answers an empty array for a host with no AAAA
	 * records, and false when the lookup itself failed. Those are different facts, and
	 * only the first is safe to continue on. Treating a failure as "no IPv6 records" would
	 * validate the A records, report the host as safe, and leave curl free to connect
	 * over IPv6 to an address nothing looked at, which is the bypass the dual-stack
	 * resolution exists to prevent.
	 *
	 * @param array<int,array<string,mixed>>|false $records The dns_get_record() answer for DNS_AAAA.
	 *
	 * @return string[]|null The IPv6 addresses, or null when the lookup failed.
	 */
	private static function aaaa_addresses_from_records( $records ): ?array {
		if ( ! is_array( $records ) ) {
			return null;
		}

		$addresses = [];

		foreach ( $records as $record ) {
			if ( isset( $record['ipv6'] ) && is_string( $record['ipv6'] ) ) {
				$addresses[] = $record['ipv6'];
			}
		}

		return $addresses;
	}

	/**
	 * Determines whether an IP address is publicly routable.
	 *
	 * Rejects private, reserved, loopback, and link-local ranges for both IPv4
	 * and IPv6. Several IPv6 forms carry an IPv4 address inside them, and those
	 * are unwrapped so the embedded IPv4 range is evaluated rather than trusted:
	 * IPv4-mapped (::ffff:a.b.c.d), IPv4-compatible (::a.b.c.d), NAT64
	 * (64:ff9b::/96) and 6to4 (2002::/16).
	 *
	 * Everything about IPv6 is decided on the packed bytes, and every IPv6 range
	 * that is not publicly routable is listed explicitly below, because PHP's
	 * FILTER_FLAG_NO_PRIV_RANGE and FILTER_FLAG_NO_RES_RANGE read the text rather
	 * than the address. Two spellings of the same sixteen bytes get opposite
	 * answers from them, and which spellings differ changes between PHP releases:
	 * on PHP 8.3 the flags reject ::ffff:7f00:1 while allowing ::7f00:1,
	 * 64:ff9b::7f00:1 and 2002:7f00:1::, and up to PHP 8.2 they skip their IPv6
	 * range checks altogether for an address ending in a dotted quad, so
	 * fd00::0.0.0.1 passed while fd00::1 did not. The flags are therefore not
	 * trusted for IPv6 at all; they are left in place only for IPv4.
	 *
	 * Unwrapping only the dotted IPv4-mapped form previously let an author who
	 * can upload files point filePath at a host whose AAAA record was one of the
	 * other forms and have the server fetch an internal URL.
	 *
	 * @see https://github.com/wp-graphql/wp-graphql/security/advisories/GHSA-3j5r-mp5g-4wrw
	 *
	 * @param string $ip The IP address to check.
	 */
	private static function is_public_ip( string $ip ): bool {
		// Validated before it is unpacked, so inet_pton() is never handed something
		// it would emit a warning about. Fail closed either way: an address we
		// cannot parse is not one we can vouch for.
		if ( false === filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return false;
		}

		$packed = inet_pton( $ip );

		if ( false === $packed ) {
			return false;
		}

		if ( 16 === strlen( $packed ) ) {
			$embedded = self::embedded_ipv4( $packed );

			// An IPv6 form carrying an IPv4 address is judged on that address.
			if ( null !== $embedded ) {
				$ip     = $embedded;
				$packed = inet_pton( $embedded );
			} else {
				// Every IPv6 range that is not publicly routable, listed here rather
				// than left to the filter flags below. ::1/128 is loopback;
				// fe80::/10 is link-local; fc00::/7 is unique-local; fec0::/10 is
				// the deprecated site-local range, still routable inside a network;
				// ff00::/8 is multicast; ::/128 is unspecified; 100::/64 is the
				// discard-only range; 2001:db8::/32 is documentation.
				//
				// The first three are also rejected by the filter flags, but only
				// when the address is written as hex groups. Up to PHP 8.2, and up
				// to 8.3.20 and 8.4.26, the flags skip their IPv6 range checks
				// entirely for an address whose text ends in a dotted quad, so
				// `fd00::0.0.0.1` was judged public while `fd00::1`, the same
				// sixteen bytes, was not. Matching on the packed bytes is spelling
				// independent, which is the whole point of this function.
				//
				// 64:ff9b::/32 is reached only when the address was not unwrapped
				// above, which means it is in the NAT64 range but not the
				// well-known 64:ff9b::/96 translation prefix. That leaves the
				// RFC 8215 local-use prefix 64:ff9b:1::/48, where the embedded
				// IPv4 sits at a different offset for every permitted prefix
				// length. Rather than decode each of those, the range is rejected:
				// it exists for a network's own NAT64 translator, so a caller
				// pointing us into it is pointing at that network. A host using
				// the well-known prefix to reach a public address is unaffected,
				// because it is unwrapped before this list is consulted.
				foreach ( [ '::1/128', 'fe80::/10', 'fc00::/7', 'fec0::/10', 'ff00::/8', '::/128', '100::/64', '2001:db8::/32', '64:ff9b::/32' ] as $cidr ) {
					if ( self::ip_in_cidr( (string) $packed, $cidr ) ) {
						return false;
					}
				}
			}
		}

		if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
			return false;
		}

		// FILTER_FLAG_NO_RES_RANGE misses several IPv4 blocks that are not
		// publicly routable and can front internal services, so reject them
		// explicitly: 100.64.0.0/10 (carrier-grade NAT, RFC 6598, used for EKS
		// pod IPs and some metadata proxies), 192.0.0.0/24 (IETF protocol
		// assignments), and 198.18.0.0/15 (benchmarking, RFC 2544).
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			foreach ( [ '100.64.0.0/10', '192.0.0.0/24', '198.18.0.0/15' ] as $cidr ) {
				if ( self::ipv4_in_cidr( $ip, $cidr ) ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Returns the IPv4 address carried inside an IPv6 address, if there is one.
	 *
	 * Four IPv6 forms embed an IPv4 address and route to it: IPv4-mapped
	 * (::ffff:0:0/96), IPv4-compatible (::/96, deprecated but still resolvable),
	 * NAT64 (64:ff9b::/96) and 6to4 (2002::/16, where the address sits in the
	 * second through fifth bytes). Each is judged on the IPv4 address it targets.
	 *
	 * @param string $packed The address in its packed 16-byte form.
	 *
	 * @return string|null The embedded IPv4 address in dotted form, or null if there is none.
	 */
	private static function embedded_ipv4( string $packed ): ?string {
		$prefixes = [
			// Each entry pairs the prefix bytes that identify the form with the
			// offset at which its embedded IPv4 address begins.
			[ "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xff\xff", 12 ], // IPv4-mapped.
			[ "\x00\x64\xff\x9b\x00\x00\x00\x00\x00\x00\x00\x00", 12 ], // NAT64.
			[ "\x20\x02", 2 ], // 6to4.
		];

		foreach ( $prefixes as [ $prefix, $offset ] ) {
			if ( 0 === strncmp( $packed, $prefix, strlen( $prefix ) ) ) {
				return inet_ntop( substr( $packed, $offset, 4 ) ) ?: null;
			}
		}

		// ::/96 IPv4-compatible. The all-zero prefix also covers :: and ::1, so
		// only treat it as an embedded address when the low 32 bits are not 0 or 1.
		if ( 0 === strncmp( $packed, str_repeat( "\x00", 12 ), 12 ) ) {
			$low = substr( $packed, 12, 4 );
			if ( "\x00\x00\x00\x00" !== $low && "\x00\x00\x00\x01" !== $low ) {
				return inet_ntop( $low ) ?: null;
			}
		}

		return null;
	}

	/**
	 * Whether a packed IP address falls inside a CIDR range.
	 *
	 * Works on the packed form so it handles IPv6 as well as IPv4, comparing
	 * whole bytes and then the remaining bits of the final partial byte.
	 *
	 * @param string $packed The address in its packed form.
	 * @param string $cidr   The range, for example `fec0::/10`.
	 */
	private static function ip_in_cidr( string $packed, string $cidr ): bool {
		[ $subnet, $prefix ] = explode( '/', $cidr );

		// The ranges are hardcoded above, so this is a guard against a typo rather
		// than against input. Validated first so inet_pton() cannot warn, and a
		// range that does not parse matches nothing.
		if ( false === filter_var( $subnet, FILTER_VALIDATE_IP ) ) {
			return false;
		}

		$subnet_packed = inet_pton( $subnet );

		if ( false === $subnet_packed || strlen( $subnet_packed ) !== strlen( $packed ) ) {
			return false;
		}

		$bits        = (int) $prefix;
		$whole_bytes = intdiv( $bits, 8 );
		$spare_bits  = $bits % 8;

		if ( $whole_bytes > 0 && 0 !== strncmp( $packed, $subnet_packed, $whole_bytes ) ) {
			return false;
		}

		if ( 0 === $spare_bits ) {
			return true;
		}

		$mask = ~( ( 1 << ( 8 - $spare_bits ) ) - 1 ) & 0xff;

		return ( ord( $packed[ $whole_bytes ] ) & $mask ) === ( ord( $subnet_packed[ $whole_bytes ] ) & $mask );
	}

	/**
	 * Whether a dotted IPv4 address falls inside a CIDR range.
	 *
	 * Compares the two addresses as 32-bit integers under the prefix mask. Used
	 * for the IPv4 ranges PHP's filter flags do not reject on their own.
	 *
	 * @param string $ip   The IPv4 address in dotted form.
	 * @param string $cidr The range, for example `100.64.0.0/10`.
	 */
	private static function ipv4_in_cidr( string $ip, string $cidr ): bool {
		[ $subnet, $prefix ] = explode( '/', $cidr );

		$ip_long     = ip2long( $ip );
		$subnet_long = ip2long( $subnet );

		if ( false === $ip_long || false === $subnet_long ) {
			return false;
		}

		$mask = -1 << ( 32 - (int) $prefix );

		return ( $ip_long & $mask ) === ( $subnet_long & $mask );
	}
}

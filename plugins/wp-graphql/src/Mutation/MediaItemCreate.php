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
	 * cannot hide an internal IPv6 address behind a public IPv4 address. A failed
	 * lookup returns no records and yields no addresses, which is fail-closed:
	 * is_safe_remote_url() rejects a host that resolves to nothing.
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
			$records = dns_get_record( $host, DNS_AAAA );
			if ( is_array( $records ) ) {
				foreach ( $records as $record ) {
					if ( isset( $record['ipv6'] ) && is_string( $record['ipv6'] ) ) {
						$addresses[] = $record['ipv6'];
					}
				}
			}
		}

		return $addresses;
	}

	/**
	 * Determines whether an IP address is publicly routable.
	 *
	 * Rejects private, reserved, loopback, and link-local ranges for both IPv4
	 * and IPv6. IPv4-mapped IPv6 addresses (::ffff:a.b.c.d) are unwrapped so the
	 * embedded IPv4 range is evaluated rather than trusted.
	 *
	 * @param string $ip The IP address to check.
	 */
	private static function is_public_ip( string $ip ): bool {
		// Unwrap an IPv4-mapped IPv6 address so ::ffff:169.254.169.254 is judged
		// as the link-local 169.254/16 range it actually targets.
		if ( 0 === stripos( $ip, '::ffff:' ) ) {
			$mapped = substr( $ip, strlen( '::ffff:' ) );
			if ( filter_var( $mapped, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
				$ip = $mapped;
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
	 * Determines whether an IPv4 address falls within a CIDR block.
	 *
	 * @param string $ip   A validated IPv4 address.
	 * @param string $cidr A CIDR block in "network/prefix" form.
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

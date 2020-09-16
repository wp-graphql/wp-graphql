<?php
namespace WPGraphQL\Admin\Extensions;

class Extensions {

	public function init() {
		add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
	}

	public function add_menu_page() {
		add_submenu_page(
			'graphql',
			__( 'Extensions', 'wp-graphql' ),
			__( 'Extensions', 'wp-graphql' ),
			'manage_options',
			'graphql-extensions',
			[ $this, 'render_extensions_page' ]
		);
	}

	public function get_extensions() {

		$query = '
		query GetExtensionPlugins {
		  extensionPlugins(first:100) {
		    nodes {
		      id
		      title
		      content
		      extensionFields {
		        pluginHost
		        pluginLink
		      }
		      extensionAuthors {
		        nodes {
		          id
		          name
		          extensionAuthorFields {
		            bio
		            githubProfile
		            twitterProfile
		            wordpressProfile
		          }
		        }
		      }
		    }
		  }
		}
		';

		$request = wp_remote_post( 'http://wpgqldocs.local/graphql', [
			'headers' => [
				'Content-Type' => 'application/json',
			],
			'body' => wp_json_encode(['query' => $query ])
		] );

		$decoded_response = json_decode( $request['body'], true );

		return isset( $decoded_response['data']['extensionPlugins']['nodes'] ) ? $decoded_response['data']['extensionPlugins']['nodes'] : null;

	}

	public function render_extensions_page() {
		$extensions = $this->get_extensions();
		if ( $extensions ) :
		?>
		<div class="wrap wpgraphql_extensions_wrap">
			<header class="section-header">
				<h2>WPGraphQL Extensions</h2>
				<hr>
			</header>
			<ul class="extensions">
				<?php
				foreach ( $extensions as $extension ) {
					$img_url = isset( $extension['logo']['sourceUrl'] ) ? $extension['logo']['sourceUrl'] : 'https://github.com/wp-graphql/wp-graphql/raw/develop/img/logo.png';
					?>
					<div class="plugin-card plugin-card-<?php esc_html_e( $extension['title'] ); ?>">
						<div class="plugin-card-top">
							<div class="name column-name">
								<h3>
									<a href="<?php echo esc_url( $extension['extensionFields']['pluginLink'] ); ?>" class="thickbox open-plugin-details-modal">
										<?php esc_html_e( $extension['title'] ); ?><img src="<?php echo $img_url; ?>" class="plugin-icon" alt="">
									</a>
								</h3>
							</div>
							<div class="action-links">
								<ul class="plugin-action-buttons">
									<li>
										<a class="install-now button" data-slug="akismet" href="<?php echo esc_url( $extension['extensionFields']['pluginLink'] ); ?>" aria-label="Get <?php esc_html_e( $extension['title'] ); ?>" data-name="<?php esc_html_e( $extension['title'] ); ?>">Install Now</a>
									</li>
								</ul>
							</div>
							<div class="desc column-description">
								<p><?php echo $extension['content']; ?></p>
								<?php if ( isset( $extension['extensionAuthors']['nodes'] ) ) { ?>
									<p class="authors"><cite>By <?php foreach ( $extension['extensionAuthors']['nodes'] as $author ) { ?><a href="<?php echo esc_url( $author['extensionAuthorFields']['githubProfile'] ); ?>"><?php esc_html_e( $author['name'] ); ?></a> <?php } ?></cite></p>
								<?php } ?>
							</div>
						</div>
						<div class="plugin-card-bottom">
							<div class="vers column-rating">
								<div class="star-rating"><span class="screen-reader-text">4.5 rating based on 906 ratings</span><div class="star star-full" aria-hidden="true"></div><div class="star star-full" aria-hidden="true"></div><div class="star star-full" aria-hidden="true"></div><div class="star star-full" aria-hidden="true"></div><div class="star star-half" aria-hidden="true"></div></div>					<span class="num-ratings" aria-hidden="true">(906)</span>
							</div>
							<div class="column-updated">
								<strong>Last Updated:</strong>
								1 month ago				</div>
							<div class="column-downloaded">
								5+ Million Active Installations				</div>
							<div class="column-compatibility">
								<span class="compatibility-compatible"><strong>Compatible</strong> with your version of WordPress</span>				</div>
						</div>
					</div>
				<?php } ?>
			</ul>
		</div>
		<?php
		endif;

	}

}

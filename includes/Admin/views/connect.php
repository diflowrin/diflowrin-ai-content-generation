<?php
/**
 * Connect screen.
 *
 * @var \Diflowrin\ContentGenerator\Connect\Connect $connect
 * @package Diflowrin\ContentGenerator
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- template is require()d inside an Admin method, so these variables are function-scoped, not global.
defined( 'ABSPATH' ) || exit;

$checks   = $connect->preflight();
$is_ready = $connect->is_ready();
$auth_url = $connect->authorize_url();
$website  = $connect->app_website();
$store    = $connect->app_store_url();
$info     = $connect->connection_info();

$datetime_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag set by our own redirect.
$just_disconnected = isset( $_GET['diflowrin_content_generator_disconnected'] );

$pill_labels = array(
	'ok'    => __( 'Ready', 'diflowrin-ai-content-generation' ),
	'error' => __( 'Action needed', 'diflowrin-ai-content-generation' ),
	'info'  => __( 'Info', 'diflowrin-ai-content-generation' ),
);
?>
<div class="wrap ca-wrap">

	<header class="ca-header">
		<h1 class="ca-header__title"><?php esc_html_e( 'Diflowrin AI Content Generation', 'diflowrin-ai-content-generation' ); ?></h1>
		<p class="ca-header__sub">
			<?php esc_html_e( 'Connect this site to the SEO Content Architect desktop app, or write a single article right here with AI.', 'diflowrin-ai-content-generation' ); ?>
		</p>
	</header>

	<div class="ca-grid">

		<section class="ca-card" aria-labelledby="ca-connect-title">
			<h2 id="ca-connect-title" class="ca-card__title"><?php esc_html_e( 'Connect to the desktop app', 'diflowrin-ai-content-generation' ); ?></h2>
			<p class="ca-muted">
				<?php esc_html_e( 'One click creates a secure Application Password and hands it to the app. This plugin never stores your password.', 'diflowrin-ai-content-generation' ); ?>
			</p>

			<?php if ( ! $info ) : ?>
				<div class="ca-note">
					<strong><?php esc_html_e( 'Important: the SEO Content Architect desktop app must be installed on your computer first.', 'diflowrin-ai-content-generation' ); ?></strong>
					<br />
					<?php esc_html_e( 'The Connect button opens the app on this computer and hands it the connection. Without the app installed, the button does nothing.', 'diflowrin-ai-content-generation' ); ?>
					<ol style="margin:10px 0 4px 18px;padding:0;">
						<li>
							<?php
							printf(
								/* translators: %s: link to the Microsoft Store listing. */
								esc_html__( 'Download and install the desktop app from %s', 'diflowrin-ai-content-generation' ),
								'<a href="' . esc_url( $store ) . '" target="_blank" rel="noopener noreferrer"><strong>' . esc_html__( 'the Microsoft Store', 'diflowrin-ai-content-generation' ) . '</strong></a>'
							);
							?>
						</li>
						<li><?php esc_html_e( 'Open the app once on this computer.', 'diflowrin-ai-content-generation' ); ?></li>
						<li><?php esc_html_e( 'Come back to this page and click "Connect to SEO Content Architect".', 'diflowrin-ai-content-generation' ); ?></li>
					</ol>
				</div>
			<?php endif; ?>

			<?php if ( $just_disconnected ) : ?>
				<p class="ca-note ca-note--warn">
					<?php esc_html_e( 'Disconnected. The app\'s access to this site has been revoked. Remember to also remove the site in the desktop app.', 'diflowrin-ai-content-generation' ); ?>
				</p>
			<?php endif; ?>

			<?php if ( $info ) : ?>
				<div class="ca-note ca-note--ok">
					<strong><?php esc_html_e( '✅ Connected to the desktop app.', 'diflowrin-ai-content-generation' ); ?></strong><br />
					<?php
					printf(
						/* translators: %s: date and time the connection was approved. */
						esc_html__( 'Connection approved on %s.', 'diflowrin-ai-content-generation' ),
						esc_html( date_i18n( $datetime_format, $info['created'] ) )
					);
					?>
					<br />
					<?php if ( $info['last_used'] > 0 ) : ?>
						<?php
						printf(
							/* translators: 1: date and time of the app's last request, 2: IP address. */
							esc_html__( 'The app last accessed this site on %1$s%2$s.', 'diflowrin-ai-content-generation' ),
							esc_html( date_i18n( $datetime_format, $info['last_used'] ) ),
							'' !== $info['last_ip'] ? esc_html( sprintf( /* translators: %s: IP address. */ __( ' from %s', 'diflowrin-ai-content-generation' ), $info['last_ip'] ) ) : ''
						);
						?>
					<?php else : ?>
						<?php esc_html_e( 'The app has not made its first request yet — if it does not appear connected there, run the connection again.', 'diflowrin-ai-content-generation' ); ?>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<ul class="ca-status">
				<?php foreach ( $checks as $check ) : ?>
					<?php $status = $check['status']; ?>
					<li class="ca-status__row">
						<span class="ca-pill ca-pill--<?php echo esc_attr( $status ); ?>">
							<?php echo esc_html( isset( $pill_labels[ $status ] ) ? $pill_labels[ $status ] : $status ); ?>
						</span>
						<span class="ca-status__body">
							<strong class="ca-status__label"><?php echo esc_html( $check['label'] ); ?></strong>
							<span class="ca-status__detail"><?php echo esc_html( $check['detail'] ); ?></span>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>

			<?php if ( $is_ready ) : ?>
				<a class="ca-btn ca-btn--primary" href="<?php echo esc_url( $auth_url ); ?>">
					<?php $info ? esc_html_e( 'Reconnect to SEO Content Architect', 'diflowrin-ai-content-generation' ) : esc_html_e( 'Connect to SEO Content Architect', 'diflowrin-ai-content-generation' ); ?>
				</a>
				<?php if ( $info ) : ?>
					<a
						class="ca-btn ca-btn--ghost"
						href="<?php echo esc_url( $connect->disconnect_url() ); ?>"
						data-ca-confirm="<?php esc_attr_e( 'Revoke the desktop app\'s access to this site?', 'diflowrin-ai-content-generation' ); ?>"
					>
						<?php esc_html_e( 'Disconnect', 'diflowrin-ai-content-generation' ); ?>
					</a>
				<?php else : ?>
					<p class="ca-help" style="margin-top:8px;">
						<?php
						printf(
							/* translators: %s: link to the Microsoft Store listing. */
							esc_html__( 'Clicked Connect and nothing opened? The desktop app is not installed on this computer yet — get it from %s and try again.', 'diflowrin-ai-content-generation' ),
							'<a href="' . esc_url( $store ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'the Microsoft Store', 'diflowrin-ai-content-generation' ) . '</a>'
						);
						?>
					</p>
				<?php endif; ?>
			<?php else : ?>
				<span class="ca-btn ca-btn--primary is-disabled" aria-disabled="true" role="button" tabindex="-1">
					<?php esc_html_e( 'Connect to SEO Content Architect', 'diflowrin-ai-content-generation' ); ?>
				</span>
				<p class="ca-note ca-note--warn">
					<?php esc_html_e( 'Resolve the items marked “Action needed” above, then reload this page to connect.', 'diflowrin-ai-content-generation' ); ?>
				</p>
			<?php endif; ?>
		</section>

		<aside class="ca-card ca-card--soft" aria-labelledby="ca-upsell-title">
			<h2 id="ca-upsell-title" class="ca-card__title"><?php esc_html_e( 'Scale beyond one article', 'diflowrin-ai-content-generation' ); ?></h2>
			<p class="ca-muted">
				<?php esc_html_e( 'Single-article writing lives here in WordPress. The desktop app adds the heavy lifting:', 'diflowrin-ai-content-generation' ); ?>
			</p>
			<ul class="ca-featurelist">
				<li><?php esc_html_e( 'Premium article generation — deeper research, fact-checking and a high-quality editorial pass', 'diflowrin-ai-content-generation' ); ?></li>
				<li><?php esc_html_e( 'Bulk generation across many topics at once', 'diflowrin-ai-content-generation' ); ?></li>
				<li><?php esc_html_e( 'Scheduled auto-posting to one or many sites', 'diflowrin-ai-content-generation' ); ?></li>
				<li><?php esc_html_e( 'Social Studio for turning posts into social content', 'diflowrin-ai-content-generation' ); ?></li>
				<li><?php esc_html_e( 'Image pipeline and multi-site distribution', 'diflowrin-ai-content-generation' ); ?></li>
				<li><?php esc_html_e( 'A full SEO toolkit — keyword research, competitor and SERP analysis, content scoring, rank tracking and site audits', 'diflowrin-ai-content-generation' ); ?></li>
			</ul>
			<p class="ca-note ca-note--ok">
				<?php esc_html_e( 'Article generation and content research are free in the app. You use your own API key there too, so there is no subscription for them.', 'diflowrin-ai-content-generation' ); ?>
			</p>
			<a class="ca-btn ca-btn--ghost" href="<?php echo esc_url( $website ); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Get the desktop app', 'diflowrin-ai-content-generation' ); ?>
			</a>
		</aside>

	</div>
</div>

<?php
/**
 * Plugin Name:       Novoslate Site Agent
 * Description:       Reports WordPress health (pending updates, versions) to Novoslate Ops once a day.
 * Version:           1.1.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Novoslate
 * Author URI:        https://novoslate.com
 * License:           GPL-2.0-or-later
 * Update URI:        https://github.com/peterg23/novoslate-site-agent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NSA_VERSION', '1.1.0' );
define( 'NSA_PATH', plugin_dir_path( __FILE__ ) );
if ( ! defined( 'NSA_GITHUB_REPO' ) ) {
	define( 'NSA_GITHUB_REPO', 'https://github.com/peterg23/novoslate-site-agent/' );
}
define( 'NSA_DEFAULT_ENDPOINT', 'https://ops.novoslate.com/api/agent/report' );

// Updates from GitHub releases (same setup as Project Estimator).
require_once NSA_PATH . 'lib/plugin-update-checker/plugin-update-checker.php';
$nsa_updater = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
	NSA_GITHUB_REPO,
	__FILE__,
	'novoslate-site-agent'
);
$nsa_updater->getVcsApi()->enableReleaseAssets( '/novoslate-site-agent\.zip$/' );
if ( defined( 'NSA_GITHUB_TOKEN' ) && NSA_GITHUB_TOKEN ) {
	$nsa_updater->setAuthentication( NSA_GITHUB_TOKEN );
}

final class Novoslate_Site_Agent {
	const VERSION = NSA_VERSION;
	const OPT     = 'novoslate_agent';
	const HOOK    = 'novoslate_agent_daily';

	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'send_report' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_novoslate_agent_send', array( __CLASS__, 'handle_send_now' ) );
		add_action( 'update_option_' . self::OPT, array( __CLASS__, 'send_report' ) );
	}

	public static function activate() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + 300, 'daily', self::HOOK );
		}
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::HOOK );
	}

	private static function opts() {
		$o = get_option( self::OPT, array() );
		$o = wp_parse_args( $o, array( 'endpoint' => '', 'token' => '' ) );
		if ( '' === $o['endpoint'] ) {
			$o['endpoint'] = NSA_DEFAULT_ENDPOINT;
		}
		return $o;
	}

	/** Collect update + version data. */
	public static function collect() {
		require_once ABSPATH . 'wp-admin/includes/update.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		// Refresh update transients so the data is current.
		wp_update_plugins();
		wp_update_themes();
		wp_version_check();

		$core_update = null;
		$core        = get_core_updates();
		if ( is_array( $core ) ) {
			foreach ( $core as $u ) {
				if ( isset( $u->response ) && 'upgrade' === $u->response ) {
					$core_update = $u->current;
					break;
				}
			}
		}

		$plugins        = get_plugins();
		$plugin_updates = array();
		$t              = get_site_transient( 'update_plugins' );
		if ( $t && ! empty( $t->response ) ) {
			foreach ( $t->response as $file => $info ) {
				$plugin_updates[] = array(
					'name' => isset( $plugins[ $file ]['Name'] ) ? $plugins[ $file ]['Name'] : $file,
					'from' => isset( $plugins[ $file ]['Version'] ) ? $plugins[ $file ]['Version'] : '',
					'to'   => isset( $info->new_version ) ? $info->new_version : '',
				);
			}
		}

		$theme_updates = array();
		$tt            = get_site_transient( 'update_themes' );
		if ( $tt && ! empty( $tt->response ) ) {
			foreach ( $tt->response as $slug => $info ) {
				$theme            = wp_get_theme( $slug );
				$theme_updates[]  = array(
					'name' => $theme->exists() ? $theme->get( 'Name' ) : $slug,
					'from' => $theme->exists() ? $theme->get( 'Version' ) : '',
					'to'   => isset( $info['new_version'] ) ? $info['new_version'] : '',
				);
			}
		}

		return array(
			'site_url'       => home_url( '/' ),
			'wp_version'     => get_bloginfo( 'version' ),
			'php_version'    => PHP_VERSION,
			'core_update'    => $core_update,
			'plugin_updates' => $plugin_updates,
			'theme_updates'  => $theme_updates,
			'plugins_active' => count( (array) get_option( 'active_plugins', array() ) ),
			'agent_version'  => self::VERSION,
		);
	}

	/** POST the report to Novoslate Ops. Returns true or an error string. */
	public static function send_report() {
		$o = self::opts();
		if ( empty( $o['endpoint'] ) || empty( $o['token'] ) ) {
			return 'Endpoint and token are required.';
		}
		$res = wp_remote_post(
			$o['endpoint'],
			array(
				'timeout' => 20,
				'headers' => array(
					'Content-Type'      => 'application/json',
					'X-Novoslate-Token' => $o['token'],
				),
				'body'    => wp_json_encode( self::collect() ),
			)
		);
		if ( is_wp_error( $res ) ) {
			$msg = $res->get_error_message();
		} elseif ( 200 !== wp_remote_retrieve_response_code( $res ) ) {
			$msg = 'HTTP ' . wp_remote_retrieve_response_code( $res );
		} else {
			$msg = true;
		}
		update_option( self::OPT . '_last', array( 'time' => time(), 'result' => true === $msg ? 'ok' : $msg ), false );
		return $msg;
	}

	public static function menu() {
		add_options_page( 'Novoslate Agent', 'Novoslate Agent', 'manage_options', 'novoslate-agent', array( __CLASS__, 'page' ) );
	}

	public static function register_settings() {
		register_setting(
			'novoslate_agent',
			self::OPT,
			array(
				'type'              => 'array',
				'sanitize_callback' => function ( $v ) {
					return array(
						'endpoint' => esc_url_raw( isset( $v['endpoint'] ) ? trim( $v['endpoint'] ) : '' ),
						'token'    => preg_replace( '/[^a-f0-9]/', '', strtolower( isset( $v['token'] ) ? $v['token'] : '' ) ),
					);
				},
			)
		);
	}

	public static function handle_send_now() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed' );
		}
		check_admin_referer( 'novoslate_agent_send' );
		self::send_report();
		wp_safe_redirect( admin_url( 'options-general.php?page=novoslate-agent' ) );
		exit;
	}

	public static function page() {
		$o    = self::opts();
		$last = get_option( self::OPT . '_last' );
		?>
		<div class="wrap">
			<h1>Novoslate Site Agent</h1>
			<p>Sends pending updates and version info to Novoslate Ops once a day. Version <?php echo esc_html( NSA_VERSION ); ?>, updates itself from GitHub.</p>
			<form method="post" action="options.php">
				<?php settings_fields( 'novoslate_agent' ); ?>
				<table class="form-table">
					<tr><th><label for="nsa-endpoint">Endpoint</label></th>
						<td><input id="nsa-endpoint" class="regular-text" type="url" name="<?php echo esc_attr( self::OPT ); ?>[endpoint]" value="<?php echo esc_attr( $o['endpoint'] ); ?>" placeholder="https://ops.novoslate.com/api/agent/report"></td></tr>
					<tr><th><label for="nsa-token">Token</label></th>
						<td><input id="nsa-token" class="regular-text" type="password" name="<?php echo esc_attr( self::OPT ); ?>[token]" value="<?php echo esc_attr( $o['token'] ); ?>" autocomplete="off"></td></tr>
				</table>
				<?php submit_button( 'Save and send report' ); ?>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="novoslate_agent_send">
				<?php wp_nonce_field( 'novoslate_agent_send' ); ?>
				<?php submit_button( 'Send report now', 'secondary', 'submit', false ); ?>
			</form>
			<?php if ( $last ) : ?>
				<p><strong>Last report:</strong> <?php echo esc_html( wp_date( 'M j, Y g:i a', $last['time'] ) ); ?>,
					<?php echo 'ok' === $last['result'] ? '<span style="color:#16a34a">delivered</span>' : '<span style="color:#dc2626">' . esc_html( $last['result'] ) . '</span>'; ?></p>
			<?php endif; ?>
		</div>
		<?php
	}
}

register_activation_hook( __FILE__, array( 'Novoslate_Site_Agent', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Novoslate_Site_Agent', 'deactivate' ) );
Novoslate_Site_Agent::init();

<?php
/**
 * Admin footer branding set by the connected dashboard.
 *
 * The dashboard can replace the "Thank you for creating with WordPress" line at
 * the bottom of this site's admin screens with its own name and an optional
 * link, such as "Website care by Example Agency". It is plain text and one link,
 * escaped when shown, and only appears inside wp-admin, never on the public site.
 *
 * Since 0.10.0 the dashboard can also give this plugin a display name, such as
 * "Website Care by Example Agency", used in the plugin list and the Tools menu.
 * The plugin stays listed and active like any other, its description still says
 * what it is, and the settings screen names Unstoppable Site Connector.
 *
 * It is listed under Tools > Unstoppable Site Connector, and an administrator of
 * this site can switch "Set the admin footer branding" off there, which also
 * removes it. Disconnecting removes it too.
 */

defined( 'ABSPATH' ) || exit;

final class USCN_Branding {

	const OPT      = 'uscn_branding';
	const MAX_TEXT = 120;
	const MAX_NAME = 60;

	public static function hooks() {
		add_filter( 'admin_footer_text', array( __CLASS__, 'footer' ), 99 );
	}

	/** @return array { text, url, name, time } ('' = not set). */
	public static function get() {
		$b = get_option( self::OPT );
		$b = is_array( $b ) ? $b : array();
		return array(
			'text' => isset( $b['text'] ) ? (string) $b['text'] : '',
			'url'  => isset( $b['url'] ) ? (string) $b['url'] : '',
			'name' => isset( $b['name'] ) ? (string) $b['name'] : '',
			'time' => isset( $b['time'] ) ? (int) $b['time'] : 0,
		);
	}

	/** What the dashboard compares with what it wants, so it only sends changes. */
	public static function hash() {
		$b = self::get();
		if ( '' === $b['text'] && '' === $b['name'] ) {
			return '';
		}
		return md5( $b['text'] . "\n" . $b['url'] . ( '' !== $b['name'] ? "\n" . $b['name'] : '' ) );
	}

	public static function clear() {
		delete_option( self::OPT );
	}

	public static function footer( $text ) {
		$b = self::get();
		if ( '' === $b['text'] || ! USCN_Security::allowed( 'branding' ) ) {
			return $text;
		}
		if ( $b['url'] ) {
			return '<span id="footer-thankyou"><a href="' . esc_url( $b['url'] ) . '" target="_blank" rel="noopener">' . esc_html( $b['text'] ) . '</a></span>';
		}
		return '<span id="footer-thankyou">' . esc_html( $b['text'] ) . '</span>';
	}

	/** Signed route: { text, url }. Empty text removes the branding. */
	public static function rest( WP_REST_Request $request ) {
		$ok = USCN_Security::permit( 'branding' );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$p    = $request->get_json_params();
		$p    = is_array( $p ) ? $p : array();
		$text = isset( $p['text'] ) ? sanitize_text_field( (string) $p['text'] ) : '';
		$text = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, self::MAX_TEXT ) : substr( $text, 0, self::MAX_TEXT );
		$url  = isset( $p['url'] ) ? esc_url_raw( (string) $p['url'], array( 'https', 'http' ) ) : '';
		$old  = self::get();
		// Older dashboards don't send a name: keep the one already set.
		$name = array_key_exists( 'name', $p ) ? sanitize_text_field( (string) $p['name'] ) : $old['name'];
		$name = function_exists( 'mb_substr' ) ? mb_substr( $name, 0, self::MAX_NAME ) : substr( $name, 0, self::MAX_NAME );

		$raw = get_option( self::OPT );
		$raw = is_array( $raw ) ? $raw : array(); // Keep anything else stored here (author/description set on this site).
		$raw = array_merge(
			$raw,
			array(
				'text' => $text,
				'url'  => '' === $text ? '' : $url,
				'name' => $name,
				'time' => time(),
			)
		);
		if ( '' === $text && '' === $name && empty( $raw['author'] ) && empty( $raw['description'] ) ) {
			self::clear();
		} else {
			update_option( self::OPT, $raw, false );
		}
		if ( $old['text'] !== $text || $old['url'] !== $url || $old['name'] !== $name ) {
			USCN_Activity::$actor = 'dashboard';
			USCN_Activity::log( 'connector', ( '' === $text && '' === $name ) ? 'branding_removed' : 'branding_set', trim( $text . ( '' !== $name ? ' · ' . $name : '' ), ' ·' ) );
		}
		return rest_ensure_response(
			array(
				'ok'   => true,
				'hash' => self::hash(),
			)
		);
	}
}

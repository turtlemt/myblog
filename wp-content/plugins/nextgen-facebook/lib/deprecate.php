<?php
/*
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2012-2017 Jean-Sebastien Morisset (https://surniaulula.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'These aren\'t the droids you\'re looking for...' );
}

if ( ! class_exists( 'NgfbDeprecate' ) ) {

	class NgfbDeprecate {

		private static $action_query = 'ngfb-deprecate';

		// list by install order
		private static $install_slugs = array(
			'wpsso' => array(
				'wpsso' => array(
					'name' => 'WPSSO Core',
					'base' => 'wpsso/wpsso.php',
				),
			),
			'wpsso-json' => array(
				'wpsso' => array(
					'name' => 'WPSSO Core',
					'base' => 'wpsso/wpsso.php',
				),
				'wpsso-schema-json-ld' => array(
					'name' => 'WPSSO Schema JSON-LD Markup',
					'base' => 'wpsso-schema-json-ld/wpsso-schema-json-ld.php',
				),
			),
			'wpsso-ssb' => array(
				'wpsso' => array(
					'name' => 'WPSSO Core',
					'base' => 'wpsso/wpsso.php',
				),
				'wpsso-ssb' => array(
					'name' => 'WPSSO Social Sharing Buttons',
					'base' => 'wpsso-ssb/wpsso-ssb.php',
				),
			),
		);

		// list by uninstall order
		private static $uninstall_slugs = array(
			'nextgen-facebook' => array(
				'nextgen-facebook-um' => array(
					'name' => 'NextGEN Facebook Update Manager',
					'base' => 'nextgen-facebook-um/nextgen-facebook-um.php',
				),
				'nextgen-facebook' => array(
					'name' => 'NextGEN Facebook',
					'base' => 'nextgen-facebook/nextgen-facebook.php',
				),
			),
		);

		private static $ext_names_urls = array(
			'WPSSO Mobile App Meta' => 'https://wordpress.org/plugins/wpsso-am/',
			'WPSSO Schema JSON-LD Markup' => 'https://wordpress.org/plugins/wpsso-schema-json-ld/',
			'WPSSO Organization Markup' => 'https://wordpress.org/plugins/wpsso-organization/',
			'WPSSO Place / Location and Local Business Meta' => 'https://wordpress.org/plugins/wpsso-plm/',
			'WPSSO Ratings and Reviews' => 'https://wordpress.org/plugins/wpsso-ratings-and-reviews/',
			'WPSSO Ridiculously Responsive Social Sharing Buttons' => 'https://wordpress.org/plugins/wpsso-rrssb/',
			'WPSSO Social Sharing Buttons' => 'https://wordpress.org/plugins/wpsso-ssb/',
			'WPSSO Strip Schema Microdata' => 'https://wordpress.org/plugins/wpsso-strip-schema-microdata/',
			'WPSSO Tweet a Quote' => 'https://wordpress.org/plugins/wpsso-tweet-a-quote/',
			'WPSSO User Locale Selector' => 'https://wordpress.org/plugins/wpsso-user-locale/',
		);

		public function __construct() {
			if ( ! is_admin() ) {	// just in case
				return;
			} elseif ( ! empty( $_GET[self::$action_query] ) ) {
				add_action( 'ngfb_init_plugin', array( __CLASS__, 'handle_action_query' ), PHP_INT_MAX );
			} else {
				add_filter( 'current_screen', array( __CLASS__, 'maybe_show_screen_notices' ), PHP_INT_MAX );
			}
		}

		public static function handle_action_query() {

			if ( ! class_exists( 'Ngfb' ) ) {	// just in case
				return;
			} elseif ( empty( $_GET[self::$action_query] ) ) {
				return;
			} elseif ( ! self::can_deprecate_plugin() ) {
				return;
			}

			$ngfb =& Ngfb::get_instance();
			$info = $ngfb->cf['plugin']['ngfb'];
			$action_name = SucomUtil::sanitize_key( $_GET[self::$action_query] );
			$_SERVER['REQUEST_URI'] = remove_query_arg( array( self::$action_query, NGFB_NONCE_NAME ) );

			/*
			 * Sanitation checks.
			 */
			if ( ! SucomUtil::get_wp_plugin_dir() ) {	// make sure we have a valid WP_PLUGIN_DIR constant
				$ngfb->notice->err( sprintf( __( 'The %s constant is not defined or its folder is not writable.',
					'nextgen-facebook' ), 'WP_PLUGIN_DIR' ) );
				return;
			} elseif ( empty( $_GET[ NGFB_NONCE_NAME ] ) ) {
				$ngfb->notice->err( __( 'Nonce query value is empty for deprecate action.',
					'nextgen-facebook' ) );
				return;
			} elseif ( ! wp_verify_nonce( $_GET[ NGFB_NONCE_NAME ], NgfbAdmin::get_nonce_action() ) ) {
				$ngfb->notice->err( __( 'Nonce token validation failed for deprecate action.',
					'nextgen-facebook' ) );
				return;
			} elseif ( empty( self::$install_slugs[$action_name] ) ) {
				$ngfb->notice->err( sprintf( __( 'Unknown deprecate action name "%s".',
					'nextgen-facebook' ), $action_name ) );
				return;
			}

			// includes wp_clean_plugins_cache(), activate_plugin(), deactivate_plugins(), etc.
			if ( ! function_exists( 'activate_plugin' ) ) {
				require_once trailingslashit( ABSPATH ).'wp-admin/includes/plugin.php';
			}

			/*
			 * Download and install the new plugins.
			 */
			foreach ( self::$install_slugs[$action_name] as $plugin_slug => $plugin_info ) {

				// make sure the plugin isn't already active or installed
				if ( SucomUtil::plugin_is_active( $plugin_info['base'] ) ) {
					$ngfb->notice->err( sprintf( __( 'Plugin "%s" is already active.',
						'nextgen-facebook' ), $plugin_info['name'] ) );
					return;	// stop on error
				} elseif ( SucomUtil::plugin_is_installed( $plugin_info['base'] ) ) {
					$ngfb->notice->err( sprintf( __( 'Plugin "%s" is already installed.',
						'nextgen-facebook' ), $plugin_info['name'] ) );
					return;	// stop on error
				}
				
				// use $unfiltered = true to download and install the version from wordpress.org 
				// warning: does not remove an existing plugin before extracting the zip file
				$ret = SucomUtil::download_install_slug( $plugin_slug, true );
				
				if ( is_wp_error( $ret ) ) {
					$ngfb->notice->err( sprintf( __( 'Error installing the "%1$s" plugin: %2$s',
						'nextgen-facebook' ), $plugin_info['name'], $ret->get_error_message() ) );
					return;	// stop on error
				}
			}

			// must clear the cache to read the new installed plugins
			wp_clean_plugins_cache();

			/*
			 * Activate the new plugins.
			 */
			foreach ( self::$install_slugs[$action_name] as $plugin_slug => $plugin_info ) {

				// $use_cache = false to get the new installed plugins list
				if ( ! SucomUtil::plugin_is_installed( $plugin_info['base'], false ) ) {
					$ngfb->notice->err( sprintf( __( 'Plugin "%s" is not installed.',
						'nextgen-facebook' ), $plugin_info['name'] ) );
					return;	// stop on error
				}

				$ret = activate_plugin( $plugin_info['base'], '', false, true );

				if ( is_wp_error( $ret ) ) {
					$ngfb->notice->err( sprintf( __( 'Error activating the "%1$s" plugin: %2$s',
						'nextgen-facebook' ), $plugin_info['name'], $ret->get_error_message() ) );
					return;	// stop on error
				}

				$ngfb->notice->upd( sprintf( __( 'The "%s" plugin was successfully installed and activated.',
					'nextgen-facebook' ), $plugin_info['name'] ) );
			}

			if ( ! class_exists( 'Wpsso' ) || ! defined( 'WPSSO_NOTICE_NAME' ) ) {	// just in case
				$ngfb->notice->err( __( 'The Wpsso PHP class is not loaded and/or the required WPSSO_NOTICE_NAME constant is not defined.',
					'nextgen-facebook' ) );
				return;	// stop on error
			}

			/*
			 * Deactivate and uninstall the old plugins.
			 */
			foreach( self::$uninstall_slugs[$info['slug']] as $plugin_slug => $plugin_info ) {

				// skip plugins that are not installed
				if ( ! SucomUtil::plugin_is_installed( $plugin_info['base'] ) ) {
					continue;
				}

				deactivate_plugins( array( $plugin_info['base'] ), true );	// $silent = true

				// $use_cache = false to get the new active plugins list
				if ( SucomUtil::plugin_is_active( $plugin_info['base'], false ) ) {
					$ngfb->notice->err( sprintf( __( 'Failed to deactivate the "%s" plugin.',
						'nextgen-facebook' ), $plugin_info['name'] ) );
					return;	// stop on error
				}

				$ret = delete_plugins( array( $plugin_info['base'] ) );

				if ( is_wp_error( $ret ) ) {
					$ngfb->notice->err( sprintf( __( 'Error uninstalling the "%1$s" plugin: %2$s',
						'nextgen-facebook' ), $plugin_info['name'], $ret->get_error_message() ) );
					return;	// stop on error
				}

				$ngfb->notice->upd( sprintf( __( 'The "%s" plugin was successfully deactivated and uninstalled.',
					'nextgen-facebook' ), $plugin_info['name'] ) );
			}

			$user_id = (int) get_current_user_id();
			if ( empty( $user_id ) ) {	// just in case
				return;
			}

			$ngfb->notice->save_user_notices( $user_id );			// save cached notices to user option table
			$notices = get_user_option( NGFB_NOTICE_NAME, $user_id );	// get all saved user notices
			update_user_option( $user_id, WPSSO_NOTICE_NAME, $notices );	// save notices for wpsso
			wp_redirect( $_SERVER['REQUEST_URI'] );				// reload current page with wpsso active
		}

		// only show notices on the dashboard and the settings pages
		// hooked to 'current_screen' filter, so return the $screen object
		public static function maybe_show_screen_notices( $screen ) {
			$screen_id = SucomUtil::get_screen_id( $screen );
			switch ( $screen_id ) {
				case 'dashboard':
				case ( strpos( $screen_id, '_page_ngfb-' ) !== false ? true : false ):
					if ( self::can_deprecate_plugin() ) {
						self::show_deprecate_notice();
					}
					break;
			}
			return $screen;
		}

		private static function can_deprecate_plugin() {

			if ( ! class_exists( 'Ngfb' ) ) {	// just in case
				return false;
			}

			$ngfb =& Ngfb::get_instance();

			if ( ! empty( $ngfb->avail['*']['p_dir'] ) ) {
				if ( $ngfb->debug->enabled ) {
					$ngfb->debug->log( 'exiting early: p_dir is true' );
				}
				return false;
			}

			$user_id = get_current_user_id();

			if ( empty( $user_id ) ) {
				if ( $ngfb->debug->enabled ) {
					$ngfb->debug->log( 'exiting early: current user id is empty' );
				}
				return false;
			}

			$all_times = $ngfb->util->get_all_times();
			$min_active_days = SucomUtil::get_const( 'NGFB_DEPRECATE_MIN_ACTIVE_DAYS', 3 );
			$some_time_ago = time() - ( DAY_IN_SECONDS * $min_active_days );
			$dismiss_key = __CLASS__.'-'.NgfbConfig::get_version();
			$is_dismissed = $ngfb->notice->is_dismissed( $dismiss_key, $user_id );
			$is_too_young = $all_times['ngfb_activate_time'] > $some_time_ago ? true : false;

			if ( $is_dismissed ) {
				if ( $ngfb->debug->enabled ) {
					$ngfb->debug->log( 'exiting early: deprecate notice is dismissed' );
				}
				return false;
			} elseif ( $is_too_young ) {
				if ( $ngfb->debug->enabled ) {
					$ngfb->debug->log( 'exiting early: activated less than 3 days ago' );
				}
				return false;
			} elseif ( is_multisite() ) {
				if ( $ngfb->debug->enabled ) {
					$ngfb->debug->log( 'exiting early: cannot deprecate a multisite' );
				}
				return false;
			} elseif ( ! is_admin() ) {
				if ( $ngfb->debug->enabled ) {
					$ngfb->debug->log( 'exiting early: must be in admin back-end to deprecate' );
				}
				return false;
			} elseif ( ! current_user_can( 'install_plugins' ) ) {
				if ( $ngfb->debug->enabled ) {
					$ngfb->debug->log( 'exiting early: current user not allowed to install plugins' );
				}
				return false;
			} elseif ( ! $ngfb->notice->can_dismiss() ) {
				if ( $ngfb->debug->enabled ) {
					$ngfb->debug->log( 'exiting early: using older version of wordpress that cannot dismiss notices' );
				}
				return false;
			}

			return true;
		}

		private static function show_deprecate_notice() {

			$ngfb =& Ngfb::get_instance();
			$info = $ngfb->cf['plugin']['ngfb'];
			$dashboard_url = get_dashboard_url();
			$action_urls = array();
			$notice_msg = '';
			$wpsso_name = 'WPSSO Core';
			$wpsso_link = '<a href="https://wordpress.org/plugins/wpsso/">'.$wpsso_name.'</a>';
			$wpsso_json_name = 'WPSSO Schema JSON-LD Markup';
			$wpsso_ssb_name = 'WPSSO Social Sharing Buttons';
			$wpsso_ssb_link = '<a href="https://wordpress.org/plugins/wpsso-ssb/">'.$wpsso_ssb_name.'</a>';
			$wpsso_rrssb_name = 'WPSSO Ridiculously Responsive Social Sharing Buttons';
			$wpsso_rrssb_link = '<a href="https://wordpress.org/plugins/wpsso-rrssb/">'.$wpsso_rrssb_name.'</a>';

			// create an array of submit button urls
			foreach ( self::$install_slugs as $action_name => $plugin_slugs ) {
				$action_urls[$action_name] = wp_nonce_url( add_query_arg( self::$action_query,
					$action_name, $dashboard_url ), NgfbAdmin::get_nonce_action(), NGFB_NONCE_NAME );
			}

			if ( empty( $action_urls ) ) {	// just in case
				return;
			}

			if ( empty( $ngfb->options['plugin_preserve'] ) ) {	// make sure we preserve settings on uninstall
				$ngfb->options['plugin_preserve'] = 1;
				$saved = update_option( NGFB_OPTIONS_NAME, $ngfb->options );
				if ( $saved !== true ) {	// just in case
					return;
				}
			}

			$notice_msg .= '<h2>'.sprintf( __( '%1$s has been replaced by %2$s.', 'nextgen-facebook' ), $info['name'], $wpsso_name ).'</h2>';

			$notice_msg .= '<p>';
			$notice_msg .= sprintf( __( 'The %1$s plugin is a fork / child of %2$s &mdash; they\'re both <a href="%3$s">Free and available on WordPress.org</a>, have the same author and developer, the same solid core features and code-base, but %4$s is distributed without the social sharing buttons and their related features.', 'nextgen-facebook' ), $wpsso_link, $info['name'], 'https://wordpress.org/plugins/search/wpsso/', $wpsso_name ).' ';
			$notice_msg .= sprintf( __( 'Social sharing buttons are distributed separately, as optional extensions for %1$s.', 'nextgen-facebook' ), $wpsso_name ).' ';
			$notice_msg .= sprintf( __( 'You can choose not to install any social sharing buttons, use the traditional %1$s, the latest %2$s extension, or any 3rd party social sharing buttons plugin.', 'nextgen-facebook' ), $wpsso_ssb_link, $wpsso_rrssb_link );
			$notice_msg .= '</p>';

			$notice_msg .= '<h3>'.sprintf( __( '%1$s offers several useful and optional extensions:', 'nextgen-facebook' ), $wpsso_name ).'</h3>';

			$notice_msg .= '<ul>';
			foreach ( self::$ext_names_urls as $name => $url ) {
				$notice_msg .= '<li>';
				$notice_msg .= '<a href="'.$url.'">'.$name.'</a>';
				$notice_msg .= $name === $wpsso_json_name ?
					' ('._x( 'recommended for best SEO', 'label comment',
						'nextgen-facebook' ).')' : '';
				$notice_msg .= '</li>';
			}
			$notice_msg .= '</ul>';

			$notice_msg .= '<h3>'.sprintf( __( '%1$s and %2$s settings are fully compatible:', 'nextgen-facebook' ), $info['name'], $wpsso_name ).'</h3>';

			$notice_msg .= '<p>';
			$notice_msg .= sprintf( __( 'You can deactivate and uninstall the %1$s plugin manually, then install and activate the %2$s plugin &mdash; all without making any changes to your settings.', 'nextgen-facebook' ), $info['name'], $wpsso_name ).' ';
			$notice_msg .= sprintf( __( 'If you prefer to uninstall %1$s and install %2$s automatically (recommended), you can choose one of the following actions:', 'nextgen-facebook' ), $info['name'], $wpsso_name );
			$notice_msg .= '</p>';

			if ( ! empty( $action_urls['wpsso-ssb'] ) ) {
				$notice_msg .= '<p>';
				$notice_msg .= '<input style="width:100%;" type="button" class="button-primary" value="'.sprintf( __( 'Replace %1$s by %2$s and %3$s', 'nextgen-facebook' ), $info['name'], $wpsso_name, $wpsso_ssb_name ).'" onclick="location.href=\''.esc_url( $action_urls['wpsso-ssb'] ).'\';" />';
				$notice_msg .= '</p>';
			}

			if ( ! empty( $action_urls['wpsso'] ) ) {
				$notice_msg .= '<p>';
				$notice_msg .= '<input style="width:100%;" type="button" class="button-secondary" value="'.sprintf( __( 'Replace %1$s by %2$s without the social sharing buttons', 'nextgen-facebook' ), $info['name'], $wpsso_name ).'" onclick="location.href=\''.esc_url( $action_urls['wpsso'] ).'\';" />';
				$notice_msg .= '</p>';
			}

			/*
			if ( ! empty( $action_urls['wpsso-json'] ) ) {
				$notice_msg .= '<p>';
				$notice_msg .= '<input style="width:100%;" type="button" class="button-secondary" value="'.sprintf( __( 'Replace %1$s by %2$s and %3$s (%4$s)', 'nextgen-facebook' ), $info['name'], $wpsso_name, $wpsso_json_name, _x( 'recommended', 'label comment', 'nextgen-facebook' ) ).'" '.  'onclick="location.href=\''.esc_url( $action_urls['wpsso-json'] ).'\';" />';
				$notice_msg .= '</p>';
			}
			*/

			$dismiss_key = __CLASS__.'-'.NgfbConfig::get_version();
			$ngfb->notice->inf( $notice_msg, true, $dismiss_key, MONTH_IN_SECONDS * 3 );
		}
	}

	new NgfbDeprecate();	// instantiate
}


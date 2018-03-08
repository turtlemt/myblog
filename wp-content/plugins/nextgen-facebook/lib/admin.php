<?php
/*
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2012-2017 Jean-Sebastien Morisset (https://surniaulula.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'These aren\'t the droids you\'re looking for...' );
}

if ( ! class_exists( 'NgfbAdmin' ) ) {

	class NgfbAdmin {

		protected $p;
		protected $menu_id;
		protected $menu_name;
		protected $menu_lib;
		protected $menu_ext;	// lowercase acronyn for plugin or extension
		protected $pagehook;
		protected $pageref_url;
		protected $pageref_title;

		public static $pkg = array();
		public static $readme = array();	// array for the readme of each extension

		public $form = null;
		public $lang = array();
		public $submenu = array();

		public function __construct( &$plugin ) {
			$this->p =& $plugin;

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			/*
			 * The NgfbScript add_plugin_install_script() method includes jQuery in the thickbox iframe 
			 * to add the iframe_parent arguments when the Install or Update button is clicked.
			 *
			 * These class properties are used by both the NgfbAdmin plugin_complete_actions() and 
			 * plugin_complete_redirect() methods to direct the user back to the thickbox iframe parent
			 * (aka the plugin licenses settings page) after plugin installation / activation / update.
			 */
			foreach ( array(
				'pageref_url' => 'esc_url',
				'pageref_title' => 'esc_html',
			) as $pageref => $esc_func ) {
				if ( ! empty( $_GET[$this->p->lca.'_'.$pageref] ) ) {
					$this->$pageref = call_user_func( $esc_func, urldecode( $_GET[$this->p->lca.'_'.$pageref] ) );
				}
			}

			add_action( 'activated_plugin', array( &$this, 'reset_check_head_count' ), 10 );
			add_action( 'after_switch_theme', array( &$this, 'reset_check_head_count' ), 10 );
			add_action( 'upgrader_process_complete', array( &$this, 'reset_check_head_count' ), 10 );

			add_action( 'after_switch_theme', array( &$this, 'check_tmpl_head_attributes' ), 20 );
			add_action( 'upgrader_process_complete', array( &$this, 'check_tmpl_head_attributes' ), 20 );

			if ( SucomUtil::get_const( 'DOING_AJAX' ) ) {
				// nothing to do
			} else {
				// admin_menu is run before admin_init
				add_action( 'admin_menu', array( &$this, 'load_menu_objects' ), -1000 );
				add_action( 'admin_menu', array( &$this, 'add_admin_menus' ), NGFB_ADD_MENU_PRIORITY );
				add_action( 'admin_menu', array( &$this, 'add_admin_submenus' ), NGFB_ADD_SUBMENU_PRIORITY );
				add_action( 'admin_init', array( &$this, 'register_setting' ) );

				// hook in_admin_header to allow for setting changes, plugin activation / loading, etc.
				add_action( 'in_admin_header', array( &$this, 'conflict_warnings' ), 10 );
				add_action( 'in_admin_header', array( &$this, 'required_notices' ), 20 );

				add_filter( 'current_screen', array( &$this, 'maybe_show_screen_notices' ) );
				add_filter( 'plugin_action_links', array( &$this, 'add_plugin_action_links' ), 10, 2 );
				add_filter( 'wp_redirect', array( &$this, 'profile_updated_redirect' ), -100, 2 );

				if ( is_multisite() ) {
					add_action( 'network_admin_menu', array( &$this, 'load_network_menu_objects' ), -1000 );
					add_action( 'network_admin_menu', array( &$this, 'add_network_admin_menus' ), NGFB_ADD_MENU_PRIORITY );
					add_action( 'network_admin_edit_'.NGFB_SITE_OPTIONS_NAME, array( &$this, 'save_site_options' ) );
					add_filter( 'network_admin_plugin_action_links', array( &$this, 'add_plugin_action_links' ), 10, 2 );
				}

		 		/*
				 * Provide plugin data / information from the readme.txt for additional extensions.
				 * Don't hook the 'plugins_api_result' filter if the update manager is active as it
				 * provides more complete plugin data than what's available from the readme.txt.
				 */
				if ( empty( $this->p->avail['p_ext']['um'] ) ) {	// since um v1.6.0
					add_filter( 'plugins_api_result', array( &$this, 'external_plugin_data' ), 1000, 3 );	// since wp v2.7
				}

				add_filter( 'http_request_args', array( &$this, 'add_expect_header' ), 1000, 2 );
				add_filter( 'http_request_host_is_external', array( &$this, 'maybe_allow_hosts' ), 1000, 3 );
				add_filter( 'install_plugin_complete_actions', array( &$this, 'plugin_complete_actions' ), 1000, 1 );
				add_filter( 'update_plugin_complete_actions', array( &$this, 'plugin_complete_actions' ), 1000, 1 );
				add_filter( 'wp_redirect', array( &$this, 'plugin_complete_redirect' ), 1000, 1 );
			}
		}

		public function load_network_menu_objects() {
			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}
			// some network menu pages extend the site menu pages
			$this->load_menu_objects( array( 'submenu', 'sitesubmenu' ) );
		}

		public function load_menu_objects( $menu_libs = array() ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			$pdir = $this->p->avail['*']['p_dir'];
			$aop = $this->p->check->aop( $this->p->lca, true, $pdir );

			if ( empty( $menu_libs ) ) {
				// 'setting' must follow 'submenu' to extend submenu/advanced.php
				$menu_libs = array( 'submenu', 'setting', 'profile' );
			}

			foreach ( $this->p->cf['plugin'] as $ext => $info ) {
				self::$pkg[$ext]['pdir'] = $this->p->check->aop( $ext, false, $pdir );
				self::$pkg[$ext]['aop'] = ! empty( $this->p->options['plugin_'.$ext.'_tid'] ) && $aop && 
					$this->p->check->aop( $ext, true, NGFB_UNDEF_INT ) === NGFB_UNDEF_INT ? true : false;
				self::$pkg[$ext]['type'] = self::$pkg[$ext]['aop'] ?
					_x( 'Pro', 'package type', 'nextgen-facebook' ) :
					_x( 'Free', 'package type', 'nextgen-facebook' );
				self::$pkg[$ext]['short'] = $info['short'].' '.self::$pkg[$ext]['type'];
				self::$pkg[$ext]['name'] = SucomUtil::get_pkg_name( $info['name'], self::$pkg[$ext]['type'] );
			}

			foreach ( $menu_libs as $menu_lib ) {
				foreach ( $this->p->cf['plugin'] as $ext => $info ) {
					if ( ! isset( $info['lib'][$menu_lib] ) ) {	// not all extensions have submenus
						continue;
					}
					foreach ( $info['lib'][$menu_lib] as $menu_id => $menu_name ) {
						$classname = apply_filters( $ext.'_load_lib', false, $menu_lib.'/'.$menu_id );
						if ( is_string( $classname ) && class_exists( $classname ) ) {
							if ( ! empty( $info['text_domain'] ) ) {
								$menu_name = _x( $menu_name, 'lib file description', $info['text_domain'] );
							}
							$this->submenu[$menu_id] = new $classname( $this->p, $menu_id, $menu_name, $menu_lib, $ext );
						}
					}
				}
			}
		}

		public function add_network_admin_menus() {
			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}
			$this->add_admin_menus( 'sitesubmenu' );
		}

		// add a new main menu, and its sub-menu items
		public function add_admin_menus( $menu_lib = '' ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			if ( empty( $menu_lib ) ) {
				$menu_lib = 'submenu';
			}

			$libs = $this->p->cf['*']['lib'][$menu_lib];
			$this->menu_id = key( $libs );
			$this->menu_name = $libs[$this->menu_id];
			$this->menu_lib = $menu_lib;
			$this->menu_ext = $this->p->lca;

			if ( isset( $this->submenu[$this->menu_id] ) ) {
				$menu_slug = $this->p->lca.'-'.$this->menu_id;
				$this->submenu[$this->menu_id]->add_menu_page( $menu_slug );
			}

			$sorted_menu = array();
			$unsorted_menu = array();

			foreach ( $this->p->cf['plugin'] as $ext => $info ) {

				if ( ! isset( $info['lib'][$menu_lib] ) ) {	// not all extensions have submenus
					continue;
				}

				foreach ( $info['lib'][$menu_lib] as $menu_id => $menu_name ) {

					$ksort_key = $menu_name.'-'.$menu_id;
					$parent_slug = $this->p->lca.'-'.$this->menu_id;

					if ( $ext === $this->p->lca ) {
						$unsorted_menu[] = array( $parent_slug, $menu_id, $menu_name, $menu_lib, $ext );
					} else {
						$sorted_menu[$ksort_key] = array( $parent_slug, $menu_id, $menu_name, $menu_lib, $ext );
					}
				}
			}

			ksort( $sorted_menu );

			foreach ( array_merge( $unsorted_menu, $sorted_menu ) as $key => $arg ) {
				if ( isset( $this->submenu[$arg[1]] ) ) {
					$this->submenu[$arg[1]]->add_submenu_page( $arg[0] );
				} else {
					$this->add_submenu_page( $arg[0], $arg[1], $arg[2], $arg[3], $arg[4] );
				}
			}
		}

		// add sub-menu items to existing menus (profile and setting)
		public function add_admin_submenus() {
			foreach ( array( 'profile', 'setting' ) as $menu_lib ) {

				// match wordpress behavior (users page for admins, profile page for everyone else)
				if ( $menu_lib === 'profile' && current_user_can( 'list_users' ) ) {
					$parent_slug = $this->p->cf['wp']['admin']['users']['page'];
				} else {
					$parent_slug = $this->p->cf['wp']['admin'][$menu_lib]['page'];
				}

				$sorted_menu = array();

				foreach ( $this->p->cf['plugin'] as $ext => $info ) {
					if ( ! isset( $info['lib'][$menu_lib] ) ) {	// not all extensions have submenus
						continue;
					}
					foreach ( $info['lib'][$menu_lib] as $menu_id => $menu_name ) {
						$ksort_key = $menu_name.'-'.$menu_id;
						$sorted_menu[$ksort_key] = array( $parent_slug, $menu_id, $menu_name, $menu_lib, $ext );
					}
				}

				ksort( $sorted_menu );

				foreach ( $sorted_menu as $key => $arg ) {
					if ( isset( $this->submenu[$arg[1]] ) ) {
						$this->submenu[$arg[1]]->add_submenu_page( $arg[0] );
					} else {
						$this->add_submenu_page( $arg[0], $arg[1], $arg[2], $arg[3], $arg[4] );
					}
				}
			}
		}

		/*
		 * Called by show_setting_page() and extended by the sitesubmenu classes to load site options instead.
		 */
		protected function set_form_object( $menu_ext ) {	// $menu_ext required for text_domain
			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
				$this->p->debug->log( 'setting form object for '.$menu_ext );
			}
			$def_opts = $this->p->opt->get_defaults();
			$this->form = new SucomForm( $this->p, NGFB_OPTIONS_NAME, $this->p->options, $def_opts, $menu_ext );
		}

		protected function &get_form_object( $menu_ext ) {	// $menu_ext required for text_domain
			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}
			if ( ! isset( $this->form ) ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'form object not defined' );
				}
				$this->set_form_object( $menu_ext );
			} elseif ( $this->form->get_menu_ext() !== $menu_ext ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'form object text domain does not match' );
				}
				$this->set_form_object( $menu_ext );
			}
			return $this->form;
		}

		public function register_setting() {
			register_setting( $this->p->lca.'_setting', NGFB_OPTIONS_NAME, array( &$this, 'registered_setting_sanitation' ) );
		}

		protected function add_menu_page( $menu_slug ) {
			global $wp_version;

			$page_title = self::$pkg[$this->p->lca]['short'].' &mdash; '.$this->menu_name;
			$menu_title = _x( $this->p->cf['menu']['title'], 'menu title', 'nextgen-facebook' ).' '.self::$pkg[$this->p->lca]['type'];	// pre-translated
			$cap_name = isset( $this->p->cf['wp']['admin'][$this->menu_lib]['cap'] ) ? $this->p->cf['wp']['admin'][$this->menu_lib]['cap'] : 'manage_options';
			$icon_url = version_compare( $wp_version, 3.8, '>=' ) ? 'dashicons-share' : null;
			$function = array( &$this, 'show_setting_page' );

			$this->pagehook = add_menu_page( $page_title, $menu_title, $cap_name, $menu_slug, $function, $icon_url, NGFB_MENU_ORDER );

			add_action( 'load-'.$this->pagehook, array( &$this, 'load_setting_page' ) );
		}

		protected function add_submenu_page( $parent_slug, $menu_id = '', $menu_name = '', $menu_lib = '', $menu_ext = '', $css_class = '' ) {

			if ( empty( $menu_id ) ) {
				$menu_id = $this->menu_id;
			}

			if ( empty( $menu_name ) ) {
				$menu_name = $this->menu_name;
			}

			if ( empty( $menu_lib ) ) {
				$menu_lib = $this->menu_lib;
			}

			if ( empty( $menu_ext ) ) {
				$menu_ext = $this->menu_ext;	// lowercase acronyn for plugin or extension
				if ( empty( $menu_ext ) ) {
					$menu_ext = $this->p->lca;
				}
			}

			global $wp_version;
			if ( ( $menu_lib === 'submenu' || $menu_lib === 'sitesubmenu' ) && 
				version_compare( $wp_version, 3.8, '>=' ) ) {	// wp v3.8 required for dashicons

				if ( empty( $this->p->cf['menu']['dashicons'][$menu_id] ) ) {
					if ( $menu_ext === $this->p->lca ) {
						$dashicon = 'admin-settings';	// use settings dashicon by default
					} else {
						$dashicon = 'admin-plugins';	// use plugin dashicon by default for extensions
					}
				} else {
					$dashicon = $this->p->cf['menu']['dashicons'][$menu_id];
				}
				$css_class = $this->p->lca.'-menu-item'.( $css_class ? ' '.$css_class : '' );
				$menu_title = '<div class="'.$css_class.' dashicons-before dashicons-'.$dashicon.'"></div>'.
					'<div class="'.$css_class.'">'.$menu_name.'</div>';
			} else {
				$menu_title = $menu_name;
			}
	
			$page_title = self::$pkg[$menu_ext]['short'].' &mdash; '.$menu_name;
			$cap_name = isset( $this->p->cf['wp']['admin'][$menu_lib]['cap'] ) ? $this->p->cf['wp']['admin'][$menu_lib]['cap'] : 'manage_options';
			$menu_slug = $this->p->lca.'-'.$menu_id;
			$function = array( &$this, 'show_setting_page' );

			$this->pagehook = add_submenu_page( $parent_slug, $page_title, $menu_title, $cap_name, $menu_slug, $function );

			if ( $function ) {
				add_action( 'load-'.$this->pagehook, array( &$this, 'load_setting_page' ) );
			}
		}

		// add links on the main plugins page
		public function add_plugin_action_links( $links, $plugin_base, $utm_source = 'plugin-action-links', &$tabindex = false ) {

			if ( ! isset( $this->p->cf['*']['base'][$plugin_base] ) ) {
				return $links;
			}

			$ext = $this->p->cf['*']['base'][$plugin_base];
			$info = $this->p->cf['plugin'][$ext];
			$tabindex = is_integer( $tabindex ) ? $tabindex : false;	// just in case

			foreach ( $links as $num => $val ) {
				if ( strpos( $val, '>Edit<' ) !== false ) {
					unset ( $links[$num] );
				}
			}

			if ( ! empty( $info['url']['faqs'] ) ) {
				$links[] = '<a href="'.$info['url']['faqs'].'"'.
					( $tabindex !== false ? ' tabindex="'.++$tabindex.'"' : '' ).'>'.
					_x( 'FAQs', 'plugin action link', 'nextgen-facebook' ).'</a>';
			}

			if ( ! empty( $info['url']['notes'] ) ) {
				$links[] = '<a href="'.$info['url']['notes'].'"'.
					( $tabindex !== false ? ' tabindex="'.++$tabindex.'"' : '' ).'>'.
					_x( 'Other Notes', 'plugin action link', 'nextgen-facebook' ).'</a>';
			}

			if ( ! empty( $info['url']['support'] ) && self::$pkg[$ext]['aop'] ) {
				$links[] = '<a href="'.$info['url']['support'].'"'.
					( $tabindex !== false ? ' tabindex="'.++$tabindex.'"' : '' ).'>'.
					_x( 'Pro Support', 'plugin action link', 'nextgen-facebook' ).'</a>';

			} elseif ( ! empty( $info['url']['forum'] ) ) {
				$links[] = '<a href="'.$info['url']['forum'].'"'.
					( $tabindex !== false ? ' tabindex="'.++$tabindex.'"' : '' ).'>'.
					_x( 'Community Forum', 'plugin action link', 'nextgen-facebook' ).'</a>';
			}

			if ( ! empty( $info['url']['purchase'] ) ) {
				if ( ! empty( $utm_source ) ) {
					$purchase_url = add_query_arg( 'utm_source', $utm_source, $info['url']['purchase'] );
				}
				$links[] = $this->p->msgs->get( 'pro-purchase-text', array(
					'ext' => $ext,
					'url' => $purchase_url, 
					'tabindex' => ( $tabindex !== false ? ++$tabindex : false )
				) );
			}

			return $links;
		}

		// define and disable the "Expect: 100-continue" header
		// use checks to make sure other filters aren't giving us a string or boolean
		public function add_expect_header( $req, $url ) {
			if ( ! is_array( $req ) ) {
				$req = array();
			}
			if ( ! isset( $req['headers'] ) || ! is_array( $req['headers'] ) ) {
				$req['headers'] = array();
			}
			$req['headers']['Expect'] = '';
			return $req;
		}

		public function maybe_allow_hosts( $is_allowed, $ip, $url ) {
			if ( $is_allowed ) {	// already allowed
				return $is_allowed;
			}
			if ( isset( $this->p->cf['extend'] ) ) {
				foreach ( $this->p->cf['extend'] as $host ) {
					if ( strpos( $url, $host ) === 0 ) {
						return true;
					}
				}
			}
			return $is_allowed;
		}

		/*
		 * Provide plugin data / information from the readme.txt for additional extensions.
		 */
		public function external_plugin_data( $res, $action = null, $args = null ) {

			// this filter only provides plugin data
			if ( $action !== 'plugin_information' ) {
				return $res;
			// make sure we have a slug in the request
			} elseif ( empty( $args->slug ) ) {
				return $res;
			// make sure the plugin slug is one of ours
			} elseif ( empty( $this->p->cf['*']['slug'][$args->slug] ) ) {
				return $res;
			// if the object from wordpress looks complete, return it as-is
			} elseif ( isset( $res->slug ) && $res->slug === $args->slug ) {
				return $res;
			}

			// get the extension acronym for the config
			$ext = $this->p->cf['*']['slug'][$args->slug];

			// make sure we have a config for that slug
			if ( empty( $this->p->cf['plugin'][$ext] ) ) {
				return $res;
			}

			// get plugin data from the plugin readme
			$plugin_data = $this->get_plugin_data( $ext );

			// make sure we have something to return
			if ( empty( $plugin_data ) ) {
				return $res;
			}

			// let wordpress known that this is not a wordpress.org plugin
			$plugin_data->external = true;

			return $plugin_data;
		}

		/*
		 * Get the plugin readme and convert array elements to a plugin data object.
		 */
		public function get_plugin_data( $ext, $read_cache = true ) {

			$data = new StdClass;
			$info = $this->p->cf['plugin'][$ext];
			$readme = $this->get_readme_info( $ext, $read_cache );

			// make sure we got something back
			if ( empty( $readme ) ) {
				return array();
			}

			foreach ( array(
				// readme array => plugin object
				'plugin_name' => 'name',
				'plugin_slug' => 'slug',
				'base' => 'plugin',
				'stable_tag' => 'version',
				'tested_up_to' => 'tested',
				'requires_at_least' => 'requires',
				'home' => 'homepage',
				'latest' => 'download_link',
				'author' => 'author',
				'upgrade_notice' => 'upgrade_notice',
				'last_updated' => 'last_updated',
				'sections' => 'sections',
				'remaining_content' => 'other_notes',	// added to sections
				'banners' => 'banners',
			) as $key_name => $prop_name ) {
				switch ( $key_name ) {
					case 'base':	// from plugin config
						if ( ! empty( $info[$key_name] ) ) {
							$data->$prop_name = $info[$key_name];
						}
						break;
					case 'home':	// from plugin config
						if ( ! empty( $info['url']['purchase'] ) ) {	// check for purchase url first
							$data->$prop_name = $info['url']['purchase'];
							break;
						}
						// no break - override with 'home' url from config (if one is defined)
					case 'latest':	// from plugin config
						if ( ! empty( $info['url'][$key_name] ) ) {
							$data->$prop_name = $info['url'][$key_name];
						}
						break;
					case 'banners':	// from plugin config
						if ( ! empty( $info['img'][$key_name] ) ) {
							$data->$prop_name = $info['img'][$key_name];	// array with low/high images
						}
						break;
					case 'remaining_content':
						if ( ! empty( $readme[$key_name] ) ) {
							$data->sections[$prop_name] = $readme[$key_name];
						}
						break;
					default:
						if ( ! empty( $readme[$key_name] ) ) {
							$data->$prop_name = $readme[$key_name];
						}
						break;
				}
			}
			return $data;
		}

		/*
		 * This method receives only a partial options array, so re-create a full one.
		 * WordPress handles the actual saving of the options to the database table.
		 */
		public function registered_setting_sanitation( $opts ) {

			$network = false;

			if ( ! is_array( $opts ) ) {
				add_settings_error( NGFB_OPTIONS_NAME, 'notarray', '<b>'.strtoupper( $this->p->lca ).' Error</b> : '.
					__( 'Submitted options are not an array.', 'nextgen-facebook' ), 'error' );
				return $opts;
			}

			// get default values, including css from default stylesheets
			$def_opts = $this->p->opt->get_defaults();
			$opts = SucomUtil::restore_checkboxes( $opts );
			$opts = array_merge( $this->p->options, $opts );
			$this->p->notice->trunc();	// clear all messages before sanitation checks
			$opts = $this->p->opt->sanitize( $opts, $def_opts, $network );
			$opts = apply_filters( $this->p->lca.'_save_options', $opts, NGFB_OPTIONS_NAME, $network );

			if ( empty( $this->p->options['plugin_clear_on_save'] ) ) {

				// admin url will redirect to the essential settings since we're not on a settings page
				$clear_cache_link = $this->p->util->get_admin_url( wp_nonce_url( '?'.$this->p->lca.'-action=clear_all_cache',
					NgfbAdmin::get_nonce_action(), NGFB_NONCE_NAME ), _x( 'Clear All Caches', 'submit button', 'nextgen-facebook' ) );
	
				$this->p->notice->upd( '<strong>'.__( 'Plugin settings have been saved.', 'nextgen-facebook' ).'</strong> <em>'.
					__( 'Please note that webpage content may take several days to reflect changes.', 'nextgen-facebook' ).' '.
					sprintf( __( '%s now to force a refresh.', 'nextgen-facebook' ), $clear_cache_link ).'</em>' );
			} else {
				$dismiss_key = __FUNCTION__.'_clear_all_cache';
				$this->p->util->clear_all_cache( true, null, $dismiss_key );	// can be dismissed
				$this->p->notice->upd( '<strong>'.__( 'Plugin settings have been saved.', 'nextgen-facebook' ).'</strong> '.
					sprintf( __( 'All caches have also been cleared (the %s option is enabled).', 'nextgen-facebook' ),
						$this->p->util->get_admin_url( 'advanced#sucom-tabset_plugin-tab_cache',
							_x( 'Clear All Caches on Save Settings', 'option label', 'nextgen-facebook' ) ) ) );
			}

			if ( empty( $opts['plugin_filter_content'] ) ) {
				$message_key = 'notice-content-filters-disabled';
				$dismiss_key = $message_key.'-reminder';
				$this->p->notice->warn( $this->p->msgs->get( $message_key ), true, $dismiss_key, true );	// can be dismissed
			}

			$this->check_tmpl_head_attributes();

			return $opts;
		}

		public function save_site_options() {

			$network = true;

			if ( ! $page = SucomUtil::get_request_value( 'page', 'POST' ) ) {	// uses sanitize_text_field
				$page = key( $this->p->cf['*']['lib']['sitesubmenu'] );
			}

			if ( empty( $_POST[ NGFB_NONCE_NAME ] ) ) {	// NGFB_NONCE_NAME is an md5() string
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'nonce token validation post field missing' );
				}
				wp_redirect( $this->p->util->get_admin_url( $page ) );
				exit;
			} elseif ( ! wp_verify_nonce( $_POST[ NGFB_NONCE_NAME ], NgfbAdmin::get_nonce_action() ) ) {
				$this->p->notice->err( __( 'Nonce token validation failed for network options (update ignored).', 'nextgen-facebook' ) );
				wp_redirect( $this->p->util->get_admin_url( $page ) );
				exit;
			} elseif ( ! current_user_can( 'manage_network_options' ) ) {
				$this->p->notice->err( __( 'Insufficient privileges to modify network options.', 'nextgen-facebook' ) );
				wp_redirect( $this->p->util->get_admin_url( $page ) );
				exit;
			}

			$def_opts = $this->p->opt->get_site_defaults();
			$opts = empty( $_POST[NGFB_SITE_OPTIONS_NAME] ) ? $def_opts : SucomUtil::restore_checkboxes( $_POST[NGFB_SITE_OPTIONS_NAME] );
			$opts = array_merge( $this->p->site_options, $opts );
			$this->p->notice->trunc();	// clear all messages before sanitation checks
			$opts = $this->p->opt->sanitize( $opts, $def_opts, $network );
			$opts = apply_filters( $this->p->lca.'_save_site_options', $opts, $def_opts, $network );

			update_site_option( NGFB_SITE_OPTIONS_NAME, $opts );

			$this->p->notice->upd( '<strong>'.__( 'Plugin settings have been saved.', 'nextgen-facebook' ).'</strong>' );

			wp_redirect( $this->p->util->get_admin_url( $page ).'&settings-updated=true' );

			exit;	// stop after redirect
		}

		public function load_setting_page() {

			$action_query = $this->p->lca.'-action';
			wp_enqueue_script( 'postbox' );

			if ( ! empty( $_GET[$action_query] ) ) {

				$_SERVER['REQUEST_URI'] = remove_query_arg( array( $action_query, NGFB_NONCE_NAME ) );
				$action_name = SucomUtil::sanitize_hookname( $_GET[$action_query] );

				if ( empty( $_GET[ NGFB_NONCE_NAME ] ) ) {	// NGFB_NONCE_NAME is an md5() string

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( 'nonce token validation query field missing' );
					}

				} elseif ( ! wp_verify_nonce( $_GET[ NGFB_NONCE_NAME ], NgfbAdmin::get_nonce_action() ) ) {

					$this->p->notice->err( sprintf( __( 'Nonce token validation failed for %1$s action "%2$s".',
						'nextgen-facebook' ), 'admin', $action_name ) );

				} else {

					switch ( $action_name ) {

						case 'clear_all_cache':

							$this->p->util->clear_all_cache( true );	// $clear_external = true
							break;

						case 'clear_all_cache_and_short_urls':

							$this->p->util->clear_all_cache( true, true );	// $clear_external = true
							break;

						case 'clear_metabox_prefs':

							$user_id = get_current_user_id();
							$user = get_userdata( $user_id );
							$user_name = $user->display_name;
							NgfbUser::delete_metabox_prefs( $user_id );
							$this->p->notice->upd( sprintf( __( 'Metabox layout preferences for user ID #%d "%s" have been reset.',
								'nextgen-facebook' ), $user_id, $user_name ) );
							break;

						case 'clear_hidden_notices':

							$user_id = get_current_user_id();
							$user = get_userdata( $user_id );
							$user_name = $user->display_name;
							delete_user_option( $user_id, NGFB_DISMISS_NAME );
							$this->p->notice->upd( sprintf( __( 'Hidden notices for user ID #%d "%s" have been cleared.',
								'nextgen-facebook' ), $user_id, $user_name ) );
							break;

						case 'change_show_options':

							$_SERVER['REQUEST_URI'] = remove_query_arg( array( 'show-opts' ) );

							if ( isset( $this->p->cf['form']['show_options'][$_GET['show-opts']] ) ) {
								$this->p->notice->upd( sprintf( __( 'Option preference saved &mdash; viewing "%s" by default.',
									'nextgen-facebook' ), $this->p->cf['form']['show_options'][$_GET['show-opts']] ) );
								NgfbUser::save_pref( array( 'show_opts' => $_GET['show-opts'] ) );
							}
							break;

						case 'modify_tmpl_head_attributes':

							$this->modify_tmpl_head_attributes();
							break;

						case 'reload_default_sizes':

							$opts =& $this->p->options;	// update the existing options array
							$def_opts = $this->p->opt->get_defaults();
							$img_opts = SucomUtil::preg_grep_keys( '/_img_(width|height|crop|crop_x|crop_y)$/', $def_opts );
							$opts = array_merge( $this->p->options, $img_opts );
							$this->p->opt->save_options( NGFB_OPTIONS_NAME, $opts, false );
							$this->p->notice->upd( __( 'All image dimensions have been reloaded with their default value and saved.',
								'nextgen-facebook' ) );
							break;

						default:

							do_action( $this->p->lca.'_load_setting_page_'.$action_name,
								$this->pagehook, $this->menu_id, $this->menu_name, $this->menu_lib );
							break;
					}
				}
			}

			$this->add_plugin_hooks();
			$this->add_side_meta_boxes();	// add before main metaboxes
			$this->add_meta_boxes();	// add last to move duplicate side metaboxes
		}

		// called from the add_meta_boxes() method in specific settings pages (essential, general, etc.).
		protected function maybe_show_language_notice() {

			$current_locale = SucomUtil::get_locale( 'current' );
			$default_locale = SucomUtil::get_locale( 'default' );

			if ( $current_locale && $default_locale && $current_locale !== $default_locale ) {
				$dismiss_key = $this->menu_id.'-language-notice-current-'.$current_locale.'-default-'.$default_locale;
				$this->p->notice->inf( sprintf( __( 'Please note that your current language is different from the default site language (%s).', 'nextgen-facebook' ), $default_locale ).' '.sprintf( __( 'Localized option values (%s) are used for webpages and content in that language only (not for the default language, or any other language).', 'nextgen-facebook' ), $current_locale ), true, $dismiss_key, true );
			}
		}

		protected function add_side_meta_boxes() {
			if ( ! self::$pkg[$this->p->lca]['aop'] ) {
				add_meta_box( $this->pagehook.'_purchase_pro', _x( 'Pro Version Available', 'metabox title', 'nextgen-facebook' ),
					array( &$this, 'show_metabox_purchase_pro' ), $this->pagehook, 'side_fixed' );
				add_meta_box( $this->pagehook.'_status_pro', _x( 'Pro Version Features', 'metabox title', 'nextgen-facebook' ),
					array( &$this, 'show_metabox_status_pro' ), $this->pagehook, 'side' );
				NgfbUser::reset_metabox_prefs( $this->pagehook, array( 'purchase_pro' ), '', '', true );
			}
		}

		protected function add_plugin_hooks() {
			// method is extended by each submenu page
		}

		protected function add_meta_boxes() {
			// method is extended by each submenu page
		}

		public function show_setting_page() {

			if ( ! $this->is_setting() ) {
				settings_errors( NGFB_OPTIONS_NAME );
			}

			$menu_ext = $this->menu_ext;	// lowercase acronyn for plugin or extension

			if ( empty( $menu_ext ) ) {
				$menu_ext = $this->p->lca;
			}

			$this->get_form_object( $menu_ext );

			echo '<div class="wrap" id="'.$this->pagehook.'">'."\n";
			echo '<h1>';
			echo self::$pkg[$this->menu_ext]['short'].' ';
			echo '<span class="qualifier">&ndash; ';
			echo _x( $this->p->cf['meta']['title'],	'metabox title', 'nextgen-facebook' ).' ('.$this->menu_name.')';
			echo '</span></h1>'."\n";

			if ( ! self::$pkg[$this->p->lca]['aop'] ) {
				echo '<div id="poststuff" class="metabox-holder has-right-sidebar">'."\n";
				echo '<div id="side-info-column" class="inner-sidebar">'."\n";

				do_meta_boxes( $this->pagehook, 'side_top', null );
				do_meta_boxes( $this->pagehook, 'side_fixed', null );
				do_meta_boxes( $this->pagehook, 'side', null );

				echo '</div><!-- #side-info-column -->'."\n";
				echo '<div id="post-body" class="has-sidebar">'."\n";
				echo '<div id="post-body-content" class="has-sidebar-content">'."\n";
			} else {
				echo '<div id="poststuff" class="metabox-holder no-right-sidebar">'."\n";
				echo '<div id="post-body" class="no-sidebar">'."\n";
				echo '<div id="post-body-content" class="no-sidebar-content">'."\n";
			}

			$this->show_form_content(); ?>

						</div><!-- #post-body-content -->
					</div><!-- #post-body -->
				</div><!-- #poststuff -->
			</div><!-- .wrap -->
			<script type="text/javascript">
				//<![CDATA[
				jQuery(document).ready(
					function($) {
						// close postboxes that should be closed
						$('.if-js-closed').removeClass('if-js-closed').addClass('closed');
						// postboxes setup
						postboxes.add_postbox_toggles('<?php echo $this->pagehook; ?>');
					}
				);
				//]]>
			</script>
			<?php
		}

		public function profile_updated_redirect( $url, $status ) {
			if ( strpos( $url, 'updated=' ) !== false && strpos( $url, 'wp_http_referer=' ) ) {

				// match wordpress behavior (users page for admins, profile page for everyone else)
				$menu_lib = current_user_can( 'list_users' ) ? 'users' : 'profile';
				$parent_slug = $this->p->cf['wp']['admin'][$menu_lib]['page'];
				$referer_match = '/'.$parent_slug.'?page='.$this->p->lca.'-';

				parse_str( parse_url( $url, PHP_URL_QUERY ), $parts );

				if ( strpos( $parts['wp_http_referer'], $referer_match ) ) {
					$this->p->notice->upd( __( 'Profile updated.' ) );	// green status w check mark
					$url = add_query_arg( 'updated', true, $parts['wp_http_referer'] );
				}
			}
			return $url;
		}

		protected function show_form_content() {

			if ( $this->menu_lib === 'profile' ) {

				$user_id = get_current_user_id();
				$profileuser = get_user_to_edit( $user_id );
				$current_color = get_user_option( 'admin_color', $user_id );

				if ( empty( $current_color ) ) {
					$current_color = 'fresh';
				}

				// match wordpress behavior (users page for admins, profile page for everyone else)
				$admin_url = current_user_can( 'list_users' ) ?
					$this->p->util->get_admin_url( $this->menu_id, null, 'users' ) :
					$this->p->util->get_admin_url( $this->menu_id, null, $this->menu_lib );

				echo '<form name="'.$this->p->lca.'" id="'.$this->p->lca.'_setting_form" action="user-edit.php" method="post">'."\n";
				echo '<input type="hidden" name="wp_http_referer" value="'.$admin_url.'" />'."\n";
				echo '<input type="hidden" name="action" value="update" />'."\n";
				echo '<input type="hidden" name="user_id" value="'.$user_id.'" />'."\n";
				echo '<input type="hidden" name="nickname" value="'.$profileuser->nickname.'" />'."\n";
				echo '<input type="hidden" name="email" value="'.$profileuser->user_email.'" />'."\n";
				echo '<input type="hidden" name="admin_color" value="'.$current_color.'" />'."\n";
				echo '<input type="hidden" name="rich_editing" value="'.$profileuser->rich_editing.'" />'."\n";
				echo '<input type="hidden" name="comment_shortcuts" value="'.$profileuser->comment_shortcuts.'" />'."\n";
				echo '<input type="hidden" name="admin_bar_front" value="'._get_admin_bar_pref( 'front', $user_id ).'" />'."\n";

				wp_nonce_field( 'update-user_'.$user_id );

			} elseif ( $this->menu_lib === 'setting' || $this->menu_lib === 'submenu' ) {

				echo '<form name="'.$this->p->lca.'" id="'.$this->p->lca.'_setting_form" action="options.php" method="post">'."\n";

				settings_fields( $this->p->lca.'_setting' );

			} elseif ( $this->menu_lib === 'sitesubmenu' ) {

				echo '<form name="'.$this->p->lca.'" id="'.$this->p->lca.'_setting_form" action="edit.php?action='.
					NGFB_SITE_OPTIONS_NAME.'" method="post">'."\n";
				echo '<input type="hidden" name="page" value="'.$this->menu_id.'" />';

			} else {
				return;
			}

			wp_nonce_field( NgfbAdmin::get_nonce_action(), NGFB_NONCE_NAME );	// NGFB_NONCE_NAME is an md5() string
			wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );
			wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false );

			do_meta_boxes( $this->pagehook, 'normal', null );

			do_action( $this->p->lca.'_form_content_metaboxes_'.SucomUtil::sanitize_hookname( $this->menu_id ), $this->pagehook );

			if ( $this->menu_lib === 'profile' ) {
				echo $this->get_submit_buttons( _x( 'Save All Profile Settings', 'submit button', 'nextgen-facebook' ) );
			} else {
				echo $this->get_submit_buttons();
			}

			echo '</form>', "\n";
		}

		protected function get_submit_buttons( $submit_label_transl = '' ) {

			$view_next_key = SucomUtil::next_key( NgfbUser::show_opts(), $this->p->cf['form']['show_options'] );
			$view_name_transl = _x( $this->p->cf['form']['show_options'][$view_next_key], 'option value', 'nextgen-facebook' );
			$view_label_transl = sprintf( _x( 'View %s by Default', 'submit button', 'nextgen-facebook' ), $view_name_transl );
			$using_external_cache = wp_using_ext_object_cache();

			if ( empty( $submit_label_transl ) ) {
				$submit_label_transl = _x( 'Save All Plugin Settings', 'submit button', 'nextgen-facebook' );
			}

			if ( is_multisite() ) {
				$clear_label_transl = sprintf( _x( 'Clear All Caches for Site %d',
					'submit button', 'nextgen-facebook' ), get_current_blog_id() );
			} else {
				$clear_label_transl = _x( 'Clear All Caches', 'submit button', 'nextgen-facebook' );
			}

			if ( ! $using_external_cache && $this->p->options['plugin_shortener'] !== 'none' ) {
				$clear_label_transl .= ' [*]';
			}

			$action_buttons = apply_filters( $this->p->lca.'_action_buttons', array(
				array(
					'submit' => $submit_label_transl,
					'change_show_options&show-opts='.$view_next_key => $view_label_transl,
				),
				array(
					'clear_all_cache' => $clear_label_transl,
					'clear_metabox_prefs' => _x( 'Reset Metabox Layout', 'submit button', 'nextgen-facebook' ),
					'clear_hidden_notices' => _x( 'Reset Hidden Notices', 'submit button', 'nextgen-facebook' ),
				),
			), $this->menu_id, $this->menu_name, $this->menu_lib );

			$submit_buttons = '';

			foreach ( $action_buttons as $row => $row_buttons ) {
				$css_class = $row ? 'button-secondary' : 'button-secondary button-highlight';	// highlight the first row

				foreach ( $row_buttons as $action_arg => $button_label ) {
					if ( $action_arg === 'submit' ) {
						$submit_buttons .= '<input type="'.$action_arg.'" class="button-primary" value="'.$button_label.'" />';
					} else {
						$button_url = wp_nonce_url( $this->p->util->get_admin_url( '?'.$this->p->lca.'-action='.$action_arg ),
							NgfbAdmin::get_nonce_action(), NGFB_NONCE_NAME );
						$submit_buttons .= $this->form->get_button( $button_label, $css_class, '', $button_url );
					}
				}
				$submit_buttons .= '<br/>';
			}

			$html = '<div class="submit-buttons">'.$submit_buttons;

			if ( ! $using_external_cache && $this->p->options['plugin_shortener'] !== 'none' ) {

				$settings_page_link = $this->p->util->get_admin_url( 'advanced#sucom-tabset_plugin-tab_cache',
					_x( 'Clear Short URLs on Clear All Caches', 'option label', 'nextgen-facebook' ) );

				$html .= '<p><small>[*] ';
				if ( empty( $this->p->options['plugin_clear_short_urls'] ) ) {
					$html .= sprintf( __( '%1$s option is unchecked - shortened URL cache will be preserved.',
						'nextgen-facebook' ), $settings_page_link );
				} else {
					$html .= sprintf( __( '%1$s option is checked - shortened URL cache will be refreshed.',
						'nextgen-facebook' ), $settings_page_link );
				}
				$html .= '</small></p>';
			}

			$html .= '</div>';

			return $html;
		}

		public function show_metabox_version_info() {

			$table_cols = 2;
			$label_width = '70px';

			echo '<table class="sucom-settings '.$this->p->lca.' column-metabox version-info" style="table-layout:fixed;">';
			echo '<colgroup><col style="width:'.$label_width.';"/><col/></colgroup>';	// required for chrome to display fixed table layout

			foreach ( $this->p->cf['plugin'] as $ext => $info ) {

				if ( empty( $info['version'] ) ) {	// only active extensions
					continue;
				}

				$installed_version = $info['version'];	// static value from config
				$installed_style = '';
				$stable_version = __( 'Not Available', 'nextgen-facebook' );	// default value
				$latest_version = __( 'Not Available', 'nextgen-facebook' );	// default value
				$latest_notice = '';
				$changelog_url = $info['url']['changelog'];
				$readme_info = $this->get_readme_info( $ext, true );	// $read_cache = true

				if ( ! empty( $readme_info['stable_tag'] ) ) {

					$stable_version = $readme_info['stable_tag'];
					$newer_avail = version_compare( $installed_version, $stable_version, '<' );

					if ( is_array( $readme_info['upgrade_notice'] ) ) {

						// hooked by the update manager to apply the version filter
						$upgrade_notice = apply_filters( $this->p->lca.'_readme_upgrade_notices', $readme_info['upgrade_notice'], $ext );

						reset( $upgrade_notice );

						$latest_version = key( $upgrade_notice );
						$latest_notice = $upgrade_notice[$latest_version];
					}

					/*
					 * Hooked by the update manager to check installed version against the latest version, 
					 * if a non-stable filter is selected for that plugin / extension.
					 */
					if ( apply_filters( $this->p->lca.'_newer_version_available',
						$newer_avail, $ext, $installed_version, $stable_version, $latest_version ) ) {

						$installed_style = 'style="background-color:#f00;"';	// red

					} elseif ( preg_match( '/[a-z]/', $installed_version ) ) {	// current but not stable (alpha chars in version)

						$installed_style = 'style="background-color:#ff0;"';	// yellow
					} else {
						$installed_style = 'style="background-color:#0f0;"';	// green
					}
				}

				echo '<tr><td colspan="'.$table_cols.'"><h4>'.self::$pkg[$ext]['short'].'</h4></td></tr>';

				echo '<tr><th class="version-label">'._x( 'Installed', 'option label', 'nextgen-facebook' ).':</th>
					<td class="version-number" '.$installed_style.'>'.$installed_version.'</td></tr>';

				echo '<tr><th class="version-label">'._x( 'Stable', 'option label', 'nextgen-facebook' ).':</th>
					<td class="version-number">'.$stable_version.'</td></tr>';

				echo '<tr><th class="version-label">'._x( 'Latest', 'option label', 'nextgen-facebook' ).':</th>
					<td class="version-number">'.$latest_version.'</td></tr>';

				echo '<tr><td colspan="'.$table_cols.'" class="latest-notice">'.
					( empty( $latest_notice ) ? '' : '<p><em><strong>Version '.
						$latest_version.'</strong> '.$latest_notice.'</em></p>' ).
					'<p><a href="'.$changelog_url.'">'.sprintf( __( 'View %s changelog...',
						'nextgen-facebook' ), $info['short'] ).'</a></p></td></tr>';
			}

			do_action( $this->p->lca.'_column_metabox_version_info_table_rows', $table_cols, $this->form );

			echo '</table>';
		}

		public function show_metabox_status_gpl() {

			$ext_num = 0;
			$table_cols = 3;

			echo '<table class="sucom-settings '.$this->p->lca.' column-metabox module-status">';

			/*
			 * GPL version features
			 */
			foreach ( $this->p->cf['plugin'] as $ext => $info ) {

				if ( ! isset( $info['lib']['gpl'] ) ) {
					continue;
				}

				$ext_num++;

				if ( $ext === $this->p->lca ) {	// features for this plugin
					$features = array(
						'(tool) Debug Logging Enabled' => array(
							'classname' => 'SucomDebug',
						),
						'(code) Facebook / Open Graph Meta Tags' => array(
							'status' => class_exists( $this->p->lca.'opengraph' ) ? 'on' : 'rec',
						),
						'(code) Knowledge Graph Person Markup' => array(
							'status' => $this->p->options['schema_add_home_person'] ? 'on' : 'off',
						),
						'(code) Knowledge Graph Organization Markup' => array(
							'status' => $this->p->options['schema_add_home_organization'] ? 'on' : 'off',
						),
						'(code) Knowledge Graph WebSite Markup' => array(
							'status' => $this->p->options['schema_add_home_website'] ? 'on' : 'rec',
						),
						'(code) Schema Meta Property Containers' => array(
							'status' => apply_filters( $this->p->lca.'_add_schema_noscript_array',
								$this->p->options['schema_add_noscript'] ) ? 'on' : 'off',
						),
						'(code) Twitter Card Meta Tags' => array(
							'status' => class_exists( $this->p->lca.'twittercard' ) ? 'on' : 'rec',
						),
					);
				} else {
					$features = array();
				}

				self::$pkg[$ext]['purchase'] = '';

				$features = apply_filters( $ext.'_status_gpl_features', $features, $ext, $info, self::$pkg[$ext] );

				if ( ! empty( $features ) ) {
					echo '<tr><td colspan="'.$table_cols.'">'.
						'<h4'.( $ext_num > 1 ? ' style="margin-top:10px;"' : '' ).'>'.
							$info['short'].'</h4></td></tr>';
					$this->show_plugin_status( $ext, $info, $features );
				}
			}
			echo '</table>';
		}

		public function show_metabox_status_pro() {

			$ext_num = 0;

			echo '<table class="sucom-settings '.$this->p->lca.' column-metabox module-status">';

			/*
			 * Pro version features
			 */
			foreach ( $this->p->cf['plugin'] as $ext => $info ) {

				if ( ! isset( $info['lib']['pro'] ) ) {
					continue;
				}

				$ext_num++;
				$features = array();

				self::$pkg[$ext]['purchase'] = empty( $info['url']['purchase'] ) ?
					'' : add_query_arg( 'utm_source', 'status-pro-feature', $info['url']['purchase'] );

				foreach ( $info['lib']['pro'] as $sub => $libs ) {
					if ( $sub === 'admin' )	// skip status for admin menus and tabs
						continue;
					foreach ( $libs as $id_key => $label ) {
						/*
						 * Example:
						 *	'article' => 'Item Type Article',
						 *	'article#news:no_load' => 'Item Type NewsArticle',
						 *	'article#tech:no_load' => 'Item Type TechArticle',
						 */
						list( $id, $stub, $action ) = SucomUtil::get_lib_stub_action( $id_key );
						$classname = SucomUtil::sanitize_classname( $ext.'pro'.$sub.$id, false );	// $underscore = false
						$status_off = $this->p->avail[$sub][$id] ? 'rec' : 'off';
						$features[$label] = array(
							'td_class' => self::$pkg[$ext]['aop'] ? '' : 'blank',
							'purchase' => self::$pkg[$ext]['purchase'],
							'status' => class_exists( $classname ) ?
								( self::$pkg[$ext]['aop'] ?
									'on' : $status_off ) : $status_off,
						);
					}
				}

				$features = apply_filters( $ext.'_status_pro_features', $features, $ext, $info, self::$pkg[$ext] );

				if ( ! empty( $features ) ) {
					echo '<tr><td colspan="3"><h4'.( $ext_num > 1 ? ' style="margin-top:10px;"' : '' ).'>'.
						$info['short'].'</h4></td></tr>';
					$this->show_plugin_status( $ext, $info, $features );
				}
			}
			echo '</table>';
		}

		private function show_plugin_status( &$ext = '', &$info = array(), &$features = array() ) {

			$status_info = array(
				'on' => array(
					'img' => 'green-circle.png',
					'title' => __( 'Module is enabled', 'nextgen-facebook' ),
				),
				'off' => array(
					'img' => 'gray-circle.png',
					'title' => __( 'Module is disabled / not loaded', 'nextgen-facebook' ),
				),
				'rec' => array(
					'img' => 'red-circle.png',
					'title' => __( 'Module recommended but disabled / not available', 'nextgen-facebook' ),
				),
			);

			uksort( $features, array( __CLASS__, 'sort_plugin_features' ) );

			foreach ( $features as $label => $arr ) {

				if ( isset( $arr['classname'] ) ) {
					$status_key = class_exists( $arr['classname'] ) ? 'on' : 'off';
				} elseif ( isset( $arr['constant'] ) ) {
					$status_key = SucomUtil::get_const( $arr['constant'] ) ? 'on' : 'off';
				} elseif ( isset( $arr['status'] ) ) {
					$status_key = $arr['status'];
				} else {
					$status_key = '';
				}

				if ( ! empty( $status_key ) ) {

					$td_class = empty( $arr['td_class'] ) ? '' : ' '.$arr['td_class'];
					$icon_type = preg_match( '/^\(([a-z\-]+)\) (.*)/', $label, $match ) ? $match[1] : 'admin-generic';
					$icon_title = __( 'Generic feature module', 'nextgen-facebook' );
					$label_text = empty( $match[2] ) ? $label : $match[2];
					$label_text = empty( $arr['label'] ) ? $label_text : $arr['label'];
					$purchase_url = $status_key === 'rec' && ! empty( $arr['purchase'] ) ? $arr['purchase'] : '';

					switch ( $icon_type ) {
						case 'api':
							$icon_type = 'controls-repeat';
							$icon_title = __( 'Service API module', 'nextgen-facebook' );
							break;
						case 'code':
							$icon_type = 'editor-code';
							$icon_title = __( 'Meta tag and markup module', 'nextgen-facebook' );
							break;
						case 'plugin':
							$icon_type = 'admin-plugins';
							$icon_title = __( 'Plugin integration module', 'nextgen-facebook' );
							break;
						case 'sharing':
							$icon_type = 'screenoptions';
							$icon_title = __( 'Sharing functionality module', 'nextgen-facebook' );
							break;
						case 'tool':
							$icon_type = 'admin-tools';
							$icon_title = __( 'Additional functionality module', 'nextgen-facebook' );
							break;
					}

					echo '<tr>'.
					'<td><span class="dashicons dashicons-'.$icon_type.'" title="'.$icon_title.'"></span></td>'.
					'<td class="'.trim( $td_class ).'">'.$label_text.'</td>'.
					'<td>'.
						( $purchase_url ? '<a href="'.$purchase_url.'">' : '' ).
						'<img src="'.NGFB_URLPATH.'images/'.
							$status_info[$status_key]['img'].'" width="12" height="12" title="'.
							$status_info[$status_key]['title'].'"/>'.
						( $purchase_url ? '</a>' : '' ).
					'</td>'.
					'</tr>'."\n";
				}
			}
		}

		private static function sort_plugin_features( $feature_a, $feature_b ) {
			return strcasecmp( self::feature_priority( $feature_a ),
				self::feature_priority( $feature_b ) );
		}

		private static function feature_priority( $feature ) {
			if ( strpos( $feature, '(tool)' ) === 0 ) {
				return '(10) '.$feature;
			} else {
				return $feature;
			}
		}

		public function show_metabox_purchase_pro() {

			$info =& $this->p->cf['plugin'][$this->p->lca];
			$purchase_url = empty( $info['url']['purchase'] ) ?
				'' : add_query_arg( 'utm_source', 'column-purchase-pro', $info['url']['purchase'] );

			echo '<table class="sucom-settings '.$this->p->lca.' column-metabox"><tr><td>';
			echo '<div class="column-metabox-icon">';
			echo $this->get_ext_img_icon( $this->p->lca );
			echo '</div>';
			echo '<div class="column-metabox-content has-buttons">';
			echo $this->p->msgs->get( 'column-purchase-pro' );
			echo '</div>';
			echo '<div class="column-metabox-buttons">';
			echo $this->form->get_button( _x( 'Purchase Pro Version', 'submit button', 'nextgen-facebook' ),
				'button-primary', 'column-purchase-pro', $purchase_url, true );
			echo '</div>';
			echo '</td></tr></table>';
		}

		public function show_metabox_help_support() {

			echo '<table class="sucom-settings '.$this->p->lca.' column-metabox"><tr><td>';
			$this->show_follow_icons();
			echo $this->p->msgs->get( 'column-help-support' );

			foreach ( $this->p->cf['plugin'] as $ext => $info ) {

				if ( empty( $info['version'] ) ) {	// filter out extensions that are not installed
					continue;
				}

				$links = array();

				if ( ! empty( $info['url']['faqs'] ) ) {
					$links[] = sprintf( __( 'Read the <a href="%s">Frequently Asked Questions</a>',
						'nextgen-facebook' ), $info['url']['faqs'] ).( ! empty( $info['url']['notes'] ) ?
							' '.sprintf( __( 'and <a href="%s">Other Notes</a>',
								'nextgen-facebook' ), $info['url']['notes'] ) : '' );
				}

				if ( ! empty( $info['url']['support'] ) && self::$pkg[$ext]['aop'] ) {
					$links[] = sprintf( __( 'Open a <a href="%s">Priority Support Ticket</a>',
						'nextgen-facebook' ), $info['url']['support'] ).' ('.__( 'Pro version', 'nextgen-facebook' ).')';
				} elseif ( ! empty( $info['url']['forum'] ) ) {
					$links[] = sprintf( __( 'Post in the <a href="%s">Community Support Forum</a>',
						'nextgen-facebook' ), $info['url']['forum'] ).' ('.__( 'Free version', 'nextgen-facebook' ).')';
				}

				if ( ! empty( $links ) ) {
					echo '<h4>'.$info['short'].'</h4>'."\n";
					echo '<ul><li>'.implode( '</li><li>', $links ).'</li></ul>'."\n";
				}
			}

			echo '</td></tr></table>';
		}

		public function show_metabox_rate_review() {

			echo '<table class="sucom-settings '.$this->p->lca.' column-metabox"><tr><td>';
			echo $this->p->msgs->get( 'column-rate-review' );

			foreach ( $this->p->cf['plugin'] as $ext => $info ) {

				if ( empty( $info['version'] ) ) {	// filter out extensions that are not installed
					continue;
				}

				$links = array();

				if ( ! empty( $info['url']['review'] ) ) {
					$plugin_name = '<strong>'.$info['name'].'</strong>';
					$links[] = '<a href="'.$info['url']['review'].'">'.
						sprintf( __( 'Rate %s', 'nextgen-facebook' ),
							$plugin_name ).'</a>';
				}

				if ( ! empty( $links ) ) {
					echo '<ul><li>'.implode( '</li><li>', $links ).'</li></ul>'."\n";
				}
			}

			echo '</td></tr></table>';
		}

		protected function show_follow_icons() {
			echo '<div class="follow-icons">';
			$img_size = $this->p->cf['follow']['size'];
			foreach ( $this->p->cf['follow']['src'] as $img_rel => $url ) {
				echo '<a href="'.$url.'"><img src="'.NGFB_URLPATH.$img_rel.'"
					width="'.$img_size.'" height="'.$img_size.'" border="0" /></a>';
			}
			echo '</div>';
		}

		public static function get_nonce_action() {
			$salt = __FILE__.__METHOD__.__LINE__;
			foreach ( array( 'AUTH_SALT', 'NONCE_SALT' ) as $const ) {
				$salt .= defined( $const ) ? constant( $const ) : '';
			}
			return md5( $salt );
		}

		private function is_profile( $menu_id = false ) {
			return $this->is_lib( 'profile', $menu_id );
		}

		private function is_setting( $menu_id = false ) {
			return $this->is_lib( 'setting', $menu_id );
		}

		private function is_submenu( $menu_id = false ) {
			return $this->is_lib( 'submenu', $menu_id );
		}

		private function is_sitesubmenu( $menu_id = false ) {
			return $this->is_lib( 'sitesubmenu', $menu_id );
		}

		private function is_lib( $lib_name, $menu_id = false ) {
			if ( $menu_id === false ) {
				$menu_id = $this->menu_id;
			}
			return isset( $this->p->cf['*']['lib'][$lib_name][$menu_id] ) ? true : false;
		}

		public function licenses_metabox_content( $network = false ) {

			$tabindex = 0;
			$ext_num = 0;
			$ext_total = count( $this->p->cf['plugin'] );
			$charset = get_bloginfo( 'charset' );

			echo '<table class="sucom-settings '.$this->p->lca.' licenses-metabox"
				style="padding-bottom:10px">'."\n";

			echo '<tr><td colspan="3">'.
				$this->p->msgs->get( 'info-plugin-tid'.( $network ? '-network' : '' ) ).
					'</td></tr>'."\n";

			foreach ( NgfbConfig::get_ext_sorted( true ) as $ext => $info ) {

				$ext_num++;
				$ext_links = array();
				$table_rows = array();

				if ( ! empty( $info['base'] ) ) {

					$details_url = add_query_arg( array(
						'tab' => 'plugin-information',
						'plugin' => $info['slug'],
						'TB_iframe' => 'true',
						'width' => $this->p->cf['wp']['tb_iframe']['width'],
						'height' => $this->p->cf['wp']['tb_iframe']['height']
					), is_multisite() ?
						network_admin_url( 'plugin-install.php', null ) :
						get_admin_url( null, 'plugin-install.php' ) );

					if ( SucomUtil::plugin_is_installed( $info['base'] ) ) {

						if ( SucomUtil::plugin_has_update( $info['base'] ) ) {
							$ext_links[] = '<a href="'.$details_url.'" class="thickbox" tabindex="'.++$tabindex.'">'.
								'<font color="red">'._x( 'Plugin Details and Update', 'plugin action link',
									'nextgen-facebook' ).'</font></a>';
						} else {
							$ext_links[] = '<a href="'.$details_url.'" class="thickbox" tabindex="'.++$tabindex.'">'.
								_x( 'Plugin Details', 'plugin action link', 'nextgen-facebook' ).'</a>';
						}
					} else {
						$ext_links[] = '<a href="'.$details_url.'" class="thickbox" tabindex="'.++$tabindex.'">'.
							_x( 'Plugin Details and Install', 'plugin action link', 'nextgen-facebook' ).'</a>';
					}

				} elseif ( ! empty( $info['url']['home'] ) ) {
					$ext_links[] = '<a href="'.$info['url']['home'].'" tabindex="'.++$tabindex.'">'.
						_x( 'Plugin Description', 'plugin action link', 'nextgen-facebook' ).'</a>';
				}

				if ( ! empty( $info['base'] ) ) {
					$ext_links = $this->add_plugin_action_links( $ext_links, $info['base'], 'license-action-links', $tabindex );
				}

				/*
				 * Plugin Name, Description, and Links
				 */
				$plugin_name_html = '<strong>'.$info['name'].'</strong>'.( $ext === $this->p->lca ? ' ('.__( 'Main Plugin', 'nextgen-facebook' ).')' : '' );
				$plugin_desc_html = empty( $info['desc'] ) ? '' : htmlentities( _x( $info['desc'], 'plugin description', 'nextgen-facebook' ),
					ENT_QUOTES, $charset, false );

				$table_rows['plugin_name'] = '<td colspan="2" style="width:100%;">'.
					'<p style="margin-top:10px;">'.$plugin_name_html.'</p>'.
					( empty( $plugin_desc_html ) ? '' : '<p>'.$plugin_desc_html.'</p>' ).
					( empty( $ext_links ) ? '' : '<div class="row-actions visible">'.implode( ' | ', $ext_links ).'</div>' ).
					'</td>';

				/*
				 * Plugin Authentication ID and License Information
				 */
				if ( ! empty( $info['update_auth'] ) || ! empty( $this->p->options['plugin_'.$ext.'_tid'] ) ) {

					$table_rows['plugin_tid'] = $this->form->get_th_html( sprintf( _x( '%s Authentication ID',
						'option label', 'nextgen-facebook' ), $info['short'] ), 'medium nowrap' );

					if ( $this->p->lca === $ext || self::$pkg[$this->p->lca]['aop'] ) {

						$table_rows['plugin_tid'] .= '<td width="100%">'.
							$this->form->get_input( 'plugin_'.$ext.'_tid',
								'tid mono', '', 0, '', false, ++$tabindex ).'</td>';

						if ( $network ) {

							$table_rows['site_use'] = self::get_option_site_use( 'plugin_'.$ext.'_tid', 
								$this->form, $network, true );	// th and td

						} elseif ( class_exists( 'SucomUpdate' ) ) {

							foreach ( array(
								'exp_date' => _x( 'Updates and Support Expire', 'option label', 'nextgen-facebook' ),
								'qty_used' => _x( 'Site Licenses Assigned', 'option label', 'nextgen-facebook' ),
							) as $key => $label ) {
								if ( $val = SucomUpdate::get_option( $ext, $key ) ) {
									switch ( $key ) {
										case 'exp_date':
											if ( $val === '0000-00-00 00:00:00' ) {
												$val = _x( 'Never', 'option value', 'nextgen-facebook' );
											}
											break;
									}
									$table_rows[$key] = '<th>'.$label.'</th>'.
										'<td width="100%">'.$val.'</td>';
								}
							}
						}
					} else {
						$table_rows['plugin_tid'] .= '<td class="blank">'.
							( empty( $this->p->options['plugin_'.$ext.'_tid'] ) ?
								$this->form->get_no_input( 'plugin_'.$ext.'_tid', 'tid mono' ) :
								$this->form->get_input( 'plugin_'.$ext.'_tid', 'tid mono',
									'', 0, '', false, ++$tabindex ) ).'</td>';
					}
				} else {
					$table_rows['plugin_tid'] = '<td>&nbsp;</td><td width="100%">&nbsp;</td>';
				}

				/*
				 * Dotted Line
				 */
				if ( $ext_num < $ext_total ) {
					$table_rows['dotted_line'] = '<td style="border-bottom:1px dotted #ddd; height:5px;" colspan="2"></td>';
				}

				/*
				 * Show the Table Rows
				 */
				foreach ( $table_rows as $key => $row ) {
					echo '<tr>';
					if ( $key === 'plugin_name' ) {
						echo '<td style="width:168px; padding:10px 30px 10px 10px; vertical-align:top;"'.
							' width="168" rowspan="'.count( $table_rows ).'" valign="top" align="left">'."\n";
						echo $this->get_ext_img_icon( $ext );
						echo '</td>';
					}
					echo $row;
					echo '</tr>';
				}
			}
			echo '</table>'."\n";
		}

		public function conflict_warnings() {

			if ( ! is_admin() ) { 	// just in case
				return;
			}

			$err_pre =  __( 'Plugin conflict detected', 'nextgen-facebook' ) . ' &mdash; ';
			$log_pre = 'plugin conflict detected - ';	// don't translate the debug prefix

			// PHP
			foreach ( $this->p->cf['php']['extensions'] as $php_ext => $php_label ) {
				if ( ! extension_loaded( $php_ext ) ) {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( 'php '.$php_ext.' extension is not loaded' );
					}
					$this->p->notice->err( sprintf( __( 'The PHP <a href="%1$s">%2$s extension</a> is not loaded.',
						'nextgen-facebook' ), 'https://secure.php.net/manual/en/book.'.$php_ext.'.php', $php_label ).' '.
					__( 'Please contact your hosting provider to have the missing PHP extension installed and/or enabled.',
						'nextgen-facebook' ) );
				}
			}

			// WordPress
			if ( ! get_option( 'blog_public' ) ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'blog_public option is disabled' );
				}
				$dismiss_key = 'wordpress-search-engine-visibility-disabled';
				if ( $this->p->notice->is_admin_pre_notices( $dismiss_key ) ) {	// don't bother if already dismissed
					$this->p->notice->warn( sprintf( __( 'The WordPress <a href="%s">Search Engine Visibility</a> option is set to discourage search engine and social crawlers from indexing this site. This is not compatible with the purpose of sharing content on social sites &mdash; please uncheck the option to allow search engines and social crawlers to access your content.', 'nextgen-facebook' ), get_admin_url( null, 'options-reading.php' ) ), true, $dismiss_key, MONTH_IN_SECONDS * 3 );
				}
			}

			// Yoast SEO
			if ( $this->p->avail['seo']['wpseo'] ) {
				$opts = get_option( 'wpseo_social' );
				if ( ! empty( $opts['opengraph'] ) ) {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $log_pre.'wpseo opengraph meta data option is enabled' );
					}
					$this->p->notice->err( $err_pre.sprintf( __( 'please uncheck the <strong>Add Open Graph meta data</strong> option under the <a href="%s">Yoast SEO / Social / Facebook</a> settings tab.', 'nextgen-facebook' ), get_admin_url( null, 'admin.php?page=wpseo_social#top#facebook' ) ) );
				}
				if ( ! empty( $opts['twitter'] ) ) {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $log_pre.'wpseo twitter meta data option is enabled' );
					}
					$this->p->notice->err( $err_pre.sprintf( __( 'please uncheck the <strong>Add Twitter card meta data</strong> option under the <a href="%s">Yoast SEO / Social / Twitter</a> settings tab.', 'nextgen-facebook' ), get_admin_url( null, 'admin.php?page=wpseo_social#top#twitterbox' ) ) );
				}
				if ( ! empty( $opts['googleplus'] ) ) {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $log_pre.'wpseo googleplus meta data option is enabled' );
					}
					$this->p->notice->err( $err_pre.sprintf( __( 'please uncheck the <strong>Add Google+ specific post meta data</strong> option under the <a href="%s">Yoast SEO / Social / Google+</a> settings tab.', 'nextgen-facebook' ), get_admin_url( null, 'admin.php?page=wpseo_social#top#google' ) ) );
				}
				if ( ! empty( $opts['plus-publisher'] ) ) {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $log_pre.'wpseo google plus publisher option is defined' );
					}
					$this->p->notice->err( $err_pre.sprintf( __( 'please remove the <strong>Google Publisher Page</strong> value entered under the <a href="%s">Yoast SEO / Social / Google+</a> settings tab.', 'nextgen-facebook' ), get_admin_url( null, 'admin.php?page=wpseo_social#top#google' ) ) );
				}
			}

			// SEO Ultimate
			if ( $this->p->avail['seo']['seou'] ) {
				$opts = get_option( 'seo_ultimate' );
				if ( ! empty( $opts['modules'] ) && is_array( $opts['modules'] ) ) {
					if ( array_key_exists( 'opengraph', $opts['modules'] ) && $opts['modules']['opengraph'] !== -10 ) {
						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( $log_pre.'seo ultimate opengraph module is enabled' );
						}
						$this->p->notice->err( $err_pre.sprintf( __( 'please disable the <strong>Open Graph Integrator</strong> module in the <a href="%s">SEO Ultimate Module Manager</a>.', 'nextgen-facebook' ), get_admin_url( null, 'admin.php?page=seo' ) ) );
					}
				}
			}

			// All in One SEO Pack
			if ( $this->p->avail['seo']['aioseop'] ) {
				$opts = get_option( 'aioseop_options' );
				if ( ! empty( $opts['modules']['aiosp_feature_manager_options']['aiosp_feature_manager_enable_opengraph'] ) ) {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $log_pre.'aioseop social meta feature is enabled' );
					}
					$this->p->notice->err( $err_pre.sprintf( __( 'please deactivate the <strong>Social Meta</strong> feature in the <a href="%s">All in One SEO Pack Feature Manager</a>.', 'nextgen-facebook' ), get_admin_url( null, 'admin.php?page=all-in-one-seo-pack/modules/aioseop_feature_manager.php' ) ) );
				}
				if ( isset( $opts['aiosp_google_disable_profile'] ) && empty( $opts['aiosp_google_disable_profile'] ) ) {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $log_pre.'aioseop google plus profile is enabled' );
					}
					$this->p->notice->err( $err_pre.sprintf( __( 'please check the <strong>Disable Google Plus Profile</strong> option in the <a href="%s">All in One SEO Pack General Settings</a>.', 'nextgen-facebook' ), get_admin_url( null, 'admin.php?page=all-in-one-seo-pack/aioseop_class.php' ) ) );
				}
				if ( isset( $opts['aiosp_schema_markup'] ) && ! empty( $opts['aiosp_schema_markup'] ) ) {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $log_pre.'aioseop schema markup is enabled' );
					}
					$this->p->notice->err( $err_pre.sprintf( __( 'please uncheck the <strong>Use Schema.org Markup</strong> option in the <a href="%s">All in One SEO Pack General Settings</a>.', 'nextgen-facebook' ), get_admin_url( null, 'admin.php?page=all-in-one-seo-pack/aioseop_class.php' ) ) );
				}
			}

			// The SEO Framework
			if ( $this->p->avail['seo']['autodescription'] ) {
				$the_seo_framework = the_seo_framework();
				if ( $the_seo_framework->use_og_tags() ) {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $log_pre.'autodescription open graph meta tags are enabled' );
					}
					$this->p->notice->err( $err_pre.sprintf( __( 'please uncheck the <strong>%1$s</strong> option in <a href="%2$s">The SEO Framework</a> Social Meta Settings.', 'nextgen-facebook' ), 'Output Open Graph meta tags?', get_admin_url( null, 'admin.php?page=theseoframework-settings' ) ) );
				}
				if ( $the_seo_framework->use_facebook_tags() ) {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $log_pre.'autodescription facebook meta tags are enabled' );
					}
					$this->p->notice->err( $err_pre.sprintf( __( 'please uncheck the <strong>%1$s</strong> option in <a href="%2$s">The SEO Framework</a> Social Meta Settings.', 'nextgen-facebook' ), 'Output Facebook meta tags?', get_admin_url( null, 'admin.php?page=theseoframework-settings' ) ) );
				}
				if ( $the_seo_framework->use_twitter_tags() ) {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $log_pre.'autodescription twitter meta tags are enabled' );
					}
					$this->p->notice->err( $err_pre.sprintf( __( 'please uncheck the <strong>%1$s</strong> option in <a href="%2$s">The SEO Framework</a> Social Meta Settings.', 'nextgen-facebook' ), 'Output Twitter meta tags?', get_admin_url( null, 'admin.php?page=theseoframework-settings' ) ) );
				}
				if ( $the_seo_framework->is_option_checked( 'knowledge_output' ) ) {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $log_pre.'autodescription knowledge graph is enabled' );
					}
					$this->p->notice->err( $err_pre.sprintf( __( 'please uncheck the <strong>Output Authorized Presence?</strong> option in <a href="%s">The SEO Framework</a> Schema Settings.', 'nextgen-facebook' ), get_admin_url( null, 'admin.php?page=theseoframework-settings' ) ) );
				}
				foreach ( array(
					'post_publish_time' => 'Add article:published_time to Posts',
					'page_publish_time' => 'Add article:published_time to Pages',
					'home_publish_time' => 'Add article:published_time to Home Page',
					'post_modify_time' => 'Add article:modified_time to Posts',
					'page_modify_time' => 'Add article:modified_time to Pages',
					'home_modify_time' => 'Add article:modified_time to Home Page',
				) as $key => $label ) {
					if ( $the_seo_framework->get_option( $key ) ) {
						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( $log_pre.'autodescription '.$key.' option is enabled' );
						}
						$this->p->notice->err( $err_pre.sprintf( __( 'please uncheck the <strong>%1$s</strong> option in <a href="%2$s">The SEO Framework</a> Social Meta Settings.', 'nextgen-facebook' ), $label, get_admin_url( null, 'admin.php?page=theseoframework-settings' ) ) );
					}
				}
			}

			// Squirrly SEO
			if ( $this->p->avail['seo']['sq'] ) {
				$opts = json_decode( get_option( 'sq_options' ), true );
				if ( ! empty( $opts['sq_auto_facebook'] ) ) {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $log_pre.'squirrly seo open graph meta tags are enabled' );
					}
					$this->p->notice->err( $err_pre.sprintf( __( 'please uncheck <strong>Add the Social Open Graph objects</strong> in the <a href="%s">Squirrly SEO</a> Social Media Options.', 'nextgen-facebook' ), get_admin_url( null, 'admin.php?page=sq_seo' ) ) );
				}
				if ( ! empty( $opts['sq_auto_twitter'] ) ) {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $log_pre.'squirrly seo twitter card meta tags are enabled' );
					}
					$this->p->notice->err( $err_pre.sprintf( __( 'please uncheck <strong>Add the Twitter card in your tweets</strong> in the <a href="%s">Squirrly SEO</a> Social Media Options.', 'nextgen-facebook' ), get_admin_url( null, 'admin.php?page=sq_seo' ) ) );
				}
				if ( ! empty( $opts['sq_auto_jsonld'] ) ) {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $log_pre.'squirrly seo json-ld markup is enabled' );
					}
					$this->p->notice->err( $err_pre.sprintf( __( 'please uncheck the <strong>adds the JSON-LD metas for Semantic SEO</strong> option in the <a href="%s">Squirrly SEO</a> settings.', 'nextgen-facebook' ), get_admin_url( null, 'admin.php?page=sq_seo' ) ) );
				}
			}
		}

		// only show notices on the dashboard and the settings pages
		// hooked to 'current_screen' filter, so return the $screen object
		public function maybe_show_screen_notices( $screen ) {
			$screen_id = SucomUtil::get_screen_id( $screen );
			switch ( $screen_id ) {
				case 'dashboard':
				case ( strpos( $screen_id, '_page_'.$this->p->lca.'-' ) !== false ? true : false ):
					$this->timed_notices();
					break;
			}
			return $screen;
		}

		public function timed_notices() {

			// notices are dismissible since wp v4.2
			if ( ! $this->p->notice->can_dismiss() || ! current_user_can( 'manage_options' ) ) {
				return;	// stop here
			}

			$user_id = get_current_user_id();
			$cache_md5_pre = $this->p->lca.'_';
			$cache_exp_secs = DAY_IN_SECONDS;	// only show every 24 hours for each user id
			$cache_salt = __METHOD__.'(user_id:'.$user_id.')';
			$cache_id = $cache_md5_pre.md5( $cache_salt );

			if ( get_transient( $cache_id ) ) {	// is transient value true (notice already shown)?
				return;	// stop here
			}

			$all_times = $this->p->util->get_all_times();
			$some_time_ago = time() - WEEK_IN_SECONDS;
			$this->get_form_object( $this->p->lca );

			foreach ( $this->p->cf['plugin'] as $ext => $info ) {

				$msg_id_review = 'timed-notice-'.$ext.'-plugin-review';

				if ( empty( $info['version'] ) ) {	// not installed
					continue;
				} elseif ( empty( $info['url']['review'] ) ) {	// must be hosted on wordpress.org
					continue;
				} elseif ( $this->p->notice->is_dismissed( $msg_id_review, $user_id ) ) {
					continue;
				} elseif ( ! isset( $all_times[$ext.'_activate_time'] ) ) {	// never activated
					continue;
				} elseif ( $all_times[$ext.'_activate_time'] > $some_time_ago ) {	// activated less than a week ago
					continue;
				}

				if ( ! empty( $info['url']['support'] ) && self::$pkg[$ext]['aop'] ) {
					$support_url = $info['url']['support'];
				} elseif ( ! empty( $info['url']['forum'] ) ) {
					$support_url = $info['url']['forum'];
				} else {
					$support_url = '';
				}

				$rate_plugin_button = '<div style="display:inline-block;vertical-align:top;margin:5px 10px 0 0;">'.
					$this->form->get_button( sprintf( __( 'Help us by rating the %s plugin 5 stars',
						'nextgen-facebook' ), $info['short'] ), 'button-primary dismiss-on-click', '', $info['url']['review'],
							true, false, array( 'dismiss-msg' => sprintf( __( 'Thank you for rating the %s plugin! You\'re awesome!',
								'nextgen-facebook' ), $info['short'] ) ) ).'</div>';

				$already_rated_button = '<div style="display:inline-block;vertical-align:top;margin:5px 10px 0 0;">'.
					$this->form->get_button( sprintf( __( 'I\'ve already rated the %s plugin 5 stars',
						'nextgen-facebook' ), $info['short'] ), 'button-secondary dismiss-on-click', '', '',
							false, false, array( 'dismiss-msg' => sprintf( __( 'Thank you for your earlier rating of %s! You\'re awesome!',
								'nextgen-facebook' ), $info['short'] ) ) ).'</div>';

				$notice_msg = '<div style="display:table-cell;"><p style="margin-right:20px;">'.
					$this->get_ext_img_icon( $ext ).'</p></div>'."\n";

				$notice_msg .= '<div style="display:table-cell;vertical-align:top;">';

				$notice_msg .= '<p style="font-size:1.05em;">'.
					'<b>'.__( 'Fantastic!', 'nextgen-facebook' ).'</b> '.
					sprintf( __( 'You\'ve been using <b>%s</b> for a week or more.',
						'nextgen-facebook' ), '<a href="'.$info['url']['home'].'" title="'.
							sprintf( __( 'The %s plugin description page on WordPress.org',
								'nextgen-facebook' ), $info['short'] ).'">'.$info['name'].'</a>' ).' '.
					__( 'That\'s awesome!', 'nextgen-facebook' ).'</p>';
					
				$notice_msg .= '<p style="font-size:1.05em;">'.
					sprintf( __( 'Could I ask you for a small favor? Would you rate the %s plugin on WordPress.org?',
						'nextgen-facebook' ), $info['short'] ).'</p>'; 
				$notice_msg .= '<p style="font-size:1.05em;">'.
					sprintf( __( 'Your rating will encourage us to keep improving %s and help new WordPress users find the plugin as well.',
						'nextgen-facebook' ), $info['short'] ).' :-) '.'</p>';
				
				$notice_msg .= $rate_plugin_button.$already_rated_button;
					
				$notice_msg .= '<p style="font-size:0.85em;">'.
					( empty( $support_url ) ? '' : '<a href="'.$support_url.'" class="dismiss-on-click">' ).
					sprintf( __( 'No thanks &mdash; I don\'t think %s is worth a 5 star rating and would like to offer a suggestion or report a problem.',
						'nextgen-facebook' ), $info['short'] ).
					( empty( $support_url ) ? '' : '</a>' ).
					'</p>';

				$notice_msg .= '</div>'."\n";

				$this->p->notice->log( 'inf', $notice_msg, $user_id, $msg_id_review, true, array( 'label' => false ) );

				break;	// show only one notice at a time
			}

			set_transient( $cache_id, true, $cache_exp_secs );	// only show every 24 hours for each user id
		}

		public function required_notices() {

			$pdir = $this->p->avail['*']['p_dir'];
			$version = $this->p->cf['plugin'][$this->p->lca]['version'];
			$um_info = $this->p->cf['plugin']['ngfbum'];
			$have_ext_tid = false;

			if ( $pdir === true && empty( $this->p->options['plugin_'.$this->p->lca.'_tid'] ) &&
				( empty( $this->p->options['plugin_'.$this->p->lca.'_tid:is'] ) ||
					$this->p->options['plugin_'.$this->p->lca.'_tid:is'] !== 'disabled' ) ) {
				$this->p->notice->nag( $this->p->msgs->get( 'notice-pro-tid-missing' ) );
			}

			foreach ( $this->p->cf['plugin'] as $ext => $info ) {
				if ( ! empty( $this->p->options['plugin_'.$ext.'_tid'] ) ) {
					$have_ext_tid = true;	// found at least one plugin with an auth id
					/*
					 * If the update manager is active, the version should be available.
					 * Skip individual warnings and show nag to install the update manager.
					 */
					if ( empty( $um_info['version'] ) ) {
						break;
					} else {
						if ( ! self::$pkg[$ext]['pdir'] ) {
							if ( ! empty( $info['base'] ) && ! SucomUtil::plugin_is_installed( $info['base'] ) ) {
								$this->p->notice->warn( $this->p->msgs->get( 'notice-pro-not-installed', array( 'lca' => $ext ) ) );
							} else {
								$this->p->notice->warn( $this->p->msgs->get( 'notice-pro-not-updated', array( 'lca' => $ext ) ) );
							}
						}
					}
				}
			}

			if ( $have_ext_tid === true ) {

				// if the update manager is active, the version should be available
				if ( ! empty( $um_info['version'] ) ) {

					$um_rec_version = NgfbConfig::$cf['um']['rec_version'];

					if ( version_compare( $um_info['version'], $um_rec_version, '<' ) ) {
						$this->p->notice->err( $this->p->msgs->get( 'notice-um-version-recommended',
							array( 'um_rec_version' => $um_rec_version ) ) );
					}

				// if the update manager is not active, check if installed
				} elseif ( SucomUtil::plugin_is_installed( $um_info['base'] ) ) {

					$this->p->notice->nag( $this->p->msgs->get( 'notice-um-activate-extension' ) );

				// update manager is not active or installed
				} else {
					$this->p->notice->nag( $this->p->msgs->get( 'notice-um-extension-required' ) );
				}
			}

			if ( current_user_can( 'manage_options' ) ) {
				foreach ( array( 'wp', 'php' ) as $key ) {
					if ( isset( NgfbConfig::$cf[$key]['rec_version'] ) ) {
						switch ( $key ) {
							case 'wp':
								global $wp_version;
								$app_version = $wp_version;
								break;
							case 'php':
								$app_version = phpversion();
								break;
						}
						$app_label = NgfbConfig::$cf[$key]['label'];
						$rec_version = NgfbConfig::$cf[$key]['rec_version'];

						if ( version_compare( $app_version, $rec_version, '<' ) ) {
							$warn_msg = $this->p->msgs->get( 'notice-recommend-version', array(
								'app_label' => $app_label,
								'app_version' => $app_version,
								'rec_version' => NgfbConfig::$cf[$key]['rec_version'],
								'version_url' => NgfbConfig::$cf[$key]['version_url'],
							) );
							$dismiss_key = 'notice-recommend-version-'.$this->p->lca.'-'.$version.'-'.$app_label.'-'.$app_version;
							$this->p->notice->warn( $warn_msg, true, $dismiss_key, MONTH_IN_SECONDS, true );	// $silent = true
						}
					}
				}
			}

			if ( $this->p->options['plugin_shortener'] === 'googl' && 
				! empty( $this->p->options['plugin_google_api_key'] ) &&
					empty ( $this->p->options['plugin_google_shorten'] ) ) {

				$this->p->notice->warn( sprintf( __( 'Google has been selected as your preferred URL shortening service, but the %s option is not enabled.',
					'nextgen-facebook' ), $this->p->util->get_admin_url( 'advanced#sucom-tabset_plugin-tab_apikeys',
						_x( 'URL Shortener API is Enabled', 'option label', 'nextgen-facebook' ) ) ) );
			}
		}

		public function reset_check_head_count() {
			delete_option( NGFB_POST_CHECK_NAME );
		}

		public function check_tmpl_head_attributes() {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			// only check if using the default filter name
			if ( empty( $this->p->options['plugin_head_attr_filter_name'] ) ||
				$this->p->options['plugin_head_attr_filter_name'] !== 'head_attributes' ||
					! apply_filters( $this->p->lca.'_add_schema_head_attributes', true ) ) {
				return;	// exit early
			}

			foreach ( SucomUtil::get_header_files() as $tmpl_file ) {

				$html_stripped = SucomUtil::get_stripped_php( $tmpl_file );

				if ( empty( $html_stripped ) ) {	// empty string or false
					continue;
				} elseif ( strpos( $html_stripped, '<head>' ) !== false ) {
					// skip if notices already shown
					if ( $this->p->notice->is_admin_pre_notices() ) {
						// allow warning to be dismissed until the next theme update
						$dismiss_key = 'notice-header-tmpl-no-head-attr-'.SucomUtil::get_theme_slug_version();
						$this->p->notice->warn( $this->p->msgs->get( 'notice-header-tmpl-no-head-attr' ),
							true, $dismiss_key, true );	// can be dismissed
					}
					break;
				}
			}
		}

		public function modify_tmpl_head_attributes() {

			$have_changes = false;
			$header_files = SucomUtil::get_header_files();
			$head_action_php = '<head <?php do_action( \'add_head_attributes\' ); ?'.'>>';	// breakup closing php for vim

			if ( empty( $header_files ) ) {
				$this->p->notice->err( __( 'No header templates found in the parent or child theme directories.',
					'nextgen-facebook' ) );
				return;	// exit early
			}

			foreach ( $header_files as $tmpl_file ) {

				$tmpl_base = basename( $tmpl_file );
				$backup_file = $tmpl_file.'~backup-'.date( 'Ymd-His' );
				$backup_base = basename( $backup_file );
				$html_stripped = SucomUtil::get_stripped_php( $tmpl_file );
	
				// double check in case of reloads etc.
				if ( empty( $html_stripped ) || strpos( $html_stripped, '<head>' ) === false ) {
					$this->p->notice->err( sprintf( __( 'No %1$s HTML tag found in the %2$s template.',
						'nextgen-facebook' ), '&lt;head&gt;', $tmpl_file ) );
					continue;
				}

				// make a backup of the original
				if ( ! copy( $tmpl_file, $backup_file ) ) {
					$this->p->notice->err( sprintf( __( 'Error copying %1$s to %2$s.', 'nextgen-facebook' ),
						$tmpl_file, $backup_base ) );
					continue;
				}

				$tmpl_contents = file_get_contents( $tmpl_file );
				$tmpl_contents = str_replace( '<head>', $head_action_php, $tmpl_contents );

				if ( ! $tmpl_fh = @fopen( $tmpl_file, 'wb' ) ) {
					$this->p->notice->err( sprintf( __( 'Failed to open template file %s for writing.',
						'nextgen-facebook' ), $tmpl_file ) );
					continue;
				}

				if ( fwrite( $tmpl_fh, $tmpl_contents ) ) {
					$this->p->notice->upd( sprintf( __( 'The %1$s template has been successfully modified and saved. A backup copy of the original template is available as %2$s in the same folder.', 'nextgen-facebook' ), $tmpl_file, $backup_base ) );
					$have_changes = true;
				} else {
					$this->p->notice->err( sprintf( __( 'Failed to write the %1$s template. You may need to restore the original template saved as %2$s in the same folder.', 'nextgen-facebook' ), $tmpl_file, $backup_base ) );
				}

				fclose( $tmpl_fh );
			}

			if ( $have_changes ) {
				$dismiss_key = 'notice-header-tmpl-no-head-attr-'.SucomUtil::get_theme_slug_version();
				$this->p->notice->trunc_key( $dismiss_key, 'all' );	// just in case
			}
		}

		// called from the NgfbSubmenuGeneral
		protected function add_schema_item_props_table_rows( array &$table_rows ) {

			$table_rows['schema_logo_url'] = $this->form->get_th_html( 
				'<a href="https://developers.google.com/structured-data/customize/logos">'.
				_x( 'Organization Logo URL', 'option label', 'nextgen-facebook' ).'</a>',
					'', 'schema_logo_url', array( 'is_locale' => true ) ).
			'<td>'.$this->form->get_input( SucomUtil::get_key_locale( 'schema_logo_url', $this->p->options ), 'wide' ).'</td>';

			$table_rows['schema_banner_url'] = $this->form->get_th_html( _x( 'Organization Banner URL',
				'option label', 'nextgen-facebook' ), '', 'schema_banner_url', array( 'is_locale' => true ) ).
			'<td>'.$this->form->get_input( SucomUtil::get_key_locale( 'schema_banner_url', $this->p->options ), 'wide' ).'</td>';

			$table_rows['schema_img_max'] = '<tr class="hide_in_basic">'.
				$this->form->get_th_html( _x( 'Maximum Images to Include',
				'option label', 'nextgen-facebook' ), '', 'schema_img_max' ).
			'<td>'.$this->form->get_select( 'schema_img_max', 
				range( 0, $this->p->cf['form']['max_media_items'] ), 'short', '', true ).
			( empty( $this->form->options['og_vid_prev_img'] ) ?
				'' : ' <em>'._x( 'video preview images are enabled (and included first)',
					'option comment', 'nextgen-facebook' ).'</em>' ).'</td>';

			$table_rows['schema_img'] = $this->form->get_th_html( _x( 'Schema Image Dimensions',
				'option label', 'nextgen-facebook' ), '', 'schema_img_dimensions' ).
			'<td>'.$this->form->get_input_image_dimensions( 'schema_img' ).'</td>';	// $use_opts = false

			$table_rows['schema_desc_len'] = '<tr class="hide_in_basic">'.
			$this->form->get_th_html( _x( 'Maximum Description Length',
				'option label', 'nextgen-facebook' ), '', 'schema_desc_len' ).
			'<td>'.$this->form->get_input( 'schema_desc_len', 'short' ).' '.
				_x( 'characters or less', 'option comment', 'nextgen-facebook' ).'</td>';

			$table_rows['schema_author_name'] = '<tr class="hide_in_basic">'.
			$this->form->get_th_html( _x( 'Author / Person Name Format',
				'option label', 'nextgen-facebook' ), '', 'schema_author_name' ).
			'<td>'.$this->form->get_select( 'schema_author_name', 
				$this->p->cf['form']['user_name_fields'] ).'</td>';
		}

		// called from the NgfbSubmenuGeneral
		protected function add_schema_item_types_table_rows( array &$table_rows, $tr_classes = array(), $schema_types = false ) {

			$tr_class_default = is_string( $tr_classes ) ? $tr_classes : '';

			if ( ! is_array( $schema_types ) ) {
				$schema_types = $this->p->schema->get_schema_types_select( null, true );	// $add_none = true
			}

			foreach ( array( 
				'home_index' => _x( 'Item Type for Blog Front Page', 'option label', 'nextgen-facebook' ),
				'home_page' => _x( 'Item Type for Static Front Page', 'option label', 'nextgen-facebook' ),
				'archive_page' => _x( 'Item Type for Archive Page', 'option label', 'nextgen-facebook' ),
				'user_page' => _x( 'Item Type for User / Author Page', 'option label', 'nextgen-facebook' ),
				'search_page' => _x( 'Item Type for Search Results Page', 'option label', 'nextgen-facebook' ),
			) as $type_name => $type_label ) {

				$opt_key = 'schema_type_for_'.$type_name;
				$tr_class = is_array( $tr_classes ) && isset( $tr_classes[$opt_key] ) ?
					$tr_classes[$opt_key] : $tr_class_default;

				$table_rows[$opt_key] = '<tr'.( empty( $tr_class ) ? '' : ' class="'.$tr_class.'"' ).'>'.
				$this->form->get_th_html( $type_label, '', $opt_key ).
				'<td>'.$this->form->get_select( $opt_key, $schema_types, 'schema_type' ).'</td>';
			}

			$select_by_ptn = '';
			foreach ( $this->p->util->get_post_types( 'objects' ) as $pt ) {
				$select_by_ptn .= '<p>'.$this->form->get_select( 'schema_type_for_'.$pt->name,
					$schema_types, 'schema_type' ).' for '.$pt->label.'</p>'."\n";
			}

			$type_label = _x( 'Item Type by Post Type', 'option label', 'nextgen-facebook' );
			$opt_key = 'schema_type_for_ptn';
			$tr_class = is_array( $tr_classes ) && isset( $tr_classes[$opt_key] ) ?
				$tr_classes[$opt_key] : $tr_class_default;

			$table_rows[$opt_key] = '<tr'.( empty( $tr_class ) ? '' : ' class="'.$tr_class.'"' ).'>'.
			$this->form->get_th_html( $type_label, '', $opt_key ).
			'<td>'.$select_by_ptn.'</td>';

		}

		// called from the NgfbSubmenuEssential and NgfbSubmenuGeneral
		protected function add_schema_knowledge_graph_table_rows( array &$table_rows ) {

			$table_rows['schema_knowledge_graph'] = $this->form->get_th_html( _x( 'Knowledge Graph for Home Page',
				'option label', 'nextgen-facebook' ), '', 'schema_knowledge_graph' ).
			'<td>'.
			'<p>'.$this->form->get_checkbox( 'schema_add_home_website' ).' '.
				sprintf( __( 'Include <a href="%s">WebSite Information</a> for Google Search',
					'nextgen-facebook' ), 'https://developers.google.com/structured-data/site-name' ).'</p>'.
			'<p>'.$this->form->get_checkbox( 'schema_add_home_organization' ).' '.
				sprintf( __( 'Include <a href="%s">Organization Social Profile</a>',
					'nextgen-facebook' ), 'https://developers.google.com/structured-data/customize/social-profiles' ).'</p>'.
			'<p>'.$this->form->get_checkbox( 'schema_add_home_person' ).' '.
				sprintf( __( 'Include <a href="%s">Person Social Profile</a> for the Site Owner',
					'nextgen-facebook' ), 'https://developers.google.com/structured-data/customize/social-profiles' ).'</p>'.
			'</td>';

			$edit_admin_users = SucomUtil::get_user_select( array( 'editor', 'administrator' ) );

			$table_rows['schema_home_person_id'] = $this->form->get_th_html( _x( 'User for Person Social Profile',
				'option label', 'nextgen-facebook' ), '', 'schema_home_person_id' ).
			'<td>'.$this->form->get_select( 'schema_home_person_id', $edit_admin_users, '', '', true ).'</td>';
		}

		// called from the NgfbSubmenuEssential, NgfbSubmenuAdvanced, and NgfbSitesubmenuSiteadvanced classes
		protected function add_essential_advanced_table_rows( array &$table_rows, $network = false ) {

			$table_rows['plugin_preserve'] = $this->form->get_th_html( _x( 'Preserve Settings on Uninstall',
				'option label', 'nextgen-facebook' ), '', 'plugin_preserve' ).
			'<td>'.$this->form->get_checkbox( 'plugin_preserve' ).'</td>'.
			self::get_option_site_use( 'plugin_preserve', $this->form, $network, true );

			$table_rows['plugin_debug'] = $this->form->get_th_html( _x( 'Add Hidden Debug Messages',
				'option label', 'nextgen-facebook' ), '', 'plugin_debug' ).
			'<td>'.( ! $network && SucomUtil::get_const( 'NGFB_HTML_DEBUG' ) ?
				$this->form->get_no_checkbox( 'plugin_debug' ).' <em>NGFB_HTML_DEBUG constant is true</em>' :
				$this->form->get_checkbox( 'plugin_debug' ) ).'</td>'.
			self::get_option_site_use( 'plugin_debug', $this->form, $network, true );

			if ( $network || ! $this->p->check->aop( $this->p->lca, true, $this->p->avail['*']['p_dir'] ) ) {
				$table_rows['plugin_hide_pro'] = $this->form->get_th_html( _x( 'Hide All Pro Version Options',
					'option label', 'nextgen-facebook' ), null, 'plugin_hide_pro' ).
				'<td>'.$this->form->get_checkbox( 'plugin_hide_pro' ).'</td>'.
				self::get_option_site_use( 'plugin_show_opts', $this->form, $network, true );
			} else {
				$this->form->get_hidden( 'plugin_hide_pro', 0, true );
			}

			$table_rows['plugin_show_opts'] = $this->form->get_th_html( _x( 'Options to Show by Default',
				'option label', 'nextgen-facebook' ), null, 'plugin_show_opts' ).
			'<td>'.$this->form->get_select( 'plugin_show_opts', $this->p->cf['form']['show_options'] ).'</td>'.
			self::get_option_site_use( 'plugin_show_opts', $this->form, $network, true );

			if ( ! empty( $this->p->cf['*']['lib']['shortcode'] ) ) {
				$table_rows['plugin_shortcodes'] = '<tr class="hide_in_basic">'.
				$this->form->get_th_html( _x( 'Enable Plugin Shortcode(s)',
					'option label', 'nextgen-facebook' ), '', 'plugin_shortcodes' ).
				'<td>'.$this->form->get_checkbox( 'plugin_shortcodes' ).'</td>'.
				self::get_option_site_use( 'plugin_shortcodes', $this->form, $network, true );
			}

			if ( ! empty( $this->p->cf['*']['lib']['widget'] ) ) {
				$table_rows['plugin_widgets'] = '<tr class="hide_in_basic">'.
				$this->form->get_th_html( _x( 'Enable Plugin Widget(s)',
					'option label', 'nextgen-facebook' ), '', 'plugin_widgets' ).
				'<td>'.$this->form->get_checkbox( 'plugin_widgets' ).'</td>'.
				self::get_option_site_use( 'plugin_widgets', $this->form, $network, true );
			}
		}

		public static function get_option_site_use( $name, $form, $network = false, $enabled = false ) {
			if ( $network ) {
				return $form->get_th_html( _x( 'Site Use',
					'option label (very short)', 'nextgen-facebook' ),
						'site_use' ).( $enabled || self::$pkg['ngfb']['aop'] ?
					'<td class="site_use">'.$form->get_select( $name.':use',
						NgfbConfig::$cf['form']['site_option_use'], 'site_use' ).'</td>' :
					'<td class="blank site_use">'.$form->get_select( $name.':use',
						NgfbConfig::$cf['form']['site_option_use'], 'site_use', '', true, true ).'</td>' );
			} else {
				return '';
			}
		}

		public function get_readme_info( $ext, $read_cache = true ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->log_args( array( 
					'ext' => $ext,
					'read_cache' => $read_cache,
				) );
			}

			$file_name = 'readme.txt';
			$file_key = SucomUtil::sanitize_hookname( $file_name );	// readme.txt -> readme_txt
			$file_dir = SucomUtil::get_const( strtoupper( $ext ).'_PLUGINDIR' );
			$file_local = $file_dir ? trailingslashit( $file_dir ).$file_name : false;
			$file_remote = isset( $this->p->cf['plugin'][$ext]['url'][$file_key] ) ? 
				$this->p->cf['plugin'][$ext]['url'][$file_key] : false;

			static $cache_exp_secs = null;
			$cache_md5_pre = $this->p->lca.'_';
			if ( ! isset( $cache_exp_secs ) ) {
				$cache_exp_filter = $this->p->lca.'_cache_expire_'.$file_key;	// 'ngfb_cache_expire_readme_txt'
				$cache_exp_secs = (int) apply_filters( $cache_exp_filter, DAY_IN_SECONDS );
			}
			$cache_salt = __METHOD__.'(ext:'.$ext.')';
			$cache_id = $cache_md5_pre.md5( $cache_salt );

			$readme_info = false;
			$readme_content = false;
			$readme_from_url = false;

			if ( $cache_exp_secs > 0 ) {
				if ( $read_cache ) {
					$readme_info = get_transient( $cache_id );
					if ( is_array( $readme_info ) ) {
						return $readme_info;	// stop here
					}
				}
				if ( $file_remote && strpos( $file_remote, '://' ) ) {
					// clear the cache first if reading the cache is disabled
					if ( ! $read_cache ) {
						$this->p->cache->clear( $file_remote );
					}
					$readme_from_url = true;
					$readme_content = $this->p->cache->get( $file_remote, 'raw', 'file', $cache_exp_secs );
				}
			} else {
				delete_transient( $cache_id );	// just in case
			}

			if ( empty( $readme_content ) ) {
				if ( $file_local && file_exists( $file_local ) && $fh = @fopen( $file_local, 'rb' ) ) {
					$readme_from_url = false;
					$readme_content = fread( $fh, filesize( $file_local ) );
					fclose( $fh );
				}
			}

			if ( empty( $readme_content ) ) {
				$readme_info = array();	// save an empty array
			} else {
				$parser = new SuextParseReadme( $this->p->debug );
				$readme_info = $parser->parse_readme_contents( $readme_content );
	
				// remove possibly inaccurate information from local file
				if ( ! $readme_from_url && is_array( $readme_info ) ) {
					foreach ( array( 'stable_tag', 'upgrade_notice' ) as $key ) {
						unset ( $readme_info[$key] );
					}
				}
			}

			// save the parsed readme to the transient cache
			if ( $cache_exp_secs > 0 ) {
				set_transient( $cache_id, $readme_info, $cache_exp_secs );
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'readme_info saved to transient cache for '.$cache_exp_secs.' seconds' );
				}
			}

			return is_array( $readme_info ) ? $readme_info : array();	// just in case
		}

		public function get_config_url_content( $ext, $file_name, $cache_exp_secs = null ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->log_args( array( 
					'ext' => $ext,
					'file_name' => $file_name,
					'cache_exp_secs' => $cache_exp_secs,
				) );
			}

			$file_name = SucomUtil::sanitize_file_path( $file_name );
			$file_key = SucomUtil::sanitize_hookname( basename( $file_name ) );	// html/setup.html -> setup_html
			$file_dir = SucomUtil::get_const( strtoupper( $ext ).'_PLUGINDIR' );
			$file_local = $file_dir ? trailingslashit( $file_dir ).$file_name : false;
			$file_remote = isset( $this->p->cf['plugin'][$ext]['url'][$file_key] ) ? 
				$this->p->cf['plugin'][$ext]['url'][$file_key] : false;

			if ( $cache_exp_secs === null ) {
				$cache_exp_secs = WEEK_IN_SECONDS;
			}

			$cache_exp_filter = $this->p->lca.'_cache_expire_'.$file_key;	// 'ngfb_cache_expire_setup_html'
			$cache_exp_secs = (int) apply_filters( $cache_exp_filter, $cache_exp_secs );
			$cache_content = false;

			if ( $cache_exp_secs > 0 ) {
				if ( $file_remote && strpos( $file_remote, '://' ) ) {
					$cache_content = $this->p->cache->get( $file_remote, 'raw', 'file', $cache_exp_secs );
				}
			}

			if ( empty( $cache_content ) ) {
				if ( $file_local && file_exists( $file_local ) && $fh = @fopen( $file_local, 'rb' ) ) {
					$cache_content = fread( $fh, filesize( $file_local ) );
					fclose( $fh );
				}
			}

			return $cache_content;
		}

		public function plugin_complete_actions( $actions ) {
			if ( ! empty( $this->pageref_url ) && ! empty( $this->pageref_title ) ) {
				foreach ( $actions as $action => &$html ) {
					switch ( $action ) {
						case 'plugins_page':
							$html = '<a href="'.$this->pageref_url.'" target="_parent">'.
								sprintf( __( 'Return to %s', 'nextgen-facebook' ), $this->pageref_title ).'</a>';
							break;
						default:
							if ( preg_match( '/^(.*href=")([^"]+)(".*)$/', $html, $matches ) ) {
								$url = add_query_arg( array(
									$this->p->lca.'_pageref_url' => urlencode( $this->pageref_url ),
									$this->p->lca.'_pageref_title' => urlencode( $this->pageref_title ),
								), $matches[2] );
								$html = $matches[1].$url.$matches[3];
							}
							break;
					}
				}
			}
			return $actions;
		}

		public function plugin_complete_redirect( $url ) {
			if ( strpos( $url, '?activate=true' ) ) {
				if ( ! empty( $this->pageref_url ) ) {
					$this->p->notice->upd( __( 'Plugin <strong>activated</strong>.' ) );	// green status w check mark
					$url = $this->pageref_url;
				}
			}
			return $url;
		}

		public function get_ext_img_icon( $ext ) {
			// default transparent 1px image
			$img_src = 'src="data:image/gif;base64,R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw=="';
			if ( ! empty( $this->p->cf['plugin'][$ext]['img']['icons'] ) ) {
				$icons = $this->p->cf['plugin'][$ext]['img']['icons'];
				if ( ! empty( $icons['low'] ) ) {
					$img_src = 'src="'.$icons['low'].'"';
				}
				if ( ! empty( $icons['high'] ) ) {
					$img_src .= ' srcset="'.$icons['high'].' 256w"';
				}
			}
			return '<img '.$img_src.' width="128" height="128" />';
		}
	}
}


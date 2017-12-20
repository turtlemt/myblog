<?php
/*
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2012-2017 Jean-Sebastien Morisset (https://surniaulula.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'These aren\'t the droids you\'re looking for...' );
}

if ( ! class_exists( 'NgfbSharing' ) ) {

	class NgfbSharing {

		protected $p;
		protected $website = array();
		protected $buttons_for_type = array();		// cache for have_buttons_for_type()
		protected $post_buttons_disabled = array();	// cache for is_post_buttons_disabled()

		public static $sharing_css_name = '';
		public static $sharing_css_file = '';
		public static $sharing_css_url = '';

		public static $cf = array(
			'opt' => array(				// options
				'defaults' => array(
					/*
					 * Advanced Settings
					 */
					// Cache Settings Tab
					'plugin_sharing_buttons_cache_exp' => WEEK_IN_SECONDS,	// Sharing Buttons HTML Cache Expiry (7 days)
					'plugin_social_file_cache_exp' => 0,			// Get Social JS Files Cache Expiry
					/*
					 * Sharing Buttons
					 */
					// Include Buttons Tab
					'buttons_on_index' => 0,
					'buttons_on_front' => 0,
					'buttons_add_to_post' => 1,
					'buttons_add_to_page' => 1,
					'buttons_add_to_attachment' => 1,
					// Buttons Position Tab
					'buttons_pos_content' => 'bottom',
					'buttons_pos_excerpt' => 'bottom',
					// Buttons Presets Tab
					'buttons_preset_content' => '',
					'buttons_preset_excerpt' => '',
					'buttons_preset_admin_edit' => 'small_share_count',
					'buttons_preset_sidebar' => 'large_share_vertical',
					'buttons_preset_shortcode' => '',
					'buttons_preset_widget' => '',
					// Advanced Tab
					'buttons_force_prot' => '',
					/*
					 * Sharing Styles
					 */
					'buttons_use_social_style' => 1,
					'buttons_enqueue_social_style' => 1,
					'buttons_css_sharing' => '',		// all buttons
					'buttons_css_content' => '',		// post/page content
					'buttons_css_excerpt' => '',		// post/page excerpt
					'buttons_css_admin_edit' => '',
					'buttons_css_sidebar' => '',
					'buttons_css_shortcode' => '',
					'buttons_css_widget' => '',
					'buttons_js_sidebar' => '/* Save an empty style text box to reload the default javascript */
jQuery("#ngfb-sidebar").mouseenter( function(){ 
	jQuery("#ngfb-sidebar-buttons").css({
		display:"block",
		width:"auto",
		height:"auto",
		overflow:"visible",
		"border-style":"solid",
	}); } );
jQuery("#ngfb-sidebar-header").click( function(){ 
	jQuery("#ngfb-sidebar-buttons").toggle(); } );',
				),	// end of defaults
				'site_defaults' => array(
					'plugin_sharing_buttons_cache_exp' => WEEK_IN_SECONDS,	// Sharing Buttons HTML Cache Expiry (7 days)
					'plugin_sharing_buttons_cache_exp:use' => 'default',
					'plugin_social_file_cache_exp' => 0,			// Get Social JS Files Cache Expiry
					'plugin_social_file_cache_exp:use' => 'default',
				),	// end of site defaults
			),
		);

		public function __construct( &$plugin ) {
			$this->p =& $plugin;

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark( 'sharing action / filter setup' );	// begin timer
			}

			self::$sharing_css_name = 'sharing-styles-id-'.get_current_blog_id().'.min.css';
			self::$sharing_css_file = NGFB_CACHEDIR.self::$sharing_css_name;
			self::$sharing_css_url = NGFB_CACHEURL.self::$sharing_css_name;

			$this->set_objects();

			add_action( 'wp_enqueue_scripts', array( &$this, 'wp_enqueue_styles' ) );
			add_action( 'wp_head', array( &$this, 'show_head' ), NGFB_HEAD_PRIORITY );
			add_action( 'wp_footer', array( &$this, 'show_footer' ), NGFB_FOOTER_PRIORITY );

			if ( $this->have_buttons_for_type( 'content' ) ) {
				$this->add_buttons_filter( 'the_content' );
			}

			if ( $this->have_buttons_for_type( 'excerpt' ) ) {
				$this->add_buttons_filter( 'get_the_excerpt' );
				$this->add_buttons_filter( 'the_excerpt' );
			}

			$this->p->util->add_plugin_filters( $this, array( 
				'get_defaults' => 1,
				'get_site_defaults' => 1,
				'get_md_defaults' => 1,
			) );

			$this->p->util->add_plugin_actions( $this, array( 
				'text_filter_before' => 1,
				'text_filter_after' => 1,
			) );

			if ( is_admin() ) {
				if ( $this->have_buttons_for_type( 'admin_edit' ) ) {
					add_action( 'add_meta_boxes', array( &$this, 'add_post_buttons_metabox' ) );
				}

				$this->p->util->add_plugin_actions( $this, array( 
					'load_setting_page_reload_default_sharing_styles' => 4,
				) );

				$this->p->util->add_plugin_filters( $this, array( 
					'save_options' => 3,			// update the sharing css file
					'option_type' => 2,			// identify option type for sanitation
					'post_custom_meta_tabs' => 3,		// $tabs, $mod, $metabox_id
					'post_cache_transient_keys' => 4,	// clear transients on post save
					'messages_info' => 2,
					'messages_tooltip' => 2,
					'messages_tooltip_plugin' => 2,
					'settings_page_custom_style_css' => 1,
				) );

				$this->p->util->add_plugin_filters( $this, array( 
					'status_gpl_features' => 4,		// include sharing, shortcode, and widget status
					'status_pro_features' => 4,		// include social file cache status
				), 10, 'ngfb' );				// hook into the extension name instead
			}

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark( 'sharing action / filter setup' );	// end timer
			}
		}

		private function set_objects() {
			foreach ( $this->p->cf['plugin']['ngfb']['lib']['website'] as $id => $name ) {
				$classname = NgfbConfig::load_lib( false, 'website/'.$id, 'ngfbwebsite'.$id );
				if ( $classname !== false && class_exists( $classname ) ) {
					$this->website[$id] = new $classname( $this->p );
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $classname.' class loaded' );
					}
				}
			}
		}

		public function filter_get_defaults( $def_opts ) {

			$lca = $this->p->cf['lca'];
			$def_opts = array_merge( $def_opts, self::$cf['opt']['defaults'] );
			/*
			 * Add options using a key prefix array and post type names.
			 */
			$def_opts = $this->p->util->add_ptns_to_opts( $def_opts, 'buttons_add_to', 1 );
			$rel_url_path = parse_url( NGFB_URLPATH, PHP_URL_PATH );	// returns a relative URL
			$tabs = apply_filters( $lca.'_sharing_styles_tabs', $this->p->cf['sharing']['styles'] );

			foreach ( $tabs as $id => $name ) {
				$buttons_css_file = NGFB_PLUGINDIR.'css/'.$id.'-buttons.css';

				// css files are only loaded once (when variable is empty) into defaults to minimize disk i/o
				if ( empty( $def_opts['buttons_css_'.$id] ) ) {
					if ( ! file_exists( $buttons_css_file ) ) {
						continue;
					} elseif ( ! $fh = @fopen( $buttons_css_file, 'rb' ) ) {
						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( 'failed to open the css file '.self::$buttons_css_file.' for reading' );
						}
						if ( is_admin() ) {
							$this->p->notice->err( sprintf( __( 'Failed to open the css file %s for reading.',
								'nextgen-facebook' ), $buttons_css_file ) );
						}
					} else {
						$buttons_css_data = fread( $fh, filesize( $buttons_css_file ) );
						fclose( $fh );
						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( 'read css file '.$buttons_css_file );
						}
						foreach ( array( 'plugin_url_path' => $rel_url_path ) as $macro => $value ) {
							$buttons_css_data = preg_replace( '/%%'.$macro.'%%/', $value, $buttons_css_data );
						}
						$def_opts['buttons_css_'.$id] = $buttons_css_data;
					}
				}
			}
			return $def_opts;
		}

		public function filter_get_site_defaults( $site_def_opts ) {
			return array_merge( $site_def_opts, self::$cf['opt']['site_defaults'] );
		}

		public function filter_get_md_defaults( $md_defs ) {
			return array_merge( $md_defs, array(
				'email_title' => '',		// Email Subject
				'email_desc' => '',		// Email Message
				'twitter_desc' => '',		// Tweet Text
				'pin_desc' => '',		// Pinterest Caption Text
				'tumblr_img_desc' => '',	// Tumblr Image Caption
				'tumblr_vid_desc' => '',	// Tumblr Video Caption
				'buttons_disabled' => 0,	// Disable Sharing Buttons
			) );
		}

		public function filter_save_options( $opts, $options_name, $network ) {
			// update the combined and minimized social stylesheet
			if ( $network === false ) {
				$this->update_sharing_css( $opts );
			}
			return $opts;
		}

		public function filter_option_type( $type, $key ) {
			if ( ! empty( $type ) ) {
				return $type;
			}
			switch ( $key ) {
				// integer options that must be 1 or more (not zero)
				case 'stumble_badge':
				case ( preg_match( '/_order$/', $key ) ? true : false ):
					return 'pos_int';
					break;
				// text strings that can be blank
				case 'buttons_force_prot':
				case 'gp_expandto':
				case 'pin_desc':
				case 'tumblr_img_desc':
				case 'tumblr_vid_desc':
				case 'twitter_desc':
					return 'ok_blank';
					break;
				// options that cannot be blank
				case 'fb_platform': 
				case 'fb_script_loc': 
				case 'fb_lang': 
				case 'fb_button': 
				case 'fb_markup': 
				case 'fb_layout': 
				case 'fb_font': 
				case 'fb_colorscheme': 
				case 'fb_action': 
				case 'fb_share_markup': 
				case 'fb_share_layout': 
				case 'fb_share_size': 
				case 'gp_lang': 
				case 'gp_action': 
				case 'gp_size': 
				case 'gp_annotation': 
				case 'gp_expandto': 
				case 'twitter_count': 
				case 'twitter_size': 
				case 'linkedin_counter':
				case 'managewp_type':
				case 'pin_button_lang':
				case 'pin_button_shape':
				case 'pin_button_color':
				case 'pin_button_height':
				case 'pin_count_layout':
				case 'pin_caption':
				case 'tumblr_button_style':
				case 'tumblr_caption':
				case ( strpos( $key, 'buttons_pos_' ) === 0 ? true : false ):
				case ( preg_match( '/^[a-z]+_script_loc$/', $key ) ? true : false ):
					return 'not_blank';
					break;
			}
			return $type;
		}

		public function filter_post_custom_meta_tabs( $tabs, $mod, $metabox_id ) {
			if ( $metabox_id === $this->p->cf['meta']['id'] ) {
				SucomUtil::add_after_key( $tabs, 'media', 'buttons',
					_x( 'Sharing Buttons', 'metabox tab', 'nextgen-facebook' ) );
			}
			return $tabs;
		}

		public function filter_post_cache_transient_keys( $transient_keys, $mod, $sharing_url, $mod_salt ) {
			$lca = $this->p->cf['lca'];
			$cache_md5_pre = $lca.'_b_';
			$classname_pre = 'Ngfb';
			$transient_keys[] = $cache_md5_pre.md5( $classname_pre.'Sharing::get_buttons('.$mod_salt.')' );
			$transient_keys[] = $cache_md5_pre.md5( $classname_pre.'ShortcodeSharing::do_shortcode('.$mod_salt.')' );
			$transient_keys[] = $cache_md5_pre.md5( $classname_pre.'WidgetSharing::widget('.$mod_salt.')' );
			return $transient_keys;
		}

		// hooked to 'ngfb_status_gpl_features'
		public function filter_status_gpl_features( $features, $ext, $info, $pkg ) {
			if ( ! empty( $info['lib']['submenu']['buttons'] ) )
				$features['(sharing) Sharing Buttons'] = array(
					'classname' => $ext.'Sharing',
				);
			if ( ! empty( $info['lib']['submenu']['styles'] ) )
				$features['(sharing) Sharing Stylesheet'] = array(
					'status' => empty( $this->p->options['buttons_use_social_style'] ) ? 'off' : 'on',
				);
			if ( ! empty( $info['lib']['shortcode']['sharing'] ) )
				$features['(sharing) Sharing Shortcode'] = array(
					'classname' => $ext.'ShortcodeSharing',
				);
			if ( ! empty( $info['lib']['widget']['sharing'] ) )
				$features['(sharing) Sharing Widget'] = array(
					'classname' => $ext.'WidgetSharing'
				);
			return $features;
		}

		public function filter_status_pro_features( $features, $ext, $info, $pkg ) {
			if ( ! empty( $info['lib']['submenu']['buttons'] ) ) {
				$features['(tool) Sharing Styles Editor'] = array( 
					'td_class' => $pkg['aop'] ? '' : 'blank',
					'purchase' => $pkg['purchase'],
					'status' => $pkg['aop'] ? 'on' : 'rec',
				);
			}
			return $features;
		}

		public function action_load_setting_page_reload_default_sharing_styles( $pagehook, $menu_id, $menu_name, $menu_lib ) {

			$opts =& $this->p->options;	// update the existing options array
			$def_opts = $this->p->opt->get_defaults();
			$tabs = apply_filters( $this->p->cf['lca'].'_sharing_styles_tabs', $this->p->cf['sharing']['styles'] );

			foreach ( $tabs as $id => $name ) {
				if ( isset( $opts['buttons_css_'.$id] ) && isset( $def_opts['buttons_css_'.$id] ) ) {
					$opts['buttons_css_'.$id] = $def_opts['buttons_css_'.$id];
				}
			}

			$this->update_sharing_css( $opts );
			$this->p->opt->save_options( NGFB_OPTIONS_NAME, $opts, false );
			$this->p->notice->upd( __( 'All sharing styles have been reloaded with their default value and saved.',
				'nextgen-facebook' ) );
		}

		public function wp_enqueue_styles() {
			if ( ! empty( $this->p->options['buttons_use_social_style'] ) ) {
				if ( ! file_exists( self::$sharing_css_file ) ) {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( 'updating '.self::$sharing_css_file );
					}
					$this->update_sharing_css( $this->p->options );
				}
				if ( ! empty( $this->p->options['buttons_enqueue_social_style'] ) ) {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( 'wp_enqueue_style = '.$this->p->cf['lca'].'_sharing_css' );
					}
					wp_enqueue_style( $this->p->cf['lca'].'_sharing_css', self::$sharing_css_url, 
						false, $this->p->cf['plugin'][$this->p->cf['lca']]['version'] );
				} else {
					if ( ! is_readable( self::$sharing_css_file ) ) {
						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( self::$sharing_css_file.' is not readable' );
						}
						if ( is_admin() ) {
							$this->p->notice->err( sprintf( __( 'The %s file is not readable.',
								'nextgen-facebook' ), self::$sharing_css_file ) );
						}
					} elseif ( ( $fsize = @filesize( self::$sharing_css_file ) ) > 0 &&
						$fh = @fopen( self::$sharing_css_file, 'rb' ) ) {
						echo '<style type="text/css">';
						echo fread( $fh, $fsize );
						echo '</style>',"\n";
						fclose( $fh );
					}
				}
			} elseif ( $this->p->debug->enabled ) {
				$this->p->debug->log( 'buttons_use_social_style option is disabled' );
			}
		}

		public function update_sharing_css( &$opts ) {

			if ( empty( $opts['buttons_use_social_style'] ) ) {
				$this->unlink_sharing_css();
				return;
			}

			$lca = $this->p->cf['lca'];
			$tabs = apply_filters( $lca.'_sharing_styles_tabs', $this->p->cf['sharing']['styles'] );
			$sharing_css_data = '';

			foreach ( $tabs as $id => $name ) {
				if ( isset( $opts['buttons_css_'.$id] ) ) {
					$sharing_css_data .= $opts['buttons_css_'.$id];
				}
			}

			$sharing_css_data = SucomUtil::minify_css( $sharing_css_data, $lca );

			if ( $fh = @fopen( self::$sharing_css_file, 'wb' ) ) {
				if ( ( $written = fwrite( $fh, $sharing_css_data ) ) === false ) {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( 'failed writing the css file '.self::$sharing_css_file );
					}
					if ( is_admin() ) {
						$this->p->notice->err( sprintf( __( 'Failed writing the css file %s.',
							'nextgen-facebook' ), self::$sharing_css_file ) );
					}
				} elseif ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'updated css file '.self::$sharing_css_file.' ('.$written.' bytes written)' );
					if ( is_admin() ) {
						$this->p->notice->upd( sprintf( __( 'Updated the <a href="%1$s">%2$s</a> stylesheet (%3$d bytes written).',
							'nextgen-facebook' ), self::$sharing_css_url, self::$sharing_css_file, $written ), 
								true, 'updated_'.self::$sharing_css_file, true );	// allow dismiss
					}
				}
				fclose( $fh );
			} else {
				if ( ! is_writable( NGFB_CACHEDIR ) ) {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( 'cache folder '.NGFB_CACHEDIR.' is not writable' );
					}
					if ( is_admin() ) {
						$this->p->notice->err( sprintf( __( 'Cache folder %s is not writable.',
							'nextgen-facebook' ), NGFB_CACHEDIR ) );
					}
				}
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'failed to open the css file '.self::$sharing_css_file.' for writing' );
				}
				if ( is_admin() ) {
					$this->p->notice->err( sprintf( __( 'Failed to open the css file %s for writing.',
						'nextgen-facebook' ), self::$sharing_css_file ) );
				}
			}
		}

		public function unlink_sharing_css() {
			if ( file_exists( self::$sharing_css_file ) ) {
				if ( ! @unlink( self::$sharing_css_file ) ) {
					if ( is_admin() ) {
						$this->p->notice->err( __( 'Error removing the minimized stylesheet &mdash; does the web server have sufficient privileges?',
							'nextgen-facebook' ) );
					}
				}
			}
		}

		public function add_post_buttons_metabox() {
			if ( ! is_admin() ) {	// just in case
				return;
			}

			// get the current object / post type
			if ( ( $post_obj = SucomUtil::get_post_object() ) === false ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'exiting early: invalid post object' );
				}
				return;
			}

			if ( ! empty( $this->p->options['buttons_add_to_'.$post_obj->post_type] ) ) {
				// add_meta_box( $id, $title, $callback, $post_type, $context, $priority, $callback_args );
				add_meta_box( '_'.$this->p->cf['lca'].'_share',
					_x( 'Sharing Buttons', 'metabox title', 'nextgen-facebook' ),
						array( &$this, 'show_admin_sharing' ), $post_obj->post_type, 'side', 'high' );
			}
		}

		public function action_pre_apply_filters_text( $filter_name ) {
			if ( $this->p->debug->enabled ) {
				$this->p->debug->log_args( array( 
					'filter_name' => $filter_name,
				) );
			}
			$this->remove_buttons_filter( $filter_name );
		}

		public function action_after_apply_filters_text( $filter_name ) {
			if ( $this->p->debug->enabled ) {
				$this->p->debug->log_args( array( 
					'filter_name' => $filter_name,
				) );
			}
			$this->add_buttons_filter( $filter_name );
		}

		public function show_head() {
			echo $this->get_script_loader();
			echo $this->get_script( 'header' );
		}

		public function show_footer() {
			if ( $this->have_buttons_for_type( 'sidebar' ) ) {
				echo $this->show_sidebar();
			} elseif ( $this->p->debug->enabled ) {
				$this->p->debug->log( 'no buttons enabled for sidebar' );
			}
			echo $this->get_script( 'footer' );
		}

		public function show_sidebar() {
			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			$lca = $this->p->cf['lca'];
			$js = trim( preg_replace( '/\/\*.*\*\//', '', $this->p->options['buttons_js_sidebar'] ) );
			$text = $this->get_buttons( '', 'sidebar', false );	// $use_post = false

			if ( ! empty( $text ) ) {
				echo '<div id="'.$lca.'-sidebar">';
				echo '<div id="'.$lca.'-sidebar-header"></div>';
				echo $text;
				echo '</div>', "\n";
				echo '<script type="text/javascript">'.$js.'</script>', "\n";
			}
		}

		public function show_admin_sharing( $post_obj ) {
			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}
			$lca = $this->p->cf['lca'];
			$sharing_css_data = $this->p->options['buttons_css_admin_edit'];
			$sharing_css_data = SucomUtil::minify_css( $sharing_css_data, $lca );

			echo '<style type="text/css">'.$sharing_css_data.'</style>', "\n";
			echo '<table class="sucom-settings '.$lca.' post-side-metabox"><tr><td>';

			if ( get_post_status( $post_obj->ID ) === 'publish' || $post_obj->post_type === 'attachment' ) {
				echo $this->get_script_loader();
				echo $this->get_script( 'header' );
				echo $this->get_buttons( '', 'admin_edit' );
				echo $this->get_script( 'footer' );
			} else {
				echo '<p class="centered">'.sprintf( __( '%s must be published<br/>before it can be shared.',
					'nextgen-facebook' ), SucomUtil::titleize( $post_obj->post_type ) ).'</p>';
			}

			echo '</td></tr></table>';
		}

		public function add_buttons_filter( $filter_name = 'the_content' ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->log_args( array( 
					'filter_name' => $filter_name,
				) );
			}

			$added = false;

			if ( empty( $filter_name ) ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'filter_name argument is empty' );
				}
			} elseif ( method_exists( $this, 'get_buttons_'.$filter_name ) ) {
				$added = add_filter( $filter_name, array( &$this, 'get_buttons_'.$filter_name ), NGFB_SOCIAL_PRIORITY );
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'buttons filter '.$filter_name.' added ('.( $added  ? 'true' : 'false' ).')' );
				}
			} elseif ( $this->p->debug->enabled ) {
				$this->p->debug->log( 'get_buttons_'.$filter_name.' method is missing' );
			}

			return $added;
		}

		public function remove_buttons_filter( $filter_name = 'the_content' ) {
			$removed = false;
			if ( method_exists( $this, 'get_buttons_'.$filter_name ) ) {
				$removed = remove_filter( $filter_name, array( &$this, 'get_buttons_'.$filter_name ), NGFB_SOCIAL_PRIORITY );
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'buttons filter '.$filter_name.' removed ('.( $removed  ? 'true' : 'false' ).')' );
				}
			}
			return $removed;
		}

		public function get_buttons_the_excerpt( $text ) {
			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}
			$lca = $this->p->cf['lca'];
			$css_type_name = 'excerpt-buttons';
			$text = preg_replace_callback( '/(<!-- '.$lca.' '.$css_type_name.' begin -->'.
				'.*<!-- '.$lca.' '.$css_type_name.' end -->)(<\/p>)?/Usi', 
					array( __CLASS__, 'remove_paragraph_tags' ), $text );
			return $text;
		}

		public function get_buttons_get_the_excerpt( $text ) {
			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}
			return $this->get_buttons( $text, 'excerpt' );
		}

		public function get_buttons_the_content( $text ) {
			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}
			return $this->get_buttons( $text, 'content' );
		}

		// $mod = true | false | post_id | $mod array
		public function get_buttons( $text, $type = 'content', $mod = true, $location = '', $atts = array() ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark( 'getting buttons for '.$type );	// start timer
			}

			$error_text = false;
			$add_comment = true;

			if ( is_admin() ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'is_admin is true' );
				}
				if ( strpos( $type, 'admin_' ) !== 0 ) {
					$error_text = $type.' ignored in back-end';
				}
			} elseif ( SucomUtil::is_amp() ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'is_amp is true' );
				}
				$error_text = 'buttons not allowed in amp endpoint';
			} elseif ( is_feed() ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'is_feed is true' );
				}
				$error_text = 'buttons not allowed in rss feeds';
			} elseif ( ! is_singular() ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'is_singular is false' );
				}
				if ( empty( $this->p->options['buttons_on_index'] ) ) {
					$error_text = 'buttons_on_index not enabled';
				}
			} elseif ( is_front_page() ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'is_front_page is true' );
				}
				if ( empty( $this->p->options['buttons_on_front'] ) ) {
					$error_text = 'buttons_on_front not enabled';
				}
			} elseif ( is_singular() ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'is_singular is true' );
				}
				if ( $this->is_post_buttons_disabled() ) {
					$error_text = 'post buttons are disabled';
				}
			}

			if ( $error_text === false && ! $this->have_buttons_for_type( $type ) ) {
				$error_text = 'no sharing buttons enabled';
			}

			if ( $error_text !== false ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( $type.' filter skipped: '.$error_text );
					$this->p->debug->mark( 'getting buttons for '.$type );	// end timer
				}
				if ( $add_comment ) {
					return $text."\n".'<!-- '.__METHOD__.' '.$type.' filter skipped: '.$error_text.' -->'."\n";
				} else {
					return $text;
				}
			}

			// $mod is preferred but not required
			// $mod = true | false | post_id | $mod array
			if ( ! is_array( $mod ) ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'optional call to get_page_mod()' );
				}
				$mod = $this->p->util->get_page_mod( $mod );
			}

			$lca = $this->p->cf['lca'];
			$sharing_url = $this->p->util->get_sharing_url( $mod );
			$buttons_array = array();

			$cache_md5_pre = $lca.'_b_';
			$cache_exp_secs = $this->get_buttons_cache_exp();
			$cache_index = 0;	// redefined if $cache_exp_secs > 0

			if ( $this->p->debug->enabled ) {
				$this->p->debug->log( 'sharing url = '.$sharing_url );
				$this->p->debug->log( 'cache expire = '.$cache_exp_secs );
			}

			if ( $cache_exp_secs > 0 ) {

				$cache_salt = __METHOD__.'('.SucomUtil::get_mod_salt( $mod, $sharing_url ).')';
				$cache_id = $cache_md5_pre.md5( $cache_salt );
				$cache_index = $this->get_buttons_cache_index( $type );

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'cache salt = '.$cache_salt );
					$this->p->debug->log( 'cache index = '.$cache_index );
				}

				$buttons_array = get_transient( $cache_id );

				if ( isset( $buttons_array[$cache_index] ) ) {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $type.' cache index found in array from transient '.$cache_id );
					}
				} else {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $type.' cache index not in array from transient '.$cache_id );
					}
					if ( ! is_array( $buttons_array ) ) {	// just in case
						$buttons_array = array();
					}
				}
			} else {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( $type.' buttons array transient cache is disabled' );
				}
			}

			if ( empty( $location ) ) {
				$location = empty( $this->p->options['buttons_pos_'.$type] ) ? 
					'bottom' : $this->p->options['buttons_pos_'.$type];
			}

			if ( ! isset( $buttons_array[$cache_index] ) ) {

				// sort enabled sharing buttons by their preferred order
				$sorted_ids = array();
				foreach ( $this->p->cf['opt']['cm_prefix'] as $id => $opt_pre ) {
					if ( ! empty( $this->p->options[$opt_pre.'_on_'.$type] ) ) {
						$sorted_ids[ zeroise( $this->p->options[$opt_pre.'_order'], 3 ).'-'.$id ] = $id;
					}
				}
				ksort( $sorted_ids );

				$atts['use_post'] = $mod['use_post'];
				$atts['css_id'] = $css_type_name = $type.'-buttons';

				if ( ! empty( $this->p->options['buttons_preset_'.$type] ) ) {
					$atts['preset_id'] = $this->p->options['buttons_preset_'.$type];
				}

				// returns html or an empty string
				$buttons_array[$cache_index] = $this->get_html( $sorted_ids, $atts, $mod );

				if ( ! empty( $buttons_array[$cache_index] ) ) {
					$buttons_array[$cache_index] = apply_filters( $lca.'_sharing_buttons_html', '
<!-- '.$lca.' '.$css_type_name.' begin -->
<!-- generated on '.date( 'c' ).' -->
<div '.( $mod['use_post'] ? 'class="'.$lca.'-'.$css_type_name.'"' : 'id="'.$lca.'-'.$css_type_name.'"' ).'>'."\n".
$buttons_array[$cache_index].
'</div><!-- '.( $mod['use_post'] ? '.' : '#' ).$lca.'-'.$css_type_name.' -->
<!-- '.$lca.' '.$css_type_name.' end -->'."\n\n", $type, $mod, $location, $atts );

					if ( $cache_exp_secs > 0 ) {
						// update the cached array and maintain the existing transient expiration time
						$expires_in_secs = SucomUtil::update_transient_array( $cache_id, $buttons_array, $cache_exp_secs );
						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( $type.' buttons html saved to transient cache (expires in '.$expires_in_secs.' seconds)' );
						}
					}
				}
			}

			switch ( $location ) {
				case 'top': 
					$text = $buttons_array[$cache_index].$text; 
					break;
				case 'bottom': 
					$text = $text.$buttons_array[$cache_index]; 
					break;
				case 'both': 
					$text = $buttons_array[$cache_index].$text.$buttons_array[$cache_index]; 
					break;
			}

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark( 'getting buttons for '.$type );	// end timer
			}

			return $text;
		}

		public function get_buttons_cache_exp() {
			static $cache_exp_secs = null;	// filter the cache expiration value only once
			if ( ! isset( $cache_exp_secs ) ) {
				$lca = $this->p->cf['lca'];
				$cache_md5_pre = $lca.'_b_';
				$cache_exp_filter = $this->p->cf['wp']['transient'][$cache_md5_pre]['filter'];
				$cache_opt_key = $this->p->cf['wp']['transient'][$cache_md5_pre]['opt_key'];
				$cache_exp_secs = isset( $this->p->options[$cache_opt_key] ) ? $this->p->options[$cache_opt_key] : WEEK_IN_SECONDS;
				$cache_exp_secs = (int) apply_filters( $cache_exp_filter, $cache_exp_secs );
			}
			return $cache_exp_secs;
		}

		public function get_buttons_cache_index( $type, $atts = false, $ids = false ) {
			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}
			return 'locale:'.SucomUtil::get_locale( 'current' ).
				'_type:'.( empty( $type ) ? 'none' : $type ).
				'_https:'.( SucomUtil::is_https() ? 'true' : 'false' ).
				( $this->p->avail['*']['vary_ua'] ? '_mobile:'.( SucomUtil::is_mobile() ? 'true' : 'false' ) : '' ).
				( $atts !== false ? '_atts:'.http_build_query( $atts, '', '_' ) : '' ).
				( $ids !== false ? '_ids:'.http_build_query( $ids, '', '_' ) : '' );
		}

		// get_html() is called by the widget, shortcode, function, and perhaps some filter hooks
		public function get_html( array $ids, array $atts, $mod = false ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			$lca = $this->p->cf['lca'];
			$atts['use_post'] = isset( $atts['use_post'] ) ? $atts['use_post'] : true;	// maintain backwards compat
			$atts['add_page'] = isset( $atts['add_page'] ) ? $atts['add_page'] : true;	// used by get_sharing_url()
			$atts['preset_id'] = isset( $atts['preset_id'] ) ? SucomUtil::sanitize_key( $atts['preset_id'] ) : '';
			$atts['filter_id'] = isset( $atts['filter_id'] ) ? SucomUtil::sanitize_key( $atts['filter_id'] ) : '';

			// $mod is preferred but not required
			// $mod = true | false | post_id | $mod array
			if ( ! is_array( $mod ) ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'optional call to get_page_mod()' );
				}
				$mod = $this->p->util->get_page_mod( $atts['use_post'] );
			}

			$buttons_html = '';
			$buttons_begin = ( empty( $atts['preset_id'] ) ? '' : '<div class="ngfb-preset-'.$atts['preset_id'].'">'."\n" ).
				'<div class="ngfb-buttons '.SucomUtil::get_locale( $mod ).'">'."\n";
			$buttons_end = '</div><!-- .ngfb-buttons.'.SucomUtil::get_locale( $mod ).' -->'."\n".
				( empty( $atts['preset_id'] ) ? '' : '</div><!-- .ngfb-preset-'.$atts['preset_id'].' -->'."\n" );

			// possibly dereference the opts variable to prevent passing on changes
			if ( empty( $atts['preset_id'] ) && empty( $atts['filter_id'] ) ) {
				$custom_opts =& $this->p->options;
			} else {
				$custom_opts = $this->p->options;
			}

			// apply the presets to $custom_opts
			if ( ! empty( $atts['preset_id'] ) && ! empty( $this->p->cf['opt']['preset'] ) ) {
				if ( isset( $this->p->cf['opt']['preset'][$atts['preset_id']] ) &&
					is_array( $this->p->cf['opt']['preset'][$atts['preset_id']] ) ) {
					if ( $this->p->debug->enabled )
						$this->p->debug->log( 'applying preset_id '.$atts['preset_id'].' to options' );
					$custom_opts = array_merge( $custom_opts, $this->p->cf['opt']['preset'][$atts['preset_id']] );
				} elseif ( $this->p->debug->enabled )
					$this->p->debug->log( $atts['preset_id'].' preset_id missing or not array'  );
			} 

			// apply the filter_id if the filter name has hooks
			if ( ! empty( $atts['filter_id'] ) ) {
				$filter_name = $lca.'_sharing_html_'.$atts['filter_id'].'_options';
				if ( has_filter( $filter_name ) ) {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( 'applying filter_id '.$atts['filter_id'].' to options ('.$filter_name.')' );
					}
					$custom_opts = apply_filters( $filter_name, $custom_opts );
				} elseif ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'no filter(s) found for '.$filter_name );
				}
			}

			$saved_atts = $atts;
			foreach ( $ids as $id ) {
				if ( isset( $this->website[$id] ) ) {
					if ( method_exists( $this->website[$id], 'get_html' ) ) {
						if ( $this->allow_for_platform( $id ) ) {

							$atts['src_id'] = SucomUtil::get_atts_src_id( $atts, $id );	// uses 'css_id' and 'use_post'

							if ( empty( $atts['url'] ) ) {
								$atts['url'] = $this->p->util->get_sharing_url( $mod,
									$atts['add_page'], $atts['src_id'] );
							} else {
								$atts['url'] = apply_filters( $lca.'_sharing_url',
									$atts['url'], $mod, $atts['add_page'], $atts['src_id'] );
							}

							// filter to add custom tracking arguments
							$atts['url'] = apply_filters( $lca.'_sharing_buttons_shared_url',
								$atts['url'], $mod, $id );

							$force_prot = apply_filters( $lca.'_sharing_buttons_force_prot',
								$this->p->options['buttons_force_prot'], $mod, $id, $atts['url'] );

							if ( ! empty( $force_prot ) && $force_prot !== 'none' ) {
								$atts['url'] = preg_replace( '/^.*:\/\//', $force_prot.'://', $atts['url'] );
							}

							$buttons_html .= $this->website[$id]->get_html( $atts, $custom_opts, $mod )."\n";

							$atts = $saved_atts;	// restore the common $atts array

						} elseif ( $this->p->debug->enabled ) {
							$this->p->debug->log( $id.' not allowed for platform' );
						}
					} elseif ( $this->p->debug->enabled ) {
						$this->p->debug->log( 'get_html method missing for '.$id );
					}
				} elseif ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'website object missing for '.$id );
				}
			}

			$buttons_html = trim( $buttons_html );

			return empty( $buttons_html ) ? '' : $buttons_begin.$buttons_html.$buttons_end;
		}

		// add javascript for enabled buttons in content, widget, shortcode, etc.
		public function get_script( $pos = 'header', $request_ids = array() ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			$enabled_ids = array();

			// there are no widgets on the admin back-end, so don't bother checking
			if ( ! is_admin() ) {
				if ( class_exists( 'NgfbWidgetSharing' ) ) {
					$widget = new NgfbWidgetSharing();
			 		$widget_settings = $widget->get_settings();
				} else {
					$widget_settings = array();
				}

				// check for enabled buttons in ACTIVE widget(s)
				foreach ( $widget_settings as $num => $instance ) {
					if ( is_object( $widget ) && is_active_widget( false,
						$widget->id_base.'-'.$num, $widget->id_base ) ) {

						foreach ( $this->p->cf['opt']['cm_prefix'] as $id => $opt_pre ) {
							if ( array_key_exists( $id, $instance ) && 
								! empty( $instance[$id] ) )
									$enabled_ids[] = $id;
						}
					}
				}
				if ( $this->p->debug->enabled )
					$this->p->debug->log( 'enabled widget ids: '.
						SucomDebug::pretty_array( $enabled_ids, true ) );
			}

			$exit_message = false;
			if ( is_admin() ) {
				if ( ( $post_obj = SucomUtil::get_post_object() ) === false ||
					( get_post_status( $post_obj->ID ) !== 'publish' && $post_obj->post_type !== 'attachment' ) ) {
					$exit_message = 'must be published or attachment for admin buttons';
				}
			} elseif ( ! is_singular() ) {
				if ( empty( $this->p->options['buttons_on_index'] ) ) {
					$exit_message = 'buttons_on_index not enabled';
				}
			} elseif ( is_front_page() ) {
				if ( empty( $this->p->options['buttons_on_front'] ) ) {
					$exit_message = 'buttons_on_front not enabled';
				}
			} elseif ( is_singular() ) {
				if ( $this->is_post_buttons_disabled() ) {
					$exit_message = 'post buttons are disabled';
				}
			}

			if ( $exit_message ) {
				if ( empty( $request_ids ) && empty( $enabled_ids ) ) {
					if ( $this->p->debug->enabled )
						$this->p->debug->log( 'exiting early: '.$exit_message  );
					return '<!-- '.$this->p->cf['lca'].' '.$pos.': '.$exit_message.' -->'."\n";
				} elseif ( $this->p->debug->enabled )
					$this->p->debug->log( 'ignoring exit message: have requested or enabled ids' );
			} elseif ( is_admin() ) {
				foreach ( $this->p->cf['opt']['cm_prefix'] as $id => $opt_pre ) {
					foreach ( SucomUtil::preg_grep_keys( '/^'.$opt_pre.'_on_admin_/', $this->p->options ) as $key => $val ) {
						if ( ! empty( $val ) )
							$enabled_ids[] = $id;
					}
				}
			} else {
				foreach ( $this->p->cf['opt']['cm_prefix'] as $id => $opt_pre ) {
					foreach ( SucomUtil::preg_grep_keys( '/^'.$opt_pre.'_on_/', $this->p->options ) as $key => $val ) {
						// exclude buttons enabled for admin editing pages
						if ( strpos( $key, $opt_pre.'_on_admin_' ) === false && ! empty( $val ) )
							$enabled_ids[] = $id;
					}
				}
			}

			if ( empty( $request_ids ) ) {
				if ( empty( $enabled_ids ) ) {
					if ( $this->p->debug->enabled )
						$this->p->debug->log( 'exiting early: no buttons enabled or requested' );
					return '<!-- ngfb '.$pos.': no buttons enabled or requested -->'."\n";
				} else $include_ids = $enabled_ids;
			} else {
				$include_ids = array_diff( $request_ids, $enabled_ids );
				if ( empty( $include_ids ) ) {
					if ( $this->p->debug->enabled )
						$this->p->debug->log( 'exiting early: no scripts after removing enabled buttons' );
					return '<!-- ngfb '.$pos.': no scripts after removing enabled buttons -->'."\n";
				}
			}

			natsort( $include_ids );
			$include_ids = array_unique( $include_ids );
			$script_html = '<!-- ngfb '.$pos.' javascript begin -->'."\n".
				'<!-- generated on '.date( 'c' ).' -->'."\n";

			if ( strpos( $pos, '-header' ) ) 
				$script_loc = 'header';
			elseif ( strpos( $pos, '-footer' ) ) 
				$script_loc = 'footer';
			else $script_loc = $pos;

			if ( ! empty( $include_ids ) ) {
				foreach ( $include_ids as $id ) {
					$id = preg_replace( '/[^a-z]/', '', $id );
					$opt_name = $this->p->cf['opt']['cm_prefix'][$id].'_script_loc';

					if ( isset( $this->website[$id] ) &&
						method_exists( $this->website[$id], 'get_script' ) ) {

						if ( isset( $this->p->options[$opt_name] ) && 
							$this->p->options[$opt_name] === $script_loc )
								$script_html .= $this->website[$id]->get_script( $pos )."\n";
						else $script_html .= '<!-- ngfb '.$pos.': '.$id.' script location is '.$this->p->options[$opt_name].' -->'."\n";
					}
				}
			}

			$script_html .= '<!-- ngfb '.$pos.' javascript end -->'."\n";

			return $script_html;
		}

		public function get_script_loader( $pos = 'id' ) {

			$lang = empty( $this->p->options['gp_lang'] ) ? 'en-US' : $this->p->options['gp_lang'];
			$lang = apply_filters( $this->p->cf['lca'].'_pub_lang', $lang, 'google', 'current' );

			return '<script type="text/javascript" id="ngfb-header-script">
	window.___gcfg = { lang: "'.$lang.'" };
	function '.$this->p->cf['lca'].'_insert_js( script_id, url, async ) {
		if ( document.getElementById( script_id + "-js" ) ) return;
		var async = typeof async !== "undefined" ? async : true;
		var script_pos = document.getElementById( script_id );
		var js = document.createElement( "script" );
		js.id = script_id + "-js";
		js.async = async;
		js.type = "text/javascript";
		js.language = "JavaScript";
		js.src = url;
		script_pos.parentNode.insertBefore( js, script_pos );
	};
</script>'."\n";
		}

		public function have_buttons_for_type( $type ) {
			if ( isset( $this->buttons_for_type[$type] ) ) {
				return $this->buttons_for_type[$type];
			}
			foreach ( $this->p->cf['opt']['cm_prefix'] as $id => $opt_pre ) {
				if ( ! empty( $this->p->options[$opt_pre.'_on_'.$type] ) &&	// check if button is enabled
					$this->allow_for_platform( $id ) ) {			// check if allowed on platform
					return $this->buttons_for_type[$type] = true;
				}
			}
			return $this->buttons_for_type[$type] = false;
		}

		public function allow_for_platform( $id ) {

			// always allow if the content does not vary by user agent
			if ( ! $this->p->avail['*']['vary_ua'] ) {
				return true;
			}

			$opt_pre = isset( $this->p->cf['opt']['cm_prefix'][$id] ) ?
				$this->p->cf['opt']['cm_prefix'][$id] : $id;

			if ( isset( $this->p->options[$opt_pre.'_platform'] ) ) {
				switch( $this->p->options[$opt_pre.'_platform'] ) {
					case 'any':
						return true;
					case 'desktop':
						return SucomUtil::is_desktop();
					case 'mobile':
						return SucomUtil::is_mobile();
					default:
						return true;
				}
			}

			return true;
		}

		public function is_post_buttons_disabled() {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			$ret = false;

			if ( ( $post_obj = SucomUtil::get_post_object() ) === false ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'exiting early: invalid post object' );
				}
				return $ret;
			} else {
				$post_id = empty( $post_obj->ID ) ? 0 : $post_obj->ID;
			}

			if ( empty( $post_id ) ) {
				return $ret;
			}

			if ( isset( $this->post_buttons_disabled[$post_id] ) ) {
				return $this->post_buttons_disabled[$post_id];
			}

			// get_options() returns null if an index key is not found
			if ( $this->p->m['util']['post']->get_options( $post_id, 'buttons_disabled' ) ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'post '.$post_id.': sharing buttons disabled by meta data option' );
				}
				$ret = true;
			} elseif ( ! empty( $post_obj->post_type ) && empty( $this->p->options['buttons_add_to_'.$post_obj->post_type] ) ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'post '.$post_id.': sharing buttons not enabled for post type '.$post_obj->post_type );
				}
				$ret = true;
			}

			return $this->post_buttons_disabled[$post_id] = apply_filters( $this->p->cf['lca'].'_post_buttons_disabled', $ret, $post_id );
		}

		public function remove_paragraph_tags( $match = array() ) {
			if ( empty( $match ) || ! is_array( $match ) ) return;
			$text = empty( $match[1] ) ? '' : $match[1];
			$suff = empty( $match[2] ) ? '' : $match[2];
			$ret = preg_replace( '/(<\/*[pP]>|\n)/', '', $text );
			return $suff.$ret; 
		}

		public function get_website_object_ids( $website = array() ) {
			$website_ids = array();

			if ( empty( $website ) ) {
				$keys = array_keys( $this->website );
			} else {
				$keys = array_keys( $website );
			}

			$website_lib = $this->p->cf['plugin']['ngfb']['lib']['website'];

			foreach ( $keys as $id ) {
				$website_ids[$id] = isset( $website_lib[$id] ) ?
					$website_lib[$id] : ucfirst( $id );
			}

			return $website_ids;
		}

		public function get_tweet_text( array $mod, $atts = array(), $opt_pre = 'twitter', $md_pre = 'twitter' ) {
			if ( ! isset( $atts['tweet'] ) ) {	// just in case
				$atts['use_post'] = isset( $atts['use_post'] ) ? $atts['use_post'] : true;
				$atts['add_page'] = isset( $atts['add_page'] ) ? $atts['add_page'] : true;	// used by get_sharing_url()
				$atts['add_hashtags'] = isset( $atts['add_hashtags'] ) ? $atts['add_hashtags'] : true;
				return $this->p->page->get_caption( ( empty( $this->p->options[$opt_pre.'_caption'] ) ?
					'title' : $this->p->options[$opt_pre.'_caption'] ), $this->get_tweet_max_len( $opt_pre ),
						$mod, true, $atts['add_hashtags'], false, $md_pre.'_desc' );
			} else return $atts['tweet'];
		}

		// $opt_pre can be twitter, buffer, etc.
		public function get_tweet_max_len( $opt_pre = 'twitter' ) {

			$short_len = 23;	// twitter counts 23 characters for any url

			if ( isset( $this->p->options['tc_site'] ) && ! empty( $this->p->options[$opt_pre.'_via'] ) ) {
				$tc_site = preg_replace( '/^@/', '', $this->p->options['tc_site'] );
				$site_len = empty( $tc_site ) ? 0 : strlen( $tc_site ) + 6;
			} else {
				$site_len = 0;
			}

			$max_len = $this->p->options[$opt_pre.'_cap_len'] - $site_len - $short_len;

			if ( $this->p->debug->enabled ) {
				$this->p->debug->log( 'max tweet length is '.$max_len.' chars ('.$this->p->options[$opt_pre.'_cap_len'].
					' less '.$site_len.' for site name and '.$short_len.' for url)' );
			}

			return $max_len;
		}

		public function get_social_file_cache_url( $url, $file_ext = '' ) {

			$lca = $this->p->cf['lca'];
			$cache_exp_secs = (int) apply_filters( $lca.'_cache_expire_social_file', 
				$this->p->options['plugin_social_file_cache_exp'] );

			if ( $cache_exp_secs > 0 && isset( $this->p->cache->base_dir ) ) {
				$url = $this->p->cache->get( $url, 'url', 'file', $cache_exp_secs, $file_ext );
			}

			return apply_filters( $lca.'_rewrite_cache_url', $url );	// social file cache url
		}

		public function filter_messages_tooltip( $text, $idx ) {
			if ( strpos( $idx, 'tooltip-buttons_' ) !== 0 ) {
				return $text;
			}
			switch ( $idx ) {
				case ( strpos( $idx, 'tooltip-buttons_pos_' ) === false ? false : true ):
					$text = sprintf( __( 'Social sharing buttons can be added to the top, bottom, or both. Each sharing button must also be enabled below (see the <em>%s</em> options).', 'nextgen-facebook' ), _x( 'Show Button in', 'option label', 'nextgen-facebook' ) );
					break;
				case 'tooltip-buttons_on_index':
					$text = __( 'Add the social sharing buttons to each entry of an index webpage (blog front page, category, archive, etc.). Social sharing buttons are not included on index webpages by default.', 'nextgen-facebook' );
					break;
				case 'tooltip-buttons_on_front':
					$text = __( 'If a static Post or Page has been selected for the front page, you can add the social sharing buttons to that static front page as well (default is unchecked).', 'nextgen-facebook' );
					break;
				case 'tooltip-buttons_add_to':
					$text = __( 'Enabled social sharing buttons are added to the Post, Page, Media, and Product webpages by default. If your theme (or another plugin) supports additional custom post types, and you would like to include social sharing buttons on these webpages, check the appropriate option(s) here.', 'nextgen-facebook' );
					break;
				case 'tooltip-buttons_preset':
					$text = __( 'Select a pre-defined set of option values for sharing buttons in this location.', 'nextgen-facebook' );
					break;
				case 'tooltip-buttons_force_prot':
					$text = __( 'Modify URLs shared by the sharing buttons to use a specific protocol. This option can be useful to retain the share count of HTTP URLs after moving your site to HTTPS.', 'nextgen-facebook' );
					break;
				case 'tooltip-buttons_use_social_style':
					$text = sprintf( __( 'Add the CSS of all <em>%1$s</em> to webpages (default is checked). The CSS will be <strong>minimized</strong>, and saved to a single stylesheet with a URL of <a href="%2$s">%3$s</a>. The minimized stylesheet can be enqueued or added directly to the webpage HTML.', 'nextgen-facebook' ), _x( 'Sharing Styles', 'lib file description', 'nextgen-facebook' ), NgfbSharing::$sharing_css_url, NgfbSharing::$sharing_css_url );
					break;
				case 'tooltip-buttons_enqueue_social_style':
					$text = __( 'Have WordPress enqueue the social stylesheet instead of adding the CSS to in the webpage HTML (default is unchecked). Enqueueing the stylesheet may be desirable if you use a plugin to concatenate all enqueued styles into a single stylesheet URL.', 'nextgen-facebook' );
					break;
				case 'tooltip-buttons_js_sidebar':
					$text = __( 'JavaScript added to webpages for the social sharing sidebar.' );
					break;
				case 'tooltip-buttons_add_via':
					$text = sprintf( __( 'Append the %1$s to the tweet (see <a href="%2$s">the Twitter options tab</a> in the %3$s page). The %1$s will be displayed and recommended after the webpage is shared.', 'nextgen-facebook' ), _x( 'Twitter Business @username', 'option label', 'nextgen-facebook' ), $this->p->util->get_admin_url( 'general#sucom-tabset_pub-tab_twitter' ), _x( 'General Settings', 'lib file description', 'nextgen-facebook' ) );
					break;
				case 'tooltip-buttons_rec_author':
					$text = sprintf( __( 'Recommend following the author\'s Twitter @username after sharing a webpage. If the %1$s option (above) is also checked, the %2$s is suggested first.', 'nextgen-facebook' ), _x( 'Add via @username', 'option label (short)', 'nextgen-facebook' ), _x( 'Twitter Business @username', 'option label', 'nextgen-facebook' ) );
					break;
			}
			return $text;
		}

		public function filter_messages_tooltip_plugin( $text, $idx ) {
			switch ( $idx ) {
				case 'tooltip-plugin_sharing_buttons_cache_exp':

					$cache_exp_secs = NgfbSharing::$cf['opt']['defaults']['plugin_sharing_buttons_cache_exp'];
					$cache_exp_human = $cache_exp_secs ? 
						human_time_diff( 0, $cache_exp_secs ) : 
						_x( 'disabled', 'option comment', 'nextgen-facebook' );

					$text = __( 'The rendered HTML for social sharing buttons is saved to the WordPress transient cache to optimize performance.',
						'nextgen-facebook' ).' '.sprintf( __( 'The suggested cache expiration value is %1$s seconds (%2$s).',
							'nextgen-facebook' ), $cache_exp_secs, $cache_exp_human );

					break;

				case 'tooltip-plugin_social_file_cache_exp':

					$cache_exp_secs = NgfbSharing::$cf['opt']['defaults']['plugin_social_file_cache_exp'];
					$cache_exp_human = $cache_exp_secs ? 
						human_time_diff( 0, $cache_exp_secs ) : 
						_x( 'disabled', 'option comment', 'nextgen-facebook' );

					$text = __( 'The JavaScript of most social sharing buttons can be saved locally to cache folder in order to provide cached URLs instead of the originals.', 'nextgen-facebook' ).' '.__( 'If your hosting infrastructure performs reasonably well, this option can improve page load times significantly.', 'nextgen-facebook' ).' '.sprintf( __( 'The suggested cache expiration value is %1$s seconds (%2$s).', 'nextgen-facebook' ), $cache_exp_secs, $cache_exp_human );

					break;
			}
			return $text;
		}

		public function filter_messages_info( $text, $idx ) {
			if ( strpos( $idx, 'info-styles-' ) !== 0 )
				return $text;
			$lca =  $this->p->cf['lca'];
			$short = $this->p->cf['plugin'][$lca]['short'];
			switch ( $idx ) {
				case 'info-styles-sharing':
					$notes_url = $this->p->cf['plugin'][$lca]['url']['notes'];
					$text = '<p>'.$short.' uses the \''.$lca.'-buttons\' class to wrap all its sharing buttons, and each button has it\'s own individual class name as well. Refer to the <a href="'.$notes_url.'">Notes</a> webpage for additional stylesheet information, including how to hide the sharing buttons for specific Posts, Pages, categories, tags, etc.</p>';
					break;
				case 'info-styles-content':
					$text = '<p>Social sharing buttons, enabled / added to the content text from the '.$this->p->util->get_admin_url( 'buttons', 'Sharing Buttons' ).' settings page, are assigned the \''.$lca.'-content-buttons\' class, which itself contains the \''.$lca.'-buttons\' class -- a common class for all buttons (see the All Buttons tab).</p> 
					<p>Example:</p><pre>
.'.$lca.'-content-buttons 
    .'.$lca.'-buttons
        .facebook-button { }</pre>';
					break;
				case 'info-styles-excerpt':
					$text = '<p>Social sharing buttons, enabled / added to the excerpt text from the '.$this->p->util->get_admin_url( 'buttons', 'Sharing Buttons' ).' settings page, are assigned the \''.$lca.'-excerpt-buttons\' class, which itself contains the \''.$lca.'-buttons\' class -- a common class for all buttons (see the All Buttons tab).</p> 
					<p>Example:</p><pre>
.'.$lca.'-excerpt-buttons 
    .'.$lca.'-buttons
        .facebook-button { }</pre>';
					break;
				case 'info-styles-sidebar':
					$text = '<p>Social sharing buttons added to the sidebar are assigned the \'#'.$lca.'-sidebar\' CSS id, which itself contains \'#'.$lca.'-sidebar-header\', \'#'.$lca.'-sidebar-buttons\' and the \''.$lca.'-buttons\' class -- a common class for all buttons (see the All Buttons tab).</p>
					<p>Example:</p><pre>
#'.$lca.'-sidebar
    #'.$lca.'-sidebar-header { }

#'.$lca.'-sidebar
    #'.$lca.'-sidebar-buttons
        .'.$lca.'-buttons
	    .facebook-button { }</pre>';
					break;
				case 'info-styles-shortcode':
					$text = '<p>Social sharing buttons added from a shortcode are assigned the \''.$lca.'-shortcode-buttons\' class, which itself contains the \''.$lca.'-buttons\' class -- a common class for all buttons (see the All Buttons tab).</p> 

					<p>Example:</p><pre>
.'.$lca.'-shortcode-buttons 
    .'.$lca.'-buttons
        .facebook-button { }</pre>';
					break;
				case 'info-styles-widget':
					$text = '<p>Social sharing buttons within the social sharing buttons widget are assigned the \''.$lca.'-widget-buttons\' class, which itself contains the \''.$lca.'-buttons\' class -- a common class for all buttons (see the All Buttons tab).</p> 
					<p>Example:</p><pre>
.'.$lca.'-widget-buttons 
    .'.$lca.'-buttons
        .facebook-button { }</pre>
					<p>The social sharing buttons widget also has an id of \''.$lca.'-widget-buttons-<em>#</em>\', and the buttons have an id of \'<em>name</em>-'.$lca.'-widget-buttons-<em>#</em>\'.</p>
					<p>Example:</p><pre>
#'.$lca.'-widget-buttons-2
    .'.$lca.'-buttons
        #facebook-'.$lca.'-widget-buttons-2 { }</pre>';
					break;
				case 'info-styles-admin_edit':
					$text = '<p>Social sharing buttons within the Admin Post / Page Edit metabox are assigned the \''.$lca.'-admin_edit-buttons\' class, which itself contains the \''.$lca.'-buttons\' class -- a common class for all buttons (see the All Buttons tab).</p> 
					<p>Example:</p><pre>
.'.$lca.'-admin_edit-buttons 
    .'.$lca.'-buttons
        .facebook-button { }</pre>';
					break;
			}
			return $text;
		}

		public function filter_settings_page_custom_style_css( $custom_style_css ) {
			$custom_style_css .= '
				.website_col {
					float:left;
					min-height:50px;
				}
				.max_cols_1.website_col {
					width:100%;
					min-width:100%;
					max-width:100%;
				}
				.max_cols_2.website_col {
					width:50%;
					min-width:50%;
					max-width:50%;
				}
				.max_cols_3.website_col {
					width:33.3333%;
					min-width:33.3333%;
					max-width:33.3333%;
				}
				.max_cols_4.website_col {
					width:25%;
					min-width:25%;
					max-width:25%;
				}
				.website_col .postbox {
					overflow-x:hidden;
				}
				.postbox-website {
					min-width:452px;
					overflow-y:auto;
				}
				.postbox-website .metabox-website {
					min-height:565px;
					overflow-y:auto;
				}
				.postbox-website.postbox-show_basic .metabox-website {
					min-height:435px;
				}
				.postbox-website div.sucom-metabox-tabs div.sucom-tabset.active {
					min-height:519px;
				}
				.postbox-website.postbox-show_basic div.sucom-metabox-tabs div.sucom-tabset.active {
					min-height:389px;
				}
				.postbox-website.closed,
				.postbox-website.closed .metabox-website,
				.postbox-website.postbox-show_basic.closed .metabox-website {
					height:auto;
					min-height:0;
					overflow:hidden;
				}
			';
			return $custom_style_css;
		}
	}
}


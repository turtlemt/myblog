<?php
/*
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2012-2017 Jean-Sebastien Morisset (https://surniaulula.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'These aren\'t the droids you\'re looking for...' );
}

if ( ! class_exists( 'NgfbPost' ) ) {

	/*
	 * This class is extended by gpl/util/post.php or pro/util/post.php
	 * and the class object is created as $this->p->m['util']['post'].
	 */
	class NgfbPost extends NgfbMeta {

		protected static $cache_short_url = null;
		protected static $cache_shortlinks = array();

		public function __construct() {
		}

		protected function add_actions() {

			if ( is_admin() ) {
				if ( ! empty( $_GET ) || basename( $_SERVER['PHP_SELF'] ) === 'post-new.php' ) {
					// load_meta_page() priorities: 100 post, 200 user, 300 term
					// sets the NgfbMeta::$head_meta_tags and NgfbMeta::$head_meta_info class properties
					add_action( 'current_screen', array( &$this, 'load_meta_page' ), 100, 1 );
					add_action( 'add_meta_boxes', array( &$this, 'add_meta_boxes' ) );
				}

				add_action( 'save_post', array( &$this, 'save_options' ), NGFB_META_SAVE_PRIORITY );
				add_action( 'save_post', array( &$this, 'clear_cache' ), NGFB_META_CACHE_PRIORITY );

				add_action( 'edit_attachment', array( &$this, 'save_options' ), NGFB_META_SAVE_PRIORITY );
				add_action( 'edit_attachment', array( &$this, 'clear_cache' ), NGFB_META_CACHE_PRIORITY );
			}

			// add the columns when doing AJAX as well to allow Quick Edit to add the required columns
			if ( is_admin() || SucomUtil::get_const( 'DOING_AJAX' ) ) {

				// only use public post types (to avoid menu items, product variations, etc.)
				$ptns = $this->p->util->get_post_types( 'names' );

				if ( is_array( $ptns ) ) {
					foreach ( $ptns as $ptn ) {

						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( 'adding column filters for post type '.$ptn );
						}

						// https://codex.wordpress.org/Plugin_API/Filter_Reference/manage_$post_type_posts_columns
						add_filter( 'manage_'.$ptn.'_posts_columns',
							array( &$this, 'add_post_column_headings' ), NGFB_ADD_COLUMN_PRIORITY, 1 );

						add_filter( 'manage_edit-'.$ptn.'_sortable_columns',
							array( &$this, 'add_sortable_columns' ), 10, 1 );

						// https://codex.wordpress.org/Plugin_API/Action_Reference/manage_$post_type_posts_custom_column
						add_action( 'manage_'.$ptn.'_posts_custom_column',
							array( &$this, 'show_column_content' ), 10, 2 );
					}
				}

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'adding column filters for media library' );
				}

				add_filter( 'manage_media_columns', array( &$this, 'add_media_column_headings' ), NGFB_ADD_COLUMN_PRIORITY, 1 );
				add_filter( 'manage_upload_sortable_columns', array( &$this, 'add_sortable_columns' ), 10, 1 );
				add_action( 'manage_media_custom_column', array( &$this, 'show_column_content' ), 10, 2 );

				/*
				 * The 'parse_query' action is hooked ONCE in the NgfbPost class
				 * to set the column orderby for post, term, and user edit tables.
				 */
				add_action( 'parse_query', array( &$this, 'set_column_orderby' ), 10, 1 );
				add_action( 'get_post_metadata', array( &$this, 'check_sortable_metadata' ), 10, 4 );
			}

			if ( ! empty( $this->p->options['plugin_shortener'] ) && $this->p->options['plugin_shortener'] !== 'none' ) {
				if ( ! empty( $this->p->options['plugin_wp_shortlink'] ) ) {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( 'adding pre_get_shortlink filters for shortened sharing url' );
					}
					add_filter( 'pre_get_shortlink', array( &$this, 'get_sharing_shortlink' ), SucomUtil::get_min_int(), 4 );
					add_filter( 'pre_get_shortlink', array( &$this, 'restore_sharing_shortlink' ), SucomUtil::get_max_int(), 4 );

					if ( function_exists( 'wpme_get_shortlink_handler' ) ) {
						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( 'removing the jetpack pre_get_shortlink filter hook' );
						}
						remove_filter( 'pre_get_shortlink', 'wpme_get_shortlink_handler', 1 );
					}
				}
			}

			if ( ! empty( $this->p->options['plugin_clear_for_comment'] ) ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'adding clear cache for comment actions' );
				}
				// fires when a comment is inserted into the database
				add_action ( 'comment_post', array( &$this, 'clear_cache_for_new_comment' ), 10, 2 );
	
				// fires before transitioning a comment's status from one to another
				add_action ( 'wp_set_comment_status', array( &$this, 'clear_cache_for_comment_status' ), 10, 2 );
			}
		}

		public function get_mod( $mod_id ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			$mod = NgfbMeta::$mod_defaults;
			$mod['id'] = (int) $mod_id;
			$mod['name'] = 'post';
			$mod['obj'] =& $this;
			/*
			 * Post
			 */
			$mod['is_post'] = true;
			$mod['is_home_page'] = SucomUtil::is_home_page( $mod_id );
			$mod['is_home_index'] = $mod['is_home_page'] ? false : SucomUtil::is_home_index( $mod_id );
			$mod['is_home'] = $mod['is_home_page'] || $mod['is_home_index'] ? true : false;
			$mod['post_type'] = get_post_type( $mod_id );					// post type name
			$mod['post_mime'] = get_post_mime_type( $mod_id );				// post mime type (ie. image/jpg)
			$mod['post_status'] = get_post_status( $mod_id );				// post status name
			$mod['post_author'] = (int) get_post_field( 'post_author', $mod_id );		// post author id

			// hooked by the 'coauthors' pro module
			return apply_filters( $this->p->lca.'_get_post_mod', $mod, $mod_id );
		}

		public function get_posts( array $mod, $posts_per_page = false, $paged = false ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			if ( $posts_per_page === false ) {
				$posts_per_page = apply_filters( $this->p->lca.'_posts_per_page', get_option( 'posts_per_page' ), $mod );
			}

			if ( $paged === false ) {
				$paged = get_query_var( 'paged' );
			}

			if ( ! $paged > 1 ) {
				$paged = 1;
			}

			$posts = get_posts( array(
				'posts_per_page' => $posts_per_page,
				'paged' => $paged,
				'post_status' => 'publish',
				'post_type' => 'any',
				'post_parent' => $mod['id'],
				'child_of' => $mod['id'],	// only include direct children
				'has_password' => false,	// since wp 3.9
			) );

			return $posts;
		}

		/*
		 * Filters the wp shortlink for a post - returns the shortened sharing URL.
		 * The wp_shortlink_wp_head() function calls wp_get_shortlink( 0, 'query' );
		 */
		public function get_sharing_shortlink( $shortlink = false, $post_id = 0, $context = 'post', $allow_slugs = true ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->log_args( array( 
					'shortlink' => $shortlink, 
					'post_id' => $post_id, 
					'context' => $context, 
					'allow_slugs' => $allow_slugs, 
				) );
			}

			self::$cache_short_url = null;	// just in case

			if ( isset( self::$cache_shortlinks[$post_id][$context][$allow_slugs] ) ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'returning shortlink / short_url (from static cache) = '.
						self::$cache_shortlinks[$post_id][$context][$allow_slugs] );
				}
				return self::$cache_short_url = self::$cache_shortlinks[$post_id][$context][$allow_slugs];
			}

			// just in case, check to make sure we have a plugin shortener selected
			if ( empty( $this->p->options['plugin_shortener'] ) || $this->p->options['plugin_shortener'] === 'none' ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'exiting early: no shortening service defined' );
				}
				return $shortlink;	// return original shortlink
			}

			/*
			 * The WordPress link-template.php functions call wp_get_shortlink() with a post ID of 0.
			 * Recreate the same code here to get a real post ID and create a default shortlink (if required).
			 */
			if ( $post_id === 0 ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'provided post id is 0 (current post)' );
				}

				if ( $context === 'query' && is_singular() ) {	// wp_get_shortlink() uses the same logic
					$post_id = get_queried_object_id();
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( 'setting post id '.$post_id.' from queried object' );
					}
				} elseif ( $context === 'post' ) {
					$post_obj = get_post();
					if ( empty( $post_obj->ID ) ) {
						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( 'exiting early: post object ID is empty' );
						}
						return $shortlink;	// return original shortlink
					} else {
						$post_id = $post_obj->ID;
						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( 'setting post id '.$post_id.' from post object' );
						}
					}
				}

				if ( empty( $post_id ) ) {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( 'exiting early: unable to determine the post id' );
					}
					return $shortlink;	// return original shortlink
				}

				if ( empty( $shortlink ) ) {
					if ( get_post_type( $post_id ) === 'page' && get_option( 'page_on_front' ) == $post_id && get_option( 'show_on_front' ) == 'page' ) {
						$shortlink = home_url( '/' );
					} else {
						$shortlink = home_url( '?p='.$post_id );
					}
				}
			} elseif ( ! is_numeric( $post_id ) ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'exiting early: post_id argument is not numeric' );
				}
				return $shortlink;	// return original shortlink
			}

			$mod = $this->get_mod( $post_id );

			if ( empty( $mod['post_type'] ) ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'exiting early: post_type is empty' );
				}
				return $shortlink;	// return original shortlink
			} elseif ( empty( $mod['post_status'] ) ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'exiting early: post_status is empty' );
				}
				return $shortlink;	// return original shortlink
			} elseif ( $mod['post_status'] === 'auto-draft' ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'exiting early: post_status is auto-draft' );
				}
				return $shortlink;	// return original shortlink
			}

			$sharing_url = $this->p->util->get_sharing_url( $mod, false );	// $add_page = false
			$service_key = $this->p->options['plugin_shortener'];
			$short_url = apply_filters( $this->p->lca.'_get_short_url', $sharing_url, $service_key, $mod, $context );

			if ( $sharing_url === $short_url ) {	// shortened failed
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'exiting early: short URL ('.$short_url.') returned is identical to long URL.' );
				}
				return $shortlink;	// return original shortlink
			} elseif ( filter_var( $short_url, FILTER_VALIDATE_URL ) === false ) {	// invalid url
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'exiting early: invalid short URL ('.$short_url.') returned by filter' );
				}
				return $shortlink;	// return original shortlink
			}

			if ( $this->p->debug->enabled ) {
				$this->p->debug->log( 'returning shortlink / short_url = '.$short_url );
			}

			return self::$cache_short_url = self::$cache_shortlinks[$post_id][$context][$allow_slugs] = $short_url;	// success - return short url
		}

		public function restore_sharing_shortlink( $shortlink = false, $post_id = 0, $context = 'post', $allow_slugs = true ) {

			if ( self::$cache_short_url === $shortlink ) {	// shortlink value has not changed
				self::$cache_short_url = null;	// just in case
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'exiting early: shortlink / short_url value has not changed' );
				}
				return $shortlink;
			}

			self::$cache_short_url = null;	// just in case

			if ( isset( self::$cache_shortlinks[$post_id][$context][$allow_slugs] ) ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'restoring shortlink / short_url '.$shortlink.' to '.
						self::$cache_shortlinks[$post_id][$context][$allow_slugs] );
				}
				return self::$cache_shortlinks[$post_id][$context][$allow_slugs];
			}

			return $shortlink;
		}

		public function add_post_column_headings( $columns ) { 
			return $this->add_mod_column_headings( $columns, 'post' );
		}

		public function add_media_column_headings( $columns ) { 
			return $this->add_mod_column_headings( $columns, 'media' );
		}

		public function show_column_content( $column_name, $post_id ) {
			echo $this->get_column_content( '', $column_name, $post_id );
		}

		public function get_column_content( $value, $column_name, $post_id ) {
			if ( ! empty( $post_id ) && strpos( $column_name, $this->p->lca.'_' ) === 0 ) {	// just in case
				$col_idx = str_replace( $this->p->lca.'_', '', $column_name );
				if ( ( $col_info = self::get_sortable_columns( $col_idx ) ) !== null ) {
					if ( isset( $col_info['meta_key'] ) ) {	// just in case
						// optimize and check wp_cache first
						$meta_cache = wp_cache_get( $post_id, 'post_meta' );
						if ( isset( $meta_cache[$col_info['meta_key']][0] ) ) {
							$value = (string) maybe_unserialize( $meta_cache[$col_info['meta_key']][0] );
						} else {
							$value = (string) get_post_meta( $post_id, $col_info['meta_key'], true );	// $single = true
						}
						if ( $value === 'none' ) {
							$value = '';
						}
					}
				}
			}
			return $value;
		}

		public function update_sortable_meta( $post_id, $col_idx, $content ) { 
			if ( ! empty( $post_id ) ) {	// just in case
				if ( ( $col_info = self::get_sortable_columns( $col_idx ) ) !== null ) {
					if ( isset( $col_info['meta_key'] ) ) {	// just in case
						update_post_meta( $post_id, $col_info['meta_key'], $content );
					}
				}
			}
		}

		public function check_sortable_metadata( $value, $post_id, $meta_key, $single ) {

			static $do_once = array();

			if ( strpos( $meta_key, '_'.$this->p->lca.'_head_info_' ) !== 0 ) {	// example: _ngfb_head_info_og_img_thumb
				return $value;	// return null
			}

			if ( isset( $do_once[$post_id][$meta_key] ) ) {
				return $value;	// return null
			} else {
				$do_once[$post_id][$meta_key] = true;	// prevent recursion
			}

			if ( get_post_meta( $post_id, $meta_key, true ) === '' ) {	// returns empty string if meta not found
				$mod = $this->get_mod( $post_id );
				$head_meta_tags = $this->p->head->get_head_array( $post_id, $mod, true );	// $read_cache = true
				$head_meta_info = $this->p->head->extract_head_info( $mod, $head_meta_tags );
			}

			return $value;	// return null
		}

		// hooked into the current_screen action
		// sets the NgfbMeta::$head_meta_tags and NgfbMeta::$head_meta_info class properties
		public function load_meta_page( $screen = false ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			// all meta modules set this property, so use it to optimize code execution
			if ( NgfbMeta::$head_meta_tags !== false || ! isset( $screen->id ) ) {
				return;
			}

			if ( $this->p->debug->enabled ) {
				$this->p->debug->log( 'screen id: '.$screen->id );
			}

			switch ( $screen->id ) {
				case 'upload':
				case ( strpos( $screen->id, 'edit-' ) === 0 ? true : false ):	// posts list table
					return;
			}

			$post_obj = SucomUtil::get_post_object( true );
			$post_id = empty( $post_obj->ID ) ? 0 : $post_obj->ID;

			// make sure we have at least a post type and status
			if ( ! is_object( $post_obj ) ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'exiting early: post_obj is not an object' );
				}
				return;
			} elseif ( empty( $post_obj->post_type ) ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'exiting early: post_type is empty' );
				}
				return;
			} elseif ( empty( $post_obj->post_status ) ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'exiting early: post_status is empty' );
				}
				return;
			}

			$mod = $this->get_mod( $post_id );

			if ( $this->p->debug->enabled ) {
				$this->p->debug->log( 'home url = '.get_option( 'home' ) );
				$this->p->debug->log( 'locale default = '.SucomUtil::get_locale( 'default' ) );
				$this->p->debug->log( 'locale current = '.SucomUtil::get_locale( 'current' ) );
				$this->p->debug->log( 'locale mod = '.SucomUtil::get_locale( $mod ) );
				$this->p->debug->log( SucomDebug::pretty_array( $mod ) );
			}

			if ( $post_obj->post_status === 'auto-draft' ) {

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'head meta skipped: post_status is auto-draft' );
				}

				NgfbMeta::$head_meta_tags = array();

			} else {

				$add_metabox = empty( $this->p->options['plugin_add_to_'.$post_obj->post_type] ) ? false : true;
				$add_metabox = apply_filters( $this->p->lca.'_add_metabox_post', $add_metabox, $post_id, $post_obj->post_type );

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'add metabox for post ID '.$post_id.' of type '.$post_obj->post_type.' is '.
						( $add_metabox ? 'true' : 'false' ) );
				}

				if ( $add_metabox ) {

					// hooked by woocommerce module to load front-end libraries and start a session
					do_action( $this->p->lca.'_admin_post_head', $mod, $screen->id );

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( 'setting head_meta_info static property' );
					}

					// $read_cache is false to generate notices etc.
					NgfbMeta::$head_meta_tags = $this->p->head->get_head_array( $post_id, $mod, false );
					NgfbMeta::$head_meta_info = $this->p->head->extract_head_info( $mod, NgfbMeta::$head_meta_tags );

					if ( $post_obj->post_status === 'publish' ) {

						// check for missing open graph image and description values
						foreach ( array( 'image', 'description' ) as $mt_suffix ) {
							if ( empty( NgfbMeta::$head_meta_info['og:'.$mt_suffix] ) ) {
								if ( $this->p->debug->enabled ) {
									$this->p->debug->log( 'og:'.$mt_suffix.' meta tag is value empty and required' );
								}
								if ( $this->p->notice->is_admin_pre_notices() ) {	// skip if notices already shown
									$this->p->notice->err( $this->p->msgs->get( 'notice-missing-og-'.$mt_suffix ) );
								}
							}
						}

						// check duplicates only when the post is available publicly and we have a valid permalink
						if ( current_user_can( 'manage_options' ) ) {
							if ( apply_filters( $this->p->lca.'_check_post_head', $this->p->options['plugin_check_head'], $post_id, $post_obj ) ) {
								$this->check_post_head_duplicates( $post_id, $post_obj );
							}
						}
					}

				} else {
					NgfbMeta::$head_meta_tags = array();
				}
			} 

			$action_query = $this->p->lca.'-action';

			if ( ! empty( $_GET[$action_query] ) ) {
				$action_name = SucomUtil::sanitize_hookname( $_GET[$action_query] );
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'found action query: '.$action_name );
				}
				if ( empty( $_GET[ NGFB_NONCE_NAME ] ) ) {	// NGFB_NONCE_NAME is an md5() string
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( 'nonce token query field missing' );
					}
				} elseif ( ! wp_verify_nonce( $_GET[ NGFB_NONCE_NAME ], NgfbAdmin::get_nonce_action() ) ) {
					$this->p->notice->err( sprintf( __( 'Nonce token validation failed for %1$s action "%2$s".',
						'nextgen-facebook' ), 'post', $action_name ) );
				} else {
					$_SERVER['REQUEST_URI'] = remove_query_arg( array( $action_query, NGFB_NONCE_NAME ) );
					switch ( $action_name ) {
						default: 
							do_action( $this->p->lca.'_load_meta_page_post_'.$action_name, $post_id, $post_obj );
							break;
					}
				}
			}
		}

		public function check_post_head_duplicates( $post_id = true, $post_obj = false ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			$is_admin = is_admin();	// check once
			$short = $this->p->cf['plugin'][$this->p->lca]['short'];

			if ( empty( $this->p->options['plugin_check_head'] ) ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'exiting early: plugin_check_head option is disabled');
				}
				return;	// stop here
			}

			if ( ! apply_filters( $this->p->lca.'_add_meta_name_'.$this->p->lca.':mark', true ) ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'exiting early: '.$this->p->lca.':mark meta tags are disabled');
				}
				return;	// stop here
			}

			if ( empty( $post_id ) ) {
				$post_id = true;
			}

			if ( ! is_object( $post_obj ) ) {
				$post_obj = SucomUtil::get_post_object( $post_id );
				if ( empty( $post_obj ) ) {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( 'exiting early: unable to get the post object');
					}
					return;	// stop here
				}
			}

			// just in case post_id is true/false
			if ( ! is_numeric( $post_id ) ) {
				if ( empty( $post_obj->ID ) ) {	// just in case
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( 'exiting early: post id in post object is empty');
					}
					return;	// stop here
				}
				$post_id = $post_obj->ID;
			}

			// only check publicly available posts
			if ( ! isset( $post_obj->post_status ) || $post_obj->post_status !== 'publish' ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'exiting early: post_status \''.$post_obj->post_status.'\' not published');
				}
				return;	// stop here
			}

			// only check public post types (to avoid menu items, product variations, etc.)
			$ptns = $this->p->util->get_post_types( 'names' );

			if ( empty( $post_obj->post_type ) || ! in_array( $post_obj->post_type, $ptns ) ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'exiting early: post_type \''.$post_obj->post_type.'\' not public' );
				}
				return;	// stop here
			}

			$exec_count = $this->p->debug->enabled ? 0 : (int) get_option( NGFB_POST_CHECK_NAME );		// cast to change false to 0
			$max_count = SucomUtil::get_const( 'NGFB_DUPE_CHECK_HEADER_COUNT' );

			if ( $exec_count >= $max_count ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'exiting early: exec_count of '.$exec_count.' exceeds max_count of '.$max_count );
				}
				return;	// stop here
			}

			if ( ini_get( 'open_basedir' ) ) {	// cannot follow redirects
				$mod = $this->get_mod( $mod );
				$check_url = $this->p->util->get_sharing_url( $mod, false );	// $add_page = false
			} else {
				$check_url = SucomUtilWP::wp_get_shortlink( $post_id, 'post' );	// $context = post
			}

			$check_url_htmlenc = SucomUtil::encode_html_emoji( urldecode( $check_url ) );

			if ( empty( $check_url ) ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'exiting early: invalid shortlink' );
				}
				return;	// stop here
			}

			if ( $this->p->debug->enabled ) {
				$this->p->debug->log( 'checking '.$check_url.' head meta for duplicates' );
			}

			$clear_shortlink = SucomUtil::get_const( 'NGFB_DUPE_CHECK_CLEAR_SHORTLINK', true );

			if ( $clear_shortlink ) {
				$this->p->cache->clear( $check_url );	// clear cache before fetching shortlink url
			}

			if ( $is_admin ) {
				if ( $clear_shortlink ) {
					$this->p->notice->inf( sprintf( __( 'Checking %1$s for duplicate meta tags...', 'nextgen-facebook' ), 
						'<a href="'.$check_url.'">'.$check_url_htmlenc.'</a>' ) );
				} else {
					$this->p->notice->inf( sprintf( __( 'Checking %1$s for duplicate meta tags (webpage could be from cache)...', 'nextgen-facebook' ), 
						'<a href="'.$check_url.'">'.$check_url_htmlenc.'</a>' ) );
				}
			}

			/*
			 * Fetch HTML using the Facebook user agent to get Open Graph meta tags.
			 */
			$curl_opts = array( 'CURLOPT_USERAGENT' => WPSSO_PHP_CURL_USERAGENT_FACEBOOK );
			$html = $this->p->cache->get( $check_url, 'raw', 'transient', false, '', $curl_opts );
			$in_secs = $this->p->cache->in_secs( $check_url );
			$warning_secs = (int) SucomUtil::get_const( 'NGFB_DUPE_CHECK_WARNING_SECS', 2.5 );
			$timeout_secs = (int) SucomUtil::get_const( 'NGFB_DUPE_CHECK_TIMEOUT_SECS', 3.0 );

			if ( $in_secs === true ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'fetched '.$check_url.' from transient cache' );
				}
			} elseif ( $in_secs === false ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'fetched '.$check_url.' returned a failure' );
				}
			} else {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'fetched '.$check_url.' in '.$in_secs.' secs' );
				}
				if ( $is_admin && $in_secs > $warning_secs ) {
					$this->p->notice->warn(
						sprintf( __( 'Retrieving the HTML document for %1$s took %2$s seconds.',
							'nextgen-facebook' ), '<a href="'.$check_url.'">'.$check_url_htmlenc.'</a>', $in_secs ).' '.
						sprintf( __( 'This exceeds the recommended limit of %1$s seconds (crawlers often time-out after %2$s seconds).',
							'nextgen-facebook' ), $warning_secs, $timeout_secs ).' '.
						__( 'Please consider improving the speed of your site.',
							'nextgen-facebook' ).' '.
						__( 'As an added benefit, a faster site will also improve ranking in search results.',
							'nextgen-facebook' ).' ;-)'
					);
				}
			}

			if ( empty( $html ) ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'exiting early: error retrieving webpage from '.$check_url );
				}
				if ( $is_admin ) {
					$this->p->notice->err( sprintf( __( 'Error retrieving webpage from <a href="%1$s">%1$s</a>.',
						'nextgen-facebook' ), $check_url ) );
				}
				return;	// stop here
			} elseif ( stripos( $html, '<html' ) === false ) {	// webpage must have an <html> tag
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'exiting early: <html> tag not found in '.$check_url );
				}
				if ( $is_admin ) {
					$this->p->notice->err( sprintf( __( 'An &lt;html&gt; tag was not found in <a href="%1$s">%1$s</a>.',
						'nextgen-facebook' ), $check_url ) );
				}
				return;	// stop here
			} elseif ( ! preg_match( '/<meta[ \n]/i', $html ) ) {	// webpage must have one or more <meta/> tags
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'exiting early: No <meta/> HTML tags were found in '.$check_url );
				}
				if ( $is_admin ) {
					$this->p->notice->err( sprintf( __( 'No %1$s HTML tags were found in <a href="%2$s">%2$s</a>.',
						'nextgen-facebook' ), '&lt;meta/&gt;', $check_url ) );
				}
				return;	// stop here
			} elseif ( strpos( $html, $this->p->lca.' meta tags begin' ) === false ) {	// webpage should include our own meta tags
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'exiting early: '.$this->p->lca.' meta tag section not found in '.$check_url );
				}
				if ( $is_admin ) {
					$this->p->notice->err( sprintf( __( 'A %2$s meta tag section was not found in <a href="%1$s">%1$s</a> &mdash; perhaps a webpage caching plugin or service needs to be refreshed?', 'nextgen-facebook' ), $check_url, $short ) );
				}
				return;	// stop here
			}

			if ( $this->p->debug->enabled ) {
				$this->p->debug->log( 'removing '.$this->p->lca.' meta tag section' );
			}

			$html = preg_replace( $this->p->head->get_mt_mark( 'preg' ), '', $html, -1, $mark_count );

			if ( ! $mark_count ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'exiting early: preg_replace() function failed to remove the meta tag section' );
				}
				if ( $is_admin ) {
					$this->p->notice->err( sprintf( __( 'The PHP preg_replace() function failed to remove the %1$s meta tag section &mdash; this could be an indication of a problem with PHP\'s PCRE library or a webpage filter corrupting the %1$s meta tags.', 'nextgen-facebook' ), $short ) );
				}
				return;	// stop here
			}

			// providing html, so no need to specify a user agent
			$metas = $this->p->util->get_head_meta( $html, '/html/head/link|/html/head/meta', true );	// false on error
			$check_opts = SucomUtil::preg_grep_keys( '/^add_/', $this->p->options, false, '' );
			$conflicts_msg = __( 'Conflict detected &mdash; your theme or another plugin is adding %1$s to the head section of this webpage.', 'nextgen-facebook' );
			$conflicts_found = 0;

			if ( is_array( $metas ) ) {
				if ( empty( $metas ) ) {	// no link or meta tags found
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( 'error parsing head meta for '.$check_url );
					}
					if ( $is_admin ) {
						$w3c_html_check_url = 'https://validator.w3.org/nu/?doc='.urlencode( $check_url );
						$pinterest_tab_url = $this->p->util->get_admin_url( 'general#sucom-tabset_pub-tab_pinterest' );
						$this->p->notice->err( sprintf( __( 'An error occured parsing the head meta tags from <a href="%1$s">%1$s</a>.', 'nextgen-facebook' ), $check_url ).' '.sprintf( __( 'The webpage may contain serious HTML syntax errors &mdash; please review the <a href="%1$s">W3C Markup Validation Service</a> results and correct any errors.', 'nextgen-facebook' ), $w3c_html_check_url ).' '.sprintf( __( 'You may safely ignore any "nopin" attribute errors, or disable the "nopin" attribute under the <a href="%s">Pinterest settings tab</a>.', 'nextgen-facebook' ), $pinterest_tab_url ) );
					}
				} else {
					foreach( array(
						'link' => array( 'rel' ),
						'meta' => array( 'name', 'property', 'itemprop' ),
					) as $tag => $types ) {
						if ( isset( $metas[$tag] ) ) {
							foreach( $metas[$tag] as $meta ) {
								foreach( $types as $type ) {
									if ( isset( $meta[$type] ) && $meta[$type] !== 'generator' && 
										! empty( $check_opts[$tag.'_'.$type.'_'.$meta[$type]] ) ) {
										$conflicts_found++;
										$conflicts_tag = '<code>'.$tag.' '.$type.'="'.$meta[$type].'"</code>';
										$this->p->notice->err( sprintf( $conflicts_msg, $conflicts_tag ) );
									}
								}
							}
						}
					}
					if ( $is_admin ) {
						if ( $conflicts_found ) {
							$warn_msg = __( '%1$s duplicate meta tags found. Check %2$s of %3$s failed (will try again later)...', 'nextgen-facebook' );
							$this->p->notice->warn( sprintf( $warn_msg, $conflicts_found, $exec_count, $max_count ) );
						} else {
							if ( $this->p->debug->enabled ) {
								$inf_msg = __( 'Awesome! No duplicate meta tags found. :-) Debug option is enabled - will keep repeating duplicate check...', 'nextgen-facebook' );
							} else {
								$inf_msg = __( 'Awesome! No duplicate meta tags found. :-) Check %2$s of %3$s successful...', 'nextgen-facebook' );
							}
							$exec_count++;
							update_option( NGFB_POST_CHECK_NAME, $exec_count, false );	// autoload = false
							$this->p->notice->inf( sprintf( $inf_msg, $conflicts_found, $exec_count, $max_count ) );
						}
					}
				}
			}
		}

		public function add_meta_boxes() {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			if ( ( $post_obj = SucomUtil::get_post_object( true ) ) === false || empty( $post_obj->post_type ) ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'exiting early: object without post type' );
				}
				return;
			} else {
				$post_id = empty( $post_obj->ID ) ? 0 : $post_obj->ID;
			}

			if ( ( $post_obj->post_type === 'page' && ! current_user_can( 'edit_page', $post_id ) ) || ! current_user_can( 'edit_post', $post_id ) ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'insufficient privileges to add metabox for '.$post_obj->post_type.' ID '.$post_id );
				}
				return;
			}

			$metabox_id = $this->p->cf['meta']['id'];
			$metabox_title = _x( $this->p->cf['meta']['title'], 'metabox title', 'nextgen-facebook' );
			$add_metabox = empty( $this->p->options[ 'plugin_add_to_'.$post_obj->post_type ] ) ? false : true;
			$add_metabox = apply_filters( $this->p->lca.'_add_metabox_post', $add_metabox, $post_id, $post_obj->post_type );

			if ( $this->p->debug->enabled ) {
				$this->p->debug->log( 'add metabox for post ID '.$post_id.' of type '.$post_obj->post_type.' is '.
					( $add_metabox ? 'true' : 'false' ) );
			}

			if ( $add_metabox ) {
				add_meta_box( $this->p->lca.'_'.$metabox_id, $metabox_title,
					array( &$this, 'show_metabox_custom_meta' ),
						$post_obj->post_type, 'normal', 'low' );
			}
		}

		public function show_metabox_custom_meta( $post_obj ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
				$this->p->debug->log( 'post id = '.( empty( $post_obj->ID ) ? 0 : $post_obj->ID ) );
				$this->p->debug->log( 'post type = '.( empty( $post_obj->post_type ) ? 'empty' : $post_obj->post_type ) );
				$this->p->debug->log( 'post status = '.( empty( $post_obj->post_status ) ? 'empty' : $post_obj->post_status ) );
			}

			$metabox_id = $this->p->cf['meta']['id'];
			$mod = $this->get_mod( $post_obj->ID );
			$tabs = $this->get_custom_meta_tabs( $metabox_id, $mod );
			$opts = $this->get_options( $post_obj->ID );
			$def_opts = $this->get_defaults( $post_obj->ID );
			$this->form = new SucomForm( $this->p, NGFB_META_NAME, $opts, $def_opts, $this->p->lca );

			wp_nonce_field( NgfbAdmin::get_nonce_action(), NGFB_NONCE_NAME );

			if ( $this->p->debug->enabled )
				$this->p->debug->mark( $metabox_id.' table rows' );	// start timer

			$table_rows = array();
			foreach ( $tabs as $key => $title ) {
				$table_rows[$key] = array_merge( $this->get_table_rows( $metabox_id, $key, NgfbMeta::$head_meta_info, $mod ), 
					apply_filters( $this->p->lca.'_'.$mod['name'].'_'.$key.'_rows', array(), $this->form, NgfbMeta::$head_meta_info, $mod ) );
			}
			$this->p->util->do_metabox_tabs( $metabox_id, $tabs, $table_rows );

			if ( $this->p->debug->enabled )
				$this->p->debug->mark( $metabox_id.' table rows' );	// end timer
		}

		protected function get_table_rows( &$metabox_id, &$key, &$head, &$mod ) {

			$is_auto_draft = empty( $mod['post_status'] ) || 
				$mod['post_status'] === 'auto-draft' ? true : false;
			$auto_draft_msg = sprintf( __( 'Save a draft version or publish the %s to update this value.',
				'nextgen-facebook' ), SucomUtil::titleize( $mod['post_type'] ) );

			$table_rows = array();
			switch ( $key ) {
				case 'preview':
					$table_rows = $this->get_rows_social_preview( $this->form, $head, $mod );
					break;

				case 'tags':	
					if ( $is_auto_draft ) {
						$table_rows[] = '<td><blockquote class="status-info"><p class="centered">'.
							$auto_draft_msg.'</p></blockquote></td>';
					} else {
						$table_rows = $this->get_rows_head_tags( $this->form, $head, $mod );
					}
					break; 

				case 'validate':
					if ( $is_auto_draft ) {
						$table_rows[] = '<td><blockquote class="status-info"><p class="centered">'.
							$auto_draft_msg.'</p></blockquote></td>';
					} else {
						$table_rows = $this->get_rows_validate( $this->form, $head, $mod );
					}
					break; 
			}
			return $table_rows;
		}

		public function clear_cache_for_new_comment( $comment_id, $comment_approved ) {
			if ( $comment_id && $comment_approved === 1 ) {
				if ( ( $comment = get_comment( $comment_id ) ) && $comment->comment_post_ID ) {
					$post_id = $comment->comment_post_ID;
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( 'clearing post_id '.$post_id.' cache for comment_id '.$comment_id );
					}
					$this->clear_cache( $post_id );
				}
			}
		}

		public function clear_cache_for_comment_status( $comment_id, $comment_status ) {
			if ( $comment_id ) {	// just in case
				if ( ( $comment = get_comment( $comment_id ) ) && $comment->comment_post_ID ) {
					$post_id = $comment->comment_post_ID;
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( 'clearing post_id '.$post_id.' cache for comment_id '.$comment_id );
					}
					$this->clear_cache( $post_id );
				}
			}
		}

		public function clear_cache( $post_id, $rel_id = false ) {

			switch ( get_post_status( $post_id ) ) {
				case 'draft':
				case 'pending':
				case 'future':
				case 'private':
				case 'publish':
					break;	// stop here
				default:
					return;
			}

			$mod = $this->get_mod( $post_id );
			$cache_md5_pre = $this->p->lca.'_';
			$permalink = get_permalink( $post_id );

			if ( ini_get( 'open_basedir' ) ) {
				$check_url = $this->p->util->get_sharing_url( $mod, false );	// $add_page = false
			} else {
				$check_url = SucomUtilWP::wp_get_shortlink( $post_id, 'post' );	// $context = post
			}

			$cache_types = array();
			$cache_types['transient'][] = $cache_md5_pre.md5( 'SucomCache::get(url:'.$permalink.')' );

			if ( $permalink !== $check_url ) {
				$cache_types['transient'][] = $cache_md5_pre.md5( 'SucomCache::get(url:'.$check_url.')' );
			}

			$this->clear_mod_cache_types( $mod, $cache_types );

			if ( function_exists( 'w3tc_pgcache_flush_post' ) ) {	// w3 total cache
				w3tc_pgcache_flush_post( $post_id );
			}

			if ( function_exists( 'wp_cache_post_change' ) ) {	// wp super cache
				wp_cache_post_change( $post_id );
			}
		}

		public function get_og_type_reviews( $post_id, $og_type = 'product', $rating_meta = 'rating' ) {

			$ret = array();

			if ( empty( $post_id ) ) {
				return $ret;
			}

			$comments = get_comments( array(
				'post_id' => $post_id,
				'status' => 'approve',
				'parent' => 0,	// don't get replies
				'order' => 'DESC',
				'number' => get_option( 'page_comments' ),	// limit number of comments
			) );

			if ( is_array( $comments ) ) {
				foreach( $comments as $num => $comment_obj ) {
					$og_review = $this->get_og_review_mt( $comment_obj, $og_type, $rating_meta );
					if ( ! empty( $og_review ) ) {	// just in case
						$ret[] = $og_review;
					}
				}
			}

			return $ret;
		}

		public function get_og_review_mt( $comment_obj, $og_type = 'product', $rating_meta = 'rating' ) {

			$ret = array();
			$rating_value = (float) get_comment_meta( $comment_obj->comment_ID, $rating_meta, true );

			$ret[$og_type.':review:id'] = $comment_obj->comment_ID;
			$ret[$og_type.':review:url'] = get_comment_link( $comment_obj->comment_ID );
			$ret[$og_type.':review:author:id'] = $comment_obj->user_id;	// author ID if registered (0 otherwise)
			$ret[$og_type.':review:author:name'] = $comment_obj->comment_author;	// author display name
			$ret[$og_type.':review:created_time'] = mysql2date( 'c', $comment_obj->comment_date_gmt );
			$ret[$og_type.':review:excerpt'] = get_comment_excerpt( $comment_obj->comment_ID );

			// rating values must be larger than 0 to include rating info
			if ( $rating_value > 0 ) {
				$ret[$og_type.':review:rating:value'] = $rating_value;
				$ret[$og_type.':review:rating:worst'] = 1;
				$ret[$og_type.':review:rating:best'] = 5;
			}

			return $ret;
		}
	}
}


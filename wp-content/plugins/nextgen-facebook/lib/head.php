<?php
/*
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2012-2017 Jean-Sebastien Morisset (https://surniaulula.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'These aren\'t the droids you\'re looking for...' );
}

if ( ! class_exists( 'NgfbHead' ) ) {

	class NgfbHead {

		private $p;

		public function __construct( &$plugin ) {
			$this->p =& $plugin;

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			add_action( 'wp_head', array( &$this, 'show_head' ), NGFB_HEAD_PRIORITY );
			add_action( 'amp_post_template_head', array( &$this, 'show_head' ), NGFB_HEAD_PRIORITY );

			// crawlers are only seen on the front-end, so skip if in back-end
			if ( ! is_admin() && $this->p->avail['*']['vary_ua'] ) {
				$this->vary_user_agent_check();
			}
		}

		public function vary_user_agent_check() {

			// query argument used to bust external caches
			$crawler_arg = 'uaid';

			if ( strpos( $this->get_head_cache_index( false ), 'uaid:' ) !== false ) {	// custom crawler found

				$crawler_name = SucomUtil::get_crawler_name();

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'custom crawler cache index found for '.$crawler_name );

				}
				if ( ! defined( 'DONOTCACHEPAGE' ) ) {	// define as true
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( 'defining DONOTCACHEPAGE as true' );
					}
					define( 'DONOTCACHEPAGE', true );
				} elseif ( DONOTCACHEPAGE ) {	// already defined as true
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( 'DONOTCACHEPAGE already defined as true' );
					}
				} else {	// already defined as false
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( 'error defining DONOTCACHEPAGE - constant already defined as false' );
					}
				}

				// add a query argument for this crawler and redirect to bust external caches
				if ( empty( $_GET[$crawler_arg] ) || $_GET[$crawler_arg] !== $crawler_name ) {
					wp_redirect( add_query_arg( $crawler_arg, $crawler_name, remove_query_arg( $crawler_arg ) ) );	// 302 by default
					exit;
				}

			// if not a custom crawler, then remove the query (if set) and redirect
			} elseif ( isset( $_GET[$crawler_arg] ) ) {
				wp_redirect( remove_query_arg( $crawler_arg ) );	// 302 by default
				exit;
			}

			add_filter( 'wp_headers', array( &$this, 'add_vary_user_agent_header' ) );
		}

		public function add_vary_user_agent_header( $headers ) {
			$headers['Vary'] = 'User-Agent';
			return $headers;
		}

		// $mixed = 'default' | 'current' | post ID | $mod array
		public function get_head_cache_index( $mixed = 'current', $sharing_url = false ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			$cache_index = '';

			if ( $mixed !== false ) {
				$cache_index .= '_locale:'.SucomUtil::get_locale( $mixed );
			}

			if ( $sharing_url !== false ) {
				$cache_index .= '_url:'.$sharing_url;
			}

			if ( SucomUtil::is_amp() ) {
				$cache_index .= '_amp:true';
			}

			// crawlers are only seen on the front-end, so skip if in back-end
			if ( ! is_admin() && $this->p->avail['*']['vary_ua'] ) {
				$crawler_name = SucomUtil::get_crawler_name();
				switch ( $crawler_name ) {
					case 'pinterest':
						$cache_index .= '_uaid:'.$crawler_name;
						break;
				}
			}

			$cache_index = trim( $cache_index, '_' );	// cleanup leading underscores
			$cache_index = apply_filters( $this->p->lca.'_head_cache_index', $cache_index, $mixed, $sharing_url );

			return $cache_index;
		}

		// called by wp_head action
		public function show_head() {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			$use_post = apply_filters( $this->p->lca.'_use_post', false );	// used by woocommerce with is_shop()
			if ( $this->p->debug->enabled ) {
				$this->p->debug->log( 'required call to get_page_mod()' );
			}
			$mod = $this->p->util->get_page_mod( $use_post );	// get post/user/term id, module name, and module object reference
			$read_cache = true;
			$mt_og = array();

			if ( $this->p->debug->enabled ) {
				$this->p->debug->log( 'home url = '.get_option( 'home' ) );
				$this->p->debug->log( 'locale default = '.SucomUtil::get_locale( 'default' ) );
				$this->p->debug->log( 'locale current = '.SucomUtil::get_locale( 'current' ) );
				$this->p->debug->log( 'locale mod = '.SucomUtil::get_locale( $mod ) );
				$this->p->util->log_is_functions();
			}

			$add_head_html = apply_filters( $this->p->lca.'_add_head_html', $this->p->avail['*']['head_html'], $mod );

			if ( $this->p->debug->enabled ) {
				$this->p->debug->log( 'avail head_html = '.( $this->p->avail['*']['head_html'] ? 'true' : 'false' ) );
				$this->p->debug->log( 'add_head_html = '.( $add_head_html ? 'true' : 'false' ) );
			}

			if ( $add_head_html ) {
				echo $this->get_head_html( $use_post, $mod, $read_cache, $mt_og );
			} else {
				echo "\n<!-- ".$this->p->lca." head html is disabled -->\n";
			}

			if ( $this->p->debug->enabled ) {
				$this->p->debug->log( 'end of get_head_html' );
			}
		}

		// extract certain key fields for display and sanity checks
		public function extract_head_info( array $mod, array $head_mt ) {

			$head_info = array();

			foreach ( $head_mt as $mt ) {
				if ( ! isset( $mt[2] ) || ! isset( $mt[3] ) ) {
					continue;
				}

				$mt_match = $mt[2].'-'.$mt[3];

				switch ( $mt_match ) {
					case 'property-og:type':
					case 'property-og:title':
					case 'property-og:description':
					case 'property-article:author:name':
					case ( strpos( $mt_match, 'name-schema:' ) === 0 ? true : false ):
						if ( ! isset( $head_info[$mt[3]] ) ) {	// only save the first meta tag value
							$head_info[$mt[3]] = $mt[5];
						}
						break;
					case ( preg_match( '/^property-((og|p):(image|video))(:secure_url|:url)?$/', $mt_match, $m ) ? true : false ):
						if ( ! empty( $mt[5] ) ) {
							$has_media[$m[1]] = true;	// optimize media loop
						}
						break;
				}
			}

			/*
			 * Save the first image and video information found. Assumes array key order
			 * defined by SucomUtil::get_mt_prop_image() and SucomUtil::get_mt_prop_video().
			 */
			foreach ( array( 'og:image', 'og:video', 'p:image' ) as $prefix ) {
				if ( empty( $has_media[$prefix] ) ) {
					continue;
				}

				$is_first = false;

				foreach ( $head_mt as $mt ) {
					if ( ! isset( $mt[2] ) || ! isset( $mt[3] ) ) {
						continue;
					}

					if ( strpos( $mt[3], $prefix ) !== 0 ) {
						$is_first = false;

						// if we already found media, then skip to the next media prefix
						if ( ! empty( $head_info[$prefix] ) ) {
							continue 2;
						} else {
							continue;	// skip meta tags without matching prefix
						}
					}

					$mt_match = $mt[2].'-'.$mt[3];

					switch ( $mt_match ) {
						case ( preg_match( '/^property-'.$prefix.'(:secure_url|:url)?$/', $mt_match, $m ) ? true : false ):
							if ( ! empty( $head_info[$prefix] ) ) {	// only save the media URL once
								continue 2;			// get the next meta tag
							}
							if ( ! empty( $mt[5] ) ) {
								$head_info[$prefix] = $mt[5];	// save the media URL
								$is_first = true;
							}
							break;
						case ( preg_match( '/^property-'.$prefix.':(width|height|cropped|id|title|description)$/', $mt_match, $m ) ? true : false ):
							if ( $is_first !== true ) {		// only save for first media found
								continue 2;			// get the next meta tag
							}
							$head_info[$mt[3]] = $mt[5];
							break;
					}
				}
			}

			/*
			 * Save meta tag values for later sorting in list tables.
			 */
			foreach ( NgfbMeta::get_sortable_columns() as $col_idx => $col_info ) {
				if ( empty( $col_info['meta_key'] ) || strpos( $col_info['meta_key'], '_'.$this->p->lca.'_head_info_' ) !== 0 ) {
					continue;
				}
				$meta_value = 'none';
				switch ( $col_idx ) {
		 			case 'schema_type':
						if ( isset( $head_info['schema:type:id'] ) ) {
							$meta_value = $head_info['schema:type:id'];
						}
						break;
					case 'og_img':
						if ( $og_img = $mod['obj']->get_og_img_column_html( $head_info, $mod ) ) {
							$meta_value = $og_img;
						}
						break;
					case 'og_desc':
						if ( isset( $head_info['og:description'] ) ) {
							$meta_value = $head_info['og:description'];
						}
						break;
				}
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'sortable meta for '.$mod['name'].' id '.$mod['id'].' '.$col_idx.' = '.$meta_value );
				}
				$mod['obj']->update_sortable_meta( $mod['id'], $col_idx, $meta_value );
			}

			return $head_info;
		}

		public function get_mt_mark( $type ) {

			$ret = '';

			switch ( $type ) {
				case 'begin':
				case 'end':
					$add_meta = apply_filters( $this->p->lca.'_add_meta_name_'.$this->p->lca.':mark',
						( empty( $this->p->options['plugin_check_head'] ) ? false : true ) );

					$comment = '<!-- '.$this->p->lca.' meta tags '.$type.' -->';
					$mt_name = $add_meta ? '<meta name="'.$this->p->lca.':mark:'.$type.'" '.
						'content="'.$this->p->lca.' meta tags '.$type.'"/>'."\n" : '';

					if ( $type === 'begin' ) {
						$ret = "\n\n".$comment."\n".$mt_name;
					} else {
						$ret = $mt_name.$comment."\n";
					}

					break;

				case 'preg':
					/*
					 * Some HTML optimization plugins/services may remove the double-quotes from the name attribute, 
					 * along with the trailing space and slash characters, so make these optional in the regex.
					 */
					$prefix = '<(!--[\s\n\r]+|meta[\s\n\r]+name="?'.$this->p->lca.':mark:(begin|end)"?[\s\n\r]+content=")';
					$suffix = '([\s\n\r]+--|"[\s\n\r]*\/?)>';
		
					$ret = '/'.$prefix.$this->p->lca.' meta tags begin'.$suffix.'.*'.
						$prefix.$this->p->lca.' meta tags end'.$suffix.'/ums';	// enable utf8 support

					break;
			}

			return $ret;
		}

		public function get_head_html( $use_post = false, &$mod = false, $read_cache = true, array &$mt_og ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			if ( ! is_array( $mod ) ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'optional call to get_page_mod()' );
				}
				$mod = $this->p->util->get_page_mod( $use_post );	// get post/user/term id, module name, and module object reference
			}

			$start_time = microtime( true );
			$crawler_name = SucomUtil::get_crawler_name();
			$html = $this->get_mt_mark( 'begin' );

			// first element of returned array is the html tag
			$indent = 0;
			foreach ( $this->get_head_array( $use_post, $mod, $read_cache, $mt_og ) as $mt ) {
				if ( ! empty( $mt[0] ) ) {
					if ( $indent && strpos( $mt[0], '</noscript' ) === 0 ) {
						$indent = 0;
					}
					$html .= str_repeat( "\t", (int) $indent ).$mt[0];
					if ( strpos( $mt[0], '<noscript' ) === 0 ) {
						$indent = 1;
					}
				}
			}

			$html .= $this->get_mt_mark( 'end' );
			$html .= '<!-- added on '.date( 'c' ).' in '.sprintf( '%f secs', microtime( true ) - $start_time ).
				( $crawler_name !== 'none' ? ' for '.$crawler_name : '' ).' from '.SucomUtilWP::raw_home_url().' -->'."\n\n";

			return $html;
		}

		// $read_cache is false when called by post/term/user load_meta_page() method
		public function get_head_array( $use_post = false, &$mod = false, $read_cache = true, &$mt_og = array() ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark( 'build head array' );	// begin timer
			}

			// $mod is preferred but not required
			// $mod = true | false | post_id | $mod array
			if ( ! is_array( $mod ) ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'optional call to get_page_mod()' );
				}
				$mod = $this->p->util->get_page_mod( $use_post );	// get post/user/term id, module name, and module object reference
			}

			$sharing_url = $this->p->util->get_sharing_url( $mod, true, 'head_sharing_url' );	// $add_page = true

			if ( empty( $sharing_url ) ) {	// just in case
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'exiting early: get_sharing_url() returned an empty string' );
				}
				return array();
			}

			$is_admin = is_admin();	// call the function only once
			$crawler_name = SucomUtil::get_crawler_name();
			$head_array = array();
			$cache_index = 0;	// redefined if $cache_exp_secs > 0

			static $cache_exp_secs = null;	// filter the cache expiration value only once
			$cache_md5_pre = $this->p->lca.'_h_';
			if ( ! isset( $cache_exp_secs ) ) {	// filter cache expiration if not already set
				$cache_exp_filter = $this->p->cf['wp']['transient'][$cache_md5_pre]['filter'];
				$cache_opt_key = $this->p->cf['wp']['transient'][$cache_md5_pre]['opt_key'];
				$cache_exp_secs = (int) apply_filters( $cache_exp_filter, $this->p->options[$cache_opt_key] );
			}

			if ( $this->p->debug->enabled ) {
				$this->p->debug->log( 'sharing url = '.$sharing_url );
				$this->p->debug->log( 'crawler name = '.$crawler_name );
				$this->p->debug->log( 'cache expire = '.$cache_exp_secs );
			}

			if ( $cache_exp_secs > 0 ) {
				/*
				 * Note that cache_id is a unique identifier for the cached data and should be 45 characters or
				 * less in length. If using a site transient, it should be 40 characters or less in length.
				 */
				$cache_salt = __METHOD__.'('.SucomUtil::get_mod_salt( $mod, $sharing_url ).')';
				$cache_id = $cache_md5_pre.md5( $cache_salt );
				$cache_index = $this->get_head_cache_index( $mod, $sharing_url );

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'cache salt = '.$cache_salt );
					$this->p->debug->log( 'cache index = '.$cache_index );
				}

				if ( $read_cache ) {	// false when called by post/term/user load_meta_page() method
					$head_array = get_transient( $cache_id );
					if ( isset( $head_array[$cache_index] ) ) {
						if ( is_array( $head_array[$cache_index] ) ) {	// just in case
							if ( $this->p->debug->enabled ) {
								$this->p->debug->log( 'cache index found in transient '.$cache_id );
								$this->p->debug->mark( 'build head array' );	// end timer
							}
							return $head_array[$cache_index];	// stop here
						} elseif ( $this->p->debug->enabled ) {
							$this->p->debug->log( 'cache index is not an array' );
						}
					} else {
						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( 'cache index not in transient '.$cache_id );
						}
						if ( ! is_array( $head_array ) ) {	// just in case
							$head_array = array();
						}
					}
				} elseif ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'read cache for head is disabled' );
				}
			} elseif ( $this->p->debug->enabled ) {
				$this->p->debug->log( 'head array transient cache is disabled' );
			}

			// set reference values for admin notices
			$is_admin ? $this->p->notice->set_ref( $sharing_url, $mod ) : false;

			// define the author_id (if one is available)
			$author_id = NgfbUser::get_author_id( $mod );

			if ( $this->p->debug->enabled ) {
				$this->p->debug->log( 'author_id = '.( $author_id === false ? 'false' : $author_id ) );
			}

			/*
			 * Open Graph - define first to pass the mt_og array to other methods.
			 */
			$is_admin ? $this->p->notice->set_ref( $sharing_url, $mod, __( 'adding open graph meta tags', 'nextgen-facebook' ) ) : false;
			$mt_og = $this->p->og->get_array( $mod, $mt_og, $crawler_name );
			$is_admin ? $this->p->notice->unset_ref( $sharing_url ) : false;

			/*
			 * Weibo
			 */
			$mt_weibo = $this->p->weibo->get_array( $mod, $mt_og, $crawler_name );

			/*
			 * Twitter Cards
			 */
			$is_admin ? $this->p->notice->set_ref( $sharing_url, $mod, __( 'adding twitter card meta tags', 'nextgen-facebook' ) ) : false;
			$mt_tc = $this->p->tc->get_array( $mod, $mt_og, $crawler_name );
			$is_admin ? $this->p->notice->unset_ref( $sharing_url ) : false;

			/*
			 * Name / SEO meta tags
			 */
			$mt_name = array();

			if ( ! empty( $this->p->options['add_meta_name_author'] ) ) {
				// fallback for authors without a Facebook page URL in their user profile
				if ( empty( $mt_og['article:author'] ) &&
					is_object( $this->p->m['util']['user'] ) ) {	// just in case

					$mt_name['author'] = $this->p->m['util']['user']->get_author_meta( $author_id,
						$this->p->options['fb_author_name'] );
				}
			}

			if ( apply_filters( $this->p->lca.'_add_meta_name_description',
				( empty( $this->p->options['add_meta_name_description'] ) ? false : true ) ) ) {
				$mt_name['description'] = $this->p->page->get_description( $this->p->options['seo_desc_len'],
					'...', $mod, true, false, true, 'seo_desc' );	// add_hashtags = false
			}

			if ( ! empty( $this->p->options['add_meta_name_p:domain_verify'] ) ) {
				if ( ! empty( $this->p->options['p_dom_verify'] ) ) {
					$mt_name['p:domain_verify'] = $this->p->options['p_dom_verify'];
				}
			}

			$mt_name = (array) apply_filters( $this->p->lca.'_meta_name', $mt_name, $mod );

			/*
			 * Link relation tags
			 */
			$link_rel = $this->p->link_rel->get_array( $mod, $mt_og, $crawler_name, $author_id );

			/*
			 * Schema meta tags
			 */
			$is_admin ? $this->p->notice->set_ref( $sharing_url, $mod, __( 'adding schema meta tags', 'nextgen-facebook' ) ) : false;
			$mt_schema = $this->p->schema->get_meta_array( $mod, $mt_og, $crawler_name );
			$is_admin ? $this->p->notice->unset_ref( $sharing_url ) : false;

			/*
			 * JSON-LD script
			 */
			$is_admin ? $this->p->notice->set_ref( $sharing_url, $mod, __( 'adding schema json-ld markup', 'nextgen-facebook' ) ) : false;
			$mt_json_array = $this->p->schema->get_json_array( $mod, $mt_og, $crawler_name );
			$is_admin ? $this->p->notice->unset_ref( $sharing_url ) : false;

			/*
			 * Generator meta tags
			 */
			$mt_generators['generator'] = $this->p->check->get_ext_list();

			/*
			 * Combine and return all meta tags
			 */
			$mt_og = $this->p->og->sanitize_array( $mod, $mt_og );	// unset mis-matched og_type meta tags

			$head_array[$cache_index] = array_merge(
				$this->get_mt_array( 'meta', 'name', $mt_generators, $mod ),
				$this->get_mt_array( 'link', 'rel', $link_rel, $mod ),
				$this->get_mt_array( 'meta', 'property', $mt_og, $mod ),
				$this->get_mt_array( 'meta', 'name', $mt_weibo, $mod ),
				$this->get_mt_array( 'meta', 'name', $mt_tc, $mod ),
				$this->get_mt_array( 'meta', 'itemprop', $mt_schema, $mod ),
				$this->get_mt_array( 'meta', 'name', $mt_name, $mod ),	// seo description is last
				$this->p->schema->get_noscript_array( $mod, $mt_og, $crawler_name ),
				$mt_json_array
			);

			if ( $cache_exp_secs > 0 ) {
				// update the cached array and maintain the existing transient expiration time
				SucomUtil::update_transient_array( $cache_id, $head_array, $cache_exp_secs );
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'head array saved to transient for '.$cache_exp_secs.' seconds' );
				}
			}

			// unset the general reference value for admin notices
			$is_admin ? $this->p->notice->unset_ref( $sharing_url ) : false;

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark( 'build head array' );	// end timer
			}

			return $head_array[$cache_index];
		}

		/*
		 * Loops through the arrays and calls get_single_mt() for each
		 */
		private function get_mt_array( $tag, $type, array &$mt_array, array &$mod ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->log( count( $mt_array ).' '.$tag.' '.$type.' to process' );
				$this->p->debug->log( $mt_array );
			}

			if ( empty( $mt_array ) ) {
				return array();
			} elseif ( ! is_array( $mt_array ) ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'exiting early: mt_array argument is not an array' );
				}
				return array();
			}

			$singles = array();
			foreach ( $mt_array as $d_name => $d_val ) {	// first dimension array (associative)

				if ( is_array( $d_val ) ) {

					// skip product offer and review arrays
					if ( preg_match( '/:(offers|reviews)$/', $d_name ) ) {
						continue;

					} elseif ( empty( $d_val ) ) {	// allow hooks to modify the value
						$singles[] = $this->get_single_mt( $tag,
							$type, $d_name, null, '', $mod );

					} else foreach ( $d_val as $dd_num => $dd_val ) {	// second dimension array

						if ( SucomUtil::is_assoc( $dd_val ) ) {

							// prevent duplicates - ignore images from text/html video
							if ( isset( $dd_val['og:video:type'] ) && $dd_val['og:video:type'] === 'text/html' ) {

								// skip if text/html video markup is disabled
								if ( empty( $this->p->options['og_vid_html_type'] ) ) {
									continue;
								}

								unset( $dd_val['og:video:embed_url'] );	// redundant - just in case

								$ignore_images = true;

							} else {
								$ignore_images = false;
							}

							foreach ( $dd_val as $ddd_name => $ddd_val ) {	// third dimension array (associative)

								// prevent duplicates - ignore images from text/html video
								if ( $ignore_images && strpos( $ddd_name, 'og:image' ) !== false ) {
									continue;
								}

								if ( is_array( $ddd_val ) ) {
									if ( empty( $ddd_val ) ) {
										$singles[] = $this->get_single_mt( $tag,
											$type, $ddd_name, null, '', $mod );
									} else foreach ( $ddd_val as $dddd_num => $dddd_val ) {	// fourth dimension array
										$singles[] = $this->get_single_mt( $tag,
											$type, $ddd_name, $dddd_val, $d_name.':'.
												( $dd_num + 1 ), $mod );
									}
								} else $singles[] = $this->get_single_mt( $tag,
									$type, $ddd_name, $ddd_val, $d_name.':'.
										( $dd_num + 1 ), $mod );
							}
						} else $singles[] = $this->get_single_mt( $tag,
							$type, $d_name, $dd_val, $d_name.':'.
								( $dd_num + 1 ), $mod );
					}
				} else $singles[] = $this->get_single_mt( $tag,
					$type, $d_name, $d_val, '', $mod );
			}

			$merged = array();

			foreach ( $singles as $num => $element ) {
				foreach ( $element as $parts ) {
					$merged[] = $parts;
				}
				unset ( $singles[$num] );
			}

			return $merged;
		}

		public function get_single_mt( $tag, $type, $name, $value, $cmt, array &$mod ) {

			// check for known exceptions for the 'property' $type
			if ( $tag === 'meta' ) {
				if ( $type === 'property' ) {
					// double-check the name to make sure its an open graph meta tag
					switch ( $name ) {
						// these names are not open graph meta tag names
						case ( strpos( $name, 'twitter:' ) === 0 ? true : false ):
						case ( strpos( $name, 'schema:' ) === 0 ? true : false ):	// internal meta tags
						case ( strpos( $name, ':' ) === false ? true : false ):		// no colon in $name
							$type = 'name';
							break;
					}
				} elseif ( $type === 'itemprop' ) {
					// use filter_var() instead of strpos() to exclude (description) strings that contain urls
					if ( $tag !== 'link' && filter_var( $value, FILTER_VALIDATE_URL ) !== false ) {	// itemprop urls must be links
						$tag = 'link';
					}
				}
			}

			// applies to both "link rel href" and "link itemprop href"
			if ( $tag === 'link' ) {
				$attr = 'href';
			// everything else uses the 'content' attribute name
			} else {
				$attr = 'content';
			}

			$ret = array();
			$log_prefix = $tag.' '.$type.' '.$name;

			static $charset = null;
			if ( ! isset( $charset  ) ) {
				$charset = get_bloginfo( 'charset' );
			}

			if ( is_array( $value ) ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( $log_prefix.' value is an array (skipped)' );
				}
				return $ret;
			} elseif ( is_object( $value ) ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( $log_prefix.' value is an object (skipped)' );
				}
				return $ret;
			}

			if ( strpos( $value, '%%' ) !== false ) {
				$value = $this->p->util->replace_inline_vars( $value, $mod );
			}

			switch ( $name ) {
				case 'og:image:secure_url':
				case 'og:video:secure_url':
					if ( strpos( $value, 'https:' ) !== 0 ) {	// just in case
						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( $log_prefix.' is not https (skipped)' );
						}
						return $ret;
					}
					break;
			}

			$ret[] = array( '', $tag, $type, $name, $attr, $value, $cmt );

			// $parts = array( $html, $tag, $type, $name, $attr, $value, $cmt );
			foreach ( $ret as $num => $parts ) {

				if ( ! isset( $parts[6] ) ) {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( 'parts array is incomplete (skipped)' );
						$this->p->debug->log_arr( '$parts', $parts );
					}
					continue;
				}

				// filtering of single meta tags can be enabled by defining NGFB_APPLY_FILTERS_SINGLE_MT as true
				if ( SucomUtil::get_const( 'NGFB_APPLY_FILTERS_SINGLE_MT' ) ) {
					$parts = $this->apply_filters_single_mt( $parts, $mod );
				}

				$log_prefix = $parts[1].' '.$parts[2].' '.$parts[3];

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( $log_prefix.' = "'.$parts[5].'"' );
				}

				if ( $parts[5] === '' || $parts[5] === null ) {	// allow for 0
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $log_prefix.' value is empty (skipped)' );
					}
				} elseif ( $parts[5] === NGFB_UNDEF_INT || $parts[5] === (string) NGFB_UNDEF_INT ) {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $log_prefix.' value is '.NGFB_UNDEF_INT.' (skipped)' );
					}
				} else {
					/*
					 * Encode and escape all values, regardless if the meta tag is enabled or not.
					 * If the meta tag is enabled, HTML will be created and saved in $parts[0].
					 */
					if ( $parts[2] === 'itemprop' && strpos( $parts[3], '.' ) !== 0 ) {
						$match_name = preg_replace( '/^.*\./', '', $parts[3] );
					} else {
						$match_name = $parts[3];
					}

					// boolean values are converted to their string equivalent
					if ( is_bool( $parts[5] ) ) {
						$parts[5] = $parts[5] ? 'true' : 'false';
					}

					switch ( $match_name ) {
						// description values that may include emoji
						case 'og:title':
						case 'og:description':
						case 'twitter:title':
						case 'twitter:description':
						case 'description':
						case 'name':
							$parts[5] = SucomUtil::encode_html_emoji( $parts[5] );
							break;
						// url values that must be url encoded
						case 'og:url':
						case 'og:secure_url':
						case 'og:image':
						case 'og:image:url':
						case 'og:image:secure_url':
						case 'og:video':
						case 'og:video:url':
						case 'og:video:secure_url':
						case 'og:video:embed_url':
						case 'place:business:menu_url':
						case 'place:business:order_url':
						case 'twitter:image':
						case 'twitter:player':
						case 'canonical':
						case 'shortlink':
						case 'image':
						case 'menu':	// place restaurant menu url
						case 'url':
							$parts[5] = SucomUtil::esc_url_encode( $parts[5] );
							if ( $parts[2] === 'itemprop' ) {	// itemprop urls must be links
								$parts[1] = 'link';
								$parts[4] = 'href';
							}
							break;
						// encode html entities for everything else
						default:
							$parts[5] = htmlentities( $parts[5],
								ENT_QUOTES, $charset, false );	// double_encode = false
							break;
					}

					// convert mixed case itemprop names (for example) to lower case
					$opt_name = strtolower( 'add_'.$parts[1].'_'.$parts[2].'_'.$parts[3] );

					if ( ! empty( $this->p->options[$opt_name] ) ) {
						$parts[0] = ( empty( $parts[6] ) ? '' : '<!-- '.$parts[6].' -->' ).
							'<'.$parts[1].' '.$parts[2].'="'.$match_name.'" '.$parts[4].'="'.$parts[5].'"/>'."\n";
					} elseif ( $this->p->debug->enabled ) {
						$this->p->debug->log( $log_prefix.' is disabled (skipped)' );
					}

					$ret[$num] = $parts;	// save the HTML and encoded value
				}
			}

			return $ret;
		}

		// filtering of single meta tags can be enabled by defining NGFB_APPLY_FILTERS_SINGLE_MT as true
		// $parts = array( $html, $tag, $type, $name, $attr, $value, $cmt );
		private function apply_filters_single_mt( array &$parts, array &$mod ) {

			$log_prefix = $parts[1].' '.$parts[2].' '.$parts[3];
			$filter_name = $this->p->lca.'_'.$parts[1].'_'.$parts[2].'_'.$parts[3].'_'.$parts[4];
			$new_value = apply_filters( $filter_name, $parts[5], $parts[6], $mod );

			if ( $parts[5] !== $new_value ) {
				if ( $this->p->debug->enabled )
					$this->p->debug->log( $log_prefix.' (original) = "'.$parts[5].'"' );
				if ( is_array( $new_value ) ) {
					foreach( $new_value as $key => $value ) {
						$this->p->debug->log( $log_prefix.' (filtered:'.$key.') = "'.$value.'"' );
						$parts[6] = $parts[3].':'.( is_numeric( $key ) ? $key + 1 : $key );
						$parts[5] = $value;
					}
				} else {
					$this->p->debug->log( $log_prefix.' (filtered) = "'.$new_value.'"' );
					$parts[5] = $new_value;
				}
			}
			return $parts;
		}
	}
}


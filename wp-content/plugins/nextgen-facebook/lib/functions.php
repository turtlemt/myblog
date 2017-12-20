<?php
/*
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2012-2017 Jean-Sebastien Morisset (https://surniaulula.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'These aren\'t the droids you\'re looking for...' );
}

if ( ! function_exists( 'ngfb_is_mobile' ) ) {
	function ngfb_is_mobile() {
		// return null if the content is not allowed to vary
		if ( ! SucomUtil::get_const( 'NGFB_VARY_USER_AGENT_DISABLE' ) ) {
			return SucomUtil::is_mobile();
		} else {
			return null;
		}
	}
}

if ( ! function_exists( 'ngfb_schema_attributes' ) ) {
	function ngfb_schema_attributes( $attr = '' ) {
		$ngfb =& Ngfb::get_instance();
		if ( is_object( $ngfb->schema ) ) {
			echo $ngfb->schema->filter_head_attributes( $attr );
		}
	}
}

if ( ! function_exists( 'ngfb_clear_all_cache' ) ) {
	function ngfb_clear_all_cache( $clear_external = false ) {
		$ngfb =& Ngfb::get_instance();
		if ( is_object( $ngfb->util ) ) {
			$dismiss_key = __FUNCTION__.'_function';
			$ngfb->util->clear_all_cache( $clear_external, null, $dismiss_key );
		}
	}
}

if ( ! function_exists( 'ngfb_clear_post_cache' ) ) {
	function ngfb_clear_post_cache( $post_id ) {
		$ngfb =& Ngfb::get_instance();
		if ( isset( $ngfb->m['util']['post'] ) ) {	// just in case
			$ngfb->m['util']['post']->clear_cache( $post_id );
		}
	}
}

if ( ! function_exists( 'ngfb_get_page_mod' ) ) {
	function ngfb_get_page_mod( $use_post = false ) {
		$ngfb =& Ngfb::get_instance();
		if ( is_object( $ngfb->util ) ) {
			return $ngfb->util->get_page_mod( $use_post );
		} else {
			return false;
		}
	}
}

if ( ! function_exists( 'ngfb_get_post_mod' ) ) {
	function ngfb_get_post_mod( $post_id ) {
		$ngfb =& Ngfb::get_instance();
		if ( isset( $ngfb->m['util']['post'] ) ) {
			return $ngfb->m['util']['post']->get_mod( $post_id );
		} else {
			return false;
		}
	}
}

if ( ! function_exists( 'ngfb_get_term_mod' ) ) {
	function ngfb_get_term_mod( $term_id ) {
		$ngfb =& Ngfb::get_instance();
		if ( isset( $ngfb->m['util']['term'] ) ) {
			return $ngfb->m['util']['term']->get_mod( $term_id );
		} else {
			return false;
		}
	}
}

if ( ! function_exists( 'ngfb_get_user_mod' ) ) {
	function ngfb_get_user_mod( $user_id ) {
		$ngfb =& Ngfb::get_instance();
		if ( isset( $ngfb->m['util']['user'] ) ) {
			return $ngfb->m['util']['user']->get_mod( $user_id );
		} else {
			return false;
		}
	}
}

if ( ! function_exists( 'ngfb_get_sharing_url' ) ) {
	function ngfb_get_sharing_url( $mod = false, $add_page = true ) {
		$ngfb =& Ngfb::get_instance();
		if ( is_object( $ngfb->util ) ) {
			return $ngfb->util->get_sharing_url( $mod, $add_page );
		} else {
			return false;
		}
	}
}

if ( ! function_exists( 'ngfb_get_short_url' ) ) {
	function ngfb_get_short_url( $mod = false, $add_page = true ) {
		$ngfb =& Ngfb::get_instance();
		$sharing_url = $ngfb->util->get_sharing_url( $mod, $add_page );
		$service_key = $ngfb->options['plugin_shortener'];
		return apply_filters( 'ngfb_get_short_url', $sharing_url, $service_key, $mod, $mod['name'] );
	}
}

/*
 * Sharing Buttons
 */
if ( ! function_exists( 'ngfb_get_sharing_buttons' ) ) {
	function ngfb_get_sharing_buttons( $ids = array(), $atts = array(), $cache_exp_secs = false ) {

		$ngfb =& Ngfb::get_instance();

		if ( $ngfb->debug->enabled ) {
			$ngfb->debug->mark();
		}

		$error_msg = false;

		if ( ! is_array( $ids ) ) {
			$error_msg = 'sharing button ids must be an array';
			error_log( __FUNCTION__.'() error: '.$error_msg );
		} elseif ( ! is_array( $atts ) ) {
			$error_msg = 'sharing button attributes must be an array';
			error_log( __FUNCTION__.'() error: '.$error_msg );
		} elseif ( ! $ngfb->avail['p_ext']['ssb'] ) {
			$error_msg = 'sharing buttons are disabled';
		} elseif ( empty( $ids ) ) {	// nothing to do
			$error_msg = 'no buttons requested';
		}

		if ( $error_msg !== false ) {
			if ( $ngfb->debug->enabled ) {
				$ngfb->debug->log( 'exiting early: '.$error_msg );
			}
			return '<!-- '.__FUNCTION__.' exiting early: '.$error_msg.' -->'."\n";
		}

		$atts['use_post'] = SucomUtil::sanitize_use_post( $atts );

		if ( $ngfb->debug->enabled ) {
			$ngfb->debug->log( 'required call to get_page_mod()' );
		}
		$mod = $ngfb->util->get_page_mod( $atts['use_post'] );

		$lca = $ngfb->cf['lca'];
		$type = __FUNCTION__;
		$sharing_url = $ngfb->util->get_sharing_url( $mod );
		$buttons_array = array();

		$cache_md5_pre = $lca.'_b_';
		$cache_exp_secs = $cache_exp_secs === false ? $ngfb->sharing->get_buttons_cache_exp() : $cache_exp_secs;
		$cache_index = 0;	// redefined if $cache_exp_secs > 0

		if ( $ngfb->debug->enabled ) {
			$ngfb->debug->log( 'sharing url = '.$sharing_url );
			$ngfb->debug->log( 'cache expire = '.$cache_exp_secs );
		}

		if ( $cache_exp_secs > 0 ) {

			$cache_salt = __FUNCTION__.'('.SucomUtil::get_mod_salt( $mod, $sharing_url ).')';
			$cache_id = $cache_md5_pre.md5( $cache_salt );
			$cache_index = $ngfb->sharing->get_buttons_cache_index( $type, $atts, $ids );

			if ( $ngfb->debug->enabled ) {
				$ngfb->debug->log( 'cache salt = '.$cache_salt );
				$ngfb->debug->log( 'cache index = '.$cache_index );
			}

			$buttons_array = get_transient( $cache_id );

			if ( isset( $buttons_array[$cache_index] ) ) {
				if ( $ngfb->debug->enabled ) {
					$ngfb->debug->log( $type.' cache index found in array from transient '.$cache_id );
				}
			} else {
				if ( $ngfb->debug->enabled ) {
					$ngfb->debug->log( $type.' cache index not in array from transient '.$cache_id );
				}
				if ( ! is_array( $buttons_array ) ) {	// just in case
					$buttons_array = array();
				}
			}
		} else {
			if ( $ngfb->debug->enabled ) {
				$ngfb->debug->log( $type.' buttons array transient cache is disabled' );
			}
		}

		if ( ! isset( $buttons_array[$cache_index] ) ) {

			// returns html or an empty string
			$buttons_array[$cache_index] = $ngfb->sharing->get_html( $ids, $atts, $mod );

			if ( ! empty( $buttons_array[$cache_index] ) ) {
				$buttons_array[$cache_index] = '
<!-- '.$lca.' '.__FUNCTION__.' function begin -->
<!-- generated on '.date( 'c' ).' -->'."\n".
$ngfb->sharing->get_script( 'sharing-buttons-header', $ids ).
$buttons_array[$cache_index]."\n".	// buttons html is trimmed, so add newline
$ngfb->sharing->get_script( 'sharing-buttons-footer', $ids ).
'<!-- '.$lca.' '.__FUNCTION__.' function end -->'."\n\n";

				if ( $cache_exp_secs > 0 ) {
					// update the cached array and maintain the existing transient expiration time
					$expires_in_secs = SucomUtil::update_transient_array( $cache_id, $buttons_array, $cache_exp_secs );
					if ( $ngfb->debug->enabled ) {
						$ngfb->debug->log( $type.' buttons html saved to transient cache (expires in '.$expires_in_secs.' seconds)' );
					}
				}
			}
		}

		return $buttons_array[$cache_index];
	}
}


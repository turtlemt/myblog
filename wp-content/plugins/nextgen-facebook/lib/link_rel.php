<?php
/*
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2012-2017 Jean-Sebastien Morisset (https://wpsso.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'These aren\'t the droids you\'re looking for...' );
}

if ( ! class_exists( 'NgfbLinkRel' ) ) {

	class NgfbLinkRel {

		private $p;

		public function __construct( &$plugin ) {
			$this->p =& $plugin;

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			$add_link_rel_shortlink = empty( $this->p->options['add_link_rel_shortlink'] ) ? false : true;

			// remove the 'wp_shortlink_wp_head' hook so we can add our own shortlink meta tag
			if ( $add_link_rel_shortlink ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'removing default wp_shortlink_wp_head action' );
				}
				remove_action( 'wp_head', 'wp_shortlink_wp_head' );
			}
		}

		public function get_array( array &$mod, array &$mt_og, $crawler_name, $author_id ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			$lca = $this->p->cf['lca'];
			$sharing_url = empty( $mt_og['og:url'] ) ? $this->p->util->get_sharing_url( $mod ) : $mt_og['og:url'];
			$link_rel = apply_filters( $lca.'_link_rel_seed', array(), $mod );

			/*
			 * link rel author
			 */
			if ( ! empty( $author_id ) ) {
				$add_link_rel_author = empty( $this->p->options['add_link_rel_author'] ) ? false : true;
				if ( apply_filters( $lca.'_add_link_rel_author', $add_link_rel_author, $mod ) ) {
					if ( is_object( $this->p->m['util']['user'] ) ) {	// just in case
						$link_rel['author'] = $this->p->m['util']['user']->get_author_website( $author_id,
							$this->p->options['seo_author_field'] );
					}
				}
			} elseif ( $this->p->debug->enabled ) {
				$this->p->debug->log( 'skipping author: author id is empty' );
			}

			/*
			 * link rel canonical
			 */
			$add_link_rel_canonical = empty( $this->p->options['add_link_rel_canonical'] ) ? false : true;

			if ( apply_filters( $lca.'_add_link_rel_canonical', $add_link_rel_canonical, $mod ) ) {
				$link_rel['canonical'] = $this->p->util->get_canonical_url( $mod );
			}

			/*
			 * link rel publisher
			 */
			if ( ! empty( $this->p->options['seo_publisher_url'] ) ) {
				$add_link_rel_publisher = empty( $this->p->options['add_link_rel_publisher'] ) ? false : true;
				if ( apply_filters( $lca.'_add_link_rel_publisher', $add_link_rel_publisher, $mod ) ) {
					$link_rel['publisher'] = $this->p->options['seo_publisher_url'];
				}
			} elseif ( $this->p->debug->enabled ) {
				$this->p->debug->log( 'skipping publisher: seo publisher url is empty' );
			}

			/*
			 * link rel shortlink
			 */
			$add_link_rel_shortlink = empty( $this->p->options['add_link_rel_shortlink'] ) || is_404() || is_search() ? false : true;

			if ( $add_link_rel_shortlink ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'pre-filter add_link_rel_shortlink is true' );
				}
			} elseif ( $this->p->debug->enabled ) {
				$this->p->debug->log( 'pre-filter add_link_rel_shortlink is false' );
			}

			if ( apply_filters( $lca.'_add_link_rel_shortlink', $add_link_rel_shortlink, $mod ) ) {
				$shortlink = '';
				if ( $mod['is_post'] ) {
					$wp_shortlink_value = wp_get_shortlink( $mod['id'], 'post' );
					$shortlink = SucomUtilWP::wp_get_shortlink( $mod['id'], 'post' );	// $context = post
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( 'wp_get_shortlink() for post id '.$mod['id'].' = '.$wp_shortlink_value );
						$this->p->debug->log( 'SucomUtilWP::wp_get_shortlink() = '.$shortlink );
					}
				} elseif ( ! empty( $mt_og['og:url'] ) ) {	// just in case
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( 'using '.$lca.'_get_short_url filters to get shortlink' );
					}
					$service_key = $this->p->options['plugin_shortener'];
					$shortlink = apply_filters( $lca.'_get_short_url', $sharing_url, $service_key, $mod, $mod['name'] );
				}
				if ( empty( $shortlink ) ) {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( 'skipping shortlink: short url is empty' );
					}
				} elseif ( $shortlink === $sharing_url ) {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( 'skipping shortlink: short url is same as sharing url' );
					}
				} else {
					$link_rel['shortlink'] = $shortlink;
				}
			} elseif ( $this->p->debug->enabled ) {
				$this->p->debug->log( 'skipping shortlink: add_link_rel_shortlink filter returned false' );
			}

			return (array) apply_filters( $lca.'_link_rel', $link_rel, $mod );
		}
	}
}


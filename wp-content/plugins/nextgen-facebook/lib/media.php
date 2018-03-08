<?php
/*
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2012-2017 Jean-Sebastien Morisset (https://surniaulula.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'These aren\'t the droids you\'re looking for...' );
}

if ( ! class_exists( 'NgfbMedia' ) ) {

	class NgfbMedia {

		private $p;
		private $def_img_preg = array(
			'html_tag' => 'img',
			'pid_attr' => 'data-[a-z]+-pid',
			'ngg_src' => '[^\'"]+\/cache\/([0-9]+)_(crop)?_[0-9]+x[0-9]+_[^\/\'"]+|[^\'"]+-nggid0[1-f]([0-9]+)-[^\'"]+',
		);
		private static $image_src_info = null;

		public function __construct( &$plugin ) {
			$this->p =& $plugin;

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			add_action( 'init', array( &$this, 'allow_img_data_attributes' ) );

			// prevent image_downsize from lying about image width and height
			if ( is_admin() ) {
				add_filter( 'editor_max_image_size', array( &$this, 'editor_max_image_size' ), 10, 3 );
			}

			add_filter( 'wp_get_attachment_image_attributes', array( &$this, 'add_attachment_image_attributes' ), 10, 2 );
			add_filter( 'get_image_tag', array( &$this, 'get_image_tag' ), 10, 6 );
			add_filter( 'get_header_image_tag', array( &$this, 'get_header_image_tag' ), 10, 3 );
		}

		public function allow_img_data_attributes() {
			global $allowedposttags;
			$allowedposttags['img']['data-wp-pid'] = true;
			if ( ! empty( $this->p->options['p_add_nopin_media_img_tag'] ) ) {
				$allowedposttags['img']['nopin'] = true;
			}
		}

		// note that $size_name can be a string or an array()
		public function editor_max_image_size( $max_sizes = array(), $size_name = '', $context = '' ) {
			// allow only our sizes to exceed the editor width
			if ( is_string( $size_name ) &&
				strpos( $size_name, $this->p->cf['lca'].'-' ) === 0 ) {
					$max_sizes = array( 0, 0 );
			}
			return $max_sizes;
		}

		// $attr = apply_filters( 'wp_get_attachment_image_attributes', $attr, $attachment );
		public function add_attachment_image_attributes( $attr, $attach ) {
			$attr['data-wp-pid'] = $attach->ID;
			if ( ! empty( $this->p->options['p_add_nopin_media_img_tag'] ) ) {
				$attr['nopin'] = 'nopin';
			}
			return $attr;
		}

		// $html = apply_filters( 'get_image_tag', $html, $id, $alt, $title, $align, $size );
		public function get_image_tag( $html, $id, $alt, $title, $align, $size ) {
			return $this->add_header_image_tag( $html, array(
				'data-wp-pid' => $id,
				'nopin' => empty( $this->p->options['p_add_nopin_media_img_tag'] ) ? false : 'nopin'
			) );
		}

		// $html = apply_filters( 'get_header_image_tag', $html, $header, $attr );
		public function get_header_image_tag( $html, $header, $attr ) {
			return $this->add_header_image_tag( $html, array(
				'nopin' => empty( $this->p->options['p_add_nopin_header_img_tag'] ) ? false : 'nopin'
			) );
		}

		private function add_header_image_tag( $html, $add_attr ) {
			foreach ( $add_attr as $attr_name => $attr_value ) {
				if ( $attr_value !== false && strpos( $html, ' '.$attr_name.'=' ) === false ) {
					$html = preg_replace( '/ *\/?'.'>/', ' '.$attr_name.'="'.$attr_value.'"$0', $html );
				}
			}
			return $html;
		}

		public function get_post_images( $num = 0, $size_name = 'thumbnail', $post_id, $check_dupes = true, $md_pre = 'og' ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->log_args( array(
					'num' => $num,
					'size_name' => $size_name,
					'post_id' => $post_id,
					'check_dupes' => $check_dupes,
					'md_pre' => $md_pre,
				) );
			}

			$og_ret = array();
			$force_regen = $this->p->util->is_force_regen( $post_id, $md_pre );	// false by default

			if ( ! empty( $post_id ) ) {

				// get_og_images() also provides filter hooks for additional image ids and urls
				// unless $md_pre is 'none', get_og_images() will fallback to the 'og' custom meta
				$og_ret = array_merge( $og_ret, $this->p->m['util']['post']->get_og_images( 1,
					$size_name, $post_id, $check_dupes, $force_regen, $md_pre ) );
			}

			// allow for empty post_id in order to execute featured / attached image filters for modules
			if ( ! $this->p->util->is_maxed( $og_ret, $num ) ) {
				$num_diff = SucomUtil::count_diff( $og_ret, $num );
				$og_ret = array_merge( $og_ret, $this->get_featured( $num_diff,
					$size_name, $post_id, $check_dupes, $force_regen ) );
			}

			// 'ngfb_attached_images' filter is used by the buddypress module
			if ( ! $this->p->util->is_maxed( $og_ret, $num ) ) {
				$num_diff = SucomUtil::count_diff( $og_ret, $num );
				$og_ret = array_merge( $og_ret, $this->get_attached_images( $num_diff,
					$size_name, $post_id, $check_dupes, $force_regen ) );
			}

			return $og_ret;
		}

		public function get_featured( $num = 0, $size_name = 'thumbnail', $post_id, $check_dupes = true, $force_regen = false ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->log_args( array(
					'num' => $num,
					'size_name' => $size_name,
					'post_id' => $post_id,
					'check_dupes' => $check_dupes,
					'force_regen' => $force_regen,
				) );
			}

			$og_ret = array();
			$og_single_image = SucomUtil::get_mt_prop_image();

			if ( ! empty( $post_id ) ) {
				// check for an attachment page, just in case
				if ( ( is_attachment( $post_id ) || get_post_type( $post_id ) === 'attachment' ) &&
					wp_attachment_is_image( $post_id ) ) {

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( 'post_type is an attachment - using post_id '.$post_id. ' as the image ID' );
					}
					$pid = $post_id;

				} elseif ( $this->p->avail['*']['featured'] == true && has_post_thumbnail( $post_id ) ) {
					$pid = get_post_thumbnail_id( $post_id );
				} else {
					$pid = false;
				}

				if ( ! empty( $pid ) ) {
					list(
						$og_single_image['og:image'],
						$og_single_image['og:image:width'],
						$og_single_image['og:image:height'],
						$og_single_image['og:image:cropped'],
						$og_single_image['og:image:id']
					) = $this->get_attachment_image_src( $pid, $size_name, $check_dupes, $force_regen );

					if ( ! empty( $og_single_image['og:image'] ) ) {
						$this->p->util->push_max( $og_ret, $og_single_image, $num );
					}
				}
			}
			return apply_filters( $this->p->cf['lca'].'_og_featured', $og_ret, $num,
				$size_name, $post_id, $check_dupes, $force_regen );
		}

		public function get_first_attached_image_id( $post_id ) {
			if ( ! empty( $post_id ) ) {
				// check for an attachment page, just in case
				if ( ( is_attachment( $post_id ) || get_post_type( $post_id ) === 'attachment' ) &&
					wp_attachment_is_image( $post_id ) ) {
					return $post_id;
				} else {
					$images = get_children( array( 'post_parent' => $post_id,
						'post_type' => 'attachment', 'post_mime_type' => 'image' ) );
					$attach = reset( $images );
					if ( ! empty( $attach->ID ) ) {
						return $attach->ID;
					}
				}
			}
			return false;
		}

		public function get_attachment_image( $num = 0, $size_name = 'thumbnail', $attach_id, $check_dupes = true, $force_regen = false ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->log_args( array(
					'num' => $num,
					'size_name' => $size_name,
					'attach_id' => $attach_id,
					'check_dupes' => $check_dupes,
					'force_regen' => $force_regen,
				) );
			}

			$og_ret = array();
			$og_single_image = SucomUtil::get_mt_prop_image();

			if ( ! empty( $attach_id ) ) {
				if ( wp_attachment_is_image( $attach_id ) ) {	// since wp 2.1.0
					list(
						$og_single_image['og:image'],
						$og_single_image['og:image:width'],
						$og_single_image['og:image:height'],
						$og_single_image['og:image:cropped'],
						$og_single_image['og:image:id']
					) = $this->get_attachment_image_src( $attach_id, $size_name, $check_dupes, $force_regen );

					if ( ! empty( $og_single_image['og:image'] ) &&
						$this->p->util->push_max( $og_ret, $og_single_image, $num ) ) {
						return $og_ret;
					}
				} elseif ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'attachment id '.$attach_id.' is not an image' );
				}
			}
			return $og_ret;
		}

		public function get_attached_images( $num = 0, $size_name = 'thumbnail', $post_id, $check_dupes = true, $force_regen = false ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->log_args( array(
					'num' => $num,
					'size_name' => $size_name,
					'post_id' => $post_id,
					'check_dupes' => $check_dupes,
					'force_regen' => $force_regen,
				) );
			}

			$og_ret = array();
			$og_single_image = SucomUtil::get_mt_prop_image();

			if ( ! empty( $post_id ) ) {

				$images = get_children( array(
					'post_parent' => $post_id,
					'post_type' => 'attachment',
					'post_mime_type' => 'image'
				), OBJECT );	// OBJECT, ARRAY_A, or ARRAY_N

				$attach_ids = array();
				foreach ( $images as $attach ) {
					if ( ! empty( $attach->ID ) ) {
						$attach_ids[] = $attach->ID;
					}
				}
				rsort( $attach_ids, SORT_NUMERIC );

				$attach_ids = array_unique( apply_filters( $this->p->cf['lca'].'_attached_image_ids', $attach_ids, $post_id ) );
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'found '.count( $attach_ids ).' attached images for post_id '.$post_id );
				}

				foreach ( $attach_ids as $pid ) {
					list(
						$og_single_image['og:image'],
						$og_single_image['og:image:width'],
						$og_single_image['og:image:height'],
						$og_single_image['og:image:cropped'],
						$og_single_image['og:image:id']
					) = $this->get_attachment_image_src( $pid, $size_name, $check_dupes, $force_regen );

					if ( ! empty( $og_single_image['og:image'] ) &&
						$this->p->util->push_max( $og_ret, $og_single_image, $num ) ) {
						break;	// stop here and apply the 'ngfb_attached_images' filter
					}
				}
			}

			// 'ngfb_attached_images' filter is used by the buddypress module
			return apply_filters( $this->p->cf['lca'].'_attached_images', $og_ret, $num, $size_name, $post_id, $check_dupes, $force_regen );
		}

		/* Use these static methods in get_attachment_image_src() to set/reset information about
		 * the image being processed for down-stream filters / methods lacking this information.
		 * They can call NgfbMedia::get_image_src_info() to retrieve the image information.
		 */
		public static function set_image_src_info( $image_src_args = null ) {
			self::$image_src_info = $image_src_args;
		}

		public static function get_image_src_info( $idx = false ) {
			if ( $idx !== false ) {
				if ( isset( self::$image_src_info[$idx] ) ) {
					return self::$image_src_info[$idx];
				} else {
					return null;
				}
			} else {
				return self::$image_src_info;
			}
		}

		// by default, return an empty image array
		public static function reset_image_src_info( $image_src_ret = array( null, null, null, null, null ) ) {
			self::$image_src_info = null;
			return $image_src_ret;
		}

		// make sure every return is wrapped with self::reset_image_src_info()
		public function get_attachment_image_src( $pid, $size_name = 'thumbnail', $check_dupes = true, $force_regen = false ) {

			self::set_image_src_info( $args = array(
				'pid' => $pid,
				'size_name' => $size_name,
				'check_dupes' => $check_dupes,
				'force_regen' => $force_regen,
			) );

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
				$this->p->debug->log_args( $args );
			}

			$lca = $this->p->cf['lca'];
			$size_info = SucomUtil::get_size_info( $size_name );
			$img_url = '';
			$img_width = NGFB_UNDEF_INT;
			$img_height = NGFB_UNDEF_INT;
			$img_cropped = empty( $size_info['crop'] ) ? 0 : 1;	// get_size_info() returns false, true, or an array

			if ( $this->p->avail['media']['ngg'] && strpos( $pid, 'ngg-' ) === 0 ) {

				if ( ! empty( $this->p->m['media']['ngg'] ) ) {
					return self::reset_image_src_info( $this->p->m['media']['ngg']->get_image_src( $pid, $size_name, $check_dupes ) );
				} else {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( 'ngg module is not available: image ID '.$attr_value.' ignored' );
					}
					if ( $this->p->notice->is_admin_pre_notices() ) {	// skip if notices already shown
						$this->p->notice->err( sprintf( __( 'The NextGEN Gallery integration module provided by %1$s is required to read information for image ID %2$s.', 'nextgen-facebook' ), $this->p->cf['plugin'][$lca]['short'].' Pro', $pid ) );
					}
					return self::reset_image_src_info();
				}
			} elseif ( ! wp_attachment_is_image( $pid ) ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'exiting early: attachment '.$pid.' is not an image' );
				}
				return self::reset_image_src_info();
			}

			$use_full = false;
			$img_meta = wp_get_attachment_metadata( $pid );

			if ( $this->p->debug->enabled ) {
				$this->p->debug->log_arr( 'wp_get_attachment_metadata', $img_meta );
			}

			if ( isset( $img_meta['width'] ) && isset( $img_meta['height'] ) ) {
				if ( $img_meta['width'] === $size_info['width'] &&
					$img_meta['height'] === $size_info['height'] ) {
					$use_full = true;
				}
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'full size image '.$img_meta['file'].' dimensions '.
						$img_meta['width'].'x'.$img_meta['height'] );
				}
			} elseif ( $this->p->debug->enabled ) {
				if ( isset( $img_meta['file'] ) ) {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( 'full size image '.$img_meta['file'].' dimensions missing from image metadata' );
					}
					if ( $this->p->notice->is_admin_pre_notices() ) {	// skip if notices already shown
						$dismiss_key = 'full-size-image-'.$pid.'-dimensions-missing';
						$this->p->notice->err( sprintf( __( 'Possible Media Library corruption detected &mdash; the full size image dimensions for image ID %1$s are missing from the image metadata returned by the WordPress wp_get_attachment_metadata() function.', 'nextgen-facebook' ), $pid ).' '.sprintf( 'You may consider regenerating the thumbnails of all WordPress Media Library images using one of <a href="%s">several available plugins on WordPress.org</a>.', 'https://wordpress.org/plugins/search/regenerate+thumbnails/' ), true, $dismiss_key, WEEK_IN_SECONDS );
					}
				} else {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( 'full size image file path meta for '.$pid.' missing from image metadata' );
					}
					if ( $this->p->notice->is_admin_pre_notices() ) {	// skip if notices already shown
						$dismiss_key = 'full-size-image-'.$pid.'-file-path-missing';
						$this->p->notice->err( sprintf( __( 'Possible Media Library corruption detected &mdash; the full size image file path for image ID %1$s is missing from the image metadata returned by the WordPress wp_get_attachment_metadata() function.', 'nextgen-facebook' ), $pid ).' '.sprintf( 'You may consider regenerating the thumbnails of all WordPress Media Library images using one of <a href="%s">several available plugins on WordPress.org</a>.', 'https://wordpress.org/plugins/search/regenerate+thumbnails/' ), true, $dismiss_key, WEEK_IN_SECONDS );
					}
				}
			}

			if ( $use_full === true ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'requesting full size instead - image dimensions same as '.
						$size_name.' ('.$size_info['width'].'x'.$size_info['height'].')' );
				}
			} elseif ( strpos( $size_name, $lca.'-' ) === 0 ) {	// only resize our own custom image sizes

				if ( $force_regen || ! empty( $this->p->options['plugin_create_wp_sizes'] ) ) {

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( 'checking image metadata for inconsistencies' );

						if ( $force_regen ) {
							$this->p->debug->log( 'force regen is true' );
						} elseif ( empty( $img_meta['sizes'][$size_name] ) ) {
							$this->p->debug->log( $size_name.' size missing from image metadata' );
						}
					}

					// does the image metadata contain our image sizes?
					if ( $force_regen || empty( $img_meta['sizes'][$size_name] ) ) {
						$is_accurate_width = false;
						$is_accurate_height = false;
					} else {
						// is the width and height in the image metadata accurate?
						$is_accurate_width = ! empty( $img_meta['sizes'][$size_name]['width'] ) &&
							$img_meta['sizes'][$size_name]['width'] == $size_info['width'] ? true : false;
						$is_accurate_height = ! empty( $img_meta['sizes'][$size_name]['height'] ) &&
							$img_meta['sizes'][$size_name]['height'] == $size_info['height'] ? true : false;

						// if not cropped, make sure the resized image respects the original aspect ratio
						if ( $is_accurate_width && $is_accurate_height && empty( $img_cropped ) &&
							isset( $img_meta['width'] ) && isset( $img_meta['height'] ) ) {

							if ( $img_meta['width'] > $img_meta['height'] ) {
								$ratio = $img_meta['width'] / $size_info['width'];
								$check = 'height';
							} else {
								$ratio = $img_meta['height'] / $size_info['height'];
								$check = 'width';
							}
							$should_be = (int) round( $img_meta[$check] / $ratio );

							// allow for a +/- one pixel difference
							if ( $img_meta['sizes'][$size_name][$check] < ( $should_be - 1 ) ||
								$img_meta['sizes'][$size_name][$check] > ( $should_be + 1 ) ) {
								if ( $this->p->debug->enabled ) {
									$this->p->debug->log( $size_name.' image metadata not accurate' );
								}
								$is_accurate_width = false;
								$is_accurate_height = false;
							}
						}
					}

					// depending on cropping, one or both sides of the image must be accurate
					// if not, attempt to create a resized image by calling image_make_intermediate_size()
					if ( ( ! $img_cropped && ( ! $is_accurate_width && ! $is_accurate_height ) ) ||
						( $img_cropped && ( ! $is_accurate_width || ! $is_accurate_height ) ) ) {

						if ( $this->p->debug->enabled ) {
							if ( ! $force_regen && ! empty( $img_meta['sizes'][$size_name] ) ) {
								$this->p->debug->log( 'image metadata ('.
									( empty( $img_meta['sizes'][$size_name]['width'] ) ? 0 :
										$img_meta['sizes'][$size_name]['width'] ).'x'.
									( empty( $img_meta['sizes'][$size_name]['height'] ) ? 0 :
										$img_meta['sizes'][$size_name]['height'] ).') does not match '.
									$size_name.' ('.$size_info['width'].'x'.$size_info['height'].
										( $img_cropped ? ' cropped' : '' ).')' );
							}
						}

						if ( $this->can_make_size( $img_meta, $size_info ) ) {
							$fullsizepath = get_attached_file( $pid );
							$resized = image_make_intermediate_size( $fullsizepath,
								$size_info['width'], $size_info['height'], $size_info['crop'] );
	
							if ( $this->p->debug->enabled ) {
								$this->p->debug->log( 'WordPress image_make_intermediate_size() reported '.
									( $resized === false ? 'failure' : 'success' ) );
							}
	
							if ( $resized !== false ) {
								$img_meta['sizes'][$size_name] = $resized;
								wp_update_attachment_metadata( $pid, $img_meta );
							}

						} elseif ( $this->p->debug->enabled ) {
							$this->p->debug->log( 'skipped image_make_intermediate_size()' );
						}
					}
				} elseif ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'image metadata check skipped: plugin_create_wp_sizes option is disabled' );
				}
			}

			// some image_downsize hooks may return only 3 elements, use array_pad to sanitize the returned array
			list( $img_url, $img_width, $img_height, $img_intermediate ) = apply_filters( $lca.'_image_downsize',
				array_pad( image_downsize( $pid, ( $use_full === true ? 'full' : $size_name ) ), 4, null ), $pid, $size_name );

			if ( $this->p->debug->enabled ) {
				$this->p->debug->log( 'image_downsize returned '.$img_url.' ('.$img_width.'x'.$img_height.')' );
			}

			if ( empty( $img_url ) ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'exiting early: image_downsize returned an empty url' );
				}
				return self::reset_image_src_info();
			}

			// check if image exceeds hard-coded limits (dimensions, ratio, etc.)
			$img_size_within_limits = $this->img_size_within_limits( $pid, $size_name, $img_width, $img_height );

			// 'ngfb_attached_accept_img_dims' is hooked by the NgfbProCheckImgSize class / module.
			if ( apply_filters( $lca.'_attached_accept_img_dims', $img_size_within_limits,
				$img_url, $img_width, $img_height, $size_name, $pid ) ) {

				if ( ! $check_dupes || $this->p->util->is_uniq_url( $img_url, $size_name ) ) {

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( 'applying rewrite_image_url filter for '.$img_url );
					}

					return self::reset_image_src_info( array( apply_filters( $lca.'_rewrite_image_url',
						$this->p->util->fix_relative_url( $img_url ) ),	// just in case
							$img_width, $img_height, $img_cropped, $pid ) );
				}
			}

			return self::reset_image_src_info();
		}

		public function get_default_images( $num = 1, $size_name = 'thumbnail', $check_dupes = true, $force_regen = false ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->log_args( array(
					'num' => $num,
					'size_name' => $size_name,
					'check_dupes' => $check_dupes,
					'force_regen' => $force_regen,
				) );
			}

			$og_ret = array();
			$og_single_image = SucomUtil::get_mt_prop_image();

			foreach ( array( 'id', 'id_pre', 'url', 'url:width', 'url:height' ) as $key ) {
				$img[$key] = empty( $this->p->options['og_def_img_'.$key] ) ?
					'' : $this->p->options['og_def_img_'.$key];
			}

			if ( empty( $img['id'] ) && empty( $img['url'] ) ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'exiting early: no default image defined' );
				}
				return $og_ret;
			}

			if ( ! empty( $img['id'] ) ) {

				$img['id'] = $img['id_pre'] === 'ngg' ?
					'ngg-'.$img['id'] : $img['id'];

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'using default image pid: '.$img['id'] );
				}

				list(
					$og_single_image['og:image'],
					$og_single_image['og:image:width'],
					$og_single_image['og:image:height'],
					$og_single_image['og:image:cropped'],
					$og_single_image['og:image:id']
				) = $this->get_attachment_image_src( $img['id'], $size_name, $check_dupes, $force_regen );
			}

			if ( empty( $og_single_image['og:image'] ) && ! empty( $img['url'] ) ) {

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'using default image url: '.$img['url'] );
				}

				$og_single_image = array(
					'og:image' => $img['url'],
					'og:image:width' => $img['url:width'],
					'og:image:height' => $img['url:height'],
				);
			}

			if ( ! empty( $og_single_image['og:image'] ) &&
				$this->p->util->push_max( $og_ret, $og_single_image, $num ) ) {
				return $og_ret;
			}

			return $og_ret;
		}

		public function get_content_images( $num = 0, $size_name = 'thumbnail', $mod = true, $check_dupes = true, $force_regen = false, $content = '' ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->log_args( array(
					'num' => $num,
					'size_name' => $size_name,
					'mod' => $mod,
					'check_dupes' => $check_dupes,
					'content strlen' => strlen( $content ),
				) );
			}

			// $mod is preferred but not required
			// $mod = true | false | post_id | $mod array
			if ( ! is_array( $mod ) ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'optional call to get_page_mod()' );
				}
				$mod = $this->p->util->get_page_mod( $mod );
			}

			$og_ret = array();

			// allow custom content to be passed as an argument in $content
			// allow empty post IDs to get additional content from filter hooks
			if ( empty( $content ) ) {
				$content = $this->p->page->get_the_content( $mod );
				$content_passed = false;
			} else {
				$content_passed = true;
			}

			if ( empty( $content ) ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'exiting early: empty post content' );
				}
				return $og_ret;
			}

			$og_single_image = SucomUtil::get_mt_prop_image();
			$size_info = SucomUtil::get_size_info( $size_name );
			$img_preg = $this->def_img_preg;

			// allow the html_tag and pid_attr regex to be modified
			foreach( array( 'html_tag', 'pid_attr' ) as $type ) {
				$filter_name = $this->p->cf['lca'].'_content_image_preg_'.$type;
				if ( has_filter( $filter_name ) ) {
					$img_preg[$type] = apply_filters( $filter_name, $this->def_img_preg[$type] );
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( 'filtered image preg '.$type.' = \''.$img_preg[$type].'\'' );
					}
				}
			}

			// img attributes in order of preference
			if ( preg_match_all( '/<(('.$img_preg['html_tag'].')[^>]*? ('.$img_preg['pid_attr'].')=[\'"]([0-9]+)[\'"]|'.
				'(img)[^>]*? (data-share-src|data-lazy-src|data-src|src)=[\'"]([^\'"]+)[\'"])[^>]*>/s',
					$content, $all_matches, PREG_SET_ORDER ) ) {

				$content_img_max = SucomUtil::get_const( 'NGFB_CONTENT_IMAGES_MAX_LIMIT', 5 );

				if ( count( $all_matches ) > $content_img_max ) {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( 'limiting matches returned from '.
							count( $all_matches ).' to '.$content_img_max );
					}
					$all_matches = array_splice( $all_matches, 0, $content_img_max );
				}

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( count( $all_matches ).' x matching <'.$img_preg['html_tag'].'/> html tag(s) found' );
				}

				foreach ( $all_matches as $img_num => $img_arr ) {

					$tag_value = $img_arr[0];

					if ( empty( $img_arr[5] ) ) {
						$tag_name = $img_arr[2];	// img
						$attr_name = $img_arr[3];	// data-wp-pid
						$attr_value = $img_arr[4];	// id
					} else {
						$tag_name = $img_arr[5];	// img
						$attr_name = $img_arr[6];	// data-share-src|data-lazy-src|data-src|src
						$attr_value = $img_arr[7];	// url
					}

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( 'match '.$img_num.': '.$tag_name.' '.$attr_name.'="'.$attr_value.'"' );
					}

					switch ( $attr_name ) {

						// wordpress media library image id
						case 'data-wp-pid':

							if ( $this->p->debug->enabled ) {
								$this->p->debug->log( 'WP image attribute id found: '.$attr_value );
							}

							list(
								$og_single_image['og:image'],
								$og_single_image['og:image:width'],
								$og_single_image['og:image:height'],
								$og_single_image['og:image:cropped'],
								$og_single_image['og:image:id']
							) = $this->get_attachment_image_src( $attr_value, $size_name, false, $force_regen );

							break;

						// check for other data attributes like 'data-ngg-pid'
						case ( preg_match( '/^'.$img_preg['pid_attr'].'$/', $attr_name ) ? true : false ):

							// build a filter hook for 3rd party modules to return image information
							$filter_name = $this->p->cf['lca'].'_get_content_'.
								$tag_name.'_'.( preg_replace( '/-/', '_', $attr_name ) );

							if ( $this->p->debug->enabled ) {
								$this->p->debug->log( 'applying filter '.$filter_name );
							}

							list(
								$og_single_image['og:image'],
								$og_single_image['og:image:width'],
								$og_single_image['og:image:height'],
								$og_single_image['og:image:cropped'],
								$og_single_image['og:image:id']
							) = apply_filters( $filter_name, array( null, null, null, null, null ), $attr_value, $size_name, false );

							break;

						// data-share-src|data-lazy-src|data-src|src
						default:

							// prevent duplicates by silently ignoring ngg images (already processed by the ngg module)
							if ( $this->p->avail['media']['ngg'] === true &&
								! empty( $this->p->m['media']['ngg'] ) &&
									( preg_match( '/ class=[\'"]ngg[_-]/', $tag_value ) ||
										preg_match( '/^('.$img_preg['ngg_src'].')$/', $attr_value ) ) ) {
								if ( $this->p->debug->enabled ) {
									$this->p->debug->log( 'silently ignoring ngg image for '.$attr_name );
								}
								break;	// stop here
							}

							// recognize gravatar images in the content
							if ( preg_match( '/^(https?:)?(\/\/([^\.]+\.)?gravatar\.com\/avatar\/[a-zA-Z0-9]+)/', $attr_value, $match ) ) {

								$og_single_image['og:image'] = SucomUtil::get_prot().':'.$match[2].'?s='.$size_info['width'].'&d=404&r=G';
								$og_single_image['og:image:width'] = $size_info['width'];
								$og_single_image['og:image:height'] = $size_info['width'];	// square image

								if ( $this->p->debug->enabled ) {
									$this->p->debug->log( 'gravatar image found: '.$og_single_image['og:image'] );
								}

								break;	// stop here
							}

							// check for image ID in class for old content w/o the data-wp-pid attribute
							if ( preg_match( '/class="[^"]+ wp-image-([0-9]+)/', $tag_value, $match ) ) {
								list(
									$og_single_image['og:image'],
									$og_single_image['og:image:width'],
									$og_single_image['og:image:height'],
									$og_single_image['og:image:cropped'],
									$og_single_image['og:image:id']
								) = $this->get_attachment_image_src( $match[1], $size_name, false, $force_regen );
								break;	// stop here
							} else {
								if ( $this->p->debug->enabled ) {
									$this->p->debug->log( 'using attribute value for og:image = '.$attr_value.
										' ('.NGFB_UNDEF_INT.'x'.NGFB_UNDEF_INT.')' );
								}
								$og_single_image = array(
									'og:image' => $attr_value,
									'og:image:width' => NGFB_UNDEF_INT,
									'og:image:height' => NGFB_UNDEF_INT,
								);
							}

							if ( empty( $og_single_image['og:image'] ) ) {
								$this->p->debug->log( 'single image og:image value is empty' );
								break;	// stop here
							}

							$check_size_limits = true;
							$img_size_within_limits = true;

							// get the actual width and height of the image using http / https
							if ( empty( $og_single_image['og:image:width'] ) || $og_single_image['og:image:width'] < 0 ||
								empty( $og_single_image['og:image:height'] ) || $og_single_image['og:image:height'] < 0 ) {

								$this->p->util->add_image_url_size( 'og:image', $og_single_image );

								if ( $this->p->debug->enabled ) {
									$this->p->debug->log( 'returned / fetched image url size: '.
										$og_single_image['og:image:width'].'x'.$og_single_image['og:image:height'] );
								}

								// no use checking / retrieving the image size twice
								if ( $og_single_image['og:image:width'] === NGFB_UNDEF_INT &&
									$og_single_image['og:image:height'] === NGFB_UNDEF_INT ) {
									$check_size_limits = false;
									$img_size_within_limits = false;
								}

							} elseif ( $this->p->debug->enabled ) {
								$this->p->debug->log( 'image width / height values: '.
									$og_single_image['og:image:width'].'x'.$og_single_image['og:image:height'] );
							}

							if ( $check_size_limits ) {
								if ( $this->p->debug->enabled ) {
									$this->p->debug->log( 'checking image size limits for '.$og_single_image['og:image'].
										' ('.$og_single_image['og:image:width'].'x'.$og_single_image['og:image:height'].')' );
								}
								// check if image exceeds hard-coded limits (dimensions, ratio, etc.)
								$img_size_within_limits = $this->img_size_within_limits( $og_single_image['og:image'],
									$size_name, $og_single_image['og:image:width'], $og_single_image['og:image:height'],
										__( 'Content', 'nextgen-facebook' ) );

							} elseif ( $this->p->debug->enabled ) {
								$this->p->debug->log( 'skipped image size limits for '.$og_single_image['og:image'].
									' ('.$og_single_image['og:image:width'].'x'.$og_single_image['og:image:height'].')' );
							}

							// 'ngfb_content_accept_img_dims' is hooked by the NgfbProCheckImgSize class / module.
							if ( ! apply_filters( $this->p->cf['lca'].'_content_accept_img_dims',
								$img_size_within_limits, $og_single_image, $size_name, $attr_name, $content_passed ) ) {
								$og_single_image = array();
							}

							break;
					}

					if ( ! empty( $og_single_image['og:image'] ) ) {

						$og_single_image['og:image'] = apply_filters( $this->p->cf['lca'].'_rewrite_image_url',
							$this->p->util->fix_relative_url( $og_single_image['og:image'] ) );

						if ( $check_dupes === false || $this->p->util->is_uniq_url( $og_single_image['og:image'], $size_name ) ) {
							if ( $this->p->util->push_max( $og_ret, $og_single_image, $num ) ) {
								return $og_ret;
							}
						}
					}
				}
				return $og_ret;
			}
			if ( $this->p->debug->enabled ) {
				$this->p->debug->log( 'no matching <'.$img_preg['html_tag'].'/> html tag(s) found' );
			}
			return $og_ret;
		}

		public function get_opts_image( $opts, $size_name, $check_dupes = true, $force_regen = false, $opt_pre = 'og', $mt_pre = 'og' ) {

			foreach ( array( 'id', 'id_pre', 'url', 'url:width', 'url:height' ) as $key ) {
				$img[$key] = empty( $opts[$opt_pre.'_img_'.$key] ) ?
					'' : $opts[$opt_pre.'_img_'.$key];
			}

			$mt_image = array();

			if ( ! empty( $img['id'] ) ) {

				$img['id'] = $img['id_pre'] === 'ngg' ?
					'ngg-'.$img['id'] : $img['id'];

				list( 
					$mt_image[$mt_pre.':image'],
					$mt_image[$mt_pre.':image:width'],
					$mt_image[$mt_pre.':image:height'],
					$mt_image[$mt_pre.':image:cropped'],
					$mt_image[$mt_pre.':image:id']
				) = $this->get_attachment_image_src( $img['id'], $size_name, $check_dupes, $force_regen );
			}

			if ( empty( $mt_image[$mt_pre.':image'] ) && ! empty( $img['url'] ) ) {
				$mt_image = array(
					$mt_pre.':image' => $img['url'],
					$mt_pre.':image:width' => ( $img['url:width'] > 0 ? $img['url:width'] : NGFB_UNDEF_INT ),
					$mt_pre.':image:height' => ( $img['url:height'] > 0 ? $img['url:height'] : NGFB_UNDEF_INT ),
				);
			}

			return $mt_image;
		}

		public function get_content_videos( $num = 0, $mod = true, $check_dupes = true, $content = '' ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->log_args( array(
					'num' => $num,
					'mod' => $mod,
					'check_dupes' => $check_dupes,
					'content' => strlen( $content ).' chars',
				) );
			}

			// $mod = true | false | post_id | $mod array
			if ( ! is_array( $mod ) ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'optional call to get_page_mod()' );
				}
				$mod = $this->p->util->get_page_mod( $mod );
			}

			$og_ret = array();

			// allow custom content to be passed as an argument in $content
			// allow empty post IDs to get additional content from filter hooks
			if ( empty( $content ) ) {
				$content = $this->p->page->get_the_content( $mod );
				$content_passed = false;
			} else {
				$content_passed = true;
			}

			if ( empty( $content ) ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'exiting early: empty post content' );
				}
				return $og_ret;
			}

			// detect standard iframe/embed tags - use the ngfb_content_videos filter for additional html5/javascript methods
			if ( preg_match_all( '/<(iframe|embed)[^<>]*? (data-share-src|data-lazy-src|data-src|src)=[\'"]'.
				'([^\'"<>]+\/(embed\/|embed_code\/|player\/|swf\/|v\/|video\/|video\.php\?)[^\'"<>]+)[\'"][^<>]*>/i',
					$content, $all_matches, PREG_SET_ORDER ) ) {

				$content_vid_max = SucomUtil::get_const( 'NGFB_CONTENT_VIDEOS_MAX_LIMIT', 5 );

				if ( count( $all_matches ) > $content_vid_max ) {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( 'limiting matches returned from '.
							count( $all_matches ).' to '.$content_vid_max );
					}
					$all_matches = array_splice( $all_matches, 0, $content_vid_max );
				}

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( count( $all_matches ).' x video <iframe|embed/> html tag(s) found' );
				}

				foreach ( $all_matches as $media ) {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( '<'.$media[1].'/> html tag found '.$media[2].' = '.$media[3] );
					}
					$embed_url = $media[3];
					if ( ! empty( $embed_url ) && ( $check_dupes == false || $this->p->util->is_uniq_url( $embed_url, 'video' ) ) ) {

						$embed_width = preg_match( '/ width=[\'"]?([0-9]+)[\'"]?/i', $media[0], $match) ? $match[1] : NGFB_UNDEF_INT;
						$embed_height = preg_match( '/ height=[\'"]?([0-9]+)[\'"]?/i', $media[0], $match) ? $match[1] : NGFB_UNDEF_INT;
						$og_video = $this->get_video_info( $embed_url, $embed_width, $embed_height, $check_dupes );

						if ( ! empty( $og_video ) && $this->p->util->push_max( $og_ret, $og_video, $num ) ) {
							return $og_ret;
						}
					}
				}
			} elseif ( $this->p->debug->enabled ) {
				$this->p->debug->log( 'no <iframe|embed/> html tag(s) found' );
			}

			// additional filters / Pro modules may detect other embedded video markup
			$filter_name = $this->p->cf['lca'].'_content_videos';

			if ( has_filter( $filter_name ) ) {

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'applying filter '.$filter_name );
				}

				// should return an array of arrays
				if ( ( $all_matches = apply_filters( $filter_name, false, $content ) ) !== false ) {

					if ( is_array( $all_matches ) ) {

						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( count( $all_matches ).' x videos returned by '.$filter_name.' filter' );
						}

						foreach ( $all_matches as $media ) {

							if ( ! empty( $media[0] ) && ( $check_dupes == false || $this->p->util->is_uniq_url( $media[0], 'video' ) ) ) {

								$og_video = $this->get_video_info( $media[0], $media[1], $media[2], $check_dupes );

								if ( ! empty( $og_video ) && $this->p->util->push_max( $og_ret, $og_video, $num ) ) {
									return $og_ret;
								}
							}
						}

					} elseif ( $this->p->debug->enabled ) {
						$this->p->debug->log( $filter_name.' filter did not return false or an array' );
					}
				}
			}

			return $og_ret;
		}

		public function get_video_info( $embed_url, $embed_width, $embed_height, $check_dupes, $fallback = false ) {

			if ( empty( $embed_url ) ) {
				return array();
			}

			$filter_name = $this->p->cf['lca'].'_video_info';

			$og_video = array_merge(
				SucomUtil::get_mt_prop_video(),			// includes og:image meta tags for the preview image
				array(
					'og:video:width' => $embed_width,	// default width
					'og:video:height' => $embed_height,	// default height
				)
			);

			$og_video = apply_filters( $filter_name, $og_video, $embed_url, $embed_width, $embed_height );

			if ( $this->p->debug->enabled ) {
				$this->p->debug->log_arr( 'og_video after filters', $og_video );
			}

			// sanitation of media
			foreach ( array( 'og:video', 'og:image' ) as $prefix ) {

				$media_url = SucomUtil::get_mt_media_url( $og_video, $prefix );

				// fallback to the original url
				if ( empty( $media_url ) && $prefix === 'og:video' && $fallback ) {

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( 'no video returned by filters' );
						$this->p->debug->log( 'falling back to embed url: '.$embed_url );
					}

					// define the og:video:secure_url meta tag if possible
					if ( ! empty( $this->p->options['add_meta_property_og:video:secure_url'] ) ) {
						$og_video['og:video:secure_url'] = strpos( $embed_url, 'https:' ) === 0 ? $embed_url : '';
					}

					$media_url = $og_video['og:video:url'] = $embed_url;

					if ( preg_match( '/\.mp4(\?.*)?$/', $media_url ) ) {	// check for video/mp4
						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( 'setting og:video:type = video/mp4' );
						}
						$og_video['og:video:type'] = 'video/mp4';
					}
				}

				$have_media[$prefix] = empty( $media_url ) ? false : true;

				// remove all meta tags if there's no media URL or media is a duplicate
				if ( ! $have_media[$prefix] || ( $check_dupes && ! $this->p->util->is_uniq_url( $media_url, 'video' ) ) ) {

					foreach( SucomUtil::preg_grep_keys( '/^'.$prefix.'(:.*)?$/', $og_video ) as $k => $v ) {
						unset ( $og_video[$k] );
					}

				// if the media is an image, then check and add missing sizes
				} elseif ( $prefix === 'og:image' ) {

					if ( empty( $og_video['og:image:width'] ) || $og_video['og:image:width'] < 0 ||
						empty( $og_video['og:image:height'] ) || $og_video['og:image:height'] < 0 ) {

						// add correct image sizes for the image URL using getimagesize()
						$this->p->util->add_image_url_size( 'og:image', $og_video );

						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( 'returned / fetched video image url size: '.
								$og_video['og:image:width'].'x'.$og_video['og:image:height'] );
						}

					} elseif ( $this->p->debug->enabled ) {
						$this->p->debug->log( 'video image width / height values: '.
							$og_video['og:image:width'].'x'.$og_video['og:image:height'] );
					}
				}
			}

			// if there's no video or preview image, then return an empty array
			if ( ! $have_media['og:video'] && ! $have_media['og:image'] ) {
				return array();
			} else {
				return $og_video;
			}
		}

		// $img_name cam be an image ID or URL
		// $src_name can be 'Media Library', 'NextGEN Gallery', 'Content', etc.
		public function img_size_within_limits( $img_name, $size_name, $img_width, $img_height, $src_name = '' ) {

			$lca = $this->p->cf['lca'];
			$cf_min = $this->p->cf['head']['limit_min'];
			$cf_max = $this->p->cf['head']['limit_max'];
			$img_ratio = 0;

			if ( strpos( $size_name, $lca.'-' ) !== 0 ) {	// only check our own sizes
				return true;
			}

			if ( $src_name === '' ) {
				$src_name = __( 'Media Library', 'nextgen-facebook' );
			}

			if ( is_numeric( $img_name ) ) {
				$img_name = 'ID '.$img_name;
			} elseif ( strpos( $img_name, '://' ) !== false ) {
				if ( $img_width === NGFB_UNDEF_INT || $img_height === NGFB_UNDEF_INT ) {
					list( $img_width, $img_height, $img_type, $img_attr ) = $this->p->util->get_image_url_info( $img_name );
				}
			}

			// exit silently if width and/or height is not valid
			if ( $img_width === NGFB_UNDEF_INT || $img_height === NGFB_UNDEF_INT ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'exiting early: '.strtolower( $src_name ).' image '.$img_name.' rejected - '.
						'invalid width and/or height '.$img_width.'x'.$img_height );
				}
				return false;	// image rejected
			}

			if ( $img_width > 0 && $img_height > 0 ) {
				$img_ratio = $img_width >= $img_height ? $img_width / $img_height : $img_height / $img_width;
			}

			switch ( $size_name ) {
				case $lca.'-opengraph':
					$std_name = 'Facebook / Open Graph';
					$min_width = $cf_min['og_img_width'];
					$min_height = $cf_min['og_img_height'];
					$max_ratio = $cf_max['og_img_ratio'];
					break;

				case $lca.'-schema':
					$std_name = 'Google / Schema';
					$min_width = $cf_min['schema_img_width'];
					$min_height = $cf_min['schema_img_height'];
					$max_ratio = $cf_max['schema_img_ratio'];
					break;

				case $lca.'-schema-article':
					$std_name = 'Google / Schema Article';
					$min_width = $cf_min['schema_article_img_width'];
					$min_height = $cf_min['schema_article_img_height'];
					$max_ratio = $cf_max['schema_article_img_ratio'];
					break;

				default:
					$min_width = 0;
					$min_height = 0;
					$max_ratio = 0;
					break;
			}

			// filter name example: ngfb_opengraph_img_size_limits
			list( $min_width, $min_height, $max_ratio ) = apply_filters( SucomUtil::sanitize_hookname( $size_name ).'_img_size_limits',
				array( $min_width, $min_height, $max_ratio ) );

			// check the maximum image aspect ratio
			if ( $max_ratio > 0 && $img_ratio >= $max_ratio ) {

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'exiting early: '.strtolower( $src_name ).' image '.$img_name.' rejected - '.
						$img_width.'x'.$img_height.' aspect ratio is equal to/or greater than '.$max_ratio.':1' );
				}

				if ( $this->p->notice->is_admin_pre_notices() ) {	// skip if notices already shown

					$size_label = $this->p->util->get_image_size_label( $size_name );
					$reject_notice = $this->p->msgs->get( 'notice-image-rejected', array(
						'size_label' => $size_label,
						'allow_upscale' => false
					) );

					$this->p->notice->err( sprintf( __( '%1$s image %2$s ignored &mdash; the resulting image of %3$s has an <strong>aspect ratio equal to/or greater than %4$d:1 allowed by the %5$s standard</strong>.', 'nextgen-facebook' ), $src_name, $img_name, $img_width.'x'.$img_height, $max_ratio, $std_name ).' '.$reject_notice );
				}

				return false;	// image rejected
			}

			// check the minimum image width and/or height
			if ( ( $min_width > 0 || $min_height > 0 ) && ( $img_width < $min_width || $img_height < $min_height ) ) {

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'exiting early: '.strtolower( $src_name ).' image '.$img_name.' rejected - '.
						$img_width.'x'.$img_height.' smaller than minimum '.$min_width.'x'.$min_height.' for '.$size_name );
				}

				if ( $this->p->notice->is_admin_pre_notices() ) {	// skip if notices already shown
					$size_label = $this->p->util->get_image_size_label( $size_name );
					$reject_notice = $this->p->msgs->get( 'notice-image-rejected', array(
						'size_label' => $size_label,
						'allow_upscale' => true
					) );
					$this->p->notice->err( sprintf( __( '%1$s image %2$s ignored &mdash; the resulting image of %3$s is <strong>smaller than the minimum of %4$s allowed by the %5$s standard</strong>.', 'nextgen-facebook' ), $src_name, $img_name, $img_width.'x'.$img_height, $min_width.'x'.$min_height, $std_name ).' '.$reject_notice );
				}

				return false;	// image rejected
			}

			return true;	// image accepted
		}

		public function can_make_size( $img_meta, $size_info ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			$full_width = empty( $img_meta['width'] ) ? 0 : $img_meta['width'];
			$full_height = empty( $img_meta['height'] ) ? 0 : $img_meta['height'];

			$is_sufficient_w = $full_width >= $size_info['width'] ? true : false;
			$is_sufficient_h = $full_height >= $size_info['height'] ? true : false;

			$img_cropped = empty( $size_info['crop'] ) ? 0 : 1;
			$upscale_multiplier = 1;

			if ( $this->p->options['plugin_upscale_images'] ) {
				$img_info = (array) self::get_image_src_info();
				$upscale_multiplier = 1 + ( apply_filters( $this->p->cf['lca'].'_image_upscale_max',
					$this->p->options['plugin_upscale_img_max'], $img_info ) / 100 );
				$upscale_full_width = round( $full_width * $upscale_multiplier );
				$upscale_full_height = round( $full_height * $upscale_multiplier );
				$is_sufficient_w = $upscale_full_width >= $size_info['width'] ? true : false;
				$is_sufficient_h = $upscale_full_height >= $size_info['height'] ? true : false;
			}


			if ( ( ! $img_cropped && ( ! $is_sufficient_w && ! $is_sufficient_h ) ) ||
				( $img_cropped && ( ! $is_sufficient_w || ! $is_sufficient_h ) ) ) {
				$ret = false;
			} else {
				$ret = true;
			}

			if ( $this->p->debug->enabled ) {
				$this->p->debug->log( 'full size image of '.$full_width.'x'.$full_height.( $upscale_multiplier !== 1 ?
					' ('.$upscale_full_width.'x'.$upscale_full_height.' upscaled by '.$upscale_multiplier.')' : '' ).
					( $ret ? ' sufficient' : ' too small' ).' to create size '.$size_info['width'].'x'.$size_info['height'].
					( $img_cropped ? ' cropped' : '' ) );
			}

			return $ret;
		}

		public function add_og_video_from_url( array &$og_video, $url ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			/*
			 * Fetch HTML using the Facebook user agent to get Open Graph meta tags.
			 *
			 * get_head_meta( $request, $query, $libxml_errors, $curl_opts );
			 */
			$curl_opts = array( 'CURLOPT_USERAGENT' => WPSSO_PHP_CURL_USERAGENT_FACEBOOK );
			$metas = $this->p->util->get_head_meta( $url, '//meta', false, $curl_opts );

			if ( isset( $metas['meta'] ) ) {

				foreach ( $metas as $m ) {		// loop through all meta tags
					foreach ( $m as $a ) {		// loop through all attributes for that meta tag

						$meta_type = key( $a );
						$meta_name = reset( $a );
						$meta_match = $meta_type.'-'.$meta_name;

						switch ( $meta_match ) {

							// use the property meta tag content as-is
							case 'property-og:video:width':
							case 'property-og:video:height':
							case ( strpos( $meta_match, 'property-al:' ) === 0 ? true : false ):	// Facebook AppLink
								if ( ! empty( $a['content'] ) ) {
									$og_video[$a['property']] = $a['content'];
								}
								break;

							// add the property meta tag content as an array
							case 'property-og:video:tag':
								if ( ! empty( $a['content'] ) ) {
									$og_video[$a['property']][] = $a['content'];	// array of tags
								}
								break;

							case 'property-og:image:secure_url':
								if ( ! empty( $a['content'] ) ) {

									// add the meta name as a query string to know where the value came from
									$a['content'] = add_query_arg( 'm', $meta_name, $a['content'] );

									if ( ! empty( $this->p->options['add_meta_property_og:image:secure_url'] ) ) {
										$og_video['og:image:secure_url'] = $a['content'];
									} else {
										$og_video['og:image'] = $a['content'];
									}

									$og_video['og:video:thumbnail_url'] = $a['content'];
									$og_video['og:video:has_image'] = true;
								}
								break;

							case 'property-og:image:url':
							case 'property-og:image':
								if ( ! empty( $a['content'] ) ) {

									// add the meta name as a query string to know where the value came from
									$a['content'] = add_query_arg( 'm', $meta_name, $a['content'] );

									if ( strpos( $a['content'], 'https:' ) === 0 &&
										! empty( $this->p->options['add_meta_property_og:image:secure_url'] ) ) {
										$og_video['og:image:secure_url'] = $a['content'];
									}

									$og_video['og:image'] = $a['content'];
									$og_video['og:video:thumbnail_url'] = $a['content'];
									$og_video['og:video:has_image'] = true;
								}
								break;

							// add additional, non-standard properties
							// like og:video:title and og:video:description
							case 'property-og:title':
							case 'property-og:description':
								if ( ! empty( $a['content'] ) ) {
									$og_key = 'og:video:'.substr( $a['property'], 3 );
									$og_video[$og_key] = $this->p->util->cleanup_html_tags( $a['content'] );
									if ( $this->p->debug->enabled ) {
										$this->p->debug->log( 'adding '.$og_key.' = '.$og_video[$og_key] );
									}
								}
								break;

							// twitter:app:name:iphone
							// twitter:app:id:iphone
							// twitter:app:url:iphone
							case ( strpos( $meta_match, 'name-twitter:app:' ) === 0 ? true : false ):	// Twitter Apps
								if ( ! empty( $a['content'] ) ) {
									if ( preg_match( '/^twitter:app:([a-z]+):([a-z]+)$/', $meta_name, $matches ) ) {
										$og_video['og:video:'.$matches[2].'_'.$matches[1]] = $a['content'];
									}
								}
								break;

							case 'itemprop-datePublished':
								if ( ! empty( $a['content'] ) ) {
									$og_video['og:video:upload_date'] = gmdate( 'c', strtotime( $a['content'] ) );
								}
								break;

							case 'itemprop-embedUrl':
							case 'itemprop-embedURL':
								if ( ! empty( $a['content'] ) ) {
									$og_video['og:video:embed_url'] = $a['content'];
								}
								break;
						}
					}
				}

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( $og_video );
				}
	
			} elseif ( $this->p->debug->enabled ) {
				$this->p->debug->log( 'no head meta found in '.$url );
			}
		}
	}
}


<?php
/*
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2012-2017 Jean-Sebastien Morisset (https://surniaulula.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'These aren\'t the droids you\'re looking for...' );
}

if ( ! class_exists( 'NgfbGplAdminSharing' ) ) {

	class NgfbGplAdminSharing {

		public function __construct( &$plugin ) {
			$this->p =& $plugin;

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			$this->p->util->add_plugin_filters( $this, array(
				'plugin_cache_rows' => 3,		// $table_rows, $form, $network
				'buttons_include_rows' => 2,		// $table_rows, $form
				'buttons_preset_rows' => 2,		// $table_rows, $form
				'buttons_advanced_rows' => 2,		// $table_rows, $form
				'post_buttons_rows' => 4,		// $table_rows, $form, $head, $mod
				'styles_sharing_rows' => 2,		// $table_rows, $form
				'styles_content_rows' => 2,		// $table_rows, $form
				'styles_excerpt_rows' => 2,		// $table_rows, $form
				'styles_sidebar_rows' => 2,		// $table_rows, $form
				'styles_shortcode_rows' => 2,		// $table_rows, $form
				'styles_widget_rows' => 2,		// $table_rows, $form
				'styles_admin_edit_rows' => 2,		// $table_rows, $form
			), 30 );
		}

		public function filter_plugin_cache_rows( $table_rows, $form, $network = false ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			SucomUtil::add_before_key( $table_rows, 'plugin_show_purge_count', array(
				'plugin_sharing_buttons_cache_exp' => $form->get_th_html( _x( 'Sharing Buttons HTML Cache Expiry',
					'option label', 'nextgen-facebook' ), null, 'plugin_sharing_buttons_cache_exp' ).
				'<td nowrap class="blank">'.$this->p->options['plugin_sharing_buttons_cache_exp'].' '.
				_x( 'seconds (0 to disable)', 'option comment', 'nextgen-facebook' ).'</td>'.
				NgfbAdmin::get_option_site_use( 'plugin_sharing_buttons_cache_exp', $form, $network ),

				'plugin_social_file_cache_exp' => $form->get_th_html( _x( 'Get Social JS Files Cache Expiry',
					'option label', 'nextgen-facebook' ), null, 'plugin_social_file_cache_exp' ).
				'<td nowrap class="blank">'.$this->p->options['plugin_social_file_cache_exp'].' '.
				_x( 'seconds (0 to disable)', 'option comment', 'nextgen-facebook' ).'</td>'.
				NgfbAdmin::get_option_site_use( 'plugin_social_file_cache_exp', $form, $network ),
			) );

			return $table_rows;
		}

		public function filter_buttons_include_rows( $table_rows, $form ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			$table_rows[] = '<td colspan="2" align="center">'.$this->p->msgs->get( 'pro-feature-msg' ).'</td>';

			$table_rows['buttons_add_to'] = $form->get_th_html( _x( 'Include on Post Types',
				'option label', 'nextgen-facebook' ), '', 'buttons_add_to' ).
			'<td class="blank">'.$form->get_no_checklist_post_types( 'buttons_add_to' ).'</td>';

			return $table_rows;
		}

		public function filter_buttons_preset_rows( $table_rows, $form ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			$presets = array( 'shortcode' => 'Shortcode', 'widget' => 'Widget' );
			$show_on = apply_filters( $this->p->cf['lca'].'_buttons_show_on', $this->p->cf['sharing']['show_on'], '' );

			foreach ( $show_on as $type => $label ) {
				$presets[$type] = $label;
			}

			asort( $presets );

			$table_rows[] = '<td colspan="2" align="center">'.
				$this->p->msgs->get( 'pro-feature-msg' ).'</td>';

			foreach( $presets as $filter_id => $filter_name ) {
				$table_rows[] = $form->get_th_html( sprintf( _x( '%s Preset',
					'option label', 'nextgen-facebook' ), $filter_name ), null, 'buttons_preset' ).
				'<td class="blank">'.$form->get_no_select( 'buttons_preset_'.$filter_id,
					array_merge( array( '' ), array_keys( $this->p->cf['opt']['preset'] ) ) ).'</td>';
			}

			return $table_rows;
		}

		public function filter_buttons_advanced_rows( $table_rows, $form ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			$table_rows['buttons_force_prot'] = $form->get_th_html( _x( 'Force Protocol for Shared URLs',
				'option label', 'nextgen-facebook' ), '', 'buttons_force_prot' ).
			'<td class="blank">'.$form->get_no_select( 'buttons_force_prot', 
				array_merge( array( '' => 'none' ), $this->p->cf['sharing']['force_prot'] ) ).'</td>';

			$table_rows['plugin_sharing_buttons_cache_exp'] = $form->get_th_html( _x( 'Sharing Buttons HTML Cache Expiry',
				'option label', 'nextgen-facebook' ), null, 'plugin_sharing_buttons_cache_exp' ).
			'<td nowrap class="blank">'.$this->p->options['plugin_sharing_buttons_cache_exp'].' '.
				_x( 'seconds (0 to disable)', 'option comment', 'nextgen-facebook' ).'</td>';

			$table_rows['plugin_social_file_cache_exp'] = $form->get_th_html( _x( 'Get Social JS Files Cache Expiry',
				'option label', 'nextgen-facebook' ), null, 'plugin_social_file_cache_exp' ).
			'<td nowrap class="blank">'.$this->p->options['plugin_social_file_cache_exp'].' '.
				_x( 'seconds (0 to disable)', 'option comment', 'nextgen-facebook' ).'</td>';

			return $table_rows;
		}

		public function filter_post_buttons_rows( $table_rows, $form, $head, $mod ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			if ( empty( $mod['post_status'] ) || $mod['post_status'] === 'auto-draft' ) {
				$table_rows['save_a_draft'] = '<td><blockquote class="status-info"><p class="centered">'.
					sprintf( __( 'Save a draft version or publish the %s to display these options.',
						'nextgen-facebook' ), SucomUtil::titleize( $mod['post_type'] ) ).'</p></td>';
				return $table_rows;	// abort
			}

			$size_info = SucomUtil::get_size_info( 'thumbnail' );
			$title_caption = $this->p->page->get_caption( 'title', 0, $mod, true, false );

			$table_rows[] = '<td colspan="3" align="center">'.
				$this->p->msgs->get( 'pro-feature-msg',
					array( 'lca' => 'ngfb' ) ).'</td>';

			/*
			 * Email
			 */
			$caption_len = $this->p->options['email_cap_len'];
			$caption_text = $this->p->page->get_caption( 'excerpt', $caption_len,
				$mod, true, $this->p->options['email_cap_hashtags'], true, 'none' );

			$form_rows['email_title'] = array(
				'label' => _x( 'Email Subject', 'option label', 'nextgen-facebook' ),
				'th_class' => 'medium', 'tooltip' => 'post-email_title', 'td_class' => 'blank',
				'content' => $form->get_no_input_value( $title_caption, 'wide' ),
			);
			$form_rows['email_desc'] = array(
				'label' => _x( 'Email Message', 'option label', 'nextgen-facebook' ),
				'th_class' => 'medium', 'tooltip' => 'post-email_desc', 'td_class' => 'blank',
				'content' => $form->get_no_textarea_value( $caption_text, '', '', $caption_len ),
			);

			/*
			 * Twitter
			 */
			$caption_len = $this->p->sharing->get_tweet_max_len();
			$caption_text = $this->p->page->get_caption( $this->p->options['twitter_caption'],
				$caption_len, $mod, true, true );	// $use_cache = true, $add_hashtags = true

			$form_rows['twitter_desc'] = array(
				'label' => _x( 'Tweet Text', 'option label', 'nextgen-facebook' ),
				'th_class' => 'medium', 'tooltip' => 'post-twitter_desc', 'td_class' => 'blank',
				'content' => $form->get_no_textarea_value( $caption_text, '', '', $caption_len ),
			);

			/*
			 * Pinterest
			 */
			$caption_len = $this->p->options['pin_cap_len'];
			$caption_text = $this->p->page->get_caption( $this->p->options['pin_caption'], $caption_len, $mod );
			$force_regen = $this->p->util->is_force_regen( $mod, 'schema' );	// false by default
			$media = $this->p->og->get_media_info( $this->p->cf['lca'].'-pinterest-button',
				array( 'pid', 'img_url' ), $mod, 'schema' );	// $md_pre = 'schema'

			if ( ! empty( $media['pid'] ) ) {
				list(
					$media['img_url'],
					$img_width,
					$img_height,
					$img_cropped,
					$img_pid
				) = $this->p->media->get_attachment_image_src( $media['pid'], 'thumbnail', false, $force_regen );
			}

			$form_rows['pin_desc'] = array(
				'label' => _x( 'Pinterest Caption Text', 'option label', 'nextgen-facebook' ),
				'th_class' => 'medium', 'tooltip' => 'post-pin_desc', 'td_class' => 'blank top',
				'content' => $form->get_no_textarea_value( $caption_text, '', '', $caption_len ).
					( empty( $media['img_url'] ) ? '' : '</td><td class="top thumb_preview">'.
					'<img src="'.$media['img_url'].'" style="max-width:'.$size_info['width'].'px;">' ),
			);

			/*
			 * Tumblr
			 */
			$caption_len = $this->p->options['tumblr_cap_len'];
			$caption_text = $this->p->page->get_caption( $this->p->options['tumblr_caption'], $caption_len, $mod );
			$force_regen = $this->p->util->is_force_regen( $mod, 'og' );	// false by default
			$media = $this->p->og->get_media_info( $this->p->cf['lca'].'-tumblr-button',
				array( 'pid', 'img_url' ), $mod, 'og' );	// $md_pre = 'og'

			if ( ! empty( $media['pid'] ) ) {
				list(
					$media['img_url'],
					$img_width,
					$img_height,
					$img_cropped,
					$img_pid
				) = $this->p->media->get_attachment_image_src( $media['pid'], 'thumbnail', false, $force_regen );
			}

			$form_rows['tumblr_img_desc'] = array(
				'label' => _x( 'Tumblr Image Caption', 'option label', 'nextgen-facebook' ),
				'th_class' => 'medium', 'tooltip' => 'post-tumblr_img_desc', 'td_class' => 'blank top',
				'content' => ( empty( $media['img_url'] ) ?
					'<em>'.sprintf( __( 'Caption disabled - no suitable image found for the %s button',
						'nextgen-facebook' ), 'Tumblr' ).'</em>' :
					$form->get_no_textarea_value( $caption_text, '', '', $caption_len ).
					'</td><td class="top thumb_preview"><img src="'.$media['img_url'].'"'.
					' style="max-width:'.$size_info['width'].'px;">' ),
			);

			$form_rows['tumblr_vid_desc'] = array(
				'label' => _x( 'Tumblr Video Caption', 'option label', 'nextgen-facebook' ),
				'th_class' => 'medium', 'tooltip' => 'post-tumblr_vid_desc', 'td_class' => 'blank top',
				'content' => '<em>'.sprintf( __( 'Caption disabled - no suitable video found for the %s button',
					'nextgen-facebook' ), 'Tumblr' ).'</em>',
			);

			/*
			 * Disable Buttons Checkbox
			 */
			$form_rows['buttons_disabled'] = array(
				'label' => _x( 'Disable Sharing Buttons', 'option label', 'nextgen-facebook' ),
				'th_class' => 'medium', 'tooltip' => 'post-buttons_disabled', 'td_class' => 'blank',
				'content' => $form->get_no_checkbox( 'buttons_disabled' ),
			);

			return $form->get_md_form_rows( $table_rows, $form_rows, $head, $mod );
		}

		public function filter_styles_sharing_rows( $table_rows, $form ) {
			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}
			return $this->get_styles_common_rows( $table_rows, $form, 'sharing' );
		}

		public function filter_styles_content_rows( $table_rows, $form ) {
			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}
			return $this->get_styles_common_rows( $table_rows, $form, 'content' );
		}

		public function filter_styles_excerpt_rows( $table_rows, $form ) {
			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}
			return $this->get_styles_common_rows( $table_rows, $form, 'excerpt' );
		}

		public function filter_styles_sidebar_rows( $table_rows, $form ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			$table_rows = array_merge( $table_rows,
				$this->get_styles_common_rows( $table_rows, $form, 'sidebar' ) );

			$table_rows[] = '<tr class="hide_in_basic">'.
			$form->get_th_html( _x( 'Sidebar Javascript',
				'option label', 'nextgen-facebook' ), null, 'buttons_js_sidebar' ).
			'<td><textarea disabled="disabled" class="average code">'.
			$this->p->options['buttons_js_sidebar'].'</textarea></td>';

			return $table_rows;
		}

		public function filter_styles_shortcode_rows( $table_rows, $form ) {
			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}
			return $this->get_styles_common_rows( $table_rows, $form, 'shortcode' );
		}

		public function filter_styles_widget_rows( $table_rows, $form ) {
			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}
			return $this->get_styles_common_rows( $table_rows, $form, 'widget' );
		}

		public function filter_styles_admin_edit_rows( $table_rows, $form ) {
			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}
			return $this->get_styles_common_rows( $table_rows, $form, 'admin_edit' );
		}

		private function get_styles_common_rows( &$table_rows, &$form, $idx ) {

			$text = $this->p->msgs->get( 'info-styles-'.$idx );

			if ( isset( $this->p->options['buttons_preset_'.$idx] ) ) {
				$text .= '<p>The social sharing button options for the "'.$idx.'" style are subject to preset values selected on the '.$this->p->util->get_admin_url( 'sharing#sucom-tabset_sharing-tab_preset', 'Sharing Buttons' ).' settings page (used to modify the default behavior, size, counter orientation, etc.). The width and height values in your CSS should support these preset classes (if any).</p>';
				$text .= '<p><strong>Selected preset:</strong> '.
					( empty( $this->p->options['buttons_preset_'.$idx] ) ? '[None]' :
						$this->p->options['buttons_preset_'.$idx] ).'</p>';
			}

			$table_rows[] = '<td colspan="2" align="center">'.
				$this->p->msgs->get( 'pro-feature-msg' ).'</td>';

			$table_rows[] = '<th class="textinfo">'.$text.'</th>'.
			'<td><textarea disabled="disabled" class="tall code">'.
			$this->p->options['buttons_css_'.$idx].'</textarea></td>';

			return $table_rows;
		}
	}
}


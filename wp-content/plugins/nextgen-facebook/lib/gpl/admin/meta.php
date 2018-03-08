<?php
/*
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2012-2017 Jean-Sebastien Morisset (https://surniaulula.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'These aren\'t the droids you\'re looking for...' );
}

if ( ! class_exists( 'NgfbGplAdminMeta' ) ) {

	class NgfbGplAdminMeta {

		public function __construct( &$plugin ) {
			$this->p =& $plugin;

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			$this->p->util->add_plugin_filters( $this, array(
				'meta_text_rows' => array(
					'user_text_rows' => 4,	// $table_rows, $form, $head, $mod
					'term_text_rows' => 4,	// $table_rows, $form, $head, $mod
				),
				'meta_media_rows' => array(
					'post_media_rows' => 4,	// $table_rows, $form, $head, $mod
					'user_media_rows' => 4,	// $table_rows, $form, $head, $mod
					'term_media_rows' => 4,	// $table_rows, $form, $head, $mod
				),
			) );
		}

		public function filter_meta_text_rows( $table_rows, $form, $head, $mod ) {
			if ( $this->p->debug->enabled )
				$this->p->debug->mark();

			$table_rows[] = '<td colspan="2" align="center">'.
				$this->p->msgs->get( 'pro-feature-msg' ).
				'</td>';

			$form_rows = array(
				'og_title' => array(
					'label' => _x( 'Default Title', 'option label', 'nextgen-facebook' ),
					'th_class' => 'medium', 'tooltip' => 'meta-og_title', 'td_class' => 'blank',
					'content' => $form->get_no_input_value( $this->p->page->get_title( $this->p->options['og_title_len'],
						'...', $mod, true, false, true, 'none' ), 'wide' ),	// $md_idx = 'none'
				),
				'og_desc' => array(
					'label' => _x( 'Default Description (Facebook / Open Graph, LinkedIn, Pinterest Rich Pin)', 'option label', 'nextgen-facebook' ),
					'th_class' => 'medium', 'tooltip' => 'meta-og_desc', 'td_class' => 'blank',
					'content' => $form->get_no_textarea_value( $this->p->page->get_description( $this->p->options['og_desc_len'],
						'...', $mod, true, true, true, 'none' ), '', '', $this->p->options['og_desc_len'] ),	// $md_idx = 'none'
				),
				'seo_desc' => array(
					'tr_class' => ( $this->p->options['add_meta_name_description'] ? '' : 'hide_in_basic' ),
					'label' => _x( 'Google Search / SEO Description', 'option label', 'nextgen-facebook' ),
					'th_class' => 'medium', 'tooltip' => 'meta-seo_desc', 'td_class' => 'blank',
					'content' => $form->get_no_textarea_value( $this->p->page->get_description( $this->p->options['seo_desc_len'],
						'...', $mod, true, false ), '', '', $this->p->options['seo_desc_len'] ),	// $add_hashtags = false
				),
				'tc_desc' => array(
					'label' => _x( 'Twitter Card Description', 'option label', 'nextgen-facebook' ),
					'th_class' => 'medium', 'tooltip' => 'meta-tc_desc', 'td_class' => 'blank',
					'content' => $form->get_no_textarea_value( $this->p->page->get_description( $this->p->options['tc_desc_len'],
						'...', $mod ), '', '', $this->p->options['tc_desc_len'] ),
				),
				'sharing_url' => array(
					'tr_class' => 'hide_in_basic',
					'label' => _x( 'Sharing URL', 'option label', 'nextgen-facebook' ),
					'th_class' => 'medium', 'tooltip' => 'meta-sharing_url', 'td_class' => 'blank',
					'content' => $form->get_no_input_value( $this->p->util->get_sharing_url( $mod, false ), 'wide' ),	// $add_page = false
				),
				'subsection_schema' => array(
					'td_class' => 'subsection', 'header' => 'h4',
					'label' => _x( 'Structured Data / Schema Markup', 'metabox title', 'nextgen-facebook' )
				),
				'schema_desc' => array(
					'label' => _x( 'Schema Description', 'option label', 'nextgen-facebook' ),
					'th_class' => 'medium', 'tooltip' => 'meta-schema_desc', 'td_class' => 'blank',
					'content' => $form->get_no_textarea_value( $this->p->page->get_description( $this->p->options['schema_desc_len'],
						'...', $mod ), '', '', $this->p->options['schema_desc_len'] ),
				),
			);

			return $form->get_md_form_rows( $table_rows, $form_rows, $head, $mod );
		}

		public function filter_meta_media_rows( $table_rows, $form, $head, $mod ) {
			if ( $this->p->debug->enabled )
				$this->p->debug->mark();

			if ( $mod['is_post'] && ( empty( $mod['post_status'] ) || $mod['post_status'] === 'auto-draft' ) ) {
				$table_rows[] = '<td><blockquote class="status-info"><p class="centered">'.
					sprintf( __( 'Save a draft version or publish the %s to display these options.',
						'nextgen-facebook' ), SucomUtil::titleize( $mod['post_type'] ) ).'</p></td>';
				return $table_rows;	// abort
			}

			$media_info = $this->p->og->get_media_info( $this->p->cf['lca'].'-opengraph',
				array( 'pid', 'img_url' ), $mod, 'none', 'og', $head );	// $md_pre = none

			$table_rows[] = '<td colspan="2" align="center">'.
				( $mod['is_post'] ? $this->p->msgs->get( 'pro-about-msg-post-media' ) : '' ).
				$this->p->msgs->get( 'pro-feature-msg' ).
				'</td>';

			$form_rows['subsection_opengraph'] = array(
				'tr_class' => 'hide_in_basic',
				'td_class' => 'subsection top', 'header' => 'h4',
				'label' => _x( 'All Social WebSites / Open Graph', 'metabox title', 'nextgen-facebook' )
			);
			$form_rows['subsection_priority_image'] = array(
				'header' => 'h5',
				'label' => _x( 'Priority Image Information', 'metabox title', 'nextgen-facebook' )
			);
			$form_rows['og_img_dimensions'] = array(
				'tr_class' => 'hide_in_basic',
				'label' => _x( 'Image Dimensions', 'option label', 'nextgen-facebook' ),
				'th_class' => 'medium', 'tooltip' => 'og_img_dimensions', 'td_class' => 'blank',
				'content' => $form->get_no_input_image_dimensions( 'og_img', true ),	// $use_opts = true
			);
			$form_rows['og_img_id'] = array(
				'label' => _x( 'Image ID', 'option label', 'nextgen-facebook' ),
				'th_class' => 'medium', 'tooltip' => 'meta-og_img_id', 'td_class' => 'blank',
				'content' => $form->get_no_input_image_upload( 'og_img', $media_info['pid'], true ),
			);
			$form_rows['og_img_url'] = array(
				'label' => _x( 'or an Image URL', 'option label', 'nextgen-facebook' ),
				'th_class' => 'medium', 'tooltip' => 'meta-og_img_url', 'td_class' => 'blank',
				'content' => $form->get_no_input_value( $media_info['img_url'], 'wide' ),
			);
			if ( $mod['is_post'] ) {
				$form_rows['og_img_max'] = array(
					'tr_class' => 'hide_in_basic',
					'label' => _x( 'Maximum Images', 'option label', 'nextgen-facebook' ),
					'th_class' => 'medium', 'tooltip' => 'meta-og_img_max', 'td_class' => 'blank',
					'content' => $form->get_no_select( 'og_img_max',
						range( 0, $this->p->cf['form']['max_media_items'] ), 'medium' ),
				);
			}
			$form_rows['subsection_priority_video'] = array(
				'header' => 'h5',
				'label' => _x( 'Priority Video Information', 'metabox title', 'nextgen-facebook' )
			);
			$form_rows['og_vid_embed'] = array(
				'label' => _x( 'Video Embed HTML', 'option label', 'nextgen-facebook' ),
				'th_class' => 'medium', 'tooltip' => 'meta-og_vid_embed', 'td_class' => 'blank',
				'content' => $form->get_no_textarea_value( '' ),
			);
			$form_rows['og_vid_url'] = array(
				'label' => _x( 'or a Video URL', 'option label', 'nextgen-facebook' ),
				'th_class' => 'medium', 'tooltip' => 'meta-og_vid_url', 'td_class' => 'blank',
				'content' => $form->get_no_input_value( '', 'wide' ),
			);
			$form_rows['og_vid_title'] = array(
				'tr_class' => 'hide_in_basic',
				'label' => _x( 'Video Name / Title', 'option label', 'nextgen-facebook' ),
				'th_class' => 'medium', 'tooltip' => 'meta-og_vid_title', 'td_class' => 'blank',
				'content' => $form->get_no_input_value( '', 'wide' ),
			);
			$form_rows['og_vid_desc'] = array(
				'tr_class' => 'hide_in_basic',
				'label' => _x( 'Video Description', 'option label', 'nextgen-facebook' ),
				'th_class' => 'medium', 'tooltip' => 'meta-og_vid_desc', 'td_class' => 'blank',
				'content' => $form->get_no_input_value( '', 'wide' ),
			);
			if ( $mod['is_post'] ) {
				$form_rows['og_vid_max'] = array(
					'tr_class' => 'hide_in_basic',
					'label' => _x( 'Maximum Videos', 'option label', 'nextgen-facebook' ),
					'th_class' => 'medium', 'tooltip' => 'meta-og_vid_max', 'td_class' => 'blank',
					'content' => $form->get_no_select( 'og_vid_max', 
						range( 0, $this->p->cf['form']['max_media_items'] ), 'medium' ),
				);
			}
			$form_rows['og_vid_prev_img'] = array(
				'tr_class' => 'hide_in_basic',
				'label' => _x( 'Include Preview Images', 'option label', 'nextgen-facebook' ),
				'th_class' => 'medium', 'tooltip' => 'meta-og_vid_prev_img', 'td_class' => 'blank',
					'content' => $form->get_no_checkbox( 'og_vid_prev_img' ),
			);

			$media_info = $this->p->og->get_media_info( $this->p->cf['lca'].'-schema',
				array( 'pid', 'img_url' ), $mod, 'og', 'og', $head );
	
			$form_rows['subsection_schema'] = array(
				'tr_class' => 'hide_in_basic',
				'td_class' => 'subsection', 'header' => 'h4',
				'label' => _x( 'Structured Data / Schema Markup / Pinterest', 'metabox title', 'nextgen-facebook' )
			);
			$form_rows['schema_img_dimensions'] = array(
				'tr_class' => 'hide_in_basic',
				'label' => _x( 'Image Dimensions', 'option label', 'nextgen-facebook' ),
				'th_class' => 'medium', 'tooltip' => 'schema_img_dimensions', 'td_class' => 'blank',
				'content' => $form->get_no_input_image_dimensions( 'schema_img', true ),	// $use_opts = true
			);
			$form_rows['schema_img_id'] = array(
				'tr_class' => 'hide_in_basic',
				'label' => _x( 'Image ID', 'option label', 'nextgen-facebook' ),
				'th_class' => 'medium', 'tooltip' => 'meta-schema_img_id', 'td_class' => 'blank',
				'content' => $form->get_no_input_image_upload( 'schema_img', $media_info['pid'], true ),
			);
			$form_rows['schema_img_url'] = array(
				'tr_class' => 'hide_in_basic',
				'label' => _x( 'or an Image URL', 'option label', 'nextgen-facebook' ),
				'th_class' => 'medium', 'tooltip' => 'meta-schema_img_url', 'td_class' => 'blank',
				'content' => $form->get_no_input_value( $media_info['img_url'], 'wide' ),
			);
			if ( $mod['is_post'] ) {
				$form_rows['schema_img_max'] = array(
					'tr_class' => 'hide_in_basic',
					'label' => _x( 'Maximum Images', 'option label', 'nextgen-facebook' ),
					'th_class' => 'medium', 'tooltip' => 'meta-schema_img_max', 'td_class' => 'blank',
					'content' => $form->get_no_select( 'schema_img_max', 
						range( 0, $this->p->cf['form']['max_media_items'] ), 'medium' ),
				);
			}

			return $form->get_md_form_rows( $table_rows, $form_rows, $head, $mod );
		}
	}
}


<?php
/*
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2012-2017 Jean-Sebastien Morisset (https://surniaulula.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'These aren\'t the droids you\'re looking for...' );
}

if ( ! class_exists( 'NgfbSubmenuGeneral' ) && class_exists( 'NgfbAdmin' ) ) {

	class NgfbSubmenuGeneral extends NgfbAdmin {

		public function __construct( &$plugin, $id, $name, $lib, $ext ) {
			$this->p =& $plugin;

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			$this->menu_id = $id;
			$this->menu_name = $name;
			$this->menu_lib = $lib;
			$this->menu_ext = $ext;	// lowercase acronyn for plugin or extension
		}

		// called by the extended NgfbAdmin class
		protected function add_meta_boxes() {
			$this->maybe_show_language_notice();

			add_meta_box( $this->pagehook.'_opengraph',
				_x( 'All Social WebSites / Open Graph', 'metabox title', 'nextgen-facebook' ),
					array( &$this, 'show_metabox_opengraph' ), $this->pagehook, 'normal' );

			add_meta_box( $this->pagehook.'_publishers',
				_x( 'Specific WebSites and Publishers', 'metabox title', 'nextgen-facebook' ),
					array( &$this, 'show_metabox_publishers' ), $this->pagehook, 'normal' );

			// issues a warning notice if the default image size is too small
			// unless the NGFB_CHECK_DEFAULT_IMAGE constant has been defined as false
			if ( SucomUtil::get_const( 'NGFB_CHECK_DEFAULT_IMAGE' ) !== false ) {
				$this->p->media->get_default_images( 1, $this->p->cf['lca'].'-opengraph', false );
			}
		}

		public function show_metabox_opengraph() {
			$metabox_id = 'og';
			$tabs = apply_filters( $this->p->cf['lca'].'_general_og_tabs', array(
				'general' => _x( 'Site Information', 'metabox tab', 'nextgen-facebook' ),
				'text' => _x( 'Titles / Descriptions', 'metabox tab', 'nextgen-facebook' ),
				'author' => _x( 'Authorship', 'metabox tab', 'nextgen-facebook' ),
				'images' => _x( 'Images', 'metabox tab', 'nextgen-facebook' ),
				'videos' => _x( 'Videos', 'metabox tab', 'nextgen-facebook' ),
			) );
			$table_rows = array();
			foreach ( $tabs as $key => $title ) {
				$table_rows[$key] = apply_filters( $this->p->cf['lca'].'_'.$metabox_id.'_'.$key.'_rows',
					$this->get_table_rows( $metabox_id, $key ), $this->form );
			}
			$this->p->util->do_metabox_tabs( $metabox_id, $tabs, $table_rows );
		}

		public function show_metabox_publishers() {
			$metabox_id = 'pub';
			$tabs = apply_filters( $this->p->cf['lca'].'_general_pub_tabs', array(
				'facebook' => _x( 'Facebook', 'metabox tab', 'nextgen-facebook' ),
				'google' => _x( 'Google / Schema', 'metabox tab', 'nextgen-facebook' ),
				'pinterest' => _x( 'Pinterest', 'metabox tab', 'nextgen-facebook' ),
				'twitter' => _x( 'Twitter', 'metabox tab', 'nextgen-facebook' ),
				'other' => _x( 'Other', 'metabox tab', 'nextgen-facebook' ),
			) );
			$table_rows = array();
			foreach ( $tabs as $key => $title ) {
				$table_rows[$key] = apply_filters( $this->p->cf['lca'].'_'.$metabox_id.'_'.$key.'_rows',
					$this->get_table_rows( $metabox_id, $key ), $this->form );
			}
			$this->p->util->do_metabox_tabs( $metabox_id, $tabs, $table_rows );
		}

		protected function get_table_rows( $metabox_id, $key ) {

			$table_rows = array();
			$user_contacts = $this->p->m['util']['user']->get_form_contact_fields();

			switch ( $metabox_id.'-'.$key ) {

				case 'og-general':

					$table_rows['site_name'] = $this->form->get_th_html( _x( 'WebSite Name',
						'option label', 'nextgen-facebook' ), '', 'site_name', array( 'is_locale' => true ) ).
					'<td>'.$this->form->get_input( SucomUtil::get_key_locale( 'site_name', $this->p->options ),
						'long_name', '', 0, get_bloginfo( 'name', 'display' ) ).'</td>';

					$table_rows['site_desc'] = $this->form->get_th_html( _x( 'WebSite Description',
						'option label', 'nextgen-facebook' ), '', 'site_desc', array( 'is_locale' => true ) ).
					'<td>'.$this->form->get_textarea( SucomUtil::get_key_locale( 'site_desc', $this->p->options ),
						'', '', 0, get_bloginfo( 'description', 'display' ) ).'</td>';

					$table_rows['og_post_type'] = $this->form->get_th_html( _x( 'Default Post / Page Type',
						'option label', 'nextgen-facebook' ), '', 'og_post_type' ).
					'<td>'.$this->form->get_select( 'og_post_type', array( 'article', 'website' ) ).'</td>';

					$table_rows['og_art_section'] = $this->form->get_th_html( _x( 'Default Article Topic',
						'option label', 'nextgen-facebook' ), '', 'og_art_section' ).
					'<td>'.$this->form->get_select( 'og_art_section', $this->p->util->get_article_topics() ).'</td>';

					break;

				case 'og-text':

					$table_rows['og_title_sep'] = $this->form->get_th_html( _x( 'Title Separator',
						'option label', 'nextgen-facebook' ), '', 'og_title_sep' ).
					'<td>'.$this->form->get_input( 'og_title_sep', 'short' ).'</td>';

					$table_rows['og_title_len'] = '<tr class="hide_in_basic">'.
					$this->form->get_th_html( _x( 'Maximum Title Length',
						'option label', 'nextgen-facebook' ), '', 'og_title_len' ).
					'<td>'.
						$this->form->get_input( 'og_title_len', 'short' ).' '.
						_x( 'characters or less (hard limit), and warn at', 'option comment', 'nextgen-facebook' ).' '.
						$this->form->get_input( 'og_title_warn', 'short' ).' '.
						_x( 'characters (soft limit)', 'option comment', 'nextgen-facebook' ).
					'</td>';


					$table_rows['og_desc_len'] = '<tr class="hide_in_basic">'.
					$this->form->get_th_html( _x( 'Maximum Description Length',
						'option label', 'nextgen-facebook' ), '', 'og_desc_len' ).
					'<td>'.
						$this->form->get_input( 'og_desc_len', 'short' ).' '.
						_x( 'characters or less (hard limit), and warn at', 'option comment', 'nextgen-facebook' ).' '.
						$this->form->get_input( 'og_desc_warn', 'short' ).' '.
						_x( 'characters (soft limit)', 'option comment', 'nextgen-facebook' ).
					'</td>';

					$table_rows['og_desc_hashtags'] = $this->form->get_th_html( _x( 'Add Hashtags to Descriptions',
						'option label', 'nextgen-facebook' ), '', 'og_desc_hashtags' ).
					'<td>'.$this->form->get_select( 'og_desc_hashtags',
						range( 0, $this->p->cf['form']['max_hashtags'] ), 'short', '', true ).' '.
							_x( 'tag names', 'option comment', 'nextgen-facebook' ).'</td>';

					$table_rows['og_page_title_tag'] = '<tr class="hide_in_basic">'.
					$this->form->get_th_html( _x( 'Add Page Title in Tags / Hashtags',
						'option label', 'nextgen-facebook' ), '', 'og_page_title_tag' ).
					'<td>'.$this->form->get_checkbox( 'og_page_title_tag' ).'</td>';

					$table_rows['og_page_parent_tags'] = '<tr class="hide_in_basic">'.
					$this->form->get_th_html( _x( 'Add Parent Page Tags / Hashtags',
						'option label', 'nextgen-facebook' ), '', 'og_page_parent_tags' ).
					'<td>'.$this->form->get_checkbox( 'og_page_parent_tags' ).'</td>';

					break;

				case 'og-author':

					$table_rows['og_author_field'] = $this->form->get_th_html( _x( 'Author Profile URL Field',
						'option label', 'nextgen-facebook' ), '', 'og_author_field' ).
					'<td>'.$this->form->get_select( 'og_author_field', $user_contacts ).'</td>';

					$table_rows['og_author_fallback'] = '<tr class="hide_in_basic">'.
					$this->form->get_th_html( _x( 'Fallback to Author\'s Archive Page',
						'option label', 'nextgen-facebook' ), '', 'og_author_fallback' ).
					'<td>'.$this->form->get_checkbox( 'og_author_fallback' ).'</td>';

					break;

				case 'og-images':

					$table_rows['og_img_max'] = '<tr class="hide_in_basic">'.
					$this->form->get_th_html( _x( 'Maximum Images to Include',
						'option label', 'nextgen-facebook' ), '', 'og_img_max' ).
					'<td>'.$this->form->get_select( 'og_img_max',
						range( 0, $this->p->cf['form']['max_media_items'] ), 'short', '', true ).
					( empty( $this->form->options['og_vid_prev_img'] ) ?
						'' : ' <em>'._x( 'video preview images are enabled (and included first)',
							'option comment', 'nextgen-facebook' ).'</em>' ).'</td>';

					$table_rows['og_img'] = $this->form->get_th_html( _x( 'Open Graph Image Dimensions',
						'option label', 'nextgen-facebook' ), '', 'og_img_dimensions' ).
					'<td>'.$this->form->get_input_image_dimensions( 'og_img' ).'</td>';	// $use_opts = false

					$table_rows['og_def_img_id'] = $this->form->get_th_html( _x( 'Default / Fallback Image ID',
						'option label', 'nextgen-facebook' ), '', 'og_def_img_id' ).
					'<td>'.$this->form->get_input_image_upload( 'og_def_img' ).'</td>';

					$table_rows['og_def_img_url'] = $this->form->get_th_html( _x( 'or Default / Fallback Image URL',
						'option label', 'nextgen-facebook' ), '', 'og_def_img_url' ).
					'<td>'.$this->form->get_input_image_url( 'og_def_img' ).'</td>';

					$table_rows['og_def_img_on_index'] = '<tr class="hide_in_basic">'.
					$this->form->get_th_html( _x( 'Use Default Image on Archive',
						'option label', 'nextgen-facebook' ), '', 'og_def_img_on_index' ).
					'<td>'.$this->form->get_checkbox( 'og_def_img_on_index' ).'</td>';

					$table_rows['og_def_img_on_search'] = '<tr class="hide_in_basic">'.
					$this->form->get_th_html( _x( 'Use Default Image on Search Results',
						'option label', 'nextgen-facebook' ), '', 'og_def_img_on_search' ).
					'<td>'.$this->form->get_checkbox( 'og_def_img_on_search' ).'</td>';

					if ( $this->p->avail['media']['ngg'] === true ) {
						$table_rows['og_ngg_tags'] = '<tr class="hide_in_basic">'.
						$this->form->get_th_html( _x( 'Add Tags from NGG Featured Image',
							'option label', 'nextgen-facebook' ), '', 'og_ngg_tags' ).
						'<td>'.$this->form->get_checkbox( 'og_ngg_tags' ).'</td>';
					}

					break;

				case 'og-videos':

					break;

				case 'pub-facebook':

					$table_rows['fb_publisher_url'] = $this->form->get_th_html( _x( 'Facebook Business Page URL',
						'option label', 'nextgen-facebook' ), '', 'fb_publisher_url', array( 'is_locale' => true ) ).
					'<td>'.$this->form->get_input( SucomUtil::get_key_locale( 'fb_publisher_url',
						$this->p->options ), 'wide' ).'</td>';

					$table_rows['fb_app_id'] = $this->form->get_th_html( _x( 'Facebook Application ID',
						'option label', 'nextgen-facebook' ), '', 'fb_app_id' ).
					'<td>'.$this->form->get_input( 'fb_app_id' ).'</td>';

					$table_rows['fb_admins'] = $this->form->get_th_html( _x( 'or Facebook Admin Username(s)',
						'option label', 'nextgen-facebook' ), '', 'fb_admins' ).
					'<td>'.$this->form->get_input( 'fb_admins' ).'</td>';

					$table_rows['fb_author_name'] = '<tr class="hide_in_basic">'.
					$this->form->get_th_html( _x( 'Author Name Format',
						'option label', 'nextgen-facebook' ), '', 'fb_author_name' ).
					'<td>'.$this->form->get_select( 'fb_author_name',
						$this->p->cf['form']['user_name_fields'] ).'</td>';

					$fb_pub_lang = SucomUtil::get_pub_lang( 'facebook' );
					$fb_locale_key = SucomUtil::get_key_locale( 'fb_locale', $this->p->options );
					$table_rows['fb_locale'] = '<tr class="hide_in_basic">'.
					$this->form->get_th_html( _x( 'Custom Facebook Locale',
						'option label', 'nextgen-facebook' ), '', 'fb_locale', array( 'is_locale' => true ) ).
					'<td>'.$this->form->get_select( $fb_locale_key, $fb_pub_lang ).'</td>';

					break;

				case 'pub-google':

					$table_rows['seo_publisher_url'] = $this->form->get_th_html( _x( 'Google+ Business Page URL',
						'option label', 'nextgen-facebook' ), '', 'seo_publisher_url', array( 'is_locale' => true ) ).
					'<td>'.$this->form->get_input( SucomUtil::get_key_locale( 'seo_publisher_url',
						$this->p->options ), 'wide' ).'</td>';

					$table_rows['seo_desc_len'] = '<tr class="hide_in_basic">'.
					$this->form->get_th_html( _x( 'Search / SEO Description Length',
						'option label', 'nextgen-facebook' ), '', 'seo_desc_len' ).
					'<td>'.$this->form->get_input( 'seo_desc_len', 'short' ).' '.
						_x( 'characters or less', 'option comment', 'nextgen-facebook' ).'</td>';

					$table_rows['seo_author_field'] = '<tr class="hide_in_basic">'.
					$this->form->get_th_html( _x( 'Author Link URL Profile Contact',
						'option label', 'nextgen-facebook' ), '', 'seo_author_field' ).
					'<td>'.$this->form->get_select( 'seo_author_field', $user_contacts ).'</td>';

					$table_rows['subsection_google_schema'] = '<td></td><td class="subsection"><h4>'.
						_x( 'Structured Data / Schema Markup', 'metabox title', 'nextgen-facebook' ).'</h4></td>';

					$noscript_disabled = apply_filters( $this->p->cf['lca'].'_add_schema_noscript_array', true ) ? false : true;
					$noscript_comment_transl = _x( 'option disabled by extension plugin or custom filter', 'option comment', 'nextgen-facebook' );

					if ( ! $noscript_disabled ) {
						$table_rows['schema_add_noscript'] = '<tr class="hide_in_basic">'.
						$this->form->get_th_html( _x( 'Meta Property Containers',
							'option label', 'wpsso' ), '', 'schema_add_noscript' ).
						'<td>'.( $noscript_disabled ? $this->form->get_no_checkbox( 'schema_add_noscript', '', '', 0 ).
							' <em>'.$noscript_comment_transl.'</em>' : $this->form->get_checkbox( 'schema_add_noscript' ) ).'</td>';
					}

					$this->add_schema_knowledge_graph_table_rows( $table_rows );

					$this->add_schema_item_props_table_rows( $table_rows );

					$this->add_schema_item_types_table_rows( $table_rows, 'hide_in_basic' );	// hide all in basic view

					break;

				case 'pub-pinterest':

					$table_rows['p_publisher_url'] = $this->form->get_th_html( _x( 'Pinterest Company Page URL',
						'option label', 'nextgen-facebook' ), '', 'p_publisher_url', array( 'is_locale' => true ) ).
					'<td>'.$this->form->get_input( SucomUtil::get_key_locale( 'p_publisher_url',
						$this->p->options ), 'wide' ).'</td>';

					$table_rows['p_dom_verify'] = '<tr class="hide_in_basic">'.
					$this->form->get_th_html( _x( 'Pinterest Verification ID',
						'option label', 'nextgen-facebook' ), '', 'p_dom_verify' ).
					'<td>'.$this->form->get_input( 'p_dom_verify', 'api_key' ).'</td>';

					$table_rows['p_author_name'] = '<tr class="hide_in_basic">'.
					$this->form->get_th_html( _x( 'Author Name Format',
						'option label', 'nextgen-facebook' ), '', 'p_author_name' ).
					'<td>'.$this->form->get_select( 'p_author_name',
						$this->p->cf['form']['user_name_fields'] ).'</td>';

					$table_rows['p_add_img_html'] = $this->form->get_th_html( _x( 'Add Hidden Image for Pin It Button',
						'option label', 'nextgen-facebook' ), '', 'p_add_img_html' ).
					'<td>'.$this->form->get_checkbox( 'p_add_img_html' ).'</td>';

					$table_rows['p_add_nopin_header_img_tag'] = $this->form->get_th_html( _x( 'Add "nopin" to Header Image Tag',
						'option label', 'nextgen-facebook' ), '', 'p_add_nopin_header_img_tag' ).
					'<td>'.$this->form->get_checkbox( 'p_add_nopin_header_img_tag' ).'</td>';

					$table_rows['p_add_nopin_media_img_tag'] = $this->form->get_th_html( _x( 'Add "nopin" to Media Lib Images',
						'option label', 'nextgen-facebook' ), '', 'p_add_nopin_media_img_tag' ).
					'<td>'.$this->form->get_checkbox( 'p_add_nopin_media_img_tag' ).'</td>';

					break;

				case 'pub-twitter':

					$tc_types = array(
						'summary' => _x( 'Summary', 'option value', 'nextgen-facebook' ),
						'summary_large_image' => _x( 'Summary Large Image', 'option value', 'nextgen-facebook' ),
					);

					$table_rows['tc_site'] = $this->form->get_th_html( _x( 'Twitter Business @username',
						'option label', 'nextgen-facebook' ), '', 'tc_site', array( 'is_locale' => true ) ).
					'<td>'.$this->form->get_input( SucomUtil::get_key_locale( 'tc_site',
						$this->p->options ) ).'</td>';

					$table_rows['tc_desc_len'] = '<tr class="hide_in_basic">'.
					$this->form->get_th_html( _x( 'Maximum Description Length',
						'option label', 'nextgen-facebook' ), '', 'tc_desc_len' ).
					'<td>'.$this->form->get_input( 'tc_desc_len', 'short' ).' '.
						_x( 'characters or less', 'option comment', 'nextgen-facebook' ).'</td>';

					$table_rows['tc_type_singular'] = '<tr class="hide_in_basic">'.
					$this->form->get_th_html( _x( 'Twitter Card for Post / Page Image',
						'option label', 'nextgen-facebook' ), '', 'tc_type_post' ).
					'<td>'.$this->form->get_select( 'tc_type_post', $tc_types ).'</td>';

					$table_rows['tc_type_default'] = '<tr class="hide_in_basic">'.
					$this->form->get_th_html( _x( 'Twitter Card Type by Default',
						'option label', 'nextgen-facebook' ), '', 'tc_type_default' ).
					'<td>'.$this->form->get_select( 'tc_type_default', $tc_types ).'</td>';

					$table_rows['tc_sum_img'] = $this->form->get_th_html( _x( '<em>Summary</em> Card Image Dimensions',
						'option label', 'nextgen-facebook' ), '', 'tc_sum_img_dimensions' ).
					'<td>'.$this->form->get_input_image_dimensions( 'tc_sum_img' ).'</td>';	// $use_opts = false

					$table_rows['tc_lrg_img'] = $this->form->get_th_html( _x( '<em>Large Image</em> Card Img Dimensions',
						'option label', 'nextgen-facebook' ), '', 'tc_lrg_img_dimensions' ).
					'<td>'.$this->form->get_input_image_dimensions( 'tc_lrg_img' ).'</td>';	// $use_opts = false

					break;

				case 'pub-other':

					$social_accounts = apply_filters( $this->p->cf['lca'].'_social_accounts',
						$this->p->cf['form']['social_accounts'] );

					asort( $social_accounts );	// sort by label and maintain key association

					foreach ( $social_accounts as $key => $label ) {
						// skip options shown in previous tabs
						switch ( $key ) {
							case 'fb_publisher_url':
							case 'seo_publisher_url':
							case 'p_publisher_url':
							case 'tc_site':
								continue 2;
						}

						$table_rows[$key] = $this->form->get_th_html( _x( $label, 'option value', 'nextgen-facebook' ),
							'nowrap', $key, array( 'is_locale' => true ) ).
						'<td>'.$this->form->get_input( SucomUtil::get_key_locale( $key, $this->p->options ),
							( strpos( $key, '_url' ) ? 'wide' : '' ) ).'</td>';
					}

					break;
			}
			return $table_rows;
		}
	}
}


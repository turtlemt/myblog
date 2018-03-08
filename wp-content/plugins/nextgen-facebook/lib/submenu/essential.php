<?php
/*
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2012-2017 Jean-Sebastien Morisset (https://surniaulula.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'These aren\'t the droids you\'re looking for...' );
}

if ( ! class_exists( 'NgfbSubmenuEssential' ) && class_exists( 'NgfbAdmin' ) ) {

	class NgfbSubmenuEssential extends NgfbAdmin {

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

			add_meta_box( $this->pagehook.'_general',
				_x( 'Essential General Settings', 'metabox title', 'nextgen-facebook' ),
					array( &$this, 'show_metabox_general' ), $this->pagehook, 'normal' );

			add_meta_box( $this->pagehook.'_advanced',
				_x( 'Optional Advanced Settings', 'metabox title', 'nextgen-facebook' ),
					array( &$this, 'show_metabox_advanced' ), $this->pagehook, 'normal' );

			// issues a warning notice if the default image size is too small
			// unless the NGFB_CHECK_DEFAULT_IMAGE constant has been defined as false
			if ( SucomUtil::get_const( 'NGFB_CHECK_DEFAULT_IMAGE' ) !== false ) {
				$this->p->media->get_default_images( 1, $this->p->cf['lca'].'-opengraph', false );
			}
		}

		public function show_metabox_general() {
			$metabox_id = $this->menu_id;
			$key = 'general';
			$this->p->util->do_table_rows( apply_filters( $this->p->cf['lca'].'_'.$metabox_id.'_'.$key.'_rows',
				$this->get_table_rows( $metabox_id, $key ), $this->form, false ), 'metabox-'.$metabox_id.'-'.$key );
		}

		public function show_metabox_advanced() {
			$metabox_id = $this->menu_id;
			$key = 'advanced';
			$this->p->util->do_table_rows( apply_filters( $this->p->cf['lca'].'_'.$metabox_id.'_'.$key.'_rows',
				$this->get_table_rows( $metabox_id, $key ), $this->form, false ), 'metabox-'.$metabox_id.'-'.$key );
		}

		protected function get_table_rows( $metabox_id, $key ) {
			$table_rows = array();
			switch ( $metabox_id.'-'.$key ) {
				case 'essential-general':

					$table_rows['subsection_site_information'] = '<td></td><td class="subsection top"><h4>'.
						_x( 'Site Information', 'metabox title', 'nextgen-facebook' ).'</h4></td>';

					$table_rows['site_name'] = $this->form->get_th_html( _x( 'WebSite Name',
						'option label', 'nextgen-facebook' ), null, 'site_name', array( 'is_locale' => true ) ).
					'<td>'.$this->form->get_input( SucomUtil::get_key_locale( 'site_name', $this->p->options ),
						null, null, null, get_bloginfo( 'name', 'display' ) ).'</td>';

					$table_rows['site_desc'] = $this->form->get_th_html( _x( 'WebSite Description',
						'option label', 'nextgen-facebook' ), null, 'site_desc', array( 'is_locale' => true ) ).
					'<td>'.$this->form->get_textarea( SucomUtil::get_key_locale( 'site_desc', $this->p->options ),
						null, null, null, get_bloginfo( 'description', 'display' ) ).'</td>';

					$table_rows['og_art_section'] = $this->form->get_th_html( _x( 'Default Article Topic',
						'option label', 'nextgen-facebook' ), null, 'og_art_section' ).
					'<td>'.$this->form->get_select( 'og_art_section', $this->p->util->get_article_topics() ).'</td>';

					$table_rows['subsection_opengraph'] = '<td></td><td class="subsection"><h4>'.
						_x( 'Facebook / Open Graph', 'metabox title', 'nextgen-facebook' ).'</h4></td>';

					$table_rows['fb_publisher_url'] = $this->form->get_th_html( _x( 'Facebook Business Page URL',
						'option label', 'nextgen-facebook' ), null, 'fb_publisher_url', array( 'is_locale' => true ) ).
					'<td>'.$this->form->get_input( SucomUtil::get_key_locale( 'fb_publisher_url', $this->p->options ), 'wide' ).'</td>';

					$table_rows['fb_app_id'] = $this->form->get_th_html( _x( 'Facebook Application ID',
						'option label', 'nextgen-facebook' ), null, 'fb_app_id' ).
					'<td>'.$this->form->get_input( 'fb_app_id' ).'</td>';

					$table_rows['fb_admins'] = $this->form->get_th_html( _x( 'or Facebook Admin Username(s)',
						'option label', 'nextgen-facebook' ), null, 'fb_admins' ).
					'<td>'.$this->form->get_input( 'fb_admins' ).'</td>';

					$table_rows['og_def_img_id'] = $this->form->get_th_html( _x( 'Default / Fallback Image ID',
						'option label', 'nextgen-facebook' ), null, 'og_def_img_id' ).
					'<td>'.$this->form->get_input_image_upload( 'og_def_img' ).'</td>';

					$table_rows['og_def_img_url'] = $this->form->get_th_html( _x( 'or Default / Fallback Image URL',
						'option label', 'nextgen-facebook' ), null, 'og_def_img_url' ).
					'<td>'.$this->form->get_input_image_url( 'og_def_img' ).'</td>';

					$table_rows['subsection_google_schema'] = '<td></td><td class="subsection"><h4>'.
						_x( 'Google / Schema', 'metabox title', 'nextgen-facebook' ).'</h4></td>';

					$table_rows['seo_publisher_url'] = $this->form->get_th_html( _x( 'Google+ Business Page URL',
						'option label', 'nextgen-facebook' ), null, 'seo_publisher_url', array( 'is_locale' => true ) ).
					'<td>'.$this->form->get_input( SucomUtil::get_key_locale( 'seo_publisher_url', $this->p->options ), 'wide' ).'</td>';

					$this->add_schema_knowledge_graph_table_rows( $table_rows );

					$table_rows['schema_logo_url'] = $this->form->get_th_html(
						'<a href="https://developers.google.com/structured-data/customize/logos">'.
						_x( 'Organization Logo URL', 'option label', 'nextgen-facebook' ).'</a>',
							'', 'schema_logo_url', array( 'is_locale' => true ) ).
					'<td>'.$this->form->get_input( SucomUtil::get_key_locale( 'schema_logo_url', $this->p->options ), 'wide' ).'</td>';

					$table_rows['schema_banner_url'] = $this->form->get_th_html( _x( 'Organization Banner URL',
						'option label', 'nextgen-facebook' ), '', 'schema_banner_url', array( 'is_locale' => true ) ).
					'<td>'.$this->form->get_input( SucomUtil::get_key_locale( 'schema_banner_url', $this->p->options ), 'wide' ).'</td>';

					$table_rows['subsection_pinterest'] = '<td></td><td class="subsection"><h4>'.
						_x( 'Pinterest', 'metabox title', 'nextgen-facebook' ).'</h4></td>';

					$table_rows['p_publisher_url'] = $this->form->get_th_html( _x( 'Pinterest Company Page URL',
						'option label', 'nextgen-facebook' ), null, 'p_publisher_url', array( 'is_locale' => true ) ).
					'<td>'.$this->form->get_input( SucomUtil::get_key_locale( 'p_publisher_url', $this->p->options ), 'wide' ).'</td>';

					$table_rows['subsection_twitter'] = '<td></td><td class="subsection"><h4>'.
						_x( 'Twitter', 'metabox title', 'nextgen-facebook' ).'</h4></td>';

					$table_rows['tc_site'] = $this->form->get_th_html( _x( 'Twitter Business @username',
						'option label', 'nextgen-facebook' ), null, 'tc_site', array( 'is_locale' => true ) ).
					'<td>'.$this->form->get_input( SucomUtil::get_key_locale( 'tc_site', $this->p->options ) ).'</td>';

					break;

				case 'essential-advanced':

					$this->add_essential_advanced_table_rows( $table_rows );

					unset ( $table_rows['plugin_shortcodes'] );
					unset ( $table_rows['plugin_widgets'] );

					break;
			}
			return $table_rows;
		}
	}
}


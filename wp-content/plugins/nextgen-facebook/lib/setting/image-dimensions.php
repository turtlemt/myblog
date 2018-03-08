<?php
/*
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2012-2017 Jean-Sebastien Morisset (https://surniaulula.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'These aren\'t the droids you\'re looking for...' );
}

if ( ! class_exists( 'NgfbSettingImagedimensions' ) && class_exists( 'NgfbAdmin' ) ) {

	class NgfbSettingImagedimensions extends NgfbAdmin {

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

		protected function add_plugin_hooks() {
			$this->p->util->add_plugin_filters( $this, array(
				'action_buttons' => 1,
			) );
		}

		// called by the extended NgfbAdmin class
		protected function add_meta_boxes() {
			add_meta_box( $this->pagehook.'_image_dimensions',
				_x( 'Image Dimensions', 'metabox title', 'nextgen-facebook' ),
					array( &$this, 'show_metabox_image_dimensions' ), $this->pagehook, 'normal' );
		}

		public function filter_action_buttons( $action_buttons ) {
			$action_buttons[0]['reload_default_sizes'] = _x( 'Reload Default Sizes',
				'submit button', 'nextgen-facebook' );
			return $action_buttons;
		}

		public function show_metabox_image_dimensions() {
			$metabox_id = $this->menu_id;
			echo '<table class="sucom-settings '.$this->p->cf['lca'].'">';

			$table_rows = array_merge( $this->get_table_rows( $metabox_id, 'general' ),
				apply_filters( SucomUtil::sanitize_hookname( $this->p->cf['lca'].'_'.$metabox_id.'_general_rows' ),
					array(), $this->form ) );

			sort( $table_rows );

			foreach ( $table_rows as $num => $row ) {
				echo '<tr>'.$row.'</tr>'."\n";
			}
			echo '</table>';
		}

		protected function get_table_rows( $metabox_id, $key ) {
			$table_rows = array();

			switch ( $metabox_id.'-'.$key ) {

				case 'image-dimensions-general':

					$table_rows['og_img_dimensions'] = $this->form->get_th_html( _x( 'Facebook / Open Graph',
						'option label', 'nextgen-facebook' ), null, 'og_img_dimensions' ).
					'<td>'.$this->form->get_input_image_dimensions( 'og_img' ).'</td>';	// $use_opts = false

					$table_rows['schema_img_dimensions'] = $this->form->get_th_html( _x( 'Google / Schema / Pinterest',
						'option label', 'nextgen-facebook' ), null, 'schema_img_dimensions' ).
					'<td>'.$this->form->get_input_image_dimensions( 'schema_img' ).'</td>';	// $use_opts = false

					$table_rows['tc_sum_img_dimensions'] = $this->form->get_th_html( _x( 'Twitter <em>Summary</em> Card',
						'option label', 'nextgen-facebook' ), null, 'tc_sum_img_dimensions' ).
					'<td>'.$this->form->get_input_image_dimensions( 'tc_sum_img' ).'</td>';	// $use_opts = false

					$table_rows['tc_lrg_img_dimensions'] = $this->form->get_th_html( _x( 'Twitter <em>Large Image Summary</em> Card',
						'option label', 'nextgen-facebook' ), null, 'tc_lrg_img_dimensions' ).
					'<td>'.$this->form->get_input_image_dimensions( 'tc_lrg_img' ).'</td>';	// $use_opts = false

					break;
			}
			return $table_rows;
		}
	}
}


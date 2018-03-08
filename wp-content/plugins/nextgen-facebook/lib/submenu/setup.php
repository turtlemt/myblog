<?php
/*
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2012-2017 Jean-Sebastien Morisset (https://surniaulula.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'These aren\'t the droids you\'re looking for...' );
}

if ( ! class_exists( 'NgfbSubmenuSetup' ) && class_exists( 'NgfbAdmin' ) ) {

	class NgfbSubmenuSetup extends NgfbAdmin {

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
			add_meta_box( $this->pagehook.'_setup_guide',
				_x( 'Setup Guide', 'metabox title', 'nextgen-facebook' ),
					array( &$this, 'show_metabox_setup_guide' ),
						$this->pagehook, 'normal' );
		}

		public function filter_action_buttons( $action_buttons ) {
			unset( $action_buttons[0] );
			return $action_buttons;
		}

		public function show_metabox_setup_guide() {
			$lca = $this->p->cf['lca'];
			echo '<table class="sucom-settings '.$lca.' html-content-metabox">';
			echo '<tr><td>';
			echo $this->get_config_url_content( 'ngfb', 'html/setup.html' );
			echo '</td></tr></table>';
		}
	}
}


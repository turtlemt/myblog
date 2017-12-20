<?php
/*
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2012-2017 Jean-Sebastien Morisset (https://surniaulula.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'These aren\'t the droids you\'re looking for...' );
}

if ( ! class_exists( 'NgfbSubmenuLicenses' ) && class_exists( 'NgfbAdmin' ) ) {

	class NgfbSubmenuLicenses extends NgfbAdmin {

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
			add_meta_box( $this->pagehook.'_licenses',
				_x( 'Extension Plugins and Pro Licenses', 'metabox title', 'nextgen-facebook' ),
					array( &$this, 'show_metabox_licenses' ), $this->pagehook, 'normal' );
		}

		public function show_metabox_licenses() {
			$this->licenses_metabox_content( false );	// $network = false
		}
	}
}


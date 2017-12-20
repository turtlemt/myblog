<?php
/*
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2012-2017 Jean-Sebastien Morisset (https://surniaulula.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'These aren\'t the droids you\'re looking for...' );
}

if ( ! class_exists( 'NgfbSitesubmenuSiteadvanced' ) && class_exists( 'NgfbAdmin' ) ) {

	class NgfbSitesubmenuSiteadvanced extends NgfbAdmin {

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

		protected function set_form_object( $menu_ext ) {
			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
				$this->p->debug->log( 'setting site form object for '.$menu_ext );
			}
			$def_site_opts = $this->p->opt->get_site_defaults();
			$this->form = new SucomForm( $this->p, NGFB_SITE_OPTIONS_NAME, $this->p->site_options, $def_site_opts, $menu_ext );
		}

		// called by the extended NgfbAdmin class
		protected function add_meta_boxes() {
			add_meta_box( $this->pagehook.'_plugin',
				_x( 'Network Advanced Settings', 'metabox title', 'nextgen-facebook' ),
					array( &$this, 'show_metabox_plugin' ), $this->pagehook, 'normal' );

			// add a class to set a minimum width for the network postboxes
			add_filter( 'postbox_classes_'.$this->pagehook.'_'.$this->pagehook.'_plugin',
				array( &$this, 'add_class_postbox_network' ) );
		}

		public function add_class_postbox_network( $classes ) {
			$classes[] = 'postbox-network';
			return $classes;
		}

		public function show_metabox_plugin() {
			$metabox_id = 'plugin';
			$tabs = apply_filters( $this->p->cf['lca'].'_siteadvanced_plugin_tabs', array(
				'settings' => _x( 'Plugin Settings', 'metabox tab', 'nextgen-facebook' ),
				'cache' => _x( 'Cache Settings', 'metabox tab', 'nextgen-facebook' ),
			) );
			$table_rows = array();
			foreach ( $tabs as $key => $title )
				$table_rows[$key] = array_merge( $this->get_table_rows( $metabox_id, $key ),
					apply_filters( $this->p->cf['lca'].'_'.$metabox_id.'_'.$key.'_rows',
						array(), $this->form, true ) );	// $network = true
			$this->p->util->do_metabox_tabs( $metabox_id, $tabs, $table_rows );
		}

		protected function get_table_rows( $metabox_id, $key ) {
			$table_rows = array();
			switch ( $metabox_id.'-'.$key ) {
				case 'plugin-settings':

					$this->add_essential_advanced_table_rows( $table_rows, true );	// $network = true

					break;
			}
			return $table_rows;
		}
	}
}


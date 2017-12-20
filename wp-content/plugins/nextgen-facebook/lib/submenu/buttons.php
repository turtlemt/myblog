<?php
/*
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2012-2017 Jean-Sebastien Morisset (https://surniaulula.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'These aren\'t the droids you\'re looking for...' );
}

if ( ! class_exists( 'NgfbSubmenuButtons' ) && class_exists( 'NgfbAdmin' ) ) {

	class NgfbSubmenuButtons extends NgfbAdmin {

		public $website = array();

		private $max_cols = 2;

		public function __construct( &$plugin, $id, $name, $lib, $ext ) {
			$this->p =& $plugin;

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			$this->menu_id = $id;
			$this->menu_name = $name;
			$this->menu_lib = $lib;
			$this->menu_ext = $ext;	// lowercase acronyn for plugin or extension

			$this->set_objects();
		}

		private function set_objects() {
			foreach ( $this->p->cf['plugin']['ngfb']['lib']['website'] as $id => $name ) {
				$classname = NgfbConfig::load_lib( false, 'website/'.$id, 'ngfbsubmenuwebsite'.$id );
				if ( $classname !== false && class_exists( $classname ) ) {
					$this->website[$id] = new $classname( $this->p );
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $classname.' class loaded' );
					}
				}
			}
		}

		protected function add_plugin_hooks() {
			$this->p->util->add_plugin_actions( $this, array(
				'form_content_metaboxes_buttons' => 1,	// show two-column metaboxes
			) );
		}

		// called by the extended NgfbAdmin class
		protected function add_meta_boxes() {

			add_meta_box( $this->pagehook.'_buttons',
				_x( 'Social Sharing Buttons', 'metabox title', 'nextgen-facebook' ),
					array( &$this, 'show_metabox_buttons' ),
						$this->pagehook, 'normal' );

			$col = 0;
			$ids = $this->p->sharing->get_website_object_ids( $this->website );

			foreach ( $ids as $id => $name ) {
				$col = $col >= $this->max_cols ? 1 : $col + 1;
				$name = $name == 'GooglePlus' ? 'Google+' : $name;
				$pos_id = 'website_col_'.$col;	// ids must use underscores instead of hyphens to order metaboxes
				$prio = 'default';
				$args = array( 'id' => $id, 'name' => $name );

				add_meta_box( $this->pagehook.'_'.$id, $name,
					array( &$this, 'show_metabox_website' ),
						$this->pagehook, $pos_id, $prio, $args );

				add_filter( 'postbox_classes_'.$this->pagehook.'_'.$this->pagehook.'_'.$id,
					array( &$this, 'add_class_postbox_website' ) );
			}

			// close all website metaboxes by default
			NgfbUser::reset_metabox_prefs( $this->pagehook, array_keys( $ids ), 'closed' );
		}

		public function add_class_postbox_website( $classes ) {
			$show_opts = NgfbUser::show_opts();
			$classes[] = 'postbox-website';
			if ( ! empty( $show_opts ) ) {
				$classes[] = 'postbox-show_'.$show_opts;
			}
			return $classes;
		}

		// show two-column metaboxes for sharing buttons
		public function action_form_content_metaboxes_buttons( $pagehook ) {
			if ( ! empty( $this->website ) ) {
				foreach ( range( 1, $this->max_cols ) as $col ) {
					// ids must use underscores instead of hyphens to order metaboxes
					echo '<div id="website_col_'.$col.'" class="max_cols_'.$this->max_cols.' website_col">';
					do_meta_boxes( $pagehook, 'website_col_'.$col, null );
					echo '</div><!-- #website_col_'.$col.' -->'."\n";
				}
				echo '<div style="clear:both;"></div>'."\n";
			}
		}

		public function show_metabox_buttons() {
			$lca = $this->p->cf['lca'];
			$metabox_id = 'buttons';
			$tabs = apply_filters( $lca.'_buttons_tabs', array(
				'include' => _x( 'Include Buttons', 'metabox tab', 'nextgen-facebook' ),
				'position' => _x( 'Buttons Position', 'metabox tab', 'nextgen-facebook' ),
				'preset' => _x( 'Buttons Presets', 'metabox tab', 'nextgen-facebook' ),
				'advanced' => _x( 'Advanced Settings', 'metabox tab', 'nextgen-facebook' ),
			) );
			$table_rows = array();
			foreach ( $tabs as $key => $title ) {
				$table_rows[$key] = array_merge( $this->get_table_rows( $metabox_id, $key ),
					apply_filters( $lca.'_'.$metabox_id.'_'.$key.'_rows', array(), $this->form ) );
			}
			$this->p->util->do_metabox_tabs( $metabox_id, $tabs, $table_rows );
		}

		public function show_metabox_website( $post, $callback ) {

			$lca = $this->p->cf['lca'];
			$args = $callback['args'];
			$metabox_id = 'website';
			$tabs = apply_filters( $lca.'_'.$metabox_id.'_'.$args['id'].'_tabs', array() );

			if ( empty( $tabs ) ) {
				$this->p->util->do_table_rows( apply_filters( $lca.'_'.$metabox_id.'_'.$args['id'].'_rows',
					array(), $this->form, $this ), 'metabox-'.$metabox_id.'-'.$args['id'], 'metabox-'.$metabox_id );
			} else {
				foreach ( $tabs as $tab => $title ) {
					$table_rows[$tab] = apply_filters( $lca.'_'.$metabox_id.'_'.$args['id'].'_'.$tab.'_rows',
						array(), $this->form, $this );
				}
				$this->p->util->do_metabox_tabs( $metabox_id.'_'.$args['id'], $tabs, $table_rows );
			}
		}

		protected function get_table_rows( $metabox_id, $key ) {
			$table_rows = array();
			switch ( $metabox_id.'-'.$key ) {

				case 'buttons-include':

					$table_rows[] = $this->form->get_th_html( _x( 'Include on Archive Webpages',
						'option label', 'nextgen-facebook' ), null, 'buttons_on_index' ).
					'<td>'.$this->form->get_checkbox( 'buttons_on_index' ).'</td>';

					$table_rows[] = $this->form->get_th_html( _x( 'Include on Static Front Page',
						'option label', 'nextgen-facebook' ), null, 'buttons_on_front' ).
					'<td>'.$this->form->get_checkbox( 'buttons_on_front' ).'</td>';

					break;

				case 'buttons-position':

					$table_rows[] = $this->form->get_th_html( _x( 'Position in Content Text',
						'option label', 'nextgen-facebook' ), null, 'buttons_pos_content' ).
					'<td>'.$this->form->get_select( 'buttons_pos_content',
						$this->p->cf['sharing']['position'] ).'</td>';

					$table_rows[] = $this->form->get_th_html( _x( 'Position in Excerpt Text',
						'option label', 'nextgen-facebook' ), null, 'buttons_pos_excerpt' ).
					'<td>'.$this->form->get_select( 'buttons_pos_excerpt',
						$this->p->cf['sharing']['position'] ).'</td>';

					break;
			}
			return $table_rows;
		}

		public function show_on_checkboxes( $opt_prefix ) {

			$col = 0;
			$max = 2;
			$html = '<table>';
			$lca = $this->p->cf['lca'];
			$aop = $this->p->check->aop( 'ngfb', true, $this->p->avail['*']['p_dir'] );
			$show_on = apply_filters( $lca.'_buttons_show_on', $this->p->cf['sharing']['show_on'], $opt_prefix );

			foreach ( $show_on as $opt_suffix => $short_desc ) {

				$css_class = isset( $this->p->options[$opt_prefix.'_on_'.$opt_suffix.':is'] ) &&
					$this->p->options[$opt_prefix.'_on_'.$opt_suffix.':is'] === 'disabled' &&
						! $aop ? 'show_on blank' : 'show_on';

				$col++;

				if ( $col === 1 ) {
					$html .= '<tr><td class="'.$css_class.'">';
				} else {
					$html .= '<td class="'.$css_class.'">';
				}

				$html .= $this->form->get_checkbox( $opt_prefix.'_on_'.$opt_suffix ).
					_x( $short_desc, 'option value', 'nextgen-facebook' ).'&nbsp; ';

				if ( $col === $max ) {
					$html .= '</td></tr>';
					$col = 0;
				} else {
					$html .= '</td>';
				}
			}

			$html .= $col < $max ? '</tr>' : '';
			$html .= '</table>';

			return $html;
		}
	}
}


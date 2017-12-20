<?php
/*
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2012-2017 Jean-Sebastien Morisset (https://surniaulula.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'These aren\'t the droids you\'re looking for...' );
}

if ( ! class_exists( 'NgfbGplForumBbpress' ) ) {

	class NgfbGplForumBbpress {

		private $p;
		private $sharing;

		public function __construct( &$plugin ) {
			$this->p =& $plugin;

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			if ( class_exists( 'bbpress' ) ) {	// is_bbpress() is not available here
				if ( ! empty( $this->p->avail['p_ext']['ssb'] ) ) {
					$classname = __CLASS__.'Sharing';
					if ( class_exists( $classname ) ) {
						$this->sharing = new $classname( $this->p );
					}
				}
			}
		}
	}
}

if ( ! class_exists( 'NgfbGplForumBbpressSharing' ) ) {

	class NgfbGplForumBbpressSharing {

		private $p;

		public function __construct( &$plugin ) {
			$this->p =& $plugin;

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			$this->p->util->add_plugin_filters( $this, array(
				'get_defaults' => 1,
			) );

			if ( is_admin() && empty( $this->p->options['plugin_hide_pro'] ) ) {
				$this->p->util->add_plugin_filters( $this, array(
					'buttons_show_on' => 2,
					'sharing_styles_tabs' => 1,
					'styles_bbp_single_rows' => 2,
					'buttons_position_rows' => 2,
				) );
			}
		}

		public function filter_get_defaults( $opts_def ) {
			$opts_def['buttons_css_bbp_single'] = '/* Save an empty style text box to reload the default example styles.
 * These styles are provided as examples only - modifications may be
 * necessary to customize the layout for your website. Social sharing
 * buttons can be aligned vertically, horizontally, floated, etc.
 */

.ngfb-bbp_single-buttons {
	clear:both;
	display:block;
	margin:10px auto;
	text-align:center;
}';
			foreach ( $this->p->cf['opt']['cm_prefix'] as $id => $opt_pre ) {
				$opts_def[$opt_pre.'_on_bbp_single'] = 0;
			}
			$opts_def['buttons_pos_bbp_single'] = 'top';

			return $opts_def;
		}

		public function filter_buttons_show_on( $show_on = array(), $opt_pre ) {
			switch ( $opt_pre ) {
				case 'pin':
					break;
				default:
					$show_on['bbp_single'] = 'bbPress Single';
					$this->p->options[$opt_pre.'_on_bbp_single:is'] = 'disabled';
					break;
			}
			return $show_on;
		}

		public function filter_sharing_styles_tabs( $tabs ) {
			$tabs['bbp_single'] = 'bbPress Single';
			$this->p->options['buttons_css_bbp_single:is'] = 'disabled';
			return $tabs;
		}

		public function filter_styles_bbp_single_rows( $table_rows, $form ) {
			$table_rows[] = '<td colspan="2" align="center">'.
				$this->p->msgs->get( 'pro-feature-msg' ).'</td>';

			$table_rows[] = '<th class="textinfo">
			<p>Social sharing buttons added at the top of bbPress Single Templates are assigned the \'ngfb-bbp_single-buttons\' class, which itself contains the \'ngfb-buttons\' class -- a common class for all buttons (see the All Buttons tab).</p>

			<p>Example:</p><pre>
.ngfb-bbp_single-buttons
    .ngfb-buttons
        .facebook-button { }</pre></th><td><textarea disabled="disabled" class="tall code">'.
			$this->p->options['buttons_css_bbp_single'].'</textarea></td>';

			return $table_rows;
		}

		public function filter_buttons_position_rows( $table_rows, $form ) {
			$table_rows[] = '<td colspan="2" align="center">'.
				$this->p->msgs->get( 'pro-feature-msg' ).'</td>';

			$table_rows['buttons_pos_bbp_single'] = $form->get_th_html( _x( 'Position in bbPress Single',
				'option label', 'nextgen-facebook' ), null, 'buttons_pos_bbp_single' ).
			'<td class="blank">'.$this->p->cf['sharing']['position'][$this->p->options['buttons_pos_bbp_single']].'</td>';

			return $table_rows;
		}
	}
}


<?php
/*
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2012-2017 Jean-Sebastien Morisset (https://surniaulula.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'These aren\'t the droids you\'re looking for...' );
}

if ( ! class_exists( 'NgfbGplSocialBuddypress' ) ) {

	class NgfbGplSocialBuddypress {

		private $p;
		private $sharing;

		public function __construct( &$plugin ) {
			$this->p =& $plugin;

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			if ( is_admin() || bp_current_component() ) {

				$this->p->util->add_plugin_filters( $this, array( 
					'get_defaults' => 1,
					'plugin_integration_rows' => 2,
					'messages_tooltip_plugin' => 2,
				), 200 );

				if ( ! empty( $this->p->avail['p_ext']['ssb'] ) ) {
					$classname = __CLASS__.'Sharing';
					if ( class_exists( $classname ) ) {
						$this->sharing = new $classname( $this->p );
					}
				}
			}
		}

		public function filter_get_defaults( $def_opts ) {

			$lca = $this->p->cf['lca'];
			$bio_const_name = strtoupper( $lca ).'_BP_MEMBER_BIOGRAPHICAL_FIELD';
			$def_opts['plugin_bp_bio_field'] = SucomUtil::get_const( $bio_const_name );

			return $def_opts;
		}

		public function filter_plugin_integration_rows( $table_rows, $form ) {

			$table_rows['plugin_bp_bio_field'] = $form->get_th_html( _x( 'BuddyPress Member Bio Field Name',
				'option label', 'nextgen-facebook' ), '', 'plugin_bp_bio_field' ).
			'<td class="blank">'.$this->p->options['plugin_bp_bio_field'].'</td>';

			return $table_rows;
		}

		public function filter_messages_tooltip_plugin( $text, $idx ) {
			if ( strpos( $idx, 'tooltip-plugin_bp_' ) !== 0 ) {
				return $text;
			}
			switch ( $idx ) {
				case 'tooltip-plugin_bp_bio_field':
					$text = __( 'The BuddyPress member profile page does not include the <em>Biographical Info</em> text from the WordPress user profile. If you\'ve created an additional BuddyPress Profile Field for members to enter their profile description, enter the field name here (example: Biographical Info, About Me, etc.).', 'nextgen-facebook' );
					break;
			}
			return $text;
		}
	}
}

if ( ! class_exists( 'NgfbGplSocialBuddypressSharing' ) ) {

	class NgfbGplSocialBuddypressSharing {

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
					'styles_bp_activity_rows' => 2,
				) );
			}
		}

		public function filter_get_defaults( $opts_def ) {
			$opts_def['buttons_css_bp_activity'] = '/* Save an empty style text box to reload the default example styles.
 * These styles are provided as examples only - modifications may be
 * necessary to customize the layout for your website. Social sharing
 * buttons can be aligned vertically, horizontally, floated, etc.
 */

.ngfb-bp_activity-buttons {
	display:block;
	margin:10px auto;
	text-align:center;
}';
			foreach ( $this->p->cf['opt']['cm_prefix'] as $id => $opt_pre ) {
				$opts_def[$opt_pre.'_on_bp_activity'] = 0;
			}
			return $opts_def;
		}

		public function filter_buttons_show_on( $show_on = array(), $opt_pre = '' ) {
			switch ( $opt_pre ) {
				case 'pin':
					break;
				default:
					$show_on['bp_activity'] = 'BP Activity';
					$this->p->options[$opt_pre.'_on_bp_activity:is'] = 'disabled';
					break;
			}
			return $show_on;
		}

		public function filter_sharing_styles_tabs( $tabs ) {
			$tabs['bp_activity'] = 'BP Activity';
			$this->p->options['buttons_css_bp_activity:is'] = 'disabled';
			return $tabs;
		}

		public function filter_styles_bp_activity_rows( $rows, $form ) {
			$rows[] = '<td colspan="2" align="center">'.
				$this->p->msgs->get( 'pro-feature-msg' ).'</td>';

			$rows[] = '<th class="textinfo">
			<p>Social sharing buttons added to BuddyPress Activities are assigned the \'ngfb-bp_activity-buttons\' class, which itself contains the \'ngfb-buttons\' class -- a common class for all buttons (see the All Buttons tab).</p>

			<p>Example:</p><pre>
.ngfb-bp_activity-buttons
    .ngfb-buttons
        .facebook-button { }</pre></th><td><textarea disabled="disabled" class="tall code">'.
			$this->p->options['buttons_css_bp_activity'].'</textarea></td>';

			return $rows;
		}
	}
}


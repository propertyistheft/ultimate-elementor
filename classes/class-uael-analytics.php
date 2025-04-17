<?php
/**
 * UAEL Analytics.
 *
 * @package UAEL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'UAEL_Analytics' ) ) {
	/**
	 * Class UAEL_Analytics
	 *
	 * Handles analytics-related functionality for the Ultimate Addons for Elementor plugin.
	 *
	 * @since 1.39.3
	 */
	class UAEL_Analytics {

		/**
		 * UAEL Analytics constructor.
		 *
		 * Initializing UAEL Analytics.
		 *
		 * @since 1.39.3
		 * @access public
		 */
		public function __construct() {

			// BSF Analytics Tracker.
			if ( ! class_exists( 'BSF_Analytics_Loader' ) ) {
				require_once UAEL_DIR . 'admin/bsf-analytics/class-bsf-analytics-loader.php';
			}

			$bsf_analytics = BSF_Analytics_Loader::get_instance();

			$bsf_analytics->set_entity(
				array(
					'bsf' => array(
						'product_name'        => 'Ultimate Addons for Elementor Pro',
						'path'                => UAEL_DIR . 'admin/bsf-analytics',
						'author'              => 'Brainstorm Force',
						'time_to_display'     => '+24 hours',
						'deactivation_survey' => array(
							array(
								'id'                => 'deactivation-survey-ultimate-elementor', // 'deactivation-survey-<your-plugin-slug>'
								'popup_logo'        => UAEL_URL . 'assets/images/settings/logo.svg',
								'plugin_slug'       => 'ultimate-elementor', // <your-plugin-slug>
								'plugin_version'    => UAEL_VER,
								'popup_title'       => 'Quick Feedback',
								'support_url'       => 'https://ultimateelementor.com/contact/',
								'popup_description' => 'If you have a moment, please share why you are deactivating Ultimate Addons for Elementor Pro:',
								'show_on_screens'   => array( 'plugins' ),
							),
						),
					),
				)
			);
			
			add_filter( 'bsf_core_stats', array( $this, 'add_uae_analytics_data' ) );
		}

		/**
		 * Callback function to add specific analytics data.
		 *
		 * @param array $stats_data existing stats_data.
		 * @since 1.39.3
		 * @return array
		 */
		public function add_uae_analytics_data( $stats_data ) {
			$stats_data['plugin_data']['uae'] = array(
				'free_version'          => ( defined( 'HFE_VER' ) ? HFE_VER : '' ),
				'pro_version'           => UAEL_VER,
				'site_language'         => get_locale(),
				'elementor_version'     => ( defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '' ),
				'elementor_pro_version' => ( defined( 'ELEMENTOR_PRO_VERSION' ) ? ELEMENTOR_PRO_VERSION : '' ),
				'onboarding_triggered'  => ( 'yes' === get_option( 'uaepro_onboarding_triggered' ) ) ? 'yes' : 'no',
			);

			$fetch_elementor_data = $this->uael_get_widgets_usage();
			foreach ( $fetch_elementor_data as $key => $value ) {
				$stats_data['plugin_data']['uae']['numeric_values'][ $key ] = $value;
			}
			return $stats_data;
		}

		/**
		 * Fetch Elementor data.
		 */
		private function uael_get_widgets_usage() {
				$get_widgets = get_option( 'uaepro_widgets_usage_data_option', array() );
				return $get_widgets;
		}
	}
}
new UAEL_Analytics();

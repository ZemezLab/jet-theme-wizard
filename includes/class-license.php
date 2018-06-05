<?php
/**
 * Class description
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! class_exists( 'Jet_Theme_Wizard_License' ) ) {

	/**
	 * Define Jet_Theme_Wizard_License class
	 */
	class Jet_Theme_Wizard_License {

		/**
		 * A reference to an instance of this class.
		 *
		 * @since 1.0.0
		 * @var   object
		 */
		private static $instance = null;

		/**
		 * Config properties
		 */
		public $license_option = 'jet_theme_core_license';
		public $api            = 'https://account.crocoblock.com/';
		public $item_id        = 9;
		public $theme_link     = 'https://account.crocoblock.com/free-download/kava.zip';
		public $theme_slug     = 'kava';

		/**
		 * Error message holder
		 */
		private $error = null;

		/**
		 * Retuirn license
		 *
		 * @return [type] [description]
		 */
		public function get_license() {
			return get_option( $this->license_option );
		}

		/**
		 * Check if license is already active
		 *
		 * @return boolean
		 */
		public function is_active() {

			$license = $this->get_license();

			if ( ! $license ) {
				return false;
			}

			$response = $this->license_request( 'check_license', $license );
			$result   = wp_remote_retrieve_body( $response );
			$result   = json_decode( $result, true );

			if ( ! isset( $result['success'] ) ) {
				return false;
			}

			if ( true === $result['success'] && 'valid' === $result['license'] ) {
				return true;
			} else {
				return false;
			}

		}

		/**
		 * Perform a remote request with passed action for passed license key
		 *
		 * @param  string $action  EDD action to perform (activate_license, check_license etc)
		 * @param  string $license License key
		 * @return WP_Error|array
		 */
		public function license_request( $action, $license ) {

			$api_url = $this->api;
			$item_id = $this->item_id;

			$url = add_query_arg(
				array(
					'edd_action' => $action,
					'item_id'    => $item_id,
					'license'    => $license,
					'url'        => urlencode( home_url( '/' ) ),
				),
				$api_url
			);

			$args = array(
				'timeout'   => 60,
				'sslverify' => false
			);

			return wp_remote_get( $url, $args );
		}

		/**
		 * Activate license.
		 *
		 * @return void
		 */
		public function activate_license( $license = null ) {

			if ( ! current_user_can( 'manage_options' ) ) {
				return $this->set_error( __( 'Sorry, you not allowed to activate license', 'jet-plugins-wizard' ) );
			}

			if ( ! $license ) {
				return $this->set_error( __( 'Please provide valid license key', 'jet-plugins-wizard' ) );
			}

			$response = $this->license_request( 'activate_license', $license );

			$result   = wp_remote_retrieve_body( $response );
			$result   = json_decode( $result, true );

			if ( ! isset( $result['success'] ) ) {
				return $this->set_error( __( 'Internal error, please try again later.', 'jet-plugins-wizard' ) );
			}

			if ( true === $result['success'] ) {

				if ( 'valid' === $result['license'] ) {

					update_option( $this->license_option, $license, 'no' );

					return array(
						'id'   => $this->theme_slug,
						'link' => $this->theme_link,
					);

				} else {
					return $this->set_error( $this->get_error_by_code( 'default' ) );
				}

			} else {

				if ( ! empty( $result['error'] ) ) {
					return $this->set_error( $this->get_error_by_code( $result['error'] ) );
				} else {
					return $this->set_error( $this->get_error_by_code( 'default' ) );
				}

			}

		}

		/**
		 * Store error
		 *
		 * @param [type] $error [description]
		 */
		public function set_error( $error ) {
			$this->error = $error;
		}

		/**
		 * Return error message.
		 *
		 * @return string
		 */
		public function get_error() {
			return $this->error;
		}

		/**
		 * Retrirve error message by error code
		 *
		 * @return string
		 */
		public function get_error_by_code( $code ) {

			$messages = array(
				'missing' => __( 'Your license is missing. Please check your key again.', 'jet-theme-core' ),
				'no_activations_left' => __( '<strong>You have no more activations left.</strong> Please upgrade to a more advanced license (you\'ll only need to cover the difference).', 'jet-theme-core' ),
				'expired' => __( '<strong>Your License Has Expired.</strong> Renew your license today to keep getting feature updates, premium support and unlimited access to the template library.', 'jet-theme-core' ),
				'revoked' => __( '<strong>Your license key has been cancelled</strong> (most likely due to a refund request). Please consider acquiring a new license.', 'jet-theme-core' ),
				'disabled' => __( '<strong>Your license key has been cancelled</strong> (most likely due to a refund request). Please consider acquiring a new license.', 'jet-theme-core' ),
				'invalid' => __( '<strong>Your license key doesn\'t match your current domain</strong>. This is most likely due to a change in the domain URL of your site (including HTTPS/SSL migration). Please deactivate the license and then reactivate it again.', 'jet-theme-core' ),
				'site_inactive' => __( '<strong>Your license key doesn\'t match your current domain</strong>. This is most likely due to a change in the domain URL. Please deactivate the license and then reactivate it again.', 'jet-theme-core' ),
				'inactive' => __( '<strong>Your license key doesn\'t match your current domain</strong>. This is most likely due to a change in the domain URL of your site (including HTTPS/SSL migration). Please deactivate the license and then reactivate it again.', 'jet-theme-core' ),
			);

			$default = __( 'An error occurred. Please check your internet connection and try again. If the problem persists, contact our support.', 'jet-theme-core' );

			return isset( $messages[ $code ] ) ? $messages[ $code ] : $default;

		}

		/**
		 * Returns the instance.
		 *
		 * @since  1.0.0
		 * @return object
		 */
		public static function get_instance() {

			// If the single instance hasn't been set, set it now.
			if ( null == self::$instance ) {
				self::$instance = new self;
			}
			return self::$instance;
		}
	}

}

/**
 * Returns instance of Jet_Theme_Wizard_License
 *
 * @return object
 */
function jet_theme_wizard_license() {
	return Jet_Theme_Wizard_License::get_instance( $template_id, $order_id );
}

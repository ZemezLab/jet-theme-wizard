<?php
/**
 * Theme installation API handlers
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! class_exists( 'Jet_Theme_Wizard_Install_API' ) ) {

	/**
	 * Define Jet_Theme_Wizard_Install_API class
	 */
	class Jet_Theme_Wizard_Install_API {

		/**
		 * Installed theme URL.
		 *
		 * @var string
		 */
		private $url;

		/**
		 * A reference to an instance of this class.
		 *
		 * @since 1.0.0
		 * @var   object
		 */
		private static $instance = null;

		/**
		 * Installation result
		 *
		 * @var mixed
		 */
		private $result;

		/**
		 * Adjusted theme/plugin directory name
		 *
		 * @var string
		 */
		private $adjusted_dir;

		/**
		 * Constructor for the class
		 */
		function __construct( $url = null ) {
			$this->url  = $url;
		}

		/**
		 * Perform theme installation
		 *
		 * @return array
		 */
		public function do_theme_install() {

			include_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );

			add_filter( 'upgrader_source_selection', array( $this, 'adjust_theme_dir' ), 1, 3 );

			$theme_url = $this->url;
			$skin      = new WP_Ajax_Upgrader_Skin();
			$upgrader  = new Theme_Upgrader( $skin );
			$result    = $upgrader->install( $theme_url );

			remove_filter( 'upgrader_source_selection', array( $this, 'adjust_theme_dir' ), 1 );

			$data    = array();
			$success = true;
			$message = esc_html__( 'The theme is succesfully installed. Activating...', 'jet-theme-wizard' );

			if ( is_wp_error( $result ) ) {

				$message = $result->get_error_message();
				$success = false;

			} elseif ( is_wp_error( $skin->result ) ) {

				if ( ! isset( $skin->result->errors['folder_exists'] ) ) {
					$message = $skin->result->get_error_message();
					$success = false;
				} else {
					$message = esc_html__( 'The theme has been already installed. Activating...', 'jet-theme-wizard' );
				}

			} elseif ( $skin->get_errors()->get_error_code() ) {

				$message = $skin->get_error_messages();
				$success = false;

			} elseif ( is_null( $result ) ) {

				global $wp_filesystem;
				$message = esc_html__( 'Unable to connect to the filesystem. Please confirm your credentials.', 'jet-theme-wizard' );

				// Pass through the error from WP_Filesystem if one was raised.
				if ( $wp_filesystem instanceof WP_Filesystem_Base && is_wp_error( $wp_filesystem->errors ) && $wp_filesystem->errors->get_error_code() ) {
					$message = esc_html( $wp_filesystem->errors->get_error_message() );
				}

				$success  = false;

			}

			return array(
				'success' => $success,
				'message' => $message,
			);
		}

		/**
		 * Perform plugin installation.
		 *
		 * @return array
		 */
		public function do_plugin_install( $slug = null ) {

			if ( ! $slug ) {
				return;
			}

			include_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );

			add_filter( 'upgrader_source_selection', array( $this, 'adjust_plugin_dir' ), 1, 3 );

			$plugin_url = $this->url;

			$skin     = new WP_Ajax_Upgrader_Skin( array( 'plugin' => $slug ) );
			$upgrader = new Plugin_Upgrader( $skin );
			$result   = $upgrader->install( $plugin_url );

			$this->result = $result;

			remove_filter( 'upgrader_source_selection', array( $this, 'adjust_plugin_dir' ), 1 );

			$data    = array();
			$success = true;
			$message = esc_html__( 'Plugins wizard installed and activated. Redirecting...', 'jet-theme-wizard' );

			if ( is_wp_error( $result ) ) {

				$message = $result->get_error_message();
				$success = false;

			} elseif ( is_wp_error( $skin->result ) ) {

				$this->result = $skin->result;

				$message = $skin->result->get_error_message();
				$success = false;

			} elseif ( $skin->get_errors()->get_error_code() ) {

				$message = $skin->get_error_messages();
				$success = false;

			} elseif ( is_null( $result ) ) {

				global $wp_filesystem;
				$message = __( 'Unable to connect to the filesystem. Please confirm your credentials.' );

				// Pass through the error from WP_Filesystem if one was raised.
				if ( $wp_filesystem instanceof WP_Filesystem_Base && is_wp_error( $wp_filesystem->errors ) && $wp_filesystem->errors->get_error_code() ) {
					$message= esc_html( $wp_filesystem->errors->get_error_message() );
				}

				$success = false;
			}

			return array(
				'success' => $success,
				'message' => $message,
			);
		}

		/**
		 * Get information about installed object.
		 *
		 * @return array
		 */
		public function get_info() {

			$info = array(
				'dir'  => false,
				'file' => false,
			);

			if ( true === $this->result ) {

				if ( ! $this->adjusted_dir ) {
					return $info;
				}

				$info['dir'] = basename( $this->adjusted_dir );
			}

			if ( is_wp_error( $this->result ) && 'folder_exists' === $this->result->get_error_code() ) {
				$path = $this->result->get_error_data();
				$info['dir'] = basename( $path );
			}

			if ( empty( $info['dir'] ) ) {
				return $info;
			}

			/** Get the installed plugin file or return false if it isn't set */
			$plugin = get_plugins( '/' . $info['dir'] );

			if ( ! empty( $plugin ) ) {
				$pluginfiles = array_keys( $plugin );
				$info['file'] = $info['dir'] . '/' . $pluginfiles[0];
			}

			return $info;
		}

		/**
		 * Adjust the plugin directory name if necessary.
		 *
		 * The final destination directory of a plugin is based on the subdirectory name found in the
		 * (un)zipped source. In some cases - most notably GitHub repository plugin downloads -, this
		 * subdirectory name is not the same as the expected slug and the plugin will not be recognized
		 * as installed. This is fixed by adjusting the temporary unzipped source subdirectory name to
		 * the expected plugin slug.
		 *
		 * @since  1.0.0
		 * @param  string       $source        Path to upgrade/zip-file-name.tmp/subdirectory/.
		 * @param  string       $remote_source Path to upgrade/zip-file-name.tmp.
		 * @param  \WP_Upgrader $upgrader      Instance of the upgrader which installs the plugin.
		 * @return string $source
		 */
		public function adjust_plugin_dir( $source, $remote_source, $upgrader ) {

			global $wp_filesystem;

			// Ensure that is Wizard installation request
			if ( empty( $_REQUEST['action'] ) && 'jet_theme_wizard_install_plugins_wizard' !== $_REQUEST['action'] ) {
				return $source;
			}

			// Check for single file plugins.
			$source_files = array_keys( $wp_filesystem->dirlist( $remote_source ) );
			if ( 1 === count( $source_files ) && false === $wp_filesystem->is_dir( $source ) ) {
				return $source;
			}

			$desired_slug = isset( $upgrader->skin->options['plugin'] ) ? $upgrader->skin->options['plugin'] : false;

			if ( ! $desired_slug ) {
				return $source;
			}

			$subdir_name = untrailingslashit( str_replace( trailingslashit( $remote_source ), '', $source ) );

			if ( ! empty( $subdir_name ) && $subdir_name !== $desired_slug ) {

				$from_path = untrailingslashit( $source );
				$to_path   = trailingslashit( $remote_source ) . $desired_slug;

				if ( true === $wp_filesystem->move( $from_path, $to_path ) ) {

					$this->adjusted_dir = $to_path;

					return trailingslashit( $to_path );
				} else {
					return new WP_Error(
						'rename_failed',
						esc_html__( 'The remote plugin package does not contain a folder with the desired slug and renaming did not work.', 'tm-wizard' ) . ' ' . esc_html__( 'Please contact the plugin provider and ask them to package their plugin according to the WordPress guidelines.', 'tm-wizard' ),
						array( 'found' => $subdir_name, 'expected' => $desired_slug )
					);
				}

			} elseif ( empty( $subdir_name ) ) {
				return new WP_Error(
					'packaged_wrong',
					esc_html__( 'The remote plugin package consists of more than one file, but the files are not packaged in a folder.', 'tm-wizard' ) . ' ' . esc_html__( 'Please contact the plugin provider and ask them to package their plugin according to the WordPress guidelines.', 'tm-wizard' ),
					array( 'found' => $subdir_name, 'expected' => $desired_slug )
				);
			}

			return $source;
		}

		/**
		 * Adjust the theme directory name.
		 *
		 * @since  1.0.0
		 * @param  string       $source        Path to upgrade/zip-file-name.tmp/subdirectory/.
		 * @param  string       $remote_source Path to upgrade/zip-file-name.tmp.
		 * @param  \WP_Upgrader $upgrader      Instance of the upgrader which installs the theme.
		 * @return string $source
		 */
		public function adjust_theme_dir( $source, $remote_source, $upgrader ) {

			global $wp_filesystem;

			if ( ! is_object( $wp_filesystem ) ) {
				return $source;
			}

			// Ensure that is Wizard installation request
			if ( empty( $_REQUEST['action'] ) && 'jet_theme_wizard_install_parent' !== $_REQUEST['action'] ) {
				return $source;
			}

			// Check for single file plugins.
			$source_files = array_keys( $wp_filesystem->dirlist( $remote_source ) );
			if ( 1 === count( $source_files ) && false === $wp_filesystem->is_dir( $source ) ) {
				return $source;
			}

			$css_key  = array_search( 'style.css', $source_files );

			if ( false === $css_key ) {
				return $source;
			}

			$css_path = $remote_source . '/' . $source_files[ $css_key ];

			if ( ! file_exists( $css_path ) ) {
				return $source;
			}

			$theme_data = get_file_data( $css_path, array(
				'TextDomain' => 'Text Domain',
				'ThemeName'  => 'Theme Name',
			), 'theme' );

			if ( ! $theme_data || ! isset( $theme_data['TextDomain'] ) ) {
				return $source;
			}

			$theme_name = $theme_data['TextDomain'];
			$from_path  = untrailingslashit( $source );
			$to_path    = untrailingslashit( str_replace( basename( $remote_source ), $theme_name, $remote_source ) );

			if ( true === $wp_filesystem->move( $from_path, $to_path ) ) {

				/**
				 * Fires after reanming before returns result.
				 */
				do_action( 'jet-theme-wizard/source-rename-done', $theme_data );

				return trailingslashit( $to_path );

			} else {

				return new WP_Error(
					'rename_failed',
					esc_html__( 'The remote plugin package does not contain a folder with the desired slug and renaming did not work.', 'jet-theme-wizard' ),
					array( 'found' => $subdir_name, 'expected' => $theme_name )
				);

			}

			return $source;
		}

		/**
		 * Returns the instance.
		 *
		 * @since  1.0.0
		 * @return object
		 */
		public static function get_instance( $url = null ) {
			return new self( $url );
		}
	}

}

/**
 * Returns instance of Jet_Theme_Wizard_Install_API
 *
 * @return object
 */
function jet_theme_install_api( $url = null ) {
	return Jet_Theme_Wizard_Install_API::get_instance( $url );
}

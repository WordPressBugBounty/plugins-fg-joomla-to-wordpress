<?php
/**
 * FTP module
 *
 * @link       https://www.fredericgilles.net/fg-joomla-to-wordpress/
 * @since      2.7.0
 *
 * @package    FG_Joomla_to_WordPress_Premium
 * @subpackage FG_Joomla_to_WordPress_Premium/admin
 */

if ( !class_exists('FG_Joomla_to_WordPress_FTP', false) ) {

	/**
	 * FTP class
	 *
	 * @package    FG_Joomla_to_WordPress_Premium
	 * @subpackage FG_Joomla_to_WordPress_Premium/admin
	 * @author     Frédéric GILLES
	 */
	class FG_Joomla_to_WordPress_FTP extends FG_Joomla_to_WordPress_Download_FTP {
		
		/**
		 * Initialize the class and set its properties.
		 *
		 * @since    2.7.0
		 * @param    object    $plugin       Admin plugin
		 */
		public function __construct( $plugin ) {

			parent::__construct($plugin);
			
			// Default values
			$this->plugin->ftp_options = array(
				'hostname'			=> '',
				'port'				=> 21,
				'username'			=> '',
				'password'			=> '',
				'connection_type'	=> 'ftp',
				'basedir'			=> '',
			);
			$options = get_option('fgj2wp_ftp_options');
			if ( is_array($options) ) {
				$this->plugin->ftp_options = array_merge($this->plugin->ftp_options, $options);
			}
		}
		
		/**
		 * Get the WP options name
		 * 
		 * @since 3.57.0
		 * 
		 * @param array $option_names Option names
		 * @return array Option names
		 */
		public function get_option_names($option_names) {
			$option_names[] = 'fgj2wp_ftp_options';
			return $option_names;
		}

		/**
		 * Display the FTP settings
		 * 
		 */
		public function display_ftp_settings() {
			$data = array();
			$data['ftp_host'] = $this->plugin->ftp_options['hostname'];
			$data['ftp_port'] = $this->plugin->ftp_options['port'];
			$data['ftp_login'] = $this->plugin->ftp_options['username'];
			$data['ftp_password'] = $this->plugin->ftp_options['password'];
			$data['ftp_connection_type'] = $this->plugin->ftp_options['connection_type'];
			$data['ftp_dir'] = $this->plugin->ftp_options['basedir'];
			require('partials/ftp-settings.php');
		}

		/**
		 * Save the FTP settings
		 * 
		 */
		public function save_ftp_settings() {
			$this->plugin->ftp_options = array_merge($this->plugin->ftp_options, $this->validate_form_info());
			update_option('fgj2wp_ftp_options', $this->plugin->ftp_options);
		}
		
		/**
		 * Validate POST info
		 *
		 * @return array Form parameters
		 */
		private function validate_form_info() {
			$ftp_host = filter_input(INPUT_POST, 'ftp_host', FILTER_SANITIZE_SPECIAL_CHARS);
			$ftp_port = filter_input(INPUT_POST, 'ftp_port', FILTER_SANITIZE_SPECIAL_CHARS);
			$ftp_login = filter_input(INPUT_POST, 'ftp_login', FILTER_SANITIZE_SPECIAL_CHARS);
			$ftp_password = filter_input(INPUT_POST, 'ftp_password', FILTER_DEFAULT);
			$ftp_connection_type = filter_input(INPUT_POST, 'ftp_connection_type', FILTER_SANITIZE_SPECIAL_CHARS);
			$ftp_dir = filter_input(INPUT_POST, 'ftp_dir', FILTER_SANITIZE_SPECIAL_CHARS);
			return array(
				'hostname'			=> isset($ftp_host)? $ftp_host : '',
				'port'				=> isset($ftp_port)? $ftp_port : '',
				'username'			=> isset($ftp_login)? $ftp_login : '',
				'password'			=> isset($ftp_password)? $ftp_password : '',
				'connection_type'	=> isset($ftp_connection_type)? $ftp_connection_type : '',
				'basedir'			=> isset($ftp_dir)? $ftp_dir : '',
			);
		}
		
		/**
		 * Test FTP connection
		 *
		 */
		public function test_ftp_connection($action) {
			if ( $action == 'test_ftp' ) {

				// Save database options
				$this->plugin->save_plugin_options();

				// Test the database connection
				if ( check_admin_referer( 'parameters_form', 'fgj2wp_nonce' ) ) { // Security check
					if ( $this->test_connection() ) {
						$this->plugin->display_admin_notice(__('FTP connection successful', 'fg-joomla-to-wordpress'));
						$result = array('status' => 'OK', 'message' => __('FTP connection successful', 'fg-joomla-to-wordpress'));
					} else {
						$result = array('status' => 'Error', 'message' => __('FTP connection failed', 'fg-joomla-to-wordpress'));
					}
					echo wp_json_encode($result);
				}
			}
		}

		/* ====================================================================
		 * The following functions are kept for compatibility with add-ons
		 * 
		 * TODO Remove them once the add-ons using this class have been updated
		 * 
		 * ====================================================================
		 */
		
		/**
		 * Change the current FTP directory
		 *
		 * @param string $directory Directory
		 * @return bool
		 */
		public function chdir($directory) {
			$result = false;
			if ( $this->is_connected() ) {
				$full_directory = trailingslashit($this->plugin->ftp_options['basedir']) . $directory;
				$result = $this->ftp->chdir($full_directory);
			}
			return $result;
		}

		/**
		 * Get a file
		 *
		 * @param string $source Original filename
		 * @param string $destination Destination filename
		 * @return bool File downloaded or not
		 */
		public function get($source, $destination) {
			$result = false;
			
			if ( !$this->plugin->plugin_options['force_media_import'] && file_exists($destination) && (filesize($destination) > 0) ) {
				// Don't download the file if already downloaded
				return true;
			}
			if ( $this->is_connected() ) {
				$file_content = $this->ftp->get_contents($source);
				if ( $file_content ) {
					$result = (file_put_contents($destination, $file_content) !== false);
				}
			}
			return $result;
		}
	}
}

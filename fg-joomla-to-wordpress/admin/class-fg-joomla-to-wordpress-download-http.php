<?php
/**
 * Download HTTP module
 *
 * @link       https://www.fredericgilles.net/fg-joomla-to-wordpress/
 * @since      3.72.0
 *
 * @package    FG_Joomla_to_WordPress_Premium
 * @subpackage FG_Joomla_to_WordPress_Premium/admin
 */

if ( !class_exists('FG_Joomla_to_WordPress_Download_HTTP', false) ) {

	/**
	 * Download HTTP class
	 *
	 * @package    FG_Joomla_to_WordPress_Premium
	 * @subpackage FG_Joomla_to_WordPress_Premium/admin
	 * @author     Frédéric GILLES
	 */
	class FG_Joomla_to_WordPress_Download_HTTP {
		
		private $plugin;
		const USER_AGENT = 'Mozilla/5.0 AppleWebKit (KHTML, like Gecko) Chrome/ Safari/'; // the default "WordPress..." user agent is rejected with some NGINX config
		
		/**
		 * Initialize the class and set its properties.
		 *
		 * @param object $plugin Admin plugin
		 */
		public function __construct($plugin) {

			$this->plugin = $plugin;
		}
		
		/**
		 * Test connection
		 *
		 * @return bool Connection successful or not
		 */
		public function test_connection() {
			$response = wp_remote_get($this->plugin->plugin_options['url'], array(
				'timeout'		=> $this->plugin->plugin_options['timeout'],
				'sslverify'		=> false,
				'user-agent'	=> self::USER_AGENT, 
			)); // Uses WordPress HTTP API
			
			return !is_wp_error($response) && ($response['response']['code'] == 200);
		}

		/**
		 * List the files in a directory
		 *
		 * @param string $directory Directory
		 * @return array List of files
		 */
		public function list_directory($directory) {
			// Not implemented in HTTP
			return array();
		}

		/**
		 * Is the path a directory?
		 * 
		 * @since 3.74.0
		 * 
		 * @param string $path Path
		 * @return boolean
		 */
		public function is_dir($path) {
			// Not implemented in HTTP
			return false;
		}

		/**
		 * Get the content of a file
		 *
		 * @param string $source Original filename
		 * @return string File content
		 */
		public function get_content($source) {
			$content = false;
			$source = str_replace(" ", "%20", $source); // for filenames with spaces
			$source = str_replace("&amp;", "&", $source); // for filenames with &

			$response = wp_remote_get($source, array(
				'timeout'		=> $this->plugin->plugin_options['timeout'],
				'sslverify'		=> false,
				'user-agent'	=> self::USER_AGENT,
			)); // Uses WordPress HTTP API
			
			if ( is_wp_error($response) ) {
				trigger_error($response->get_error_message(), E_USER_WARNING);
			} elseif ( $response['response']['code'] != 200 ) {
				trigger_error($response['response']['message'], E_USER_WARNING);
			} else {
				$content_type = wp_remote_retrieve_header($response, 'content-type');
				if ( is_array($content_type) ) { // multiple headers with the same name
					$content_type = implode(' ', $content_type);
				}
				if ( preg_match('/^text/', $content_type) ) {
					// Not a media
					trigger_error('Not a media', E_USER_WARNING);
				} else {
					$content = wp_remote_retrieve_body($response);
				}
			}
			return $content;
		}
		
	}
}

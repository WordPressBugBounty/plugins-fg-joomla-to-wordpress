<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://wordpress.org/plugins/fg-joomla-to-wordpress/
 * @since      2.0.0
 *
 * @package    FG_Joomla_to_WordPress
 * @subpackage FG_Joomla_to_WordPress/admin
 */

if ( !class_exists('FG_Joomla_to_WordPress_Admin', false) ) {

	/**
	 * The admin-specific functionality of the plugin.
	 *
	 * @package    FG_Joomla_to_WordPress
	 * @subpackage FG_Joomla_to_WordPress/admin
	 * @author     Frédéric GILLES
	 */
	class FG_Joomla_to_WordPress_Admin extends WP_Importer {

		/**
		 * The ID of this plugin.
		 *
		 * @since    2.0.0
		 * @access   private
		 * @var      string    $plugin_name    The ID of this plugin.
		 */
		private $plugin_name;

		/**
		 * The version of this plugin.
		 *
		 * @since    2.0.0
		 * @access   private
		 * @var      string    $version    The current version of this plugin.
		 */
		private $version;					// Plugin version
		private $importer = 'fgj2wp';		// URL parameter

		public $joomla_version;
		public $plugin_options;				// Plug-in options
		public $download_manager;			// Download Manager
		public $progressbar;
		public $imported_media = array();
		public $imported_categories = array();
		public $skipped_categories = array();
		public $chunks_size = 10;
		public $posts_count = 0;			// Number of imported posts
		public $media_count = 0;			// Number of imported medias
		public $tags_count = 0;				// Number of imported tags
		public $links_count = 0;			// Number of links modified

		protected $post_type = 'post';		// post or page
		protected $faq_url;					// URL of the FAQ page
		protected $notices = array();		// Error or success messages
		
		private $log_file;
		private $log_file_url;
		private $test_antiduplicate = false;
		private $imported_tags = array();

		/**
		 * Initialize the class and set its properties.
		 *
		 * @since    2.0.0
		 * @param    string    $plugin_name       The name of this plugin.
		 * @param    string    $version           The version of this plugin.
		 */
		public function __construct( $plugin_name, $version ) {

			$this->plugin_name = $plugin_name;
			$this->version = $version;
			$this->faq_url = 'https://wordpress.org/plugins/fg-joomla-to-wordpress/faq/';
			$upload_dir = wp_upload_dir();
			$log_filename = $this->get_log_filename();
			$this->log_file = $upload_dir['basedir'] . '/' . $log_filename;
			$this->rename_old_log_file($upload_dir);
			$this->log_file_url = $upload_dir['baseurl'] . '/' . $log_filename;
			// Replace the protocol if the WordPress address is wrong in the WordPress General settings
			if ( is_ssl() ) {
				$this->log_file_url = preg_replace('/^https?/', 'https', $this->log_file_url);
			}

			// Progress bar
			$this->progressbar = new FG_Joomla_to_WordPress_ProgressBar($this);

		}
		
		/**
		 * Get the log filename
		 * 
		 * @since 4.21.0
		 * 
		 * @return string Log filename
		 */
		private function get_log_filename() {
			$option_key = 'fgj2wp_log_filename';
			$log_filename = get_option($option_key, '');
			if ( empty($log_filename) ) {
				$random_string = substr(md5(wp_rand()), 0, 8);
				$log_filename = 'fgj2wp-' . $random_string . '.logs';
				add_option($option_key, $log_filename);
			}
			return $log_filename;
		}

		/**
		 * Rename the old log file
		 * 
		 * @since 4.21.0
		 * 
		 * @param string $upload_dir WP upload directory
		 */
		private function rename_old_log_file($upload_dir) {
			$old_log_filename = $upload_dir['basedir'] . '/' . $this->plugin_name . '.logs';
			if ( file_exists($old_log_filename) ) {
				rename($old_log_filename, $this->log_file);
			}
		}
		
		/**
		 * The name of the plugin used to uniquely identify it within the context of
		 * WordPress and to define internationalization functionality.
		 *
		 * @since     2.0.0
		 * @return    string    The name of the plugin.
		 */
		public function get_plugin_name() {
			return $this->plugin_name;
		}

		/**
		 * Register the stylesheets for the admin area.
		 *
		 * @since    2.0.0
		 */
		public function enqueue_styles() {

			wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/fg-joomla-to-wordpress-admin.css', array(), $this->version, 'all' );

		}

		/**
		 * Register the JavaScript for the admin area.
		 *
		 * @since    2.0.0
		 */
		public function enqueue_scripts() {

			wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/fg-joomla-to-wordpress-admin.js', array( 'jquery', 'jquery-ui-progressbar' ), $this->version, false );
			wp_localize_script( $this->plugin_name, 'objectL10n', array(
				'delete_imported_data_confirmation_message' => __( 'All imported data will be deleted from WordPress.', 'fg-joomla-to-wordpress' ),
				'delete_all_confirmation_message' => __( 'All content will be deleted from WordPress.', 'fg-joomla-to-wordpress' ),
				'delete_no_answer_message' => __( 'Please select a remove option.', 'fg-joomla-to-wordpress' ),
				'import_completed' => __( 'IMPORT COMPLETED', 'fg-joomla-to-wordpress' ),
				'content_removed_from_wordpress' => __( 'Content removed from WordPress', 'fg-joomla-to-wordpress' ),
				'settings_saved' => __( 'Settings saved', 'fg-joomla-to-wordpress' ),
				'importing' => __( 'Importing…', 'fg-joomla-to-wordpress' ),
				'import_stopped_by_user' => __( 'IMPORT STOPPED BY USER', 'fg-joomla-to-wordpress' ),
				'internal_links_modified' => __( 'Internal links modified', 'fg-joomla-to-wordpress' ),
			) );
			wp_localize_script( $this->plugin_name, 'objectPlugin', array(
				'log_file_url' => $this->log_file_url,
				'progress_url' => $this->progressbar->get_url(),
				'enable_ftp' => defined('FGJ2WPP_ENABLE_FTP'),
				'legacy_enable_ftp' => defined('FGJ2WPP_USE_FTP'), // TODO To remove once the add-ons using it have been updated
			));

		}

		/**
		 * Initialize the plugin
		 */
		public function init() {
			register_importer($this->importer, __('Joomla (FG)', 'fg-joomla-to-wordpress'), __('Import categories, articles and medias (images, attachments) from a Joomla database into WordPress.', 'fg-joomla-to-wordpress'), array($this, 'importer'));
		}

		/**
		 * Display the stored notices
		 */
		public function display_notices() {
			foreach ( $this->notices as $notice ) {
				echo '<div class="' . $notice['level'] . '"><p>[' . $this->plugin_name . '] ' . $notice['message'] . "</p></div>\n";
			}
		}
		
		/**
		 * Write a message in the log file
		 * 
		 * @param string $message
		 */
		public function log($message) {
			file_put_contents($this->log_file, "$message\n", FILE_APPEND);
		}
		
		/**
		 * Store an admin notice
		 */
		public function display_admin_notice( $message )	{
			$this->notices[] = array('level' => 'updated', 'message' => $message);
			error_log('[INFO] [' . $this->plugin_name . '] ' . $message);
			$this->log($message);
			if ( defined('WP_CLI') && WP_CLI ) {
				WP_CLI::log($message);
			}
		}

		/**
		 * Store an admin error
		 */
		public function display_admin_error( $message )	{
			$this->notices[] = array('level' => 'error', 'message' => $message);
			error_log('[ERROR] [' . $this->plugin_name . '] ' . $message);
			$this->log('[ERROR] ' . $message);
			if ( defined('WP_CLI') && WP_CLI ) {
				WP_CLI::error($message, false);
			}
		}

		/**
		 * Store an admin warning
		 */
		public function display_admin_warning( $message )	{
			$this->notices[] = array('level' => 'error', 'message' => $message);
			error_log('[WARNING] [' . $this->plugin_name . '] ' . $message);
			$this->log('[WARNING] ' . $message);
			if ( defined('WP_CLI') && WP_CLI ) {
				WP_CLI::warning($message);
			}
		}

		/**
		 * Run the importer
		 */
		public function importer() {
			$feasible_actions = array(
				'empty',
				'save',
				'test_database',
				'test_download',
				'test_ftp',
				'import',
				'modify_links',
			);
			$action = '';
			foreach ( $feasible_actions as $potential_action ) {
				if ( isset($_POST[$potential_action]) ) {
					$action = $potential_action;
					break;
				}
			}
			$this->set_plugin_options();
			$this->set_local_timezone();
			$this->dispatch($action);
			$this->display_admin_page(); // Display the admin page
		}
		
		/**
		 * Import triggered by AJAX
		 *
		 * @since    3.0.0
		 */
		public function ajax_importer() {
			$current_user = wp_get_current_user();
			if ( !empty($current_user) && $current_user->has_cap('import') ) {
				$action = filter_input(INPUT_POST, 'plugin_action', FILTER_SANITIZE_SPECIAL_CHARS);

				$this->set_plugin_options();
			
				if ( $action == 'update_wordpress_info') {
					// Update the WordPress database info
					echo $this->get_database_info();

				} else {
					ini_set('display_errors', true); // Display the errors that may happen (ex: Allowed memory size exhausted)

					// Empty the log file if we empty the WordPress content
					if ( (($action == 'empty') && check_admin_referer('empty', 'fgj2wp_nonce_empty'))
					  || (($action == 'import') && filter_input(INPUT_POST, 'automatic_empty', FILTER_VALIDATE_BOOLEAN) && check_admin_referer( 'parameters_form', 'fgj2wp_nonce')) ) {
						$this->empty_log_file();
					}

					$this->set_local_timezone();
					$time_start = date('Y-m-d H:i:s');
					$this->display_admin_notice("=== START $action $time_start ===");
					$result = $this->dispatch($action);
					if ( !empty($result) ) {
						echo wp_json_encode($result); // Send the result to the AJAX caller
					}
					$time_end = date('Y-m-d H:i:s');
					$this->display_admin_notice("=== END $action $time_end ===\n");
				}
			}
			wp_die();
		}
		
		/**
		 * Set the plugin options
		 * 
		 * @since 3.87.0
		 */
		public function set_plugin_options() {
			// Default values
			$this->plugin_options = array(
				'automatic_empty'		=> 0,
				'url'					=> null,
				'download_protocol'		=> 'http',
				'base_dir'				=> '',
				'hostname'				=> 'localhost',
				'port'					=> 3306,
				'database'				=> null,
				'username'				=> 'root',
				'password'				=> '',
				'prefix'				=> 'jos_',
				'introtext'				=> 'in_content',
				'archived_posts'		=> 'not_imported',
				'archived_categories'	=> 'not_imported',
				'unpublished_categories'=> 'not_imported',
				'skip_media'			=> 0,
				'featured_image'		=> 'fulltext',
				'only_featured_image'	=> 0,
				'remove_first_image'	=> 0,
				'remove_accents'		=> 0,
				'skip_thumbnails'		=> 0,
				'import_external'		=> 0,
				'import_duplicates'		=> 0,
				'force_media_import'	=> 0,
				'meta_keywords_in_tags'	=> 0,
				'import_as_pages'		=> 0,
				'timeout'				=> 20,
				'logger_autorefresh'	=> 1,
			);
			$options = get_option('fgj2wp_options');
			if ( is_array($options) ) {
				$this->plugin_options = array_merge($this->plugin_options, $options);
			}
			do_action('fgj2wp_set_plugin_options');
		}
		
		/**
		 * Empty the log file
		 * 
		 * @since 3.80.0
		 */
		public function empty_log_file() {
			file_put_contents($this->log_file, '');
		}
		
		/**
		 * Set the local timezone
		 * 
		 * @since 3.70.0
		 */
		public function set_local_timezone() {
			// Set the time zone
			$timezone = get_option('timezone_string');
			if ( !empty($timezone) ) {
				date_default_timezone_set($timezone);
			}
		}
		
		/**
		 * Dispatch the actions
		 * 
		 * @param string $action Action
		 * @return object Result to return to the caller
		 */
		public function dispatch($action) {
			$timeout = defined('IMPORT_TIMEOUT')? IMPORT_TIMEOUT : 7200; // 2 hours
			set_time_limit($timeout);

			// Suspend the cache during the migration to avoid exhausted memory problem
			wp_suspend_cache_addition(true);
			wp_suspend_cache_invalidation(true);

			// Check if the upload directory is writable
			$upload_dir = wp_upload_dir();
			if ( !is_writable($upload_dir['basedir']) ) {
				$this->display_admin_error(__('The wp-content directory must be writable.', 'fg-joomla-to-wordpress'));
			}

			// Requires at least WordPress 4.5
			if ( version_compare(get_bloginfo('version'), '4.5', '<') ) {
				$this->display_admin_error(sprintf(__('WordPress 4.5+ is required. Please <a href="%s">update WordPress</a>.', 'fg-joomla-to-wordpress'), admin_url('update-core.php')));
			}
			
			elseif ( !empty($action) ) {
				switch($action) {
					
					// Delete content
					case 'empty':
						if ( defined('WP_CLI') || check_admin_referer( 'empty', 'fgj2wp_nonce_empty' ) ) { // Security check
							if ($this->empty_database($_POST['empty_action'])) { // Empty WP database
								$this->display_admin_notice(__('WordPress content removed', 'fg-joomla-to-wordpress'));
							} else {
								$this->display_admin_error(__('Couldn\'t remove content', 'fg-joomla-to-wordpress'));
							}
							wp_cache_flush();
						}
						break;
					
					// Save database options
					case 'save':
						if ( check_admin_referer( 'parameters_form', 'fgj2wp_nonce' ) ) { // Security check
							$this->save_plugin_options();
							$this->display_admin_notice(__('Settings saved', 'fg-joomla-to-wordpress'));
						}
						break;
					
					// Test the database connection
					case 'test_database':
						if ( defined('WP_CLI') || check_admin_referer( 'parameters_form', 'fgj2wp_nonce' ) ) { // Security check
							if ( !defined('WP_CLI') ) {
								// Save database options
								$this->save_plugin_options();
							}
							
							if ( $this->test_database_connection() ) {
								return array('status' => 'OK', 'message' => __('Connection successful', 'fg-joomla-to-wordpress'));
							} else {
								return array('status' => 'Error', 'message' => __('Connection failed', 'fg-joomla-to-wordpress') . '<br />' . __('See the errors in the log below', 'fg-joomla-to-wordpress'));
							}
						}
						break;
					
					// Test the media connection
					case 'test_download':
						if ( defined('WP_CLI') || check_admin_referer( 'parameters_form', 'fgj2wp_nonce' ) ) { // Security check
							if ( !defined('WP_CLI') ) {
								// Save database options
								$this->save_plugin_options();
							}

							$protocol = $this->plugin_options['download_protocol'];
							$protocol_upcase = strtoupper(str_replace('_', ' ', $protocol));
							$this->download_manager = new FG_Joomla_to_WordPress_Download($this, $protocol);
							if ( $this->download_manager->test_connection() ) {
								return array('status' => 'OK', 'message' => sprintf(__('%s connection successful', 'fg-joomla-to-wordpress'), $protocol_upcase));
							} else {
								return array('status' => 'Error', 'message' => sprintf(__('%s connection failed', 'fg-joomla-to-wordpress'), $protocol_upcase));
							}
						}
						break;
					
					// Run the import
					case 'import':
						if ( defined('WP_CLI') || defined('DOING_CRON') || check_admin_referer( 'parameters_form', 'fgj2wp_nonce' ) ) { // Security check
							if ( !defined('DOING_CRON') && !defined('WP_CLI') ) {
								// Save database options
								$this->save_plugin_options();
							} else {
								if ( defined('DOING_CRON') ) {
									// CRON triggered
									$this->plugin_options['automatic_empty'] = 0; // Don't delete the existing data when triggered by cron
								}
							}
							
							if ( $this->test_database_connection() ) {
								// Automatic empty
								if ( $this->plugin_options['automatic_empty'] ) {
									if ($this->empty_database('all')) {
										$this->display_admin_notice(__('WordPress content removed', 'fg-joomla-to-wordpress'));
									} else {
										$this->display_admin_error(__('Couldn\'t remove content', 'fg-joomla-to-wordpress'));
									}
									wp_cache_flush();
								}

								// Import content
								$this->import();
							}
						}
						break;
					
					// Stop the import
					case 'stop_import':
						if ( check_admin_referer( 'parameters_form', 'fgj2wp_nonce' ) ) { // Security check
							$this->stop_import();
						}
						break;
					
					// Modify internal links
					case 'modify_links':
						if ( defined('WP_CLI') || check_admin_referer( 'modify_links', 'fgj2wp_nonce_modify_links' ) ) { // Security check
							$this->modify_links();
							$this->display_admin_notice(sprintf(_n('%d internal link modified', '%d internal links modified', $this->links_count, 'fg-joomla-to-wordpress'), $this->links_count));
						}
						break;
					
					default:
						// Do other actions
						do_action('fgj2wp_dispatch', $action);
				}
			}
		}

		/**
		 * Display the admin page
		 * 
		 */
		private function display_admin_page() {
			$data = $this->plugin_options;

			$data['title'] = __('Import Joomla (FG)', 'fg-joomla-to-wordpress');
			$data['importer'] = $this->importer;
			$data['description'] = __('This plugin will import sections, categories, posts, medias (images, attachments) and web links from a Joomla database into WordPress.', 'fg-joomla-to-wordpress');
			$data['description'] .= "<br />\n" . sprintf(__('For any issue, please read the <a href="%s" target="_blank">FAQ</a> first.', 'fg-joomla-to-wordpress'), $this->faq_url);
			$data['database_info'] = $this->get_database_info();
			
			$data['tab'] = filter_input(INPUT_GET, 'tab', FILTER_SANITIZE_SPECIAL_CHARS);
			
			// Hook for modifying the admin page
			$data = apply_filters('fgj2wp_pre_display_admin_page', $data);

			// Load the CSS and Javascript
			$this->enqueue_styles();
			$this->enqueue_scripts();
			
			include plugin_dir_path( dirname( __FILE__ ) ) . 'admin/partials/admin-display.php';

			// Hook for doing other actions after displaying the admin page
			do_action('fgj2wp_post_display_admin_page');

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
			$option_names[] = 'fgj2wp_options';
			return $option_names;
		}
		
		/**
		 * Get the WordPress database info
		 * 
		 * @return string Database info
		 */
		private function get_database_info() {
			$posts_count = $this->count_posts('post');
			$pages_count = $this->count_posts('page');
			$media_count = $this->count_posts('attachment');
			$cat_count = wp_count_terms('category');
			$tags_count = wp_count_terms('post_tag');

			$database_info =
				sprintf(_n('%d category', '%d categories', $cat_count, 'fg-joomla-to-wordpress'), $cat_count) . "<br />" .
				sprintf(_n('%d post', '%d posts', $posts_count, 'fg-joomla-to-wordpress'), $posts_count) . "<br />" .
				sprintf(_n('%d page', '%d pages', $pages_count, 'fg-joomla-to-wordpress'), $pages_count) . "<br />" .
				sprintf(_n('%d media', '%d medias', $media_count, 'fg-joomla-to-wordpress'), $media_count) . "<br />" .
				sprintf(_n('%d tag', '%d tags', $tags_count, 'fg-joomla-to-wordpress'), $tags_count) . "<br />";
			$database_info = apply_filters('fgj2wp_get_database_info', $database_info);
			return $database_info;
		}
		
		/**
		 * Count the number of posts for a post type
		 * 
		 * @param string $post_type
		 * @return int Number of posts
		 */
		public function count_posts($post_type) {
			$count = 0;
			$excluded_status = array('trash', 'auto-draft');
			$tab_count = wp_count_posts($post_type);
			foreach ( $tab_count as $key => $value ) {
				if ( !in_array($key, $excluded_status) ) {
					$count += $value;
				}
			}
			return $count;
		}

		/**
		 * Add an help tab
		 * 
		 */
		public function add_help_tab() {
			$screen = get_current_screen();
			$screen->add_help_tab(array(
				'id'	=> 'fgj2wp_help_instructions',
				'title'	=> __('Instructions', 'fg-joomla-to-wordpress'),
				'content'	=> '',
				'callback' => array($this, 'help_instructions'),
			));
			$screen->add_help_tab(array(
				'id'	=> 'fgj2wp_help_options',
				'title'	=> __('Options', 'fg-joomla-to-wordpress'),
				'content'	=> '',
				'callback' => array($this, 'help_options'),
			));
			$screen->set_help_sidebar('<a href="' . $this->faq_url . '" target="_blank">' . __('FAQ', 'fg-joomla-to-wordpress') . '</a>');
		}

		/**
		 * Instructions help screen
		 * 
		 * @return string Help content
		 */
		public function help_instructions() {
			include plugin_dir_path( dirname( __FILE__ ) ) . 'admin/partials/help-instructions.tpl.php';
		}

		/**
		 * Options help screen
		 * 
		 * @return string Help content
		 */
		public function help_options() {
			include plugin_dir_path( dirname( __FILE__ ) ) . 'admin/partials/help-options.tpl.php';
		}

		/**
		 * Open the connection on Joomla database
		 *
		 * return boolean Connection successful or not
		 */
		public function joomla_connect() {
			global $joomla_db;

			if ( !class_exists('PDO') ) {
				$this->display_admin_error(__('PDO is required. Please enable it.', 'fg-joomla-to-wordpress'));
				return false;
			}
			try {
				$joomla_db = new PDO('mysql:host=' . $this->plugin_options['hostname'] . ';port=' . $this->plugin_options['port'] . ';dbname=' . $this->plugin_options['database'], $this->plugin_options['username'], $this->plugin_options['password'], array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\''));
				$joomla_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // Display SQL errors
			} catch ( PDOException $e ) {
				$this->display_admin_error(__('Couldn\'t connect to the Joomla database. Please check your parameters. And be sure the WordPress server can access the Joomla database.', 'fg-joomla-to-wordpress') . "<br />\n" . $e->getMessage() . "<br />\n" . sprintf(__('Please read the <a href="%s" target="_blank">FAQ for the solution</a>.', 'fg-joomla-to-wordpress'), $this->faq_url));
				return false;
			}
			$this->joomla_version = $this->joomla_version();
			return true;
		}

		/**
		 * Execute a SQL query on the Joomla database
		 * 
		 * @param string $sql SQL query
		 * @param bool $display_error Display the error?
		 * @return array Query result
		 */
		public function joomla_query($sql, $display_error = true) {
			global $joomla_db;
			$result = array();
			
			if ( !empty($sql) ) {
				try {
					$query = $joomla_db->query($sql, PDO::FETCH_ASSOC);
					if ( is_object($query) ) {
						foreach ( $query as $row ) {
							$result[] = $row;
						}
					}

				} catch ( PDOException $e ) {
					$error_message = $e->getMessage();
					if ( preg_match('/SQLSTATE\[HY000\]/', $error_message) ) { // MySQL server has gone away
						$this->display_admin_error(__('Error:', 'fg-joomla-to-wordpress') . $error_message);
						die();
					}
					if ( $display_error ) {
						$this->display_admin_error(__('Error:', 'fg-joomla-to-wordpress') . $error_message);
					}
				}
			}
			return $result;
		}

		/**
		 * Delete all posts, medias and categories from the database
		 *
		 * @param string $action	imported = removes only new imported data
		 * 							all = removes all
		 * @return boolean
		 */
		private function empty_database($action) {
			global $wpdb;
			$result = true;

			$wpdb->show_errors();

			// Hook for doing other actions before emptying the database
			do_action('fgj2wp_pre_empty_database', $action);

			$sql_queries = array();

			if ( $action == 'all' ) {
				// Remove all content
				
				$this->save_wp_data();
				
				$sql_queries[] = "TRUNCATE $wpdb->commentmeta";
				$sql_queries[] = "TRUNCATE $wpdb->comments";
				$sql_queries[] = "TRUNCATE $wpdb->term_relationships";
				$sql_queries[] = "TRUNCATE $wpdb->termmeta";
				$sql_queries[] = "TRUNCATE $wpdb->postmeta";
				$sql_queries[] = "TRUNCATE $wpdb->posts";
				$sql_queries[] = <<<SQL
-- Delete Terms
DELETE FROM $wpdb->terms
WHERE term_id > 1 -- non-classe
SQL;
				$sql_queries[] = <<<SQL
-- Delete Terms taxonomies
DELETE FROM $wpdb->term_taxonomy
WHERE term_id > 1 -- non-classe
SQL;
				$sql_queries[] = "ALTER TABLE $wpdb->terms AUTO_INCREMENT = 2";
				$sql_queries[] = "ALTER TABLE $wpdb->term_taxonomy AUTO_INCREMENT = 2";
				
			} else {
				
				// (Re)create a temporary table with the IDs to delete
				$sql_queries[] = <<<SQL
DROP TEMPORARY TABLE IF EXISTS {$wpdb->prefix}fg_data_to_delete;
SQL;

				$sql_queries[] = <<<SQL
CREATE TEMPORARY TABLE IF NOT EXISTS {$wpdb->prefix}fg_data_to_delete (
`id` bigint(20) unsigned NOT NULL,
PRIMARY KEY (`id`)
) DEFAULT CHARSET=utf8;
SQL;
				
				// Insert the imported posts IDs in the temporary table
				$sql_queries[] = <<<SQL
INSERT IGNORE INTO {$wpdb->prefix}fg_data_to_delete (`id`)
SELECT post_id FROM $wpdb->postmeta
WHERE meta_key LIKE '_fgj2wp_%'
SQL;
				
				// Delete the imported posts and related data

				$sql_queries[] = <<<SQL
-- Delete Comments and Comment metas
DELETE c, cm
FROM $wpdb->comments c
LEFT JOIN $wpdb->commentmeta cm ON cm.comment_id = c.comment_ID
INNER JOIN {$wpdb->prefix}fg_data_to_delete del
WHERE c.comment_post_ID = del.id;
SQL;

				$sql_queries[] = <<<SQL
-- Delete Term relashionships
DELETE tr
FROM $wpdb->term_relationships tr
INNER JOIN {$wpdb->prefix}fg_data_to_delete del
WHERE tr.object_id = del.id;
SQL;

				$sql_queries[] = <<<SQL
-- Delete Posts Children and Post metas
DELETE p, pm
FROM $wpdb->posts p
LEFT JOIN $wpdb->postmeta pm ON pm.post_id = p.ID
INNER JOIN {$wpdb->prefix}fg_data_to_delete del
WHERE p.post_parent = del.id
AND p.post_type != 'attachment'; -- Don't remove the old medias attached to posts
SQL;

				$sql_queries[] = <<<SQL
-- Delete Posts and Post metas
DELETE p, pm
FROM $wpdb->posts p
LEFT JOIN $wpdb->postmeta pm ON pm.post_id = p.ID
INNER JOIN {$wpdb->prefix}fg_data_to_delete del
WHERE p.ID = del.id;
SQL;

				// Truncate the temporary table
				$sql_queries[] = <<<SQL
TRUNCATE {$wpdb->prefix}fg_data_to_delete;
SQL;
				
				// Insert the imported terms IDs in the temporary table
				$sql_queries[] = <<<SQL
INSERT IGNORE INTO {$wpdb->prefix}fg_data_to_delete (`id`)
SELECT term_id FROM $wpdb->termmeta
WHERE meta_key LIKE '_fgj2wp_%'
SQL;
				
				// Delete the imported terms and related data

				$sql_queries[] = <<<SQL
-- Delete Terms, Term taxonomies and Term metas
DELETE t, tt, tm
FROM $wpdb->terms t
LEFT JOIN $wpdb->term_taxonomy tt ON tt.term_id = t.term_id
LEFT JOIN $wpdb->termmeta tm ON tm.term_id = t.term_id
INNER JOIN {$wpdb->prefix}fg_data_to_delete del
WHERE t.term_id = del.id;
SQL;

				// Truncate the temporary table
				$sql_queries[] = <<<SQL
TRUNCATE {$wpdb->prefix}fg_data_to_delete;
SQL;
				
				// Insert the imported comments IDs in the temporary table
				$sql_queries[] = <<<SQL
INSERT IGNORE INTO {$wpdb->prefix}fg_data_to_delete (`id`)
SELECT comment_id FROM $wpdb->commentmeta
WHERE meta_key LIKE '_fgj2wp_%'
SQL;
				
				// Delete the imported comments and related data
				$sql_queries[] = <<<SQL
-- Delete Comments and Comment metas
DELETE c, cm
FROM $wpdb->comments c
LEFT JOIN $wpdb->commentmeta cm ON cm.comment_id = c.comment_ID
INNER JOIN {$wpdb->prefix}fg_data_to_delete del
WHERE c.comment_ID = del.id;
SQL;

			}

			// Execute SQL queries
			if ( count($sql_queries) > 0 ) {
				foreach ( $sql_queries as $sql ) {
					$result &= $wpdb->query($sql);
				}
			}

			if ( $action == 'all' ) {
				$this->restore_wp_data();
			}
				
			// Hook for doing other actions after emptying the database
			do_action('fgj2wp_post_empty_database', $action);

			// Drop the temporary table
			$wpdb->query("DROP TEMPORARY TABLE IF EXISTS {$wpdb->prefix}fg_data_to_delete;");
				
			// Reset the Joomla import counters
			update_option('fgj2wp_last_article_id', 0);
			update_option('fgj2wp_last_category_id', 0);
			update_option('fgj2wp_last_section_id', 0);

			// Delete the sticky posts
			delete_option('sticky_posts');
			
			// Re-count categories and tags items
			$this->terms_count();

			// Update cache
			$this->clean_cache();
			delete_transient('wc_count_comments');

			$this->optimize_database();

			$this->progressbar->set_total_count(0);
			
			$wpdb->hide_errors();
			return ($result !== false);
		}
		
		/**
		 * Save the data used by the theme (WP 5.9)
		 * 
		 * @since 3.93.0
		 */
		private function save_wp_data() {
			$this->save_wp_posts();
			$this->save_wp_terms();
			$this->save_wp_term_relationships();
		}
		
		/**
		 * Save the posts and post meta used by the theme (WP 5.9)
		 * 
		 * @since 3.93.0
		 */
		private function save_wp_posts() {
			global $wpdb;
			$sql = "
				SELECT *
				FROM {$wpdb->posts} p
				WHERE p.`post_type` LIKE 'wp\_%'
				ORDER BY p.`ID`
			";
			$posts = $wpdb->get_results($sql, ARRAY_A);
			foreach ( $posts as &$post ) {
				$sql_meta = "SELECT `meta_key`, `meta_value` FROM {$wpdb->postmeta} WHERE `post_id` = %d ORDER BY `meta_id`";
				$postmetas = $wpdb->get_results($wpdb->prepare($sql_meta, $post['ID']), ARRAY_A);
				$post['meta'] = $postmetas;
				unset($post['ID']);
			}
			update_option('fgj2wp_save_posts', $posts);
		}

		/**
		 * Save the terms, term taxonomies and term meta used by the theme (WP 5.9)
		 * 
		 * @since 3.93.0
		 */
		private function save_wp_terms() {
			global $wpdb;
			$sql = "
				SELECT t.term_id, t.name, t.slug, tt.taxonomy, tt.description, tt.count
				FROM {$wpdb->terms} t
				INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
				WHERE tt.`taxonomy` LIKE 'wp\_%'
				ORDER BY t.term_id
			";
			$terms = $wpdb->get_results($sql, ARRAY_A);
			foreach ( $terms as &$term ) {
				$sql_meta = "SELECT `meta_key`, `meta_value` FROM {$wpdb->termmeta} WHERE `term_id` = %d ORDER BY `meta_id`";
				$termmetas = $wpdb->get_results($wpdb->prepare($sql_meta, $term['term_id']), ARRAY_A);
				$term['meta'] = $termmetas;
				unset($term['term_id']);
			}
			update_option('fgj2wp_save_terms', $terms);
		}

		/**
		 * Save the terms relationships used by the theme (WP 5.9)
		 * 
		 * @since 3.93.0
		 */
		private function save_wp_term_relationships() {
			global $wpdb;
			$sql = "
				SELECT p.post_name, t.name AS term_name
				FROM {$wpdb->term_relationships} tr
				INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id
				INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
				INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
				WHERE p.`post_type` LIKE 'wp\_%'
			";
			$term_relationships = $wpdb->get_results($sql, ARRAY_A);
			update_option('fgj2wp_save_term_relationships', $term_relationships);
		}

		/**
		 * Restore the saved data used by the theme (WP 5.9)
		 * 
		 * @since 3.93.0
		 */
		private function restore_wp_data() {
			$this->restore_wp_posts();
			$this->restore_wp_terms();
			$this->restore_wp_term_relationships();
		}
		
		/**
		 * Restore the saved posts and post meta used by the theme (WP 5.9)
		 * 
		 * @since 3.93.0
		 */
		private function restore_wp_posts() {
			global $wpdb;
			$posts = get_option('fgj2wp_save_posts');
			foreach ( $posts as $post ) {
				$postmetas = $post['meta'];
				unset($post['meta']);
				$wpdb->insert($wpdb->posts, $post);
				$post_id = $wpdb->insert_id;
				if ( $post_id ) {
					foreach ( $postmetas as $meta ) {
						add_post_meta($post_id, $meta['meta_key'], $meta['meta_value']);
					}
				}
			}
		}

		/**
		 * Restore the saved terms, term taxonomies and term meta used by the theme (WP 5.9)
		 * 
		 * @since 3.93.0
		 */
		private function restore_wp_terms() {
			global $wpdb;
			$terms = get_option('fgj2wp_save_terms');
			foreach ( $terms as $term ) {
				$wpdb->insert($wpdb->terms, array(
					'name' => $term['name'],
					'slug' => $term['slug'],
				));
				$term_id = $wpdb->insert_id;
				if ( $term_id ) {
					$wpdb->insert($wpdb->term_taxonomy, array(
						'term_id' => $term_id,
						'taxonomy' => $term['taxonomy'],
						'description' => $term['description'],
						'count' => $term['count'],
					));
					foreach ( $term['meta'] as $meta ) {
						add_term_meta($term_id, $meta['meta_key'], $meta['meta_value']);
					}
				}
			}
		}
		
		/**
		 * Restore the saved term relationships used by the theme (WP 5.9)
		 * 
		 * @since 3.93.0
		 */
		private function restore_wp_term_relationships() {
			global $wpdb;
			$term_relationships = get_option('fgj2wp_save_term_relationships');
			foreach ( $term_relationships as $term_relationship ) {
				$post_id = $wpdb->get_var($wpdb->prepare("SELECT `ID` FROM {$wpdb->posts} WHERE post_name = %s", $term_relationship['post_name']));
				$term_taxonomy_id = $wpdb->get_var($wpdb->prepare("SELECT tt.`term_taxonomy_id` FROM {$wpdb->term_taxonomy} tt INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id WHERE t.name = %s", $term_relationship['term_name']));
				if ( $post_id && $term_taxonomy_id ) {
					$wpdb->insert($wpdb->term_relationships, array(
						'object_id' => $post_id,
						'term_taxonomy_id' => $term_taxonomy_id,
					));
				}
			}
		}

		/**
		 * Optimize the database
		 *
		 */
		protected function optimize_database() {
			global $wpdb;

			$sql = <<<SQL
OPTIMIZE TABLE 
`$wpdb->commentmeta`,
`$wpdb->comments`,
`$wpdb->options`,
`$wpdb->postmeta`,
`$wpdb->posts`,
`$wpdb->terms`,
`$wpdb->term_relationships`,
`$wpdb->term_taxonomy`,
`$wpdb->termmeta`
SQL;
			$wpdb->query($sql);
		}

		/**
		 * Test the database connection
		 * 
		 * @return boolean
		 */
		private function test_database_connection() {
			global $joomla_db;

			if ( $this->joomla_connect() ) {
				if ( $this->table_exists('content') ) {
					$this->display_admin_notice(__('Connected with success to the Joomla database', 'fg-joomla-to-wordpress'));
					$connection_ok = true;
				} else {
					$connection_ok = apply_filters('fgj2wp_test_database_connection', false);
				}
				if ( $connection_ok ) {
					do_action('fgj2wp_post_test_database_connection');
					return true;
				} else {
					$this->display_admin_error(__('Joomla tables not found. Please make sure you have entered the right Joomla database name and table prefix.', 'fg-joomla-to-wordpress') . "<br />\n");
				}
			}
			$joomla_db = null;
			return false;
		}

		/**
		 * Test for Joomla version 1.0
		 *
		 * @param bool $import_doable Can we start the import?
		 * @return bool False if Joomla version < 1.5 (for Joomla 1.0 and Mambo)
		 */
		public function test_joomla_1_0($import_doable) {
			if ( $import_doable ) {
				if ( version_compare($this->joomla_version, '1.5', '<') ) {
					$this->display_admin_error(sprintf(__('Your version of Joomla (probably 1.0) is not supported by this plugin. Please consider upgrading to the <a href="%s" target="_blank">Premium version</a>.', 'fg-joomla-to-wordpress'), 'https://www.fredericgilles.net/fg-joomla-to-wordpress/'));
					// Deactivate the Joomla info feature
					remove_action('fgj2wp_post_test_database_connection', array($this, 'get_joomla_info'), 9);
					$import_doable = false;
				}
			}
			return $import_doable;
		}

		/**
		 * Get some Joomla information
		 *
		 */
		public function get_joomla_info() {
			$message = __('Joomla data found:', 'fg-joomla-to-wordpress') . "\n";

			// Sections
			if ( version_compare($this->joomla_version, '1.5', '<=') ) {
				$sections_count = $this->get_sections_count();
				$message .= sprintf(_n('%d section', '%d sections', $sections_count, 'fg-joomla-to-wordpress'), $sections_count) . "\n";
			}

			// Categories
			$cat_count = $this->get_categories_count();
			$message .= sprintf(_n('%d category', '%d categories', $cat_count, 'fg-joomla-to-wordpress'), $cat_count) . "\n";

			// Articles
			$posts_count = $this->get_posts_count();
			$message .= sprintf(_n('%d article', '%d articles', $posts_count, 'fg-joomla-to-wordpress'), $posts_count) . "\n";

			// Web links
			if ( $this->table_exists('weblinks') ) { // Joomla 3.4
				$weblinks_count = $this->get_weblinks_count();
				$message .= sprintf(_n('%d web link', '%d web links', $weblinks_count, 'fg-joomla-to-wordpress'), $weblinks_count) . "\n";
			}

			$message = apply_filters('fgj2wp_pre_display_joomla_info', $message);

			$this->display_admin_notice($message);
		}

		/**
		 * Get the number of Joomla categories
		 * 
		 * @param string $extension Joomla Extension ("com_content" by default)
		 * @return int Number of categories
		 */
		public function get_categories_count($extension='com_content') {
			$prefix = $this->plugin_options['prefix'];
			if ( version_compare($this->joomla_version, '1.5', '<=') ) {
				if ( $extension == 'com_content' ) {
					$sql = "
						SELECT COUNT(*) AS nb
						FROM {$prefix}categories c
					";
					if ( $this->table_exists('sections') ) {
						$sql .= "INNER JOIN {$prefix}sections AS s ON s.id = c.section\n";
					}
					$sql .= "WHERE 1 = 1\n";
				} else {
					$sql = "
						SELECT COUNT(*) AS nb
						FROM {$prefix}categories c
						WHERE c.section = '$extension'
					";
				}
			} else { // Joomla > 1.5
				$sql = "
					SELECT COUNT(*) AS nb
					FROM {$prefix}categories c
					WHERE c.extension = '$extension'
				";
			}
			$sql .= "AND c.published >= 0 -- Don't import the deleted categories\n";
			$sql .= $this->get_categories_extra_criteria();
			$sql = apply_filters('fgj2wp_get_categories_count_sql', $sql, $prefix);
			$result = $this->joomla_query($sql);
			$cat_count = isset($result[0]['nb'])? $result[0]['nb'] : 0;
			return $cat_count;
		}

		/**
		 * Get the number of Joomla sections
		 * 
		 * @return int Number of sections
		 */
		private function get_sections_count() {
			$sections_count = 0;
			if ( $this->table_exists('sections') ) {
				$prefix = $this->plugin_options['prefix'];
				$sql = "
					SELECT COUNT(*) AS nb
					FROM {$prefix}sections s
				";
				$result = $this->joomla_query($sql);
				$sections_count = isset($result[0]['nb'])? $result[0]['nb'] : 0;
			}
			return $sections_count;
		}

		/**
		 * Get the number of Joomla categories
		 * 
		 * @param string $component Component name
		 * @return int Number of categories
		 */
		public function get_component_categories_count($component) {
			$prefix = $this->plugin_options['prefix'];
			if ( version_compare($this->joomla_version, '1.5', '<=') ) {
				$extension_field = 'c.section';
			} else {
				$extension_field = 'c.extension';
			}
			$sql = "
				SELECT COUNT(*) AS nb
				FROM {$prefix}categories c
				WHERE $extension_field = '$component'
			";
			$result = $this->joomla_query($sql);
			$cat_count = isset($result[0]['nb'])? $result[0]['nb'] : 0;
			return $cat_count;
		}

		/**
		 * Get the number of Joomla articles
		 * 
		 * @return int Number of articles
		 */
		private function get_posts_count() {
			$prefix = $this->plugin_options['prefix'];
			$extra_criteria = '';
			$sql = "
				SELECT COUNT(*) AS nb
				FROM {$prefix}content p
				WHERE 1 = 1
			";
			if ( $this->column_exists('content', 'state') ) {
				$extra_criteria .= "AND p.state >= -1 -- don't get the trash\n";
				if ( $this->plugin_options['archived_posts'] == 'not_imported' ) {
					$extra_criteria .= "AND p.state != 2\n";
				}
			}
			$skipped_categories = $this->get_skipped_categories();
			if ( !empty($skipped_categories) ) {
				$extra_criteria .= "AND p.catid NOT IN(" . implode(',', $skipped_categories) . ")\n";
			}
			$sql .= $extra_criteria;
			$sql = apply_filters('fgj2wp_get_posts_count_sql', $sql, $prefix);
			$result = $this->joomla_query($sql);
			$posts_count = isset($result[0]['nb'])? $result[0]['nb'] : 0;
			return $posts_count;
		}

		/**
		 * Get the number of Joomla web links
		 * 
		 * @return int Number of web links
		 */
		private function get_weblinks_count() {
			$prefix = $this->plugin_options['prefix'];
			if ( version_compare($this->joomla_version, '1.5', '<=') ) {
				$published_field = 'published';
			} else {
				$published_field = 'state';
			}
			$sql = "
				SELECT COUNT(*) AS nb
				FROM {$prefix}weblinks l
				WHERE l.$published_field = 1
			";
			$result = $this->joomla_query($sql);
			$weblinks_count = isset($result[0]['nb'])? $result[0]['nb'] : 0;
			return $weblinks_count;
		}

		/**
		 * Save the plugin options
		 *
		 */
		public function save_plugin_options() {
			$this->plugin_options = array_merge($this->plugin_options, $this->validate_form_info());
			update_option('fgj2wp_options', $this->plugin_options);

			// Hook for doing other actions after saving the options
			do_action('fgj2wp_post_save_plugin_options');
		}

		/**
		 * Validate POST info
		 *
		 * @return array Form parameters
		 */
		private function validate_form_info() {
			// Add http:// before the URL if it is missing
			$url = esc_url(filter_input(INPUT_POST, 'url', FILTER_SANITIZE_URL));
			if ( !empty($url) && (preg_match('#^https?://#', $url) == 0) ) {
				$url = 'http://' . $url;
			}
			return array(
				'automatic_empty'		=> filter_input(INPUT_POST, 'automatic_empty', FILTER_VALIDATE_BOOLEAN),
				'url'					=> $url,
				'download_protocol'		=> filter_input(INPUT_POST, 'download_protocol', FILTER_SANITIZE_SPECIAL_CHARS),
				'base_dir'				=> filter_input(INPUT_POST, 'base_dir', FILTER_SANITIZE_SPECIAL_CHARS),
				'hostname'				=> filter_input(INPUT_POST, 'hostname', FILTER_SANITIZE_SPECIAL_CHARS),
				'port'					=> filter_input(INPUT_POST, 'port', FILTER_SANITIZE_NUMBER_INT),
				'database'				=> filter_input(INPUT_POST, 'database', FILTER_SANITIZE_SPECIAL_CHARS),
				'username'				=> filter_input(INPUT_POST, 'username'),
				'password'				=> filter_input(INPUT_POST, 'password'),
				'prefix'				=> filter_input(INPUT_POST, 'prefix', FILTER_SANITIZE_SPECIAL_CHARS),
				'introtext'				=> filter_input(INPUT_POST, 'introtext', FILTER_SANITIZE_SPECIAL_CHARS),
				'archived_posts'		=> filter_input(INPUT_POST, 'archived_posts', FILTER_SANITIZE_SPECIAL_CHARS),
				'archived_categories'	=> filter_input(INPUT_POST, 'archived_categories', FILTER_SANITIZE_SPECIAL_CHARS),
				'unpublished_categories'=> filter_input(INPUT_POST, 'unpublished_categories', FILTER_SANITIZE_SPECIAL_CHARS),
				'skip_media'			=> filter_input(INPUT_POST, 'skip_media', FILTER_VALIDATE_BOOLEAN),
				'featured_image'		=> filter_input(INPUT_POST, 'featured_image', FILTER_SANITIZE_SPECIAL_CHARS),
				'only_featured_image'	=> filter_input(INPUT_POST, 'only_featured_image', FILTER_VALIDATE_BOOLEAN),
				'remove_first_image'	=> filter_input(INPUT_POST, 'remove_first_image', FILTER_VALIDATE_BOOLEAN),
				'remove_accents'		=> filter_input(INPUT_POST, 'remove_accents', FILTER_VALIDATE_BOOLEAN),
				'skip_thumbnails'		=> filter_input(INPUT_POST, 'skip_thumbnails', FILTER_VALIDATE_BOOLEAN),
				'import_external'		=> filter_input(INPUT_POST, 'import_external', FILTER_VALIDATE_BOOLEAN),
				'import_duplicates'		=> filter_input(INPUT_POST, 'import_duplicates', FILTER_VALIDATE_BOOLEAN),
				'force_media_import'	=> filter_input(INPUT_POST, 'force_media_import', FILTER_VALIDATE_BOOLEAN),
				'meta_keywords_in_tags'	=> filter_input(INPUT_POST, 'meta_keywords_in_tags', FILTER_VALIDATE_BOOLEAN),
				'import_as_pages'		=> filter_input(INPUT_POST, 'import_as_pages', FILTER_VALIDATE_BOOLEAN),
				'timeout'				=> filter_input(INPUT_POST, 'timeout', FILTER_SANITIZE_NUMBER_INT),
				'logger_autorefresh'	=> filter_input(INPUT_POST, 'logger_autorefresh', FILTER_VALIDATE_BOOLEAN),
			);
		}

		/**
		 * Import
		 *
		 */
		private function import() {
			if ( $this->joomla_connect() ) {

				$time_start = microtime(true);

				define('WP_IMPORTING', true);
				update_option('fgj2wp_stop_import', false, false); // Reset the stop import action
				
				// To solve the issue of links containing ":" in multisite mode
				kses_remove_filters();
				
				global $wp_filter;
				unset($wp_filter['wp_insert_post']); // Remove the "wp_insert_post" hook that consumes a lot of CPU and memory
				
				// Check prerequesites before the import
				$do_import = apply_filters('fgj2wp_pre_import_check', true);
				if ( !$do_import) {
					return;
				}

				// Allow the non valid SSL certificates
				stream_context_set_default(array(
					'ssl' => array(
						'verify_peer' => false,
						'verify_peer_name' => false,
					),
				));
				
				$total_elements_count = $this->get_total_elements_count();
				$this->progressbar->set_total_count($total_elements_count);
				
				$this->post_type = ($this->plugin_options['import_as_pages'] == 1) ? 'page' : 'post';

				// Set the Download Manager
				$this->download_manager = new FG_Joomla_to_WordPress_Download($this, $this->plugin_options['download_protocol']);
				$this->download_manager->test_connection();
				
				$this->imported_media = $this->get_imported_joomla_posts($meta_key = '_fgj2wp_old_file');
				
				// Hook for doing other actions before the import
				do_action('fgj2wp_pre_import');

				// Categories
				if ( !isset($this->premium_options['skip_categories']) || !$this->premium_options['skip_categories'] ) {
					$cat_count = $this->import_categories();
					$this->display_admin_notice(sprintf(_n('%d category imported', '%d categories imported', $cat_count, 'fg-joomla-to-wordpress'), $cat_count));
				}

				// Set the list of previously imported categories
				$this->get_imported_categories();
				$this->skipped_categories = $this->get_skipped_categories();
				
				if ( !isset($this->premium_options['skip_articles']) || !$this->premium_options['skip_articles'] ) {
					// Posts and medias
					if ( !$this->import_posts() ) { // Anti-duplicate
						return;
					}
					switch ($this->post_type) {
						case 'page':
							$this->display_admin_notice(sprintf(_n('%d page imported', '%d pages imported', $this->posts_count, 'fg-joomla-to-wordpress'), $this->posts_count));
							break;
						case 'post':
						default:
							$this->display_admin_notice(sprintf(_n('%d post imported', '%d posts imported', $this->posts_count, 'fg-joomla-to-wordpress'), $this->posts_count));
					}

					// Tags
					if ($this->post_type == 'post') {
						if ( $this->plugin_options['meta_keywords_in_tags'] ) {
							$tags_count = count($this->imported_tags);
							$this->display_admin_notice(sprintf(_n('%d tag imported', '%d tags imported', $tags_count, 'fg-joomla-to-wordpress'), $tags_count));
						}
					}
				}
				if ( !$this->import_stopped() ) {
					// Hook for doing other actions after the import
					do_action('fgj2wp_post_import');
				}
				$this->display_admin_notice(sprintf(_n('%d media imported', '%d medias imported', $this->media_count, 'fg-joomla-to-wordpress'), $this->media_count));

				// Hook for other notices
				do_action('fgj2wp_import_notices');

				// Debug info
				if ( function_exists('wp_get_environment_type') && in_array(wp_get_environment_type(), array('local', 'development')) ) {
					$this->display_admin_notice(sprintf("Memory used: %s bytes<br />\n", number_format(memory_get_peak_usage())));
					$time_end = microtime(true);
					$this->display_admin_notice(sprintf("Duration: %d sec<br />\n", $time_end - $time_start));
				}

				if ( $this->import_stopped() ) {
					
					// Import stopped by the user
					$this->display_admin_notice("IMPORT STOPPED BY USER");
					
				} else {
					// Import completed
					$this->display_admin_notice(__("Don't forget to modify internal links.", 'fg-joomla-to-wordpress'));
					$this->display_admin_notice("IMPORT COMPLETED");
				}
				
				wp_cache_flush();
			}
		}

		/**
		 * Actions to do before the import
		 * 
		 * @param bool $import_doable Can we start the import?
		 * @return bool Can we start the import?
		 */
		public function pre_import_check($import_doable) {
			if ( $import_doable ) {
				if ( !$this->plugin_options['skip_media'] && empty($this->plugin_options['url']) ) {
					$this->display_admin_error(__('The URL field is required to import the media.', 'fg-joomla-to-wordpress'));
					$import_doable = false;
				}
			}
			return $import_doable;
		}

		/**
		 * Get the number of elements to import
		 * 
		 * @return int Number of elements to import
		 */
		private function get_total_elements_count() {
			$count = 0;
			
			if ( !isset($this->premium_options['skip_categories']) || !$this->premium_options['skip_categories'] ) {
				// Sections
				if ( version_compare($this->joomla_version, '1.5', '<=') ) {
					$count += $this->get_sections_count();
				}

				// Categories
				$count += $this->get_categories_count();
			}

			// Articles
			if ( !isset($this->premium_options['skip_articles']) || !$this->premium_options['skip_articles'] ) {
				$count += $this->get_posts_count();
			}

			// Web links
			if ( !isset($this->premium_options['skip_weblinks']) || !$this->premium_options['skip_weblinks'] ) {
				if ( $this->table_exists('weblinks') ) { // Joomla 3.4
					$count += $this->get_weblinks_count();
					$count += $this->get_component_categories_count('com_weblinks');
				}
			}
			$count = apply_filters('fgj2wp_get_total_elements_count', $count);
			
			return $count;
		}
		
		/**
		 * Import categories
		 *
		 * @return int Number of categories imported
		 */
		private function import_categories() {
			$cat_count = 0;
			$taxonomy = 'category';
			$all_categories = array();
			
			if ( $this->import_stopped() ) {
				return 0;
			}
			
			$message = __('Importing categories...', 'fg-joomla-to-wordpress');
			if ( defined('WP_CLI') ) {
				$progress_cli = \WP_CLI\Utils\make_progress_bar($message, $this->get_categories_count());
			} else {
				$this->log($message);
			}
			
			// Allow HTML in term descriptions
			foreach ( array('pre_term_description') as $filter ) {
				remove_filter($filter, 'wp_filter_kses');
			}
			
			// Joomla sections (Joomla version ≤ 1.5)
			if ( version_compare($this->joomla_version, '1.5', '<=') ) {
				do {
					if ( $this->import_stopped() ) {
						break;
					}
					$sections = $this->get_sections($this->chunks_size);
					$all_categories = array_merge($all_categories, $sections);
					// Insert the sections
					$cat_count += $this->insert_categories($sections, $taxonomy, 'fgj2wp_last_section_id');
					
					if ( defined('WP_CLI') ) {
						$progress_cli->tick($this->chunks_size);
					}
				} while ( !is_null($sections) && (count($sections) > 0) );
			}
			
			if ( $this->exists_duplicate_categories() ) {
				// Import all the categories starting by the parent categories
				$categories = $this->get_categories(0); // Get all the categories
				if ( !is_null($categories) && (count($categories) > 0) ) {
					$all_categories = array_merge($all_categories, $categories);
					// Insert the categories
					$cat_count += $this->insert_categories($categories);
				}

				if ( defined('WP_CLI') ) {
					$progress_cli->tick(count($categories));
				}
				
			} else {
				// Import the categories by batch
				do {
					if ( $this->import_stopped() ) {
						break;
					}
					$categories = $this->get_categories($this->chunks_size); // Get the Joomla categories

					if ( !is_null($categories) && (count($categories) > 0) ) {
						$all_categories = array_merge($all_categories, $categories);
						// Insert the categories
						$cat_count += $this->insert_categories($categories);
					}

					if ( defined('WP_CLI') ) {
						$progress_cli->tick($this->chunks_size);
					}
				} while ( !is_null($categories) && (count($categories) > 0) );
			}
			
			$all_categories = apply_filters('fgj2wp_import_categories', $all_categories);
			
			if ( defined('WP_CLI') ) {
				$progress_cli->finish();
			}
			
			if ( !$this->import_stopped() ) {
				// Hook after importing all the categories
				do_action('fgj2wp_post_import_categories', $all_categories, $taxonomy);
			}

			return $cat_count;
		}
		
		/**
		 * Are there categories with the same name?
		 * 
		 * @since 4.13.0
		 * 
		 * @return boolean
		 */
		private function exists_duplicate_categories() {
			$prefix = $this->plugin_options['prefix'];
			if ( version_compare($this->joomla_version, '1.5', '>') ) {
				$extension_criteria = "AND c.extension = 'com_content'";
			} else {
				$extension_criteria = '';
			}
			$sql = "
				SELECT c.title, COUNT(*) AS nb
				FROM {$prefix}categories c
				WHERE c.published >= 0 -- Don't import the deleted categories
				$extension_criteria
				GROUP BY c.title
				HAVING nb > 1
			";
			$duplicate_categories = $this->joomla_query($sql);
			return count($duplicate_categories) > 0;
		}
		
		/**
		 * Insert a list of categories in the database
		 * 
		 * @param array $categories List of categories
		 * @param string $taxonomy Taxonomy
		 * @param string $last_category_metakey Last category meta key
		 * @return int Number of inserted categories
		 */
		public function insert_categories($categories, $taxonomy='category', $last_category_metakey='fgj2wp_last_category_id') {
			$cat_count = 0;
			$processed_cat_count = 0;
			$term_metakey = '_fgj2wp_old_category_id';
			
			// Set the list of previously imported categories
			$this->get_imported_categories();
			
			$terms = array();
			if ( $taxonomy == 'category') {
				$terms[] = '1'; // unclassified category
			}
			
			foreach ( $categories as $category ) {

				$category_id = $category['id'];

				// Check if the category is already imported
				if ( array_key_exists($category_id, $this->imported_categories) ) {
					// Prevent the process to hang if the categories counter has been resetted
					$category_id_without_prefix = preg_replace('/^(\D*)/', '', $category_id);
					update_option($last_category_metakey, $category_id_without_prefix);

					continue; // Do not import already imported category
				}
				$processed_cat_count++;
				
				$parent_id = isset($category['parent_id']) && isset($this->imported_categories[$category['parent_id']])? $this->imported_categories[$category['parent_id']]: '';
				
				// If the slug is a date, get the title instead
				if ( preg_match('/^\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2}$/', $category['name']) ) {
					$slug = $category['title'];
				} else {
					$slug = $category['name'];
				}
				
				$description = isset($category['description'])? $category['description']: '';
				$date = isset($category['date'])? $category['date'] : '';
				$description = $this->process_category_description($description, $date);
				
				// Insert the category
				$new_category = array(
					'cat_name' 				=> $category['title'],
					'category_description'	=> $description,
					'category_nicename'		=> $slug,
					'taxonomy'				=> $taxonomy,
					'category_parent'		=> $parent_id,
				);

				// Hook before inserting the category
				$new_category = apply_filters('fgj2wp_pre_insert_category', $new_category, $category);
				
				$new_cat_id = wp_insert_category($new_category, true);
				
				// Store the last ID to resume the import where it left off
				$category_id_without_prefix = preg_replace('/^(\D*)/', '', $category_id);
				update_option($last_category_metakey, $category_id_without_prefix);
				
				if ( is_wp_error($new_cat_id) ) {
					if ( isset($new_cat_id->error_data['term_exists']) ) {
						// Store the Joomla category ID
						add_term_meta($new_cat_id->error_data['term_exists'], $term_metakey, $category_id, false);
					}
					continue;
				}
				$cat_count++;
				$terms[] = $new_cat_id;
				$this->imported_categories[$category_id] = $new_cat_id;

				// Store the Joomla category ID
				add_term_meta($new_cat_id, $term_metakey, $category_id, true);
				
				// Hook after inserting the category
				do_action('fgj2wp_post_insert_category', $new_cat_id, $category);
			}
			
			$this->progressbar->increment_current_count($processed_cat_count);
			
			// Update cache
			if ( !empty($terms) ) {
				wp_update_term_count_now($terms, $taxonomy);
				$this->clean_cache($terms, $taxonomy);
			}
			
			return $cat_count;
		}

		/**
		 * Update the categories hierarchy
		 * 
		 * @since 3.23.0
		 * 
		 * @param array $categories Categories
		 * @param string $taxonomy Taxonomy
		 * @param string $language Language code
		 */
		public function update_categories_hierarchy($categories, $taxonomy='category', $language='') {
			foreach ( $categories as $category ) {
				if ( !empty($category['parent_id']) ) {
					$joomla_category_id = $category['id'];
					$joomla_parent_category_id = $category['parent_id'];
					if ( !empty($language) ) {
						$joomla_category_id .= '-' . $language;
						$joomla_parent_category_id .= '-' . $language;
					}
					// Parent category
					if ( isset($this->imported_categories[$joomla_category_id]) && isset($this->imported_categories[$joomla_parent_category_id]) ) {
						$cat_id = $this->imported_categories[$joomla_category_id];
						$parent_cat_id = $this->imported_categories[$joomla_parent_category_id];
						wp_update_term($cat_id, $taxonomy, array('parent' => $parent_cat_id));
					}
				}
			}
		}
		
		/**
		 * Clean the cache
		 * 
		 * @param array $terms Terms
		 * @param string $taxonomy Taxonomy
		 */
		public function clean_cache($terms=array(), $taxonomy='category') {
			delete_option($taxonomy . '_children');
			clean_term_cache($terms, $taxonomy);
		}

		/**
		 * Import posts
		 *
		 * @param bool $test_mode Test mode active: import only one post
		 * @return bool Import successful or not
		 */
		private function import_posts($test_mode = false) {
			$step = $test_mode? 1 : $this->chunks_size; // to limit the results

			$message = __('Importing posts...', 'fg-joomla-to-wordpress');
			if ( defined('WP_CLI') ) {
				$progress_cli = \WP_CLI\Utils\make_progress_bar($message, $this->get_posts_count());
			} else {
				$this->log($message);
			}
			
			// Hook for doing other actions before the import
			do_action('fgj2wp_pre_import_posts');

			do {
				if ( $this->import_stopped() ) {
					break;
				}
				$posts = $this->get_posts($step); // Get the Joomla posts
				$posts_count = count($posts);
				
				if ( is_array($posts) ) {
					foreach ( $posts as $post ) {
						if ( $this->import_post($post) === false ) {
							return false;
						}
						if ( defined('WP_CLI') ) {
							$progress_cli->tick();
						}
					}
				}
				$this->progressbar->increment_current_count($posts_count);
			} while ( !is_null($posts) && ($posts_count > 0) && !$test_mode);

			if ( defined('WP_CLI') ) {
				$progress_cli->finish();
			}
			
			if ( !$this->import_stopped() ) {
				// Hook for doing other actions after the import
				do_action('fgj2wp_post_import_posts');
			}

			return true;
		}

		/**
		 * Import a post
		 * 
		 * @since 3.4.0
		 * 
		 * @param array $post Post data
		 * @param bool $test_mode Test mode active
		 * @return int new post ID | false | WP_Error
		 */
		private function import_post($post, $test_mode = false) {
			// Anti-duplicate
			if ( !$test_mode && !$this->test_antiduplicate ) {
				sleep(2);
				$test_post_id = $this->get_wp_post_id_from_joomla_id($post['id']);
				if ( !empty($test_post_id) ) {
					$this->display_admin_error(__('The import process is still running. Please wait before running it again.', 'fg-joomla-to-wordpress'));
					return false;
				}
				$this->test_antiduplicate = true;
			}

			// Hook for modifying the Joomla post before processing
			$post = apply_filters('fgj2wp_pre_process_post', $post);

			// If the slug is a date, get the title instead
			if ( preg_match('/^\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2}$/', $post['alias']) ) {
				$slug = $post['title'];
			} else {
				$slug = $post['alias'];
			}

			// Date
			$post_date = ($post['date'] != '0000-00-00 00:00:00')? $post['date']: $post['modified'];
			if ( $post_date != '0000-00-00 00:00:00' ) {
				$post_date = get_date_from_gmt($post_date);
			}

			// Medias
			$post_media = array();
			$featured_image_id = '';
			if ( !$this->plugin_options['skip_media'] ) {
				// Featured image
				list($featured_image_id, $post) = $this->get_and_process_featured_image($post);
				
				// Import media
				if ( !$this->plugin_options['only_featured_image'] ) {
					$post_media = $this->import_media_from_content($post['introtext'] . $post['fulltext'], $post_date);
				}
			}

			// Categories IDs
			$categories = array($post['catid']);
			// Hook for modifying the post categories
			$categories = apply_filters('fgj2wp_post_categories', $categories, $post);
			$categories_ids = array();
			foreach ( $categories as $catid ) {
				if ( array_key_exists($catid, $this->imported_categories) ) {
					$categories_ids[] = $this->imported_categories[$catid];
				}
			}
			if ( count($categories_ids) == 0 ) {
				$categories_ids[] = 1; // default category
			}

			// Define excerpt and post content
			list($excerpt, $content) = $this->set_excerpt_content($post);

			// Process content
			$excerpt = $this->process_content($excerpt, $post_media);
			$content = $this->process_content($content, $post_media);

			// Status
			switch ( $post['state'] ) {
				case 1: // published post
					$status = 'publish';
					break;
				case -1: // archived post
				case 2: // archived post in Joomla 2.5
					$status = ($this->plugin_options['archived_posts'] == 'published')? 'publish' : 'draft';
					break;
				default:
					$status = 'draft';
			}

			// Tags
			$tags = array();
			if ( $this->plugin_options['meta_keywords_in_tags'] && !empty($post['metakey']) ) {
				$tags = explode(',', $post['metakey']);
				$this->import_tags($tags, 'post_tag');
				$this->imported_tags = array_merge($this->imported_tags, $tags);
			}

			// Insert the post
			$new_post = array(
				'post_category'		=> $categories_ids,
				'post_content'		=> $content,
				'post_date'			=> $post_date,
				'post_excerpt'		=> $excerpt,
				'post_status'		=> $status,
				'post_title'		=> $post['title'],
				'post_name'			=> $slug,
				'post_type'			=> $this->post_type,
				'tags_input'		=> $tags,
				'menu_order'        => $post['ordering'],
			);

			// Hook for modifying the WordPress post just before the insert
			$new_post = apply_filters('fgj2wp_pre_insert_post', $new_post, $post);

			$new_post_id = wp_insert_post($new_post, true);
			
			// Increment the Joomla last imported post ID
			update_option('fgj2wp_last_article_id', $post['id']);
			
			if ( is_wp_error($new_post_id) ) {
				$this->display_admin_error(sprintf(__('Article #%d:', 'fg-joomla-to-wordpress'), $post['id']) . ' ' . $new_post_id->get_error_message() . ' ' . $new_post_id->get_error_data());
			} else {
				// Add links between the post and its medias
				if ( !empty($featured_image_id) ) {
					$post_media[] = $featured_image_id;
				}
				$this->add_post_media($new_post_id, $new_post, $post_media, false);
				
				// Set the featured image
				if ( !empty($featured_image_id) ) {
					set_post_thumbnail($new_post_id, $featured_image_id);
				}
				
				// Sticky post
				if ( isset($post['featured']) && ($post['featured'] == 1) ) {
					$sticky_posts = get_option('sticky_posts', array());
					$sticky_posts[] = $new_post_id;
					update_option('sticky_posts', $sticky_posts);
				}

				// Add the Joomla ID as a post meta in order to modify links after
				add_post_meta($new_post_id, '_fgj2wp_old_id', $post['id'], true);

				$this->posts_count++;

				// Hook for doing other actions after inserting the post
				do_action('fgj2wp_post_insert_post', $new_post_id, $post, $new_post['post_type']);
			}

			return $new_post_id;
		}

		/**
		 * Import tags
		 * 
		 * @since 3.17.2
		 * 
		 * @param array $tags Tags
		 * @param string $taxonomy Taxonomy (post_tag | product_tag)
		 * @return int Number of tags imported
		 */
		public function import_tags($tags, $taxonomy) {
			$imported_tags_count = 0;
			foreach ( $tags as $tag ) {
				if ( is_array($tag) ) {
					// Tag with ID, title, description and alias
					$tag_id = $tag['id'];
					$tag_title = $tag['title'];
					$tag_description = $tag['description'];
					$tag_slug = $tag['alias'];
				} else {
					// Only the tag title
					$tag_id = $tag;
					$tag_title = $tag;
					$tag_description = '';
					$tag_slug = $tag;
				}
				$new_term = wp_insert_term($tag_title, $taxonomy, array(
					'description' => $tag_description,
					'slug' => $tag_slug,
				));
				if ( !is_wp_error($new_term) ) {
					$imported_tags_count++;
					$this->tags_count++;
					add_term_meta($new_term['term_id'], '_fgj2wp_old_tag_id', $tag_id, true);
				}
			}
			return $imported_tags_count;
		}
		
		/**
		 * Determine the featured image and modify the post if needed
		 * 
		 * @since 3.4.0
		 * 
		 * @param array $post Post data
		 * @return array [Featured image ID, Post]
		 */
		public function get_and_process_featured_image($post) {
			$featured_image = '';
			$featured_image_id = 0;
			if ( $this->plugin_options['featured_image'] != 'none' ) {
				
				list($featured_image, $post) = apply_filters('fgj2wp_pre_import_media', array($featured_image, $post));
				
				if ( empty($featured_image) ) {
					$featured_image = $this->get_first_image_from($post['introtext']);
					if ( empty($featured_image) ) {
						$featured_image = $this->get_first_image_from($post['fulltext']);
					}
				}

				// Remove the featured image from the content
				if ( !empty($featured_image) && $this->plugin_options['remove_first_image'] ) {
					$post['introtext'] = $this->remove_image_from_content($featured_image, $post['introtext']);
					$post['fulltext'] = $this->remove_image_from_content($featured_image, $post['fulltext']);
				}

				// Import the featured image
				if ( !empty($featured_image) ) {
					$media = $this->import_media_from_content($featured_image, $post['date']);
					if ( count($media) > 0 ) {
						$featured_image_id = array_shift($media);
					}
				}
			}
			return array($featured_image_id, $post);
		}
		
		/**
		 * Get the first image from a content
		 * 
		 * @since 3.4.0
		 * 
		 * @param string $content
		 * @return string Featured image tag
		 */
		private function get_first_image_from($content) {
			$matches = array();
			$featured_image = '';
			
			$img_pattern = '#(<img .*?>)#i';
			if ( preg_match($img_pattern, $content, $matches) ) {
				$featured_image = $matches[1];
			}
			return $featured_image;
		}
		
		/**
		 * Remove the image from the content
		 * 
		 * @since 3.5.1
		 * 
		 * @param string $image Image to remove
		 * @param string $content Content
		 * @return string Content
		 */
		private function remove_image_from_content($image, $content) {
			$matches = array();
			$image_src = '';
			if ( preg_match('#src=["\'](.*?)["\']#', $image, $matches) ) {
				$image_src = $matches[1];
			}
			if ( !empty($image_src) ) {
				$img_pattern = '#(<img.*?src=["\']' . preg_quote($image_src) . '["\'].*?>)#i';
				$content = preg_replace($img_pattern, '', $content, 1);
			}
			return $content;
		}
		
		/**
		 * Stop the import
		 * 
		 */
		public function stop_import() {
			update_option('fgj2wp_stop_import', true);
		}
		
		/**
		 * Test if the import needs to stop
		 * 
		 * @return boolean Import needs to stop or not
		 */
		public function import_stopped() {
			return get_option('fgj2wp_stop_import');
		}
		
		/**
		 * Get Joomla sections
		 *
		 * @param int $limit Number of categories max
		 * @return array of Sections
		 */
		protected function get_sections($limit=1000) {
			$sections = array();

			if ( $this->table_exists('sections') ) {
				$prefix = $this->plugin_options['prefix'];
				$last_section_id = (int)get_option('fgj2wp_last_section_id'); // to restore the import where it left
				$sql = "
					SELECT CONCAT('s', s.id) AS id, s.title, IF(s.alias <> '', s.alias, s.name) AS name, s.description, 0 AS parent_id, '' AS date
					FROM {$prefix}sections s
					WHERE s.id > '$last_section_id'
					ORDER BY s.id
					LIMIT $limit
				";
				$sql = apply_filters('fgj2wp_get_sections_sql', $sql);
				$sections = $this->joomla_query($sql);
			}
			return $sections;
		}

		/**
		 * Get Joomla categories
		 *
		 * @param int $chunks Number of categories max (0: get all the categories)
		 * @return array of Categories
		 */
		protected function get_categories($chunks=1000) {
			$categories = array();

			$prefix = $this->plugin_options['prefix'];
			$last_category_id = (int)get_option('fgj2wp_last_category_id'); // to restore the import where it left
			
			// Hooks for adding extra cols and extra joins
			$extra_cols = apply_filters('fgj2wp_get_categories_add_extra_cols', '');
			$extra_joins = apply_filters('fgj2wp_get_categories_add_extra_joins', '');
			$extra_criteria = $this->get_categories_extra_criteria();
			
			if ( $chunks == 0 ) {
				// Get all the categories
				$id_criteria = '';
				if ( $this->column_exists('categories', 'lft') ) {
					$order = 'ORDER BY c.lft';
				} else {
					$order = 'ORDER BY c.parent_id, c.id';
				}
				$limit = '';
			} else {
				$id_criteria = "AND c.id > '$last_category_id'";
				$order = 'ORDER BY c.id';
				$limit = "LIMIT $chunks";
			}
			if ( version_compare($this->joomla_version, '1.5', '<=') ) {
				$sql = "
					SELECT c.id, c.title, IF(c.alias <> '', c.alias, c.name) AS name, c.description, CONCAT('s', s.id) AS parent_id, '' AS date
					$extra_cols
					FROM {$prefix}categories c
					INNER JOIN {$prefix}sections AS s ON s.id = c.section
					$extra_joins
					WHERE c.published >= 0 -- Don't import the deleted categories
					$id_criteria
					$extra_criteria
					$order
					$limit
				";
			} else {
				$sql = "
					SELECT c.id, c.title, c.alias AS name, c.description, c.parent_id, c.created_time AS date
					$extra_cols
					FROM {$prefix}categories c
					$extra_joins
					WHERE c.extension = 'com_content'
					AND c.published >= 0 -- Don't import the deleted categories
					$id_criteria
					$extra_criteria
					$order
					$limit
				";
			}
			$sql = apply_filters('fgj2wp_get_categories_sql', $sql, $prefix);
			$categories = $this->joomla_query($sql);
			return $categories;
		}
		
		/**
		 * Filter the categories by their status
		 * 
		 * @since 3.77.0
		 * 
		 * @return string SQL
		 */
		private function get_categories_extra_criteria() {
			$extra_criteria = '';
			// Archived categories
			if ( $this->plugin_options['archived_categories'] == 'not_imported' ) {
				$extra_criteria .= ' AND c.published != 2';
			}
			// Unpublished categories
			if ( $this->plugin_options['unpublished_categories'] == 'not_imported' ) {
				$extra_criteria .= ' AND c.published != 0';
			}
			return $extra_criteria;
		}
		
		/**
		 * Get the list of skipped categories (deleted, archived, unpublished)
		 * 
		 * @since 3.77.0
		 * 
		 * @return array List of skipped categories IDs
		 */
		private function get_skipped_categories() {
			$categories = array();
			
			$prefix = $this->plugin_options['prefix'];
			$extra_criteria = '';
			// Archived categories
			if ( $this->plugin_options['archived_categories'] == 'not_imported' ) {
				$extra_criteria .= ' OR c.published = 2';
			}
			// Unpublished categories
			if ( $this->plugin_options['unpublished_categories'] == 'not_imported' ) {
				$extra_criteria .= ' OR c.published = 0';
			}
			$sql = "
				SELECT c.id
				FROM {$prefix}categories c
				WHERE c.published < 0 -- Deleted categories
				$extra_criteria
			";
			$result = $this->joomla_query($sql);
			foreach ( $result as $row ) {
				$categories[] = $row['id'];
			}
			$categories = apply_filters('fgj2wp_get_skipped_categories', $categories);
				
			return $categories;
		}

		/**
		 * Get Joomla component categories
		 *
		 * @param string $component Component name
		 * @param string $last_category_metakey Last category meta key
		 * @return array of Categories
		 */
		public function get_component_categories($component, $last_category_metakey) {
			$categories = array();

			$prefix = $this->plugin_options['prefix'];
			if ( version_compare($this->joomla_version, '1.5', '<=') ) {
				$name_field = "IF(c.alias <> '', c.alias, c.name)";
				$extension_field = 'c.section';
				$date_field = "'' AS date";
			} else {
				$name_field = 'c.alias';
				$extension_field = 'c.extension';
				$date_field = 'c.created_time AS date';
			}
			$sql = "
				SELECT c.id, c.title, $name_field AS name, c.description, c.parent_id, $date_field
				FROM {$prefix}categories c
				WHERE $extension_field = '$component'
				AND c.id > '$last_category_metakey'
				ORDER BY c.id
			";
			$sql = apply_filters('fgj2wp_get_categories_sql', $sql, $prefix);
			$categories = $this->joomla_query($sql);
			return $categories;
		}

		/**
		 * Get Joomla posts
		 *
		 * @param int $limit Number of posts max
		 * @return array of Posts
		 */
		protected function get_posts($limit=1000) {
			$posts = array();

			$last_joomla_id = (int)get_option('fgj2wp_last_article_id'); // to restore the import where it left
			$prefix = $this->plugin_options['prefix'];

			$extra_criteria = '';
			if ( $this->plugin_options['archived_posts'] == 'not_imported' ) {
				$extra_criteria .= "AND p.state != 2\n";
			}
			if ( !empty($this->skipped_categories) ) {
				$extra_criteria .= "AND p.catid NOT IN(" . implode(',', $this->skipped_categories) . ")\n";
			}
			
			$extra_cols = '';
			if ( $this->column_exists('content', 'featured') ) {
				$extra_cols .= ', p.featured';
			}
			
			// Hooks for adding extra cols and extra joins
			$extra_cols = apply_filters('fgj2wp_get_posts_add_extra_cols', $extra_cols);
			$extra_joins = apply_filters('fgj2wp_get_posts_add_extra_joins', '');

			$sql = "
				SELECT DISTINCT p.id, 'content' AS type, p.title, p.alias, p.introtext, p.`fulltext`, p.state, p.catid, p.modified, p.created AS `date`, p.attribs, p.metakey, p.metadesc, p.access, p.ordering
				$extra_cols
				FROM {$prefix}content p
				$extra_joins
				WHERE p.state >= -1 -- don't get the trash
				$extra_criteria
				AND p.id > '$last_joomla_id'
				ORDER BY p.id
				LIMIT $limit
			";
			$sql = apply_filters('fgj2wp_get_posts_sql', $sql, $prefix, $extra_cols, $extra_joins, $last_joomla_id, $limit);
			$posts = $this->joomla_query($sql);
			return $posts;
		}

		/**
		 * Return the excerpt and the content of a post
		 *
		 * @param array $post Post data
		 * @return array ($excerpt, $content)
		 */
		public function set_excerpt_content($post) {
			$excerpt = '';
			$content = '';

			// Attribs
			$post_attribs = $this->convert_post_attribs_to_array(array_key_exists('attribs', $post)? $post['attribs']: '');

			if ( empty($post['introtext']) ) {
				$content = isset($post['fulltext'])? $post['fulltext'] : '';
			} elseif ( empty($post['fulltext']) ) {
				// Posts without a "Read more" link
				$content = $post['introtext'];
			} else {
				// Posts with a "Read more" link
				$show_intro = (is_array($post_attribs) && array_key_exists('show_intro', $post_attribs))? $post_attribs['show_intro'] : '';
				if ( (($this->plugin_options['introtext'] == 'in_excerpt') && ($show_intro !== '1'))
					|| (($this->plugin_options['introtext'] == 'in_excerpt_and_content') && ($show_intro == '0')) ) {
					// Introtext imported in excerpt
					$excerpt = $post['introtext'];
					$content = $post['fulltext'];
				} elseif ( (($this->plugin_options['introtext'] == 'in_excerpt_and_content') && ($show_intro !== '0'))
					|| (($this->plugin_options['introtext'] == 'in_excerpt') && ($show_intro == '1')) ) {
					// Introtext imported in excerpt and in content
					$excerpt = $post['introtext'];
					$content = $post['introtext'] . "\n" . $post['fulltext'];
				} else {
					if ( $show_intro !== '0' ) {
						// Introtext imported in post content with a "Read more" tag
						$content = $post['introtext'] . "\n<!--more-->\n";
					}
					$content .= $post['fulltext'];
				}
			}
			return array($excerpt, $content);
		}

		/**
		 * Return the post attribs in an array
		 *
		 * @param string $attribs Post attribs as a string
		 * @return array Post attribs as an array
		 */
		public function convert_post_attribs_to_array($attribs) {
			$attribs = trim($attribs);
			if ( (substr($attribs, 0, 1) != '{') && (substr($attribs, -1, 1) != '}') ) {
				$post_attribs = parse_ini_string($attribs, false, INI_SCANNER_RAW);
			} else {
				$post_attribs = json_decode($attribs, true);
			}
			return $post_attribs;
		}

		/**
		 * Import post medias from content
		 *
		 * @param string $content post content
		 * @param date $post_date Post date (for storing media)
		 * @param array $options Options
		 * @return array Medias imported
		 */
		public function import_media_from_content($content, $post_date, $options=array()) {
			$media = array();
			$matches = array();
			$alt_matches = array();
			$title_matches = array();
			
			if ( preg_match_all('#<(img|a|iframe|object)(.*?) (src|href|data)="(.*?)"(.*?)>#s', $content, $matches, PREG_SET_ORDER) > 0 ) {
				if ( is_array($matches) ) {
					foreach ($matches as $match ) {
						$filename = $match[4];
						$other_attributes = $match[2] . $match[5];
						// Image Alt
						$image_alt = '';
						if (preg_match('#alt="(.*?)"#', $other_attributes, $alt_matches) ) {
							$image_alt = wp_strip_all_tags(stripslashes($alt_matches[1]), true);
						}
						// Image caption
						$image_caption = '';
						if (preg_match('#title="(.*?)"#', $other_attributes, $title_matches) ) {
							$image_caption = $title_matches[1];
						}
						$attachment_id = $this->import_media($image_alt, $filename, $post_date, $options, $image_caption);
						if ( $attachment_id ) {
							$media[$filename] = $attachment_id;
						}
					}
				}
			}
			return $media;
		}
		
		/**
		 * Import a media
		 *
		 * @param string $name Image name
		 * @param string $filename Image URL
		 * @param date $date Date
		 * @param array $options Options
		 * @param string $image_caption Image caption
		 * @param string $image_description Image description
		 * @return int attachment ID or false
		 */
		public function import_media($name, $filename, $date, $options=array(), $image_caption='', $image_description='') {
			if ( $date == '0000-00-00 00:00:00' ) {
				$date = date('Y-m-d H:i:s');
			}
			$import_external = ($this->plugin_options['import_external'] == 1) || (isset($options['force_external']) && $options['force_external'] );
			
			$filename = trim($filename); // for filenames with extra spaces at the beginning or at the end
			$filename = preg_replace('/[?#].*/', '', $filename); // Remove the attributes and anchors
			$filename = str_replace('\\', '/', $filename); // Replace the backslash by a slash
			$filename = rawurldecode($filename); // for filenames with spaces or accents
			$filename = html_entity_decode($filename); // for filenames with HTML entities
			// Filenames starting with //
			if ( preg_match('#^//#', $filename) ) {
				$filename = 'http:' . $filename;
			}
			$filename = apply_filters('fgj2wp_import_media_filename', $filename, $name);
			
			$filetype = wp_check_filetype($filename);
			if ( empty($filetype['type']) || ($filetype['type'] == 'text/html') ) { // Unrecognized file type
				return false;
			}

			// Upload the file from the Joomla web site to WordPress upload dir
			if ( preg_match('/^http/', $filename) ) {
				if ( $import_external || // External file 
					preg_match('#^' . $this->plugin_options['url'] . '#', $filename) // Local file
				) {
					$old_filename = $filename;
				} else {
					return false;
				}
			} else {
				if ( strpos($filename, '/') === 0 ) { // Avoid a double slash
					$old_filename = untrailingslashit($this->plugin_options['url']) . $filename;
				} else {
					$old_filename = trailingslashit($this->plugin_options['url']) . $filename;
				}
			}
			
			// Don't re-import the already imported media
			if ( array_key_exists($old_filename, $this->imported_media) ) {
				return $this->imported_media[$old_filename];
			}
			
			// Get the upload path
			if ( isset($options['woocommerce']) ) {
				// WooCommerce secure directory
				$upload_path = $this->wc_upload_dir($filename, $date);
			} else {
				// Standard uploads directory
				$upload_path = $this->upload_dir($filename, $date, get_option('uploads_use_yearmonth_folders'));
			}
			
			// Make sure we have an uploads directory.
			if ( !wp_mkdir_p($upload_path) ) {
				$this->display_admin_error(sprintf(__("Unable to create directory %s", 'fg-joomla-to-wordpress'), $upload_path));
				return false;
			}
			
			$new_filename = $filename;
			
			// Remove the accents (useful on Windows system)
			if ( $this->plugin_options['remove_accents'] ) {
				$new_filename = remove_accents($new_filename);
			}
			
			if ( $this->plugin_options['import_duplicates'] == 1 ) {
				// Images with duplicate names
				$new_filename = preg_replace('#^https?://#', '', $new_filename);
				$crc = hash("crc32b", $new_filename);
				$short_crc = substr($crc, 0, 3); // Keep only the 3 first characters of the CRC (should be enough)
				$new_filename = preg_replace('/(.*)\.(.+?)$/', "$1-" . $short_crc . ".$2", $new_filename); // Insert the CRC before the file extension
			}

			$basename = basename($new_filename);
			$basename = sanitize_file_name($basename);
			$new_full_filename = $upload_path . '/' . $basename;

			// GUID
			$upload_dir = wp_upload_dir();
			$guid = substr(str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $new_full_filename), 0, 255);
			$attachment_id = $this->get_post_id_from_guid($guid);
			
			if ( empty($attachment_id) ) {
				if ( !$this->download_manager->copy($old_filename, $new_full_filename) ) {
					$error = error_get_last();
					$error_message = isset($error['message'])? $error['message'] : '';
					$this->display_admin_error("Can't copy $old_filename to $new_full_filename : $error_message");
					return false;
				}

				$post_title = !empty($name)? $name : preg_replace('/\.[^.]+$/', '', $basename);

				// Image Alt
				$image_alt = '';
				if ( !empty($name) ) {
					$image_alt = wp_strip_all_tags(stripslashes($name), true);
				}

				$attachment_id = $this->insert_attachment($post_title, $basename, $new_full_filename, $guid, $date, $filetype['type'], $image_alt, $image_caption, $image_description);
				if ( $attachment_id ) {
					update_post_meta($attachment_id, '_fgj2wp_old_file', $old_filename);
					$this->imported_media[$old_filename] = $attachment_id;
					$this->media_count++;
				}
			}
			return $attachment_id;
		}
		
		/**
		 * Determine the media uploads directory
		 * 
		 * @since 2.13.0
		 * 
		 * @param string $filename Filename
		 * @param date $date Date
		 * @param bool $use_yearmonth_folders Use the Year/Month tree folder
		 * @return string Upload directory
		 */
		public function upload_dir($filename, $date, $use_yearmonth_folders=true) {
			$upload_dir = wp_upload_dir(date('Y/m', strtotime($date)));
			if ( $use_yearmonth_folders ) {
				$upload_path = $upload_dir['path'];
			} else {
				$short_filename = preg_replace('#.*images/(stories/)?#', '/', $filename);
				if ( strpos($short_filename, '/') != 0 ) {
					$short_filename = '/' . $short_filename; // Add a slash before the filename
				}
				$upload_path = $upload_dir['basedir'] . untrailingslashit(dirname($short_filename));
			}
			return $upload_path;
		}
		
		/**
		 * Get the WooCommerce uploads dir
		 * 
		 * @since 3.43.0
		 * 
		 * @param string $filename Filename
		 * @param date $date Date
		 * @return string Upload directory
		 */
		private function wc_upload_dir($filename, $date) {
			$wp_upload_dir = wp_upload_dir();
			$upload_path = $wp_upload_dir['basedir'];
			$upload_dir = $this->upload_dir($filename, $date);
			$upload_dir = str_replace($upload_path, $upload_path . '/woocommerce_uploads', $upload_dir);
			return $upload_dir;
		}
		
		/**
		 * Save the attachment and generates its metadata
		 * 
		 * @param string $attachment_title Attachment name
		 * @param string $basename Original attachment filename
		 * @param string $new_full_filename New attachment filename with path
		 * @param string $guid GUID
		 * @param date $date Date
		 * @param string $filetype File type
		 * @param string $image_alt Image alternative text
		 * @param string $image_caption Image caption
		 * @param string $image_description Image description
		 * @return int|bool Attachment ID or false
		 */
		public function insert_attachment($attachment_title, $basename, $new_full_filename, $guid, $date, $filetype, $image_alt='', $image_caption='', $image_description='') {
			$post_name = 'attachment-' . sanitize_title($attachment_title); // Prefix the post name to avoid wrong redirect to a post with the same name
			
			// If the attachment does not exist yet, insert it in the database
			$attachment_id = false;
			$attachment = $this->get_attachment_from_name($post_name);
			if ( $attachment ) {
				$attached_file = basename(get_attached_file($attachment->ID));
				if ( $attached_file == $basename ) { // Check if the filename is the same (in case of the legend is not unique)
					$attachment_id = $attachment->ID;
				}
			}
			if ( !$attachment_id ) {
				// Insert the attachment in the database
				$attachment_data = array(
					'guid'				=> $guid, 
					'post_date'			=> $date,
					'post_mime_type'	=> $filetype,
					'post_name'			=> $post_name,
					'post_title'		=> $attachment_title,
					'post_status'		=> 'inherit',
					'post_content'		=> $image_description? $image_description : '',
					'post_excerpt'		=> $image_caption? $image_caption : '',
				);
				$attachment_id = wp_insert_attachment($attachment_data, $new_full_filename);
				
				if ( $attachment_id ) {
					// Resize the image and add its meta data
					if ( preg_match('/(image|audio|video)/', $filetype) ) { // Image, audio or video
						if ( !$this->plugin_options['skip_thumbnails'] ) {
							// you must first include the image.php file
							// for the function wp_generate_attachment_metadata() to work
							require_once(ABSPATH . 'wp-admin/includes/image.php');
							$attach_data = wp_generate_attachment_metadata( $attachment_id, $new_full_filename );
							wp_update_attachment_metadata($attachment_id, $attach_data);
						}
					}
				}
			}

			if ( $attachment_id ) {
				// Image Alt
				if ( !empty($image_alt) ) {
					update_post_meta($attachment_id, '_wp_attachment_image_alt', addslashes($image_alt)); // update_post_meta expects slashed
				}
			}
			return $attachment_id;
		}
		
		/**
		 * Check if the attachment exists in the database
		 *
		 * @param string $name
		 * @return object Post
		 */
		private function get_attachment_from_name($name) {
			$name = preg_replace('/\.[^.]+$/', '', basename($name));
			$r = array(
				'name'			=> $name,
				'post_type'		=> 'attachment',
				'numberposts'	=> 1,
			);
			$posts_array = get_posts($r);
			if ( is_array($posts_array) && (count($posts_array) > 0) ) {
				return $posts_array[0];
			}
			else {
				return false;
			}
		}

		/**
		 * Process the category description (import embedded images)
		 * 
		 * @since 3.35.0
		 * 
		 * @param string $description Category description
		 * @param date $date Category date
		 * @return string Category description
		 */
		public function process_category_description($description, $date) {
			// Import medias
			if ( !$this->plugin_options['skip_media'] ) {
				$media = $this->import_media_from_content($description, $date);
				$description = $this->process_content_media_links($description, $media);
			}
			return $description;
		}
		
		/**
		 * Process the post content
		 *
		 * @param string $content Post content
		 * @param array $post_media Post medias
		 * @return string Processed post content
		 */
		public function process_content($content, $post_media) {

			if ( empty($content) ) {
				$content = ''; // to avoid NULL
			} else {
				// Replace page breaks
				$content = preg_replace("#<hr([^>]*?)class=\"system-pagebreak\"(.*?)/>#", "<!--nextpage-->", $content);

				// Replace media URLs with the new URLs
				$content = $this->process_content_media_links($content, $post_media);

				// Replace audio and video links
				$content = $this->process_audio_video_links($content);
				
				// Fix the colon in the links
				$content = $this->fix_colon_in_links($content);

				// For importing backslashes
				$content = addslashes($content);
			}
			$content = apply_filters('fgj2wp_process_content', $content, $post_media);

			return $content;
		}

		/**
		 * Replace media URLs with the new URLs
		 *
		 * @param string $content Post content
		 * @param array $post_media Post medias
		 * @return string Processed post content
		 */
		private function process_content_media_links($content, $post_media) {
			$matches = array();
			$matches_caption = array();

			if ( is_array($post_media) ) {

				// Get the attachments attributes
				$attachments_found = false;
				$medias = array();
				foreach ( $post_media as $old_filename => $attachment_id ) {
					$media = array();
					$media['attachment_id'] = $attachment_id;
					$media['url_old_filename'] = urlencode($old_filename); // for filenames with spaces or accents
					if ( preg_match('/image/', get_post_mime_type($attachment_id)) ) {
						// Image
						$image_src = wp_get_attachment_image_src($attachment_id, 'full');
						$media['new_url'] = $image_src[0];
						$media['width'] = $image_src[1];
						$media['height'] = $image_src[2];
					} else {
						// Other media
						$media['new_url'] = wp_get_attachment_url($attachment_id);
					}
					$medias[$old_filename] = $media;
					$attachments_found = true;
				}
				if ( $attachments_found ) {

					// Remove the links from the content
					$this->post_link_count = 0;
					$this->post_link = array();
					$content = preg_replace_callback('#<(a) (.*?)(href)?=(.*?)</a>#i', array($this, 'remove_links'), $content);
					$content = preg_replace_callback('#<(img) (.*?)(src)=(.*?)>#i', array($this, 'remove_links'), $content);
					$content = preg_replace_callback('#<(iframe) (.*?)(src)=(.*?)>#i', array($this, 'remove_links'), $content);
					$content = preg_replace_callback('#<(object) (.*?)(data)=(.*?)>#i', array($this, 'remove_links'), $content);

					// Process the stored medias links
					foreach ($this->post_link as &$link) {
						$new_link = $link['old_link'];
						$alignment = '';
						if ( preg_match('/(align="|float: )(left|right)/', $new_link, $matches) ) {
							$alignment = 'align' . $matches[2];
						}
						if ( preg_match('/width="(\d+)"/', $new_link, $matches) ) {
							$width = $matches[1];
						} else {
							$width = '';
						}
						if ( preg_match_all('#(img|a|iframe|object).*?(src|href|data)="(.*?)"#i', $new_link, $matches, PREG_SET_ORDER) ) {
							$caption = '';
							foreach ( $matches as $match ) {
								$link_type = $match[1];
								$old_filename = $match[3];
								if ( array_key_exists($old_filename, $medias) ) {
									$media = $medias[$old_filename];
									if ( array_key_exists('new_url', $media) ) {
										if ( (strpos($new_link, $old_filename) > 0) || (strpos($new_link, $media['url_old_filename']) > 0) ) {
											// URL encode the filename
											$new_filename = basename($media['new_url']);
											$encoded_new_filename = rawurlencode($new_filename);
											$new_url = str_replace($new_filename, $encoded_new_filename, $media['new_url']);
											$new_link = preg_replace('#(' . preg_quote($old_filename) . '|' . preg_quote($media['url_old_filename']) . ')#', $new_url, $new_link);

											if ( $link_type == 'img' ) { // images only
												// Define the width and the height of the image if it isn't defined yet
												if ((strpos($new_link, 'width=') === false) && (strpos($new_link, 'height=') === false)) {
													$width_assertion = isset($media['width']) && !empty($media['width'])? ' width="' . $media['width'] . '"' : '';
													$height_assertion = isset($media['height']) && !empty($media['height'])? ' height="' . $media['height'] . '"' : '';
												} else {
													$width_assertion = '';
													$height_assertion = '';
												}

												// Caption shortcode
												if ( preg_match('/class=".*caption.*?"/', $link['old_link']) ) {
													if ( preg_match('/title="(.*?)"/', $link['old_link'], $matches_caption) ) {
														$caption_value = str_replace('%', '%%', $matches_caption[1]);
														$align_value = ($alignment != '')? $alignment : 'alignnone';
														$caption = '[caption id="attachment_' . $media['attachment_id'] . '" align="' . $align_value . '" width="' . $width . '"]%s' . $caption_value . '[/caption]';
													}
												}

												$align_class = ($alignment != '')? $alignment . ' ' : '';
												$new_link = preg_replace('#<img(.*?)( class="(.*?)")?(.*) />#', "<img$1 class=\"$3 " . $align_class . 'size-full wp-image-' . $media['attachment_id'] . "\"$4" . $width_assertion . $height_assertion . ' />', $new_link);
												$new_link = apply_filters('fgj2wp_process_content_media_links_new_link', $new_link, $link['old_link']);
											}
										}
									}
								}
							}

							// Add the caption
							if ( $caption != '' ) {
								$new_link = sprintf($caption, $new_link);
							}
						}
						$link['new_link'] = $new_link;
					}

					// Reinsert the converted medias links
					$content = preg_replace_callback('#__fg_link_(\d+)__#', array($this, 'restore_links'), $content);
				}
			}
			return $content;
		}

		/**
		 * Remove all the links from the content and replace them with a specific tag
		 * 
		 * @param array $matches Result of the preg_match
		 * @return string Replacement
		 */
		private function remove_links($matches) {
			$this->post_link[] = array('old_link' => $matches[0]);
			return '__fg_link_' . $this->post_link_count++ . '__';
		}

		/**
		 * Restore the links in the content and replace them with the new calculated link
		 * 
		 * @param array $matches Result of the preg_match
		 * @return string Replacement
		 */
		private function restore_links($matches) {
			$link = $this->post_link[$matches[1]];
			$new_link = array_key_exists('new_link', $link)? $link['new_link'] : $link['old_link'];
			return $new_link;
		}

		/**
		 * Add a link between a media and a post (parent id + thumbnail)
		 *
		 * @param int $post_id Post ID
		 * @param array $post_data Post data
		 * @param array $post_media Post medias IDs
		 * @param boolean $set_featured_image Set the featured image?
		 */
		public function add_post_media($post_id, $post_data, $post_media, $set_featured_image=true) {
			$thumbnail_is_set = false;
			if ( is_array($post_media) ) {
				foreach ( $post_media as $attachment_id ) {
					$attachment = get_post($attachment_id);
					if ( !empty($attachment) ) {
						$attachment->post_parent = $post_id; // Attach the post to the media
						$attachment->post_date = $post_data['post_date'] ;// Define the media's date
						wp_update_post($attachment);

						// Set the featured image. If not defined, it is the first image of the content.
						if ( $set_featured_image && !$thumbnail_is_set ) {
							set_post_thumbnail($post_id, $attachment_id);
							$thumbnail_is_set = true;
						}
					}
				}
			}
		}

		/**
		 * Modify the audio and video links
		 *
		 * @param string $content Content
		 * @return string Content
		 */
		private function process_audio_video_links($content) {
			if ( strpos($content, '{"video"') !== false ) {
				$content = preg_replace('/(<p>)?{"video":"(.*?)".*?}(<\/p>)?/', "$2", $content);
			}
			if ( strpos($content, '{audio}') !== false ) {
				$content = preg_replace('#{audio}(.*?){/audio}#', "$1", $content);
			}
			return $content;
		}
		
		/**
		 * Modify the internal links of all posts and categories
		 *
		 */
		private function modify_links() {
			$message = __('Modifying internal links...', 'fg-joomla-to-wordpress');
			
			// Allow HTML in term descriptions
			foreach ( array('pre_term_description') as $filter ) {
				remove_filter($filter, 'wp_filter_kses');
			}
			
			if ( defined('WP_CLI') ) {
				$posts_count = $this->count_posts('post') + $this->count_posts('page');
				$progress_cli = \WP_CLI\Utils\make_progress_bar($message, $posts_count);
			} else {
				$this->log($message);
				$progress_cli = null;
			}
			
			// Hook for doing other actions before modifying the links
			do_action('fgj2wp_pre_modify_links');
			
			$this->modify_links_in_posts($progress_cli);
			$this->modify_links_in_categories($progress_cli);
			
			if ( defined('WP_CLI') ) {
				$progress_cli->finish();
			}
			
			// Hook for doing other actions after modifying the links
			do_action('fgj2wp_post_modify_links');
		}

		/**
		 * Modify the internal links in all posts
		 *
		 * @since 3.84.0
		 * 
		 * @param $progress_cli Progress CLI object
		 */
		private function modify_links_in_posts($progress_cli) {
			$step = 1000; // to limit the results
			$offset = 0;

			do {
				$args = array(
					'numberposts'	=> $step,
					'offset'		=> $offset,
					'orderby'		=> 'ID',
					'order'			=> 'ASC',
					'post_type'		=> 'any',
					'post_status'	=> 'any',
				);
				$posts = get_posts($args);
				foreach ( $posts as $post ) {
					$current_links_count = $this->links_count;
					$post = apply_filters('fgj2wp_post_get_post', $post); // Used to translate the links
					
					// Modify the links in the content
					$content = $this->modify_links_in_string($post->post_content);
					
					if ( $this->links_count != $current_links_count ) { // Some links were modified
						// Update the post
						wp_update_post(array(
							'ID'			=> $post->ID,
							'post_content'	=> $content,
						));
						$post->post_content = $content;
					}
					
					if ( defined('WP_CLI') ) {
						$progress_cli->tick();
					}
				}
				$offset += $step;
			} while ( !is_null($posts) && (count($posts) > 0) );
		}

		/**
		 * Modify the internal links in all categories
		 *
		 * @since 3.84.0
		 * 
		 * @param $progress_cli Progress CLI object
		 */
		private function modify_links_in_categories($progress_cli) {
			$taxonomies = get_taxonomies();
			$args = array(
				'taxonomy' => $taxonomies,
				'hide_empty' => false,
			);
			$terms = get_terms($args);
			foreach ( $terms as $term ) {
				$current_links_count = $this->links_count;

				// Modify the links in the content
				$content = $this->modify_links_in_string($term->description);

				if ( $this->links_count != $current_links_count ) { // Some links were modified
					// Update the term
					wp_update_term($term->term_id, $term->taxonomy, array(
						'description'	=> $content,
					));
				}

				if ( defined('WP_CLI') ) {
					$progress_cli->tick();
				}
			}
		}

		/**
		 * Modify the links in a string
		 * 
		 * @since 3.80.0
		 * 
		 * @param string $content Content
		 * @return string Content
		 */
		public function modify_links_in_string($content) {
			$matches = array();
			if ( !empty($content) && (preg_match_all('#<a(.*?)href="(.*?)"(.*?)>#', $content, $matches, PREG_SET_ORDER) > 0) ) {
				if ( is_array($matches) ) {
					foreach ( $matches as $match ) {
						$link = $match[2];
						list($link_without_anchor, $anchor_link) = $this->split_anchor_link($link); // Split the anchor link
						// Is it an internal link ?
						if ( !empty($link_without_anchor) && $this->is_internal_link($link_without_anchor) ) {
							$new_link = $this->modify_link($link_without_anchor);

							// Replace the link in the post content
							if ( !empty($new_link) ) {
								if ( !empty($anchor_link) ) {
									$new_link .= '#' . $anchor_link;
								}
								$content = str_replace("href=\"$link\"", "href=\"$new_link\"", $content);
								$this->links_count++;
							}
						}
					}
				}
			}
			$content = apply_filters('fgj2wp_modify_links_in_string', $content);
			return $content;
		}
		
		/**
		 * Test if the link is an internal link or not
		 *
		 * @param string $link
		 * @return bool
		 */
		private function is_internal_link($link) {
			$result = (preg_match("#^".$this->plugin_options['url']."#", $link) > 0) ||
				(preg_match("#^(http|//)#", $link) == 0);
			return $result;
		}
		
		/**
		 * Modify a link
		 * 
		 * @since 3.80.0
		 * 
		 * @param string $link Link
		 * @return string Modified link
		 */
		private function modify_link($link) {
			$new_link = '';

			// Try to find a matching term
			$linked_term = $this->get_wp_term_from_joomla_url($link);
			if ( $linked_term ) {
				$new_link = get_term_link($linked_term->term_id, $linked_term->taxonomy);
			}

			if ( empty($new_link) ) {
				// Try to find a matching post
				$linked_post = $this->get_wp_post_from_joomla_url($link);
				if ( $linked_post ) {
					$linked_post_id = $linked_post->ID;
					$linked_post_id = apply_filters('fgj2wp_post_get_post_by_joomla_id', $linked_post_id, $linked_post); // Used to get the ID of the translated post
					$new_link = get_permalink($linked_post_id);
				}
			}
			$new_link = apply_filters('fgj2wp_pre_modify_link', $new_link, $link);
			return $new_link;
		}
		
		/**
		 * Get a WordPress post that matches a Joomla URL
		 * 
		 * @param string $url URL
		 * @return WP_Post WordPress post | null
		 */
		private function get_wp_post_from_joomla_url($url) {
			$post = null;
			$post_name = $this->remove_html_extension(basename($url));
			
			// Try to find a post by its post name
			$post_id = $this->get_post_by_name($post_name);
			
			// Try to find a post by its post name by replacing _ by -
			if ( empty($post_id) ) {
				$post_id = $this->get_post_by_name(preg_replace('/_(-_)*/', '-', $post_name));
			}
			
			// Try to find a post in the redirect table
			if ( empty($post_id) && class_exists('FG_Joomla_to_WordPress_Redirect') ) {
				$redirect_obj = new FG_Joomla_to_WordPress_Redirect();
				$post = $redirect_obj->find_url_in_redirect_table($post_name);
				if ( $post ) {
					$post_id = $post->id;
				}
			}
			
			// Try to find a post by an ID in the URL
			if ( empty($post_id) && !preg_match('/view=weblink/', $url) ) {
				$meta_key_value = $this->get_joomla_id_in_link($url);
				$post_id = $this->get_wp_post_id_from_meta($meta_key_value['meta_key'], $meta_key_value['meta_value']);
			}
			
			if ( !empty($post_id) ) {
				$post = get_post($post_id);
			}
			if ( !$post ) {
				$post = apply_filters('fgj2wp_get_wp_post_from_joomla_url', $post, $url);
			}
			return $post;
		}

		/**
		 * Get a WordPress term that matches a Joomla URL
		 * 
		 * @since 3.19.0
		 * 
		 * @param string $url URL
		 * @return WP_Term WordPress term | null
		 */
		private function get_wp_term_from_joomla_url($url) {
			$term = null;
			$matches = array();

			// Try to find a category in the URL
			if ( preg_match('#view=category.*cid=(\d+)#', $url, $matches) ) {
				$cat_id = $matches[1];
				$term_id = $this->get_wp_term_id_from_meta('_fgj2wp_old_category_id', $cat_id);
				if ( !empty($term_id) ) {
					$term = get_term($term_id);
				}
			}
			if ( !$term ) {
				$term = apply_filters('fgj2wp_get_wp_term_from_joomla_url', null, $url);
			}
			return $term;
		}
		
		/**
		 * Remove the file extension .html
		 * 
		 * @param string $url URL
		 * @return string URL
		 */
		private function remove_html_extension($url) {
			$url = preg_replace('/\.html$/', '', $url);
			return $url;
		}
		
		/**
		 * Get a post by its name
		 * 
		 * @global object $wpdb
		 * @param string $post_name
		 * @param string $post_type
		 * @return int $post_id
		 */
		private function get_post_by_name($post_name, $post_type = 'post') {
			global $wpdb;
			$post_id = $wpdb->get_var($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE post_name = %s AND post_type= %s", $post_name, $post_type));
			return $post_id;
		}
		
		/**
		 * Get the Joomla ID in a link
		 *
		 * @param string $link
		 * @return array('meta_key' => $meta_key, 'meta_value' => $meta_value)
		 */
		private function get_joomla_id_in_link($link) {
			$matches = array();

			$meta_key_value = array(
				'meta_key'		=> '',
				'meta_value'	=> 0);
			$meta_key_value = apply_filters('fgj2wp_pre_get_joomla_id_in_link', $meta_key_value, $link);
			if ( $meta_key_value['meta_value'] == 0 ) {
				$meta_key_value['meta_key'] = '_fgj2wp_old_id';
				// Without URL rewriting
				if ( preg_match("#[^a-zA-Z_]id=(\d+)#", $link, $matches) ) {
					$meta_key_value['meta_value'] = $matches[1];
				}
				// With URL rewriting
				elseif ( preg_match("#^((.*)/)?(\d+)-(.*)#", $link, $matches) ) {
					$meta_key_value['meta_value'] = $matches[3];
				} else {
					$meta_key_value = apply_filters('fgj2wp_post_get_joomla_id_in_link', $meta_key_value);
				}
			}
			return $meta_key_value;
		}

		/**
		 * Split a link by its anchor link
		 * 
		 * @param string $link Original link
		 * @return array(string link, string anchor_link) [link without anchor, anchor_link]
		 */
		private function split_anchor_link($link) {
			$pos = strpos($link, '#');
			if ( $pos !== false ) {
				// anchor link found
				$link_without_anchor = substr($link, 0, $pos);
				$anchor_link = substr($link, $pos + 1);
				return array($link_without_anchor, $anchor_link);
			} else {
				// anchor link not found
				return array($link, '');
			}
		}

		/**
		 * Copy a remote file
		 * in replacement of the copy function
		 * 
		 * @deprecated
		 * @param string $url URL of the source file
		 * @param string $path destination file
		 * @return boolean
		 */
		public function remote_copy($url, $path) {
			return $this->download_manager->copy($url, $path);
		}

		/**
		 * Recount the items for a taxonomy
		 * 
		 * @return boolean
		 */
		private function terms_tax_count($taxonomy) {
			$terms = get_terms(array(
				'taxonomy' => $taxonomy,
			));
			// Get the term taxonomies
			$terms_taxonomies = array();
			foreach ( $terms as $term ) {
				$terms_taxonomies[] = $term->term_taxonomy_id;
			}
			if ( !empty($terms_taxonomies) ) {
				return wp_update_term_count_now($terms_taxonomies, $taxonomy);
			} else {
				return true;
			}
		}

		/**
		 * Recount the items for each category and tag
		 * 
		 * @return boolean
		 */
		private function terms_count() {
			$result = $this->terms_tax_count('category');
			$result |= $this->terms_tax_count('post_tag');
		}

		/**
		 * Guess the Joomla version
		 *
		 * @return string Joomla version
		 */
		private function joomla_version() {
			if ( !$this->table_exists('content') ) {
				$version = '0.0';
			} elseif ( !$this->column_exists('content', 'alias') ) {
				$version = '1.0';
			} elseif ( !$this->column_exists('content', 'asset_id') ) {
				$version = '1.5';
			} elseif ( $this->column_exists('content', 'title_alias') ) {
				$version = '2.5';
			} elseif ( !$this->table_exists('tags') ) {
				$version = '3.0';
			} elseif ( !$this->table_exists('fields') ) {
				$version = '3.1';
			} else {
				$version = '3.7';
			}
			return $version;
		}

		/**
		 * Get the Joomla installation language
		 *
		 * @return string Language code (eg: fr-FR)
		 */
		public function get_joomla_language() {
			$lang = '';

			$params = $this->get_params('com_languages');
			if ( isset($params['site']) ) {
				$lang = $params['site'];
			}
			return $lang;
		}
		
		/**
		 * Get the Joomla parameters of an extension
		 *
		 * @param string $extension Extension code
		 * @return array Parameters
		 */
		public function get_params($extension) {
			$params = array();
			$prefix = $this->plugin_options['prefix'];

			if ( $this->table_exists('extensions') ) {
				$sql = "
					SELECT `params`
					FROM {$prefix}extensions
					WHERE `element` = '$extension'
					ORDER BY `extension_id`
				";
			} elseif ( $this->table_exists('components') ) {
				$sql = "
					SELECT `params`
					FROM {$prefix}components
					WHERE `option` = '$extension'
					ORDER BY `id`
				";
			} else {
				return $params;
			}
			$result = $this->joomla_query($sql);
			if ( isset($result[0]['params']) ) {
				$params = $this->parse_ini_or_json($result[0]['params']);
			}
			return $params;
		}

		/**
		 * Get the Joomla manifest of an extension (used to get the version of a Joomla component)
		 *
		 * @since 3.92.0
		 * 
		 * @param string $extension Extension code
		 * @param string $type component | plugin
		 * @return array Manifest values
		 */
		public function get_extension_manifest($extension, $type='component') {
			$manifest = array();

			if ( $this->table_exists('extensions') ) {
				$prefix = $this->plugin_options['prefix'];
				$sql = "
					SELECT `manifest_cache`
					FROM {$prefix}extensions
					WHERE `element` = '$extension'
					AND `type` = '$type'
					ORDER BY `extension_id`
				";
				$result = $this->joomla_query($sql);
				if ( isset($result[0]['manifest_cache']) ) {
					$manifest = $this->parse_ini_or_json($result[0]['manifest_cache']);
				}
			}
			return $manifest;
		}
		
		/**
		 * Parse a INI or JSON string
		 * 
		 * @since 3.92.0
		 * 
		 * @param string $string String
		 * @return array Parsed result
		 */
		private function parse_ini_or_json($string) {
			if ( (substr($string, 0, 1) != '{') && (substr($string, -1, 1) != '}') ) {
				$result = parse_ini_string($string, false, INI_SCANNER_RAW);
			} else {
				$result = json_decode($string, true);
			}
			return $result;
		}

		/**
		 * Returns the imported posts mapped with their Joomla ID
		 *
		 * @param string $meta_key Meta key (default = _fgj2wp_old_id)
		 * @return array of post IDs [joomla_article_id => wordpress_post_id]
		 */
		public function get_imported_joomla_posts($meta_key = '_fgj2wp_old_id') {
			global $wpdb;
			$posts = array();

			$sql = $wpdb->prepare("SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s", $meta_key);
			$results = $wpdb->get_results($sql);
			foreach ( $results as $result ) {
				$posts[$result->meta_value] = $result->post_id;
			}
			ksort($posts);
			return $posts;
		}

		/**
		 * Returns the imported posts (including their post type) mapped with their Joomla ID
		 *
		 * @param string $meta_key Meta key (default = _fgj2wp_old_id)
		 * @return array of post IDs [joomla_article_id => [wordpress_post_id, wordpress_post_type]]
		 */
		public function get_imported_joomla_posts_with_post_type($meta_key = '_fgj2wp_old_id') {
			global $wpdb;
			$posts = array();

			$sql = $wpdb->prepare("
				SELECT pm.post_id, pm.meta_value, p.post_type
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = %s
			", $meta_key);
			$results = $wpdb->get_results($sql);
			foreach ( $results as $result ) {
				$posts[$result->meta_value] = array(
					'post_id' => $result->post_id,
					'post_type' => $result->post_type,
				);
			}
			ksort($posts);
			return $posts;
		}

		/**
		 * Returns the imported post ID corresponding to a Joomla ID
		 *
		 * @param int $joomla_id Joomla article ID
		 * @return int WordPress post ID
		 */
		public function get_wp_post_id_from_joomla_id($joomla_id) {
			$post_id = $this->get_wp_post_id_from_meta('_fgj2wp_old_id', $joomla_id);
			return $post_id;
		}

		/**
		 * Returns the imported post ID corresponding to a meta key and value
		 *
		 * @param string $meta_key Meta key
		 * @param string $meta_value Meta value
		 * @return int WordPress post ID
		 */
		public function get_wp_post_id_from_meta($meta_key, $meta_value) {
			global $wpdb;

			$sql = $wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1", $meta_key, $meta_value);
			$post_id = $wpdb->get_var($sql);
			return $post_id;
		}

		/**
		 * Returns the imported post IDs corresponding to a meta key and value
		 *
		 * @since 4.10.0
		 * 
		 * @param string $meta_key Meta key
		 * @param string $meta_value Meta value
		 * @return array WordPress post IDs
		 */
		public function get_wp_post_ids_from_meta($meta_key, $meta_value) {
			global $wpdb;

			$sql = $wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s", $meta_key, $meta_value);
			$post_ids = $wpdb->get_col($sql);
			return $post_ids;
		}

		/**
		 * Get a Post ID from its GUID
		 * 
		 * @since 3.72.0
		 * 
		 * @global object $wpdb
		 * @param string $guid GUID
		 * @return int Post ID
		 */
		public function get_post_id_from_guid($guid) {
			global $wpdb;
			return $wpdb->get_var($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE guid=%s", $guid));
		}
		
		/**
		 * Returns the imported term ID corresponding to a meta key and value
		 *
		 * @since 3.10.0
		 * 
		 * @param string $meta_key Meta key
		 * @param string $meta_value Meta value
		 * @return int WordPress term ID
		 */
		public function get_wp_term_id_from_meta($meta_key, $meta_value) {
			global $wpdb;

			$sql = $wpdb->prepare("SELECT term_id FROM {$wpdb->termmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1", $meta_key, $meta_value);
			$term_id = $wpdb->get_var($sql);
			return $term_id;
		}

		/**
		 * Returns the imported term IDs corresponding to a meta key and value
		 *
		 * @since 4.10.0
		 * 
		 * @param string $meta_key Meta key
		 * @param string $meta_value Meta value
		 * @return array WordPress category IDs
		 */
		public function get_wp_term_ids_from_meta($meta_key, $meta_value) {
			global $wpdb;

			$sql = $wpdb->prepare("SELECT term_id FROM {$wpdb->termmeta} WHERE meta_key = %s AND meta_value = %s", $meta_key, $meta_value);
			$term_ids = $wpdb->get_col($sql);
			return $term_ids;
		}

		/**
		 * Get the WordPress menu item from the Joomla Itemid
		 * 
		 * @since 3.52.0
		 * 
		 * @param int $item_id Joomla item ID
		 * @return array Menu item
		 */
		public function get_menu_item_from_item_id($item_id) {
			$menu_item_meta = array();
			$args = array(
				'post_type'		=> 'nav_menu_item',
				'meta_query'	=> array(
					array(
					   'key'       => '_fgj2wp_old_menu_item_id',
					   'value'     => $item_id,
					   'compare'   => '='
					)
				)
			);
			$posts = get_posts($args);
			if ( count($posts) > 0 ) {
				$menu_item_meta = get_metadata('post', $posts[0]->ID);
				foreach ( $menu_item_meta as &$value ) {
					if ( is_array($value) ) {
						$value = $value[0];
					}
				}
			}
			return $menu_item_meta;
		}
		
		/**
		 * Returns the imported users mapped with their Joomla ID
		 *
		 * @return array of user IDs [joomla_user_id => wordpress_user_id]
		 */
		public function get_imported_joomla_users() {
			global $wpdb;
			$users = array();

			$sql = "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = '_fgj2wp_old_user_id'";
			$results = $wpdb->get_results($sql);
			foreach ( $results as $result ) {
				$users[$result->meta_value] = $result->user_id;
			}
			ksort($users);
			return $users;
		}

		/**
		 * Test if a column exists
		 *
		 * @param string $table Table name
		 * @param string $column Column name
		 * @return bool
		 */
		public function column_exists($table, $column) {
			global $joomla_db;

			$cache_key = 'fgj2wp_column_exists:' . $table . '.' . $column;
			$found = false;
			$column_exists = wp_cache_get($cache_key, '', false, $found);
			if ( $found === false ) {
				$column_exists = false;
				try {
					$prefix = $this->plugin_options['prefix'];

					$sql = "SHOW COLUMNS FROM `{$prefix}{$table}` LIKE '$column'";
					$query = $joomla_db->query($sql, PDO::FETCH_ASSOC);
					if ( $query !== false ) {
						$result = $query->fetch();
						$column_exists = !empty($result);
					}
				} catch ( PDOException $e ) {}
				
				// Store the result in cache for the current request
				wp_cache_set($cache_key, $column_exists);
			}
			return $column_exists;
		}

		/**
		 * Test if a table exists
		 *
		 * @param string $table Table name
		 * @return bool
		 */
		public function table_exists($table) {
			global $joomla_db;

			$cache_key = 'fgj2wp_table_exists:' . $table;
			$found = false;
			$table_exists = wp_cache_get($cache_key, '', false, $found);
			if ( $found === false ) {
				$table_exists = false;
				try {
					$prefix = $this->plugin_options['prefix'];

					$sql = "SHOW TABLES LIKE '{$prefix}{$table}'";
					$query = $joomla_db->query($sql, PDO::FETCH_ASSOC);
					if ( $query !== false ) {
						$result = $query->fetch();
						$table_exists = !empty($result);
					}
				} catch ( PDOException $e ) {}
				
				// Store the result in cache for the current request
				wp_cache_set($cache_key, $table_exists);
			}
			return $table_exists;
		}

		/**
		 * Test if a table exists in WordPress
		 *
		 * @since 3.74.0
		 * 
		 * @param string $table Table name
		 * @return bool
		 */
		public function wp_table_exists($table) {
			global $wpdb;
			
			$table_name = $wpdb->prefix . $table;
			return $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) == $table_name;
		}

		/**
		 * Test if a remote file exists
		 * 
		 * @param string $filePath
		 * @return boolean True if the file exists
		 */
		public function url_exists($filePath) {
			$url = str_replace(' ', '%20', $filePath);
			$user_agent = 'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/85.0.4183.102 Safari/537.36';
			
			// Try the get_headers method
			stream_context_set_default(array(
				'http' => array(
					'header' => $user_agent,
				)
			));
			$headers = @get_headers($url);
			$result = preg_match("/200/", $headers[0]);
			
			if ( !$result && strpos($filePath, 'https:') !== 0 ) {
				// Try the fsock method
				$url = str_replace('http://', '', $url);
				if ( strstr($url, '/') ) {
					$url = explode('/', $url, 2);
					$url[1] = '/' . $url[1];
				} else {
					$url = array($url, '/');
				}

				$fh = fsockopen($url[0], 80);
				if ( $fh ) {
					fputs($fh, 'GET ' . $url[1] . " HTTP/1.1\nHost:" . $url[0] . "\n");
					fputs($fh, $user_agent . "\n\n");
					$response = fread($fh, 22);
					fclose($fh);
					$result = (strpos($response, '200') !== false);
				} else {
					$result = false;
				}
			}
			
			return $result;
		}
		
		/**
		 * Store the mapping of the imported categories
		 * 
		 * @since 3.22.0
		 */
		public function get_imported_categories($meta_key='_fgj2wp_old_category_id') {
			$this->imported_categories = $this->get_term_metas_by_metakey($meta_key);
		}
		
		/**
		 * Get all the term metas corresponding to a meta key
		 * 
		 * @param string $meta_key Meta key
		 * @return array List of term metas: term_id => meta_value
		 */
		public function get_term_metas_by_metakey($meta_key) {
			global $wpdb;
			$metas = array();
			
			$sql = $wpdb->prepare("SELECT term_id, meta_value FROM {$wpdb->termmeta} WHERE meta_key = %s", $meta_key);
			$results = $wpdb->get_results($sql);
			foreach ( $results as $result ) {
				$metas[$result->meta_value] = $result->term_id;
			}
			ksort($metas);
			return $metas;
		}
		
		/**
		 * Search a term by its slug (LIKE search)
		 * 
		 * @param string $slug slug
		 * @return int Term id
		 */
		public function get_term_id_by_slug($slug) {
			global $wpdb;
			return $wpdb->get_var($wpdb->prepare("
				SELECT term_id FROM {$wpdb->terms}
				WHERE slug LIKE %s
			", $slug));
		}
		
		/**
		 * Fix the internal links
		 * Prevent WordPress from breaking links containing ':'
		 * 
		 * @since 3.64.1
		 * 
		 * @param string $content Content
		 * @return string Content
		 */
		public function fix_colon_in_links($content) {
			$matches = array();
			$count = 0;
			if ( preg_match_all('#<a(.*?)href="(.*?)"(.*?)>#', $content, $matches, PREG_SET_ORDER) > 0 ) {
				if ( is_array($matches) ) {
					$links_to_replace = array();
					foreach ( $matches as $match ) {
						$link = $match[2];
						$new_link = preg_replace('/(id=\d+):/', '$1;', $link, -1, $count);
						if ( $count > 0 ) {
							$links_to_replace[$link] = $new_link;
						}
					}
					if ( !empty($links_to_replace) ) {
						$content = str_replace(array_keys($links_to_replace), array_values($links_to_replace), $content);
					}
				}
			}
			return $content;
		}
		
		/**
		 * Same as get_post_meta() but query the key with LIKE
		 * 
		 * @since 4.3.0
		 * 
		 * @param int $post_id Post ID
		 * @param string $meta_key Meta key
		 * @return string Meta value
		 */
		public function get_post_meta_like($post_id, $meta_key) {
			global $wpdb;
			$sql = $wpdb->prepare("
				SELECT meta_value FROM {$wpdb->postmeta}
				WHERE post_id = %d
				AND meta_key LIKE %s
			", $post_id, $meta_key);
			$result = $wpdb->get_col($sql);
			return $result;
		}
		
		/**
		 * Map a taxonomy
		 * 
		 * @since 4.27.0
		 * 
		 * @param string $taxonomy Taxonomy
		 * @return string Taxonomy
		 */
		public function map_taxonomy($taxonomy) {
			$wp_taxonomy = '';
			switch ( $taxonomy ) {
				case 'categories':
					$wp_taxonomy = 'category';
					break;
				case 'tags':
					$wp_taxonomy = 'post_tag';
					break;
				case 'post_type': // The taxonomy "post_type" prevents the posts to display on the backend with ACF
					$wp_taxonomy = 'posttype';
					break;
				case 'type': // "type" is reserved
				case 'terms': // "terms" is reserved
				case 'year': // "year" is reserved
					$wp_taxonomy = $taxonomy . '_tax';
					break;
				default:
					$wp_taxonomy = $this->build_taxonomy_slug($taxonomy);
			}
			$wp_taxonomy = apply_filters('fgj2wp_map_taxonomy', $wp_taxonomy, $taxonomy);
			return $wp_taxonomy;
		}
		
		/**
		 * Build the taxonomy slug
		 * 
		 * @since 4.27.0
		 * 
		 * @param string $taxonomy Taxonomy name
		 * @return string Taxonomy slug
		 */
		public function build_taxonomy_slug($taxonomy) {
			if ( is_numeric($taxonomy) ) {
				$taxonomy = '_' . $taxonomy; // Avoid only numeric taxonomy slug
			}
			$taxonomy = substr(sanitize_key(FG_Joomla_to_WordPress_Tools::convert_to_latin(remove_accents($taxonomy))), 0, 30); // The taxonomy is limited to 30 characters in Types
			return $taxonomy;
		}
		
	}
}

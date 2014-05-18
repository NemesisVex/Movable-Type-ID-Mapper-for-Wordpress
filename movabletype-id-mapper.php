<?php

/*
  Plugin Name: Movable Type ID Mapper
  Plugin URI: http://vigilantmedia.com/index.php/projects/
  Description: Movable Type ID Mapper looks up an entry from mt_entry by entry_id, then queries Wordpress for a corresponding entry by entry_basename.
  Author: Greg Bueno
  Version: 0.01
  Author URI: http://vigilantmedia.com/
 */


if (!class_exists('Movabletype_Id_Mapper')) {

	/**
	 * Movabletype_Id_Mapper
	 * 
	 * Movabletype_Id_Mapper maps custom Movable Type URLs to corresponding
	 * Wordpress entries that have been imported from a Movable Type blog.
	 * The mapper parses a URL for an entry ID, then queries a Movable Type
	 * mt_entry table for a post. If a post is found, the entry_basename
	 * field is used to query Wordpress, and a successful match redirects
	 * to that post.
	 * 
	 * A theme registers at least one regular expression to the mapper,
	 * indicating an offset where the entry ID may be found.
	 * 
	 * The mapper checks for pages but ignores posts and attachments,
	 * as well as all archive, category and author URLs.
	 * 
	 * An administrator may specify an external database where Movable Type
	 * may be hosted. If none is configued, the Wordpress database is used.
	 */
	class Movabletype_Id_Mapper {

		private $patterns;
		private $mt_db;
		private $map_ready;

		public function __construct() {
			$this->map_ready = false;

			// Initialize settings in the admin menu.
			add_action('admin_init', array(&$this, 'admin_init'));
			add_action('admin_menu', array(&$this, 'add_menu'));

			// Initialize the plugin itself.
			add_action('parse_query', array(&$this, 'init'));
			add_action('pre_get_posts', array(&$this, 'remap_mt'));
		}

		/**
		 * init
		 * 
		 * init performs a few checks before making an actual remap. It won't
		 * run for WP posts, attachments or archives. It won't run
		 * if the configured database does not contain the mt_entry table.
		 * And it won't run if no patterns are registered from a theme.
		 * 
		 * @global type $_mt_id_mapper_registered_patterns
		 * @global type $wpdb
		 * @return boolean
		 */
		public function init() {
			global $_mt_id_mapper_registered_patterns, $wpdb;

			// Ignore anything that isn't singular (post, page, attachment).
			if (is_singular() === false) {
				return false;
			} else {
				// Ignore posts and attachments.
				if (is_single() === true || is_attachment() === true) {
					return false;
				}
			}

			// Exit if no patterns are registered.
			do_action('mt_id_mapper_pattern_setup');

			if (empty($_mt_id_mapper_registered_patterns)) {
				return false;
			} else {
				$this->patterns = $_mt_id_mapper_registered_patterns;
			}

			// Establish a connection to configured database.
			// Use the WP database if none is configured.
			$mt_db_host = get_option('mt_db_host', DB_HOST);
			if (empty($mt_db_host)) {
				$mt_db_host = DB_HOST;
			}

			$mt_db_name = get_option('mt_db_name', DB_NAME);
			if (empty($mt_db_name)) {
				$mt_db_name = DB_NAME;
			}

			$mt_db_user = get_option('mt_db_user', DB_USER);
			if (empty($mt_db_user)) {
				$mt_db_user = DB_USER;
			}

			$mt_db_password = get_option('mt_db_password', DB_PASSWORD);
			if (empty($mt_db_password)) {
				$mt_db_password = DB_PASSWORD;
			}

			if (false === $this->mt_db = new wpdb($mt_db_user, $mt_db_password, $mt_db_name, $mt_db_host)) {
				$this->mt_db = $wpdb;
			}

			// Exit if mt_entry table doesn't exist.
			$mt_entry_table = $this->mt_db->get_var("show tables like 'mt_entry';");
			if ($mt_entry_table != 'mt_entry') {
				return false;
			}

			// Everything look cool?
			$this->map_ready = true;
		}

		/**
		 * remap_mt
		 * 
		 * remap_mt extracts an entry_id from a URL, queries Movable Type
		 * for that entry, queries Wordpress on the results of the Movable Type
		 * query, then redirects if a mapping is made.
		 * 
		 */
		public function remap_mt() {
			if ($this->map_ready === false) {
				return false;
			}

			if (is_array($this->patterns)) {
				// Process each pattern.
				foreach ($this->patterns as $pattern) {
					// Match our URL with a pattern.
					preg_match($pattern->pattern, $_SERVER['REQUEST_URI'], $match);

					if (count($match) > 0) {
						// Extract the entry_id.
						$mt_entry_id = $match[$pattern->offset];

						// Use that entry_id to pivot on the base name.
						$wp_entry = $this->get_wp_entry_by_mt_id($mt_entry_id);

						// Redirect to the new URL.
						if (!empty($wp_entry->ID)) {
							$url = get_permalink($wp_entry->ID);
							wp_redirect($url, 301);
							die();
						}
					}
				}
			}
		}

		/**
		 * get_wp_entry
		 * 
		 * get_wp_entry takes a Movable Type entry ID, queries Movable Type
		 * for the entry_basename, queries Wordpress with the entry_basename
		 * and returns the result of the query.
		 * 
		 * @global object $wpdb
		 * @param int $mt_entry_id
		 * @return object
		 */
		protected function get_wp_entry_by_mt_id($mt_entry_id) {
			global $wpdb;

			$mt_entry = $this->mt_db->get_row($this->mt_db->prepare("select * from mt_entry where entry_id=%d", $mt_entry_id));

			$post_name = $mt_entry->entry_basename;

			$wp_entry = $wpdb->get_row($wpdb->prepare("select * from $wpdb->posts where post_name=%s", $post_name));

			return $wp_entry;
		}

		/**
		 * admin_init
		 * 
		 * admin_init creates the settings page with which we can connect
		 * to a database with Movable Type.
		 */
		public function admin_init() {
			register_setting('mt_id_mapper-group', 'mt_db_host');
			register_setting('mt_id_mapper-group', 'mt_db_name');
			register_setting('mt_id_mapper-group', 'mt_db_user');
			register_setting('mt_id_mapper-group', 'mt_db_password');

			add_settings_section('mt_id_mapper-db_section', 'Database Connection', array(&$this, 'settings_section'), 'mt_id_mapper');
			add_settings_field('mt_id_mapper-db_host', 'Database host', array(&$this, 'settings_input_text'), 'mt_id_mapper', 'mt_id_mapper-db_section', array('field' => 'mt_db_host'));
			add_settings_field('mt_id_mapper-db_name', 'Database name', array(&$this, 'settings_input_text'), 'mt_id_mapper', 'mt_id_mapper-db_section', array('field' => 'mt_db_name'));
			add_settings_field('mt_id_mapper-db_user', 'Database user', array(&$this, 'settings_input_text'), 'mt_id_mapper', 'mt_id_mapper-db_section', array('field' => 'mt_db_user'));
			add_settings_field('mt_id_mapper-db_password', 'Database password', array(&$this, 'settings_input_password'), 'mt_id_mapper', 'mt_id_mapper-db_section', array('field' => 'mt_db_password'));
		}

		public function settings_section() {
			echo "Configure how Wordpress connects to Movable Type.";
		}

		public function settings_input_text($args) {
			// Get the field name from the $args array
			$field = $args['field'];
			// Get the value of this setting
			$value = get_option($field);
			// echo a proper input type="text"
			echo sprintf('<input type="text" name="%s" id="%s" value="%s" />', $field, $field, $value);
		}

		public function settings_input_password($args) {
			// Get the field name from the $args array
			$field = $args['field'];
			// Get the value of this setting
			$value = get_option($field);
			// echo a proper input type="text"
			echo sprintf('<input type="password" name="%s" id="%s" value="%s" />', $field, $field, $value);
		}

		public function settings_page() {
			if (!current_user_can('manage_options')) {
				wp_die(__('You do not have sufficient permissions to access this page.'));
			}

			// Render the settings template
			include(sprintf("%s/templates/settings.php", dirname(__FILE__)));
		}

		public function add_menu() {
			// Add a page to manage this plugin's settings
			add_options_page(
					'Movable Type ID Mapper Settings', 'Movable Type ID Mapper', 'manage_options', 'mt_id_mapper', array(&$this, 'settings_page')
			);
		}

		public static function activate() {
			
		}

		public static function deactivate() {
			
		}

	}

}

$_mt_id_mapper_registered_patterns = array();

if (class_exists('Movabletype_Id_Mapper')) {
	register_activation_hook(__FILE__, array('Movabletype_Id_Mapper', 'activate'));
	register_activation_hook(__FILE__, array('Movabletype_Id_Mapper', 'deactivate'));

	$mt_id_mapper = new Movabletype_Id_Mapper();
}

if (!function_exists('mt_id_mapper_register_pattern')) {

	/**
	 * mt_id_mapper_register_pattern
	 * 
	 * mt_id_mapper_register_pattern registers a regular expression
	 * with which to check against the URL of an entry. This function
	 * accepts a single array with the following keys:
	 * 
	 * pattern: The regular expression with a grouping to mark the entry ID.
	 * offset: The number representing the grouping results, e.g. 2 would represent $2.
	 * 
	 * @global array $_mt_id_mapper_registered_patterns
	 * @param type $pattern
	 * @return boolean
	 */
	function mt_id_mapper_register_pattern($pattern) {
		global $_mt_id_mapper_registered_patterns;

		if (!array_key_exists('pattern', $pattern) || !array_key_exists('offset', $pattern)) {
			return false;
		}

		$_mt_id_mapper_registered_patterns[] = (object) $pattern;
	}

}

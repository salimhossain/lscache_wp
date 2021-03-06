<?php

/**
 * LiteSpeed Cache Admin Interface
 */
class LiteSpeed_Cache_Cli_Admin
{

	private static $checkboxes;
	private static $purges;

	function __construct()
	{
		self::$checkboxes =
			array(
				LiteSpeed_Cache_Config::OPID_MOBILEVIEW_ENABLED,
				LiteSpeed_Cache_Config::OPID_PURGE_ON_UPGRADE,
				LiteSpeed_Cache_Config::OPID_CACHE_COMMENTERS,
				LiteSpeed_Cache_Config::OPID_CACHE_LOGIN,
				LiteSpeed_Cache_Config::OPID_CACHE_FAVICON,
				LiteSpeed_Cache_Config::OPID_CACHE_RES,
				LiteSpeed_Cache_Config::OPID_CHECK_ADVANCEDCACHE,
			);
		self::$purges =
			array(
				'purge_' . LiteSpeed_Cache_Config::PURGE_ALL_PAGES =>
					LiteSpeed_Cache_Config::PURGE_ALL_PAGES,
				'purge_' . LiteSpeed_Cache_Config::PURGE_FRONT_PAGE =>
					LiteSpeed_Cache_Config::PURGE_FRONT_PAGE,
				'purge_' . LiteSpeed_Cache_Config::PURGE_HOME_PAGE =>
					LiteSpeed_Cache_Config::PURGE_HOME_PAGE,
				'purge_' . LiteSpeed_Cache_Config::PURGE_AUTHOR =>
					LiteSpeed_Cache_Config::PURGE_AUTHOR,
				'purge_' . LiteSpeed_Cache_Config::PURGE_YEAR =>
					LiteSpeed_Cache_Config::PURGE_YEAR,
				'purge_' . LiteSpeed_Cache_Config::PURGE_MONTH =>
					LiteSpeed_Cache_Config::PURGE_MONTH,
				'purge_' . LiteSpeed_Cache_Config::PURGE_DATE =>
					LiteSpeed_Cache_Config::PURGE_DATE,
				'purge_' . LiteSpeed_Cache_Config::PURGE_TERM =>
					LiteSpeed_Cache_Config::PURGE_TERM,
				'purge_' . LiteSpeed_Cache_Config::PURGE_POST_TYPE =>
					LiteSpeed_Cache_Config::PURGE_POST_TYPE,
			);
	}

	/**
	 * Set an individual LiteSpeed Cache option.
	 *
	 * ## OPTIONS
	 *
	 * <key>
	 * : The option key to update.
	 *
	 * <newvalue>
	 * : The new value to set the option to.
	 *
	 * ## EXAMPLES
	 *
	 *     # Set to not cache the login page
	 *     $ wp lscache-admin set_option cache_login false
	 *
	 */
	function set_option($args, $assoc_args)
	{
		$plugin_dir = plugin_dir_path(dirname(__FILE__));
		require_once $plugin_dir . 'admin/class-litespeed-cache-admin.php';
		require_once $plugin_dir . 'admin/class-litespeed-cache-admin-display.php';
		require_once $plugin_dir . 'admin/class-litespeed-cache-admin-rules.php';

		$key = $args[0];
		$val = $args[1];

		$config = LiteSpeed_Cache::plugin()->get_config();
		$options = $config->get_options();

		if (!isset($options) || ((!isset($options[$key]))
				&& (!isset(self::$purges[$key])))) {
			WP_CLI::error('The options array is empty or the key is not valid.');
			return;
		}

		LiteSpeed_Cache_Config::convert_options_to_input($options);

		switch ($key) {
		case LiteSpeed_Cache_Config::OPID_VERSION:
			//do not allow
			WP_CLI::error('This option is not available for setting.');
			return;
		case LiteSpeed_Cache_Config::OPID_MOBILEVIEW_ENABLED:
			// set list then do checkbox
			if ($val === 'true') {
				$options[LiteSpeed_Cache_Config::ID_MOBILEVIEW_LIST] =
					'Mobile|Android|Silk/|Kindle|BlackBerry|Opera\ Mini|Opera\ Mobi';
			}
			//fall through
		case LiteSpeed_Cache_Config::OPID_PURGE_ON_UPGRADE:
		case LiteSpeed_Cache_Config::OPID_CACHE_COMMENTERS:
		case LiteSpeed_Cache_Config::OPID_CACHE_LOGIN:
		case LiteSpeed_Cache_Config::OPID_CACHE_FAVICON:
		case LiteSpeed_Cache_Config::OPID_CACHE_RES:
		case LiteSpeed_Cache_Config::OPID_CHECK_ADVANCEDCACHE:
			//checkbox
			if ($val === 'true') {
				$options['lscwp_' . $key] = $key;
			}
			elseif ($val === 'false') {
				unset($options['lscwp_' . $key]);
			}
			else {
				WP_CLI::error('Checkbox value must be true or false.');
				return;
			}
			break;
		case LiteSpeed_Cache_Config::ID_MOBILEVIEW_LIST:
			$enable_key = LiteSpeed_Cache_Config::OPID_MOBILEVIEW_ENABLED;
			if (!$options[$enable_key]) {
				$options['lscwp_' . $enable_key] = $enable_key;
			}
			$options[$key] = $val;
			break;
		default:
			if (substr($key, 0, 6) === 'purge_') {
				if ($val === 'true') {
					WP_CLI::line('key is ' . $key . ', substr is ' . substr($key, 6));
					$options[$key] = substr($key, 6);
				}
				elseif ($val === 'false') {
					unset($options[$key]);
				}
				else {
					WP_CLI::error('Purge checkbox value must be true or false.');
					return;
				}
			}
			else {
				// Everything else, just set the value
				$options[$key] = $val;
			}
			break;
		}

		$admin = new LiteSpeed_Cache_Admin(LiteSpeed_Cache::PLUGIN_NAME,
			LiteSpeed_Cache::PLUGIN_VERSION);

		$output = $admin->validate_plugin_settings($options);

		global $wp_settings_errors;

		if (!empty($wp_settings_errors)) {
			foreach ($wp_settings_errors as $err) {
				WP_CLI::error($err['message']);
			}
			return;
		}

		$ret = update_option(LiteSpeed_Cache_Config::OPTION_NAME, $output);

		if ($ret) {
			WP_CLI::success('Options updated. Please purge the cache.');
		}
		else {
			WP_CLI::error('No options updated.');
		}


	}

	/**
	 * Get the plugin options.
	 *
	 * ## OPTIONS
	 *
	 * ## EXAMPLES
	 *
	 *     # Get all options
	 *     $ wp lscache-admin get_options
	 *
	 */
	function get_options($args, $assoc_args)
	{
		$config = LiteSpeed_Cache::plugin()->get_config();
		$options = $config->get_options();
		$purge_options = $config->get_purge_options();
		unset($options[LiteSpeed_Cache_Config::OPID_PURGE_BY_POST]);
		$option_out = array();
		$purge_diff = array_diff(self::$purges, $purge_options);
		$purge_out = array();

		$buf = WP_CLI::colorize("%CThe list of options:%n\n");
		WP_CLI::line($buf);

		foreach($options as $key => $value) {
			if (in_array($key, self::$checkboxes)) {
				if ($value) {
					$value = 'true';
				}
				else {
					$value = 'false';
				}
			}
			elseif ($value === '') {
				$value = "''";
			}
			$option_out[] = array('key' => $key, 'value' => $value);
		}

		foreach ($purge_options as $opt_name) {
			$purge_out[] = array('key' => 'purge_' . $opt_name, 'value' => 'true');
		}

		foreach ($purge_diff as $opt_name) {
			$purge_out[] = array('key' => 'purge_' . $opt_name, 'value' => 'false');
		}

		WP_CLI\Utils\format_items('table', $option_out, array('key', 'value'));

		$buf = WP_CLI::colorize("%CThe list of PURGE ON POST UPDATE options:%n\n");
		WP_CLI::line($buf);
		WP_CLI\Utils\format_items('table', $purge_out, array('key', 'value'));
	}

	/**
	 * Export plugin options to a file.
	 *
	 * ## OPTIONS
	 *
	 * [--filename=<path>]
	 * : The default path used is CURRENTDIR/lscache_wp_options_DATE-TIME.txt.
	 * To select a different file, use this option.
	 *
	 * ## EXAMPLES
	 *
	 *     # Export options to a file.
	 *     $ wp lscache-admin export_options
	 *
	 */
	function export_options($args, $assoc_args)
	{
		$options = LiteSpeed_Cache::plugin()->get_config()->get_options();
		$output = '';
		if (isset($assoc_args['filename'])) {
			$file = $assoc_args['filename'];
		}
		else {
			$file = getcwd() . '/lscache_wp_options_' . date('d_m_Y-His')
				. '.txt';
		}

		if (!is_writable(dirname($file))) {
			WP_CLI::error('Directory not writable.');
			return;
		}

		foreach ($options as $key => $val)
		{
			$output .= sprintf("%s=%s\n", $key, $val);
		}
		$output .= "\n";

		if (file_put_contents($file, $output) === false) {
			WP_CLI::error('Failed to create file.');
		}
		else {
			WP_CLI::success('Created file ' . $file);
		}
	}

	/**
	 * Import plugin options from a file.
	 *
	 * The file must be formatted as such:
	 * option_key=option_value
	 * One per line.
	 * A Semicolon at the beginning of the line indicates a comment and will be skipped.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : The file to import options from.
	 *
	 * ## EXAMPLES
	 *
	 *     # Import options from CURRENTDIR/options.txt
	 *     $ wp lscache-admin import_options options.txt
	 *
	 */
	function import_options($args, $assoc_args)
	{
		$file = $args[0];
		if (!file_exists($file) || !is_readable($file)) {
			WP_CLI::error('File does not exist or is not readable.');
		}
		$config = LiteSpeed_Cache::plugin()->get_config();
		$content = file_get_contents($file);
		preg_match_all("/^[^;][^=]+=[^=\n\r]*$/m", $content, $input);
		$options = array();
		$default = $config->get_options();

		foreach ($input as $opt) {
			$kv = explode('=', $opt);
			$options[$kv[0]] = $kv[1];
		}

		LiteSpeed_Cache_Config::option_diff($default, $options);

		LiteSpeed_Cache_Config::convert_options_to_input($options);

		$admin = new LiteSpeed_Cache_Admin(LiteSpeed_Cache::PLUGIN_NAME,
			LiteSpeed_Cache::PLUGIN_VERSION);

		$output = $admin->validate_plugin_settings($options);

		global $wp_settings_errors;

		if (!empty($wp_settings_errors)) {
			foreach ($wp_settings_errors as $err) {
				WP_CLI::error($err['message']);
			}
			return;
		}
		WP_CLI::success('Options updated. Please purge the cache. New options: '
			. print_r($output, true));

	}
}

WP_CLI::add_command( 'lscache-admin', 'LiteSpeed_Cache_Cli_Admin' );

<?php
/**
 * WPR Courses
 *
 * @package     WPR
 * @author      Alex Nitu
 * @copyright   2018 WPRiders
 * @license     GPL-2.0+
 *
 * @wordpress-plugin
 * Plugin Name: WPR Courses
 * Plugin URI:  https://www.wpriders.com
 * Description: Custom LearnDash and WooCommerce integration.
 * Author:      Alex Nitu from WPRiders
 * Author URI:  https://www.wpriders.com
 * License:     GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit;
}

// Base plugin directory path.
define( 'WPR_COURSES_DIR', plugin_dir_path( __FILE__ ) );
// Plugin directory URI.
define( 'WPR_COURSES_URI', plugin_dir_url( __FILE__ ) );
// Maximum security question retries.
define( 'WPR_COURSES_RETRIES', 2 );

// Load the settings class file.
require_once WPR_COURSES_DIR . 'inc/class-wpr-courses-settings.php';

// Load the logging class file.
require_once WPR_COURSES_DIR . 'inc/class-wp-logging.php';

// Load hooks.
require_once WPR_COURSES_DIR . 'inc/hooks.php';

// Load shortcodes.
require_once WPR_COURSES_DIR . 'inc/shortcodes.php';

// Load Gravity Hook.
require_once WPR_COURSES_DIR . 'inc/gravity-form-hook.php';
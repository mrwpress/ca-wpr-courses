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
const ABC_API_KEY                  = 'rE1m9FSUFP5sTpKMJlzYo1wBQIvATAra6bs202Ay';
const WPR_COURSES_STATUS_CERTIFIED = 'Certified';
const PROGRAM_ID                   = 312677644;
const PROVIDER_ID                  = 312677985;
const CABL_CERT_STATUS             = 'cabl_cert_status';
const CABL_CERT_INFO               = 'cabl_cert_info';
const CABL_CERT_DATE               = 'cabl_cert_date';
const CABL_STATUS_CERTIFIED        = 'Certified';

// Load the logging class file.
require_once WPR_COURSES_DIR . 'inc/class-wp-logging.php';

// Load hooks.
require_once WPR_COURSES_DIR . 'inc/hooks.php';
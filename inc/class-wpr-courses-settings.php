<?php
/**
 * Settings class file.
 *
 * @package     WPR
 * @author      Alex Nitu
 * @copyright   2018 WPRiders
 * @license     GPL-2.0+
 */

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit;
}

/**
 * Settings class.
 */
class WPR_Courses_Settings {
	/**
	 * Bootstraps the class and hooks required actions & filters.
	 */
	public static function init() {
		add_action( 'woocommerce_settings_tabs_general', [ __CLASS__, 'settings_tab' ] );
		add_action( 'woocommerce_update_options_general', [ __CLASS__, 'update_settings' ] );
	}

	/**
	 * Uses the WooCommerce admin fields API to output settings via the @see woocommerce_admin_fields() function.
	 *
	 * @uses woocommerce_admin_fields()
	 * @uses self::get_settings()
	 */
	public static function settings_tab() {
		woocommerce_admin_fields( self::get_settings() );
	}

	/**
	 * Uses the WooCommerce options API to save settings via the @see woocommerce_update_options() function.
	 *
	 * @uses woocommerce_update_options()
	 * @uses self::get_settings()
	 */
	public static function update_settings() {
		woocommerce_update_options( self::get_settings() );
	}

	/**
	 * Get all the settings for this plugin for @return array Array of settings for @see woocommerce_admin_fields() function.
	 * @see woocommerce_admin_fields() function.
	 *
	 */
	public static function get_settings() {
		$settings = array(
			'section_title'         => array(
				'name' => __( 'Course Settings', 'wpr' ),
				'type' => 'title',
				'id'   => 'wpr_courses_settings_section_title',
			),
			'register_form_id'      => array(
				'name'     => __( 'Register Form ID', 'wpr' ),
				'type'     => 'number',
				'desc'     => __( 'Enter the ID of the Gravity Forms form that handles user registration, e.g. 1.', 'wpr' ),
				'desc_tip' => TRUE,
				'id'       => 'wpr_courses_settings_gf_reg_id',
			),
			'course_id'             => array(
				'name'     => __( 'Course ID', 'wpr' ),
				'type'     => 'number',
				'desc'     => __( 'Enter the course ID, e.g. 388.', 'wpr' ),
				'desc_tip' => TRUE,
				'id'       => 'wpr_courses_settings_course_id',
			),
			'course_product_id'     => array(
				'name'     => __( 'Course Product ID', 'wpr' ),
				'type'     => 'number',
				'desc'     => __( 'Enter the ID of the product associated with the LearnDash course, e.g. 388.', 'wpr' ),
				'desc_tip' => TRUE,
				'id'       => 'wpr_courses_settings_product_id',
			),
			'final_quiz_id'         => array(
				'name'     => __( 'BAC Charts ID', 'wpr' ),
				'type'     => 'number',
				'desc'     => __( 'Enter the ID of the BAC Charts associated with the LearnDash course, e.g. 1790.', 'wpr' ),
				'desc_tip' => TRUE,
				'id'       => 'wpr_courses_settings_quiz_id',
			),
			'final_updated_quiz_id' => array(
				'name'     => __( 'Final Quiz ID', 'wpr' ),
				'type'     => 'number',
				'desc'     => __( 'Enter the ID of the final quiz associated with the LearnDash course, e.g. 1790.', 'wpr' ),
				'desc_tip' => TRUE,
				'id'       => 'wpr_courses_settings_quiz_final_id',
			),
			'certificate_id'        => array(
				'name'     => __( 'Certificate ID', 'wpr' ),
				'type'     => 'text',
				'desc'     => __( 'Enter certificate ID.', 'wpr' ),
				'desc_tip' => TRUE,
				'id'       => 'wpr_courses_settings_certificate_id',
			),
			'section_end'           => array(
				'type' => 'sectionend',
				'id'   => 'wpr_courses_settings_section_end',
			),
		);
		return apply_filters( 'wpr_courses_settings_fields', $settings );
	}
}

WPR_Courses_Settings::init();

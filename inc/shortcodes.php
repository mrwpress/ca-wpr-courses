<?php
/**
 * Shortcodes.
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

function wpr_add_return_to_test_func() {
	$html      = '';
	$user_data = wp_get_current_user();
	if ( 0 !== $user_data->ID ) {
		$status_test  = get_user_meta( $user_data->ID, '_wpr_witobaccocheck_status' );
		$quizz_id     = get_option( 'wpr_courses_settings_quiz_id' );
		$cert_user_id = $user_data->ID;

		$quiz_done         = false;
		$time              = isset( $_GET['time'] ) ? intval( $_GET['time'] ) : -1;
		$quizinfo          = get_user_meta( $cert_user_id, '_sfwd-quizzes', true );
		$selected_quizinfo = $selected_quizinfo2 = null;
		if ( ! empty( $quizinfo ) ) {
			foreach ( $quizinfo as $quiz_i ) {

				if ( ( ( isset( $quiz_i['time'] ) ) && intval( $quiz_i['time'] ) == intval( $time ) )
					&& ( intval( $quiz_i['quiz'] ) === intval( $quizz_id ) ) ) {
					$selected_quizinfo = $quiz_i;
					break;
				}

				if ( intval( $quiz_i['quiz'] ) === intval( $quizz_id ) ) {
					$selected_quizinfo2 = $quiz_i;
				}
			}
		}

		$selected_quizinfo = empty( $selected_quizinfo ) ? $selected_quizinfo2 : $selected_quizinfo;
		if ( ! empty( $selected_quizinfo ) ) {
			$certificate_threshold = learndash_get_setting( $selected_quizinfo['quiz'], 'threshold' );

			if ( ( isset( $selected_quizinfo['percentage'] ) && $selected_quizinfo['percentage'] >= $certificate_threshold * 100 ) || ( isset( $selected_quizinfo['count'] ) &&
			 ( $selected_quizinfo['score'] / $selected_quizinfo['count'] ) >= $certificate_threshold ) ) {
				$quiz_done = true;
			}
		}

		if ( $quiz_done ) {
			if ( 'false' == $status_test ) {
				$link = get_third_party_certificate_link();
				$html = '<span class="learndash-wrapper"><span class="wpProQuiz_content"><span class="wpProQuiz_certificate"><a class="wpr_button btn-blue" href="' . $link . '" style="font-size: 1em;">' . __( 'Return to WI Tobacco Check test', 'wpr' ) . '</a></span></span></span><br /><br /><br />';
			} else {
				$link = get_site_certificate_link( $user_data->ID );
				$html = '<span class="learndash-wrapper"><span class="wpProQuiz_content"><span class="wpProQuiz_certificate"><a class="wpr_button btn-blue" href="' . $link . '" style="font-size: 1em;">' . __( 'Print Responsible Beverage Course Certificate', 'wpr' ) . '</a></span></span></span><br /><br /><br />';
			}
		} else {
			$html = '<span class="learndash-wrapper"><span class="wpProQuiz_content"><span class="wpProQuiz_certificate"><a class="wpr_button btn-blue" href="' . get_permalink( $quizz_id ) . '" style="font-size: 1em;">' . __( 'Return to Final Exam', 'wpr' ) . '</a></span></span></span><br /><br /><br />';
		}
	}

	return $html;
}

add_shortcode( 'wpr_add_return_to_test', 'wpr_add_return_to_test_func' );

/**
 * Birthy date format
 */
function get_birth_date_formatted( $atts, $content = null ) {
	$a = shortcode_atts(
		array(
			'format' => 'm/d/Y',
		),
		$atts
	);

	$date = date_create( get_user_meta( get_current_user_id(), '_wpr_dob', true ) );

	return date_format( $date, $a['format'] );
}
add_shortcode( 'birth_date_formatted', 'get_birth_date_formatted' );


/**
 * Current date format for certificate
 */
function get_current_date_formatted( $atts, $content = null ) {
	$a = shortcode_atts(
		array(
			'format' => 'm/d/Y',
		),
		$atts
	);

	return gmdate( $a['format'] );
}
add_shortcode( 'current_date_formatted', 'get_current_date_formatted' );

/**
 * Date complete certificate
 */
function get_date_certificate_formatted( $atts ) {
	$a = shortcode_atts(
		array(
			'format' => 'm/d/Y',
		),
		$atts
	);

	// Get last date of completion certificate saved in user_meta
	$date = get_user_meta( get_current_user_id(), 'course_completed_100', true );

	if (! $date) {
		$date = time()+date("Z");
	}

	$dt = new DateTime("@$date");

	return $dt->format($a['format']);
}
add_shortcode( 'date_certificate', 'get_date_certificate_formatted' );
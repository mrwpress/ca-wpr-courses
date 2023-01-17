<?php
/**
 * Hooks.
 *
 * @package     WPR
 * @author      Alex Nitu
 * @copyright   2018 WPRiders
 * @license     GPL-2.0+
 */

/*
 * NOTES:
 * User 545 is Karl and has completed Quiz 5
 * http://localhost/cabl/wp-admin/user-edit.php?user_id=545&wp_http_referer=%2Fcabl%2Fwp-admin%2Fusers.php
 *
 * Look into extra sites being loaded in the webhook status on this page
 * http://localhost/cabl/wp-admin/admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway&ppcp-tab=ppcp-connection
 *
 * On Users list in admin dash make another column with expiration of the course ID 100
 *
 * WHEN DONE:
 * In motion hosting copy CA to MI => "Louisiana Server"
 */

/**
 * NOTES
 *
 * @help: https://developers.learndash.com/hook/learndash_quiz_completed/
 */

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit;
}

function wpr_safe_request( $key, $default = '', $type = 'text' ) {
	if ( ! isset( $_POST[ $key ] ) && ! isset( $_GET[ $key ] ) ) {
		return $default;
	}

	$value = ( isset( $_POST[ $key ] ) ) ? $_POST[ $key ] : $_GET[ $key ];

	if ( 'array' == $type || is_array( $value ) ) {
		return recursive_sanitize_text_field( $value );
	}

	if ( 'textarea' == $type ) {
		return sanitize_textarea_field( $value );
	}

	if ( 'email' == $type ) {
		return sanitize_email( $value );
	}

	return sanitize_text_field( $value );
}

function recursive_sanitize_text_field( $array ) {
	$new_array = [];
	foreach ( $array as $key => &$value ) {
		if ( is_array( $value ) ) {
			$value = recursive_sanitize_text_field( $value );
		} else {
			$new_array[ $key ] = $value;
		}
	}

	return $new_array;
}

function wpr_courses_get( $array, $keys = NULL, $default = NULL ) {
	// when no key passed in, just return the original array / object
	if ( NULL === $keys ) {
		return $array;
	}

	if ( is_object( $array ) ) {
		$array = (array) $array;
	}

	$key = ( ! is_array( $keys ) ) ? $keys : $keys[0];

	if ( ! is_array( $array ) || ! array_key_exists( $key, $array ) ) {
		return $default;
	}

	$value = $array[ $key ];

	if ( ! is_array( $keys ) || count( $keys ) == 1 ) {
		return $value;
	}

	return wpr_courses_get( $value, array_slice( $keys, 1 ), $default );
}

add_action( 'manage_users_columns', 'cabl_modify_user_columns' );
function cabl_modify_user_columns( $column_headers ) {
	$column_headers['ca_certified'] = 'CA Certified';
	return $column_headers;
}

add_action( 'manage_users_custom_column', 'cabl_user_posts_count_column_content', 10, 3 );
function cabl_user_posts_count_column_content( $value, $column_name, $user_id ) {
	if ( 'ca_certified' == $column_name ) {
		$value = get_user_meta( $user_id, 'cabl_cert_status', TRUE ) == WPR_COURSES_STATUS_CERTIFIED ? 'Yes' : 'No';
	}
	return $value;
}

add_action( 'admin_init', 'my_admin_init' );
function my_admin_init() {
	$args = [
		'number'     => 200,
		'meta_query' => [
			'relation' => 'OR',
			[
				'key'     => CABL_CERT_STATUS,
				'value'   => CABL_STATUS_CERTIFIED,
				'compare' => '!='
			],
			[
				'key'     => CABL_CERT_DATE,
				'value'   => time(),
				'compare' => '<'
			]
		]
	];

//	$query = new WP_User_Query( $args );
	/*
   * https://abcbiz.abc.ca.gov/login
   * https://bizmod-assets.s3.us-west-2.amazonaws.com/otp-api-doc.html - Documentation
   */

	global $pagenow;
	if ( $pagenow == 'user-edit.php' ) {
		$user_id = wpr_safe_request( 'user_id', 0 ) ? wpr_safe_request( 'user_id' ) : get_current_user_id();
		if ( ! $user_id ) {
			return;
		}

		ca_user_api_call( $user_id );
	}
}

function ca_user_api_call( $user_id ) {
	if ( ! $user_id ) {
		return;
	}

	$server_id = get_user_meta( $user_id, '_wpr_ssn', TRUE );
	if ( ! $server_id ) {
		return;
	}

	$last_name = get_user_meta( $user_id, 'last_name', TRUE );
	if ( ! $last_name ) {
		return;
	}

	//The URL you're sending the request to.
//	$url = 'https://api-services-sb.abc.ca.gov/servers/313230866/lastnames/Albright'; // Sandbox
	$url = 'https://api-services.abc.ca.gov/servers/' . $server_id . '/lastnames/' . $last_name; // Live


//Create a cURL handle.
	$ch = curl_init( $url );

//Create an array of custom headers.
	$customHeaders = [
		'X-API-Key: ' . ABC_API_KEY
	];

//Use the CURLOPT_HTTPHEADER option to use our
//custom headers.
	curl_setopt( $ch, CURLOPT_HTTPHEADER, $customHeaders );

//Set options to follow redirects and return output
//as a string.
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, TRUE );
	curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, TRUE );

//Execute the request.
	$raw_data = curl_exec( $ch );
	if ( ! empty( $raw_data ) ) {
		$result = json_decode( $raw_data );

		$code = wpr_courses_get( $result, 'code', 200 );
		if ( $code != 200 ) {
			$api_log = new WP_Logging();
			$api_log::add( 'User ID:' . $user_id . ' Code: ' . $code, $raw_data );
			return;
		}

		$cert_date = wpr_courses_get( $result, 'certifiedToDate', 0 );
		update_user_meta( $user_id, CABL_CERT_STATUS, $result->certifiedStatus );
		update_user_meta( $user_id, CABL_CERT_INFO, curl_exec( $ch ) );
		update_user_meta( $user_id, CABL_CERT_DATE, $cert_date );
	}
}

add_filter( 'cron_schedules', 'ca_cert_add_cron_interval' );
function ca_cert_add_cron_interval( $schedules ) {
	$schedules['five_minute'] = array(
		'interval' => 300,
		'display'  => esc_html__( 'Every Five Minutes' ),
	);
	return $schedules;
}

//if ( ! wp_next_scheduled( 'ca_cron_task_hook' ) ) {
//	wp_schedule_event( time(), 'five_minute', 'ca_cron_task_hook' );
//}
//add_action( 'ca_cron_task_hook', 'ca_process_user_cert_data' );

function ca_process_user_cert_data() {
	// TODO: Discuss with Will and Bobby - is 200 too low?
	// TODO: Pickup at offset?  Then loop back around?
	// TODO:  What if there are 200+ abandoned accounts?
	$args = [
		'number'     => 200,
		'meta_query' => [
			'relation' => 'OR',
			[
				'key'     => CABL_CERT_STATUS,
				'value'   => CABL_STATUS_CERTIFIED,
				'compare' => '!='
			],
			[
				'key'     => CABL_CERT_DATE,
				'value'   => time(),
				'compare' => '<'
			]
		]
	];

	$query = new WP_User_Query( $args );

	if ( $query->get_results() ) {
		foreach ( $query->get_results() as $result ) {
			$user_id = $result->ID;
			ca_user_api_call( $user_id );
		}
	}

	wp_reset_postdata();
}

/**
 * Hide admin bar for regular users.
 */
function wpr_show_admin_bar() {
	return current_user_can( 'manage_options' );
}

add_filter( 'show_admin_bar', 'wpr_show_admin_bar', 99 );

/**
 * Enqueue scripts and styles.
 */
function wpr_wp_enqueue_scripts() {
	if ( is_plugin_active( 'sfwd-lms/sfwd_lms.php' ) ) {

		wp_enqueue_script( 'wpr_course', WPR_COURSES_URI . 'assets/js/script.js', array( 'jquery' ), NULL, TRUE );
		$wpr_course_js = array(
			'quiz_id'         => ( is_singular( 'sfwd-quiz' ) ? get_the_ID() : 0 ),
			'setting_quiz_id' => get_option( 'wpr_courses_settings_quiz_id' ),
			'redirect_url'    => get_third_party_certificate_link(),
		);
		wp_localize_script( 'wpr_course', 'wprcourseData', $wpr_course_js );
		wp_enqueue_script( 'wpr_course' );

		// We are on a lesson page.
		if ( is_singular( 'sfwd-lessons' ) ) {
			// User is currently going through a course, enqueue the inquisitor script and stylesheet.
			wp_enqueue_style( 'wpr-inquisitor', WPR_COURSES_URI . 'assets/css/inquisitor.css' );
			wp_enqueue_script( 'wpr-inquisitor', WPR_COURSES_URI . 'assets/js/inquisitor.js', array( 'jquery' ), NULL, TRUE );
			// Localize inquisitor script.
			wp_localize_script(
				'wpr-inquisitor',
				'wpr_inquisitor_js',
				array(
					'ajaxurl'     => admin_url( 'admin-ajax.php' ),
					'logout_url'  => wp_logout_url( home_url() ),
					/* translators: %d: number of retries. */
					'logout_text' => sprintf( esc_html__( 'Security question was answered incorrectly %d times. You will be logged out.', 'wpr' ), WPR_COURSES_RETRIES ),
					'intro_text'  => esc_html__( 'Please answer the following question in order to verify your identity and continue the course:', 'wpr' ),
					'error'       => esc_html__( 'There was an unexpected error and we could not verify your answer. Please refresh the page and try again.', 'wpr' ),
					'no_answer'   => esc_html__( 'Please answer the question in order to continue the course!', 'wpr' ),
					'incorrect'   => esc_html__( 'That is not the correct answer!', 'wpr' ),
					'retry_max'   => WPR_COURSES_RETRIES,
					/* translators: %d: number of retries allowed. */
					'retry_warn'  => sprintf( esc_html__( 'Caution: %d wrong answers will log you out of your account!', 'wpr' ), WPR_COURSES_RETRIES ),
				)
			);
		} elseif ( is_singular( 'sfwd-quiz' ) ) {
			// Quiz page, load forced timer script if any questions have timers set.
			$questions = get_post_meta( get_the_ID(), '_wpr_question_timers', TRUE );

			if ( ! empty( $questions ) && is_array( $questions ) ) {
				wp_enqueue_style( 'wpr-quiz-timer', WPR_COURSES_URI . 'assets/css/quiz-timer.css' );
				wp_enqueue_script( 'wpr-quiz-timer', WPR_COURSES_URI . 'assets/js/quiz-timer.js', array( 'jquery' ), NULL, TRUE );
				// Localize forced timer script.
				$quiz_timer_questions = array_map(
					function ( $timers ) {
						return array_map(
							function ( $timeval ) {
								$time_sections = explode( ' ', $timeval );

								$h = 0;
								$m = 0;
								$s = 0;

								foreach ( $time_sections as $k => $v ) {
									$value = trim( $v );

									if ( strpos( $value, 'h' ) ) {
										$h = intVal( $value );
									} elseif ( strpos( $value, 'm' ) ) {
										$m = intVal( $value );
									} elseif ( strpos( $value, 's' ) ) {
										$s = intVal( $value );
									}
								}

								$time = $h * 60 * 60 + $m * 60 + $s;

								if ( 0 === $time ) {
									$time = (int) $timeval;
								}

								return $time;
							},
							$timers
						);
					},
					$questions
				);

				$quiz_timer_strings = array(
					'timer_title'            => __( 'Please listen to the recording!', 'wpr' ),
					'timer_question_message' => __( 'You need to listen to the whole audio recording before answering the question.', 'wpr' ),
					'timer_answer_message'   => __( 'You need to listen to the whole audio recording before proceeding to the next question.', 'wpr' ),
				);

				wp_localize_script(
					'wpr-quiz-timer',
					'wpr_quiz_timer_js',
					array(
						'timers'  => $quiz_timer_questions,
						'strings' => $quiz_timer_strings,
					)
				);
			}
		}
	}
}

add_action( 'wp_enqueue_scripts', 'wpr_wp_enqueue_scripts' );

/**
 * Disallow logged-in users from accessing the registration page.
 *
 * @param string $url
 *
 * @return string
 */
function wpr_template_redirect( $url ) {
	if ( ! is_page( 'create-account' ) || ! is_user_logged_in() ) {
		return;
	}

	if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
		$url = wc_get_page_permalink( 'myaccount' );
	} else {
		$url = home_url();
	}

	wp_safe_redirect( $url );
	die;
}

add_action( 'template_redirect', 'wpr_template_redirect' );

/**
 * Add the course product to cart after registering and redirect to checkout page.
 *
 * @param string|array $confirmation
 * @param object       $form
 * @param object       $entry
 * @param bool         $ajax
 *
 * @return array
 */
function wpr_gform_confirmation( $confirmation, $form, $entry, $ajax ) {
	$register_form = intval( get_option( 'wpr_courses_settings_gf_reg_id', 1 ) );
	if ( $register_form === $form['id'] && is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
		// This is the registration form.
		// Get the course product ID.
		$product_id = intval( get_option( 'wpr_courses_settings_product_id' ) );

		if ( ! empty( $product_id ) ) {
			// Add course product to cart.
			WC()->cart->add_to_cart( $product_id );

			// Redirect to checkout page.
			$confirmation = array(
				'redirect' => get_permalink( wc_get_page_id( 'checkout' ) ),
			);
		}
	}

	return $confirmation;
}

add_action( 'gform_confirmation', 'wpr_gform_confirmation', 10, 4 );

/**
 * Auto login to site after GF User Registration.
 */
function wpr_gform_user_registered( $user_id, $config, $entry, $password ) {
	wp_set_auth_cookie( $user_id );
}

add_action( 'gform_user_registered', 'wpr_gform_user_registered', 10, 4 );

/**
 * Display review page before final form submission.
 *
 * @param array  $review_page
 * @param object $form
 * @param object $entry
 *
 * @return array
 */
function wpr_gform_review_page( $review_page, $form, $entry ) {
	$register_form = intval( get_option( 'wpr_courses_settings_gf_reg_id', 1 ) );
	if ( $register_form === $form['id'] ) {
		// Enable the review page
		$review_page['is_enabled'] = TRUE;

		// Populate the review page
		$review_page['content'] = GFCommon::replace_variables( '{all_fields}', $form, $entry );

		// Change button text.
		$review_page['nextButton']['text'] = __( 'Review Information', 'wpr' );
	}

	return $review_page;
}

add_filter( 'gform_review_page', 'wpr_gform_review_page', 10, 3 );

/**
 * Add custom user details
 *
 * @param [type] $user
 *
 * @return void
 */
function wpr_witobaccocheck_status( $user ) {
	$current_user = wp_get_current_user();

	if ( in_array( 'administrator', $current_user->roles ) ) {

		$cert_details = get_user_meta( $user->ID, 'cabl_cert_info', TRUE );
		echo '<div class="cabl_cert_status">Status: ' . get_user_meta( $user->ID, 'cabl_cert_status', TRUE ) . '</div>';
		if ( ! empty( $cert_details ) ) {
			$details = json_decode( $cert_details );
			echo '<div>';
			echo '<pre>';
			echo print_r( $details );
			echo '</div>';
		}
	}
}

add_action( 'show_user_profile', 'wpr_witobaccocheck_status', 99 );
add_action( 'edit_user_profile', 'wpr_witobaccocheck_status', 99 );

/**
 * Update custom user details
 *
 * @param [type] $user_id
 *
 * @return void
 */
function wpr_witobaccocheck_status_action( $user_id ) {
	$current_user = wp_get_current_user();

	if ( in_array( 'administrator', $current_user->roles ) ) {
		if ( isset( $_POST['wpr_witobaccocheck_status'] ) ) {
			$value = sanitize_text_field( $_POST['wpr_witobaccocheck_status'] );
			update_user_meta( $user_id, '_wpr_witobaccocheck_status', $value );
		}
		if ( isset( $_POST['_wpr_witobaccocheck_date'] ) ) {
			$value = sanitize_text_field( $_POST['_wpr_witobaccocheck_date'] );
			update_user_meta( $user_id, '_wpr_witobaccocheck_date', $value );
		}
	}
}

add_action( 'personal_options_update', 'wpr_witobaccocheck_status_action', 99 );
add_action( 'edit_user_profile_update', 'wpr_witobaccocheck_status_action', 99 );

/**
 * Set U.S. as the default checkout country.
 *
 * @return string
 */
function wpr_default_checkout_country() {
	return 'US';
}

add_filter( 'default_checkout_country', 'wpr_default_checkout_country' );

/**
 * Populate the checkout state.
 *
 * @return string
 */
function wpr_default_checkout_state() {
	$user = wp_get_current_user();

	if ( ! empty( $user->billing_state ) ) {
		return $user->billing_state;
	}

	return '';
}

add_filter( 'default_checkout_state', 'wpr_default_checkout_state' );

/**
 * User short state names in the state dropdown.
 *
 * @param array $states
 *
 * @return array
 */
function wpr_us_states( $states ) {
	$short_state = array();

	foreach ( $states as $state ) {
		$short_state[ GF_Fields::get( 'address' )->get_us_state_code( $state ) ] = $state;
	}

	return $short_state;
}

add_filter( 'gform_us_states', 'wpr_us_states' );

/**
 * Generate unique usernames for registering users.
 *
 * @param string $username
 *
 * @return string
 */
function wpr_gform_user_registration_username( $username ) {
	// $user_add = wp_generate_password( 12, false ) );
	$min_number = 1;
	$max_number = 9999;
	$user_add   = rand( $min_number, $max_number );
	$str_length = strlen( (string) $max_number );
	$user_add   = substr( str_repeat( 0, $str_length ) . $user_add, - $str_length );
	return sanitize_user( sanitize_title( $username . ' ' . $user_add ) );
}

add_filter( 'gform_user_registration_username', 'wpr_gform_user_registration_username' );


/*
 * NOTE:  THIS IS WHERE THE POST to the API HAPPENS
 */
function wpr_send_trainee_data_fliped( $quiz_data, $user_id ) {
	/*
   * FIRE ON QUIZ 5
   */
	// Fires when quiz is marked complete
	// @help: https://developers.learndash.com/hook/wp_pro_quiz_completed_quiz/
	$final_quiz_id = get_option( 'wpr_courses_settings_quiz_final_id', TRUE );
	if ( (int) $quiz_data['quiz'] === (int) $final_quiz_id ) {
		update_user_meta( $user_id->ID, '_wpr_witobaccocheck_date', gmdate( 'Y/m/d' ) );
	}
}

// @help: https://developers.learndash.com/hook/learndash_quiz_submitted/
add_action( 'learndash_quiz_submitted', 'wpr_send_trainee_data_fliped', 1000, 2 );


/**
 * @param $user
 *
 * @return false|void
 */
function wpr_show_security_fields( $user ) {
	wp_enqueue_script( 'jquery-ui-datepicker' );
	wp_register_style( 'jquery-ui', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/themes/base/jquery-ui.css' );
	wp_enqueue_style( 'jquery-ui' );

	if ( ! current_user_can( 'edit_user', $user->ID ) ) {
		return FALSE;
	}

	?>
  <h3><?php _e( 'Security details' ); ?></h3>
  <table class="form-table">
    <tr>
      <th><label for="_wpr_mfn">Mother's first name</label></th>
      <td>
        <input type="text" name="_wpr_mfn" id="_wpr_mfn" value="<?php echo get_user_meta( $user->ID, '_wpr_mfn', TRUE ); ?>" class="regular-text"/><br/>
      </td>
    </tr>
    <tr>
      <th><label for="_wpr_ssn">Server ID #</label></th>
      <td>
        <input type="text" name="_wpr_ssn" id="_wpr_ssn" value="<?php echo get_user_meta( $user->ID, '_wpr_ssn', TRUE ); ?>" class="regular-text"/><br/>
      </td>
    </tr>
    <tr>
      <th><label for="_wpr_dob">Birth date</label></th>
      <td>
		  <?php
		  $dob = get_user_meta( $user->ID, '_wpr_dob', TRUE );
		  if ( ! empty( $dob ) ) {
			  $dob = date_create( $dob );
		  }
		  $value = ( empty( $dob ) ) ? '' : date_format( $dob, 'm/d/Y' );
		  ?>
        <input type="text" name="_wpr_dob" id="wpr_dob" value="<?php echo $value; ?>" class="regular-text"/><br/>
        <span><em>Eg.: 04/24/1994</em></span>
        <script type="text/javascript">
          jQuery( document ).ready( function( $ ) {
            $( "#wpr_dob" ).datepicker( {
              minDate: "-100Y",
              dateFormat: "mm/dd/yy"
            } );
			  <?php
			  if ( ! empty( $value ) ) {
				  echo "$( '#wpr_dob' ).datepicker('setDate', '" . $value . "');";
			  }
			  ?>
          } );
        </script>
      </td>
    </tr>
  </table>
	<?php
}

add_action( 'show_user_profile', 'wpr_show_security_fields', 10 );
add_action( 'edit_user_profile', 'wpr_show_security_fields', 10 );

/**
 * @param $user_id
 *
 * @return false|void
 */
function save_security_fields( $user_id ) {
	if ( ! current_user_can( 'edit_user', $user_id ) ) {
		return FALSE;
	}

	if ( isset( $_POST['_wpr_middle_name'] ) ) {
		$gf_entry = get_user_meta( $user_id, '_gform-entry-id', TRUE );
		gform_update_meta( $gf_entry, '1.4', $_POST['_wpr_middle_name'] );
	}

	update_user_meta( $user_id, '_wpr_mfn', $_POST['_wpr_mfn'] );
	update_user_meta( $user_id, '_wpr_ssn', $_POST['_wpr_ssn'] );
	$value = '';
	if ( ! empty( $_POST['_wpr_dob'] ) ) {
		$dob   = date_create( $_POST['_wpr_dob'] );
		$value = date_format( $dob, 'Y-m-d' );
	}
	update_user_meta( $user_id, '_wpr_dob', $value );
}

/*
 * TODO: Combine these with the established hooks already set above
 */
add_action( 'personal_options_update', 'save_security_fields' );
add_action( 'edit_user_profile_update', 'save_security_fields' );

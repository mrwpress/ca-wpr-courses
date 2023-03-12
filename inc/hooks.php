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
	$column_headers['ca_api_status']        = 'API Send Status';
	$column_headers['ca_course_expiration'] = 'Course Expiration';
	$column_headers['ca_final_quiz_taken']  = 'Final Quiz';
	return $column_headers;
}

add_action( 'manage_users_custom_column', 'cabl_user_posts_count_column_content', 10, 3 );
function cabl_user_posts_count_column_content( $value, $column_name, $user_id ) {
	if ( 'ca_api_status' == $column_name ) {
		$data  = get_user_meta( $user_id, 'cabl_cert_info', TRUE ) ? json_decode( get_user_meta( $user_id, 'cabl_cert_info', TRUE ) ) : NULL;
		$value = ! $data || empty( $data->mostRecentTraining ) ? 'N/A' : $data->mostRecentTraining->status;
	}

	if ( 'ca_course_expiration' == $column_name ) {
		$date  = new DateTime();
		$value = ld_course_access_expires_on( CABL_COURSE_ID, $user_id );
		if ( ! $value ) {
			return 'N/A';
		}
		$formatted = $date->setTimestamp( $value );
		$value     = date_format( $formatted, 'F jS, Y' );
	}

	if ( 'ca_final_quiz_taken' == $column_name ) {
		$value = 'Incomplete';
		if ( learndash_user_quiz_has_completed( $user_id, CABL_FINAL_QUIZ_ID, CABL_COURSE_ID ) ) {
			$value = 'Complete';
		}
	}

	return $value;
}

add_action( 'admin_init', 'my_admin_init' );
function my_admin_init() {

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
		$status = wpr_courses_get( $result, 'certifiedStatus', '' );

		$cert_date = wpr_courses_get( $result, 'certifiedToDate', 0 );
		update_user_meta( $user_id, CABL_CERT_STATUS, $status );
		update_user_meta( $user_id, CABL_CERT_INFO, curl_exec( $ch ) );
		update_user_meta( $user_id, CABL_CERT_DATE, $cert_date );
	}
}

//add_filter( 'cron_schedules', 'ca_cert_add_cron_interval' );
function ca_cert_add_cron_interval( $schedules ) {
	$schedules['five_minute'] = array(
		'interval' => 300,
		'display'  => esc_html__( 'Every Five Minutes' ),
	);
	return $schedules;
}

if ( ! wp_next_scheduled( 'ca_cron_task_hook' ) ) {
	wp_schedule_event( time(), 'five_minute', 'ca_cron_task_hook' );
}
add_action( 'ca_cron_task_hook', 'ca_process_user_cert_data' );

function ca_process_user_cert_data() {
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
function cabl_extend_user_profile( $user ) {
	$current_user = wp_get_current_user();

	if ( in_array( 'administrator', $current_user->roles ) ) {

		echo '<div class="cabl_cert_status">Status: ' . get_user_meta( $user->ID, CABL_CERT_STATUS, TRUE ) . '</div>';
		echo '<div class="admin_manual_send"><p><label><input type="checkbox" name="cabl_manual_process_cert_auth"> Send Authorization to CA Certification Site</label></p></div>';

	}
}

add_action( 'show_user_profile', 'cabl_extend_user_profile', 99 );
add_action( 'edit_user_profile', 'cabl_extend_user_profile', 99 );

/**
 * Update custom user details
 *
 * @param [type] $user_id
 *
 * @return void
 */
function cabl_update_user_data( $user_id ) {
	$current_user = wp_get_current_user();

	if ( in_array( 'administrator', $current_user->roles ) ) {
		$manual_send = wpr_safe_request( 'cabl_manual_process_cert_auth', FALSE );
		if ( $manual_send ) {
			$user_id = wpr_safe_request( 'user_id', 0 );
			if ( $user_id ) {
				ca_post_to_california( $user_id );
			}
		}
	}
}

add_action( 'personal_options_update', 'cabl_update_user_data', 99 );
add_action( 'edit_user_profile_update', 'cabl_update_user_data', 99 );

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
function cabl_default_checkout_state() {
	$user = wp_get_current_user();

	if ( ! empty( $user->billing_state ) ) {
		return $user->billing_state;
	}

	return '';
}

add_filter( 'default_checkout_state', 'cabl_default_checkout_state' );

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
function cabl_after_quiz_submitted( $quiz_data, $user ) {
	// Fires when quiz is marked complete
	// @help: https://developers.learndash.com/hook/wp_pro_quiz_completed_quiz/
	$data_sent = (bool) get_user_meta( $user->ID, CABL_QUIZ_COMPLETE_KEY, TRUE );
	if ( (int) $quiz_data['quiz'] === CABL_FINAL_QUIZ_ID ) {
		ca_post_to_california( $user->ID );
		update_user_meta( $user->ID, CABL_QUIZ_COMPLETE_KEY, time() );
	}
}

// @help: https://developers.learndash.com/hook/learndash_quiz_submitted/
add_action( 'learndash_quiz_submitted', 'cabl_after_quiz_submitted', 1000, 2 );

function ca_post_to_california( $user_id ) {

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

	$url = 'https://api-services.abc.ca.gov/servers/' . $server_id . '/lastnames/' . $last_name . '/providers/' . PROVIDER_ID . '/programs/' . PROGRAM_ID;


//Create a cURL handle.
	$ch = curl_init( $url );

//Create an array of custom headers.
	$customHeaders = [
		'X-API-Key: ' . ABC_API_KEY
	];

//Use the CURLOPT_HTTPHEADER option to use our
//custom headers.
	curl_setopt( $ch, CURLOPT_POST, TRUE );
	curl_setopt( $ch, CURLOPT_HTTPHEADER, $customHeaders );

//Set options to follow redirects and return output
//as a string.
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, TRUE );
	curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, TRUE );

//Execute the request.
	$raw_data = curl_exec( $ch );
	if ( ! empty( $raw_data ) ) {
		$result       = json_decode( $raw_data );
		$success      = [ 200, 201 ];
		$prefix       = 'ERROR: ';
		$column_value = 'ERROR';
		if ( in_array( $result->code, $success ) ) {
			$prefix       = 'SUCCESS: ';
			$column_value = 'SUCCESS';
		}

		$api_log   = new WP_Logging();
		$content   = '<p><a href="' . get_edit_user_link( $user_id ) . '">Edit User</a></p>' . $raw_data;
		$insert_id = $api_log::add( $prefix . 'User ID:' . $user_id . ' Code: ' . $result->code, $content );
		update_post_meta( $insert_id, 'cabl_api_code', $column_value );

	}
}


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

add_action( 'personal_options_update', 'save_security_fields' );
add_action( 'edit_user_profile_update', 'save_security_fields' );

/**
 * Redirect to course page after successful checkout.
 *
 * @param int $order_id
 */
function wpr_woocommerce_thankyou( $order_id ) {
	$order = new WC_Order( $order_id );

	if ( 'failed' !== $order->status ) {
		$course_arr = get_post_meta( CABL_COURSE_PROD_ID, '_related_course', TRUE );

		if ( is_array( $course_arr ) ) {
			$course_url = get_permalink( intval( $course_arr[0] ) );
			wp_safe_redirect( $course_url );
			die;
		}
	}
}

add_action( 'woocommerce_thankyou', 'wpr_woocommerce_thankyou' );

// Add the custom columns to the book post type:
add_filter( 'manage_wp_log_posts_columns', 'set_custom_edit_wp_log_columns' );
function set_custom_edit_wp_log_columns( $columns ) {
	$columns['api_response'] = __( 'API Response', 'your_text_domain' );

	return $columns;
}

// Add the data to the custom columns for the book post type:
add_action( 'manage_wp_log_posts_custom_column', 'custom_wp_log_column', 10, 2 );
function custom_wp_log_column( $column, $post_id ) {
	if ( 'api_response' == $column ) {
		echo get_post_meta( $post_id, 'cabl_api_code', TRUE );
	}
}
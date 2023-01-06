<?php
/**
 * Hooks.
 *
 * @package     WPR
 * @author      Alex Nitu
 * @copyright   2018 WPRiders
 * @license     GPL-2.0+
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

function wpr_change_print_certificate_label( $label ) {
	return __( 'Print Responsible Beverage Course Certificate', 'wpr' );
}

add_filter( 'ld_certificate_link_label', 'wpr_change_print_certificate_label' );

function wpr_learndash_quizinfo( $attr ) {
	global $learndash_shortcode_used;
	$learndash_shortcode_used = TRUE;

	$shortcode_atts = shortcode_atts(
		array(
			'show'    => '',
			// [score], [count], [pass], [rank], [timestamp], [pro_quizid], [points], [total_points], [percentage], [timespent]
			'user_id' => '',
			'quiz'    => '',
			'time'    => '',
			'format'  => 'F j, Y, g:i a',
		),
		$attr
	);

	extract( $shortcode_atts );

	$time      = ( empty( $time ) && isset( $_REQUEST['time'] ) ) ? $_REQUEST['time'] : $time;
	$show      = ( empty( $show ) && isset( $_REQUEST['show'] ) ) ? $_REQUEST['show'] : $show;
	$quiz      = ( empty( $quiz ) && isset( $_REQUEST['quiz'] ) ) ? $_REQUEST['quiz'] : $quiz;
	$user_id   = ( empty( $user_id ) && isset( $_REQUEST['user_id'] ) ) ? $_REQUEST['user_id'] : $user_id;
	$course_id = ( empty( $course_id ) && isset( $_REQUEST['course_id'] ) ) ? $_REQUEST['course_id'] : NULL;

	if ( empty( $user_id ) ) {
		$user_id = get_current_user_id();

		/**
		 * Added logic to allow admin and group_leader to view certificate from other users.
		 *
		 * @since 2.3
		 */
		$post_type = '';
		if ( get_query_var( 'post_type' ) ) {
			$post_type = get_query_var( 'post_type' );
		}

		if ( $post_type == 'sfwd-certificates' ) {
			if ( ( ( learndash_is_admin_user() ) || ( learndash_is_group_leader_user() ) ) && ( ( isset( $_GET['user'] ) ) && ( ! empty( $_GET['user'] ) ) ) ) {
				$user_id = intval( $_GET['user'] );
			}
		}
	}

	if ( empty( $quiz ) || empty( $user_id ) || empty( $show ) ) {
		return '';
	}

	$quizinfo = get_user_meta( $user_id, '_sfwd-quizzes', TRUE );

	$selected_quizinfo  = '';
	$selected_quizinfo2 = '';

	foreach ( $quizinfo as $quiz_i ) {

		if ( isset( $quiz_i['time'] ) && $quiz_i['time'] == $time && $quiz_i['quiz'] == $quiz ) {
			$selected_quizinfo = $quiz_i;
			break;
		}

		if ( $quiz_i['quiz'] == $quiz ) {
			$selected_quizinfo2 = $quiz_i;
		}
	}

	$selected_quizinfo = empty( $selected_quizinfo ) ? $selected_quizinfo2 : $selected_quizinfo;

	switch ( $show ) {
		case 'timestamp':
			date_default_timezone_set( get_option( 'timezone_string' ) );
			$selected_quizinfo['timestamp'] = date_i18n( $format, ( $selected_quizinfo['time'] + get_option( 'gmt_offset' ) * 3600 ) );
			break;

		case 'percentage':
			if ( empty( $selected_quizinfo['percentage'] ) ) {
				$selected_quizinfo['percentage'] = empty( $selected_quizinfo['count'] ) ? 0 : $selected_quizinfo['score'] * 100 / $selected_quizinfo['count'];
			}

			break;

		case 'pass':
			$selected_quizinfo['pass'] = ! empty( $selected_quizinfo['pass'] ) ? esc_html__( 'Yes', 'learndash' ) : esc_html__( 'No', 'learndash' );
			break;

		case 'quiz_title':
			$quiz_post = get_post( $quiz );

			if ( ! empty( $quiz_post->post_title ) ) {
				$selected_quizinfo['quiz_title'] = $quiz_post->post_title;
			}

			break;

		case 'course_title':
			if ( ( isset( $selected_quizinfo['course'] ) ) && ( ! empty( $selected_quizinfo['course'] ) ) ) {
				$course_id = intval( $selected_quizinfo['course'] );
			} else {
				$course_id = learndash_get_setting( $quiz, 'course' );
			}
			if ( ! empty( $course_id ) ) {
				$course = get_post( $course_id );
				if ( ( is_a( $course, 'WP_Post' ) ) && ( ! empty( $course->post_title ) ) ) {
					$selected_quizinfo['course_title'] = $course->post_title;
				}
			}

			break;

		case 'timespent':
			$selected_quizinfo['timespent'] = isset( $selected_quizinfo['timespent'] ) ? learndash_seconds_to_time( $selected_quizinfo['timespent'] ) : '';
			break;

	}

	if ( isset( $selected_quizinfo[ $show ] ) ) {
		return apply_filters( 'learndash_quizinfo', $selected_quizinfo[ $show ], $shortcode_atts );
	} else {
		return apply_filters( 'learndash_quizinfo', '', $shortcode_atts );
	}
}

if ( FALSE !== strpos( $_SERVER['REQUEST_URI'], 'certificates/ramp-certificate' ) ) {
	remove_shortcode( 'quizinfo' );
	add_shortcode( 'quizinfo', 'wpr_learndash_quizinfo' );
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
		if ( is_singular( 'sfwd-lessons' ) && wpr_should_load_inquisitor() ) {
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
 * Check whether to load the inquisitor script in order to ask security questions.
 *
 * @return bool
 */
function wpr_should_load_inquisitor() {
	$course_id = learndash_get_course_id( get_the_ID() );
	if ( empty( $course_id ) ) {
		// Course ID not found.
		return FALSE;
	}

	$in_progress = empty( learndash_user_get_course_completed_date( $course_id ) );
	if ( ! $in_progress ) {
		// Course is not in progress.
		return FALSE;
	}

	$inquisitor          = wpr_get_user_inquisitor();
	$remaining_questions = count( $inquisitor['questions'] );
	if ( 0 === $remaining_questions ) {
		// No more questions to ask.
		return FALSE;
	}

	$course_obj     = new LDLMS_Course_Steps( $course_id );
	$all_lessons    = $course_obj->get_steps_count();
	$current_lesson = wpr_get_current_lesson();
	if ( NULL === $current_lesson ) {
		// Could not get number of complete lessons.
		return FALSE;
	}

	$remaining_lessons = $all_lessons - $current_lesson;

	$range_min = max( $current_lesson, 1 );
	$range_max = min( max( intval( ceil( $remaining_lessons / $remaining_questions ) ), $range_min ), $all_lessons );

	// Lesson index at which to load inquisitor.
	$load_at_lesson = rand( $range_min, $range_max );

	$load = ( ( $current_lesson === $load_at_lesson ) || $current_lesson >= $range_max || $current_lesson < $range_min );

	return $load;
}

/**
 * Get the index of the current lesson being viewed.
 *
 * @return null|int
 */
function wpr_get_current_lesson() {
	global $post;

	$posts = learndash_get_lesson_list( NULL, array( 'num' => 0 ) );

	$current_index = NULL;

	foreach ( $posts as $index => $p ) {
		if ( ! $p instanceof WP_Post ) {
			continue;
		}

		if ( $p->ID === $post->ID ) {
			$current_index = $index;
			break;
		}
	}

	return $current_index;
}

/**
 * Get inquisitor status and questions for the user.
 *
 * @param int|null $user_id
 *
 * @return array
 */
function wpr_get_user_inquisitor( $user_id = NULL ) {
	if ( empty( $user_id ) ) {
		$user_id = get_current_user_id();
	}

	$inquisitor = get_user_meta( get_current_user_id(), '_wpr_inquisitor', TRUE );

	if ( empty( $inquisitor ) || ! is_array( $inquisitor ) ) {
		$inquisitor = wpr_set_user_inquisitor();
	}

	return $inquisitor;
}

/**
 * Set/update inquisitor status and questions for the user.
 *
 * @param int|null   $user_id
 * @param array|null $inquisitor
 *
 * @return array
 */
function wpr_set_user_inquisitor( $user_id = NULL, $inquisitor = NULL ) {
	if ( empty( $user_id ) ) {
		$user_id = get_current_user_id();
	}

	if ( empty( $inquisitor ) ) {
		$dob_date   = date_create( trim( get_user_meta( $user_id, '_wpr_dob', TRUE ) ) );
		$inquisitor = array(
			'asked'     => array(),
			'questions' => array(
				esc_html__( 'What is your mother\'s first name?', 'wpr' )                          => trim( get_user_meta( $user_id, '_wpr_mfn', TRUE ) ),
				esc_html__( 'Please re-enter the last 4 digits of your social security #', 'wpr' ) => trim( get_user_meta( $user_id, '_wpr_ssn', TRUE ) ),
				esc_html__( 'What is your birth date? (MM/DD/YYYY)', 'wpr' )                       => date_format( $dob_date, 'm/d/Y' ),
			),
		);
	}

	update_user_meta( $user_id, '_wpr_inquisitor', $inquisitor );

	return $inquisitor;
}

/**
 * Check if the user needs to answer security questions, and if so, send the question and answer as JSON data.
 */
function wpr_ajax_summon_inquisitor() {
	$inquisitor = wpr_get_user_inquisitor();

	if ( 0 === count( $inquisitor['questions'] ) ) {
		// No more questions to answer.
		wp_send_json_success( 0, 200 );
	} elseif ( is_array( $inquisitor['questions'] ) ) {
		$question = array_rand( $inquisitor['questions'] );
		$data     = array(
			'question' => $question,
			'answer'   => base64_encode( $inquisitor['questions'][ $question ] ),
			'nonce'    => wp_create_nonce( '_wpr_inquisitor_' . get_current_user_id() ),
		);
		wp_send_json_success( $data, 200 );
	} else {
		wp_send_json_error();
	}
}

add_action( 'wp_ajax_wpr_summon_inquisitor', 'wpr_ajax_summon_inquisitor' );

/**
 * Check the user's answer to the security question.
 */
function wpr_ajax_check_inquisitor_answer() {
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], '_wpr_inquisitor_' . get_current_user_id() ) ) {
		wp_send_json_error( __( 'Nonce error', 'wpr' ) );
	} elseif ( ! isset( $_POST['answer'] ) ) {
		wp_send_json_error( __( 'No answer', 'wpr' ) );
	}

	$inquisitor = wpr_get_user_inquisitor();

	$answer = trim( $_POST['answer'] );

	if ( is_array( $inquisitor['questions'] ) ) {
		$question = array_search( $answer, $inquisitor['questions'] );
		$passed   = ( FALSE !== $question );

		if ( $passed ) {
			// Mark question as asked and answered.
			$inquisitor['asked'][ $question ] = $inquisitor['questions'][ $question ];
			unset( $inquisitor['questions'][ $question ] );
			wpr_set_user_inquisitor( NULL, $inquisitor );
		}

		wp_send_json_success(
			array(
				'passed' => $passed,
			),
			200
		);
	} else {
		wp_send_json_error();
	}
}

add_action( 'wp_ajax_wpr_check_inquisitor_answer', 'wpr_ajax_check_inquisitor_answer' );

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
 * Redirect to course page after successful checkout.
 *
 * @param int $order_id
 */
function wpr_woocommerce_thankyou( $order_id ) {
	$order = new WC_Order( $order_id );

	if ( 'failed' !== $order->status ) {
		$product_id = intval( get_option( 'wpr_courses_settings_product_id' ) );
		$course_arr = get_post_meta( $product_id, '_related_course', TRUE );

		if ( is_array( $course_arr ) ) {
			$course_url = get_permalink( intval( $course_arr[0] ) );
			wp_safe_redirect( $course_url );
			die;
		}
	}
}

add_action( 'woocommerce_thankyou', 'wpr_woocommerce_thankyou' );

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

	wpr_set_user_inquisitor( $user_id );
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
 * Check for Learndash template override on plugin template
 *
 * @param [type] $filepath
 * @param [type] $name
 * @param [type] $args
 * @param [type] $echo
 * @param [type] $return_file_path
 *
 * @return void
 */
function wpr_ld_override_template( $filepath, $name, $args, $echo, $return_file_path ) {
	$new_path      = WPR_COURSES_DIR . 'learndash/' . $name;
	$file_pathinfo = pathinfo( $new_path );
	if ( ( ! isset( $file_pathinfo['extension'] ) ) || ( empty( $file_pathinfo['extension'] ) ) ) {
		$new_path .= '.php';
	}

	if ( file_exists( $new_path ) ) {
		return $new_path;
	} else {
		return $filepath;
	}
}

add_filter( 'learndash_template', 'wpr_ld_override_template', 10, 5 );

function get_third_party_certificate_link() {
	$redirect_link = get_home_url();
	if ( is_user_logged_in() ) {
		$current_user = wp_get_current_user();
		$key          = md5( $current_user->user_email . '-' . $current_user->ID );

		$redirect_link = get_option( 'wpr_courses_settings_redirect_link' );
		$redirect_link = add_query_arg(
			[
				'witobaccocheck' => 'true',
				'hash'           => $key,
				'user_id'        => $current_user->ID
			],
			$redirect_link
		);
	}

	return $redirect_link;
}

/**
 * Get site link to certificate
 *
 * @param [type] $user_id
 *
 * @return void
 */
function get_site_certificate_link( $user_id ) {
	$course_id      = get_option( 'wpr_courses_settings_course_id' );
	$quiz_id        = get_option( 'wpr_courses_settings_quiz_id' );
	$certificate_id = get_option( 'wpr_courses_settings_certificate_id' );

	$cert_nonce = wp_create_nonce( $quiz_id . $user_id . $user_id );

	$cert_query_args = array(
		'quiz'       => $quiz_id,
		'user'       => $user_id,
		'cert-nonce' => $cert_nonce,
	);

	$url = add_query_arg( $cert_query_args, get_permalink( $certificate_id ) );
	return $url;
}

/**
 * Remove link to certificate from users
 *
 * @param [type] $certificateLink
 * @param [type] $certificate_post
 * @param [type] $post
 * @param [type] $cert_user_id
 *
 * @return void
 */
function wpr_remove_certificate_link_no_pass_third_party( $certificate_link ) {
	if ( ! isset( $_GET['trainingId'] ) ) {
		$cert_user             = wp_get_current_user();
		$witobaccocheck_status = get_user_meta( $cert_user->ID, '_wpr_witobaccocheck_status', 'false' );
		$course_id             = get_option( 'wpr_courses_settings_course_id' );
		$completed_on          = get_user_meta( $cert_user->ID, 'course_completed_' . $course_id, TRUE );

		if ( in_array( 'administrator', $cert_user->roles ) || in_array( 'customer', $cert_user->roles ) ) {
			return $certificate_link;
		} elseif ( 'false' == $witobaccocheck_status || empty( $completed_on ) ) {
			return '';
		} else {
			return $certificate_link;
		}
	} else {
		return $certificate_link;
	}
}

add_filter( 'learndash_certificate_details_link', 'wpr_remove_certificate_link_no_pass_third_party', 99, 1 );

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
		$witobaccocheck_status = get_user_meta( $user->ID, '_wpr_witobaccocheck_status', 'false' );
		// var_dump($witobaccocheck_status); die();
		?>
      <!--
		<h3><?php esc_html_e( 'Witobaccocheck Test Done', 'wpr' ); ?></h3>
		<div>
			<select name="wpr_witobaccocheck_status">
				<option value="true"
				<?php
	  if ( 'true' === $witobaccocheck_status ) {
		  echo ' selected="selected"';
	  }
	  ?>
				>Done</option>
				<option value="false"
				<?php
	  if ( '' === $witobaccocheck_status || 'false' === $witobaccocheck_status ) {
		  echo ' selected="selected"';
	  }
	  ?>
				>NOT Done</option>
			</select>
		</div>
		<br />
		-->
      <h3><?php esc_html_e( 'Test Done DATE', 'wpr' ); ?></h3>
      <div>
        <input name="_wpr_witobaccocheck_date" value="
			<?php
		$value = get_user_meta( $user->ID, '_wpr_witobaccocheck_date', '' );
		echo $value[0];
		?>
			"/>
      </div>
		<?php
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
 * Webhook to catch site return
 *
 * @param [type] $query
 *
 * @return void
 */
function wpr_catch_witobaccocheck_callback() {

	if ( isset( $_REQUEST['witobaccocheck'] ) && 'true' == $_REQUEST['witobaccocheck'] ) {
		if ( ( isset( $_REQUEST['trainingId'] ) && '' != $_REQUEST['trainingId'] ) || ( isset( $_REQUEST['hash'] ) && '' != $_REQUEST['hash'] ) || ( isset( $_REQUEST['user_id'] ) && '' != $_REQUEST['user_id'] ) ) {

			$training_id = ( isset( $_REQUEST['trainingId'] ) ? $_REQUEST['trainingId'] : '' );
			if ( '' == $training_id ) {
				WP_Logging::add( __( 'Request not enought parameters', 'wpr' ) . ' @ ' . date( 'Y-m-d h:i:s' ), 'trainingId not set', 0, 'event' );
				wp_die( __( 'trainingId not set', 'wpr' ) );
			}

			$hash = ( isset( $_REQUEST['hash'] ) ? $_REQUEST['hash'] : '' );
			if ( '' == $hash ) {
				WP_Logging::add( __( 'Request not enought parameters', 'wpr' ) . ' @ ' . date( 'Y-m-d h:i:s' ), 'Hash not set', 0, 'event' );
				wp_die( __( 'Hash not set', 'wpr' ) );
			}

			$user_id = ( isset( $_REQUEST['user_id'] ) ? $_REQUEST['user_id'] : '' );
			if ( '' == $user_id ) {
				WP_Logging::add( __( 'Request not enought parameters', 'wpr' ) . ' @ ' . date( 'Y-m-d h:i:s' ), 'User id not set', 0, 'event' );
				wp_die( __( 'User id not set', 'wpr' ) );
			}

			$trainings = array( 6970, 7609 );
			if ( TRUE == in_array( $training_id, $trainings ) ) {
				$user_data = get_userdata( $user_id );
				if ( FALSE !== $user_data ) {
					$hash_test = md5( $user_data->user_email . '-' . $user_data->ID );
					if ( $hash_test === $hash ) {
						// WP_Logging::add( __( 'Hash OK', 'wpr' ) . ' @ ' . date( 'Y-m-d h:i:s' ), 'Hash: ' . $_REQUEST['hash'] .' User id: ' . $_REQUEST['user_id'] , 0, 'event' );
						update_user_meta( $user_data->ID, '_wpr_witobaccocheck_status', 'true' );
						update_user_meta( $user_data->ID, '_wpr_witobaccocheck_date', wp_date( 'm/d/Y' ) );

						// Redirect to custom page after test is done.
						$redirect_link = get_option( 'wpr_courses_settings_redirect_link_after_tobacco' );
						if ( ! empty( $redirect_link ) ) {
							if ( is_numeric( $redirect_link ) ) {
								$redirect_link = get_permalink( $redirect_link );
							}
							wp_redirect( $redirect_link );
						} else {
							// GENERATE CERTIFICATE
							$url = get_site_certificate_link( $user_data->ID );
							wp_redirect( $url );
						}
						exit;

					} else {
						WP_Logging::add( __( 'Hash not matching', 'wpr' ) . ' @ ' . date( 'Y-m-d h:i:s' ), 'Incorrect hash sent: ' . $_REQUEST['hash'] . ' User id: ' . $_REQUEST['user_id'], 0, 'event' );
						wp_die( __( 'Incorrect hash sent', 'wpr' ) );
					}
				} else {
					WP_Logging::add( __( 'User not found', 'wpr' ) . ' @ ' . date( 'Y-m-d h:i:s' ), 'Incorrect user sent: ' . $_REQUEST['user_id'], 0, 'event' );
					wp_die( __( 'Incorrect user sent', 'wpr' ) );
				}
			} else {
				WP_Logging::add( __( 'Training id not matching', 'wpr' ) . ' @ ' . date( 'Y-m-d h:i:s' ), 'Training id not matching', 0, 'event' );
				wp_die( __( 'Training id not matching', 'wpr' ) );
			}
		} else {
			WP_Logging::add( __( 'Request not enought parameters', 'wpr' ) . ' @ ' . date( 'Y-m-d h:i:s' ), 'No parameters sent', 0, 'event' );
			wp_die( __( 'No parameters sent', 'wpr' ) );
		}
	}

}

add_action( 'template_redirect', 'wpr_catch_witobaccocheck_callback' );

/**
 * Add custom forced timer fields to the question and answer LearnDash quiz fields.
 *
 * @param string $the_editor
 *
 * @return string
 */
function wpr_add_quiz_timers( $the_editor ) {
	if ( strpos( $the_editor, 'id="wp-question-editor-container"' ) !== FALSE ) {
		// This is the question editor, add our question timer field.
		$timer_type = 'question';
	} elseif ( strpos( $the_editor, 'id="wp-correctMsg-editor-container"' ) !== FALSE ) {
		// This is the answer editor, add our answer timer field.
		$timer_type = 'answer';
	} else {
		return $the_editor;
	}

	$post_id     = ( ! empty( $_GET['post_id'] ) ) ? intval( $_GET['post_id'] ) : 0;
	$question_id = ( ! empty( $_GET['questionId'] ) ) ? intval( $_GET['questionId'] ) : 0;

	if ( $post_id && $question_id ) {
		$question_timers = get_post_meta( $post_id, '_wpr_question_timers', TRUE );

		if ( ! is_array( $question_timers ) ) {
			$question_timers = array();
		}

		$timer_field = function ( $id ) use ( $timer_type, $question_id, $question_timers ) {
			$field_id   = "wpr_quiz_forced_{$id}_time";
			$field_val  = ( isset( $question_timers[ $question_id ][ $timer_type ] ) ) ? $question_timers[ $question_id ][ $timer_type ] : '';
			$field_desc = ( 'question' === $id ) ? __( 'Minimum time a user has to spend on quiz question page before being able to answer.', 'wpr' ) : __( 'Minimum time a user has to spend on the quiz question page after answering and before being able to continue.', 'wpr' );
			$field_desc .= ' ' . __( 'Examples: 40 (for 40 seconds), 20s, 45sec, 2m 30s, 2min 30sec, 1h 5m 10s, 1hr 5min 10sec' );
			/* translators: %s: "Question" or "Answer". */
			$field_label = sprintf( esc_html__( 'Forced %s Timer', 'wpr' ), ucfirst( $id ) );

			return '<div class="sfwd_input" id="' . esc_attr( $field_id ) . '">' .
			       '<span class="sfwd_option_label" style="text-align:right;vertical-align:top;">' .
			       '<a class="sfwd_help_text_link" style="cursor:pointer;" title="' . __( 'Click for Help!' ) . '" onclick="jQuery( \'#' . $field_id . '_tip\' ).toggle();">' .
			       '<img src="' . esc_url( LEARNDASH_LMS_PLUGIN_URL . 'assets/images/question.png' ) . '">' .
			       '<label class="sfwd_label textinput">' . esc_html( $field_label ) . '</label>' .
			       '</a>' .
			       '</span>' .
			       '<span class="sfwd_option_input">' .
			       '<div class="sfwd_option_div">' .
			       '<input name="' . esc_attr( $field_id ) . '" type="text" size="57" value="' . esc_attr( $field_val ) . '">' .
			       '</div>' .
			       '<div class="sfwd_help_text_div" style="display:none" id="' . esc_attr( $field_id ) . '_tip">' .
			       '<label class="sfwd_help_text">' .
			       esc_html( $field_desc ) .
			       '</label>' .
			       '</div>' .
			       '</span>' .
			       '<p style="clear:left"></p>' .
			       '</div>';
		};

		$the_editor .= $timer_field( $timer_type );
	}

	return $the_editor;
}

add_filter( 'the_editor', 'wpr_add_quiz_timers', 10, 2 );

/**
 * Save timer values for quiz questions.
 *
 * @param int $post_id
 */
function wpr_ld_quiz_edit() {
	if (
		( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ||
		empty( $_GET['post_id'] ) ||
		empty( $_GET['questionId'] ) ||
		! current_user_can( 'edit_post', $_GET['post_id'] ) ||
		( ! isset( $_POST['wpr_quiz_forced_question_time'] ) && ! isset( $_POST['wpr_quiz_forced_answer_time'] ) )
	) {
		return;
	}

	$post = get_post( $_GET['post_id'] );

	if ( ! $post || 'sfwd-quiz' != $post->post_type ) {
		return;
	}

	$question_id = intval( $_GET['questionId'] );

	$question_timers = get_post_meta( $post->ID, '_wpr_question_timers', TRUE );

	if ( ! is_array( $question_timers ) ) {
		$question_timers = array();
	}

	$question_timers[ $question_id ]['question'] = $_POST['wpr_quiz_forced_question_time'];
	$question_timers[ $question_id ]['answer']   = $_POST['wpr_quiz_forced_answer_time'];

	update_post_meta( $post->ID, '_wpr_question_timers', $question_timers );
}

add_action( 'plugins_loaded', 'wpr_ld_quiz_edit', 0 );

/**
 * Prevent WC from overwriting the user's name when changing the billing name.
 *
 * @param object $customer
 * @param array  $data
 */
function wpr_wc_checkout_update_customer( $customer, $data ) {
	if ( ! is_user_logged_in() || is_admin() ) {
		return;
	}

	$user_id = $customer->get_id();

	// Get the WP first name and last name (if they exist).
	$user_first_name = get_user_meta( $user_id, 'first_name', TRUE );
	$user_last_name  = get_user_meta( $user_id, 'last_name', TRUE );

	if ( empty( $user_first_name ) || empty( $user_last_name ) ) {
		return;
	}

	// Set the name back to the WP one before saving to DB.
	$customer->set_first_name( $user_first_name );
	$customer->set_last_name( $user_last_name );
}

add_action( 'woocommerce_checkout_update_customer', 'wpr_wc_checkout_update_customer', 10, 2 );

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


function wpr_send_trainee_data_fliped( $quiz_id, $user_id ) {
	$final_quiz_id = get_option( 'wpr_courses_settings_quiz_final_id', TRUE );
	if ( (int) $quiz_id['quiz'] === (int) $final_quiz_id ) {
		if ( ! update_user_meta( $user_id->ID, '_wpr_witobaccocheck_date', gmdate( 'Y/m/d' ) ) ) {
			add_user_meta( $user_id->ID, '_wpr_witobaccocheck_date', gmdate( 'Y/m/d' ) );
		}
	}
}

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

	$middle_name = gform_get_meta( get_user_meta( $user->ID, '_gform-entry-id', TRUE ), '1.4' );
	?>
  <h3><?php _e( 'Security details' ); ?></h3>
  <table class="form-table">
    <tr>
      <th><label for="_wpr_middle_name">Middle Name</label></th>
      <td>
        <input type="text" name="_wpr_middle_name" id="_wpr_middle_name" value="<?php echo $middle_name; ?>" class="regular-text"/><br/>
      </td>
    </tr>
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


add_filter( 'learndash_quiz_continue_link', 'wpr_change_continue', 11, 2 );
function wpr_change_continue( $return_link, $link ) {
	$return_val        = $return_link;
	$setting_quiz_id   = get_option( 'wpr_courses_settings_quiz_id' );
	$setting_course_id = get_option( 'wpr_courses_settings_course_id' );
	$params            = [];
	$tmp               = wp_parse_url( $link );
	parse_str( $tmp['query'], $params );

	if ( $params['quiz_id'] === (string) $setting_quiz_id && $params['course_id'] === (string) $setting_course_id ) {
		$return_val = preg_replace( '/href="(.*?)"/i', 'href="' . get_third_party_certificate_link() . '"', $return_link );
	}
	return $return_val;
}

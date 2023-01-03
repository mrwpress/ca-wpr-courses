<?php

/**
 * Settings class file.
 *
 * @package     WPR
 * @author      Andrei Leca
 * @copyright   2022 WPRiders
 * @license     GPL-2.0+
 */

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit;
}

/**
 * Assets Backend
 *
 * @return void
 */
function wpr_enqueue_scripts_gravity() {
	wp_enqueue_style( 'gravity-form-css', WPR_COURSES_URI . 'assets/css/gravity-form.css', false, '1.0.0' );

	wp_enqueue_style( 'datepicker-css', '//cdnjs.cloudflare.com/ajax/libs/datepicker/1.0.10/datepicker.min.css', false, '1.0.10' );
	wp_enqueue_script(
		'datepicker-js',
		'//cdnjs.cloudflare.com/ajax/libs/datepicker/1.0.10/datepicker.min.js',
		array( 'jquery' ),
		'1.0.10',
		false
	);

	wp_enqueue_script(
		'gravity-form-js',
		WPR_COURSES_URI . 'assets/js/gravity-form.js',
		array( 'jquery' ),
		'1.0.0',
		false
	);
}
add_action( 'admin_enqueue_scripts', 'wpr_enqueue_scripts_gravity' );

/**
 * Hook: gform_export_menu
 *
 * @param array $menu_items | array menu Gravity form Import export page
 *
 * @return array $menu_items
 */
function my_custom_export_menu_item( $menu_items ) {
	$menu_items[] = array(
		'name'  => 'filter_exportation_gravity',
		'label' => __( 'Export filtered' ),
	);

	return $menu_items;
}
add_filter( 'gform_export_menu', 'my_custom_export_menu_item' );

/**
 * Hook: gform_export_page_filter_exportation_gravity
 *
 * @return void
 */
function filter_exportation_gravity() {
	if ( ! GFCommon::current_user_can_any( 'gravityforms_export_entries' ) ) {
		wp_die( 'You do not have permission to access this page' );
	}
	GFExport::page_header();

	global $wpdb;
	$results = $wpdb->get_results(
		sprintf(
			'SELECT %s.*, date_created
             FROM %s
             LEFT JOIN %s
             ON %s.entry_id = %s.id
             WHERE 1 = 1',
			$wpdb->prefix . 'gf_entry_meta',
			$wpdb->prefix . 'gf_entry_meta',
			$wpdb->prefix . 'gf_entry',
			$wpdb->prefix . 'gf_entry_meta',
			$wpdb->prefix . 'gf_entry'
		),
		OBJECT
	);

	$date_start = '2000' . gmdate( '-m-d' );
	$date_end   = null;
	if ( isset( $_POST['wpr_export_date_start'] ) && '' !== $_POST['wpr_export_date_start'] ) {
		$date_start = $_POST['wpr_export_date_start'];
	}
	if ( isset( $_POST['wpr_export_date_end'] ) ) {
		$date_end = ( '' !== $_POST['wpr_export_date_end'] ? $_POST['wpr_export_date_end'] : gmdate( 'Y-m-d' ) );
		$date_end = gmdate( 'Y-m-d', strtotime( $date_end . ' + 1 day' ) );
	}

	$final_exportation = final_exportation_from( $results, $date_start, $date_end );
	$keys              = keys_gravity_form( $results );

	$keys[] = 'final_quiz_completation_date';
	$keys[] = 'final_test_result';

	$keys = reorder_array_custom_order( $keys );

	// Front-end form filter
	print_filter_form( $keys );

	// Download section
	if ( is_admin() && isset( $_POST['wpr_filters'] ) ) {
		$filtres = $_POST['wpr_filters'];

		$rows = filtered_form_request( $filtres, $final_exportation );
		$csv  = build_csv( $rows, $filtres );

		$id_file   = sprintf( 'wpr_%s', rand( 0, 100000000000000 ) );
		$file_name = sprintf( 'export-%s.csv', $id_file );
		GFExport::write_file( $csv, $id_file );

		wpr_download_file_gravity( $file_name );
	}

	GFExport::page_footer();
}
add_action( 'gform_export_page_filter_exportation_gravity', 'filter_exportation_gravity' );

/**
 * Final exportation array from query
 *
 * @param array  $query_rows | select query result from gravity form
 * @param string $date_start | date filter yyyy-mm-dd
 * @param string $date_end   | date filter yyyy-mm-dd
 *
 * @return array $final_exportation
 */
function final_exportation_from( $query_rows, $date_start = null, $date_end = null ) {
	$ordered_array = array();
	$final         = array();

	foreach ( $query_rows as $result ) {
		$id  = $result->entry_id;
		$key = key_to_name( $result->meta_key );

		$ordered_array[ $id ]['entry_id'] = $id;
		$ordered_array[ $id ][ $key ]     = $result->meta_value;
	}

	$date_start = new DateTime( $date_start );
	$date_start = $date_start->getTimestamp();
	$date_end   = new DateTime( $date_end );
	$date_end   = $date_end->getTimestamp();

	$already_used = array();
	foreach ( $ordered_array as $row ) {
		if ( in_array( $row['email'], $already_used, true ) ) {
			continue;
		}

		$already_used[] = $row['email'];

		$user = (array) get_user_by( 'email', $row['email'] );

		if ( isset( $user['ID'] ) ) {
			$final_quiz_id = get_option( 'wpr_courses_settings_quiz_final_id', true );
			$quizs         = (array) learndash_get_user_profile_quiz_attempts( $user['ID'] );

			if ( count( (array) $quizs ) > 0 ) {
				$last_final_quiz = get_last_final_quiz( $quizs, $final_quiz_id );

				if ( is_in_range_date( $date_start, $date_end, $last_final_quiz['time'] ) ) {

					$passingpercentage = learndash_get_setting( $final_quiz_id, 'passingpercentage' );

					$final[ $row['entry_id'] ]  = $last_final_quiz;
					$final[ $row['entry_id'] ] += $row;
					$final[ $row['entry_id'] ] += (array) $user['data'];

					$final[ $row['entry_id'] ]['final_quiz_completation_date'] = gmdate( 'Y-m-d H:i:s', substr( $last_final_quiz['time'], 0, 10 ) );
					$final[ $row['entry_id'] ]['percent_exam']                 = $last_final_quiz['percentage'];
					$final[ $row['entry_id'] ]['final_test_result']            = ( (int) $last_final_quiz['percentage'] >= $passingpercentage ? 'Pass' : 'Fail' );
				}
			}
		}
	}

	return $final;
}

/**
 * Get LastFinalQuiz
 *
 * @param array $quizs         | Quiz from learndash_get_user_profile_quiz_attempts()
 * @param int   $final_quiz_id | Last Quiz id
 *
 * @return array $lastQuiz
 */
function get_last_final_quiz( $quizs, $final_quiz_id ) {
	$quizs = array_values( $quizs )[0];

	$last_time = 0;
	$last_quiz = array();

	foreach ( $quizs as $quiz ) {

		if ( (int) $final_quiz_id === (int) $quiz['quiz'] ) {

			if ( $last_time <= $quiz['completed'] ) {
				$last_time = $quiz['completed'];
				$last_quiz = $quiz;
			}
		}
	}

	return $last_quiz;
}

/**
 * Keys of Gravity form meta
 *
 * @param array $query_rows | meta query in database
 *
 * @return array $keys
 */
function keys_gravity_form( $query_rows ) {
	$keys = array();

	foreach ( $query_rows as $result ) {

		$key = key_to_name( $result->meta_key );
		if ( 'email' === $key ) {
			$user = (array) get_user_by( 'email', $result->meta_value );
			if ( isset( $user['data'] ) ) {
				$user_keys = array_keys( (array) $user['data'] );
				foreach ( $user_keys as $user_key ) {
					if ( ! in_array( $user_key, $keys, true ) ) {
						array_push( $keys, $user_key );
					}
				}
			}
		}

		if ( ! in_array( $key, $keys, true ) ) {
			array_push( $keys, $key );
		}
	}
	array_push( $keys, 'percent_exam' );

	return $keys;
}

/**
 * Date is in range
 *
 * @param int $date_start  | date converted in epoch
 * @param int $date_end    | date converted in epoch
 * @param int $comparation | comparation time in epoch
 *
 * @return bool $in_range
 */
function is_in_range_date( $date_start, $date_end, $comparation ) {
	$in_range = false;
	if ( null !== $date_start && null !== $date_end ) {
		if ( $date_start <= $comparation && $date_end >= $comparation ) {
			$in_range = true;
		}
	} else {
		if ( null !== $date_start ) {
			if ( $date_start <= $comparation ) {
				$in_range = true;
			}
		}
		if ( null !== $date_end ) {
			if ( $date_end >= $comparation ) {
				$in_range = true;
			}
		}
	}

	if ( null === $date_start && null === $date_end ) {
		$in_range = true;
	}

	return $in_range;
}

/**
 * Download file exportred
 *
 * @param $file_name | download file in gravity custom
 *
 * @return void
 */
function wpr_download_file_gravity( $file_name ) {
	$file_path = sprintf(
		'%s/wp-content/uploads/gravity_forms/export/%s',
		get_site_url(),
		$file_name
	);
	header( 'Location: ' . $file_path );
}

/**
 * Build CSV from array's
 *
 * @param array $rows    | Rows for file
 * @param array $filtres | Filter for first row and filter rows
 *
 * @return string $csv
 */
function build_csv( $rows, $filtres ) {
	$first_row = implode( ',', $filtres );
	$csv       = sprintf( "%s\n", $first_row );

	foreach ( $rows as $row ) {
		$keys_row = array_keys( $row );
		$my_row   = '';
		foreach ( $filtres as $filter ) {
			if ( in_array( $filter, $keys_row, true ) ) {
				$my_row .= sprintf( '%s,', $row[ $filter ] );
			} else {
				$my_row .= ',';
			}
		}
		$csv .= substr( $my_row, 0, -1 ) . "\n";
	}
	return $csv;
}

/**
 * Analize request for generate output
 *
 * @param array $request_array     | Filter column for csv exportation
 * @param array $final_exportation | All array generate output
 *
 * @return array $array_summed
 */
function filtered_form_request( $request_array, $final_exportation ) {
	$array_summed = array();
	foreach ( $final_exportation as $value ) {

		$row = array();
		foreach ( $request_array as $idx ) {
			if ( isset( $value[ $idx ] ) ) {
				$row[ $idx ] = $value[ $idx ];
			}
		}

		array_push( $array_summed, $row );
	}

	return $array_summed;
}

/**
 * Print array of cackbox
 *
 * @param array $elements
 *
 * @return array $elements
 */
function print_filter_form( $elements ) {     ?>
	<form method="post">
		<div>
			<input type="text" id="wpr_export_date_start" name="wpr_export_date_start" placeholder="Start date">
			<input type="text" id="wpr_export_date_end" name="wpr_export_date_end" placeholder="End date" value="<?php echo date( 'Y-m-d' ); ?>">
		</div>
		<br />

		<?php
		foreach ( $elements as $element ) {
			?>
			<div class="wpr_element_filter">
				<input id="wpr_<?php echo $element; ?>" type="checkbox" name="wpr_filters[]" value="<?php echo $element; ?>">
				<label for="wpr_<?php echo $element; ?>">
					<?php echo $element; ?>
				</label>
			</div>
			<?php
		}
		?>
		<br />
		<button type="submit" class="button large primary">Generate Report</button>
	</form>
	<?php
	return $elements;
}

/**
 * Convert meta key to relatiuve name
 * based on database informtion of plugin gravity form
 *
 * @param String $key meta_key
 *
 * @return string
 */
function key_to_name( $key ) {
	switch ( $key ) {
		case '2.1':
			return 'billing_address_1';
		break;
		case '2.3':
			return 'billing_city';
		break;
		case '2.5':
			return 'billing_postcode';
		break;
		case '2.4':
			return 'billing_state';
		break;
		case '1.3':
			return 'first_name';
		break;
		case '1.4':
			return 'middle_name';
		break;
		case '1.6':
			return 'last_name';
		break;
		case '2.6':
			return 'billing_country';
		break;
		case '3':
			return 'billing_phone';
		break;
		case '4':
			return 'email';
		break;
		case '5':
			return 'birthday';
		break;
		case '6':
			return 'ssn';
		break;
		default:
			return $key;
		break;
	}
}

/**
 * Get gravity form
 * Middle name by key
 */
function get_gravity_middlename() {
	return gform_get_meta( get_user_meta( get_current_user_id(), '_gform-entry-id', true ), '1.4' );
}
add_shortcode( 'gravity_middlename', 'get_gravity_middlename' );

/**
 * Reorder array of keys to prrint in backend
 *
 * @param array $keys
 *
 * @return array $keys
 */
function reorder_array_custom_order( $keys ) {
	for ( $i = 0; $i < count( $keys ); $i++ ) {
		if ( 'first_name' === $keys[ $i ] ) {
			$temp       = $keys[0];
			$keys[0]    = $keys[ $i ];
			$keys[ $i ] = $temp;
		}
		if ( 'middle_name' === $keys[ $i ] ) {
			$temp       = $keys[1];
			$keys[1]    = $keys[ $i ];
			$keys[ $i ] = $temp;
		}
		if ( 'last_name' === $keys[ $i ] ) {
			$temp       = $keys[2];
			$keys[2]    = $keys[ $i ];
			$keys[ $i ] = $temp;
		}
	}
	return $keys;
}

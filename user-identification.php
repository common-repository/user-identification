<?php
/*
Plugin Name: User Identification
Description: Upload the Identification file with safety.
Version: 1.0.0
Author: hirofumi2012
Author URI: https://four-dimensional-friend.com
License: GPLv2 or later
Text Domain: user-identification
*/

/**
 * Load plugin textdomain.
 */
function i12n_load_textdomain() {
	load_plugin_textdomain( 'user-identification' );
}
add_action( 'plugins_loaded', 'i12n_load_textdomain' );

define( 'I12N_DEFAULT_UPLOADS', '/uploads/identification' );

/**
 * Retrieves the current upload directory's path.
 *
 * @param  int $user_id
 *
 * @return string
 */
function i12n_upload_path( $user_id = 0 ) {
	$i12n_upload_path = I12N_DEFAULT_UPLOADS;

	$option = trim( get_option( 'i12n_upload_path', '' ) );
	if ( $option ) {
		$i12n_upload_path = path_join( I12N_DEFAULT_UPLOADS, $option );
	}

	if ( 0 < $user_id ) {
		$i12n_upload_path .= '/' . $user_id;
	}

	return $i12n_upload_path;
}

/**
 * Register and add settings.
 */
function i12n_page_init() {
	register_setting(
		'media', // Option group
		'i12n_upload_path', // Option name
		'sanitize_text_field' // Sanitize
	);

	add_settings_section(
		'user-identification', // ID
		__( 'Identification', 'user-identification' ), // Title
		'i12n_print_section_info', // Callback
		'media' // Page
	);

	add_settings_field(
		'i12n_upload_path', // ID
		__( 'Store uploads in this folder' ), // Title
		'i12n_upload_path_input', // Callback
		'media', // Page
		'user-identification' // Section
	);
}
/**
 * Print the Section text.
 */
function i12n_print_section_info() {}
/**
 * Get the settings option and print its values.
 */
function i12n_upload_path_input() {
	$i12n_upload_path = i12n_upload_path();
	$default          = sprintf( '<code>%s</code>', I12N_DEFAULT_UPLOADS );

	require 'views/settings-field.php';
}
add_action( 'admin_init', 'i12n_page_init' );

/**
 * Add plugin action links.
 *
 * @param array $links An array of plugin action links.
 */
function i12n_add_action_links( $links ) {
	$i12n_action_links = array( sprintf( '<a href="%s">%s</a>', admin_url( 'options-media.php' ), __( 'Settings' ) ) );

	return array_merge( $i12n_action_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'i12n_add_action_links' );

/**
 * Defines the content type of the form data.
 */
function i12n_enctype() {
	echo ' enctype="multipart/form-data"';
}
add_action( 'user_edit_form_tag', 'i12n_enctype' );

/**
 * Show download link for the Identification.
 *
 * @param WP_User $user A WP_User object.
 */
function i12n_link( $user ) {
	if ( $user->has_prop( 'i12n_file' ) ) {
		$filename = $user->get( 'i12n_file' );
		$url      = wp_nonce_url( admin_url( "user-edit.php?user_id=$user->ID&action=download-i12n" ), "download-i12n_$user->ID" );

		printf( '<a href="%s" download="%s">%s</a>', esc_url( $url ), esc_attr( $filename ), esc_html( $filename ) );
	}
}

/**
 * Add upload form for the Identification on the 'Profile' editing screen.
 *
 * @param WP_User $user A WP_User object.
 */
function i12n_input( $user ) {
	require 'views/input.php';
}
add_action( 'show_user_profile', 'i12n_input' );

/**
 * Add download link for the Identification on the 'Edit User' screen.
 *
 * @param WP_User $user A WP_User object.
 */
function i12n_output( $user ) {
	require 'views/output.php';
}
add_action( 'edit_user_profile', 'i12n_output' );

/**
 * Saves the file to the appropriate directory within the uploads directory.
 *
 * @param WP_Error $errors WP_Error object (passed by reference).
 * @param bool     $update Whether this is a user update.
 * @param stdClass $user   User object (passed by reference).
 */
function i12n_upload( $errors, $update, $user ) {
	$level = 0;

	$alive  = ! $errors->has_errors();
	$alive &= $update;
	$alive &= isset( $_FILES['i12n'] );
	if ( $alive ) {
		$level++;

		$file = $_FILES['i12n'];

		$alive = empty( $file['error'] );
	}

	if ( $alive ) {
		$level++;

		$alive = 0 < $file['size'];
	}

	if ( $alive ) {
		$level++;

		$alive = is_uploaded_file( $file['tmp_name'] );
	}

	if ( $alive ) {
		$level++;

		$wp_filetype     = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
		$ext             = $wp_filetype['ext'];
		$type            = $wp_filetype['type'];
		$proper_filename = $wp_filetype['proper_filename'];

		if ( $proper_filename ) {
			$file['name'] = $proper_filename;
		}

		$alive  = $type && $ext;
		$alive |= current_user_can( 'unfiltered_upload' );
	}

	if ( $alive ) {
		$level++;

		if ( empty( $type ) ) {
			$type = $file['type'];
		}

		$upload_path = i12n_upload_path( $user->ID );

		$alive = wp_mkdir_p( $upload_path );
	}

	if ( $alive ) {
		$level++;

		$filename = wp_unique_filename( $upload_path, $file['name'] );
		$new_file = $upload_path . '/' . $filename;

		$alive = @ move_uploaded_file( $file['tmp_name'], $new_file );
	}

	if ( $alive ) {
		$level++;

		@ chmod( $new_file, 0600 );

		update_user_meta( $user->ID, 'i12n_file', $filename );
	}

	if ( 1 === $level && 4 !== $file['error'] ) {
		$errors->add( 'upload_error', i12n_upload_error_message( $file['error'] ) );
	} elseif ( 2 === $level ) {
		$errors->add( 'empty_file', __( 'File is empty. Please upload something more substantial. This error could also be caused by uploads being disabled in your php.ini or by post_max_size being defined as smaller than upload_max_filesize in php.ini.' ) );
	} elseif ( 3 === $level ) {
		$errors->add( 'failed', __( 'Specified file failed upload test.' ) );
	} elseif ( 4 === $level ) {
		$errors->add( 'security_error', __( 'Sorry, this file type is not permitted for security reasons.' ) );
	} elseif ( 5 === $level ) {
		$message = sprintf(
			/* translators: %s: upload path */
			__( 'Unable to create directory %s. Is its parent directory writable by the server?' ),
			$upload_path
		);
		$errors->add( 'mkdir_error', $message );
	} elseif ( 6 === $level ) {
		$message = sprintf(
			/* translators: %s: upload path */
			__( 'The uploaded file could not be moved to %s.' ),
			$upload_path
		);
		$errors->add( 'move_file_error', $message );
	}
}
/**
 * Get error message.
 *
 * @param  integer $error_code
 *
 * @return string
 */
function i12n_upload_error_message( $error_code ) {
	$message    = array();
	$message[1] = sprintf(
		/* translators: 1: upload_max_filesize, 2: php.ini */
		__( 'The uploaded file exceeds the %1$s directive in %2$s.' ),
		'upload_max_filesize',
		'php.ini'
	);
	$message[2] = sprintf(
		/* translators: %s: MAX_FILE_SIZE */
		__( 'The uploaded file exceeds the %s directive that was specified in the HTML form.' ),
		'MAX_FILE_SIZE'
	);
	$message[3] = __( 'The uploaded file was only partially uploaded.' );
	$message[4] = __( 'No file was uploaded.' );
	$message[6] = __( 'Missing a temporary folder.' );
	$message[7] = __( 'Failed to write file to disk.' );
	$message[8] = __( 'File upload stopped by extension.' );

	return $message[ $error_code ] ?? __( 'There was an error uploading the file.', 'user-identification' );
}
add_action( 'user_profile_update_errors', 'i12n_upload', 10, 3 );

/**
 * Export the identification.
 */
function i12n_export() {
	if ( empty( $_GET['user_id'] ) ) {
		wp_die( __( 'Invalid user ID.' ) );
	}
	$user_id = (int) $_GET['user_id'];
	check_admin_referer( "download-i12n_$user_id" );
	$i12n_path   = i12n_upload_path( $user_id );
	$filename    = get_user_meta( $user_id, 'i12n_file', true );
	$file        = $i12n_path . '/' . $filename;
	$wp_filetype = wp_check_filetype_and_ext( $file, $filename );

	header( 'Content-Type: ' . $wp_filetype['type'] );
	readfile( $file );
	exit();
}
$is_useredit_page   = 'user-edit.php' === $pagenow;
$is_download_action = isset( $_GET['action'] ) && 'download-i12n' === $_GET['action'];
if ( $is_useredit_page && $is_download_action ) {
	add_action( 'init', 'i12n_export' );
}

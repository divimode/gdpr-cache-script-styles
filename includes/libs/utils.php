<?php
/**
 * Utility functions
 *
 * @package GdprCache
 */

namespace GdprCache;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;


use WP_Filesystem_Base;


// ----------------------------------------------------------------------------


/**
 * Checks, if the given URL refers to an external asset (true), or if it points
 * to a local or relative source (false).
 *
 * @since 1.0.0
 *
 * @param string $url URL to check.
 *
 * @return bool True, if the provided URL is an external source.
 */
function is_external_url( $url ) {
	// Relative URLs are always local to the current domain.
	if ( 0 === strpos( $url, '/' ) ) {
		return false;
	}

	// Get protocol-relative websites home URL.
	$home_url = preg_replace( '/^\w+:/', '', home_url() );

	return false === strpos( $url, $home_url );
}


/**
 * Returns an absolute path to a file in the local cache folder.
 *
 * @since 1.0.0
 *
 * @param string $file Name of a file in the local cache folder.
 *
 * @return string The absolute path to the local file.
 */
function build_cache_file_path( $file ) {
	if ( ! defined( 'GDPR_CACHE_BASE_DIR' ) ) {
		$wp_upload = wp_upload_dir();
		$base_path = $wp_upload['basedir'] . DIRECTORY_SEPARATOR . 'gdpr-cache' . DIRECTORY_SEPARATOR;
		wp_mkdir_p( $base_path );

		define( 'GDPR_CACHE_BASE_DIR', $base_path );
	}

	return GDPR_CACHE_BASE_DIR . $file;
}


/**
 * Returns an absolute URL to a file in the local cache folder.
 *
 * @since 1.0.0
 *
 * @param string $file Name of a file in the local cache folder.
 *
 * @return string The absolute URL to the local file.
 */
function build_cache_file_url( $file ) {
	if ( ! defined( 'GDPR_CACHE_BASE_URL' ) ) {
		$wp_upload = wp_upload_dir();
		$base_url  = $wp_upload['baseurl'] . '/gdpr-cache/';
		define( 'GDPR_CACHE_BASE_URL', $base_url );
	}

	return GDPR_CACHE_BASE_URL . $file;
}


/**
 * Determines the type of the file that's served by the specified URL.
 *
 * @since 1.0.0
 *
 * @param string $uri The URI to analyze.
 *
 * @return string The file type of the URI contents - e.g. css, js, ttf.
 */
function get_url_type( $uri ) {
	$types         = [
		'css',
		'js',
		'ttf',
		'otf',
		'woff',
		'woff2',
		'jpeg',
		'jpg',
		'png',
		'gif',
	];
	$content_types = [
		'text/css'        => 'css',
		'text/javascript' => 'js',
		'font/ttf'        => 'ttf',
		'font/otf'        => 'otf',
		'font/woff'       => 'woff',
		'font/woff2'      => 'woff2',
		'image/jpeg'      => 'jpg',
		'image/png'       => 'png',
		'image/gif'       => 'gif',
	];

	// First, analyze the URI and see if it ends with a valid type.
	$path = wp_parse_url( $uri, PHP_URL_PATH );
	if ( preg_match( '/\w+$/', $path, $matches ) ) {
		$type = strtolower( $matches[0] );
		if ( array_key_exists( $type, $types ) ) {
			return $type;
		}
	}

	// If the URI is not unique, then we'll ask for the content-type.
	$resp         = wp_remote_head( $uri );
	$content_type = wp_remote_retrieve_header( $resp, 'content-type' );
	$content_type = explode( ';', $content_type );
	$content_type = strtolower( trim( array_shift( $content_type ) ) );

	if ( array_key_exists( $content_type, $content_types ) ) {
		return $content_types[ $content_type ];
	}

	// Unknown data type.
	return 'tmp';
}


/**
 * Returns a WP_Filesystem instance.
 *
 * @soince 1.0.0
 * @return WP_Filesystem_Base
 */
function get_filesystem() {
	global $gdpr_cache_fs;

	if ( empty( $gdpr_cache_fs ) ) {
		$replace_filesystem_method = function () {
			return 'direct';
		};

		require_once ABSPATH . '/wp-admin/includes/file.php';

		add_filter( 'filesystem_method', $replace_filesystem_method );
		WP_Filesystem();

		remove_filter( 'filesystem_method', $replace_filesystem_method );
		$gdpr_cache_fs = $GLOBALS['wp_filesystem'];
	}

	return $gdpr_cache_fs;
}


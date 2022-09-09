<?php
/**
 * Asset caching module
 *
 * @package GdprCache
 */

namespace GdprCache;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;


// ----------------------------------------------------------------------------


// ----------------------------------------------------------------------------


/**
 * Returns an array of all cached external assets.
 *
 * @since 1.0.0
 * @return array List of external assets
 */
function get_cached_data() : array {
	$data = get_option( GDPR_CACHE_OPTION );

	if ( ! is_array( $data ) ) {
		$data = [];
		set_cached_data( $data );
	}

	return $data;
}


/**
 * Sets the list of cached external assets.
 *
 * @since 1.0.0
 *
 * @param array $data List of external assets
 */
function set_cached_data( array $data ) {
	update_option( GDPR_CACHE_OPTION, $data );
}


/**
 * Flushes the entire GDPR cache.
 *
 * @since 1.0.0
 * @return void
 */
function flush_cache() {
	set_cached_data( [] );

	// TODO: Empty all files in the uploads folder
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
function get_cache_file_path( string $file ) : string {
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
function get_cache_file_url( string $file ) : string {
	if ( ! defined( 'GDPR_CACHE_BASE_URL' ) ) {
		$wp_upload = wp_upload_dir();
		$base_url  = $wp_upload['baseurl'] . '/gdpr-cache/';
		define( 'GDPR_CACHE_BASE_URL', $base_url );
	}

	return GDPR_CACHE_BASE_URL . $file;
}


/**
 * Translates an external URL to a local URL.
 * Only works, when the external URL was cached via `cache_file_locally()`,
 * otherwise returns false.
 *
 * @since 1.0.0
 *
 * @param string $url The external URL to translate.
 *
 * @return string|false Returns the URL to the local copy of the given external
 * asset, or false when that cache does not exist.
 */
function get_local_url( string $url ) {
	$cache = get_cached_data();
	if ( ! isset( $cache[ $url ] ) ) {
		return false;
	}
	$item = $cache[ $url ];
	if ( empty( $item['expires'] ) || empty( $item['file'] ) ) {
		return false;
	}
	if ( $item['expires'] < time() ) {
		return false;
	}
	if ( ! file_exists( get_cache_file_path( $item['file'] ) ) ) {
		return false;
	}

	return get_cache_file_url( $item['file'] );
}


/**
 * Stores details about a cache file in the DB.
 *
 * @since 1.0.0
 *
 * @param string $url        The external URL.
 * @param string $file       Relative filename of the locally cached asset.
 * @param int    $expiration Lifetime of the cache file (in seconds).
 *
 * @return string Returns the URL to the local copy of the given asset.
 */
function set_local_path( string $url, string $file, int $expiration = 0 ) : string {
	$cache = get_cached_data();

	if ( $expiration < 1 ) {
		$expiration = DAY_IN_SECONDS;
	}

	$cache[ $url ] = [
		'expires' => time() + $expiration,
		'file'    => $file,
	];

	set_cached_data( $cache );

	return get_cache_file_url( $file );
}


/**
 * Downloads the specified URL and stores it in the local cache folder.
 *
 * This function updates the DB cache and returns the absolute URL to the
 * local cache file when done.
 *
 * @param string $url
 * @param string $type The file type (extension).
 *
 * @return string|false URL to the local cache file. False, when the file could
 * not be downloaded to the local cache folder.
 */
function cache_file_locally( string $url, string $type ) : string {
	// Could be an empty string, theoretically.
	if ( ! $type ) {
		$type = 'tmp';
	}

	$timeout    = 300;
	$filename   = md5( $url ) . '.' . $type;
	$cache_path = get_cache_file_path( $filename );

	// Download the remote asset to a local temp file.
	$resp = wp_safe_remote_get(
		$url,
		[
			'timeout'  => $timeout,
			'stream'   => true,
			'filename' => $cache_path,
		]
	);

	if ( is_wp_error( $resp ) ) {
		if ( file_exists( $cache_path ) ) {
			unlink( $cache_path );
		}

		return false;
	}

	// Check, if the remote server tells us a custom cache expiration time.
	$cc = wp_remote_retrieve_header( $resp, 'cache-control' );

	if ( $cc ) {
		$expires = (int) preg_replace( '/^.*max-age=(\d+).*$/', '$1', $cc );
	}

	if ( empty( $expires ) || $expires < 1 ) {
		$expires = DAY_IN_SECONDS;
	}

	// Scan the contents of the cached file to embed assets that are loaded
	// within that file.
	parse_cache_contents( $type, $cache_path );

	return set_local_path( $url, $filename, $expires );
}


/**
 * Scans the contents of the cached file to download and cache dependencies that
 * are loaded by the cached asset.
 *
 * @since 1.0.0
 *
 * @param string $type The file type that's parsed (css|js|ttf|...)
 * @param string $path Full path to the local cache file.
 *
 * @return void
 */
function parse_cache_contents( string $type, string $path ) {
	if ( 'css' !== $type ) {
		return;
	}

	/**
	 * The $matches array has 4 elements:
	 * [0] the full match, with url-prefix, the URI and the closing bracket
	 * [1] the "url(" prefix
	 * [2] the URI, with enclosing quotes <-- change this!
	 * [3] the closing bracket
	 *
	 * @param array $matches Array with 4 elements.
	 *
	 * @return string The full match with a different URI
	 */
	$parse_dependency = function ( array $matches ) : string {
		$uri = trim( $matches[2], '"\'' );

		$path = parse_url( $uri, PHP_URL_PATH );
		$type = pathinfo( $path, PATHINFO_EXTENSION );

		// Try to cache the external dependency.
		$local_uri = swap_to_local_asset( $uri, $type );

		if ( $local_uri ) {
			return $matches[1] . $local_uri . $matches[3];
		} else {
			return $matches[1] . $uri . $matches[3];
		}
	};

	// Read the file contents
	$contents = file_get_contents( $path );

	$contents = preg_replace_callback(
		'/([:\s]url\s*\()("[^"]*?"|\'[^\']*?\'|[^"\'][^)]*?)(\))/',
		$parse_dependency,
		$contents,
		- 1,
		$count
	);

	// In case an asset was downloaded and cached, update the local file.
	if ( $count ) {
		file_put_contents( $path, $contents );
	}
}

<?php
/**
 * Defines constants for the GDPR cache plugin
 *
 * @package GdprCache
 */

namespace GdprCache;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Absolute path to this plugin's folder, with trailing slash.
 *
 * @var string
 */
define( 'GDPR_CACHE_PATH', plugin_dir_path( GDPR_CACHE_PLUGIN_FILE ) );

/**
 * Absolute URL to the current plugins base folder, with trailing slash. Used
 * to enqueue scripts that are shipped with this plugin.
 *
 * @var string
 */
define( 'GDPR_CACHE_PLUGIN_URL', plugin_dir_url( GDPR_CACHE_PLUGIN_FILE ) );

/**
 * Option name that holds a list of all cached assets.
 *
 * @var string
 */
const GDPR_CACHE_OPTION = 'gdpr_cache';

/**
 * Option name that contains the task queue of invalidated or missing assets.
 *
 * @var string
 */
const GDPR_CACHE_QUEUE = 'gdpr_queue';

/**
 * Option name that stores a list of assets and their dependencies.
 *
 * This list is used in combination with GDPR_CACHE_USAGE to identify stale
 * assets.
 *
 * @since 1.0.4
 * @var string
 */
const GDPR_CACHE_DEPENDENCY = 'gdpr_dependency';

/**
 * Option name that stores the last-used time for each external asset.
 *
 * @since 1.0.4
 * @var string
 */
const GDPR_CACHE_USAGE = 'gdpr_used';

/**
 * Option name that holds the timestamp of the background worker start time.
 *
 * @var string
 */
const GDPR_CACHE_WORKER_LOCK = 'gdpr_lock';

if ( ! defined( 'GDPR_CACHE_DEFAULT_UA' ) ) {
	/**
	 * Defines the default user-agent that is sent to remote servers when
	 * downloading a file to the local cache.
	 *
	 * Set this to false or an empty string to not include a default
	 * user-agent with remote requests.
	 *
	 * Effect: The default UA below will instruct Google Fonts to use WOFF2
	 * files. If you need to support IE, define an empty UA, which results
	 * in TTF fonts being used.
	 *
	 * @see   https://developers.google.com/fonts/docs/technical_considerations
	 *
	 * @since 1.0.1
	 * @var string
	 */
	define( 'GDPR_CACHE_DEFAULT_UA', 'Mozilla/5.0 AppleWebKit/537 Chrome/105' );
}

if ( ! defined( 'GDPR_CACHE_STALE_HOURS' ) ) {
	/**
	 * Defines the maximum age of a cached asset before it's considered to be
	 * stale.
	 *
	 * An asset ages when it's not used, and is deleted once it becomes stale.
	 *
	 * @since 1.0.4
	 * @var int
	 */
	define( 'GDPR_CACHE_STALE_HOURS', 30 * 24 );
}

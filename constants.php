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
 * Option name that holds a list of all cached assets.
 *
 * @var string
 */
const GDPR_CACHE_OPTION = 'gdpr_cache';

<?php
/**
 * Admin options page for our GDPR cache plugin
 *
 * @package GdprCache
 */

namespace GdprCache;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

$assets = get_cached_data();
$queue  = get_worker_queue();

$status_labels = [
	'all'      => __( 'All', 'gdpr-cache' ),
	'valid'    => __( 'Cached', 'gdpr-cache' ),
	'expired'  => __( 'Expired', 'gdpr-cache' ),
	'missing'  => __( 'Missing', 'gdpr-cache' ),
	'enqueued' => __( 'Enqueued', 'gdpr-cache' ),
];

$items  = [];
$counts = [
	'all'      => 0,
	'valid'    => 0,
	'expired'  => 0,
	'missing'  => 0,
	'enqueued' => 0,
];

foreach ( $assets as $url => $item ) {
	$status_label = '';
	$item_status  = get_asset_status( $url );

	if ( 'valid' !== $item_status ) {
		enqueue_asset( $url );
	}
	if ( 'missing' === $item_status && in_array( $url, $queue ) ) {
		$item_status = 'enqueued';
	}
	if ( array_key_exists( $item_status, $status_labels ) ) {
		$status_label = $status_labels[ $item_status ];
	}
	$counts['all'] ++;
	$counts[ $item_status ] ++;

	$items[] = [
		'url'          => $url,
		'status'       => $item_status,
		'status_label' => $status_label,
		'created'      => gmdate( 'Y-m-d H:i', $item['created'] ),
		'expires'      => gmdate( 'Y-m-d H:i', $item['expires'] ),
	];
}

foreach ( $queue as $url ) {
	if ( ! empty( $assets[ $url ] ) ) {
		continue;
	}

	$item_status = 'enqueued';
	$counts['all'] ++;
	$counts[ $item_status ] ++;

	$items[] = [
		'url'          => $url,
		'status'       => $item_status,
		'status_label' => $status_labels[ $item_status ],
		'created'      => '',
		'expires'      => '',
	];
}

$action_refresh = wp_nonce_url(
	add_query_arg( [ 'action' => 'gdpr-cache-refresh' ] ),
	'refresh'
);
$action_purge   = wp_nonce_url(
	add_query_arg( [ 'action' => 'gdpr-cache-purge' ] ),
	'purge'
);

?>
<div class="wrap">
	<h1><?php esc_html_e( 'GDPR Cache Options', 'gdpr-cache' ); ?></h1>

	<p>
		<?php esc_html_e( 'View and manage your locally cached assets', 'gdpr-cache' ); ?>
	</p>

	<h2><?php esc_html_e( 'Cache Control', 'gdpr-cache' ); ?></h2>
	<p>
		<?php esc_html_e( 'Refreshing the cache will start to download all external files in the background. While the cache is regenerated, the expired files are served.', 'gdpr-cache' ); ?>
	</p>
	<p>
		<?php esc_html_e( 'Purging the cache will instantly delete all cached files, and begin to build a new cache. Until the initialization is finished, assets are loaded from the external sites.', 'gdpr-cache' ); ?>
	</p>
	<div class="gdpr-cache-reset">
		<p class="submit">
			<a href="<?php echo esc_url_raw( $action_refresh ) ?>" class="button-primary">
				<?php esc_html_e( 'Refresh Cache', 'gdpr-cache' ) ?>
			</a>
			<a href="<?php echo esc_url_raw( $action_purge ) ?>" class="button">
				<?php esc_html_e( 'Purge Cache', 'gdpr-cache' ) ?>
			</a>
		</p>
	</div>
	<?php wp_nonce_field( 'flush' ); ?>
	<input type="hidden" name="action" value="gdpr-cache-flush"/>

	<h2><?php esc_html_e( 'Cached Assets', 'gdpr-cache' ); ?></h2>

	<ul class="subsubsub">
		<?php foreach ( $counts as $item_status => $count ) : ?>
			<?php if ( ! $count ) {
				continue;
			} ?>

			<li class="count-<?php echo esc_attr( $item_status ) ?>">
				<span class="status"><?php echo esc_html( $status_labels[ $item_status ] ) ?></span>
				<span class="count">(<?php echo esc_html( (int) $count ); ?>)</span>
			</li>
		<?php endforeach; ?>
	</ul>

	<?php if ( ! $items ): ?>
		<p class="widefat">
			<em><?php esc_html_e( 'No external assets found', 'gdpr-cache' ); ?></em>
		</p>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped table-view-list">
			<thead>
			<tr>
				<th class="asset-url"><?php esc_html_e( 'URL', 'gdpr-cache' ); ?></th>
				<th
						class="asset-status" style="width:100px"
				><?php esc_html_e( 'Status', 'gdpr-cache' ); ?></th>
				<th
						class="asset-created" style="width:125px"
				><?php esc_html_e( 'Created', 'gdpr-cache' ); ?></th>
				<th
						class="asset-expires" style="width:125px"
				><?php esc_html_e( 'Expires', 'gdpr-cache' ); ?></th>
			</tr>
			</thead>
			<?php foreach ( $items as $item ): ?>
				<tr class="status-<?php echo esc_attr( $item['status'] ); ?>">
					<td class="asset-url"><?php echo esc_html( $item['url'] ); ?></td>
					<td class="asset-status">
						<?php echo esc_html( $item['status_label'] ); ?>
					</td>
					<td class="asset-created"><?php echo esc_html( $item['created'] ); ?></td>
					<td class="asset-expires"><?php echo esc_html( $item['expires'] ); ?></td>
				</tr>
			<?php endforeach; ?>
		</table>
	<?php endif; ?>
</div>

<style>
	.subsubsub {
		margin-bottom: 12px
	}

	.subsubsub li + li:before {
		content: '|';
		padding: 0 2px;
	}

	.subsubsub .count-all .status {
		font-weight: bold;
	}

	.widefat .asset-status {
		vertical-align: middle;
		text-align: center;
	}

	.status-valid .asset-status {
		background: #c8e6c940;
		color: #005005;
	}

	.status-expired .asset-status {
		background: #ffecb340;
		color: #c56000;
	}

	.status-missing .asset-status {
		background: #ffccbc40;
		color: #9f0000;
	}

	.status-enqueued .asset-status {
		background: #e1bee740;
		color: #38006b;
	}
</style>

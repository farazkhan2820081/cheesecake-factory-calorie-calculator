<?php
/**
 * Database table and queries.
 *
 * @package Cheesecake_Factory_Calorie_Calculator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CFC_Database
 */
class CFC_Database {

	/**
	 * Table name without prefix.
	 */
	const TABLE = 'cfc_menu_items';

	/**
	 * Create table on activation.
	 */
	public static function activate() {
		self::create_table();
	}

	/**
	 * Create or upgrade table (activation + runtime safety if install skipped).
	 */
	public static function create_table() {
		global $wpdb;

		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			external_id varchar(64) NOT NULL,
			product_name varchar(500) NOT NULL,
			category varchar(255) NOT NULL DEFAULT '',
			calories int unsigned NOT NULL DEFAULT 0,
			serving_size text NULL,
			description longtext NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY external_id (external_id),
			KEY category (category(100)),
			KEY product_name (product_name(191))
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'cfc_db_version', CFC_VERSION );
	}

	/**
	 * Ensure table exists (e.g. plugin copied without running activation).
	 *
	 * @return void
	 */
	public static function maybe_install() {
		global $wpdb;
		$table = self::table_name();
		$like  = $wpdb->esc_like( $table );
		// Underscores are LIKE wildcards; esc_like keeps table names safe.
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );
		if ( $found !== $table ) {
			self::create_table();
		}
	}

	/**
	 * Full table name with prefix.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Count rows.
	 *
	 * @return int
	 */
	public static function count_items() {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internal.
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
	}

	/**
	 * Delete all rows (before full re-import).
	 *
	 * @return void
	 */
	public static function clear_all_items() {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DELETE FROM `{$table}`" );
	}

	/**
	 * Insert batch of rows.
	 *
	 * @param array<int, array<string, mixed>> $rows Rows with keys: external_id, product_name, category, calories, serving_size, description.
	 * @return int Number inserted.
	 */
	public static function insert_batch( array $rows ) {
		global $wpdb;

		if ( empty( $rows ) ) {
			return 0;
		}

		$table = self::table_name();
		$count = 0;

		// Chunk to avoid max_allowed_packet issues on large menus.
		$chunks = array_chunk( $rows, 200 );
		foreach ( $chunks as $chunk ) {
			$values = array();
			foreach ( $chunk as $row ) {
				$values[] = $wpdb->prepare(
					'(%s, %s, %s, %d, %s, %s)',
					$row['external_id'],
					$row['product_name'],
					$row['category'],
					$row['calories'],
					$row['serving_size'],
					$row['description']
				);
			}
			$sql = "INSERT INTO `{$table}` (external_id, product_name, category, calories, serving_size, description) VALUES " . implode( ', ', $values );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- values built with prepare per row.
			$wpdb->query( $sql );
			$count += count( $chunk );
		}

		return $count;
	}

	/**
	 * All items for frontend (single query, no remote calls).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_all_items() {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT external_id, product_name, category, calories, serving_size, description FROM `{$table}` ORDER BY category ASC, product_name ASC", ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Distinct categories ordered.
	 *
	 * @return string[]
	 */
	public static function get_categories() {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$cats = $wpdb->get_col( "SELECT DISTINCT category FROM `{$table}` WHERE category <> '' ORDER BY category ASC" );
		return is_array( $cats ) ? $cats : array();
	}
}

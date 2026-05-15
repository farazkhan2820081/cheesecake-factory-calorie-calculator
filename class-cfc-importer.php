<?php
/**
 * CSV import: parse, validate, normalize calories.
 *
 * @package Cheesecake_Factory_Calorie_Calculator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CFC_Importer
 */
class CFC_Importer {

	/**
	 * Expected header keys (normalized lowercase).
	 */
	const REQUIRED_HEADERS = array( 'id', 'product_name', 'category', 'calories' );

	/**
	 * Parse calories to non-negative integer. Strips text like "590 cal", commas in numbers.
	 *
	 * @param mixed $value Raw cell value.
	 * @return int|null Null if invalid/empty.
	 */
	public static function parse_calories( $value ) {
		if ( null === $value || '' === $value ) {
			return null;
		}
		$s = trim( (string) $value );
		if ( '' === $s ) {
			return null;
		}
		// Remove thousands separators then keep digits only for integer calories.
		$s = str_replace( array( ',', ' ' ), '', $s );
		$s = preg_replace( '/[^\d]/', '', $s );
		if ( null === $s || '' === $s ) {
			return null;
		}
		$n = (int) $s;
		if ( $n < 0 ) {
			return null;
		}
		return $n;
	}

	/**
	 * Import CSV from local file path.
	 *
	 * @param string $file_path Absolute path to temp uploaded file.
	 * @return array{success:bool, inserted:int, errors:string[], warnings:string[]}
	 */
	public static function import_file( $file_path ) {
		$result = array(
			'success'   => false,
			'inserted'  => 0,
			'errors'    => array(),
			'warnings'  => array(),
		);

		if ( ! is_readable( $file_path ) ) {
			$result['errors'][] = __( 'Could not read the uploaded file.', 'cheesecake-factory-calorie-calculator' );
			return $result;
		}

		$handle = fopen( $file_path, 'rb' );
		if ( false === $handle ) {
			$result['errors'][] = __( 'Could not open the file.', 'cheesecake-factory-calorie-calculator' );
			return $result;
		}

		// Skip UTF-8 BOM if present so the first column name parses correctly.
		$bom = fread( $handle, 3 );
		if ( $bom !== "\xEF\xBB\xBF" ) {
			rewind( $handle );
		}

		$header_row = fgetcsv( $handle );
		if ( false === $header_row || empty( $header_row ) ) {
			fclose( $handle );
			$result['errors'][] = __( 'Missing or invalid CSV header row.', 'cheesecake-factory-calorie-calculator' );
			return $result;
		}

		$map = self::map_headers( $header_row );
		foreach ( self::REQUIRED_HEADERS as $req ) {
			if ( ! isset( $map[ $req ] ) ) {
				fclose( $handle );
				$result['errors'][] = sprintf(
					/* translators: %s: column name */
					__( 'Missing required column: %s', 'cheesecake-factory-calorie-calculator' ),
					$req
				);
				return $result;
			}
		}

		$rows        = array();
		$line_number = 1;
		$seen_ids    = array();

		while ( ( $data = fgetcsv( $handle ) ) !== false ) {
			++$line_number;

			if ( self::is_blank_row( $data ) ) {
				continue;
			}

			$id = isset( $data[ $map['id'] ] ) ? trim( (string) $data[ $map['id'] ] ) : '';
			$product_name = isset( $data[ $map['product_name'] ] ) ? trim( (string) $data[ $map['product_name'] ] ) : '';
			$category = isset( $data[ $map['category'] ] ) ? trim( (string) $data[ $map['category'] ] ) : '';
			$cal_raw = isset( $data[ $map['calories'] ] ) ? $data[ $map['calories'] ] : '';
			$serving = isset( $map['serving_size'] ) && isset( $data[ $map['serving_size'] ] ) ? trim( (string) $data[ $map['serving_size'] ] ) : '';
			$desc = isset( $map['description'] ) && isset( $data[ $map['description'] ] ) ? trim( (string) $data[ $map['description'] ] ) : '';

			if ( '' === $id || '' === $product_name || '' === $category ) {
				$result['errors'][] = sprintf(
					/* translators: 1: line number, 2: fields */
					__( 'Line %1$d: missing required field (id, product_name, or category).', 'cheesecake-factory-calorie-calculator' ),
					$line_number
				);
				continue;
			}

			$calories = self::parse_calories( $cal_raw );
			if ( null === $calories ) {
				$result['errors'][] = sprintf(
					/* translators: %d: line number */
					__( 'Line %d: invalid or empty calories (must be a number).', 'cheesecake-factory-calorie-calculator' ),
					$line_number
				);
				continue;
			}

			if ( isset( $seen_ids[ $id ] ) ) {
				$result['errors'][] = sprintf(
					/* translators: 1: duplicate id, 2: line number */
					__( 'Duplicate id "%1$s" at line %2$d.', 'cheesecake-factory-calorie-calculator' ),
					$id,
					$line_number
				);
				continue;
			}
			$seen_ids[ $id ] = true;

			$rows[] = array(
				'external_id'   => $id,
				'product_name'  => $product_name,
				'category'      => $category,
				'calories'      => $calories,
				'serving_size'  => $serving,
				'description'   => $desc,
			);
		}

		fclose( $handle );

		if ( ! empty( $result['errors'] ) && empty( $rows ) ) {
			return $result;
		}

		if ( ! empty( $result['errors'] ) ) {
			// Partial errors: abort without inserting (strict validation).
			$result['success'] = false;
			return $result;
		}

		if ( empty( $rows ) ) {
			$result['errors'][] = __( 'No data rows to import.', 'cheesecake-factory-calorie-calculator' );
			return $result;
		}

		CFC_Database::clear_all_items();
		$inserted = CFC_Database::insert_batch( $rows );

		update_option( 'cfc_last_import', current_time( 'mysql' ) );
		update_option( 'cfc_last_import_count', $inserted );

		$result['success']  = true;
		$result['inserted'] = $inserted;
		return $result;
	}

	/**
	 * Normalize header names to keys.
	 *
	 * @param array<int, string> $header_row Header cells.
	 * @return array<string, int> key => column index.
	 */
	private static function map_headers( array $header_row ) {
		$map = array();
		foreach ( $header_row as $i => $cell ) {
			$key = strtolower( trim( (string) $cell ) );
			$key = preg_replace( '/^\xEF\xBB\xBF/', '', $key );
			if ( '' === $key ) {
				continue;
			}
			$map[ $key ] = (int) $i;
		}
		return $map;
	}

	/**
	 * True if row is empty (all cells blank).
	 *
	 * @param array<int, string|null> $data Row.
	 * @return bool
	 */
	private static function is_blank_row( $data ) {
		if ( ! is_array( $data ) || empty( $data ) ) {
			return true;
		}
		foreach ( $data as $cell ) {
			if ( null !== $cell && '' !== trim( (string) $cell ) ) {
				return false;
			}
		}
		return true;
	}
}

<?php
/**
 * Admin: CSV upload, stats, optional simple edit list.
 *
 * @package Cheesecake_Factory_Calorie_Calculator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CFC_Admin
 */
class CFC_Admin {

	/**
	 * Instance.
	 *
	 * @var CFC_Admin|null
	 */
	private static $instance = null;

	/**
	 * Option key for last import notice.
	 */
	const NOTICE_OPTION = 'cfc_admin_notice';

	/**
	 * Singleton.
	 *
	 * @return CFC_Admin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * CFC_Admin constructor.
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_post' ) );
		add_action( 'admin_notices', array( $this, 'show_notice' ) );
	}

	/**
	 * Register settings submenu.
	 */
	public function register_menu() {
		add_options_page(
			__( 'Cheesecake Calorie Calculator', 'cheesecake-factory-calorie-calculator' ),
			__( 'Cheesecake Calculator', 'cheesecake-factory-calorie-calculator' ),
			'manage_options',
			'cfc-calorie-calculator',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Handle CSV upload and row delete (simple edit).
	 */
	public function handle_post() {
		if ( ! isset( $_POST['cfc_action'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$action = sanitize_text_field( wp_unslash( $_POST['cfc_action'] ) );

		if ( 'import_csv' === $action ) {
			check_admin_referer( 'cfc_import_csv', 'cfc_import_nonce' );

			if ( empty( $_POST['cfc_confirm_replace'] ) ) {
				$this->set_notice( 'error', __( 'Please confirm that you want to replace existing data.', 'cheesecake-factory-calorie-calculator' ) );
				$this->redirect_back();
			}

			if ( empty( $_FILES['cfc_csv']['name'] ) || ! isset( $_FILES['cfc_csv']['tmp_name'] ) ) {
				$this->set_notice( 'error', __( 'Please choose a CSV file to upload.', 'cheesecake-factory-calorie-calculator' ) );
				$this->redirect_back();
			}

			$file = $_FILES['cfc_csv'];
			if ( ! empty( $file['error'] ) ) {
				$this->set_notice( 'error', __( 'Upload failed. Please try again.', 'cheesecake-factory-calorie-calculator' ) );
				$this->redirect_back();
			}

			$ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
			if ( 'csv' !== $ext ) {
				$this->set_notice( 'error', __( 'Please upload a .csv file.', 'cheesecake-factory-calorie-calculator' ) );
				$this->redirect_back();
			}

			if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
				$this->set_notice( 'error', __( 'Invalid upload. Please try again.', 'cheesecake-factory-calorie-calculator' ) );
				$this->redirect_back();
			}

			// Read directly from PHP temp file (avoids MIME mismatches on some hosts for .csv).
			$res = CFC_Importer::import_file( $file['tmp_name'] );

			if ( $res['success'] ) {
				$this->set_notice(
					'success',
					sprintf(
						/* translators: %d: number of products */
						__( 'Import complete. %d products loaded.', 'cheesecake-factory-calorie-calculator' ),
						(int) $res['inserted']
					)
				);
			} else {
				$msg = implode( ' ', array_map( 'wp_strip_all_tags', $res['errors'] ) );
				$this->set_notice( 'error', $msg ? $msg : __( 'Import failed.', 'cheesecake-factory-calorie-calculator' ) );
			}

			$this->redirect_back();
		}

		if ( 'delete_item' === $action ) {
			check_admin_referer( 'cfc_delete_item', 'cfc_delete_nonce' );
			$db_id = isset( $_POST['cfc_db_id'] ) ? absint( $_POST['cfc_db_id'] ) : 0;
			if ( $db_id ) {
				global $wpdb;
				$table = CFC_Database::table_name();
				$wpdb->delete( $table, array( 'id' => $db_id ), array( '%d' ) );
				$this->set_notice( 'success', __( 'Item removed.', 'cheesecake-factory-calorie-calculator' ) );
			}
			$this->redirect_back();
		}
	}

	/**
	 * Redirect to settings page.
	 */
	private function redirect_back() {
		wp_safe_redirect( admin_url( 'options-general.php?page=cfc-calorie-calculator' ) );
		exit;
	}

	/**
	 * Store admin notice in transient (one-time).
	 *
	 * @param string $type success|error|warning.
	 * @param string $message Message.
	 */
	private function set_notice( $type, $message ) {
		set_transient(
			self::NOTICE_OPTION,
			array(
				'type'    => $type,
				'message' => $message,
			),
			60
		);
	}

	/**
	 * Display notice after redirect.
	 */
	public function show_notice() {
		$data = get_transient( self::NOTICE_OPTION );
		if ( ! is_array( $data ) || empty( $data['message'] ) ) {
			return;
		}
		delete_transient( self::NOTICE_OPTION );
		$type = isset( $data['type'] ) && 'error' === $data['type'] ? 'error' : 'updated';
		printf(
			'<div class="%1$s notice is-dismissible"><p>%2$s</p></div>',
			esc_attr( $type ),
			wp_kses_post( $data['message'] )
		);
	}

	/**
	 * Render admin page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$count      = CFC_Database::count_items();
		$last       = get_option( 'cfc_last_import', '' );
		$items_page = isset( $_GET['cfc_items'] ) ? absint( $_GET['cfc_items'] ) : 1;
		$per_page   = 20;
		$offset     = ( $items_page - 1 ) * $per_page;

		global $wpdb;
		$table = CFC_Database::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total_items = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
		$total_pages = $total_items > 0 ? (int) ceil( $total_items / $per_page ) : 1;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, external_id, product_name, category, calories FROM `{$table}` ORDER BY category ASC, product_name ASC LIMIT %d OFFSET %d",
				$per_page,
				$offset
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! is_array( $rows ) ) {
			$rows = array();
		}

		?>
		<div class="wrap cfc-admin-wrap">
			<h1><?php esc_html_e( 'Cheesecake Factory Calorie Calculator', 'cheesecake-factory-calorie-calculator' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Import your menu CSV to power the frontend calculator. Re-import replaces all existing products.', 'cheesecake-factory-calorie-calculator' ); ?>
			</p>

			<div class="notice notice-info" style="padding:12px 14px;">
				<p style="margin:0 0 8px;"><strong><?php esc_html_e( 'Put the calculator on a page (pick one)', 'cheesecake-factory-calorie-calculator' ); ?></strong></p>
				<ol style="margin:0 0 0 1.25em;padding:0;">
					<li><?php esc_html_e( 'Easiest: edit the page, click the + block inserter, open the Patterns tab, search for “Cheesecake”, and insert “Cheesecake calorie calculator”.', 'cheesecake-factory-calorie-calculator' ); ?></li>
					<li><?php esc_html_e( 'Or: add a normal Paragraph block, type exactly this on its own line:', 'cheesecake-factory-calorie-calculator' ); ?> <code>[cheesecake_factory_calorie_calculator]</code></li>
					<li><?php esc_html_e( 'Or: add the “Shortcode” block and paste the same line there.', 'cheesecake-factory-calorie-calculator' ); ?></li>
				</ol>
			</div>

			<div class="cfc-admin-cards" style="display:flex;flex-wrap:wrap;gap:20px;margin:20px 0;">
				<div class="postbox" style="min-width:240px;padding:15px;">
					<h2 style="margin-top:0;"><?php esc_html_e( 'Products in database', 'cheesecake-factory-calorie-calculator' ); ?></h2>
					<p style="font-size:2em;margin:0;"><?php echo esc_html( (string) $count ); ?></p>
				</div>
				<div class="postbox" style="min-width:240px;padding:15px;">
					<h2 style="margin-top:0;"><?php esc_html_e( 'Last import', 'cheesecake-factory-calorie-calculator' ); ?></h2>
					<p style="margin:0;">
						<?php
						echo $last
							? esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last ) )
							: esc_html__( '—', 'cheesecake-factory-calorie-calculator' );
						?>
					</p>
				</div>
			</div>

			<h2><?php esc_html_e( 'Upload CSV', 'cheesecake-factory-calorie-calculator' ); ?></h2>
			<form method="post" enctype="multipart/form-data" action="">
				<?php wp_nonce_field( 'cfc_import_csv', 'cfc_import_nonce' ); ?>
				<input type="hidden" name="cfc_action" value="import_csv" />
				<p>
					<input type="file" name="cfc_csv" accept=".csv,text/csv" required />
				</p>
				<p>
					<label>
						<input type="checkbox" name="cfc_confirm_replace" value="1" required />
						<?php esc_html_e( 'I understand this will replace all existing menu data.', 'cheesecake-factory-calorie-calculator' ); ?>
					</label>
				</p>
				<?php submit_button( __( 'Import CSV', 'cheesecake-factory-calorie-calculator' ), 'primary', 'submit', false ); ?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Expected CSV columns', 'cheesecake-factory-calorie-calculator' ); ?></h2>
			<p><code>id, product_name, category, calories, serving_size, description</code></p>
			<ul style="list-style:disc;margin-left:1.5em;">
				<li><?php esc_html_e( 'First row must be the header with these column names.', 'cheesecake-factory-calorie-calculator' ); ?></li>
				<li><?php esc_html_e( 'Calories can include text (e.g. 590 cal); only the number is stored.', 'cheesecake-factory-calorie-calculator' ); ?></li>
				<li><?php esc_html_e( 'Each id must be unique. Blank rows are skipped.', 'cheesecake-factory-calorie-calculator' ); ?></li>
			</ul>

			<hr />

			<h2><?php esc_html_e( 'Browse items (simple edit)', 'cheesecake-factory-calorie-calculator' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Delete individual rows if needed. For bulk changes, edit your CSV and re-import.', 'cheesecake-factory-calorie-calculator' ); ?></p>

			<?php if ( empty( $rows ) ) : ?>
				<p><?php esc_html_e( 'No items yet. Import a CSV above.', 'cheesecake-factory-calorie-calculator' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'ID', 'cheesecake-factory-calorie-calculator' ); ?></th>
							<th><?php esc_html_e( 'Name', 'cheesecake-factory-calorie-calculator' ); ?></th>
							<th><?php esc_html_e( 'Category', 'cheesecake-factory-calorie-calculator' ); ?></th>
							<th><?php esc_html_e( 'Calories', 'cheesecake-factory-calorie-calculator' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'cheesecake-factory-calorie-calculator' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $row ) : ?>
							<tr>
								<td><?php echo esc_html( $row['external_id'] ); ?></td>
								<td><?php echo esc_html( $row['product_name'] ); ?></td>
								<td><?php echo esc_html( $row['category'] ); ?></td>
								<td><?php echo esc_html( (string) (int) $row['calories'] ); ?></td>
								<td>
									<form method="post" style="display:inline;" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this item?', 'cheesecake-factory-calorie-calculator' ) ); ?>');">
										<?php wp_nonce_field( 'cfc_delete_item', 'cfc_delete_nonce' ); ?>
										<input type="hidden" name="cfc_action" value="delete_item" />
										<input type="hidden" name="cfc_db_id" value="<?php echo esc_attr( (string) (int) $row['id'] ); ?>" />
										<button type="submit" class="button button-small"><?php esc_html_e( 'Delete', 'cheesecake-factory-calorie-calculator' ); ?></button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<?php if ( $total_pages > 1 ) : ?>
					<p class="tablenav">
						<?php
						$pagination_base = add_query_arg(
							'cfc_items',
							'%#%',
							admin_url( 'options-general.php?page=cfc-calorie-calculator' )
						);
						echo wp_kses_post(
							paginate_links(
								array(
									'base'      => $pagination_base,
									'format'    => '',
									'current'   => max( 1, $items_page ),
									'total'     => $total_pages,
									'prev_text' => '&laquo;',
									'next_text' => '&raquo;',
								)
							)
						);
						?>
					</p>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}
}

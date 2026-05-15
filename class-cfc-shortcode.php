<?php
/**
 * Shortcode [cheesecake_factory_calorie_calculator] and asset loading.
 *
 * @package Cheesecake_Factory_Calorie_Calculator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CFC_Shortcode
 */
class CFC_Shortcode {

	const TAG = 'cheesecake_factory_calorie_calculator';

	/**
	 * Instance.
	 *
	 * @var CFC_Shortcode|null
	 */
	private static $instance = null;

	/**
	 * Whether public assets were registered.
	 *
	 * @var bool
	 */
	private static $assets_registered = false;

	/**
	 * Whether the block pattern was registered (avoid duplicate on init).
	 *
	 * @var bool
	 */
	private static $pattern_registered = false;

	/**
	 * Singleton.
	 *
	 * @return CFC_Shortcode
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * CFC_Shortcode constructor.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'register_assets' ), 5 );
		add_action( 'init', array( $this, 'register_block_pattern' ), 15 );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_from_content' ), 20 );
		add_filter( 'render_block', array( $this, 'render_block_plain_paragraph_shortcode' ), 10, 2 );
		add_shortcode( self::TAG, array( $this, 'render' ) );
	}

	/**
	 * One-click pattern in the block editor (Patterns panel).
	 */
	public function register_block_pattern() {
		if ( self::$pattern_registered || ! function_exists( 'register_block_pattern' ) ) {
			return;
		}
		self::$pattern_registered = true;

		register_block_pattern(
			'cfc/cheesecake-calorie-calculator',
			array(
				'title'       => __( 'Cheesecake calorie calculator', 'cheesecake-factory-calorie-calculator' ),
				'description' => __( 'Adds the menu calorie calculator for your visitors.', 'cheesecake-factory-calorie-calculator' ),
				'categories'  => array( 'text' ),
				'keywords'    => array( 'calories', 'menu', 'calculator', 'cheesecake', 'restaurant' ),
				'content'     => '<!-- wp:shortcode -->[' . self::TAG . ']<!-- /wp:shortcode -->',
			)
		);
	}

	/**
	 * If a Paragraph (or Classic block) contains only the calculator shortcode, run it.
	 * Lets non-technical users paste the shortcode in a normal paragraph — no Shortcode block required.
	 *
	 * @param string               $block_content Rendered block HTML.
	 * @param array<string, mixed> $block         Parsed block data.
	 * @return string
	 */
	public function render_block_plain_paragraph_shortcode( $block_content, $block ) {
		if ( is_admin() ) {
			return $block_content;
		}
		if ( empty( $block['blockName'] ) || ! is_string( $block_content ) ) {
			return $block_content;
		}

		$allowed = array( 'core/paragraph', 'core/freeform', 'core/html' );
		if ( ! in_array( $block['blockName'], $allowed, true ) ) {
			return $block_content;
		}

		$stripped = trim( wp_strip_all_tags( $block_content ) );
		$literal  = '[' . self::TAG . ']';
		if ( $literal !== $stripped ) {
			return $block_content;
		}

		return $this->render( array() );
	}

	/**
	 * Register handles early so enqueue works from shortcode or from content scan.
	 */
	public function register_assets() {
		if ( self::$assets_registered ) {
			return;
		}
		self::$assets_registered = true;

		wp_register_style(
			'cfc-public',
			CFC_PLUGIN_URL . 'public/css/cfc-public.css',
			array(),
			CFC_VERSION
		);

		wp_register_script(
			'cfc-public',
			CFC_PLUGIN_URL . 'public/js/cfc-public.js',
			array(),
			CFC_VERSION,
			true
		);
	}

	/**
	 * Enqueue when shortcode exists in main post content (classic / Gutenberg on post_content).
	 * Page builders that store shortcodes elsewhere still rely on shortcode render to enqueue.
	 */
	public function maybe_enqueue_from_content() {
		if ( is_admin() ) {
			return;
		}
		if ( ! is_singular() ) {
			return;
		}
		$post = get_post();
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		$content = $post->post_content;
		if ( has_shortcode( $content, self::TAG ) || false !== strpos( $content, '[' . self::TAG . ']' ) ) {
			wp_enqueue_style( 'cfc-public' );
			wp_enqueue_script( 'cfc-public' );
		}
	}

	/**
	 * Enqueue CSS/JS (idempotent).
	 */
	private function enqueue_assets() {
		wp_enqueue_style( 'cfc-public' );
		wp_enqueue_script( 'cfc-public' );
	}

	/**
	 * Shortcode callback.
	 *
	 * @param array<string, string> $atts Attributes (reserved for future use).
	 * @return string
	 */
	public function render( $atts ) {
		$this->enqueue_assets();

		$items = CFC_Database::get_all_items();
		if ( empty( $items ) ) {
			return '<div class="cfc-calculator cfc-calculator--empty"><p class="cfc-msg">' .
				esc_html__( 'Menu data is not loaded yet. Please import a CSV in Settings → Cheesecake Calculator.', 'cheesecake-factory-calorie-calculator' ) .
				'</p></div>';
		}

		$by_category = array();
		foreach ( $items as $row ) {
			$cat = isset( $row['category'] ) ? $row['category'] : '';
			if ( ! isset( $by_category[ $cat ] ) ) {
				$by_category[ $cat ] = array();
			}
			$by_category[ $cat ][] = $row;
		}
		uksort(
			$by_category,
			static function ( $a, $b ) {
				return strnatcasecmp( (string) $a, (string) $b );
			}
		);

		$categories = array_keys( $by_category );

		$data = array(
			'byCategory' => $by_category,
			'categories' => $categories,
			'i18n'       => array(
				'selectCategory' => __( 'Select category', 'cheesecake-factory-calorie-calculator' ),
				'selectProduct'  => __( 'Select menu item', 'cheesecake-factory-calorie-calculator' ),
				'quantity'       => __( 'Quantity', 'cheesecake-factory-calorie-calculator' ),
				'add'            => __( 'Add item', 'cheesecake-factory-calorie-calculator' ),
				'remove'         => __( 'Remove', 'cheesecake-factory-calorie-calculator' ),
				'reset'          => __( 'Reset calculator', 'cheesecake-factory-calorie-calculator' ),
				'yourOrder'      => __( 'Your selections', 'cheesecake-factory-calorie-calculator' ),
				'item'           => __( 'Item', 'cheesecake-factory-calorie-calculator' ),
				'calPer'         => __( 'Calories (each)', 'cheesecake-factory-calorie-calculator' ),
				'qty'            => __( 'Qty', 'cheesecake-factory-calorie-calculator' ),
				'lineCal'        => __( 'Line total', 'cheesecake-factory-calorie-calculator' ),
				'total'          => __( 'Total calories', 'cheesecake-factory-calorie-calculator' ),
				'emptyCart'      => __( 'No items added yet.', 'cheesecake-factory-calorie-calculator' ),
				'pickFirst'      => __( 'Choose a category and item, then set quantity.', 'cheesecake-factory-calorie-calculator' ),
			),
		);

		$uid = function_exists( 'wp_unique_id' ) ? wp_unique_id( 'cfc-' ) : uniqid( 'cfc-', false );

		$config_json = wp_json_encode(
			$data,
			JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
		);
		if ( ! is_string( $config_json ) ) {
			$config_json = '{}';
		}

		ob_start();
		?>
		<div class="cfc-calculator" data-cfc-root>
			<script type="application/json" class="cfc-config-json"><?php echo $config_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON for client parse. ?></script>
			<div class="cfc-calculator__controls">
				<div class="cfc-field">
					<label for="<?php echo esc_attr( $uid ); ?>-category" class="cfc-label"><?php echo esc_html( $data['i18n']['selectCategory'] ); ?></label>
					<select id="<?php echo esc_attr( $uid ); ?>-category" class="cfc-select" data-cfc-category>
						<option value=""><?php echo esc_html( $data['i18n']['selectCategory'] ); ?></option>
						<?php foreach ( $categories as $cat ) : ?>
							<option value="<?php echo esc_attr( $cat ); ?>"><?php echo esc_html( $cat ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="cfc-field">
					<label for="<?php echo esc_attr( $uid ); ?>-product" class="cfc-label"><?php echo esc_html( $data['i18n']['selectProduct'] ); ?></label>
					<select id="<?php echo esc_attr( $uid ); ?>-product" class="cfc-select" data-cfc-product disabled>
						<option value=""><?php echo esc_html( $data['i18n']['selectProduct'] ); ?></option>
					</select>
				</div>
				<div class="cfc-field cfc-field--qty">
					<label for="<?php echo esc_attr( $uid ); ?>-qty" class="cfc-label"><?php echo esc_html( $data['i18n']['quantity'] ); ?></label>
					<input type="number" id="<?php echo esc_attr( $uid ); ?>-qty" class="cfc-input" data-cfc-qty min="1" max="999" step="1" value="1" inputmode="numeric" />
				</div>
				<div class="cfc-field cfc-field--btn">
					<button type="button" class="cfc-btn cfc-btn--primary" data-cfc-add><?php echo esc_html( $data['i18n']['add'] ); ?></button>
				</div>
			</div>

			<div class="cfc-calculator__summary">
				<div class="cfc-summary-head">
					<h3 class="cfc-summary-title"><?php echo esc_html( $data['i18n']['yourOrder'] ); ?></h3>
					<button type="button" class="cfc-btn cfc-btn--ghost" data-cfc-reset><?php echo esc_html( $data['i18n']['reset'] ); ?></button>
				</div>
				<div class="cfc-table-wrap">
					<table class="cfc-table" data-cfc-table>
						<thead>
							<tr>
								<th scope="col"><?php echo esc_html( $data['i18n']['item'] ); ?></th>
								<th scope="col"><?php echo esc_html( $data['i18n']['calPer'] ); ?></th>
								<th scope="col"><?php echo esc_html( $data['i18n']['qty'] ); ?></th>
								<th scope="col"><?php echo esc_html( $data['i18n']['lineCal'] ); ?></th>
								<th scope="col"><span class="screen-reader-text"><?php echo esc_html( $data['i18n']['remove'] ); ?></span></th>
							</tr>
						</thead>
						<tbody data-cfc-tbody>
							<tr class="cfc-table__empty" data-cfc-empty-row>
								<td colspan="5"><?php echo esc_html( $data['i18n']['emptyCart'] ); ?></td>
							</tr>
						</tbody>
					</table>
				</div>
				<div class="cfc-total" data-cfc-total-wrap>
					<strong><?php echo esc_html( $data['i18n']['total'] ); ?>:</strong>
					<span class="cfc-total__value" data-cfc-total>0</span>
				</div>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}

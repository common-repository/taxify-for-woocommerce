<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Taxify Order Class
 *
 * @package     Taxify/includes_Meta Box Prodcut Data
 * @author      Todd Lahman LLC
 * @copyright   Copyright (c) Taxify
 * @since       1.0
 *
 */
class WC_Taxify_Product_Data {

	/**
	 * @var null
	 */
	protected static $_instance = null;

	/**
	 * @static
	 * @return null|\WC_Taxify_Product_Data
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public function __construct() {
		if ( WCTAXIFY()->taxify_enabled() ) {
			// Load Quick Edit selected data and jQuery
			add_action( 'manage_product_posts_custom_column', array( $this, 'render_product_columns' ), 2, 2 );
			add_action( 'wp_ajax_tax_classes_ajax', array( $this, 'get_taxify_tax_classes_ajax' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );

			// Simple product
			add_action( 'woocommerce_product_options_tax', array( $this, 'taxify_tax_class_simple' ) );
			add_action( 'woocommerce_process_product_meta_simple', array( $this, 'save_product' ) );
			// Variable product
			add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'taxify_tax_class_variable' ), 10, 3 );
			add_action( 'woocommerce_process_product_meta_variable', array( $this, 'save_product' ) );
			add_action( 'woocommerce_process_product_meta', array( $this, 'save_product' ) );
			add_action( 'woocommerce_ajax_save_product_variations', array( $this, 'ajax_save_product' ) );

			// Quick edit product
			add_action( 'woocommerce_product_quick_edit_start', array( $this, 'taxify_tax_class_quick_edit' ) );
			// Bulk edit product;
			add_action( 'woocommerce_product_bulk_edit_start', array( $this, 'taxify_tax_class_bulk_edit' ) );
			add_action( 'save_post', array( $this, 'bulk_and_quick_edit_save_post' ), 10, 2 );
		}
	}

	/**
	 * Adds the Selected value inside a div to the product column, so JavaScript can read it into Quick Edit
	 *
	 * @since 1.0
	 *
	 * @param string $column_name
	 * @param int    $post_id
	 */
	public function render_product_columns( $column_name, $post_id ) {
		global $post;

		$product = wc_get_product( $post );

		switch ( $column_name ) {
			case 'name' :
				/* Custom inline data for woocommerce */
				echo '
					<div class="hidden" id="wc_taxify_inline_' . $post_id . '">
						<div class="taxify_tax_class">' . $product->get_tax_class( 'taxify_tax_class' ) . '</div>
					</div>
				';

				break;
		}
	}

	/**
	 * Load the JavaScript for Quick Edit
	 *
	 * @since 1.0
	 */
	public function admin_scripts() {
		$screen  = get_current_screen();
		$suffix  = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		$js_path = WCTAXIFY()->plugin_url() . 'includes/js/backend/';

		if ( ! wp_script_is( 'wc-taxify-quick-edit' ) && in_array( $screen->id, array( 'edit-product' ) ) ) {
			wp_enqueue_script( 'wc-taxify-quick-edit', esc_url( $js_path . 'quick-edit' . $suffix . '.js' ), array( 'jquery' ), WCTAXIFY()->version );
		}
	}

	/**
	 * Display Taxify Tax Class on the General tab of a Simple product, and a Variable parent product
	 *
	 * @since 1.0
	 */
	public function taxify_tax_class_simple() {
		$tax_classes           = WCT_TAX()->get_taxify_tax_classes();
		$classes_options       = array();
		$classes_options[ '' ] = __( 'None', 'woocommerce-taxify' );

		if ( ! empty( $tax_classes ) ) {
			foreach ( $tax_classes as $class ) {
				$classes_options[ sanitize_title( $class ) ] = esc_html( $class );
			}
		}

		woocommerce_wp_select( array(
			                       'id'          => '_taxify_tax_class',
			                       'label'       => __( 'Taxify Tax Class', 'woocommerce-taxify' ) . ' ' . '<span class="dashicons dashicons-update taxify-tax-class-update" title="' . __( 'Reload the Taxify Tax Classes from Taxify', 'woocommerce-taxify' ) . '" style="color: #0073aa;"></span>',
			                       'options'     => $classes_options,
			                       'description' => __( 'Taxify Tax Classes help to further define how Taxify calculates tax for this product.', 'woocommerce-taxify' ),
			                       'desc_tip'    => true,
		                       ) );

		/**
		 * Inline JavaScript
		 */
		ob_start();
		?>
        $('.taxify-tax-class-update').on('click', function () {
        var tax_classes_ajax_data = {
        action: 'tax_classes_ajax',
        tax_classes_ajax_nonce_security: '<?php echo wp_create_nonce( "tax-classes-ajax-nonce-key" ); ?>'
        };
        jQuery.post('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', tax_classes_ajax_data, function( response ) {
        // Success
        if (response) {
        alert('<?php echo esc_js( __( 'This page should automatically reload. If not, then reload the page manually.', 'woocommerce-taxify' ) ); ?>');
        location.reload(true);
        } else {
        alert('<?php echo esc_js( __( 'There was a problem contacting the Taxify API. Please try again in a few minutes.', 'woocommerce-taxify' ) ); ?>');
        location.reload(true);
        }
        });
        });
		<?php
		$javascript = ob_get_clean();
		wc_enqueue_js( $javascript );
	}

	/**
	 * Display Taxify Tax Class on Variations of a Variable parent product
	 *
	 * @since 1.0
	 *
	 * @param int    $loop
	 * @param array  $variation_data
	 * @param object $variation
	 */
	public function taxify_tax_class_variable( $loop, $variation_data, $variation ) {
		global $post;

		$variation_id            = absint( $variation->ID );
		$taxify_tax_class_chosen = WCT_PRODUCT_DATA_STORE()->get_meta( $variation_id, '_taxify_tax_class' );
		$tax_classes             = WCT_TAX()->get_taxify_tax_classes();
		$tax_class_options       = array();

		if ( ! empty( $tax_classes ) ) {
			foreach ( $tax_classes as $class ) {
				$tax_class_options[ sanitize_title( $class ) ] = esc_attr( $class );
			}
		}

		$parent_data = array(
			'id'                       => $post->ID,
			'taxify_tax_class_options' => $tax_class_options,
			'taxify_tax_class'         => WCT_PRODUCT_DATA_STORE()->get_meta( $post->ID, '_taxify_tax_class' ),
		);
		?>
        <div>
            <p class="form-row form-row-full">
                <label for="taxify_tax_class"><?php _e( 'Taxify Tax class:', 'woocommerce-taxify' ); ?></label>
                <select name="variable_taxify_tax_class[<?php echo $loop; ?>]">
                    <option
                            value="parent" <?php selected( is_null( '' ), true ); ?>><?php _e( 'Same as parent', 'woocommerce-taxify' ); ?></option>
					<?php
					foreach ( $parent_data[ 'taxify_tax_class_options' ] as $key => $value ) {
						echo '<option value="' . esc_attr( $key ) . '" ' . selected( $key === $taxify_tax_class_chosen, true, false ) . '>' . esc_html( $value ) . '</option>';
					}
					?></select>
            </p>
        </div>
		<?php
	}

	/**
	 * Display Taxify Tax Class when using Quick Edit for a product
	 *
	 * @since 1.0
	 */
	public function taxify_tax_class_quick_edit() {
		?>
        <br class="clear"/>
        <label class="alignleft">
            <span class="title"><?php _e( 'Taxify Tax Class', 'woocommerce-taxify' ); ?></span>
            <span class="input-text-wrap">
				<select class="taxify_tax_class" name="_taxify_tax_class">
					<?php
					$options = array(
						'' => __( 'None', 'woocommerce-taxify' )
					);

					$tax_classes = WCT_TAX()->get_taxify_tax_classes();

					if ( ! empty( $tax_classes ) ) {
						foreach ( $tax_classes as $class ) {
							$options[ sanitize_title( $class ) ] = esc_html( $class );
						}
					}

					foreach ( $options as $key => $value ) {
						echo '<option value="' . esc_attr( $key ) . '">' . $value . '</option>';
					}
					?>
				</select>
			</span>
        </label>
        <br class="clear"/>
		<?php
	}

	/**
	 * Display Taxify Tax Class when using Bulk Edit for multiple products
	 *
	 * @since 1.0
	 */
	public function taxify_tax_class_bulk_edit() {
		?>
        <label>
            <span class="title"><?php _e( 'Taxify Tax Class', 'woocommerce-taxify' ); ?></span>
            <span class="input-text-wrap">
				<select class="taxify_tax_class" name="_taxify_tax_class">
					<?php
					$options = array(
						'' => __( '— No Change —', 'woocommerce-taxify' ),
					);

					$tax_classes = WCT_TAX()->get_taxify_tax_classes();

					if ( ! empty( $tax_classes ) ) {
						foreach ( $tax_classes as $class ) {
							$options[ sanitize_title( $class ) ] = esc_html( $class );
						}
					}

					foreach ( $options as $key => $value ) {
						echo '<option value="' . esc_attr( $key ) . '">' . $value . '</option>';
					}
					?>
				</select>
			</span>
        </label>
		<?php
	}

	/**
	 * Save data for Simple and Variable products
	 *
	 * @since 1.2.8
	 *
	 * @param int $post_id
	 */
	public function ajax_save_product( $post_id ) {
		$variable_post_ids = ! empty( $_POST[ 'variable_post_id' ] ) ? $_POST[ 'variable_post_id' ] : '';

		if ( ! empty( $variable_post_ids ) && is_array( $variable_post_ids ) ) {
			$max_loop = max( array_keys( $variable_post_ids ) );

			for ( $i = 0; $i <= $max_loop; $i ++ ) {
				if ( ! isset( $variable_post_ids[ $i ] ) ) {
					continue;
				}

				$variation_id = absint( $variable_post_ids[ $i ] );

				$variable_taxify_tax_class = isset( $_POST[ 'variable_taxify_tax_class' ] ) ? $_POST[ 'variable_taxify_tax_class' ] : array();

				WCT_PRODUCT_DATA_STORE()->update_meta( $variation_id, '_taxify_tax_class', sanitize_text_field( $variable_taxify_tax_class[ $i ] ) );

				do_action( 'wc_taxify_ajax_save_product_variation', $variation_id, $i );
			}
		}
	}

	/**
	 * Save data for Simple and Variable products
	 *
	 * @since 1.0
	 *
	 * @param int $post_id
	 */
	public function save_product( $post_id ) {
		$product_type = empty( $_POST[ 'product-type' ] ) ? 'simple' : sanitize_title( stripslashes( $_POST[ 'product-type' ] ) );

		/**
		 * If this is a simple product, or the parent product of variation(s)
		 */
		if ( in_array( $product_type, array(
			'simple',
			'variable',
			'subscription',
			'simple-subscription',
			'variable-subscription',
			'subscription_variation'
		) ) ) {
			if ( isset( $_POST[ '_taxify_tax_class' ] ) ) {
				WCT_PRODUCT_DATA_STORE()->update_meta( $post_id, '_taxify_tax_class', sanitize_text_field( $_POST[ '_taxify_tax_class' ] ) );
			}
		}

		/**
		 * If this is a variable product
		 */
		if ( in_array( $product_type, array(
			'variable',
			'variable-subscription',
			'subscription_variation'
		) ) ) {
			$variable_post_ids = $_POST[ 'variable_post_id' ];

			if ( ! empty( $variable_post_ids ) && is_array( $variable_post_ids ) ) {

				$max_loop = max( array_keys( $variable_post_ids ) );

				for ( $i = 0; $i <= $max_loop; $i ++ ) {
					if ( ! isset( $variable_post_ids[ $i ] ) ) {
						continue;
					}

					$variation_id = absint( $variable_post_ids[ $i ] );

					$variable_taxify_tax_class = isset( $_POST[ 'variable_taxify_tax_class' ] ) ? $_POST[ 'variable_taxify_tax_class' ] : array();

					if ( isset( $variable_taxify_tax_class[ $i ] ) && $variable_taxify_tax_class[ $i ] !== 'parent' ) {
						WCT_PRODUCT_DATA_STORE()->update_meta( $variation_id, '_taxify_tax_class', sanitize_text_field( $variable_taxify_tax_class[ $i ] ) );
					} else {
						WCT_PRODUCT_DATA_STORE()->delete_meta( $variation_id, '_taxify_tax_class' );
					}

					do_action( 'wc_taxify_save_product_variation', $variation_id, $i );
				}
			}
		}
	}

	/**
	 * Determines if data will be saved for Bulk and Quick Edit.
	 *
	 * @since 1.0
	 *
	 * @param int    $post_id
	 * @param object $post
	 *
	 * @return mixed
	 */
	public function bulk_and_quick_edit_save_post( $post_id, $post ) {
		// If this is an autosave, our form has not been submitted, so we don't want to do anything.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $post_id;
		}

		// Don't save revisions and autosaves
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return $post_id;
		}

		// Check post type is product
		if ( 'product' != $post->post_type ) {
			return $post_id;
		}

		// Check user permission
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return $post_id;
		}

		// Check nonces
		if ( ! isset( $_REQUEST[ 'woocommerce_quick_edit_nonce' ] ) && ! isset( $_REQUEST[ 'woocommerce_bulk_edit_nonce' ] ) ) {
			return $post_id;
		}
		if ( isset( $_REQUEST[ 'woocommerce_quick_edit_nonce' ] ) && ! wp_verify_nonce( $_REQUEST[ 'woocommerce_quick_edit_nonce' ], 'woocommerce_quick_edit_nonce' ) ) {
			return $post_id;
		}
		if ( isset( $_REQUEST[ 'woocommerce_bulk_edit_nonce' ] ) && ! wp_verify_nonce( $_REQUEST[ 'woocommerce_bulk_edit_nonce' ], 'woocommerce_bulk_edit_nonce' ) ) {
			return $post_id;
		}

		// Get the product and save
		$product = wc_get_product( $post );

		if ( ! empty( $_REQUEST[ 'woocommerce_quick_edit' ] ) ) {
			$this->quick_edit_save( $post_id, $product );
		} else {
			$this->bulk_edit_save( $post_id, $product );
		}

		// Clear transient
		wc_delete_product_transients( $post_id );

		return $post_id;
	}

	/**
	 * Saves data using Quick Edit for a prodcut
	 *
	 * @since  1.0
	 *
	 * @access private
	 *
	 * @param int    $post_id
	 * @param object $product
	 */
	private function quick_edit_save( $post_id, $product ) {
		$tax_class = sanitize_text_field( $_REQUEST[ '_taxify_tax_class' ] );

		if ( empty( $tax_class ) ) {
			$tax_class = '';
		}

		WCT_PRODUCT_DATA_STORE()->update_meta( $post_id, '_taxify_tax_class', $tax_class );

		do_action( 'wc_taxify_product_quick_edit_save', $product );
	}

	/**
	 * Saves data using Buik Edit for multiple prodcuts
	 *
	 * @since  1.0
	 *
	 * @access private
	 *
	 * @param int    $post_id
	 * @param object $product
	 */
	private function bulk_edit_save( $post_id, $product ) {
		if ( ! empty( $_REQUEST[ '_taxify_tax_class' ] ) ) {
			WCT_PRODUCT_DATA_STORE()->update_meta( $post_id, '_taxify_tax_class', sanitize_text_field( $_REQUEST[ '_taxify_tax_class' ] ) );
		}

		do_action( 'wc_taxify_product_bulk_edit_save', $product );
	}

	public function get_taxify_tax_classes_ajax() {
		check_ajax_referer( 'tax-classes-ajax-nonce-key', 'tax_classes_ajax_nonce_security' );

		$tax_classes = WCT_TAX()->get_taxify_tax_classes_from_api();

		if ( ! empty( $tax_classes ) ) {
			wp_send_json( true );
		}
	}

} // End class
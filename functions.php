/* =========================================================
 * AAKASI COUPON TRACKING
 * ========================================================= */

/**
 * 1. Record coupon usage in user meta when an order is completed.
 */
add_action( 'woocommerce_order_status_completed', 'aakasi_record_used_coupons' );
add_action( 'woocommerce_order_status_processing', 'aakasi_record_used_coupons' );

function aakasi_record_used_coupons( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return;
    }

    $user_id = $order->get_customer_id();
    if ( ! $user_id ) {
        return; // Guest order — see note at bottom of this guide
    }

    $used_codes = array_map( 'strtoupper', $order->get_coupon_codes() );
    if ( empty( $used_codes ) ) {
        return;
    }

    $existing = get_user_meta( $user_id, '_aakasi_used_coupons', true );
    if ( ! is_array( $existing ) ) {
        $existing = array();
    }

    $updated = array_unique( array_merge( $existing, $used_codes ) );
    update_user_meta( $user_id, '_aakasi_used_coupons', $updated );
}

/**
 * 2. Helper: get list of coupons this logged-in user must not see again.
 */
function aakasi_get_hidden_coupons_for_user() {
    $hidden = array();

    // Currently applied in cart (session-based, works for guests too)
    if ( function_exists( 'WC' ) && WC()->cart ) {
        $hidden = array_map( 'strtoupper', WC()->cart->get_applied_coupons() );
    }

    // Previously used (logged-in users only)
    if ( is_user_logged_in() ) {
        $used = get_user_meta( get_current_user_id(), '_aakasi_used_coupons', true );
        if ( is_array( $used ) ) {
            $hidden = array_unique( array_merge( $hidden, $used ) );
        }
    }

    return $hidden;
}

/**
 * 3. AJAX endpoint so the coupon strip can refresh live after apply/remove,
 *    without a full page reload (WooCommerce coupon actions are AJAX-based).
 */
add_action( 'wp_ajax_aakasi_get_hidden_coupons', 'aakasi_ajax_get_hidden_coupons' );
add_action( 'wp_ajax_nopriv_aakasi_get_hidden_coupons', 'aakasi_ajax_get_hidden_coupons' );

function aakasi_ajax_get_hidden_coupons() {
    wp_send_json( aakasi_get_hidden_coupons_for_user() );
}

/**
 * 4. Admin UI: show + allow resetting a user's used-coupon list
 *    on their profile page (Users > Edit User).
 */
add_action( 'show_user_profile', 'aakasi_render_used_coupons_admin_field' );
add_action( 'edit_user_profile', 'aakasi_render_used_coupons_admin_field' );

function aakasi_render_used_coupons_admin_field( $user ) {
    if ( ! current_user_can( 'edit_users' ) ) {
        return;
    }

    $used = get_user_meta( $user->ID, '_aakasi_used_coupons', true );
    if ( ! is_array( $used ) ) {
        $used = array();
    }
    ?>
    <h2>Aakasi Coupon Usage</h2>
    <table class="form-table">
        <tr>
            <th><label>Used Coupons</label></th>
            <td>
                <?php if ( empty( $used ) ) : ?>
                    <p>No coupons used yet.</p>
                <?php else : ?>
                    <?php foreach ( $used as $code ) : ?>
                        <label style="display:block; margin-bottom:6px;">
                            <input type="checkbox" name="aakasi_reset_coupons[]" value="<?php echo esc_attr( $code ); ?>">
                            Reset <strong><?php echo esc_html( $code ); ?></strong> (make it available to this user again)
                        </label>
                    <?php endforeach; ?>
                    <p class="description">Tick a box and click "Update User" / "Update Profile" below to reset that coupon for this customer.</p>
                <?php endif; ?>
            </td>
        </tr>
    </table>
    <?php
}

add_action( 'personal_options_update', 'aakasi_save_used_coupons_admin_field' );
add_action( 'edit_user_profile_update', 'aakasi_save_used_coupons_admin_field' );

function aakasi_save_used_coupons_admin_field( $user_id ) {
    if ( ! current_user_can( 'edit_users' ) ) {
        return;
    }

    $used = get_user_meta( $user_id, '_aakasi_used_coupons', true );
    if ( ! is_array( $used ) ) {
        $used = array();
    }

    $to_reset = isset( $_POST['aakasi_reset_coupons'] ) ? array_map( 'sanitize_text_field', $_POST['aakasi_reset_coupons'] ) : array();

    if ( ! empty( $to_reset ) ) {
        $used = array_diff( $used, $to_reset );
        update_user_meta( $user_id, '_aakasi_used_coupons', array_values( $used ) );
    }
}

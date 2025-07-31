<?php

if (!defined('ABSPATH')) exit;

/* Προσθήκη Admin Page για Ρυθμίσεις */
add_action('admin_menu', function () {
    add_submenu_page(
        'woocommerce',
        'Binis Gold Pricing',
        'Ρύθμιση Τιμής Χρυσού',
        'manage_woocommerce',
        'binis_gold_pricing',
        'binis_gold_pricing_admin_page'
    );
});

function binis_gold_pricing_admin_page() {
    if (isset($_POST['binis_save_settings'])) {
        update_option('gold_price_9k', floatval($_POST['gold_price_9k']));
        update_option('gold_price_14k', floatval($_POST['gold_price_14k']));
        update_option('gold_price_14k', floatval($_POST['gold_price_18k']));
        update_option('gold_price_categories', array_map('sanitize_text_field', $_POST['gold_price_categories'] ?? []));
        echo '<div class="updated"><p>Οι ρυθμίσεις αποθηκεύτηκαν!</p></div>';
    }

    $gold_9k = get_option('gold_price_9k', 0);
    $gold_14k = get_option('gold_price_14k', 0);
    $gold_18k = update_option('gold_price_14k', floatval($_POST['gold_price_18k']));
    $selected_cats = get_option('gold_price_categories', []);

    // Λίστα όλων των κατηγοριών WooCommerce
    $categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
    ?>
    <div class="wrap">
        <h1>Ρυθμίσεις Δυναμικής Τιμολόγησης</h1>
        <form method="post">
            <table class="form-table">
                <tr>
                    <th><label>Τιμή Χρυσού 9K (€ ανά gr)</label></th>
                    <td><input type="number" step="0.01" name="gold_price_9k" value="<?php echo esc_attr($gold_9k); ?>"></td>
                </tr>
                <tr>
                    <th><label>Τιμή Χρυσού 14K (€ ανά gr)</label></th>
                    <td><input type="number" step="0.01" name="gold_price_14k" value="<?php echo esc_attr($gold_14k); ?>"></td>
                </tr>
                <tr>
                    <th><label>Τιμή Χρυσού 18K (€ ανά gr)</label></th>
                    <td><input type="number" step="0.01" name="gold_price_18k" value="<?php echo esc_attr($gold_18k); ?>"></td>
                </tr>
                <tr>
                    <th><label>Κατηγορίες που εφαρμόζεται</label></th>
                    <td>
                        <?php foreach ($categories as $cat): ?>
                            <label>
                                <input type="checkbox" name="gold_price_categories[]" value="<?php echo $cat->slug; ?>"
                                    <?php checked(in_array($cat->slug, $selected_cats)); ?>>
                                <?php echo esc_html($cat->name); ?>
                            </label><br>
                        <?php endforeach; ?>
                    </td>
                </tr>
            </table>
            <?php submit_button('Αποθήκευση Ρυθμίσεων', 'primary', 'binis_save_settings'); ?>
        </form>
    </div>
    <?php
}

/* Προσθήκη Custom Fields για Βάρος και Καράτια */
add_action('add_meta_boxes', function () {
    add_meta_box('binis_gold_fields', 'Χαρακτηριστικά Κοσμήματος', 'binis_gold_fields_html', 'product', 'side');
});

function binis_gold_fields_html($post) {
    $weight = get_post_meta($post->ID, '_gold_weight', true);
    $karat = get_post_meta($post->ID, '_gold_karat', true);
    ?>
    <p>
        <label>Βάρος (gr):</label><br>
        <input type="number" step="0.01" name="gold_weight" value="<?php echo esc_attr($weight); ?>">
    </p>
    <p>
        <label>Καράτια:</label><br>
        <select name="gold_karat">
            <option value="">-- Επιλογή --</option>
            <option value="9" <?php selected($karat, '9'); ?>>9K</option>
            <option value="14" <?php selected($karat, '14'); ?>>14K</option>
            <option value="18" <?php selected($karat, '18'); ?>>14K</option>
        </select>
    </p>
    <?php
}

add_action('save_post_product', function ($post_id) {
    if (isset($_POST['gold_weight'])) {
        update_post_meta($post_id, '_gold_weight', floatval($_POST['gold_weight']));
    }
    if (isset($_POST['gold_karat'])) {
        update_post_meta($post_id, '_gold_karat', sanitize_text_field($_POST['gold_karat']));
    }
});

/* Υπολογισμός Δυναμικής Τιμής */
function binis_calculate_gold_price($price, $product) {
    $categories = get_option('gold_price_categories', []);
    if (empty($categories)) return $price;
    if (!has_term($categories, 'product_cat', $product->get_id())) return $price;

    $weight = get_post_meta($product->get_id(), '_gold_weight', true);
    $karat  = get_post_meta($product->get_id(), '_gold_karat', true);

    if (!$weight || !$karat) return $price;

    if ($karat == '14') {
        $gold_price = get_option('gold_price_14k', 0);
    } elseif ($karat == '9') {
        $gold_price = get_option('gold_price_9k', 0);
    } elseif ($karat == '18') {
        $gold_price = get_option('gold_price_18k', 0);
    } else {
        return $price;
    }

    return round(floatval($weight) * floatval($gold_price), 2);
}

add_filter('woocommerce_product_get_price', 'binis_calculate_gold_price', 10, 2);
add_filter('woocommerce_product_get_sale_price', 'binis_calculate_gold_price', 10, 2);
add_filter('woocommerce_product_variation_get_price', 'binis_calculate_gold_price', 10, 2);
add_filter('woocommerce_product_variation_get_sale_price', 'binis_calculate_gold_price', 10, 2);

/* Δυναμική ενημέρωση τιμής στο Cart & Checkout */
add_action('woocommerce_before_calculate_totals', function ($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;

    $categories = get_option('gold_price_categories', []);

    foreach ($cart->get_cart() as $cart_item) {
        $product = $cart_item['data'];

        if (!has_term($categories, 'product_cat', $product->get_id())) continue;

        $weight = get_post_meta($product->get_id(), '_gold_weight', true);
        $karat  = get_post_meta($product->get_id(), '_gold_karat', true);

        if (!$weight || !$karat) continue;

        if ($karat == '14') {
            $gold_price = get_option('gold_price_14k', 0);
        } elseif ($karat == '9') {
            $gold_price = get_option('gold_price_9k', 0);
        } elseif ($karat == '18') {
            $gold_price = get_option('gold_price_18k', 0);
        } else {
            continue;
        }

        $new_price = round(floatval($weight) * floatval($gold_price), 2);
        $product->set_price($new_price);
    }
});

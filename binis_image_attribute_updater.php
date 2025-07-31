<?php
if (!defined('ABSPATH')) exit;

//Admin Menu
add_action('admin_menu', function () {
    add_submenu_page(
        'woocommerce',
        'Update Product Alt Texts',
        'Image Attribute Updater',
        'manage_woocommerce',
        'binis_plugin',
        'binis_admin_page'
    );
});

//Admin page HTML
function binis_admin_page() {
    ?>
    <div class="wrap">
        <h1>WooCommerce Alt Text Updater</h1>
        <form method="post">
            <?php submit_button('Ενημέρωση Όλων των Εικόνων', 'primary', 'update_alt'); ?>
            <?php submit_button('Εκκαθάριση Ιδιοτήτων Εικόνων', 'secondary', 'reset_alt'); ?>
        </form>
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['update_alt'])) {
                binis_update_all_alt_texts();
            } elseif (isset($_POST['reset_alt'])) {
                binis_reset_all_image_fields();
            }
        }
        ?>
    </div>
    <?php
}

function binis_update_all_alt_texts() {
    $args = [
        'post_type' => 'product',
        'posts_per_page' => -1,
        'post_status' => 'publish',
    ];

    $query = new WP_Query($args);
    $updated = 0;

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $product = wc_get_product(get_the_ID());
            if (!$product) continue;

            $product_name = $product->get_name();
            $store_name = get_bloginfo('name');

            // --- FEATURED IMAGE ---
            $featured_id = $product->get_image_id();
            if ($featured_id) {
                binis_update_image_fields($featured_id, $product_name, $store_name, 'Featured');
                $updated++;
            }

            // --- GALLERY IMAGES ---
            $gallery_ids = $product->get_gallery_image_ids();
            foreach ($gallery_ids as $gallery_id) {
                binis_update_image_fields($gallery_id, $product_name, $store_name, 'Gallery');
                $updated++;
            }

            // --- VARIATION IMAGES ---
            if ($product->is_type('variable')) {
                $variations = $product->get_children();
                foreach ($variations as $variation_id) {
                    $variation = wc_get_product($variation_id);
                    if ($variation) {
                        $variation_image_id = $variation->get_image_id();
                        if ($variation_image_id) {
                            binis_update_image_fields($variation_image_id, $product_name, $store_name, 'Variation');
                            $updated++;
                        }
                    }
                }
            }
        }
        wp_reset_postdata();
    }

    echo "<p><strong>Έτοιμο! Ενημερώθηκαν $updated εικόνες.</strong></p>";
}

function binis_reset_all_image_fields() {
    $args = [
        'post_type' => 'product',
        'posts_per_page' => -1,
        'post_status' => 'publish',
    ];

    $query = new WP_Query($args);
    $reset = 0;

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $product = wc_get_product(get_the_ID());
            if (!$product) continue;

            $image_ids = [];

            // Featured image
            $featured_id = $product->get_image_id();
            if ($featured_id) $image_ids[] = $featured_id;

            // Gallery images
            $image_ids = array_merge($image_ids, $product->get_gallery_image_ids());

            // Variation images
            if ($product->is_type('variable')) {
                foreach ($product->get_children() as $variation_id) {
                    $variation = wc_get_product($variation_id);
                    if ($variation && $variation->get_image_id()) {
                        $image_ids[] = $variation->get_image_id();
                    }
                }
            }

            // Reset all image fields
            foreach (array_unique($image_ids) as $id) {
                delete_post_meta($id, '_wp_attachment_image_alt');

                wp_update_post([
                    'ID' => $id,
                    'post_title' => '',
                    'post_excerpt' => '',
                    'post_content' => '',
                ]);

                $reset++;
            }
        }
        wp_reset_postdata();
    }

    echo "<p><strong>Η επαναφορά ολοκληρώθηκε. Έγινε εκκαθάριση $reset εικόνων.</strong></p>";
}

function binis_update_image_fields($attachment_id, $product_name, $store_name, $type_label) {
    $alt_text = "$product_name - $store_name";
    $title = "$product_name – $store_name";
    $caption = "$product_name στο $store_name.";
    $description = "Image of $product_name. Διαθέσιμο στο $store_name.";

    // Alt text
    update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);

    // Title, Caption, Description
    wp_update_post([
        'ID' => $attachment_id,
        'post_title' => $title,
        'post_excerpt' => $caption,
        'post_content' => $description,
    ]);
}

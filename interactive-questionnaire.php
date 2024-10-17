<?php
/**
 * Plugin Name: Interactive Questionnaire
 * Description: An interactive questionnaire plugin with product recommendations
 * Version: 1.1
 * Author: Aaron Lowndes
 */

// Enqueue scripts and styles
function questionnaire_enqueue_scripts() {
    wp_enqueue_script('react', 'https://unpkg.com/react@17/umd/react.production.min.js', array(), null, true);
    wp_enqueue_script('react-dom', 'https://unpkg.com/react-dom@17/umd/react-dom.production.min.js', array('react'), null, true);
    wp_enqueue_script('questionnaire', plugin_dir_url(__FILE__) . 'js/questionnaire.js', array('react', 'react-dom'), '1.0', true);
    wp_enqueue_style('questionnaire-style', plugin_dir_url(__FILE__) . 'css/questionnaire.css');

    // Pass the URL of the JSON file to JavaScript
    wp_localize_script('questionnaire', 'questionnaireData', array(
        'jsonUrl' => plugin_dir_url(__FILE__) . 'js/questions.json',
        'ajaxUrl' => admin_url('admin-ajax.php')
    ));
}
add_action('wp_enqueue_scripts', 'questionnaire_enqueue_scripts');

// Custom function to display product with uncropped image
function display_uncropped_product($product_slug) {
    // Log the received slug
    error_log('display_uncropped_product called with slug: ' . $product_slug);

    // Attempt to get the product ID by slug
    $product_id = wc_get_product_id_by_slug($product_slug);
    if (!$product_id) {
        error_log('No product found with slug: ' . $product_slug);
        return '';
    }

    // Log the found product ID
    error_log('Found product ID: ' . $product_id);

    // Attempt to get the product by ID
    $product = wc_get_product($product_id);
    if (!$product) {
        error_log('No product found with ID: ' . $product_id);
        return '';
    }

    // Log the product details
    error_log('Product found: ' . print_r($product, true));

    $image_id = $product->get_image_id();
    $image_url = wp_get_attachment_image_url($image_id, 'medium_large');

    ob_start();
    ?>
    <div class="questionnaire-product">
        <a href="<?php echo esc_url($product->get_permalink()); ?>">
            <img src="<?php echo esc_url($image_url); ?>"
                 alt="<?php echo esc_attr($product->get_name()); ?>"
                 class="product-image product-image-medium">
        </a>
        <a href="<?php echo esc_url($product->get_permalink()); ?>">
            <h2 class="woocommerce-loop-product__title"><?php echo esc_html($product->get_name()); ?></h2>
        </a>
        <span class="price"><?php echo $product->get_price_html(); ?></span>
        <a href="<?php echo esc_url($product->get_permalink()); ?>" class="button">Read more</a>
    </div>
    <?php
    return ob_get_clean();
}

// Shortcode function
function questionnaire_shortcode() {
    ob_start();
    ?>
    <div id="questionnaire-root"></div>
    <div id="recommended-product" class="questionnaire-product-recommendation" style="display: none;"></div>
    <?php
    return ob_get_clean();
}
add_shortcode('questionnaire', 'questionnaire_shortcode');

// AJAX handler to display uncropped product
function ajax_display_uncropped_product() {
    if (isset($_POST['product_slug'])) {
        $product_slug = sanitize_text_field($_POST['product_slug']);
        error_log('Received product_slug: ' . $product_slug);
        $output = display_uncropped_product($product_slug);
        if ($output) {
            echo $output;
        } else {
            error_log('Failed to retrieve product for slug: ' . $product_slug);
            echo 'Error: Product not found';
        }
    } else {
        error_log('product_slug not set in POST request');
        echo 'Error: product_slug not set';
    }
    wp_die();
}
add_action('wp_ajax_display_uncropped_product', 'ajax_display_uncropped_product');
add_action('wp_ajax_nopriv_display_uncropped_product', 'ajax_display_uncropped_product');

// Custom function to get product ID by slug
function wc_get_product_id_by_slug($slug) {
    // Query to get the product by slug
    $args = array(
        'name'        => $slug,
        'post_type'   => 'product',
        'post_status' => 'publish',
        'numberposts' => 1
    );
    $products = get_posts($args);

    // Return the product ID if found, otherwise return null
    return $products ? $products[0]->ID : null;
}
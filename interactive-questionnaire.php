<?php
/**
 * Plugin Name: Interactive Questionnaire
 * Plugin URI: https://github.com/ajlowndes/interactive-questionnaire
 * Description: An interactive questionnaire plugin with product recommendations
 * Version: 2.0
 * Author: Aaron Lowndes
 * Text Domain: interactive-questionnaire
 * Domain Path: /languages
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('IQ_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('IQ_PLUGIN_URL', plugin_dir_url(__FILE__));
define('IQ_DB_VERSION', '1.0');

// Include admin functionality
require_once IQ_PLUGIN_DIR . 'admin/admin.php';

// Database tables
global $iq_db_version;
$iq_db_version = IQ_DB_VERSION;

// Activation hook - create database tables
register_activation_hook(__FILE__, 'iq_activate_plugin');

function iq_activate_plugin() {
    global $wpdb;
    global $iq_db_version;
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Questionnaires table
    $table_questionnaires = $wpdb->prefix . 'iq_questionnaires';
    $sql_questionnaires = "CREATE TABLE $table_questionnaires (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        title varchar(255) NOT NULL,
        introduction text,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    
    // Nodes table
    $table_nodes = $wpdb->prefix . 'iq_nodes';
    $sql_nodes = "CREATE TABLE $table_nodes (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        questionnaire_id mediumint(9) NOT NULL,
        node_key varchar(255) NOT NULL,
        is_start tinyint(1) NOT NULL DEFAULT 0,
        node_type varchar(50) NOT NULL DEFAULT 'question',
        question text,
        recommendation text,
        product_slug varchar(255),
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY node_key (questionnaire_id, node_key)
    ) $charset_collate;";
    
    // Answers table
    $table_answers = $wpdb->prefix . 'iq_answers';
    $sql_answers = "CREATE TABLE $table_answers (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        node_id mediumint(9) NOT NULL,
        text text NOT NULL,
        next_node_key varchar(255) NOT NULL,
        sort_order int(11) NOT NULL DEFAULT 0,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    
    dbDelta($sql_questionnaires);
    dbDelta($sql_nodes);
    dbDelta($sql_answers);
    
    add_option('iq_db_version', $iq_db_version);
    
    // Import existing data
    iq_import_default_questionnaire();
}

// Import existing questionnaire data
function iq_import_default_questionnaire() {
    $json_file = IQ_PLUGIN_DIR . 'js/betterquestionsstructure.json';
    
    if (file_exists($json_file)) {
        $json_data = file_get_contents($json_file);
        $questionnaire_data = json_decode($json_data, true);
        
        if (!$questionnaire_data) {
            return;
        }
        
        global $wpdb;
        
        // Begin transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            // Insert questionnaire
            $questionnaire_id = iq_insert_questionnaire([
                'title' => isset($questionnaire_data['metadata']['title']) ? $questionnaire_data['metadata']['title'] : 'Rock Climbing Questionnaire',
                'introduction' => isset($questionnaire_data['metadata']['introduction']) ? $questionnaire_data['metadata']['introduction'] : ''
            ]);
            
            if (!$questionnaire_id) {
                throw new Exception('Failed to insert questionnaire');
            }
            
            // Process nested structure recursively starting from 'start' node
            iq_process_nodes($questionnaire_id, $questionnaire_data['questions'], 'start', true);
            
            // Commit transaction
            $wpdb->query('COMMIT');
        } catch (Exception $e) {
            // Rollback transaction on error
            $wpdb->query('ROLLBACK');
            error_log('Failed to import default questionnaire: ' . $e->getMessage());
        }
    }
}

function iq_process_nodes($questionnaire_id, $questions, $node_key, $is_start = false) {
    if (!isset($questions[$node_key])) {
        return;
    }
    
    global $wpdb;
    $processed_nodes = [];
    
    $process_node = function($node_key, $node_data, $is_start = false) use ($questionnaire_id, $wpdb, &$processed_nodes, &$process_node, $questions) {
        // Skip if already processed
        if (isset($processed_nodes[$node_key])) {
            return;
        }
        
        $processed_nodes[$node_key] = true;
        
        // Determine node type
        $node_type = isset($node_data['recommendation']) ? 'recommendation' : 'question';
        
        // Insert node
        $node_id = iq_insert_node([
            'questionnaire_id' => $questionnaire_id,
            'node_key' => $node_key,
            'is_start' => $is_start ? 1 : 0,
            'node_type' => $node_type,
            'question' => isset($node_data['question']) ? $node_data['question'] : '',
            'recommendation' => isset($node_data['recommendation']) ? $node_data['recommendation'] : '',
            'product_slug' => isset($node_data['productSlug']) ? $node_data['productSlug'] : ''
        ]);
        
        // Process answers if this is a question node
        if ($node_type == 'question' && isset($node_data['answers']) && is_array($node_data['answers'])) {
            foreach ($node_data['answers'] as $index => $answer) {
                // Determine next node key
                $next_node_key = isset($answer['next']) ? (is_array($answer['next']) ? 'node_' . uniqid() : $answer['next']) : '';
                
                // Insert answer
                iq_insert_answer([
                    'node_id' => $node_id,
                    'text' => $answer['text'],
                    'next_node_key' => $next_node_key,
                    'sort_order' => $index
                ]);
                
                // Process next node if it's an embedded object
                if (isset($answer['next']) && is_array($answer['next'])) {
                    // Add the new node to the questions array with a generated key
                    $questions[$next_node_key] = $answer['next'];
                    $process_node($next_node_key, $answer['next']);
                }
            }
        }
    };
    
    $process_node($node_key, $questions[$node_key], $is_start);
}

function iq_insert_questionnaire($data) {
    global $wpdb;
    
    $result = $wpdb->insert(
        $wpdb->prefix . 'iq_questionnaires',
        [
            'title' => $data['title'],
            'introduction' => $data['introduction']
        ]
    );
    
    return $result ? $wpdb->insert_id : false;
}

function iq_insert_node($data) {
    global $wpdb;
    
    $result = $wpdb->insert(
        $wpdb->prefix . 'iq_nodes',
        [
            'questionnaire_id' => $data['questionnaire_id'],
            'node_key' => $data['node_key'],
            'is_start' => $data['is_start'],
            'node_type' => $data['node_type'],
            'question' => $data['question'],
            'recommendation' => $data['recommendation'],
            'product_slug' => $data['product_slug']
        ]
    );
    
    return $result ? $wpdb->insert_id : false;
}

function iq_insert_answer($data) {
    global $wpdb;
    
    $result = $wpdb->insert(
        $wpdb->prefix . 'iq_answers',
        [
            'node_id' => $data['node_id'],
            'text' => $data['text'],
            'next_node_key' => $data['next_node_key'],
            'sort_order' => $data['sort_order']
        ]
    );
    
    return $result ? $wpdb->insert_id : false;
}

// Uninstallation hook - remove database tables
register_uninstall_hook(__FILE__, 'iq_uninstall_plugin');

function iq_uninstall_plugin() {
    global $wpdb;
    
    // Drop tables
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}iq_answers");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}iq_nodes");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}iq_questionnaires");
    
    // Delete options
    delete_option('iq_db_version');
}

// Enqueue scripts and styles
function questionnaire_enqueue_scripts() {
    wp_enqueue_script('react', 'https://unpkg.com/react@17/umd/react.production.min.js', array(), null, true);
    wp_enqueue_script('react-dom', 'https://unpkg.com/react-dom@17/umd/react-dom.production.min.js', array('react'), null, true);
    wp_enqueue_script('questionnaire', IQ_PLUGIN_URL . 'js/questionnaire.js', array('react', 'react-dom'), '2.0', true);
    wp_enqueue_style('questionnaire-style', IQ_PLUGIN_URL . 'css/questionnaire.css', array(), '2.0', 'all');

    // Pass AJAX URL to JavaScript
    wp_localize_script('questionnaire', 'questionnaireData', array(
        'ajaxUrl' => admin_url('admin-ajax.php')
    ));
}
add_action('wp_enqueue_scripts', 'questionnaire_enqueue_scripts');

// API Functions
function iq_get_questionnaires() {
    global $wpdb;
    
    $questionnaires = $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}iq_questionnaires ORDER BY id DESC",
        ARRAY_A
    );
    
    return $questionnaires;
}

function iq_get_questionnaire($id) {
    global $wpdb;
    
    $questionnaire = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}iq_questionnaires WHERE id = %d",
            $id
        ),
        ARRAY_A
    );
    
    if (!$questionnaire) {
        return false;
    }
    
    // Get the start node
    $start_node = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}iq_nodes WHERE questionnaire_id = %d AND is_start = 1",
            $id
        ),
        ARRAY_A
    );
    
    if (!$start_node) {
        return false;
    }
    
    // Build the questionnaire data
    $data = [
        'metadata' => [
            'title' => $questionnaire['title'],
            'introduction' => $questionnaire['introduction']
        ],
        'questions' => []
    ];
    
    // Get all nodes for this questionnaire
    $nodes = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}iq_nodes WHERE questionnaire_id = %d",
            $id
        ),
        ARRAY_A
    );
    
    $nodes_by_id = [];
    foreach ($nodes as $node) {
        $nodes_by_id[$node['id']] = $node;
    }
    
    // Get all answers for this questionnaire
    $answers = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT a.* FROM {$wpdb->prefix}iq_answers a
            JOIN {$wpdb->prefix}iq_nodes n ON a.node_id = n.id
            WHERE n.questionnaire_id = %d
            ORDER BY a.sort_order ASC",
            $id
        ),
        ARRAY_A
    );
    
    // Build the questions structure
    foreach ($nodes as $node) {
        $node_data = [];
        
        if ($node['node_type'] === 'question') {
            $node_data['question'] = $node['question'];
            $node_data['answers'] = [];
            
            // Add answers to this node
            foreach ($answers as $answer) {
                if ($answer['node_id'] == $node['id']) {
                    $node_data['answers'][] = [
                        'text' => $answer['text'],
                        'next' => $answer['next_node_key']
                    ];
                }
            }
        } else {
            $node_data['recommendation'] = $node['recommendation'];
            if (!empty($node['product_slug'])) {
                $node_data['productSlug'] = $node['product_slug'];
            }
        }
        
        $data['questions'][$node['node_key']] = $node_data;
    }
    
    return $data;
}

// AJAX handler to get questionnaire data
function ajax_get_questionnaire_data() {
    $questionnaire_id = isset($_GET['id']) ? intval($_GET['id']) : null;
    
    if (!$questionnaire_id) {
        // Get the latest questionnaire if no ID is provided
        $questionnaires = iq_get_questionnaires();
        
        if (!empty($questionnaires)) {
            $questionnaire_id = $questionnaires[0]['id'];
        } else {
            wp_send_json_error('No questionnaires found');
            return;
        }
    }
    
    $questionnaire_data = iq_get_questionnaire($questionnaire_id);
    
    if ($questionnaire_data) {
        wp_send_json_success($questionnaire_data);
    } else {
        wp_send_json_error('Questionnaire not found');
    }
}
add_action('wp_ajax_get_questionnaire_data', 'ajax_get_questionnaire_data');
add_action('wp_ajax_nopriv_get_questionnaire_data', 'ajax_get_questionnaire_data');

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
        <a href="<?php echo esc_url($product->get_permalink()); ?>" class="wp-block-button__link wp-element-button wc-block-components-product-button__button product_type_simple"><span>Book Here</span></a>
    </div>
    <?php
    return ob_get_clean();
}

// Shortcode function
function questionnaire_shortcode($atts) {
    $atts = shortcode_atts(
        [
            'id' => 0,
        ],
        $atts,
        'questionnaire'
    );
    
    $questionnaire_id = intval($atts['id']);
    
    if (!$questionnaire_id) {
        // Get the latest questionnaire if no ID is provided
        $questionnaires = iq_get_questionnaires();
        
        if (!empty($questionnaires)) {
            $questionnaire_id = $questionnaires[0]['id'];
        } else {
            return '<p>No questionnaires available.</p>';
        }
    }
    
    // Pass the questionnaire ID to JavaScript
    wp_localize_script('questionnaire', 'questionnaireParams', [
        'id' => $questionnaire_id
    ]);
    
    ob_start();
    ?>
    <div id="questionnaire-root" data-id="<?php echo esc_attr($questionnaire_id); ?>"></div>
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
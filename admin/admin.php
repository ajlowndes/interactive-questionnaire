<?php
/**
 * Admin functionality for Interactive Questionnaire
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Add admin menu
add_action('admin_menu', 'iq_add_admin_menu');

function iq_add_admin_menu() {
    add_menu_page(
        __('Interactive Questionnaire', 'interactive-questionnaire'),
        __('Questionnaires', 'interactive-questionnaire'),
        'manage_options',
        'interactive-questionnaire',
        'iq_admin_page',
        'dashicons-clipboard',
        25
    );
    
    add_submenu_page(
        'interactive-questionnaire',
        __('Manage Questionnaires', 'interactive-questionnaire'),
        __('All Questionnaires', 'interactive-questionnaire'),
        'manage_options',
        'interactive-questionnaire',
        'iq_admin_page'
    );
    
    add_submenu_page(
        'interactive-questionnaire',
        __('Add New Questionnaire', 'interactive-questionnaire'),
        __('Add New', 'interactive-questionnaire'),
        'manage_options',
        'interactive-questionnaire-new',
        'iq_add_questionnaire_page'
    );
    
    // Add submenus for edit and nodes, but hide them from the menu
    add_submenu_page(
        null,
        __('Edit Questionnaire', 'interactive-questionnaire'),
        __('Edit Questionnaire', 'interactive-questionnaire'),
        'manage_options',
        'interactive-questionnaire-edit',
        'iq_edit_questionnaire_page'
    );
    
    add_submenu_page(
        null,
        __('Manage Nodes', 'interactive-questionnaire'),
        __('Manage Nodes', 'interactive-questionnaire'),
        'manage_options',
        'interactive-questionnaire-nodes',
        'iq_manage_nodes_page'
    );
    
    add_submenu_page(
        null,
        __('Edit Node', 'interactive-questionnaire'),
        __('Edit Node', 'interactive-questionnaire'),
        'manage_options',
        'interactive-questionnaire-edit-node',
        'iq_edit_node_page'
    );
}

// Enqueue admin scripts and styles
add_action('admin_enqueue_scripts', 'iq_admin_enqueue_scripts');

function iq_admin_enqueue_scripts($hook) {
    // Only load on plugin admin pages
    if (strpos($hook, 'interactive-questionnaire') === false) {
        return;
    }
    
    wp_enqueue_style('iq-admin-style', IQ_PLUGIN_URL . 'admin/css/admin.css', array(), '1.0.0');
    wp_enqueue_script('iq-admin-script', IQ_PLUGIN_URL . 'admin/js/admin.js', array('jquery', 'jquery-ui-sortable'), '1.0.0', true);
    
    // Pass localized data to the script
    wp_localize_script('iq-admin-script', 'iqAdminData', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('iq_admin_nonce')
    ));
}

// Create admin directory structure if it doesn't exist
function iq_create_admin_dirs() {
    $dirs = array(
        IQ_PLUGIN_DIR . 'admin/css',
        IQ_PLUGIN_DIR . 'admin/js'
    );
    
    foreach ($dirs as $dir) {
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }
    }
}
register_activation_hook(IQ_PLUGIN_DIR . 'interactive-questionnaire.php', 'iq_create_admin_dirs');

// Main admin page - List all questionnaires
function iq_admin_page() {
    global $wpdb;
    
    // Process delete action
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']) && isset($_GET['_wpnonce'])) {
        if (wp_verify_nonce($_GET['_wpnonce'], 'delete_questionnaire_' . $_GET['id'])) {
            $id = intval($_GET['id']);
            iq_delete_questionnaire($id);
            
            // Redirect to remove the action from the URL
            wp_redirect(admin_url('admin.php?page=interactive-questionnaire&deleted=1'));
            exit;
        }
    }
    
    // Get all questionnaires
    $questionnaires = $wpdb->get_results(
        "SELECT q.*, 
        (SELECT COUNT(*) FROM {$wpdb->prefix}iq_nodes WHERE questionnaire_id = q.id) as node_count
        FROM {$wpdb->prefix}iq_questionnaires q
        ORDER BY q.id DESC",
        ARRAY_A
    );
    
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline"><?php _e('Interactive Questionnaires', 'interactive-questionnaire'); ?></h1>
        <a href="<?php echo admin_url('admin.php?page=interactive-questionnaire-new'); ?>" class="page-title-action"><?php _e('Add New', 'interactive-questionnaire'); ?></a>
        
        <?php if (isset($_GET['deleted']) && $_GET['deleted'] == 1): ?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e('Questionnaire deleted successfully.', 'interactive-questionnaire'); ?></p>
            </div>
        <?php endif; ?>
        
        <hr class="wp-header-end">
        
        <div class="tablenav top">
            <div class="alignleft actions">
                <p><?php _e('Manage your interactive questionnaires here. Add new ones, edit existing ones, or delete them.', 'interactive-questionnaire'); ?></p>
            </div>
            <br class="clear">
        </div>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col" class="manage-column column-id"><?php _e('ID', 'interactive-questionnaire'); ?></th>
                    <th scope="col" class="manage-column column-title column-primary"><?php _e('Title', 'interactive-questionnaire'); ?></th>
                    <th scope="col" class="manage-column column-nodes"><?php _e('Nodes', 'interactive-questionnaire'); ?></th>
                    <th scope="col" class="manage-column column-shortcode"><?php _e('Shortcode', 'interactive-questionnaire'); ?></th>
                    <th scope="col" class="manage-column column-date"><?php _e('Created', 'interactive-questionnaire'); ?></th>
                    <th scope="col" class="manage-column column-date"><?php _e('Updated', 'interactive-questionnaire'); ?></th>
                    <th scope="col" class="manage-column column-actions"><?php _e('Actions', 'interactive-questionnaire'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($questionnaires)): ?>
                    <tr>
                        <td colspan="7"><?php _e('No questionnaires found.', 'interactive-questionnaire'); ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($questionnaires as $questionnaire): ?>
                        <tr>
                            <td><?php echo esc_html($questionnaire['id']); ?></td>
                            <td class="column-primary">
                                <strong>
                                    <a href="<?php echo admin_url('admin.php?page=interactive-questionnaire-edit&id=' . $questionnaire['id']); ?>">
                                        <?php echo esc_html($questionnaire['title'] ?: __('(No title)', 'interactive-questionnaire')); ?>
                                    </a>
                                </strong>
                                <div class="row-actions">
                                    <span class="edit">
                                        <a href="<?php echo admin_url('admin.php?page=interactive-questionnaire-edit&id=' . $questionnaire['id']); ?>">
                                            <?php _e('Edit', 'interactive-questionnaire'); ?>
                                        </a> | 
                                    </span>
                                    <span class="nodes">
                                        <a href="<?php echo admin_url('admin.php?page=interactive-questionnaire-nodes&id=' . $questionnaire['id']); ?>">
                                            <?php _e('Manage Nodes', 'interactive-questionnaire'); ?>
                                        </a> | 
                                    </span>
                                    <span class="delete">
                                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=interactive-questionnaire&action=delete&id=' . $questionnaire['id']), 'delete_questionnaire_' . $questionnaire['id']); ?>" class="submitdelete" onclick="return confirm('<?php _e('Are you sure you want to delete this questionnaire? This action cannot be undone.', 'interactive-questionnaire'); ?>')">
                                            <?php _e('Delete', 'interactive-questionnaire'); ?>
                                        </a>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <?php echo intval($questionnaire['node_count']); ?>
                                <div class="row-actions">
                                    <span class="edit">
                                        <a href="<?php echo admin_url('admin.php?page=interactive-questionnaire-nodes&id=' . $questionnaire['id']); ?>">
                                            <?php _e('Manage', 'interactive-questionnaire'); ?>
                                        </a>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <code>[questionnaire id="<?php echo esc_attr($questionnaire['id']); ?>"]</code>
                                <button type="button" class="button button-small copy-shortcode" data-shortcode='[questionnaire id="<?php echo esc_attr($questionnaire['id']); ?>"]'>
                                    <?php _e('Copy', 'interactive-questionnaire'); ?>
                                </button>
                            </td>
                            <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($questionnaire['created_at']))); ?></td>
                            <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($questionnaire['updated_at']))); ?></td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=interactive-questionnaire-edit&id=' . $questionnaire['id']); ?>" class="button button-small">
                                    <?php _e('Edit', 'interactive-questionnaire'); ?>
                                </a>
                                <a href="<?php echo admin_url('admin.php?page=interactive-questionnaire-nodes&id=' . $questionnaire['id']); ?>" class="button button-small">
                                    <?php _e('Nodes', 'interactive-questionnaire'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

// Add new questionnaire page
function iq_add_questionnaire_page() {
    // Handle form submission
    if (isset($_POST['iq_add_questionnaire']) && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'iq_add_questionnaire')) {
        $title = sanitize_text_field($_POST['title']);
        $introduction = wp_kses_post($_POST['introduction']);
        
        if (empty($title)) {
            $error = __('Title is required.', 'interactive-questionnaire');
        } else {
            // Insert questionnaire
            $questionnaire_id = iq_insert_questionnaire([
                'title' => $title,
                'introduction' => $introduction
            ]);
            
            if ($questionnaire_id) {
                // Create default start node
                $node_id = iq_insert_node([
                    'questionnaire_id' => $questionnaire_id,
                    'node_key' => 'start',
                    'is_start' => 1,
                    'node_type' => 'question',
                    'question' => __('This is your first question', 'interactive-questionnaire'),
                    'recommendation' => '',
                    'product_slug' => ''
                ]);
                
                // Redirect to node management
                wp_redirect(admin_url('admin.php?page=interactive-questionnaire-nodes&id=' . $questionnaire_id));
                exit;
            } else {
                $error = __('Failed to create questionnaire.', 'interactive-questionnaire');
            }
        }
    }
    
    ?>
    <div class="wrap">
        <h1><?php _e('Add New Questionnaire', 'interactive-questionnaire'); ?></h1>
        
        <?php if (isset($error)): ?>
            <div class="notice notice-error is-dismissible">
                <p><?php echo esc_html($error); ?></p>
            </div>
        <?php endif; ?>
        
        <form method="post" action="">
            <?php wp_nonce_field('iq_add_questionnaire'); ?>
            
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">
                        <label for="title"><?php _e('Title', 'interactive-questionnaire'); ?></label>
                    </th>
                    <td>
                        <input name="title" type="text" id="title" value="<?php echo isset($_POST['title']) ? esc_attr($_POST['title']) : ''; ?>" class="regular-text">
                        <p class="description"><?php _e('The title of your questionnaire.', 'interactive-questionnaire'); ?></p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">
                        <label for="introduction"><?php _e('Introduction', 'interactive-questionnaire'); ?></label>
                    </th>
                    <td>
                        <textarea name="introduction" id="introduction" rows="5" class="large-text"><?php echo isset($_POST['introduction']) ? esc_textarea($_POST['introduction']) : ''; ?></textarea>
                        <p class="description"><?php _e('An optional introduction text that will be displayed at the beginning of the questionnaire.', 'interactive-questionnaire'); ?></p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="iq_add_questionnaire" class="button button-primary" value="<?php _e('Create Questionnaire', 'interactive-questionnaire'); ?>">
            </p>
        </form>
    </div>
    <?php
}

// Edit questionnaire page
function iq_edit_questionnaire_page() {
    global $wpdb;
    
    // Get questionnaire ID
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if (!$id) {
        wp_die(__('Invalid questionnaire ID.', 'interactive-questionnaire'));
    }
    
    // Get questionnaire data
    $questionnaire = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}iq_questionnaires WHERE id = %d",
            $id
        ),
        ARRAY_A
    );
    
    if (!$questionnaire) {
        wp_die(__('Questionnaire not found.', 'interactive-questionnaire'));
    }
    
    // Handle form submission
    if (isset($_POST['iq_edit_questionnaire']) && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'iq_edit_questionnaire_' . $id)) {
        $title = sanitize_text_field($_POST['title']);
        $introduction = wp_kses_post($_POST['introduction']);
        
        if (empty($title)) {
            $error = __('Title is required.', 'interactive-questionnaire');
        } else {
            // Update questionnaire
            $result = $wpdb->update(
                $wpdb->prefix . 'iq_questionnaires',
                [
                    'title' => $title,
                    'introduction' => $introduction
                ],
                ['id' => $id],
                ['%s', '%s'],
                ['%d']
            );
            
            if ($result !== false) {
                $success = __('Questionnaire updated successfully.', 'interactive-questionnaire');
                
                // Refresh questionnaire data
                $questionnaire['title'] = $title;
                $questionnaire['introduction'] = $introduction;
            } else {
                $error = __('Failed to update questionnaire.', 'interactive-questionnaire');
            }
        }
    }
    
    ?>
    <div class="wrap">
        <h1><?php _e('Edit Questionnaire', 'interactive-questionnaire'); ?></h1>
        
        <p>
            <a href="<?php echo admin_url('admin.php?page=interactive-questionnaire'); ?>" class="button">
                <?php _e('Back to All Questionnaires', 'interactive-questionnaire'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=interactive-questionnaire-nodes&id=' . $id); ?>" class="button">
                <?php _e('Manage Nodes', 'interactive-questionnaire'); ?>
            </a>
        </p>
        
        <?php if (isset($error)): ?>
            <div class="notice notice-error is-dismissible">
                <p><?php echo esc_html($error); ?></p>
            </div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="notice notice-success is-dismissible">
                <p><?php echo esc_html($success); ?></p>
            </div>
        <?php endif; ?>
        
        <form method="post" action="">
            <?php wp_nonce_field('iq_edit_questionnaire_' . $id); ?>
            
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">
                        <label for="title"><?php _e('Title', 'interactive-questionnaire'); ?></label>
                    </th>
                    <td>
                        <input name="title" type="text" id="title" value="<?php echo esc_attr($questionnaire['title']); ?>" class="regular-text">
                        <p class="description"><?php _e('The title of your questionnaire.', 'interactive-questionnaire'); ?></p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">
                        <label for="introduction"><?php _e('Introduction', 'interactive-questionnaire'); ?></label>
                    </th>
                    <td>
                        <textarea name="introduction" id="introduction" rows="5" class="large-text"><?php echo esc_textarea($questionnaire['introduction']); ?></textarea>
                        <p class="description"><?php _e('An optional introduction text that will be displayed at the beginning of the questionnaire.', 'interactive-questionnaire'); ?></p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">
                        <?php _e('Shortcode', 'interactive-questionnaire'); ?>
                    </th>
                    <td>
                        <code>[questionnaire id="<?php echo esc_attr($questionnaire['id']); ?>"]</code>
                        <button type="button" class="button button-small copy-shortcode" data-shortcode='[questionnaire id="<?php echo esc_attr($questionnaire['id']); ?>"]'>
                            <?php _e('Copy', 'interactive-questionnaire'); ?>
                        </button>
                        <p class="description"><?php _e('Use this shortcode to display the questionnaire on a page or post.', 'interactive-questionnaire'); ?></p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="iq_edit_questionnaire" class="button button-primary" value="<?php _e('Update Questionnaire', 'interactive-questionnaire'); ?>">
            </p>
        </form>
    </div>
    <?php
}

// Manage nodes page
function iq_manage_nodes_page() {
    global $wpdb;
    
    // Get questionnaire ID
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if (!$id) {
        wp_die(__('Invalid questionnaire ID.', 'interactive-questionnaire'));
    }
    
    // Get questionnaire data
    $questionnaire = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}iq_questionnaires WHERE id = %d",
            $id
        ),
        ARRAY_A
    );
    
    if (!$questionnaire) {
        wp_die(__('Questionnaire not found.', 'interactive-questionnaire'));
    }
    
    // Process node deletion
    if (isset($_GET['action']) && $_GET['action'] === 'delete_node' && isset($_GET['node_id']) && isset($_GET['_wpnonce'])) {
        if (wp_verify_nonce($_GET['_wpnonce'], 'delete_node_' . $_GET['node_id'])) {
            $node_id = intval($_GET['node_id']);
            
            // Check if this is the start node
            $is_start = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT is_start FROM {$wpdb->prefix}iq_nodes WHERE id = %d",
                    $node_id
                )
            );
            
            if ($is_start) {
                $error = __('You cannot delete the start node.', 'interactive-questionnaire');
            } else {
                // Delete the node and its answers
                $wpdb->delete(
                    $wpdb->prefix . 'iq_answers',
                    ['node_id' => $node_id],
                    ['%d']
                );
                
                $wpdb->delete(
                    $wpdb->prefix . 'iq_nodes',
                    ['id' => $node_id],
                    ['%d']
                );
                
                $success = __('Node deleted successfully.', 'interactive-questionnaire');
            }
        }
    }
    
    // Process adding a new node
    if (isset($_POST['iq_add_node']) && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'iq_add_node_' . $id)) {
        $node_key = sanitize_key($_POST['node_key']);
        $node_type = sanitize_text_field($_POST['node_type']);
        
        if (empty($node_key)) {
            $error = __('Node key is required.', 'interactive-questionnaire');
        } else {
            // Check if node key already exists
            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}iq_nodes WHERE questionnaire_id = %d AND node_key = %s",
                    $id,
                    $node_key
                )
            );
            
            if ($exists) {
                $error = __('A node with this key already exists.', 'interactive-questionnaire');
            } else {
                // Insert node
                $node_id = iq_insert_node([
                    'questionnaire_id' => $id,
                    'node_key' => $node_key,
                    'is_start' => 0,
                    'node_type' => $node_type,
                    'question' => $node_type === 'question' ? __('New question', 'interactive-questionnaire') : '',
                    'recommendation' => $node_type === 'recommendation' ? __('New recommendation', 'interactive-questionnaire') : '',
                    'product_slug' => ''
                ]);
                
                if ($node_id) {
                    $success = __('Node added successfully.', 'interactive-questionnaire');
                } else {
                    $error = __('Failed to add node.', 'interactive-questionnaire');
                }
            }
        }
    }
    
    // Get all nodes for this questionnaire
    $nodes = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT n.*, 
            (SELECT COUNT(*) FROM {$wpdb->prefix}iq_answers WHERE node_id = n.id) as answer_count
            FROM {$wpdb->prefix}iq_nodes n
            WHERE n.questionnaire_id = %d
            ORDER BY n.is_start DESC, n.node_key ASC",
            $id
        ),
        ARRAY_A
    );
    
    ?>
    <div class="wrap">
        <h1><?php printf(__('Manage Nodes: %s', 'interactive-questionnaire'), esc_html($questionnaire['title'])); ?></h1>
        
        <p>
            <a href="<?php echo admin_url('admin.php?page=interactive-questionnaire'); ?>" class="button">
                <?php _e('Back to All Questionnaires', 'interactive-questionnaire'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=interactive-questionnaire-edit&id=' . $id); ?>" class="button">
                <?php _e('Edit Questionnaire', 'interactive-questionnaire'); ?>
            </a>
        </p>
        
        <?php if (isset($error)): ?>
            <div class="notice notice-error is-dismissible">
                <p><?php echo esc_html($error); ?></p>
            </div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="notice notice-success is-dismissible">
                <p><?php echo esc_html($success); ?></p>
            </div>
        <?php endif; ?>
        
        <!-- Add New Node Form -->
        <div class="iq-add-node-form">
            <h2><?php _e('Add New Node', 'interactive-questionnaire'); ?></h2>
            
            <form method="post" action="">
                <?php wp_nonce_field('iq_add_node_' . $id); ?>
                
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">
                            <label for="node_key"><?php _e('Node Key', 'interactive-questionnaire'); ?></label>
                        </th>
                        <td>
                            <input name="node_key" type="text" id="node_key" value="" class="regular-text">
                            <p class="description"><?php _e('A unique identifier for this node (e.g., "question1", "sport_recommendation").', 'interactive-questionnaire'); ?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">
                            <label for="node_type"><?php _e('Node Type', 'interactive-questionnaire'); ?></label>
                        </th>
                        <td>
                            <select name="node_type" id="node_type">
                                <option value="question"><?php _e('Question', 'interactive-questionnaire'); ?></option>
                                <option value="recommendation"><?php _e('Recommendation', 'interactive-questionnaire'); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="iq_add_node" class="button button-primary" value="<?php _e('Add Node', 'interactive-questionnaire'); ?>">
                </p>
            </form>
        </div>
        
        <!-- Nodes Table -->
        <h2><?php _e('Nodes', 'interactive-questionnaire'); ?></h2>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col" class="manage-column column-id"><?php _e('ID', 'interactive-questionnaire'); ?></th>
                    <th scope="col" class="manage-column column-key"><?php _e('Key', 'interactive-questionnaire'); ?></th>
                    <th scope="col" class="manage-column column-type"><?php _e('Type', 'interactive-questionnaire'); ?></th>
                    <th scope="col" class="manage-column column-content column-primary"><?php _e('Content', 'interactive-questionnaire'); ?></th>
                    <th scope="col" class="manage-column column-answers"><?php _e('Answers', 'interactive-questionnaire'); ?></th>
                    <th scope="col" class="manage-column column-actions"><?php _e('Actions', 'interactive-questionnaire'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($nodes)): ?>
                    <tr>
                        <td colspan="6"><?php _e('No nodes found.', 'interactive-questionnaire'); ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($nodes as $node): ?>
                        <tr>
                            <td><?php echo esc_html($node['id']); ?></td>
                            <td>
                                <?php echo esc_html($node['node_key']); ?>
                                <?php if ($node['is_start']): ?>
                                    <span class="iq-start-badge"><?php _e('Start', 'interactive-questionnaire'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($node['node_type']); ?></td>
                            <td class="column-primary">
                                <?php if ($node['node_type'] === 'question'): ?>
                                    <strong><?php echo esc_html(wp_trim_words($node['question'], 10)); ?></strong>
                                <?php else: ?>
                                    <strong><?php echo esc_html(wp_trim_words($node['recommendation'], 10)); ?></strong>
                                    <?php if (!empty($node['product_slug'])): ?>
                                        <div><small><?php printf(__('Product: %s', 'interactive-questionnaire'), esc_html($node['product_slug'])); ?></small></div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <div class="row-actions">
                                    <span class="edit">
                                        <a href="<?php echo admin_url('admin.php?page=interactive-questionnaire-edit-node&questionnaire_id=' . $id . '&node_id=' . $node['id']); ?>">
                                            <?php _e('Edit', 'interactive-questionnaire'); ?>
                                        </a>
                                        <?php if (!$node['is_start']): ?>
                                            | <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=interactive-questionnaire-nodes&id=' . $id . '&action=delete_node&node_id=' . $node['id']), 'delete_node_' . $node['id']); ?>" class="submitdelete" onclick="return confirm('<?php _e('Are you sure you want to delete this node? This action cannot be undone.', 'interactive-questionnaire'); ?>')">
                                                <?php _e('Delete', 'interactive-questionnaire'); ?>
                                            </a>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <?php if ($node['node_type'] === 'question'): ?>
                                    <?php echo intval($node['answer_count']); ?>
                                <?php else: ?>
                                    &mdash;
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=interactive-questionnaire-edit-node&questionnaire_id=' . $id . '&node_id=' . $node['id']); ?>" class="button button-small">
                                    <?php _e('Edit', 'interactive-questionnaire'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

// Edit node page
function iq_edit_node_page() {
    global $wpdb;
    
    // Get questionnaire ID and node ID
    $questionnaire_id = isset($_GET['questionnaire_id']) ? intval($_GET['questionnaire_id']) : 0;
    $node_id = isset($_GET['node_id']) ? intval($_GET['node_id']) : 0;
    
    if (!$questionnaire_id || !$node_id) {
        wp_die(__('Invalid parameters.', 'interactive-questionnaire'));
    }
    
    // Get questionnaire data
    $questionnaire = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}iq_questionnaires WHERE id = %d",
            $questionnaire_id
        ),
        ARRAY_A
    );
    
    if (!$questionnaire) {
        wp_die(__('Questionnaire not found.', 'interactive-questionnaire'));
    }
    
    // Get node data
    $node = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}iq_nodes WHERE id = %d AND questionnaire_id = %d",
            $node_id,
            $questionnaire_id
        ),
        ARRAY_A
    );
    
    if (!$node) {
        wp_die(__('Node not found.', 'interactive-questionnaire'));
    }
    
    // Handle form submission
    if (isset($_POST['iq_edit_node']) && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'iq_edit_node_' . $node_id)) {
        $node_key = sanitize_key($_POST['node_key']);
        $node_type = sanitize_text_field($_POST['node_type']);
        $question = sanitize_text_field($_POST['question']);
        $recommendation = sanitize_textarea_field($_POST['recommendation']);
        $product_slug = sanitize_text_field($_POST['product_slug']);
        
        if (empty($node_key)) {
            $error = __('Node key is required.', 'interactive-questionnaire');
        } else {
            // Check if node key already exists and is not this node
            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}iq_nodes 
                    WHERE questionnaire_id = %d AND node_key = %s AND id != %d",
                    $questionnaire_id,
                    $node_key,
                    $node_id
                )
            );
            
            if ($exists) {
                $error = __('A node with this key already exists.', 'interactive-questionnaire');
            } else {
                // Update node
                $result = $wpdb->update(
                    $wpdb->prefix . 'iq_nodes',
                    [
                        'node_key' => $node_key,
                        'node_type' => $node_type,
                        'question' => $question,
                        'recommendation' => $recommendation,
                        'product_slug' => $product_slug
                    ],
                    ['id' => $node_id],
                    ['%s', '%s', '%s', '%s', '%s'],
                    ['%d']
                );
                
                if ($result !== false) {
                    // Process answers if this is a question node
                    if ($node_type === 'question' && isset($_POST['answers'])) {
                        // Delete existing answers
                        $wpdb->delete(
                            $wpdb->prefix . 'iq_answers',
                            ['node_id' => $node_id],
                            ['%d']
                        );
                        
                        // Add new answers
                        $answers = $_POST['answers'];
                        foreach ($answers as $index => $answer) {
                            if (!empty($answer['text']) && !empty($answer['next_node_key'])) {
                                iq_insert_answer([
                                    'node_id' => $node_id,
                                    'text' => sanitize_text_field($answer['text']),
                                    'next_node_key' => sanitize_text_field($answer['next_node_key']),
                                    'sort_order' => $index
                                ]);
                            }
                        }
                    }
                    
                    $success = __('Node updated successfully.', 'interactive-questionnaire');
                    
                    // Refresh node data
                    $node['node_key'] = $node_key;
                    $node['node_type'] = $node_type;
                    $node['question'] = $question;
                    $node['recommendation'] = $recommendation;
                    $node['product_slug'] = $product_slug;
                } else {
                    $error = __('Failed to update node.', 'interactive-questionnaire');
                }
            }
        }
    }
    
    // Get all nodes for this questionnaire (for select options)
    $all_nodes = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, node_key FROM {$wpdb->prefix}iq_nodes 
            WHERE questionnaire_id = %d
            ORDER BY is_start DESC, node_key ASC",
            $questionnaire_id
        ),
        ARRAY_A
    );
    
    // Get answers for this node
    $answers = [];
    if ($node['node_type'] === 'question') {
        $answers = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}iq_answers 
                WHERE node_id = %d
                ORDER BY sort_order ASC",
                $node_id
            ),
            ARRAY_A
        );
    }
    
    ?>
    <div class="wrap">
        <h1><?php printf(__('Edit Node: %s', 'interactive-questionnaire'), esc_html($node['node_key'])); ?></h1>
        
        <p>
            <a href="<?php echo admin_url('admin.php?page=interactive-questionnaire-nodes&id=' . $questionnaire_id); ?>" class="button">
                <?php _e('Back to Nodes', 'interactive-questionnaire'); ?>
            </a>
        </p>
        
        <?php if (isset($error)): ?>
            <div class="notice notice-error is-dismissible">
                <p><?php echo esc_html($error); ?></p>
            </div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="notice notice-success is-dismissible">
                <p><?php echo esc_html($success); ?></p>
            </div>
        <?php endif; ?>
        
        <form method="post" action="">
            <?php wp_nonce_field('iq_edit_node_' . $node_id); ?>
            
            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-2">
                    <div id="post-body-content">
                        <div class="postbox">
                            <h2 class="hndle"><?php _e('Node Details', 'interactive-questionnaire'); ?></h2>
                            <div class="inside">
                                <table class="form-table">
                                    <tr valign="top">
                                        <th scope="row">
                                            <label for="node_key"><?php _e('Node Key', 'interactive-questionnaire'); ?></label>
                                        </th>
                                        <td>
                                            <input name="node_key" type="text" id="node_key" value="<?php echo esc_attr($node['node_key']); ?>" class="regular-text" <?php echo $node['is_start'] ? 'readonly' : ''; ?>>
                                            <?php if ($node['is_start']): ?>
                                                <p class="description"><?php _e('The start node key cannot be changed.', 'interactive-questionnaire'); ?></p>
                                            <?php else: ?>
                                                <p class="description"><?php _e('A unique identifier for this node.', 'interactive-questionnaire'); ?></p>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr valign="top">
                                        <th scope="row">
                                            <label for="node_type"><?php _e('Node Type', 'interactive-questionnaire'); ?></label>
                                        </th>
                                        <td>
                                            <select name="node_type" id="node_type" class="iq-node-type-selector">
                                                <option value="question" <?php selected($node['node_type'], 'question'); ?>><?php _e('Question', 'interactive-questionnaire'); ?></option>
                                                <option value="recommendation" <?php selected($node['node_type'], 'recommendation'); ?>><?php _e('Recommendation', 'interactive-questionnaire'); ?></option>
                                            </select>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <div class="postbox iq-question-section" <?php echo $node['node_type'] !== 'question' ? 'style="display:none;"' : ''; ?>>
                            <h2 class="hndle"><?php _e('Question Content', 'interactive-questionnaire'); ?></h2>
                            <div class="inside">
                                <table class="form-table">
                                    <tr valign="top">
                                        <th scope="row">
                                            <label for="question"><?php _e('Question', 'interactive-questionnaire'); ?></label>
                                        </th>
                                        <td>
                                            <input name="question" type="text" id="question" value="<?php echo esc_attr($node['question']); ?>" class="large-text">
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <div class="postbox iq-recommendation-section" <?php echo $node['node_type'] !== 'recommendation' ? 'style="display:none;"' : ''; ?>>
                            <h2 class="hndle"><?php _e('Recommendation Content', 'interactive-questionnaire'); ?></h2>
                            <div class="inside">
                                <table class="form-table">
                                    <tr valign="top">
                                        <th scope="row">
                                            <label for="recommendation"><?php _e('Recommendation', 'interactive-questionnaire'); ?></label>
                                        </th>
                                        <td>
                                            <textarea name="recommendation" id="recommendation" rows="5" class="large-text"><?php echo esc_textarea($node['recommendation']); ?></textarea>
                                        </td>
                                    </tr>
                                    <tr valign="top">
                                        <th scope="row">
                                            <label for="product_slug"><?php _e('Product Slug', 'interactive-questionnaire'); ?></label>
                                        </th>
                                        <td>
                                            <input name="product_slug" type="text" id="product_slug" value="<?php echo esc_attr($node['product_slug']); ?>" class="regular-text">
                                            <p class="description"><?php _e('Enter the slug of the WooCommerce product to recommend.', 'interactive-questionnaire'); ?></p>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <div class="postbox iq-answers-section" <?php echo $node['node_type'] !== 'question' ? 'style="display:none;"' : ''; ?>>
                            <h2 class="hndle"><?php _e('Answers', 'interactive-questionnaire'); ?></h2>
                            <div class="inside">
                                <div id="iq-answers-container">
                                    <?php if (empty($answers)): ?>
                                        <div class="iq-answer-item">
                                            <h4><?php _e('Answer 1', 'interactive-questionnaire'); ?></h4>
                                            <table class="form-table">
                                                <tr valign="top">
                                                    <th scope="row">
                                                        <label for="answers[0][text]"><?php _e('Text', 'interactive-questionnaire'); ?></label>
                                                    </th>
                                                    <td>
                                                        <input name="answers[0][text]" type="text" value="" class="large-text">
                                                    </td>
                                                </tr>
                                                <tr valign="top">
                                                    <th scope="row">
                                                        <label for="answers[0][next_node_key]"><?php _e('Next Node', 'interactive-questionnaire'); ?></label>
                                                    </th>
                                                    <td>
                                                        <select name="answers[0][next_node_key]" class="regular-text">
                                                            <option value=""><?php _e('Select Next Node', 'interactive-questionnaire'); ?></option>
                                                            <?php foreach ($all_nodes as $n): ?>
                                                                <option value="<?php echo esc_attr($n['node_key']); ?>"><?php echo esc_html($n['node_key']); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </td>
                                                </tr>
                                            </table>
                                            <button type="button" class="button iq-remove-answer"><?php _e('Remove Answer', 'interactive-questionnaire'); ?></button>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($answers as $index => $answer): ?>
                                            <div class="iq-answer-item">
                                                <h4><?php printf(__('Answer %d', 'interactive-questionnaire'), $index + 1); ?></h4>
                                                <table class="form-table">
                                                    <tr valign="top">
                                                        <th scope="row">
                                                            <label for="answers[<?php echo $index; ?>][text]"><?php _e('Text', 'interactive-questionnaire'); ?></label>
                                                        </th>
                                                        <td>
                                                            <input name="answers[<?php echo $index; ?>][text]" type="text" value="<?php echo esc_attr($answer['text']); ?>" class="large-text">
                                                        </td>
                                                    </tr>
                                                    <tr valign="top">
                                                        <th scope="row">
                                                            <label for="answers[<?php echo $index; ?>][next_node_key]"><?php _e('Next Node', 'interactive-questionnaire'); ?></label>
                                                        </th>
                                                        <td>
                                                            <select name="answers[<?php echo $index; ?>][next_node_key]" class="regular-text">
                                                                <option value=""><?php _e('Select Next Node', 'interactive-questionnaire'); ?></option>
                                                                <?php foreach ($all_nodes as $n): ?>
                                                                    <option value="<?php echo esc_attr($n['node_key']); ?>" <?php selected($answer['next_node_key'], $n['node_key']); ?>><?php echo esc_html($n['node_key']); ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </td>
                                                    </tr>
                                                </table>
                                                <button type="button" class="button iq-remove-answer"><?php _e('Remove Answer', 'interactive-questionnaire'); ?></button>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                
                                <p>
                                    <button type="button" class="button iq-add-answer"><?php _e('Add Answer', 'interactive-questionnaire'); ?></button>
                                </p>
                                
                                <div class="iq-answer-template" style="display:none;">
                                    <div class="iq-answer-item">
                                        <h4><?php _e('New Answer', 'interactive-questionnaire'); ?></h4>
                                        <table class="form-table">
                                            <tr valign="top">
                                                <th scope="row">
                                                    <label><?php _e('Text', 'interactive-questionnaire'); ?></label>
                                                </th>
                                                <td>
                                                    <input name="answers[INDEX][text]" type="text" value="" class="large-text">
                                                </td>
                                            </tr>
                                            <tr valign="top">
                                                <th scope="row">
                                                    <label><?php _e('Next Node', 'interactive-questionnaire'); ?></label>
                                                </th>
                                                <td>
                                                    <select name="answers[INDEX][next_node_key]" class="regular-text">
                                                        <option value=""><?php _e('Select Next Node', 'interactive-questionnaire'); ?></option>
                                                        <?php foreach ($all_nodes as $n): ?>
                                                            <option value="<?php echo esc_attr($n['node_key']); ?>"><?php echo esc_html($n['node_key']); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </td>
                                            </tr>
                                        </table>
                                        <button type="button" class="button iq-remove-answer"><?php _e('Remove Answer', 'interactive-questionnaire'); ?></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <p class="submit">
                <input type="submit" name="iq_edit_node" class="button button-primary" value="<?php _e('Update Node', 'interactive-questionnaire'); ?>">
            </p>
        </form>
    </div>
    <script>
    jQuery(document).ready(function($) {
        // Handle node type change
        $('.iq-node-type-selector').on('change', function() {
            var nodeType = $(this).val();
            
            if (nodeType === 'question') {
                $('.iq-question-section, .iq-answers-section').show();
                $('.iq-recommendation-section').hide();
            } else {
                $('.iq-question-section, .iq-answers-section').hide();
                $('.iq-recommendation-section').show();
            }
        });
        
        // Add answer
        $('.iq-add-answer').on('click', function() {
            var container = $('#iq-answers-container');
            var template = $('.iq-answer-template').html();
            var count = container.find('.iq-answer-item').length;
            
            // Replace INDEX with the current count
            template = template.replace(/INDEX/g, count);
            
            // Add the new answer item
            container.append(template);
            
            // Update titles
            updateAnswerTitles();
        });
        
        // Remove answer
        $(document).on('click', '.iq-remove-answer', function() {
            $(this).closest('.iq-answer-item').remove();
            
            // Update titles
            updateAnswerTitles();
        });
        
        // Update answer titles
        function updateAnswerTitles() {
            $('.iq-answer-item h4').each(function(index) {
                $(this).text('Answer ' + (index + 1));
            });
            
            // Update input names
            $('.iq-answer-item').each(function(index) {
                $(this).find('input, select').each(function() {
                    var name = $(this).attr('name');
                    if (name) {
                        name = name.replace(/answers\[\d+\]/, 'answers[' + index + ']');
                        $(this).attr('name', name);
                    }
                });
            });
        }
        
        // Copy shortcode button
        $('.copy-shortcode').on('click', function() {
            var shortcode = $(this).data('shortcode');
            
            // Create a temporary textarea element
            var textarea = document.createElement('textarea');
            textarea.value = shortcode;
            document.body.appendChild(textarea);
            
            // Select and copy the text
            textarea.select();
            document.execCommand('copy');
            
            // Remove the temporary element
            document.body.removeChild(textarea);
            
            // Show a message
            $(this).text('Copied!');
            setTimeout(function() {
                $('.copy-shortcode').text('Copy');
            }, 2000);
        });
    });
    </script>
    <style>
    .iq-answer-item {
        background: #f9f9f9;
        border: 1px solid #e5e5e5;
        padding: 10px;
        margin-bottom: 15px;
    }
    
    .iq-answer-item h4 {
        margin-top: 0;
    }
    
    .iq-start-badge {
        display: inline-block;
        background: #0073aa;
        color: white;
        padding: 2px 5px;
        border-radius: 3px;
        font-size: 11px;
        margin-left: 5px;
    }
    </style>
    <?php
}

// Function to delete a questionnaire
function iq_delete_questionnaire($id) {
    global $wpdb;
    
    // Get all nodes for this questionnaire
    $nodes = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}iq_nodes WHERE questionnaire_id = %d",
            $id
        )
    );
    
    // Delete answers for all nodes
    if (!empty($nodes)) {
        $nodes_placeholders = implode(',', array_fill(0, count($nodes), '%d'));
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}iq_answers WHERE node_id IN ($nodes_placeholders)",
                ...$nodes
            )
        );
    }
    
    // Delete nodes
    $wpdb->delete(
        $wpdb->prefix . 'iq_nodes',
        ['questionnaire_id' => $id],
        ['%d']
    );
    
    // Delete questionnaire
    $wpdb->delete(
        $wpdb->prefix . 'iq_questionnaires',
        ['id' => $id],
        ['%d']
    );
}

// Create admin CSS file
function iq_create_admin_css() {
    $css_file = IQ_PLUGIN_DIR . 'admin/css/admin.css';
    
    if (!file_exists($css_file)) {
        $css_content = <<<CSS
/* Interactive Questionnaire Admin Styles */
.iq-answer-item {
    background: #f9f9f9;
    border: 1px solid #e5e5e5;
    padding: 10px;
    margin-bottom: 15px;
}

.iq-answer-item h4 {
    margin-top: 0;
}

.iq-start-badge {
    display: inline-block;
    background: #0073aa;
    color: white;
    padding: 2px 5px;
    border-radius: 3px;
    font-size: 11px;
    margin-left: 5px;
}
CSS;
        
        file_put_contents($css_file, $css_content);
    }
}
register_activation_hook(IQ_PLUGIN_DIR . 'interactive-questionnaire.php', 'iq_create_admin_css');

// Create admin JS file
function iq_create_admin_js() {
    $js_file = IQ_PLUGIN_DIR . 'admin/js/admin.js';
    
    if (!file_exists($js_file)) {
        $js_content = <<<JS
/* Interactive Questionnaire Admin JS */
jQuery(document).ready(function($) {
    // Handle node type change
    $('.iq-node-type-selector').on('change', function() {
        var nodeType = $(this).val();
        
        if (nodeType === 'question') {
            $('.iq-question-section, .iq-answers-section').show();
            $('.iq-recommendation-section').hide();
        } else {
            $('.iq-question-section, .iq-answers-section').hide();
            $('.iq-recommendation-section').show();
        }
    });
    
    // Add answer
    $('.iq-add-answer').on('click', function() {
        var container = $('#iq-answers-container');
        var template = $('.iq-answer-template').html();
        var count = container.find('.iq-answer-item').length;
        
        // Replace INDEX with the current count
        template = template.replace(/INDEX/g, count);
        
        // Add the new answer item
        container.append(template);
        
        // Update titles
        updateAnswerTitles();
    });
    
    // Remove answer
    $(document).on('click', '.iq-remove-answer', function() {
        $(this).closest('.iq-answer-item').remove();
        
        // Update titles
        updateAnswerTitles();
    });
    
    // Update answer titles
    function updateAnswerTitles() {
        $('.iq-answer-item h4').each(function(index) {
            $(this).text('Answer ' + (index + 1));
        });
        
        // Update input names
        $('.iq-answer-item').each(function(index) {
            $(this).find('input, select').each(function() {
                var name = $(this).attr('name');
                if (name) {
                    name = name.replace(/answers\[\d+\]/, 'answers[' + index + ']');
                    $(this).attr('name', name);
                }
            });
        });
    }
    
    // Copy shortcode button
    $('.copy-shortcode').on('click', function() {
        var shortcode = $(this).data('shortcode');
        
        // Create a temporary textarea element
        var textarea = document.createElement('textarea');
        textarea.value = shortcode;
        document.body.appendChild(textarea);
        
        // Select and copy the text
        textarea.select();
        document.execCommand('copy');
        
        // Remove the temporary element
        document.body.removeChild(textarea);
        
        // Show a message
        $(this).text('Copied!');
        setTimeout(function() {
            $('.copy-shortcode').text('Copy');
        }, 2000);
    });
});
JS;
        
        file_put_contents($js_file, $js_content);
    }
}
register_activation_hook(IQ_PLUGIN_DIR . 'interactive-questionnaire.php', 'iq_create_admin_js');
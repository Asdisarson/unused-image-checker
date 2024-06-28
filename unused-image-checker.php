 <?php
/*
Plugin Name: Unused Image Checker
Description: Checks the media library for images with no relations in WooCommerce, JetEngine, Elementor, and YITH, and displays the count of such images. Allows removal of unused images.
Version: 1.0
Author: Egill Asdisarson
*/

function check_image_usage($image_id) {
    // Check if image is a featured image for any product
    $args = array(
        'post_type'      => 'product',
        'meta_key'       => '_thumbnail_id',
        'meta_value'     => $image_id,
        'fields'         => 'ids',
        'posts_per_page' => 1,
    );
    $query = new WP_Query($args);
    if ($query->have_posts()) {
        return true;
    }
    
    // Check if image is in any product gallery
    $args = array(
        'post_type'      => 'product',
        'meta_query'     => array(
            array(
                'key'     => '_product_image_gallery',
                'value'   => '"' . $image_id . '"',
                'compare' => 'LIKE'
            ),
        ),
        'fields'         => 'ids',
        'posts_per_page' => 1,
    );
    $query = new WP_Query($args);
    if ($query->have_posts()) {
        return true;
    }
    
    // Check if image is attached to any post
    $args = array(
        'post_type'      => 'any',
        'post_status'    => 'any',
        'meta_query'     => array(
            array(
                'key'     => '_wp_attached_file',
                'value'   => get_post_meta($image_id, '_wp_attached_file', true),
                'compare' => 'LIKE'
            ),
        ),
        'fields'         => 'ids',
        'posts_per_page' => 1,
    );
    $query = new WP_Query($args);
    if ($query->have_posts()) {
        return true;
    }
    
    // Check if image is in the post content of any post
    $args = array(
        'post_type'      => 'any',
        'post_status'    => 'any',
        's'              => wp_get_attachment_url($image_id),
        'fields'         => 'ids',
        'posts_per_page' => 1,
    );
    $query = new WP_Query($args);
    if ($query->have_posts()) {
        return true;
    }
    
    // Check JetEngine
    if (class_exists('Jet_Engine')) {
        $args = array(
            'post_type'      => 'any',
            'post_status'    => 'any',
            'meta_query'     => array(
                array(
                    'key'     => '_jet_engine_gallery',
                    'value'   => '"' . $image_id . '"',
                    'compare' => 'LIKE'
                ),
            ),
            'fields'         => 'ids',
            'posts_per_page' => 1,
        );
        $query = new WP_Query($args);
        if ($query->have_posts()) {
            return true;
        }
    }
    
    // Check Elementor
    if (class_exists('Elementor\Plugin')) {
        $args = array(
            'post_type'      => 'elementor_library',
            'post_status'    => 'any',
            's'              => wp_get_attachment_url($image_id),
            'fields'         => 'ids',
            'posts_per_page' => 1,
        );
        $query = new WP_Query($args);
        if ($query->have_posts()) {
            return true;
        }
    }
    
    // Check YITH WooCommerce
    if (defined('YITH_WCWL')) {
        $args = array(
            'post_type'      => 'product',
            'meta_query'     => array(
                array(
                    'key'     => '_yith_wcwl_image',
                    'value'   => '"' . $image_id . '"',
                    'compare' => 'LIKE'
                ),
            ),
            'fields'         => 'ids',
            'posts_per_page' => 1,
        );
        $query = new WP_Query($args);
        if ($query->have_posts()) {
            return true;
        }
    }
    
    return false;
}

function get_unused_images() {
    $args = array(
        'post_type'      => 'attachment',
        'post_mime_type' => 'image',
        'post_status'    => 'inherit',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    );
    $query = new WP_Query($args);
    $image_ids = $query->posts;
    
    $unused_images = array();
    
    foreach ($image_ids as $image_id) {
        $usage = check_image_usage($image_id);
        
        // Fail safe double check
        if (!$usage) {
            $usage = check_image_usage($image_id);
        }
        
        // Triple check
        if (!$usage) {
            $usage = check_image_usage($image_id);
        }
        
        if (!$usage) {
            $unused_images[] = $image_id;
        }
    }
    
    return $unused_images;
}

function display_unused_images_count_notice() {
    $unused_images = get_unused_images();
    $unused_images_count = count($unused_images);
    echo '<div class="notice notice-success is-dismissible"><p>Number of unused images in the media library: ' . esc_html($unused_images_count) . '.</p></div>';
}
add_action('admin_notices', 'display_unused_images_count_notice');

// Add submenu under Media for checking unused images
function unused_images_checker_menu() {
    add_media_page(
        'Unused Images Checker',
        'Unused Images Checker',
        'manage_options',
        'unused-images-checker',
        'unused_images_checker_page'
    );
}
add_action('admin_menu', 'unused_images_checker_menu');

// Display the checker page
function unused_images_checker_page() {
    if (isset($_POST['check_unused_images'])) {
        $unused_images = get_unused_images();
        $unused_images_count = count($unused_images);
        
        if (!empty($_POST['delete_unused_images'])) {
            foreach ($unused_images as $image_id) {
                wp_delete_attachment($image_id, true);
            }
            echo '<div class="notice notice-success is-dismissible"><p>Deleted ' . esc_html($unused_images_count) . ' unused images from the media library.</p></div>';
        } else {
            echo '<div class="notice notice-success is-dismissible"><p>Number of unused images in the media library: ' . esc_html($unused_images_count) . '.</p></div>';
        }
    }
    ?>
    <div class="wrap">
        <h1>Unused Images Checker</h1>
        <form method="post" action="">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Delete Unused Images</th>
                    <td><input type="checkbox" name="delete_unused_images" value="1"> Delete unused images</td>
                </tr>
            </table>
            <?php submit_button('Check Unused Images', 'primary', 'check_unused_images'); ?>
        </form>
    </div>
    <?php
}

<?php
/**
 * Plugin Name:       All Export
 * Description:       Export all WordPress core data like Users, Posts, Pages, Categories, and Taxonomies.
 * Version:           1.0.0
 * Author:            Rahul Singh
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-all-export
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Add the admin menu page
function aex_add_admin_menu() {
    add_menu_page(
        'All Export',
        'All Export',
        'manage_options',
        'all-export',
        'aex_admin_page_html',
        'dashicons-download'
    );
}
add_action( 'admin_menu', 'aex_add_admin_menu' );

// Admin page HTML
function aex_admin_page_html() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <p>Select the data you want to export and click the "Export" button.</p>

        <form method="post" action="">
            <?php wp_nonce_field( 'aex-export-action', 'aex_export_nonce' ); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Export Type</th>
                    <td>
                        <select name="aex_export_action" required>
                            <option value="">-- Select Export Type --</option>
                            <option value="export_users">Users (CSV)</option>
                            <option value="export_posts">Posts (CSV)</option>
                            <option value="export_pages">Pages (CSV)</option>
                            <option value="export_categories">Categories (CSV)</option>
                            <option value="export_taxonomies">All Taxonomies (CSV)</option>
                            <option value="export_media">All Media Files (ZIP)</option>
                            <option value="export_all_json">Export All (JSON)</option>
                        </select>
                    </td>
                </tr>
            </table>
            <?php submit_button('Export Data'); ?>
        </form>
    </div>
    <?php
}

function aex_handle_export_actions() {
    if ( isset( $_POST['aex_export_action'] ) ) {
        // Security checks
        if ( ! wp_verify_nonce( $_POST['aex_export_nonce'], 'aex-export-action' ) ) {
            wp_die( 'Security check failed.' );
        }
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'You do not have sufficient permissions to perform this action.' );
        }

        $action = sanitize_text_field( $_POST['aex_export_action'] );

        switch ( $action ) {
            case 'export_users':
                aex_export_users();
                break;
            case 'export_posts':
                aex_export_posts();
                break;
            case 'export_pages':
                aex_export_pages();
                break;
            case 'export_categories':
                aex_export_categories();
                break;
            case 'export_taxonomies':
                aex_export_taxonomies();
                break;
            case 'export_media':
                aex_export_media();
                break;
            case 'export_all_json':
                aex_export_all_json();
                break;
        }
    }
}
add_action( 'admin_init', 'aex_handle_export_actions' );

// Generic CSV export function following DRY principle
function aex_generic_csv_export( $filename, $headers, $data_callback, $batch_size = 100 ) {
    $filename = sanitize_file_name( $filename );
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

    $output = fopen('php://output', 'w');
    
    // Write headers
    fputcsv($output, $headers);
    
    // Process data in batches
    $offset = 0;
    do {
        $batch_data = call_user_func( $data_callback, $offset, $batch_size );
        
        if ( !empty( $batch_data ) ) {
            foreach ( $batch_data as $row ) {
                fputcsv( $output, $row );
            }
        }
        
        $offset += $batch_size;
        
        // Flush output buffer to avoid memory issues
        if ( ob_get_level() ) {
            ob_flush();
        }
        flush();
        
    } while ( !empty( $batch_data ) && count( $batch_data ) === $batch_size );

    fclose($output);
    exit;
}

function aex_export_users() {
    $filename = 'users-export-' . date('Y-m-d') . '.csv';
    $headers = array('ID', 'Username', 'Email', 'Display Name', 'Password (Hashed)', 'Metadata');
    
    aex_generic_csv_export( $filename, $headers, 'aex_get_users_batch' );
}

// Batch callback for users export
function aex_get_users_batch( $offset, $batch_size ) {
    $users = get_users( array(
        'number' => $batch_size,
        'offset' => $offset,
        'fields' => 'all'
    ) );
    
    if ( empty( $users ) ) {
        return array();
    }
    
    // Preload user meta cache
    $user_ids = wp_list_pluck( $users, 'ID' );
    update_meta_cache( 'user', $user_ids );
    
    $rows = array();
    foreach ( $users as $user ) {
        $rows[] = array(
            $user->ID,
            $user->user_login,
            $user->user_email,
            $user->display_name,
            $user->user_pass,
            json_encode( get_user_meta( $user->ID ) )
        );
    }
    
    return $rows;
}

function aex_export_posts() {
    $filename = 'posts-export-' . date('Y-m-d') . '.csv';
    $headers = array('ID', 'Title', 'Content', 'Excerpt', 'Date', 'Status', 'Permalink', 'Featured Image URL', 'Metadata');
    
    aex_generic_csv_export( $filename, $headers, 'aex_get_posts_batch' );
}

// Batch callback for posts export
function aex_get_posts_batch( $offset, $batch_size ) {
    $page = floor( $offset / $batch_size ) + 1;
    
    $query = new WP_Query( array(
        'post_type' => 'post',
        'post_status' => 'any',
        'posts_per_page' => $batch_size,
        'paged' => $page,
        'no_found_rows' => true,
        'update_post_meta_cache' => false, // We'll do this manually for better control
        'update_post_term_cache' => false
    ) );
    
    if ( !$query->have_posts() ) {
        return array();
    }
    
    $posts = $query->posts;
    
    // Preload post meta cache
    $post_ids = wp_list_pluck( $posts, 'ID' );
    update_meta_cache( 'post', $post_ids );
    
    $rows = array();
    foreach ( $posts as $post ) {
        $rows[] = array(
            $post->ID,
            $post->post_title,
            $post->post_content,
            $post->post_excerpt,
            $post->post_date,
            $post->post_status,
            get_permalink( $post->ID ),
            get_the_post_thumbnail_url( $post->ID, 'full' ),
            json_encode( get_post_meta( $post->ID ) )
        );
    }
    
    wp_reset_postdata();
    return $rows;
}

function aex_export_pages() {
    $filename = 'pages-export-' . date('Y-m-d') . '.csv';
    $headers = array('ID', 'Title', 'Content', 'Excerpt', 'Date', 'Status', 'Permalink', 'Featured Image URL', 'Metadata');
    
    aex_generic_csv_export( $filename, $headers, 'aex_get_pages_batch' );
}

// Batch callback for pages export
function aex_get_pages_batch( $offset, $batch_size ) {
    $page = floor( $offset / $batch_size ) + 1;
    
    $query = new WP_Query( array(
        'post_type' => 'page',
        'post_status' => 'any',
        'posts_per_page' => $batch_size,
        'paged' => $page,
        'no_found_rows' => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false
    ) );
    
    if ( !$query->have_posts() ) {
        return array();
    }
    
    $pages = $query->posts;
    
    // Preload post meta cache
    $post_ids = wp_list_pluck( $pages, 'ID' );
    update_meta_cache( 'post', $post_ids );
    
    $rows = array();
    foreach ( $pages as $page ) {
        $rows[] = array(
            $page->ID,
            $page->post_title,
            $page->post_content,
            $page->post_excerpt,
            $page->post_date,
            $page->post_status,
            get_permalink( $page->ID ),
            get_the_post_thumbnail_url( $page->ID, 'full' ),
            json_encode( get_post_meta( $page->ID ) )
        );
    }
    
    wp_reset_postdata();
    return $rows;
}

function aex_export_categories() {
    $filename = 'categories-export-' . date('Y-m-d') . '.csv';
    $headers = array('ID', 'Name', 'Slug', 'Description', 'Count', 'Metadata');
    
    aex_generic_csv_export( $filename, $headers, 'aex_get_categories_batch' );
}

// Batch callback for categories export
function aex_get_categories_batch( $offset, $batch_size ) {
    $categories = get_terms( array(
        'taxonomy' => 'category',
        'hide_empty' => false,
        'number' => $batch_size,
        'offset' => $offset
    ) );
    
    if ( empty( $categories ) || is_wp_error( $categories ) ) {
        return array();
    }
    
    // Preload term meta cache
    $term_ids = wp_list_pluck( $categories, 'term_id' );
    update_meta_cache( 'term', $term_ids );
    
    $rows = array();
    foreach ( $categories as $category ) {
        $rows[] = array(
            $category->term_id,
            $category->name,
            $category->slug,
            $category->description,
            $category->count,
            json_encode( get_term_meta( $category->term_id ) )
        );
    }
    
    return $rows;
}

function aex_export_taxonomies() {
    $filename = 'taxonomies-export-' . date('Y-m-d') . '.csv';
    $headers = array('Taxonomy', 'Term ID', 'Name', 'Slug', 'Description', 'Count', 'Metadata');
    
    aex_generic_csv_export( $filename, $headers, 'aex_get_taxonomies_batch' );
}

// Global variable to track taxonomy export state
$aex_taxonomy_export_state = array(
    'taxonomies' => array(),
    'current_taxonomy_index' => 0,
    'current_taxonomy_offset' => 0
);

// Batch callback for taxonomies export
function aex_get_taxonomies_batch( $offset, $batch_size ) {
    global $aex_taxonomy_export_state;
    
    // Initialize taxonomies on first call
    if ( empty( $aex_taxonomy_export_state['taxonomies'] ) ) {
        $aex_taxonomy_export_state['taxonomies'] = get_taxonomies( array( 'public' => true ), 'objects' );
        $aex_taxonomy_export_state['current_taxonomy_index'] = 0;
        $aex_taxonomy_export_state['current_taxonomy_offset'] = 0;
    }
    
    $taxonomies = $aex_taxonomy_export_state['taxonomies'];
    $current_index = $aex_taxonomy_export_state['current_taxonomy_index'];
    $current_offset = $aex_taxonomy_export_state['current_taxonomy_offset'];
    
    if ( $current_index >= count( $taxonomies ) ) {
        return array(); // No more taxonomies to process
    }
    
    $rows = array();
    $rows_collected = 0;
    
    while ( $rows_collected < $batch_size && $current_index < count( $taxonomies ) ) {
        $taxonomy_names = array_keys( $taxonomies );
        $taxonomy_name = $taxonomy_names[$current_index];
        $taxonomy = $taxonomies[$taxonomy_name];
        
        $terms = get_terms( array(
            'taxonomy' => $taxonomy->name,
            'hide_empty' => false,
            'number' => $batch_size - $rows_collected,
            'offset' => $current_offset
        ) );
        
        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            // Move to next taxonomy
            $current_index++;
            $current_offset = 0;
            continue;
        }
        
        // Preload term meta cache
        $term_ids = wp_list_pluck( $terms, 'term_id' );
        update_meta_cache( 'term', $term_ids );
        
        foreach ( $terms as $term ) {
            $rows[] = array(
                $taxonomy->name,
                $term->term_id,
                $term->name,
                $term->slug,
                $term->description,
                $term->count,
                json_encode( get_term_meta( $term->term_id ) )
            );
            $rows_collected++;
            
            if ( $rows_collected >= $batch_size ) {
                break;
            }
        }
        
        if ( count( $terms ) < ( $batch_size - ( $rows_collected - count( $terms ) ) ) ) {
            // This taxonomy is exhausted, move to next
            $current_index++;
            $current_offset = 0;
        } else {
            // More terms in this taxonomy
            $current_offset += count( $terms );
        }
    }
    
    // Update global state
    $aex_taxonomy_export_state['current_taxonomy_index'] = $current_index;
    $aex_taxonomy_export_state['current_taxonomy_offset'] = $current_offset;
    
    return $rows;
}

function aex_export_media() {
    // Check if ZipArchive class is available
    if ( ! class_exists( 'ZipArchive' ) ) {
        wp_die( 'ZipArchive class is not available. Please enable the ZIP extension in your PHP installation.' );
    }

    // Initialize WordPress Filesystem
    if ( ! function_exists( 'WP_Filesystem' ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    
    WP_Filesystem();
    global $wp_filesystem;
    
    if ( ! $wp_filesystem ) {
        wp_die( 'Cannot initialize WordPress Filesystem.' );
    }

    $upload_dir = wp_upload_dir();
    $uploads_path = $upload_dir['basedir'];
    
    // Check if uploads directory exists
    if ( ! $wp_filesystem->is_dir( $uploads_path ) ) {
        wp_die( 'Uploads directory not found.' );
    }

    $filename = 'all-media-' . date( 'Y-m-d' ) . '.zip';
    
    // Create cache directory if it doesn't exist
    $cache_dir = WP_CONTENT_DIR . '/cache';
    if ( ! $wp_filesystem->is_dir( $cache_dir ) ) {
        $wp_filesystem->mkdir( $cache_dir, 0755 );
    }
    
    $temp_file = $cache_dir . '/' . $filename;

    $zip = new ZipArchive();
    if ( $zip->open( $temp_file, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== TRUE ) {
        wp_die( 'Cannot create zip file.' );
    }

    // Use WordPress Filesystem to recursively add files to zip
    aex_add_files_to_zip_optimized( $zip, $uploads_path, $uploads_path, $wp_filesystem );

    $zip->close();

    // Check if zip file was created successfully
    if ( ! $wp_filesystem->exists( $temp_file ) ) {
        wp_die( 'Failed to create zip file.' );
    }

    // Set headers for download
    header( 'Content-Type: application/zip' );
    header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
    header( 'Content-Length: ' . $wp_filesystem->size( $temp_file ) );
    header( 'Cache-Control: no-cache, must-revalidate' );
    header( 'Expires: Mon, 26 Jul 1997 05:00:00 GMT' );

    // Output the file in chunks to handle large files
    $handle = $wp_filesystem->get_contents( $temp_file );
    echo $handle;

    // Clean up temporary file
    $wp_filesystem->delete( $temp_file );
    exit;
}

// Optimized helper function to recursively add files to zip using WordPress Filesystem
function aex_add_files_to_zip_optimized( $zip, $source_path, $base_path, $wp_filesystem ) {
    $files = aex_get_media_files_optimized( $source_path, $wp_filesystem );
    
    foreach ( $files as $file ) {
        $relative_path = substr( $file, strlen( $base_path ) + 1 );
        
        // Skip hidden files and system files
        if ( strpos( $relative_path, '.' ) === 0 || strpos( $relative_path, '__MACOSX' ) !== false ) {
            continue;
        }
        
        // Check if we should include this file (optimized image selection)
        if ( aex_should_include_file( $relative_path ) ) {
            $zip->addFile( $file, $relative_path );
        }
    }
}

// Helper function to get media files in batches to avoid memory issues
function aex_get_media_files_optimized( $source_path, $wp_filesystem ) {
    $all_files = array();
    $image_groups = array();
    
    $files = $wp_filesystem->dirlist( $source_path, true, true );
    
    if ( ! $files ) {
        return array();
    }
    
    aex_process_directory_recursive( $source_path, $files, $all_files, $image_groups );
    
    // Select preferred images
    foreach ( $image_groups as $group ) {
        $preferred_file = aex_select_preferred_image( $group );
        if ( $preferred_file ) {
            $all_files[] = $preferred_file['path'];
        }
    }
    
    return array_unique( $all_files );
}

// Recursive helper to process directory structure
function aex_process_directory_recursive( $current_path, $files, &$all_files, &$image_groups ) {
    foreach ( $files as $name => $file_info ) {
        $full_path = $current_path . '/' . $name;
        
        if ( $file_info['type'] === 'd' && isset( $file_info['files'] ) ) {
            // Directory - recurse
            aex_process_directory_recursive( $full_path, $file_info['files'], $all_files, $image_groups );
        } else {
            // File - process
            $file_info_parsed = pathinfo( $full_path );
            $extension = strtolower( $file_info_parsed['extension'] ?? '' );
            $filename = $file_info_parsed['filename'] ?? '';
            
            $image_extensions = array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff', 'svg' );
            
            if ( in_array( $extension, $image_extensions ) ) {
                // Group images
                $base_name = aex_get_image_base_name( $filename );
                $group_key = $file_info_parsed['dirname'] . '/' . $base_name . '.' . $extension;
                
                if ( ! isset( $image_groups[$group_key] ) ) {
                    $image_groups[$group_key] = array();
                }
                
                $image_groups[$group_key][] = array(
                    'path' => $full_path,
                    'relative_path' => substr( $full_path, strlen( $current_path ) + 1 ),
                    'filename' => $filename,
                    'is_original' => ! preg_match( '/-(\d+)x(\d+)$/', $filename ),
                    'is_768x512' => preg_match( '/-768x512$/', $filename )
                );
            } else {
                // Non-image file
                $all_files[] = $full_path;
            }
        }
    }
}

// Helper function to recursively add files to zip
function aex_add_files_to_zip( $zip, $source_path, $base_path ) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source_path),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    $all_files = array();
    $image_groups = array();

    // First pass: collect all files and group images
    foreach ($iterator as $file) {
        if (!$file->isDir()) {
            $file_path = $file->getRealPath();
            $relative_path = substr($file_path, strlen($base_path) + 1);
            
            // Skip hidden files and system files
            if (strpos($relative_path, '.') === 0 || strpos($relative_path, '__MACOSX') !== false) {
                continue;
            }
            
            $file_info = pathinfo($relative_path);
            $extension = strtolower($file_info['extension']);
            $filename = $file_info['filename'];
            
            // Common image extensions
            $image_extensions = array('jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff', 'svg');
            
            if (in_array($extension, $image_extensions)) {
                // This is an image file, group it
                $base_name = aex_get_image_base_name($filename);
                $group_key = $file_info['dirname'] . '/' . $base_name . '.' . $extension;
                
                if (!isset($image_groups[$group_key])) {
                    $image_groups[$group_key] = array();
                }
                
                $image_groups[$group_key][] = array(
                    'path' => $file_path,
                    'relative_path' => $relative_path,
                    'filename' => $filename,
                    'is_original' => !preg_match('/-(\d+)x(\d+)$/', $filename),
                    'is_768x512' => preg_match('/-768x512$/', $filename)
                );
            } else {
                // Not an image, add directly
                $all_files[] = array(
                    'path' => $file_path,
                    'relative_path' => $relative_path
                );
            }
        }
    }

    // Second pass: select preferred version for each image group
    foreach ($image_groups as $group) {
        $preferred_file = aex_select_preferred_image($group);
        if ($preferred_file) {
            $all_files[] = $preferred_file;
        }
    }

    // Add all selected files to zip
    foreach ($all_files as $file) {
        $zip->addFile($file['path'], $file['relative_path']);
    }
}

// Helper function to get the base name of an image (without size suffix)
function aex_get_image_base_name($filename) {
    // Remove size suffix like -768x512, -300x200, etc.
    return preg_replace('/-\d+x\d+$/', '', $filename);
}

// Helper function to select the preferred image from a group
function aex_select_preferred_image($image_group) {
    $preferred = null;
    
    foreach ($image_group as $image) {
        if ($image['is_original']) {
            // Found original version, this is our top preference
            return $image;
        } elseif ($image['is_768x512'] && $preferred === null) {
            // 768x512 version, keep as fallback
            $preferred = $image;
        }
    }
    
    // Return the 768x512 if no original was found, or the first file if neither
    return $preferred ?: $image_group[0];
}

// Helper function to determine if a file should be included in the export
function aex_should_include_file($file_path) {
    $file_info = pathinfo($file_path);
    $extension = strtolower($file_info['extension']);
    $filename = $file_info['filename'];
    
    // Common image extensions
    $image_extensions = array('jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff', 'svg');
    
    // If it's not an image file, include it (PDFs, documents, etc.)
    if (!in_array($extension, $image_extensions)) {
        return true;
    }
    
    // For image files, prefer original images without size suffix
    // WordPress image pattern: filename-{width}x{height}.extension
    if (preg_match('/-(\d+)x(\d+)$/', $filename, $matches)) {
        // This is a resized image, only include if no original exists
        // This function is used in the older method, prefer original images
        return false;
    } else {
        // No size suffix found, this is the original image
        return true;
    }
}

function aex_export_all_json() {
    $filename = 'all-export-' . date('Y-m-d') . '.json';
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

    // Start JSON output
    echo "{\n";
    
    $sections_output = 0;
    
    // Export Users
    echo '"users": [';
    aex_stream_users_json();
    echo ']';
    $sections_output++;
    
    // Export Post Types
    if ( $sections_output > 0 ) echo ",\n";
    echo '"post_types": {';
    aex_stream_post_types_json();
    echo '}';
    $sections_output++;
    
    // Export Taxonomies
    if ( $sections_output > 0 ) echo ",\n";
    echo '"taxonomies": {';
    aex_stream_taxonomies_json();
    echo '}';
    $sections_output++;
    
    // Export Navigation Menus
    if ( $sections_output > 0 ) echo ",\n";
    echo '"navigation_menus": {';
    aex_stream_navigation_menus_json();
    echo '}';
    
    // End JSON output
    echo "\n}";
    exit;
}

// Stream users in batches
function aex_stream_users_json() {
    $batch_size = 50;
    $offset = 0;
    $first_user = true;
    
    do {
        $users = get_users( array(
            'number' => $batch_size,
            'offset' => $offset,
            'fields' => 'all'
        ) );
        
        if ( empty( $users ) ) {
            break;
        }
        
        // Preload user meta cache
        $user_ids = wp_list_pluck( $users, 'ID' );
        update_meta_cache( 'user', $user_ids );
        
        foreach ( $users as $user ) {
            if ( ! $first_user ) {
                echo ',';
            }
            $first_user = false;
            
            $userdata = $user->to_array();
            $userdata['user_pass'] = $user->user_pass;
            $userdata['metadata'] = get_user_meta( $user->ID );
            
            echo json_encode( $userdata );
            
            // Flush output to avoid memory buildup
            if ( ob_get_level() ) {
                ob_flush();
            }
            flush();
        }
        
        $offset += $batch_size;
        
    } while ( count( $users ) === $batch_size );
}

// Stream post types in batches
function aex_stream_post_types_json() {
    $post_types = get_post_types( array( 'public' => true ), 'names' );
    $first_post_type = true;
    
    foreach ( $post_types as $post_type ) {
        if ( ! $first_post_type ) {
            echo ',';
        }
        $first_post_type = false;
        
        echo '"' . esc_js( $post_type ) . '": [';
        aex_stream_posts_for_type_json( $post_type );
        echo ']';
        
        // Flush output
        if ( ob_get_level() ) {
            ob_flush();
        }
        flush();
    }
}

// Stream posts for a specific post type
function aex_stream_posts_for_type_json( $post_type ) {
    $batch_size = 50;
    $page = 1;
    $first_post = true;
    
    do {
        $query = new WP_Query( array(
            'post_type' => $post_type,
            'post_status' => 'any',
            'posts_per_page' => $batch_size,
            'paged' => $page,
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false
        ) );
        
        if ( ! $query->have_posts() ) {
            break;
        }
        
        $posts = $query->posts;
        
        // Preload post meta cache
        $post_ids = wp_list_pluck( $posts, 'ID' );
        update_meta_cache( 'post', $post_ids );
        
        foreach ( $posts as $post ) {
            if ( ! $first_post ) {
                echo ',';
            }
            $first_post = false;
            
            $postdata = $post->to_array();
            $postdata['permalink'] = get_permalink( $post->ID );
            $postdata['metadata'] = get_post_meta( $post->ID );
            $postdata['featured_image_url'] = get_the_post_thumbnail_url( $post->ID, 'full' );
            
            $post_taxonomies = get_object_taxonomies( $post, 'objects' );
            $postdata['terms'] = array();
            foreach ( $post_taxonomies as $tax_slug => $taxonomy ) {
                $postdata['terms'][$tax_slug] = wp_get_post_terms( $post->ID, $tax_slug, array( 'fields' => 'all' ) );
            }
            
            echo json_encode( $postdata );
            
            // Flush output
            if ( ob_get_level() ) {
                ob_flush();
            }
            flush();
        }
        
        wp_reset_postdata();
        $page++;
        
    } while ( count( $posts ) === $batch_size );
}

// Stream taxonomies in batches
function aex_stream_taxonomies_json() {
    $taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
    $first_taxonomy = true;
    
    foreach ( $taxonomies as $taxonomy ) {
        if ( ! $first_taxonomy ) {
            echo ',';
        }
        $first_taxonomy = false;
        
        echo '"' . esc_js( $taxonomy->name ) . '": [';
        aex_stream_terms_for_taxonomy_json( $taxonomy->name );
        echo ']';
        
        // Flush output
        if ( ob_get_level() ) {
            ob_flush();
        }
        flush();
    }
}

// Stream terms for a specific taxonomy
function aex_stream_terms_for_taxonomy_json( $taxonomy_name ) {
    $batch_size = 100;
    $offset = 0;
    $first_term = true;
    
    do {
        $terms = get_terms( array(
            'taxonomy' => $taxonomy_name,
            'hide_empty' => false,
            'number' => $batch_size,
            'offset' => $offset
        ) );
        
        if ( empty( $terms ) || is_wp_error( $terms ) ) {
            break;
        }
        
        // Preload term meta cache
        $term_ids = wp_list_pluck( $terms, 'term_id' );
        update_meta_cache( 'term', $term_ids );
        
        foreach ( $terms as $term ) {
            if ( ! $first_term ) {
                echo ',';
            }
            $first_term = false;
            
            $termdata = $term->to_array();
            $termdata['metadata'] = get_term_meta( $term->term_id );
            
            echo json_encode( $termdata );
            
            // Flush output
            if ( ob_get_level() ) {
                ob_flush();
            }
            flush();
        }
        
        $offset += $batch_size;
        
    } while ( count( $terms ) === $batch_size );
}

// Stream navigation menus
function aex_stream_navigation_menus_json() {
    $menus = get_terms( array( 'taxonomy' => 'nav_menu', 'hide_empty' => false ) );
    $first_menu = true;
    
    foreach ( $menus as $menu ) {
        if ( ! $first_menu ) {
            echo ',';
        }
        $first_menu = false;
        
        $menu_items = wp_get_nav_menu_items( $menu->term_id );
        echo '"' . esc_js( $menu->name ) . '": ' . json_encode( $menu_items );
        
        // Flush output
        if ( ob_get_level() ) {
            ob_flush();
        }
        flush();
    }
} 
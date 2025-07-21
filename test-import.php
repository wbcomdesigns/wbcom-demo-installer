<?php
/**
 * Test Import Script
 * 
 * This script tests the database import functionality with zero-padded files
 */

// Load WordPress
require_once( '../../../wp-load.php' );

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Enable WordPress debug
if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', true);
}
if (!defined('WP_DEBUG_LOG')) {
    define('WP_DEBUG_LOG', true);
}
if (!defined('WP_DEBUG_DISPLAY')) {
    define('WP_DEBUG_DISPLAY', true);
}

// Test table name extraction
$test_files = array(
    'posts_0001.json',
    'posts_0002.json',
    'postmeta_0001.json',
    'options_0001.json',
    'terms_0001.json',
    'term_taxonomy_0001.json',
    'term_relationships_0001.json'
);

echo "<h2>Testing Table Name Extraction</h2>";
echo "<pre>";

foreach ($test_files as $file) {
    $table_name = str_replace('.json', '', $file);
    
    // Old method (removes all numbers)
    $old_method = preg_replace('/[0-9]+/', '', $table_name);
    $old_method = preg_replace('/[^a-zA-Z0-9_]/', '', $old_method);
    
    // New method (removes only trailing numbers)
    $new_method = preg_replace('/_\d+$/', '', $table_name);
    
    echo "File: $file\n";
    echo "  Old method: $old_method\n";
    echo "  New method: $new_method\n";
    echo "  With prefix: " . $wpdb->prefix . $new_method . "\n\n";
}

echo "</pre>";

// Test actual import of a small file
$test_url = 'http://reign-demo.local/wp-content/uploads/wbcom-theme-demos/reign/theme_demo/terms_0001.json';

echo "<h2>Testing Actual Import</h2>";
echo "<p>Fetching: $test_url</p>";

$response = wp_remote_get($test_url, array('sslverify' => false, 'timeout' => 120));

if (is_wp_error($response)) {
    echo "<p style='color: red;'>Error fetching file: " . $response->get_error_message() . "</p>";
} else {
    $body = wp_remote_retrieve_body($response);
    if (empty($body)) {
        echo "<p style='color: red;'>Empty response body</p>";
    } else {
        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo "<p style='color: red;'>JSON decode error: " . json_last_error_msg() . "</p>";
            echo "<pre>Response preview:\n" . htmlspecialchars(substr($body, 0, 500)) . "</pre>";
        } else {
            echo "<p style='color: green;'>Successfully decoded JSON</p>";
            echo "<p>Number of records: " . count($data) . "</p>";
            
            // Show first record
            if (!empty($data)) {
                echo "<h3>First Record:</h3>";
                echo "<pre>" . print_r(array_slice($data, 0, 1), true) . "</pre>";
                
                // Check table structure
                global $wpdb;
                $table_name = $wpdb->prefix . 'terms';
                $columns = $wpdb->get_col("DESCRIBE $table_name");
                
                echo "<h3>Table Structure ($table_name):</h3>";
                echo "<pre>" . print_r($columns, true) . "</pre>";
                
                // Check if keys match
                $first_record = reset($data);
                $record_keys = array_keys($first_record);
                $missing_columns = array_diff($record_keys, $columns);
                $extra_columns = array_diff($columns, $record_keys);
                
                if (!empty($missing_columns)) {
                    echo "<p style='color: orange;'>Record has keys not in table: " . implode(', ', $missing_columns) . "</p>";
                }
                if (!empty($extra_columns)) {
                    echo "<p style='color: orange;'>Table has columns not in record: " . implode(', ', $extra_columns) . "</p>";
                }
            }
        }
    }
}

// Check debug log
$debug_log = WP_CONTENT_DIR . '/debug.log';
if (file_exists($debug_log)) {
    echo "<h2>Recent Debug Log Entries</h2>";
    $log_content = file_get_contents($debug_log);
    $lines = explode("\n", $log_content);
    $recent_lines = array_slice($lines, -20);
    echo "<pre>" . htmlspecialchars(implode("\n", $recent_lines)) . "</pre>";
}
?>
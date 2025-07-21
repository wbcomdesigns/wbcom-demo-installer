<?php
/**
 * Clear Import Cache
 * 
 * This script clears the import tracking option that might be preventing re-imports
 */

// Load WordPress
require_once( '../../../wp-load.php' );

// Check if user is admin
if (!current_user_can('manage_options')) {
    die('Access denied');
}

echo "<h1>Clear Import Cache</h1>";

// Get current option value
$import_data = get_option('wbcom_theme_demo_import_data', array());

if (empty($import_data)) {
    echo "<p>No import data found in cache.</p>";
} else {
    echo "<h2>Current Import Cache:</h2>";
    echo "<pre>" . print_r($import_data, true) . "</pre>";
    
    // Clear the option
    if (isset($_GET['clear']) && $_GET['clear'] === 'yes') {
        delete_option('wbcom_theme_demo_import_data');
        echo "<p style='color: green;'><strong>Import cache cleared successfully!</strong></p>";
        echo "<p>You can now try importing again.</p>";
    } else {
        echo "<p><a href='?clear=yes' onclick='return confirm(\"Are you sure you want to clear the import cache?\")' style='background: #d63638; color: white; padding: 10px 20px; text-decoration: none; display: inline-block;'>Clear Import Cache</a></p>";
    }
}

// Also check other related options
echo "<h2>Other Related Options:</h2>";
$req_plugins = get_option('wbcom_theme_demo_req_plugins', array());
echo "<p>Required plugins: " . (empty($req_plugins) ? 'None' : count($req_plugins) . ' plugins') . "</p>";

// Check for any tables with _done suffix
global $wpdb;
$all_options = $wpdb->get_results("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '%_done' OR option_name LIKE 'wbcom_%'");
if (!empty($all_options)) {
    echo "<h3>Related Options in Database:</h3>";
    echo "<ul>";
    foreach ($all_options as $option) {
        echo "<li>" . esc_html($option->option_name) . "</li>";
    }
    echo "</ul>";
}
?>
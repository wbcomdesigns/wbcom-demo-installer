# Wbcom Theme Demo Installer - Developer Documentation

## Table of Contents
- [Architecture Overview](#architecture-overview)
- [Core Components](#core-components)
- [API Reference](#api-reference)
- [Hooks & Filters](#hooks--filters)
- [Adding Custom Demo Packages](#adding-custom-demo-packages)
- [BuddyPress/BuddyBoss Integration](#buddypressbuddyboss-integration)
- [Security Considerations](#security-considerations)
- [Performance Optimization](#performance-optimization)

## Architecture Overview

The Wbcom Theme Demo Installer is a WordPress plugin designed to import demo content for the Reign theme. It supports both BuddyPress and BuddyBoss Platform installations.

### Directory Structure
```
wbcom-demo-installer/
├── assets/
│   ├── css/
│   └── js/
├── core/
│   ├── admin-settings.php
│   ├── ajax-handler.php
│   ├── plugins-manager.php
│   └── prerequisites-checks.php
├── includes/
│   └── class-buddypress-components-enabler.php
├── demo-plugins/
├── docs/
├── i18n/
└── wbcom-theme-demo-installer.php
```

## Core Components

### 1. Main Plugin Class (`WBCOM_Theme_Demo_Installer`)

The main plugin class handles initialization, constants definition, and loading of dependencies.

```php
// Get plugin instance
$demo_installer = WBCOM_Theme_Demo_Installer::instance();
```

### 2. AJAX Handler (`WBCOM_Demo_Importer_Ajax_Handler`)

Handles all AJAX requests for demo import operations:

- `wbcom_get_theme_demo_data` - Imports demo content (posts, database tables, uploads)
- `wbcom_read_theme_demo_package_file` - Reads demo package configuration
- `wbcom_get_demo_plugins_data` - Retrieves required plugins list
- `wbcom_demo_import_finalize` - Performs final cleanup and URL replacement
- `wbcom_enable_buddypress_components` - Enables BP/BB components

### 3. BuddyPress Components Enabler (`WBCOM_BuddyPress_Components_Enabler`)

Automatically detects and enables all available BuddyPress or BuddyBoss components.

```php
// Enable all components
$enabled = WBCOM_BuddyPress_Components_Enabler::enable_all_components();

// Get all available components
$components = WBCOM_BuddyPress_Components_Enabler::get_all_components();
```

## API Reference

### JavaScript API

#### Import Demo Data
```javascript
jQuery.ajax({
    url: ajaxurl,
    type: 'POST',
    data: {
        action: 'wbcom_get_theme_demo_data',
        action_for: 'post_types', // or 'database_tables', 'upload_folders'
        url_to_request: demo_url,
        nonce: wbcom_demo_installer_params.nonce
    },
    success: function(response) {
        // Handle success
    }
});
```

#### Enable BuddyPress Components
```javascript
jQuery.ajax({
    url: ajaxurl,
    type: 'POST',
    data: {
        action: 'wbcom_enable_buddypress_components',
        nonce: wbcom_demo_installer_params.nonce
    },
    success: function(response) {
        if (response.success) {
            console.log('Enabled components:', response.data.components);
        }
    }
});
```

### PHP API

#### Check Platform Type
```php
// Check if BuddyBoss Platform is active
if (defined('BP_PLATFORM_VERSION')) {
    // BuddyBoss specific code
}

// Check if BuddyPress is active
if (function_exists('buddypress')) {
    // BuddyPress code
}
```

## Hooks & Filters

### Actions

#### `wbcom_theme_demo_installer_loaded`
Fired after the plugin is fully loaded.
```php
add_action('wbcom_theme_demo_installer_loaded', function() {
    // Your code here
});
```

#### `wbcom_before_demo_import`
Fired before demo import starts.
```php
add_action('wbcom_before_demo_import', function($demo_data) {
    // Prepare for import
}, 10, 1);
```

#### `wbcom_after_demo_import`
Fired after demo import completes.
```php
add_action('wbcom_after_demo_import', function($demo_data) {
    // Cleanup after import
}, 10, 1);
```

### Filters

#### `wbcom_demo_installer_capability`
Filter the required capability for demo import.
```php
add_filter('wbcom_demo_installer_capability', function($capability) {
    return 'manage_options'; // Default
});
```

#### `wbcom_demo_exporter_api_key`
Filter the API key for demo exporter access.
```php
add_filter('wbcom_demo_exporter_api_key', function($api_key) {
    return 'your-custom-api-key';
});
```

#### `wbcom_demo_installer_skip_tables`
Filter database tables to skip during import.
```php
add_filter('wbcom_demo_installer_skip_tables', function($tables) {
    $tables[] = 'custom_table';
    return $tables;
});
```

## Adding Custom Demo Packages

### 1. Demo Package Structure

Create a demo package with the following structure:
```
demo-package/
├── demo-config.json
├── post-types/
│   ├── posts.xml
│   ├── pages.xml
│   └── custom-post-type.xml
├── database/
│   ├── options.json
│   ├── theme_mods.json
│   └── bp_*.json
└── uploads/
    └── demo-content.zip
```

### 2. Demo Configuration File

Create `demo-config.json`:
```json
{
    "name": "Demo Name",
    "slug": "demo-slug",
    "preview_url": "https://demo.example.com",
    "required_plugins": [
        {
            "name": "BuddyPress",
            "slug": "buddypress",
            "required": true
        },
        {
            "name": "Elementor",
            "slug": "elementor",
            "required": false
        }
    ],
    "import_steps": [
        "plugins",
        "components",
        "database",
        "posts",
        "uploads"
    ]
}
```

### 3. Register Custom Demo

```php
add_filter('wbcom_demo_installer_demos', function($demos) {
    $demos['custom-demo'] = array(
        'name' => 'Custom Demo',
        'preview_url' => 'https://demo.example.com',
        'package_url' => 'https://example.com/demos/custom-demo/',
        'required_plugins' => array('buddypress', 'elementor')
    );
    return $demos;
});
```

## BuddyPress/BuddyBoss Integration

### Component Detection and Enabling

The plugin automatically detects whether BuddyPress or BuddyBoss Platform is active and enables appropriate components:

#### BuddyPress Components
- Core
- Members
- Extended Profiles
- Settings
- Friends
- Messages
- Activity
- Notifications
- Groups
- Blogs (multisite only)

#### BuddyBoss Platform Components
- Members
- Profile Fields
- Settings
- Notifications
- Groups
- Forums
- Activity Feeds
- Media Uploading
- Document Uploading
- Video Uploading
- Private Messaging
- Member Connections
- Invites
- Search
- Moderation
- Blogs (multisite only)

### Handling Component-Specific Data

```php
// Check if specific component is active
if (bp_is_active('groups')) {
    // Import groups data
}

// Handle BuddyBoss-specific features
if (defined('BP_PLATFORM_VERSION')) {
    if (bp_is_active('media')) {
        // Import media data
    }
}
```

## Security Considerations

### 1. Nonce Verification
All AJAX requests require nonce verification:
```php
if (!wp_verify_nonce($_POST['nonce'], 'wbcom_demo_installer_nonce')) {
    wp_die('Security check failed');
}
```

### 2. Capability Checks
Ensure user has proper permissions:
```php
if (!current_user_can('manage_options')) {
    wp_die('Insufficient permissions');
}
```

### 3. Data Sanitization
Always sanitize input data:
```php
$demo_slug = sanitize_text_field($_POST['demo_slug']);
$url = esc_url_raw($_POST['url']);
```

### 4. SQL Injection Prevention
Use prepared statements for database queries:
```php
$wpdb->prepare("SELECT * FROM {$wpdb->prefix}%s WHERE id = %d", $table_name, $id);
```

## Performance Optimization

### 1. Batch Processing
Import large datasets in batches to avoid timeouts:
```php
$batch_size = 100;
$offset = 0;

while ($data = get_batch_data($offset, $batch_size)) {
    process_data($data);
    $offset += $batch_size;
}
```

### 2. Memory Management
Clear caches and free memory during import:
```php
wp_cache_flush();
if (function_exists('gc_collect_cycles')) {
    gc_collect_cycles();
}
```

### 3. Timeout Prevention
Set appropriate timeout limits:
```php
set_time_limit(300); // 5 minutes
ini_set('max_execution_time', 300);
```

### 4. Progress Tracking
Store import progress in options:
```php
update_option('wbcom_import_progress', array(
    'step' => 'database',
    'completed' => 50,
    'total' => 100
));
```

## Error Handling

### 1. Try-Catch Blocks
Wrap critical operations in try-catch blocks:
```php
try {
    $result = import_database_table($table_name, $data);
} catch (Exception $e) {
    error_log('Import failed: ' . $e->getMessage());
    wp_send_json_error(array(
        'message' => 'Import failed',
        'error' => $e->getMessage()
    ));
}
```

### 2. Transaction Support
Use database transactions for data integrity:
```php
$wpdb->query('START TRANSACTION');
try {
    // Import operations
    $wpdb->query('COMMIT');
} catch (Exception $e) {
    $wpdb->query('ROLLBACK');
    throw $e;
}
```

### 3. Logging
Log important events and errors:
```php
error_log(sprintf(
    'WBCOM Demo Import - %s: %s',
    $operation,
    $message
));
```

## Debugging

Enable debug mode in `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Check logs at: `/wp-content/debug.log`

## Contributing

1. Fork the repository
2. Create a feature branch
3. Commit your changes
4. Push to the branch
5. Create a Pull Request

## Support

For developer support, contact: support@wbcomdesigns.com
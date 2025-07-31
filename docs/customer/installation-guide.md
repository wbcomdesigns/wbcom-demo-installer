# Wbcom Theme Demo Installer - Installation Guide

## Requirements

Before installing the Wbcom Theme Demo Installer, ensure your WordPress site meets these requirements:

### System Requirements
- WordPress 5.0 or higher
- PHP 7.2 or higher
- MySQL 5.6 or higher
- At least 256MB PHP memory limit
- mod_rewrite Apache module enabled
- CURL PHP extension enabled
- ZIP PHP extension enabled

### Theme Requirements
- **Reign Theme** must be installed and activated
- The demo installer is exclusively designed for Reign theme

### Plugin Compatibility
The demo installer works with:
- BuddyPress (latest version)
- BuddyBoss Platform (latest version)
- Elementor
- WooCommerce
- bbPress
- LearnDash

## Installation Steps

### Step 1: Download the Plugin

The Wbcom Theme Demo Installer is included in your Reign theme bundle. You can find it in:
```
reign-theme-bundle/
└── plugins/
    └── wbcom-demo-installer.zip
```

### Step 2: Install via WordPress Admin

1. Log in to your WordPress admin dashboard
2. Navigate to **Plugins → Add New**
3. Click **Upload Plugin** button
4. Click **Choose File** and select `wbcom-demo-installer.zip`
5. Click **Install Now**
6. After installation, click **Activate Plugin**

### Step 3: Verify Installation

After activation, you should see:
- A new menu item **Reign → Demo Installer** in your WordPress admin
- The plugin listed in your Plugins page as "Wbcom Theme Demo Installer"

## Initial Setup

### 1. Check Prerequisites

Navigate to **Reign → Demo Installer** and the plugin will automatically check:
- ✅ Reign theme is active
- ✅ PHP version compatibility
- ✅ Required PHP extensions
- ✅ Memory limit
- ✅ File permissions

### 2. Install Required Plugins

Before importing a demo, you may need to install required plugins:

1. Click on **Prerequisites** tab
2. Review the list of required plugins for your chosen demo
3. Click **Install & Activate** for each required plugin
4. Wait for all plugins to be activated

### 3. Configure BuddyPress/BuddyBoss

If your demo requires BuddyPress or BuddyBoss Platform:

#### For BuddyPress:
1. Install and activate BuddyPress
2. The demo installer will automatically enable all components
3. No manual configuration needed

#### For BuddyBoss Platform:
1. Install and activate BuddyBoss Platform
2. The demo installer will automatically enable all components including:
   - Social Groups
   - Activity Feeds
   - Member Profiles
   - Private Messaging
   - Media/Photos
   - Forums
   - And more...

## Backup Recommendations

⚠️ **Important**: Always backup your site before importing demo content!

### Creating a Backup

We recommend using one of these methods:

1. **Using a Backup Plugin**:
   - UpdraftPlus
   - BackWPup
   - Duplicator

2. **Manual Backup**:
   - Export your database via phpMyAdmin
   - Download your entire WordPress directory via FTP
   - Save your `wp-content/uploads` folder

3. **Hosting Backup**:
   - Many hosting providers offer one-click backup solutions
   - Check your hosting control panel for backup options

## File Permissions

Ensure these directories have proper write permissions (755 or 775):

```
wp-content/
├── uploads/       (Required for media import)
├── plugins/       (Required for plugin installation)
└── themes/        (Required for theme customizations)
```

To check permissions via FTP:
1. Connect to your server via FTP
2. Right-click on the folders
3. Select "File Permissions" or "CHMOD"
4. Set to 755 or 775

## Multisite Installation

For WordPress Multisite installations:

1. Network activate the plugin from **Network Admin → Plugins**
2. Each site in the network can import demos independently
3. Ensure the plugin is activated on the specific site where you want to import the demo

## Troubleshooting Installation Issues

### Plugin Upload Failed

If you see "The uploaded file exceeds the upload_max_filesize directive":
- Contact your hosting provider to increase the upload limit
- Or extract the plugin and upload via FTP to `/wp-content/plugins/`

### Activation Errors

If the plugin won't activate:
1. Check PHP error logs for specific errors
2. Ensure Reign theme is activated first
3. Deactivate all other plugins and try again
4. Contact support with the error message

### Missing Menu Item

If you don't see the Demo Installer menu:
1. Clear your browser cache
2. Log out and log back in
3. Check if you have administrator privileges
4. Ensure the plugin is activated

## Next Steps

After successful installation:
1. Proceed to the [Usage Guide](usage-guide.md) to learn how to import demos
2. Review available demos and their requirements
3. Choose and import your preferred demo

## Support

If you encounter any issues during installation:
- Check our [Troubleshooting Guide](troubleshooting-guide.md)
- Visit our [FAQ](faq.md)
- Contact our support team at support@wbcomdesigns.com
- Visit our support forum at https://wbcomdesigns.com/support/
# Wbcom Theme Demo Installer - Usage Guide

## Getting Started

Once you have successfully installed the Wbcom Theme Demo Installer, follow this guide to import your desired demo content.

## Accessing the Demo Installer

1. Log in to your WordPress admin dashboard
2. Navigate to **Reign → Demo Installer**
3. You will see the demo installer interface with available demos

## Understanding the Interface

### Main Dashboard

The demo installer interface consists of:

- **Demo Grid**: Visual preview of all available demos
- **Filter Options**: Filter demos by category (Business, Community, Learning, etc.)
- **Search Bar**: Quick search for specific demos
- **Prerequisites Tab**: Check and install required plugins
- **Import History**: View previously imported demos

### Demo Cards

Each demo card displays:
- 📸 **Preview Screenshot**: Visual preview of the demo
- 🏷️ **Demo Name**: Title of the demo
- 🔗 **Live Preview**: Link to view the live demo
- 📋 **Requirements**: Required plugins for this demo
- 🚀 **Import Button**: Start the import process

## Importing a Demo

### Step 1: Choose Your Demo

1. Browse through available demos
2. Click **Preview** to see the live demo
3. Check the required plugins listed
4. Click **Import Demo** when ready

### Step 2: Prerequisites Check

The installer will automatically check:
- ✅ Required plugins are installed
- ✅ Required plugins are activated
- ✅ Theme compatibility
- ✅ PHP memory limit
- ✅ Server requirements

If any prerequisites are missing:
1. Click on the **Prerequisites** tab
2. Install missing plugins using the **Install & Activate** button
3. Return to the demo selection

### Step 3: Import Options

Select what content to import:

- ☑️ **Content** (Required)
  - Posts, Pages, and Custom Post Types
  - Media files and attachments
  - Menus and navigation

- ☑️ **Customizer Settings**
  - Theme options
  - Colors and typography
  - Layout settings

- ☑️ **Widgets**
  - Sidebar widgets
  - Footer widgets
  - Widget settings

- ☑️ **BuddyPress/BuddyBoss Data**
  - Component settings
  - Sample groups
  - Activity streams
  - Member profiles

- ☑️ **WooCommerce Data** (if applicable)
  - Sample products
  - Shop pages
  - Product categories

### Step 4: Start Import

1. Click **Start Import** button
2. ⚠️ **Warning**: This will modify your site content
3. Confirm by clicking **Yes, Import!**

### Step 5: Import Progress

During import, you'll see:
- Progress bar showing overall completion
- Current step being processed
- Live log of import activities
- Estimated time remaining

**Import Steps**:
1. 🔧 Preparing import
2. 🔌 Enabling BuddyPress/BuddyBoss components
3. 📄 Importing posts and pages
4. 🖼️ Importing media files
5. 🗃️ Importing database content
6. 🎨 Importing theme settings
7. 🔄 Finalizing and cleaning up

**⏱️ Typical Import Times**:
- Small demos: 2-5 minutes
- Medium demos: 5-10 minutes
- Large demos: 10-20 minutes

## Post-Import Steps

### 1. Review Imported Content

After import completes:
1. Visit your site's frontend
2. Check all pages and posts
3. Verify menus are properly set
4. Test forms and functionality

### 2. Configure Permalinks

1. Go to **Settings → Permalinks**
2. Select your preferred structure
3. Click **Save Changes**

### 3. Set Homepage

1. Go to **Settings → Reading**
2. Set **Your homepage displays** to "A static page"
3. Select the imported homepage
4. Choose the blog page if needed
5. Click **Save Changes**

### 4. Configure BuddyPress/BuddyBoss Pages

The installer automatically configures component pages, but verify:
1. Go to **Settings → BuddyPress → Pages** (or BuddyBoss → Pages)
2. Ensure all components have assigned pages
3. Create any missing pages if needed

### 5. Update Site Identity

1. Go to **Appearance → Customize → Site Identity**
2. Update site title and tagline
3. Upload your logo
4. Set site icon (favicon)

## Managing Multiple Demos

### Switching Between Demos

To import a different demo:
1. **Backup your current setup** (recommended)
2. Use a cleanup plugin to remove existing content
3. Import the new demo

### Partial Imports

For existing sites, you can:
1. Import only specific content types
2. Skip existing content
3. Merge with current content

## Customizing After Import

### Theme Customizer

1. Go to **Appearance → Customize**
2. Modify:
   - Colors and typography
   - Header and footer layouts
   - Sidebar positions
   - Widget areas

### Page Builder Editing

If your demo uses Elementor:
1. Edit any page
2. Click **Edit with Elementor**
3. Customize layouts and content
4. Save your changes

### BuddyPress/BuddyBoss Customization

1. **Profile Fields**: Settings → Profile Fields
2. **Group Types**: Settings → Groups
3. **Activity Settings**: Settings → Activity
4. **Member Types**: Users → Member Types

## Best Practices

### Before Importing

1. ✅ Always backup your site
2. ✅ Use a staging site for testing
3. ✅ Check PHP memory limit (minimum 256MB)
4. ✅ Ensure enough disk space
5. ✅ Disable caching plugins

### During Import

1. ⏸️ Don't close the browser window
2. ⏸️ Don't navigate away from the page
3. ⏸️ Be patient with large imports
4. ⏸️ Check browser console for errors

### After Import

1. 🔄 Clear all caches
2. 🔄 Regenerate thumbnails if needed
3. 🔄 Update URLs if migrating
4. 🔄 Test all functionality
5. 🔄 Set up backups

## Advanced Options

### Command Line Import

For developers, use WP-CLI:
```bash
wp wbcom-demo import <demo-slug> --all
```

### Selective Import

Import specific content types:
```bash
wp wbcom-demo import <demo-slug> --posts --pages --media
```

### Skip Existing Content

Avoid duplicates:
```bash
wp wbcom-demo import <demo-slug> --skip-existing
```

## Troubleshooting Common Issues

### Import Stuck or Failed

1. Check PHP error logs
2. Increase PHP memory limit
3. Increase max execution time
4. Try importing in smaller chunks

### Missing Images

1. Check file permissions on uploads folder
2. Verify source demo site is accessible
3. Manually upload missing images

### Broken Layouts

1. Ensure all required plugins are active
2. Clear browser cache
3. Regenerate CSS in Elementor
4. Check for JavaScript errors

## Getting Help

If you need assistance:
1. Check our [FAQ](faq.md)
2. Review [Troubleshooting Guide](troubleshooting-guide.md)
3. Contact support at support@wbcomdesigns.com
4. Visit our forum at https://wbcomdesigns.com/support/

## Video Tutorials

Watch our video guides:
1. [Installing the Demo Installer](https://wbcomdesigns.com/videos/demo-installer-setup)
2. [Importing Your First Demo](https://wbcomdesigns.com/videos/first-demo-import)
3. [Customizing After Import](https://wbcomdesigns.com/videos/post-import-customization)
4. [Troubleshooting Common Issues](https://wbcomdesigns.com/videos/demo-troubleshooting)
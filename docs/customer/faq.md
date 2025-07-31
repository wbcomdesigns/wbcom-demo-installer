# Wbcom Theme Demo Installer - Frequently Asked Questions

## General Questions

### Q: What is the Wbcom Theme Demo Installer?

**A:** The Wbcom Theme Demo Installer is a premium plugin included with the Reign theme that allows you to import professionally designed demo content with just one click. It imports pages, posts, images, theme settings, and plugin configurations to help you quickly set up your website.

### Q: Is the demo installer free?

**A:** The demo installer is included free with your Reign theme purchase. It's not sold separately and is exclusively available for Reign theme users.

### Q: Which themes are compatible with this demo installer?

**A:** The demo installer is exclusively designed for the **Reign theme**. It will not work with other themes.

### Q: Can I use the demo installer on multiple sites?

**A:** Yes, if you have a valid Reign theme license, you can use the demo installer on all sites covered by your license. Check your theme license terms for the number of allowed installations.

## Pre-Installation Questions

### Q: What are the minimum requirements?

**A:** 
- WordPress 5.0 or higher
- PHP 7.2 or higher  
- MySQL 5.6 or higher
- 256MB PHP memory limit (512MB recommended)
- Reign theme installed and activated

### Q: Do I need coding knowledge to use the demo installer?

**A:** No coding knowledge is required. The demo installer features a user-friendly interface with one-click import functionality.

### Q: Should I use the demo installer on a live site?

**A:** We recommend using the demo installer on a fresh WordPress installation or a staging site. If you must use it on a live site, always create a complete backup first.

### Q: How much disk space do I need?

**A:** Demo sizes vary, but we recommend having at least 500MB of free disk space. Large demos with many images may require up to 1GB.

## Installation Questions

### Q: Where can I download the demo installer?

**A:** The demo installer is included in your Reign theme package:
- Log in to your Wbcom Designs account
- Go to Downloads
- Download the Reign theme bundle
- Find the installer in the `/plugins/` folder

### Q: Why can't I see the Demo Installer menu after activation?

**A:** 
1. Ensure Reign theme is activated
2. Clear your browser cache
3. Log out and log back in
4. Check if you have administrator privileges
5. Deactivate and reactivate the plugin

### Q: Can I install the demo installer via FTP?

**A:** Yes:
1. Extract the `wbcom-demo-installer.zip` file
2. Upload the extracted folder to `/wp-content/plugins/`
3. Activate the plugin from WordPress admin

## Import Process Questions

### Q: How long does the import process take?

**A:** Import time depends on:
- Demo size and complexity
- Server performance
- Internet connection speed

Typical times:
- Small demos: 2-5 minutes
- Medium demos: 5-10 minutes
- Large demos: 10-20 minutes

### Q: Can I import only specific parts of a demo?

**A:** Yes, you can choose to import:
- Content only (posts, pages)
- Theme settings only
- Widgets only
- BuddyPress/BuddyBoss data only
- Or any combination

### Q: Will importing a demo overwrite my existing content?

**A:** The demo installer adds new content rather than replacing existing content. However:
- Theme settings will be overwritten
- Widget areas will be replaced
- Menus may be replaced
Always backup before importing!

### Q: Can I import multiple demos?

**A:** Yes, but:
- Content from multiple demos will be mixed
- This may create duplicate pages
- Theme settings from the latest import will apply
- We recommend using only one demo per site

### Q: What happens to my existing plugins?

**A:** Your existing plugins remain unchanged. The demo installer will:
- Notify you of required plugins
- Help install missing plugins
- Not remove any existing plugins

## BuddyPress/BuddyBoss Questions

### Q: Does the demo installer work with both BuddyPress and BuddyBoss?

**A:** Yes! The installer automatically detects which platform you're using and imports appropriate content and settings.

### Q: Will all BuddyPress components be activated?

**A:** Yes, the installer automatically activates all available components for your platform before importing demo content.

### Q: Can I import BuddyPress data into BuddyBoss or vice versa?

**A:** The installer handles compatibility between platforms, but some features may not transfer if they're platform-specific (like BuddyBoss's media features).

### Q: Do imported users have passwords?

**A:** Imported demo users have randomized passwords for security. You'll need to reset passwords or create new users for actual use.

## Troubleshooting Questions

### Q: Why is my import stuck or frozen?

**A:** Common causes:
1. PHP timeout - increase `max_execution_time`
2. Memory limit - increase to 256MB or more
3. Browser timeout - keep the window active
4. Server resources - check with hosting

### Q: Why are images not importing?

**A:** Check:
1. File permissions on `/wp-content/uploads/` (should be 755)
2. Available disk space
3. PHP `allow_url_fopen` setting
4. Source demo site accessibility

### Q: The imported site doesn't look like the demo?

**A:** Ensure:
1. All required plugins are activated
2. Permalinks are set correctly (Settings → Permalinks)
3. Homepage is set (Settings → Reading)
4. Caches are cleared
5. Theme is updated to latest version

### Q: Can I undo a demo import?

**A:** There's no automatic undo feature. Options:
1. Restore from your backup
2. Use a plugin like "WP Reset" to start fresh
3. Manually delete imported content

## Post-Import Questions

### Q: How do I change the logo and site title?

**A:** Go to **Appearance → Customize → Site Identity** to update:
- Site title and tagline
- Logo and site icon
- Display settings

### Q: How do I edit imported pages?

**A:** 
- For Elementor pages: Click "Edit with Elementor"
- For Gutenberg pages: Use the block editor
- For classic pages: Use the classic editor

### Q: Can I delete unwanted demo content?

**A:** Yes, you can safely delete any imported:
- Pages you don't need
- Posts and categories
- Media files
- Users (except administrators)

### Q: How do I set up my own menus?

**A:** 
1. Go to **Appearance → Menus**
2. Create a new menu or edit imported ones
3. Assign to menu locations
4. Save changes

## Licensing Questions

### Q: Do I need a separate license for the demo installer?

**A:** No, the demo installer is included with your Reign theme license.

### Q: Can I use imported demo content for commercial purposes?

**A:** Yes, but:
- Replace all placeholder content
- Use your own images and text
- Check licenses for any included third-party assets

### Q: What about images in the demos?

**A:** Demo images are for demonstration only. You should replace them with your own images for production sites.

## Support Questions

### Q: Where can I get help?

**A:** Support options:
1. Documentation at docs.wbcomdesigns.com
2. Support forum at wbcomdesigns.com/support
3. Email support at support@wbcomdesigns.com
4. Video tutorials on our YouTube channel

### Q: Is support included?

**A:** Yes, support is included with your Reign theme license for the duration of your support period.

### Q: What information should I provide when requesting support?

**A:** Include:
- WordPress version
- PHP version
- Error messages
- Steps to reproduce the issue
- Admin access (if needed)

### Q: Do you offer customization services?

**A:** Yes, we offer paid customization services. Contact us at customization@wbcomdesigns.com for a quote.

## Updates Questions

### Q: How do I update the demo installer?

**A:** 
1. Check for updates in **Dashboard → Updates**
2. Or download the latest version from your account
3. Upload and replace the existing plugin

### Q: Will updates affect my imported content?

**A:** No, updating the plugin won't affect your previously imported content or customizations.

### Q: How often are new demos added?

**A:** We regularly add new demos. Check the demo installer interface or our website for the latest additions.

## Advanced Questions

### Q: Can I create my own demo packages?

**A:** This feature is primarily for our internal use, but developers can contact us for documentation on creating custom demo packages.

### Q: Is there a command-line interface?

**A:** Yes, developers can use WP-CLI commands for importing demos. See the developer documentation for details.

### Q: Can I white-label the demo installer?

**A:** The demo installer cannot be white-labeled as it's part of the Reign theme ecosystem.

### Q: Does it work with multisite?

**A:** Yes, the demo installer works with WordPress multisite. Network activate it to use on all sites in the network.

---

**Still have questions?** Contact our support team at support@wbcomdesigns.com or visit our support forum at https://wbcomdesigns.com/support/
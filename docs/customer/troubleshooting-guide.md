# Wbcom Theme Demo Installer - Troubleshooting Guide

## Common Issues and Solutions

### Installation Issues

#### 1. Plugin Won't Upload

**Error**: "The uploaded file exceeds the upload_max_filesize directive"

**Solutions**:
- Contact your hosting provider to increase `upload_max_filesize`
- Add to `.htaccess`:
  ```
  php_value upload_max_filesize 64M
  php_value post_max_size 64M
  ```
- Or upload via FTP to `/wp-content/plugins/` and activate

#### 2. Plugin Activation Fails

**Error**: "Plugin could not be activated because it triggered a fatal error"

**Solutions**:
1. Check PHP version (requires 7.2+)
2. Ensure Reign theme is activated first
3. Check error logs for specific issues
4. Disable all other plugins and retry
5. Increase PHP memory limit

#### 3. Missing Menu Item After Activation

**Issue**: Demo Installer menu doesn't appear

**Solutions**:
- Clear browser cache (Ctrl+F5)
- Log out and log back in
- Check user role has administrator privileges
- Deactivate and reactivate the plugin
- Check for JavaScript errors in browser console

### Import Process Issues

#### 1. Import Stuck at 0%

**Issue**: Progress bar doesn't move

**Solutions**:
- Check browser console for errors (F12)
- Increase PHP `max_execution_time`:
  ```php
  set_time_limit(300);
  ```
- Check server error logs
- Try using a different browser
- Disable browser extensions

#### 2. Import Fails Midway

**Error**: "Import failed" or connection timeout

**Solutions**:

1. **Increase PHP Limits** in `wp-config.php`:
   ```php
   define('WP_MEMORY_LIMIT', '256M');
   define('WP_MAX_MEMORY_LIMIT', '512M');
   ```

2. **Increase Server Timeouts** in `.htaccess`:
   ```
   php_value max_execution_time 300
   php_value max_input_time 300
   ```

3. **Check Server Resources**:
   - CPU usage
   - Available RAM
   - Disk space

4. **Try Partial Import**:
   - Import content types separately
   - Skip media files initially

#### 3. 500 Internal Server Error

**Issue**: Server error during import

**Solutions**:
1. Check server error logs
2. Increase PHP memory limit
3. Check file permissions (755 for folders, 644 for files)
4. Disable mod_security temporarily
5. Contact hosting support

#### 4. Database Connection Errors

**Error**: "Error establishing a database connection"

**Solutions**:
- Check database credentials in `wp-config.php`
- Verify database server is running
- Check database user permissions
- Repair database tables
- Contact hosting provider

### Content Issues

#### 1. Missing Images After Import

**Issue**: Images show as broken links

**Solutions**:
1. **Check File Permissions**:
   ```bash
   chmod -R 755 wp-content/uploads
   ```

2. **Regenerate Thumbnails**:
   - Install "Regenerate Thumbnails" plugin
   - Run thumbnail regeneration

3. **Fix URLs**:
   - Check if source site is accessible
   - Update image URLs in database
   - Use search-replace tool

4. **Manual Upload**:
   - Download images from demo site
   - Upload to Media Library

#### 2. Broken Page Layouts

**Issue**: Pages don't look like demo

**Solutions**:
1. **Verify Required Plugins**:
   - Check all required plugins are active
   - Update plugins to latest versions

2. **Elementor Specific**:
   - Go to Elementor → Tools → Regenerate CSS
   - Clear Elementor cache
   - Check Elementor → Settings

3. **Theme Settings**:
   - Re-save permalinks
   - Check theme options
   - Clear theme cache

4. **CSS Issues**:
   - Clear browser cache
   - Check for CSS conflicts
   - Disable caching plugins

#### 3. Missing Menus

**Issue**: Navigation menus not imported

**Solutions**:
1. Go to **Appearance → Menus**
2. Check if menus were imported
3. Assign menus to locations:
   - Primary Menu
   - Footer Menu
   - Mobile Menu
4. Recreate menus if missing

#### 4. Incorrect Homepage

**Issue**: Wrong page displays as homepage

**Solutions**:
1. Go to **Settings → Reading**
2. Set "Your homepage displays" to "A static page"
3. Select correct Homepage
4. Select Posts page for blog
5. Save changes

### BuddyPress/BuddyBoss Issues

#### 1. Components Not Active

**Issue**: BuddyPress features missing

**Solutions**:
1. Go to **Settings → BuddyPress**
2. Check Components tab
3. Enable all required components
4. Save settings
5. Run demo import again

#### 2. Missing Profile Fields

**Issue**: User profile fields not imported

**Solutions**:
1. Check if xProfile component is active
2. Go to **Users → Profile Fields**
3. Manually create field groups
4. Import profile field data

#### 3. Groups Not Showing

**Issue**: BuddyPress groups missing

**Solutions**:
1. Verify Groups component is active
2. Check group visibility settings
3. Ensure user has permission to view groups
4. Create Groups page if missing

#### 4. Activity Stream Empty

**Issue**: No activity in streams

**Solutions**:
1. Check Activity component is active
2. Verify activity recording settings
3. Create some test activities
4. Check user privacy settings

### Performance Issues

#### 1. Slow Import Speed

**Issue**: Import takes too long

**Solutions**:
1. **Optimize Server**:
   - Increase PHP memory
   - Use PHP 7.4+ for better performance
   - Enable OPcache

2. **Database Optimization**:
   - Optimize database tables
   - Increase MySQL buffer sizes
   - Use persistent connections

3. **Import Strategy**:
   - Import during low-traffic hours
   - Use staging site first
   - Import in smaller chunks

#### 2. Site Slow After Import

**Issue**: Website performance degraded

**Solutions**:
1. **Clear All Caches**:
   - Browser cache
   - Plugin caches
   - Server cache
   - CDN cache

2. **Optimize Database**:
   ```sql
   OPTIMIZE TABLE wp_posts;
   OPTIMIZE TABLE wp_postmeta;
   OPTIMIZE TABLE wp_options;
   ```

3. **Check Plugins**:
   - Deactivate unnecessary plugins
   - Update all plugins
   - Check for conflicts

### Compatibility Issues

#### 1. PHP Version Errors

**Error**: "PHP version X.X is not supported"

**Solutions**:
- Upgrade PHP to 7.2 or higher
- Contact hosting to upgrade PHP
- Use PHP selector in cPanel

#### 2. Plugin Conflicts

**Issue**: Features not working correctly

**Solutions**:
1. Deactivate all plugins except required
2. Activate plugins one by one
3. Identify conflicting plugin
4. Find alternative or contact support

#### 3. Theme Conflicts

**Issue**: Demo doesn't match preview

**Solutions**:
- Ensure Reign theme is active
- Update to latest theme version
- Check child theme compatibility
- Reset theme options

### Advanced Troubleshooting

#### Enable Debug Mode

Add to `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('SCRIPT_DEBUG', true);
```

Check logs at: `/wp-content/debug.log`

#### Database Queries Debug

```php
define('SAVEQUERIES', true);
```

#### Check Server Info

Create `info.php`:
```php
<?php phpinfo(); ?>
```

Access via browser and check:
- PHP version
- Memory limits
- Loaded extensions
- Server settings

#### Using Browser Console

1. Open Developer Tools (F12)
2. Check Console tab for errors
3. Check Network tab for failed requests
4. Look for 404, 500, or timeout errors

### Getting Support

If issues persist:

1. **Gather Information**:
   - WordPress version
   - PHP version
   - Error messages
   - Debug log entries
   - Steps to reproduce

2. **Contact Support**:
   - Email: support@wbcomdesigns.com
   - Include all gathered information
   - Provide admin access if needed
   - Include FTP access for complex issues

3. **Support Forum**:
   - Visit: https://wbcomdesigns.com/support/
   - Search existing topics
   - Create new topic with details

### Prevention Tips

1. **Before Import**:
   - Always backup your site
   - Use staging environment
   - Check system requirements
   - Update all software

2. **During Import**:
   - Don't interrupt the process
   - Monitor progress
   - Keep browser window active
   - Check server resources

3. **After Import**:
   - Test all functionality
   - Check all pages
   - Verify forms work
   - Test user registration

## Emergency Recovery

If your site is broken after import:

1. **Restore from Backup**:
   - Use your pre-import backup
   - Contact hosting for backups

2. **Manual Cleanup**:
   - Delete imported content
   - Reset theme options
   - Deactivate plugins
   - Switch to default theme

3. **Database Reset**:
   - Use WP Reset plugin
   - Manual database cleanup
   - Fresh WordPress install

Remember: Always backup before importing!
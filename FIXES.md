# 🛠️ Fast Food POS System - Troubleshooting Guide

## 🚨 Common Issues and Solutions

### 1. **Database Connection Issues**

**Problem:** "Connection error" or database not found
**Solution:**
1. Run the installation script: `http://localhost/pizza_POS/install.php`
2. Check database credentials in `config/database.php`
3. Ensure MySQL service is running
4. Verify database name exists

### 2. **Login Issues**

**Problem:** Cannot login or "Invalid credentials"
**Solution:**
- Default credentials: `admin` / `password`
- Clear browser cache and cookies
- Check if sessions are working
- Run the test file: `http://localhost/pizza_POS/test_connection.php`

### 3. **Buttons Not Working**

**Problem:** Clicking buttons does nothing
**Solution:**
- Check browser console for JavaScript errors (F12)
- Ensure all JavaScript files are loaded
- Verify file permissions (755 for folders, 644 for files)
- Check if Font Awesome icons are loading

### 4. **Cart Not Working**

**Problem:** Items not adding to cart
**Solution:**
- Check if `cart.js` and `app.js` are properly loaded
- Verify localStorage is enabled in browser
- Check for JavaScript errors in console
- Ensure all functions are properly exported

### 5. **Items Not Loading**

**Problem:** Categories show but items don't appear
**Solution:**
- Check API endpoints: `api/get_items.php`
- Verify database has items
- Check browser network tab for AJAX errors
- The system has fallback mode for this issue

### 6. **Admin Panel Issues**

**Problem:** Cannot access admin panel
**Solution:**
- Ensure you're logged in as admin user
- Check if `admin/` folder exists
- Verify file permissions
- Check session variables

## 🔧 Quick Fixes

### **File Permissions**
```bash
# Set proper permissions
chmod 755 config/
chmod 755 assets/
chmod 755 api/
chmod 755 admin/
chmod 644 *.php
chmod 644 assets/css/*.css
chmod 644 assets/js/*.js
```

### **Database Setup**
```sql
-- If database doesn't exist, create it manually:
CREATE DATABASE pos_system;
USE pos_system;

-- Then import the schema:
SOURCE database.sql;
```

### **PHP Configuration**
Ensure these PHP extensions are enabled:
- `pdo_mysql`
- `session`
- `json`

### **Web Server Configuration**
For Apache, ensure `.htaccess` allows PHP execution:
```apache
<Files "*.php">
    Require all granted
</Files>
```

## 📋 System Requirements

### **Minimum Requirements:**
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- Modern web browser with JavaScript enabled

### **Recommended:**
- PHP 8.0+
- MySQL 8.0+
- HTTPS enabled
- 512MB RAM minimum

## 🧪 Testing Your Installation

1. **Run the test script:**
   ```
   http://localhost/pizza_POS/test_connection.php
   ```

2. **Check all files exist:**
   - `config/database.php` ✅
   - `assets/css/style.css` ✅
   - `assets/js/cart.js` ✅
   - `assets/js/app.js` ✅
   - `api/get_items.php` ✅
   - `api/process_order.php` ✅
   - `admin/index.php` ✅

3. **Test basic functionality:**
   - Login with admin/password
   - Click on categories
   - Add items to cart
   - Process an order

## 🔄 Recent Fixes Applied

### **JavaScript Function Exports**
- Added missing function exports in `app.js`
- Fixed cart function availability
- Added fallback for AJAX failures

### **Missing Admin Pages**
- Created `admin/manage_items.php`
- Created `admin/manage_categories.php`
- Created `admin/manage_users.php`

### **Error Handling**
- Added better error messages
- Improved AJAX error handling
- Added fallback mechanisms

### **Database Issues**
- Fixed SQL query in categories management
- Added proper foreign key handling
- Improved data validation

## 🚀 Performance Optimization

### **For Better Performance:**
1. Enable PHP OPcache
2. Use MySQL query caching
3. Enable browser caching for static files
4. Optimize images (if any)
5. Use CDN for Font Awesome

### **Security Enhancements:**
1. Change default admin password
2. Enable HTTPS
3. Set proper file permissions
4. Regular database backups
5. Keep PHP and MySQL updated

## 📞 Getting Help

### **If issues persist:**
1. Check browser console (F12) for errors
2. Check PHP error logs
3. Run the test connection script
4. Verify all files are uploaded correctly
5. Check server error logs

### **Common Error Messages:**
- **"Database connection failed"** → Check credentials and MySQL service
- **"Function not defined"** → Check JavaScript file loading
- **"Permission denied"** → Fix file permissions
- **"Session not working"** → Check PHP session configuration

## ✅ Verification Checklist

- [ ] Database connection working
- [ ] Login system functional
- [ ] Categories display correctly
- [ ] Items load in categories
- [ ] Cart adds/removes items
- [ ] Order processing works
- [ ] Admin panel accessible
- [ ] Receipt printing works
- [ ] All buttons functional
- [ ] No JavaScript errors in console

## 🎯 Quick Start After Fixes

1. **Install the system:**
   ```
   http://localhost/pizza_POS/install.php
   ```

2. **Login:**
   ```
   http://localhost/pizza_POS/login.php
   Username: admin
   Password: password
   ```

3. **Start using:**
   ```
   http://localhost/pizza_POS/index.php
   ```

4. **Access admin:**
   ```
   http://localhost/pizza_POS/admin/
   ```

---

**🍕 Fast Food POS System** - Now with comprehensive fixes and troubleshooting! 
# 🏆 Super Admin System - Almaida POS

A comprehensive stock management and multi-branch control system for the Almaida Fast Food POS.

## 🚀 Quick Start

### 1. Initial Setup
```bash
# Navigate to super admin setup
http://your-domain.com/almaida/super_admin/setup.php
```

### 2. Default Credentials
- **Username:** `superadmin`
- **Password:** `admin123`
- ⚠️ **Change these immediately after first login!**

### 3. Access Super Admin
```bash
# Login to super admin panel
http://your-domain.com/almaida/super_admin/login.php
```

## 🎯 Core Features

### 📦 Stock Management Workflow
1. **Purchase Stock** → Create purchase orders from suppliers
2. **Stock Entry** → Add purchased stock to main warehouse
3. **Distribute Stock** → Send stock from warehouse to branches
4. **Sales Deduction** → Automatic stock deduction on sales
5. **Low Stock Alerts** → Real-time notifications for low inventory

### 🏢 Multi-Branch Management
- **Branch Creation** → Add new locations
- **Stock Distribution** → Transfer inventory between branches
- **Branch-Specific Reports** → Individual branch analytics
- **Centralized Control** → Manage all branches from one dashboard

### 🔔 Smart Notifications
- **Low Stock Alerts** → When inventory falls below minimum
- **Out of Stock** → Immediate alerts for zero inventory
- **Purchase Approvals** → Notifications for pending purchases
- **Distribution Updates** → Status updates for stock transfers

## 📊 Dashboard Overview

### Statistics Cards
- **Active Branches** → Number of operational locations
- **Total Purchases** → All-time purchase orders
- **Pending Purchases** → Orders awaiting approval
- **Stock Distributions** → Warehouse to branch transfers
- **Unread Notifications** → System alerts and messages
- **Stock Alerts** → Low inventory warnings

### Quick Actions
- **Stock Purchases** → Create and manage purchase orders
- **Stock Distribution** → Transfer stock to branches
- **Manage Branches** → Add/edit branch locations
- **Notifications** → View system alerts
- **Stock Reports** → Comprehensive inventory reports
- **Manage Suppliers** → Supplier database management
- **Warehouse Stock** → Central inventory overview
- **System Settings** → Configuration options

## 🛠️ System Components

### Database Tables
- `suppliers` → Supplier information
- `stock_purchases` → Purchase orders
- `stock_purchase_items` → Items in purchases
- `stock_entries` → Warehouse stock entries
- `stock_entry_items` → Individual stock items
- `main_warehouse_stock` → Central inventory
- `stock_distributions` → Branch transfers
- `stock_distribution_items` → Transfer details
- `notifications` → System alerts
- `stock_alerts` → Low stock warnings
- `stock_movements` → Inventory tracking

### Key Files
- `index.php` → Main dashboard
- `login.php` → Authentication
- `stock_purchases.php` → Purchase management
- `stock_distributions.php` → Distribution system
- `manage_branches.php` → Branch management
- `notifications.php` → Alert system
- `setup.php` → Initial configuration

## 🔄 Complete Workflow

### 1. Stock Purchase Process
```
Supplier → Purchase Order → Approval → Stock Entry → Warehouse
```

### 2. Stock Distribution Process
```
Warehouse → Distribution Request → Approval → Transfer → Branch Stock
```

### 3. Sales Integration
```
Order → Stock Check → Sale → Automatic Deduction → Low Stock Alert
```

## 📈 Advanced Features

### Real-Time Stock Tracking
- **Automatic Deduction** → Stock reduces on each sale
- **Movement History** → Complete audit trail
- **Branch-Specific Inventory** → Per-location stock levels
- **Central Warehouse** → Main inventory hub

### Smart Alerts System
- **Configurable Thresholds** → Set minimum stock levels
- **Priority Levels** → Urgent, High, Medium, Low
- **Auto-Resolution** → Alerts clear when stock is added
- **Email Notifications** → Optional email alerts

### Comprehensive Reporting
- **Stock Levels** → Current inventory across all branches
- **Purchase History** → Complete buying records
- **Distribution Reports** → Transfer summaries
- **Low Stock Analysis** → Items needing restocking
- **Cost Analysis** → Inventory valuation

## 🔐 Security Features

### Access Control
- **Super Admin Only** → Restricted to authorized users
- **Session Management** → Secure login sessions
- **Activity Logging** → All actions are recorded
- **IP Tracking** → Login location monitoring

### Data Protection
- **Input Sanitization** → All data is cleaned
- **SQL Injection Prevention** → Prepared statements
- **XSS Protection** → Output encoding
- **CSRF Protection** → Token validation

## 🚨 Troubleshooting

### Common Issues

#### Setup Problems
```bash
# Check database connection
# Verify file permissions
# Ensure all SQL files are present
```

#### Login Issues
```bash
# Verify super admin user exists
# Check password hash
# Clear browser cache
```

#### Stock Not Updating
```bash
# Check stock_movements table
# Verify order processing
# Check branch assignments
```

### Debug Mode
```php
// Enable error reporting in config/database.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## 📋 Maintenance

### Regular Tasks
- **Monitor Notifications** → Check for alerts daily
- **Review Stock Levels** → Weekly inventory review
- **Update Suppliers** → Keep supplier info current
- **Backup Database** → Regular data backups

### Performance Optimization
- **Index Database** → Ensure proper indexing
- **Clean Logs** → Remove old activity logs
- **Optimize Queries** → Monitor slow queries
- **Update Statistics** → Refresh table stats

## 🔧 Configuration

### System Settings
- **Minimum Stock Levels** → Set alert thresholds
- **Notification Preferences** → Configure alerts
- **Branch Settings** → Location-specific options
- **Supplier Management** → Vendor database

### Customization
- **Dashboard Layout** → Modify quick actions
- **Report Templates** → Custom report formats
- **Alert Messages** → Personalized notifications
- **User Interface** → Theme and styling

## 📞 Support

### Documentation
- **API Reference** → Complete endpoint documentation
- **Database Schema** → Table relationships
- **User Manual** → Step-by-step guides
- **Video Tutorials** → Visual learning resources

### Contact
- **Technical Support** → For system issues
- **Feature Requests** → New functionality ideas
- **Bug Reports** → Issue reporting
- **Training** → User education

---

## 🎉 Success Metrics

### Key Performance Indicators
- **Stock Accuracy** → 99%+ inventory precision
- **Order Processing** → < 30 seconds per order
- **Alert Response** → < 5 minutes notification time
- **System Uptime** → 99.9% availability

### Business Benefits
- **Reduced Waste** → Better inventory control
- **Improved Efficiency** → Automated processes
- **Cost Savings** → Optimized purchasing
- **Better Service** → Real-time stock visibility

---

**🏆 Super Admin System - Powering Your Multi-Branch POS Success!**

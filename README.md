# 🍕 Almaida POS System - Complete Restaurant Management Solution

[![PHP Version](https://img.shields.io/badge/PHP-7.4+-blue.svg)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-5.7+-orange.svg)](https://mysql.com)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

A comprehensive Point of Sale (POS) system designed for multi-branch restaurant management with advanced inventory, staff scheduling, and revenue reconciliation features.

## 📋 Table of Contents

- [Features](#-features)
- [System Architecture](#-system-architecture)
- [Installation](#-installation)
- [User Roles & Permissions](#-user-roles--permissions)
- [Software Flow](#-software-flow)
- [QA Testing](#-qa-testing)
- [Troubleshooting](#-troubleshooting)
- [License](#-license)

## 🚀 Features

### Core POS Features
- **Multi-branch Management** - Support for unlimited restaurant branches
- **Order Management** - Complete order lifecycle from creation to completion
- **Real-time Kitchen Display** - Live order tracking for kitchen staff
- **Payment Processing** - Multiple payment methods with reconciliation
- **Receipt & Invoice Printing** - Professional receipt generation

### Inventory Management
- **Warehouse Stock Management** - Central inventory control
- **Branch Stock Tracking** - Per-location inventory management
- **Stock Purchases** - Purchase order management from suppliers
- **Stock Distributions** - Warehouse to branch transfers
- **Low Stock Alerts** - Automatic inventory notifications
- **Stock Movements** - Complete audit trail

### Staff & Schedule Management
- **Shift Scheduling** - Flexible staff scheduling system
- **Attendance Tracking** - Real-time attendance monitoring
- **Labor Cost Management** - Automatic labor cost calculations
- **Overtime Tracking** - Overtime hours and cost management
- **Staff Templates** - Recurring schedule templates

### Revenue & Financial Management
- **Cash Drawer Management** - Opening/closing cash drawers
- **Revenue Reconciliation** - Daily revenue tracking and variance analysis
- **Payment Method Tracking** - Cash, card, digital wallet support
- **Financial Reporting** - Comprehensive financial analytics
- **Multi-branch Revenue Monitoring** - Centralized financial oversight

### Analytics & Reporting
- **Sales Analytics** - Detailed sales performance metrics
- **Inventory Reports** - Stock movement and consumption analysis
- **Labor Cost Reports** - Staff cost and efficiency metrics
- **Revenue Reports** - Financial performance tracking
- **Branch Comparison** - Multi-location performance analysis

## 🏗️ System Architecture

### Technology Stack
- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **Libraries**: jQuery, Font Awesome, Bootstrap-inspired styling
- **Server**: Apache/Nginx with PHP support

### File Structure
```
almaida/
├── admin/                          # Branch Admin Panel
│   ├── cashier_dashboard.php      # Cashier interface
│   ├── kitchen_display.php        # Kitchen order display
│   ├── manage_*.php               # Management interfaces
│   ├── revenue_reconciliation.php # Revenue management
│   ├── shift_schedule.php         # Staff scheduling
│   └── view_orders.php            # Order management
├── super_admin/                    # Super Admin Panel
│   ├── index.php                  # Dashboard
│   ├── manage_branches.php        # Branch management
│   ├── revenue_reconciliation.php # Multi-branch revenue
│   ├── shift_schedule.php         # Multi-branch scheduling
│   └── stock_*.php                # Stock management
├── api/                           # API Endpoints
│   ├── get_items.php              # Item retrieval
│   ├── process_order.php          # Order processing
│   └── search_*.php               # Search functionality
├── assets/                        # Static Assets
│   ├── css/                       # Stylesheets
│   └── js/                        # JavaScript files
├── config/                        # Configuration
│   └── database.php               # Database configuration
├── database_*.sql                 # Database schemas
└── setup files                    # Installation scripts
```

## 📦 Installation

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- Web browser (Chrome, Firefox, Safari, Edge)

### Quick Installation

1. **Download & Extract**
   ```bash
   # Download the project files to your web directory
   cd /path/to/your/web/directory
   ```

2. **Database Setup**
   ```bash
   # Import the main database schema
   mysql -u username -p database_name < pizza_pos.sql
   ```

3. **Run Complete Setup**
   - Navigate to: `http://your-domain/almaida/complete_setup.php`
   - Click "Start Complete Setup"
   - Wait for all 16 setup steps to complete

4. **Verify Installation**
   - Navigate to: `http://your-domain/almaida/verify_features.php`
   - Ensure all tests pass

5. **Initial Configuration**
   - Configure database connection in `config/database.php`
   - Set up your first super admin account
   - Create your first branch

### Manual Installation

1. **Database Configuration**
   ```php
   // config/database.php
   $host = 'localhost';
   $dbname = 'your_database_name';
   $username = 'your_username';
   $password = 'your_password';
   ```

2. **Import Database Schemas**
   ```sql
   -- Main POS system
   SOURCE pizza_pos.sql;
   
   -- Stock management
   SOURCE database_stock_management.sql;
   
   -- Branch integration
   SOURCE database_branch_integration.sql;
   
   -- Revenue & shift management
   SOURCE database_revenue_shift_management_fixed.sql;
   ```

3. **File Permissions**
   ```bash
   chmod 755 /path/to/almaida/
   chmod 644 /path/to/almaida/config/database.php
   ```

## 👥 User Roles & Permissions

### Super Admin
- **Full System Access**: Complete control over all branches
- **Branch Management**: Create, edit, delete branches
- **Stock Management**: Warehouse inventory, purchases, distributions
- **Revenue Monitoring**: Multi-branch revenue reconciliation
- **Staff Oversight**: View all schedules and labor costs
- **System Configuration**: Global settings and configurations

### Branch Admin
- **Branch Operations**: Manage their specific branch only
- **Order Management**: Process orders and payments
- **Staff Scheduling**: Create and manage shift schedules
- **Revenue Reconciliation**: Daily cash drawer management
- **Inventory Tracking**: View and manage branch stock
- **Reports Access**: Branch-specific analytics

### Cashier
- **Order Processing**: Create and process customer orders
- **Payment Handling**: Process payments and print receipts
- **Basic Reports**: View sales summaries
- **Cash Drawer**: Open/close cash drawers

### Kitchen Staff
- **Order Display**: View kitchen display for order preparation
- **Order Status**: Update order completion status
- **No Financial Access**: Cannot access payment or cash functions

## 🔄 Software Flow

### Order Processing Flow
```
1. Customer Order Entry
   ↓
2. Order Validation & Item Availability Check
   ↓
3. Order Sent to Kitchen Display
   ↓
4. Kitchen Preparation & Status Updates
   ↓
5. Order Completion & Payment Processing
   ↓
6. Receipt Generation & Cash Drawer Update
   ↓
7. Inventory Deduction & Sales Recording
```

### Stock Management Flow
```
1. Warehouse Stock Purchase from Supplier
   ↓
2. Stock Entry into Warehouse
   ↓
3. Branch Requests Stock Distribution
   ↓
4. Super Admin Approves Distribution
   ↓
5. Stock Dispatched to Branch
   ↓
6. Branch Admin Receives & Confirms Stock
   ↓
7. Branch Stock Updated & Available for Sales
```

### Revenue Reconciliation Flow
```
1. Branch Admin Opens Cash Drawer
   ↓
2. Orders Processed Throughout Day
   ↓
3. Payment Methods Tracked per Order
   ↓
4. Branch Admin Closes Cash Drawer
   ↓
5. Actual Cash Counted & Entered
   ↓
6. System Calculates Expected vs Actual
   ↓
7. Variance Analysis & Reconciliation
   ↓
8. Super Admin Monitoring & Approval
```

### Staff Scheduling Flow
```
1. Branch Admin Creates Shift Schedules
   ↓
2. Staff Assigned to Specific Shifts
   ↓
3. Staff Members Mark Attendance (Start/End)
   ↓
4. System Tracks Actual Hours Worked
   ↓
5. Automatic Labor Cost Calculation
   ↓
6. Overtime Detection & Calculation
   ↓
7. Labor Cost Approval & Reporting
```

---

**Version**: 2.0.0  
**Last Updated**: January 2025  
**Maintainer**: MUHAMMAD WASIM - Development Team
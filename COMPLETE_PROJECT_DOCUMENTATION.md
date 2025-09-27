# Fast Food POS System - Complete Documentation

## Table of Contents
1. [Project Overview](#project-overview)
2. [System Architecture](#system-architecture)
3. [Installation & Setup](#installation--setup)
4. [Database Structure](#database-structure)
5. [User Roles & Permissions](#user-roles--permissions)
6. [Branch Management](#branch-management)
7. [Core Features](#core-features)
8. [API Documentation](#api-documentation)
9. [Security Features](#security-features)
10. [File Structure](#file-structure)
11. [Configuration](#configuration)
12. [Troubleshooting](#troubleshooting)
13. [Development Guidelines](#development-guidelines)

---

## Project Overview

**Fast Food POS System** is a comprehensive Point of Sale (POS) system designed for restaurant chains with multiple branches. The system provides complete order management, inventory tracking, staff management, financial reporting, and branch-specific data isolation.

### Key Features
- 🍕 **Multi-Branch Restaurant Chain Management**
- 💳 **Complete POS Operations** (Ordering, Payment, Receipts)
- 📊 **Real-time Analytics & Reporting**
- 👥 **Staff & Shift Management**
- 📦 **Inventory & Stock Management**
- 🎯 **Marketing & Special Offers**
- 🔒 **Role-based Access Control**
- 📱 **Responsive Design** (Mobile & Desktop)

### Technology Stack
- **Backend**: PHP 7.4+ with PDO
- **Database**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **Libraries**: PhpSpreadsheet, Chart.js, Font Awesome
- **Server**: Apache/Nginx with XAMPP/WAMP support

---

## System Architecture

### Multi-Tier Architecture
```
┌─────────────────────────────────────────────────────────────┐
│                    Presentation Layer                       │
│  ┌─────────────┐ ┌─────────────┐ ┌─────────────────────────┐ │
│  │   POS UI    │ │  Admin UI   │ │    Super Admin UI       │ │
│  │ (index.php) │ │ (admin/*)   │ │   (super_admin/*)       │ │
│  └─────────────┘ └─────────────┘ └─────────────────────────┘ │
└─────────────────────────────────────────────────────────────┘
                              │
┌─────────────────────────────────────────────────────────────┐
│                    Business Logic Layer                     │
│  ┌─────────────┐ ┌─────────────┐ ┌─────────────────────────┐ │
│  │   API       │ │  Auth       │ │    Branch Filtering     │ │
│  │ (api/*)     │ │  System     │ │    & Data Isolation     │ │
│  └─────────────┘ └─────────────┘ └─────────────────────────┘ │
└─────────────────────────────────────────────────────────────┘
                              │
┌─────────────────────────────────────────────────────────────┐
│                      Data Access Layer                      │
│  ┌─────────────┐ ┌─────────────┐ ┌─────────────────────────┐ │
│  │   Database  │ │  File       │ │    Session              │ │
│  │  (MySQL)    │ │  Storage    │ │    Management           │ │
│  └─────────────┘ └─────────────┘ └─────────────────────────┘ │
└─────────────────────────────────────────────────────────────┘
```

### Branch Isolation Model
- **Shared Data**: Items, Categories, Settings (Global across all branches)
- **Branch-Specific**: Orders, Users, Stock Levels, Special Offers, Financial Reports

---

## Installation & Setup

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- Composer (for PhpSpreadsheet)

### Installation Steps

#### 1. Download & Extract
```bash
# Clone or download the project
cd /path/to/your/web/directory
# Extract files to web root (e.g., /htdocs/almaida/)
```

#### 2. Database Setup
```sql
-- Create database
CREATE DATABASE almaida_pos;
-- Import the main database file
mysql -u root -p almaida_pos < pizza_pos.sql
```

#### 3. Configuration
```php
// Edit config/database.php
private $host = 'localhost';
private $db_name = 'almaida_pos';
private $username = 'your_username';
private $password = 'your_password';
```

#### 4. Install Dependencies
```bash
# Install PhpSpreadsheet via Composer
composer require phpoffice/phpspreadsheet
```

#### 5. File Permissions
```bash
# Ensure proper permissions
chmod 755 assets/
chmod 644 config/database.php
```

#### 6. Initial Setup
1. Access: `http://localhost/almaida/install.php`
2. Create super admin account
3. Set up initial branches
4. Configure system settings

---

## Database Structure

### Core Tables

#### Users & Authentication
```sql
-- users: System users (admin, cashier, super_admin)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'cashier', 'super_admin') NOT NULL,
    branch_id INT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id)
);

-- branches: Restaurant locations
CREATE TABLE branches (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    address TEXT,
    phone VARCHAR(20),
    email VARCHAR(100),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

#### Menu Management
```sql
-- categories: Food categories
CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    display_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- items: Menu items (shared across branches)
CREATE TABLE items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    category_id INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    description TEXT,
    has_size_variants TINYINT(1) DEFAULT 0,
    is_available TINYINT(1) DEFAULT 1,
    is_deleted TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id)
);

-- item_size_variants: Size options for items
CREATE TABLE item_size_variants (
    id INT PRIMARY KEY AUTO_INCREMENT,
    item_id INT NOT NULL,
    size_name VARCHAR(50) NOT NULL,
    size_price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
);
```

#### Order Management
```sql
-- orders: Customer orders (branch-specific)
CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    user_id INT NOT NULL,
    customer_id INT,
    branch_id INT NOT NULL,
    order_type ENUM('dine_in', 'takeaway', 'delivery') DEFAULT 'dine_in',
    subtotal DECIMAL(10,2) NOT NULL,
    tax_amount DECIMAL(10,2) DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cash', 'card', 'online') DEFAULT 'cash',
    payment_status ENUM('pending', 'paid', 'refunded') DEFAULT 'pending',
    order_status ENUM('pending', 'preparing', 'ready', 'completed', 'cancelled') DEFAULT 'pending',
    table_number VARCHAR(20),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (branch_id) REFERENCES branches(id)
);

-- order_items: Items in each order
CREATE TABLE order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    size_variant VARCHAR(50),
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id)
);
```

#### Inventory Management
```sql
-- branch_items: Stock levels per branch
CREATE TABLE branch_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    branch_id INT NOT NULL,
    item_id INT NOT NULL,
    current_stock INT DEFAULT 0,
    minimum_stock INT DEFAULT 10,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (item_id) REFERENCES items(id),
    UNIQUE KEY unique_branch_item (branch_id, item_id)
);

-- stock_movements: Inventory tracking
CREATE TABLE stock_movements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    branch_id INT NOT NULL,
    item_id INT NOT NULL,
    movement_type ENUM('in', 'out') NOT NULL,
    quantity INT NOT NULL,
    previous_stock INT NOT NULL,
    new_stock INT NOT NULL,
    reference_type VARCHAR(50),
    notes TEXT,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (item_id) REFERENCES items(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

#### Marketing & Offers
```sql
-- special_offers: Promotional offers (branch-specific)
CREATE TABLE special_offers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    discount_type ENUM('percentage', 'fixed_amount') NOT NULL,
    discount_value DECIMAL(10,2) NOT NULL,
    start_date DATE,
    end_date DATE,
    is_active TINYINT(1) DEFAULT 1,
    branch_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id)
);
```

#### Staff Management
```sql
-- shift_schedules: Work schedules
CREATE TABLE shift_schedules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    branch_id INT NOT NULL,
    shift_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    is_worker TINYINT(1) DEFAULT 0,
    fingerprint_id VARCHAR(50),
    status ENUM('scheduled', 'present', 'absent', 'late') DEFAULT 'scheduled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (branch_id) REFERENCES branches(id)
);

-- workers: External workers (non-POS users)
CREATE TABLE workers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    fingerprint_id VARCHAR(50) UNIQUE,
    branch_id INT NOT NULL,
    phone VARCHAR(20),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id)
);
```

#### System Settings
```sql
-- settings: Global system configuration
CREATE TABLE settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

---

## User Roles & Permissions

### Role Hierarchy

#### 1. Super Admin
- **Access**: All branches and system-wide settings
- **Permissions**:
  - Manage all branches
  - Create/edit/delete all users
  - View all financial reports
  - Access system settings
  - Manage global inventory
  - Export/import data

#### 2. Branch Admin
- **Access**: Assigned branch only
- **Permissions**:
  - Manage branch users (excluding super admin)
  - View/edit branch orders
  - Access branch reports
  - Manage branch inventory
  - Create/edit special offers
  - Kitchen display management

#### 3. Cashier
- **Access**: Assigned branch only
- **Permissions**:
  - Process orders (POS operations)
  - View order history
  - Basic inventory viewing
  - Receipt printing
  - Limited back office access

#### 4. Worker
- **Access**: Attendance tracking only
- **Permissions**:
  - Clock in/out
  - View personal schedule
  - No POS or admin access

---

## Branch Management

### Branch Isolation Features

#### Data Separation
- **Orders**: Each branch sees only their orders
- **Users**: Branch admins manage only their staff
- **Inventory**: Shared items with branch-specific stock levels
- **Reports**: Financial data isolated per branch
- **Special Offers**: Branch-specific promotions

#### Security Implementation
```php
// Example: Branch filtering in queries
$branch_id = $_SESSION['branch_id'] ?? null;
if (!$branch_id) {
    die('No branch assigned to your account');
}

$query = "SELECT * FROM orders WHERE branch_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$branch_id]);
```

#### Cross-Branch Access
- **Super Admin**: Can view all branches
- **Regular Users**: Restricted to assigned branch only
- **Data Export**: Branch-specific exports available

---

## Core Features

### 1. POS Operations (`index.php`)

#### Order Processing
- Category-based item browsing
- Size variant selection
- Real-time cart management
- Multiple payment methods
- Receipt generation
- Order number sequencing

#### User Interface
- Responsive design (mobile/desktop)
- Dark/light mode toggle
- Touch-friendly interface
- Real-time updates
- Keyboard shortcuts

### 2. Admin Dashboard (`admin/index.php`)

#### Statistics Overview
- Today's orders and revenue
- Total orders and revenue
- User-specific statistics
- Recent order tracking
- Branch-specific data

#### Quick Actions
- View all orders
- Kitchen display access
- Inventory management
- User management
- Reports access

### 3. Order Management

#### Order Tracking
- Real-time order status updates
- Kitchen display integration
- Order history and search
- Customer information
- Payment tracking

#### Status Management
- Pending → Preparing → Ready → Completed
- Cancellation support
- Order modifications
- Table management

### 4. Inventory Management

#### Stock Tracking
- Real-time stock levels
- Low stock alerts
- Stock movement history
- Branch-specific inventory
- Automated reordering

#### Stock Operations
- Stock receiving
- Stock distribution
- Stock adjustments
- Supplier management
- Purchase orders

### 5. Staff Management

#### User Management
- Create/edit/delete users
- Role assignment
- Branch assignment
- Password management
- User activity tracking

#### Shift Scheduling
- Weekly/monthly schedules
- Shift templates
- Attendance tracking
- Fingerprint integration
- Worker management

### 6. Financial Reporting

#### Revenue Reports
- Daily/weekly/monthly reports
- Branch comparison
- Payment method analysis
- Tax reporting
- Profit/loss statements

#### Analytics
- Top-selling items
- Category performance
- Customer analytics
- Trend analysis
- Export capabilities

### 7. Marketing Features

#### Special Offers
- Percentage discounts
- Fixed amount discounts
- Date range controls
- Branch-specific offers
- Active/inactive status

#### Promotion Management
- Offer creation and editing
- Performance tracking
- Customer targeting
- Seasonal campaigns

---

## API Documentation

### Authentication
All API endpoints require valid session authentication.

### Core APIs

#### Order Processing
```http
POST /api/process_order.php
Content-Type: application/json

{
    "order_number": "ORD20250101001",
    "items": [
        {
            "id": 1,
            "name": "Margherita Pizza",
            "price": 15.99,
            "quantity": 2,
            "size_variant": "Large"
        }
    ],
    "customer": {
        "name": "John Doe",
        "phone": "+1234567890"
    },
    "order_type": "dine_in",
    "table_number": "A1",
    "payment_method": "cash",
    "notes": "Extra cheese"
}
```

#### Complete Order
```http
POST /api/complete_order.php
Content-Type: application/json

{
    "items": [...],
    "customer": {...},
    "order_type": "takeaway",
    "payment_method": "card"
}
```

#### Get Items
```http
GET /api/get_items.php?category_id=1
Response: {
    "success": true,
    "items": [
        {
            "id": 1,
            "name": "Margherita Pizza",
            "price": 15.99,
            "category_name": "Pizza",
            "size_variants": [
                {"size_name": "Small", "size_price": 12.99},
                {"size_name": "Large", "size_price": 15.99}
            ]
        }
    ]
}
```

#### Search Orders
```http
POST /api/search_orders.php
Content-Type: application/json

{
    "search_term": "ORD20250101001",
    "date_from": "2025-01-01",
    "date_to": "2025-01-31"
}
```

### Response Format
```json
{
    "success": true|false,
    "message": "Operation result message",
    "data": {...},
    "error": "Error details if applicable"
}
```

---

## Security Features

### Authentication & Authorization
- Session-based authentication
- Role-based access control
- Branch-specific data isolation
- Password hashing (PHP password_hash)
- Session timeout management

### Data Protection
- SQL injection prevention (PDO prepared statements)
- XSS protection (htmlspecialchars)
- CSRF protection (session validation)
- Input sanitization
- File upload validation

### Security Headers
```php
// API security headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
```

### Branch Isolation
- All queries filtered by branch_id
- User access restricted to assigned branch
- Data export limited to branch scope
- Cross-branch access only for super admin

---

## File Structure

```
almaida/
├── admin/                          # Admin panel
│   ├── includes/                   # Common includes
│   │   ├── header.php             # Page header
│   │   ├── sidebar.php            # Navigation sidebar
│   │   └── footer.php             # Page footer
│   ├── index.php                  # Admin dashboard
│   ├── view_orders.php            # Order management
│   ├── kitchen_display.php        # Kitchen order display
│   ├── manage_items.php           # Menu item management
│   ├── manage_categories.php      # Category management
│   ├── manage_users.php           # User management
│   ├── received_stock.php         # Stock receiving
│   ├── shift_schedule.php         # Staff scheduling
│   ├── reports.php                # Financial reports
│   ├── revenue_reconciliation.php # Revenue management
│   ├── manage_special_offers.php  # Marketing offers
│   └── settings.php               # System settings
├── api/                           # API endpoints
│   ├── process_order.php          # Order processing
│   ├── complete_order.php         # Order completion
│   ├── get_items.php              # Item retrieval
│   ├── search_orders.php          # Order search
│   └── generate_order_number.php  # Order numbering
├── super_admin/                   # Super admin panel
│   ├── index.php                  # Super admin dashboard
│   ├── manage_branches.php        # Branch management
│   ├── stock_management.php       # Global inventory
│   └── setup.php                  # System setup
├── assets/                        # Static assets
│   ├── css/
│   │   ├── style.css              # Main stylesheet
│   │   └── print-optimized.css    # Print styles
│   └── js/
│       ├── app.js                 # Main application JS
│       └── cart.js                # Cart management
├── config/
│   └── database.php               # Database configuration
├── index.php                      # Main POS interface
├── login.php                      # Authentication
├── install.php                    # Installation script
└── pizza_pos.sql                  # Database schema
```

---

## Configuration

### Database Configuration (`config/database.php`)
```php
class Database {
    private $host = 'localhost';
    private $db_name = 'almaida_pos';
    private $username = 'root';
    private $password = '';
    // ... connection logic
}
```

### System Settings
- Company information
- Tax rates and currency
- Receipt configuration
- Order numbering
- Timezone settings

### Branch Configuration
- Branch details
- Contact information
- Operating hours
- Specific settings per branch

---

## Troubleshooting

### Common Issues

#### Database Connection Errors
```php
// Check database credentials
// Verify MySQL service is running
// Ensure database exists
```

#### Session Issues
```php
// Check session configuration
// Verify session storage permissions
// Clear browser cookies/cache
```

#### File Permission Errors
```bash
# Set proper permissions
chmod 755 assets/
chmod 644 config/database.php
```

#### PhpSpreadsheet Errors
```bash
# Reinstall Composer dependencies
composer install
composer update phpoffice/phpspreadsheet
```

### Error Logging
- PHP errors logged to server error log
- Database errors handled gracefully
- User-friendly error messages
- Debug mode for development

---

## Development Guidelines

### Code Standards
- PSR-4 autoloading
- Consistent naming conventions
- Proper error handling
- Security-first approach
- Database abstraction layer

### Database Guidelines
- Always use prepared statements
- Implement proper indexing
- Use foreign key constraints
- Normalize data structure
- Regular backups

### Security Best Practices
- Validate all inputs
- Sanitize outputs
- Use HTTPS in production
- Regular security updates
- Access control implementation

### Performance Optimization
- Database query optimization
- Image compression
- CSS/JS minification
- Caching strategies
- CDN integration

---

## Support & Maintenance

### Regular Maintenance
- Database optimization
- Log file cleanup
- Security updates
- Backup verification
- Performance monitoring

### Feature Updates
- Version control (Git recommended)
- Testing procedures
- Staging environment
- Rollback procedures
- Documentation updates

### Support Resources
- System documentation
- API documentation
- User manuals
- Troubleshooting guides
- Contact information

---

## Conclusion

The Fast Food POS System provides a comprehensive solution for restaurant chain management with:

- ✅ **Complete POS Operations**
- ✅ **Multi-Branch Management**
- ✅ **Role-Based Security**
- ✅ **Real-Time Analytics**
- ✅ **Inventory Management**
- ✅ **Staff Scheduling**
- ✅ **Marketing Tools**
- ✅ **Mobile Responsiveness**

The system is production-ready and designed for scalability, security, and ease of use. Regular maintenance and updates ensure optimal performance and security.

---

**Version**: 2.0  
**Last Updated**: January 2025  
**Compatibility**: PHP 7.4+, MySQL 5.7+, Modern Browsers  
**License**: Commercial Use

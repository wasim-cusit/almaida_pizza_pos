# 🍕 Fast Food POS System - Almaida

A complete, professional Point of Sale (POS) system designed for restaurants and fast-food establishments. Built with modern web technologies and inspired by real-world POS interfaces.

## ✨ Features

### 🛒 Core POS Features
- **Real-time Cart Management** - Add, remove, and modify items with live updates
- **Category-based Menu** - Organized food categories with intuitive navigation
- **Order Processing** - Complete order workflow with payment options
- **Customer Management** - Store customer information and order history
- **Receipt Generation** - Print-ready receipts with order details
- **Order Status Tracking** - Track orders from pending to completed
- **Size Variants** - Support for different item sizes (Small, Medium, Large, etc.)

### 🎨 User Interface
- **Modern Design** - Clean, professional interface optimized for touch screens
- **Responsive Layout** - Works on desktop, tablet, and mobile devices
- **Keyboard Shortcuts** - Function keys for quick category access
- **Toast Notifications** - Real-time feedback for user actions
- **Modal Dialogs** - Clean popup interfaces for special functions

### 🔧 Administrative Features
- **Admin Dashboard** - Overview of sales, orders, and system statistics
- **User Management** - Role-based access control (Admin/Cashier)
- **Menu Management** - Add, edit, and organize menu items and categories
- **Sales Reports** - Daily, weekly, and monthly sales analytics
- **Order History** - Complete order tracking and management
- **Kitchen Display** - Real-time order display for kitchen staff

### 💾 Technical Features
- **Database Integration** - MySQL database with normalized structure
- **Session Management** - Secure user authentication and session handling
- **AJAX Support** - Real-time updates without page refreshes
- **Local Storage** - Cart persistence across browser sessions
- **Print Support** - Receipt printing functionality
- **QR Code Generation** - Order tracking with QR codes

## 🚀 Quick Start

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- Modern web browser

### Installation

1. **Clone or Download**
   ```bash
   git clone <repository-url>
   cd almaida
   ```

2. **Database Setup**
   - Create a MySQL database named `u515862593_almaida`
   - Import the database schema:
   ```bash
   mysql -u root -p u515862593_almaida < u515862593_almaida.sql
   ```

3. **Configuration**
   - Edit `config/database.php` with your database credentials:
   ```php
   private $host = 'localhost';
   private $db_name = 'u515862593_almaida';
   private $username = 'your_username';
   private $password = 'your_password';
   ```

4. **Web Server Setup**
   - Point your web server to the project directory
   - Ensure PHP has write permissions for session handling

5. **Access the System**
   - Navigate to `http://localhost/almaida/`
   - Login with default credentials:
     - **Username:** `admin`
     - **Password:** `password`

## 📁 Project Structure

```
almaida/
├── config/
│   └── database.php              # Database configuration
├── assets/
│   ├── css/
│   │   ├── style.css             # Main stylesheet
│   │   └── print-optimized.css   # Print styles
│   └── js/
│       ├── app.js                # Main application logic
│       └── cart.js               # Cart management
├── api/
│   ├── get_items.php             # Fetch items by category
│   ├── process_order.php         # Process orders
│   ├── complete_order.php        # Complete order workflow
│   ├── search_items.php          # Search functionality
│   └── generate_order_number.php # Order number generation
├── admin/
│   ├── index.php                 # Admin dashboard
│   ├── manage_items.php          # Item management
│   ├── manage_categories.php     # Category management
│   ├── manage_users.php          # User management
│   ├── reports.php               # Sales reports
│   ├── view_orders.php           # Order management
│   ├── kitchen_display.php       # Kitchen display
│   └── settings.php              # System settings
├── u515862593_almaida.sql        # Database schema
├── index.php                     # Main POS interface
├── login.php                     # Login page
├── logout.php                    # Logout functionality
├── print_receipt.php             # Receipt printing
├── print_invoice.php             # Invoice printing
└── README.md                     # This file
```

## 🎯 Usage Guide

### For Cashiers
1. **Login** with your credentials
2. **Select Categories** using the category grid or function keys (F1-F12)
3. **Add Items** by clicking on menu items
4. **Select Sizes** for items with size variants
5. **Manage Cart** using the left sidebar controls
6. **Enter Customer Info** (optional)
7. **Process Payment** using the Order button
8. **Print Receipt** when prompted

### For Administrators
1. **Access Admin Panel** via the Back Office button
2. **View Statistics** on the dashboard
3. **Manage Menu Items** and categories
4. **Generate Reports** for sales analysis
5. **Manage Users** and system settings
6. **Monitor Kitchen** with kitchen display

### Keyboard Shortcuts
- **F1-F12**: Quick category selection
- **Arrow Keys**: Navigate cart items
- **+/-**: Increase/decrease quantity
- **Delete**: Remove selected item
- **Ctrl+Enter**: Process order
- **Escape**: Close modals

## 🗄️ Database Schema

### Core Tables
- **users** - User accounts and roles
- **categories** - Menu categories
- **items** - Menu items with prices and stock
- **item_size_variants** - Size options for items
- **customers** - Customer information
- **orders** - Order headers
- **order_items** - Individual order items
- **settings** - System configuration
- **special_offers** - Promotional offers

### Sample Data
The system comes pre-loaded with:
- 17 food categories (Pizza, Burgers, Drinks, etc.)
- 80+ menu items with realistic prices
- Size variants for pizza items
- Admin user account
- Default system settings

## 🔧 Customization

### Adding New Categories
1. Access the admin panel
2. Navigate to "Manage Categories"
3. Add new category with appropriate icon

### Modifying Menu Items
1. Go to "Manage Items" in admin panel
2. Edit existing items or add new ones
3. Set prices, descriptions, and availability
4. Configure size variants if needed

### Styling Customization
- Edit `assets/css/style.css` for visual changes
- Modify color schemes in CSS variables
- Adjust layout for different screen sizes

## 🛡️ Security Features

- **Password Hashing** - Secure password storage using bcrypt
- **SQL Injection Prevention** - Prepared statements throughout
- **Session Management** - Secure session handling
- **Input Sanitization** - All user inputs are sanitized
- **Role-based Access** - Admin/Cashier permissions
- **CSRF Protection** - Form token validation

## 📊 Reporting Features

- **Daily Sales Reports** - Revenue and order counts
- **Order Analytics** - Popular items and trends
- **Customer Reports** - Customer order history
- **Category Performance** - Sales by category
- **Export Functionality** - CSV export for reports
- **Date Range Filtering** - Custom date periods

## 🔄 Current Limitations

### Stock Management
- **Basic Structure Only** - Database has stock_quantity field but no automatic deduction
- **No Low Stock Alerts** - No warnings when inventory is low
- **No Stock Reports** - No inventory tracking reports
- **No Stock Adjustments** - No interface to add/remove stock

### Multi-Branch Support
- **Single Location Only** - All data is stored in one database
- **No Branch Management** - No support for multiple locations
- **No Cross-Branch Reporting** - No multi-location analytics

## 🚀 Future Enhancements

- **Advanced Inventory Management** - Automatic stock deduction and alerts
- **Multi-location Support** - Multiple restaurant locations
- **Online Ordering** - Customer-facing ordering system
- **Mobile App** - Native mobile applications
- **Payment Gateway Integration** - Credit card processing
- **Loyalty Program** - Customer rewards system
- **Advanced Analytics** - Machine learning insights

## 🐛 Troubleshooting

### Common Issues

**Database Connection Error**
- Verify database credentials in `config/database.php`
- Ensure MySQL service is running
- Check database name exists

**Login Issues**
- Default credentials: admin/password
- Clear browser cache and cookies
- Check PHP session configuration

**Cart Not Working**
- Ensure JavaScript is enabled
- Check browser console for errors
- Verify file permissions

**Print Issues**
- Allow popups for receipt printing
- Check printer settings
- Use modern browsers for best compatibility

## 📞 Support

For technical support or feature requests:
- Check the troubleshooting section above
- Review browser console for JavaScript errors
- Verify PHP error logs for server issues

## 📄 License

This project is open source and available under the MIT License.

## 🙏 Acknowledgments

- Inspired by real-world POS systems
- Built with modern web standards
- Designed for restaurant efficiency
- Optimized for touch-screen interfaces

---

**🍕 Fast Food POS System - Almaida** - Making restaurant management easier, one order at a time!

## 📈 System Rating

| Feature | Rating | Notes |
|---------|--------|-------|
| **Order Management** | 9/10 | Excellent order processing |
| **User Interface** | 9/10 | Modern, responsive design |
| **User Management** | 8/10 | Good role-based access |
| **Reporting** | 8/10 | Comprehensive sales reports |
| **Stock Management** | 2/10 | Basic structure only |
| **Multi-Branch** | 0/10 | Not supported |
| **Overall** | 6/10 | Good POS, needs inventory features |

**Best suited for**: Single-location restaurants that don't need advanced inventory management.
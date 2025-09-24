# 📚 API Documentation - Almaida POS System

Complete API reference for the Almaida POS System including all endpoints, parameters, responses, and integration examples.

## 📋 Table of Contents

- [Authentication](#-authentication)
- [Order Management APIs](#-order-management-apis)
- [Stock Management APIs](#-stock-management-apis)
- [Revenue Reconciliation APIs](#-revenue-reconciliation-apis)
- [Staff Scheduling APIs](#-staff-scheduling-apis)
- [User Management APIs](#-user-management-apis)
- [Reporting APIs](#-reporting-apis)
- [Error Handling](#-error-handling)
- [Rate Limiting](#-rate-limiting)

## 🔐 Authentication

All API endpoints require proper authentication through session management or API keys.

### Session Authentication
```php
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Check user role
if ($_SESSION['user_role'] !== 'required_role') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit();
}
```

### API Key Authentication (Future Implementation)
```php
// API Key validation
$api_key = $_SERVER['HTTP_X_API_KEY'] ?? '';
if (!validate_api_key($api_key)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid API key']);
    exit();
}
```

## 🛒 Order Management APIs

### Create New Order
**Endpoint**: `POST /api/process_order.php`

**Request**:
```json
{
  "action": "create_order",
  "items": [
    {
      "item_id": 1,
      "quantity": 2,
      "price": 12.99,
      "notes": "Extra cheese"
    }
  ],
  "customer_info": {
    "name": "John Doe",
    "phone": "+1234567890",
    "email": "john@example.com"
  },
  "payment_method_id": 1,
  "cash_drawer_id": 123
}
```

**Response**:
```json
{
  "success": true,
  "order_id": 456,
  "order_number": "ORD-2024-001",
  "total_amount": 25.98,
  "tax_amount": 2.08,
  "message": "Order created successfully"
}
```

### Get Order Details
**Endpoint**: `GET /api/get_order_details.php`

**Parameters**:
- `order_id` (required): Order ID
- `include_items` (optional): Include order items (true/false)

**Response**:
```json
{
  "success": true,
  "order": {
    "id": 456,
    "order_number": "ORD-2024-001",
    "customer_name": "John Doe",
    "customer_phone": "+1234567890",
    "total_amount": 25.98,
    "status": "preparing",
    "payment_status": "paid",
    "created_at": "2024-01-15 10:30:00",
    "items": [
      {
        "id": 1,
        "name": "Margherita Pizza",
        "quantity": 2,
        "price": 12.99,
        "total": 25.98,
        "notes": "Extra cheese"
      }
    ]
  }
}
```

### Update Order Status
**Endpoint**: `POST /api/update_order_status.php`

**Request**:
```json
{
  "action": "update_status",
  "order_id": 456,
  "status": "ready"
}
```

**Response**:
```json
{
  "success": true,
  "message": "Order status updated successfully",
  "new_status": "ready"
}
```

### Search Orders
**Endpoint**: `GET /api/search_orders.php`

**Parameters**:
- `query` (optional): Search term
- `status` (optional): Order status filter
- `date_from` (optional): Start date (YYYY-MM-DD)
- `date_to` (optional): End date (YYYY-MM-DD)
- `branch_id` (optional): Branch ID filter

**Response**:
```json
{
  "success": true,
  "orders": [
    {
      "id": 456,
      "order_number": "ORD-2024-001",
      "customer_name": "John Doe",
      "total_amount": 25.98,
      "status": "ready",
      "created_at": "2024-01-15 10:30:00"
    }
  ],
  "total_count": 1,
  "page": 1,
  "per_page": 20
}
```

## 📦 Stock Management APIs

### Get Items by Category
**Endpoint**: `GET /api/get_items.php`

**Parameters**:
- `category_id` (optional): Category ID filter
- `branch_id` (required): Branch ID
- `include_stock` (optional): Include stock quantities (true/false)

**Response**:
```json
{
  "success": true,
  "items": [
    {
      "id": 1,
      "name": "Margherita Pizza",
      "description": "Classic tomato and mozzarella pizza",
      "price": 12.99,
      "category_id": 1,
      "category_name": "Pizza",
      "stock_quantity": 50,
      "low_stock_threshold": 10,
      "is_available": true
    }
  ]
}
```

### Search Items
**Endpoint**: `GET /api/search_items.php`

**Parameters**:
- `query` (required): Search term
- `branch_id` (required): Branch ID
- `category_id` (optional): Category filter

**Response**:
```json
{
  "success": true,
  "items": [
    {
      "id": 1,
      "name": "Margherita Pizza",
      "price": 12.99,
      "stock_quantity": 50,
      "category_name": "Pizza"
    }
  ],
  "total_count": 1
}
```

### Create Stock Distribution
**Endpoint**: `POST /super_admin/stock_distributions.php`

**Request**:
```json
{
  "action": "create_distribution",
  "to_branch_id": 2,
  "items": [
    {
      "item_id": 1,
      "requested_quantity": 50,
      "unit_cost": 5.00
    }
  ],
  "notes": "Weekly stock replenishment"
}
```

**Response**:
```json
{
  "success": true,
  "distribution_id": 789,
  "distribution_number": "DIST-2024-001",
  "message": "Distribution created successfully"
}
```

### Approve Stock Distribution
**Endpoint**: `POST /super_admin/stock_distributions.php`

**Request**:
```json
{
  "action": "approve_distribution",
  "distribution_id": 789
}
```

**Response**:
```json
{
  "success": true,
  "message": "Distribution approved successfully",
  "distribution_status": "approved"
}
```

### Receive Stock Distribution
**Endpoint**: `POST /admin/received_stock.php`

**Request**:
```json
{
  "action": "receive_distribution",
  "distribution_id": 789,
  "received_items": [
    {
      "item_id": 1,
      "received_quantity": 48
    }
  ],
  "notes": "Received with 2 items damaged"
}
```

**Response**:
```json
{
  "success": true,
  "message": "Stock distribution received successfully"
}
```

## 💰 Revenue Reconciliation APIs

### Open Cash Drawer
**Endpoint**: `POST /admin/revenue_reconciliation.php`

**Request**:
```json
{
  "action": "open_cash_drawer",
  "opening_cash": 100.00
}
```

**Response**:
```json
{
  "success": true,
  "message": "Cash drawer opened successfully",
  "drawer_id": 123,
  "opening_cash": 100.00,
  "opened_at": "2024-01-15 09:00:00"
}
```

### Close Cash Drawer
**Endpoint**: `POST /admin/revenue_reconciliation.php`

**Request**:
```json
{
  "action": "close_cash_drawer",
  "drawer_id": 123,
  "actual_cash": 450.25,
  "notes": "All cash counted and verified"
}
```

**Response**:
```json
{
  "success": true,
  "message": "Cash drawer closed successfully",
  "expected_cash": 450.00,
  "actual_cash": 450.25,
  "variance": 0.25,
  "reconciliation_id": 456
}
```

### Get Drawer Status
**Endpoint**: `POST /admin/revenue_reconciliation.php`

**Request**:
```json
{
  "action": "get_drawer_status"
}
```

**Response**:
```json
{
  "success": true,
  "drawers": [
    {
      "id": 123,
      "user_id": 5,
      "user_name": "John Cashier",
      "opening_cash": 100.00,
      "status": "open",
      "opened_at": "2024-01-15 09:00:00"
    }
  ]
}
```

### Get Reconciliation Data
**Endpoint**: `POST /admin/revenue_reconciliation.php`

**Request**:
```json
{
  "action": "get_reconciliation_data",
  "start_date": "2024-01-01",
  "end_date": "2024-01-31"
}
```

**Response**:
```json
{
  "success": true,
  "reconciliations": [
    {
      "id": 456,
      "reconciliation_date": "2024-01-15",
      "total_sales": 450.00,
      "cash_sales": 200.00,
      "card_sales": 200.00,
      "digital_sales": 50.00,
      "expected_cash": 300.00,
      "actual_cash": 300.25,
      "cash_variance": 0.25,
      "status": "reconciled",
      "reconciled_by_name": "Branch Admin"
    }
  ]
}
```

### Force Reconcile (Super Admin)
**Endpoint**: `POST /super_admin/revenue_reconciliation.php`

**Request**:
```json
{
  "action": "force_reconcile",
  "reconciliation_id": 456
}
```

**Response**:
```json
{
  "success": true,
  "message": "Revenue reconciled successfully"
}
```

## 👥 Staff Scheduling APIs

### Get Branch Users
**Endpoint**: `POST /admin/shift_schedule.php`

**Request**:
```json
{
  "action": "get_branch_users"
}
```

**Response**:
```json
{
  "success": true,
  "users": [
    {
      "id": 5,
      "name": "John Staff",
      "role": "staff"
    },
    {
      "id": 6,
      "name": "Jane Cashier",
      "role": "cashier"
    }
  ]
}
```

### Create Shift Schedule
**Endpoint**: `POST /admin/shift_schedule.php`

**Request**:
```json
{
  "action": "create_schedule",
  "user_id": 5,
  "shift_date": "2024-01-20",
  "start_time": "09:00",
  "end_time": "17:00",
  "shift_type": "full_day",
  "notes": "Regular shift"
}
```

**Response**:
```json
{
  "success": true,
  "message": "Shift schedule created successfully"
}
```

### Get Schedules
**Endpoint**: `POST /admin/shift_schedule.php`

**Request**:
```json
{
  "action": "get_schedules",
  "start_date": "2024-01-20",
  "end_date": "2024-01-26"
}
```

**Response**:
```json
{
  "success": true,
  "schedules": [
    {
      "id": 789,
      "user_name": "John Staff",
      "shift_date": "2024-01-20",
      "start_time": "09:00",
      "end_time": "17:00",
      "shift_type": "full_day",
      "status": "scheduled",
      "actual_start_time": null,
      "actual_end_time": null,
      "total_hours": null
    }
  ]
}
```

### Mark Attendance
**Endpoint**: `POST /admin/shift_schedule.php`

**Request**:
```json
{
  "action": "mark_attendance",
  "schedule_id": 789,
  "action_type": "start"
}
```

**Response**:
```json
{
  "success": true,
  "message": "Attendance marked successfully"
}
```

### Update Schedule
**Endpoint**: `POST /admin/shift_schedule.php`

**Request**:
```json
{
  "action": "update_schedule",
  "schedule_id": 789,
  "start_time": "09:30",
  "end_time": "17:30",
  "shift_type": "full_day",
  "status": "confirmed",
  "notes": "Updated shift time"
}
```

**Response**:
```json
{
  "success": true,
  "message": "Schedule updated successfully"
}
```

### Delete Schedule
**Endpoint**: `POST /admin/shift_schedule.php`

**Request**:
```json
{
  "action": "delete_schedule",
  "schedule_id": 789
}
```

**Response**:
```json
{
  "success": true,
  "message": "Schedule deleted successfully"
}
```

## 👤 User Management APIs

### Get User Profile
**Endpoint**: `GET /api/get_user_profile.php`

**Parameters**:
- `user_id` (optional): User ID (defaults to current user)

**Response**:
```json
{
  "success": true,
  "user": {
    "id": 5,
    "name": "John Staff",
    "email": "john@example.com",
    "role": "staff",
    "branch_id": 1,
    "branch_name": "Main Branch",
    "hourly_rate": 15.00,
    "overtime_rate": 22.50,
    "is_active": true,
    "created_at": "2024-01-01 10:00:00"
  }
}
```

### Update User Profile
**Endpoint**: `POST /api/update_user_profile.php`

**Request**:
```json
{
  "action": "update_profile",
  "name": "John Updated",
  "email": "john.updated@example.com",
  "hourly_rate": 16.00,
  "overtime_rate": 24.00
}
```

**Response**:
```json
{
  "success": true,
  "message": "Profile updated successfully"
}
```

## 📊 Reporting APIs

### Get Sales Summary
**Endpoint**: `GET /api/get_sales_summary.php`

**Parameters**:
- `start_date` (required): Start date (YYYY-MM-DD)
- `end_date` (required): End date (YYYY-MM-DD)
- `branch_id` (optional): Branch ID filter

**Response**:
```json
{
  "success": true,
  "summary": {
    "total_orders": 150,
    "total_revenue": 3750.00,
    "average_order_value": 25.00,
    "payment_methods": {
      "cash": 1500.00,
      "card": 1800.00,
      "digital": 450.00
    },
    "daily_breakdown": [
      {
        "date": "2024-01-15",
        "orders": 25,
        "revenue": 625.00
      }
    ]
  }
}
```

### Get Inventory Report
**Endpoint**: `GET /api/get_inventory_report.php`

**Parameters**:
- `branch_id` (required): Branch ID
- `include_movements` (optional): Include stock movements (true/false)

**Response**:
```json
{
  "success": true,
  "inventory": [
    {
      "item_id": 1,
      "item_name": "Margherita Pizza",
      "current_stock": 45,
      "low_stock_threshold": 10,
      "last_movement": "2024-01-15 14:30:00",
      "movements": [
        {
          "type": "sale",
          "quantity": -2,
          "date": "2024-01-15 14:30:00"
        }
      ]
    }
  ]
}
```

### Get Labor Cost Report
**Endpoint**: `GET /api/get_labor_cost_report.php`

**Parameters**:
- `start_date` (required): Start date (YYYY-MM-DD)
- `end_date` (required): End date (YYYY-MM-DD)
- `branch_id` (optional): Branch ID filter

**Response**:
```json
{
  "success": true,
  "labor_costs": [
    {
      "user_id": 5,
      "user_name": "John Staff",
      "total_hours": 40.0,
      "regular_hours": 40.0,
      "overtime_hours": 0.0,
      "hourly_rate": 15.00,
      "regular_cost": 600.00,
      "overtime_cost": 0.00,
      "total_cost": 600.00
    }
  ],
  "summary": {
    "total_hours": 320.0,
    "total_cost": 4800.00,
    "average_hourly_rate": 15.00
  }
}
```

## ❌ Error Handling

### Standard Error Response Format
```json
{
  "success": false,
  "message": "Error description",
  "error_code": "ERROR_CODE",
  "details": {
    "field": "Additional error details"
  }
}
```

### Common Error Codes

#### Authentication Errors
- `UNAUTHORIZED` (401): User not logged in
- `FORBIDDEN` (403): Insufficient permissions
- `INVALID_SESSION` (401): Session expired or invalid

#### Validation Errors
- `VALIDATION_ERROR` (400): Input validation failed
- `MISSING_PARAMETER` (400): Required parameter missing
- `INVALID_FORMAT` (400): Invalid data format

#### Business Logic Errors
- `INSUFFICIENT_STOCK` (409): Not enough stock available
- `ORDER_NOT_FOUND` (404): Order does not exist
- `DRAWER_ALREADY_OPEN` (409): Cash drawer already open
- `SCHEDULE_CONFLICT` (409): Schedule conflict detected

#### System Errors
- `DATABASE_ERROR` (500): Database operation failed
- `FILE_ERROR` (500): File operation failed
- `INTERNAL_ERROR` (500): Internal server error

### Error Response Examples

#### Validation Error
```json
{
  "success": false,
  "message": "Validation failed",
  "error_code": "VALIDATION_ERROR",
  "details": {
    "opening_cash": "Opening cash amount is required",
    "user_id": "Invalid user ID format"
  }
}
```

#### Business Logic Error
```json
{
  "success": false,
  "message": "Insufficient stock available",
  "error_code": "INSUFFICIENT_STOCK",
  "details": {
    "item_id": 1,
    "requested_quantity": 10,
    "available_quantity": 5
  }
}
```

#### System Error
```json
{
  "success": false,
  "message": "Database connection failed",
  "error_code": "DATABASE_ERROR",
  "details": {
    "mysql_error": "Connection refused"
  }
}
```

## 🚦 Rate Limiting

### Current Implementation
- No rate limiting currently implemented
- Recommended: 100 requests per minute per user

### Future Implementation
```php
// Rate limiting middleware
function check_rate_limit($user_id, $endpoint) {
    $rate_limits = [
        'api/process_order.php' => 60,  // 60 requests per minute
        'api/search_items.php' => 120,  // 120 requests per minute
        'default' => 100                // 100 requests per minute
    ];
    
    $limit = $rate_limits[$endpoint] ?? $rate_limits['default'];
    
    // Check user's request count
    $request_count = get_user_request_count($user_id, $endpoint);
    
    if ($request_count >= $limit) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'message' => 'Rate limit exceeded',
            'error_code' => 'RATE_LIMIT_EXCEEDED',
            'details' => [
                'limit' => $limit,
                'reset_time' => time() + 60
            ]
        ]);
        exit();
    }
}
```

## 🔧 Integration Examples

### JavaScript/AJAX Integration
```javascript
// Create new order
function createOrder(orderData) {
    return fetch('/api/process_order.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(orderData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Order created:', data.order_id);
            return data;
        } else {
            throw new Error(data.message);
        }
    });
}

// Usage
const orderData = {
    action: 'create_order',
    items: [
        { item_id: 1, quantity: 2, price: 12.99 }
    ],
    customer_info: {
        name: 'John Doe',
        phone: '+1234567890'
    },
    payment_method_id: 1,
    cash_drawer_id: 123
};

createOrder(orderData)
    .then(result => {
        // Handle success
        displayOrderSuccess(result);
    })
    .catch(error => {
        // Handle error
        displayError(error.message);
    });
```

### PHP Integration
```php
// Process order via API
function processOrder($orderData) {
    $url = 'http://your-domain/almaida/api/process_order.php';
    
    $options = [
        'http' => [
            'header' => "Content-Type: application/json\r\n",
            'method' => 'POST',
            'content' => json_encode($orderData)
        ]
    ];
    
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    
    if ($result === FALSE) {
        throw new Exception('API request failed');
    }
    
    $response = json_decode($result, true);
    
    if (!$response['success']) {
        throw new Exception($response['message']);
    }
    
    return $response;
}
```

### cURL Integration
```bash
# Create order via cURL
curl -X POST http://your-domain/almaida/api/process_order.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "create_order",
    "items": [{"item_id": 1, "quantity": 2, "price": 12.99}],
    "customer_info": {"name": "John Doe", "phone": "+1234567890"},
    "payment_method_id": 1,
    "cash_drawer_id": 123
  }'
```

## 📝 API Versioning

### Current Version: v1
- Base URL: `/api/`
- All endpoints are version 1
- Backward compatibility maintained

### Future Versioning
- Version 2: `/api/v2/`
- Version header: `API-Version: 2.0`
- Deprecation notices in responses

## 🔒 Security Considerations

### Input Validation
- All inputs validated and sanitized
- SQL injection prevention via prepared statements
- XSS prevention via output escaping
- CSRF protection via tokens

### Data Protection
- Sensitive data encrypted in transit
- Database credentials secured
- Session management secure
- Access logs maintained

### Best Practices
- Use HTTPS in production
- Implement API key authentication
- Monitor API usage
- Regular security audits

---

**API Documentation Version**: 1.0  
**Last Updated**: January 2024  
**For**: Almaida POS System v2.0

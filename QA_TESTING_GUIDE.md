# 🧪 QA Testing Guide - Almaida POS System

This comprehensive testing guide ensures the Almaida POS System meets quality standards and functions correctly across all features and user roles.

## 📋 Testing Overview

### Testing Types
- **Functional Testing** - Core feature functionality
- **Integration Testing** - Component interactions
- **Performance Testing** - System performance under load
- **Security Testing** - Access control and data protection
- **Usability Testing** - User experience and interface
- **Compatibility Testing** - Cross-browser and device support

### Testing Environments
- **Development Environment** - Feature development and initial testing
- **Staging Environment** - Pre-production testing
- **Production Environment** - Live system validation

## 🚀 Automated Testing Scripts

### 1. Complete Feature Verification
**Script**: `verify_features.php`
**URL**: `http://your-domain/almaida/verify_features.php`

#### Test Coverage
- ✅ Database connection validation
- ✅ All required tables existence check
- ✅ Enhanced columns verification
- ✅ Default data integrity
- ✅ File access permissions
- ✅ Database views functionality
- ✅ API endpoint availability

#### Expected Results
```
✅ Database Connection: Connected successfully
✅ Table: cash_drawers: 0 records
✅ Table: payment_methods: 6 records
✅ Table: revenue_reconciliation: 0 records
✅ Table: shift_schedules: 0 records
✅ Table: shift_attendance: 0 records
✅ Table: shift_templates: 0 records
✅ Table: shift_template_assignments: 0 records
✅ Table: labor_costs: 0 records
✅ Orders table has payment_method_id column
✅ Orders table has cash_drawer_id column
✅ Users table has hourly_rate column
✅ Users table has overtime_rate column
✅ Payment Methods Data: 6 methods loaded
✅ File: admin/revenue_reconciliation.php: File exists
✅ File: admin/shift_schedule.php: File exists
✅ File: super_admin/revenue_reconciliation.php: File exists
✅ File: super_admin/shift_schedule.php: File exists
✅ Daily revenue summary view: 0 records
✅ Shift summary view: 0 records
✅ Labor cost summary view: 0 records
```

### 2. User Role Filtering Test
**Script**: `test_user_filtering.php`
**URL**: `http://your-domain/almaida/test_user_filtering.php`

#### Test Coverage
- ✅ Super admin users excluded from branch staff lists
- ✅ Branch-specific user filtering
- ✅ Role-based access control verification
- ✅ User count validation per branch

#### Test Cases
1. **All Users Display**
   - Shows complete user list with roles
   - Identifies super admin users
   - Shows branch assignments

2. **Branch Filtering**
   - Tests filtering for each branch
   - Verifies super admin exclusion
   - Validates active user filtering

3. **Role Verification**
   - Confirms role-based filtering
   - Tests user count accuracy
   - Validates permission boundaries

### 3. Complete Setup Verification
**Script**: `complete_setup.php`
**URL**: `http://your-domain/almaida/complete_setup.php`

#### Setup Steps (16 Total)
1. ✅ Creating cash_drawers table
2. ✅ Creating payment_methods table
3. ✅ Adding payment_method_id column to orders
4. ✅ Adding cash_drawer_id column to orders
5. ✅ Creating revenue_reconciliation table
6. ✅ Creating shift_schedules table
7. ✅ Creating shift_attendance table
8. ✅ Creating shift_templates table
9. ✅ Creating shift_template_assignments table
10. ✅ Creating labor_costs table
11. ✅ Adding hourly_rate column to users
12. ✅ Adding overtime_rate column to users
13. ✅ Inserting default payment methods
14. ✅ Creating performance indexes
15. ✅ Creating reporting views
16. ✅ Testing all tables

## 🔍 Manual Testing Procedures

### Order Processing Testing

#### Test Case 1: Basic Order Creation
**Objective**: Verify complete order processing workflow

**Steps**:
1. Navigate to cashier dashboard
2. Select items from menu
3. Add items to cart
4. Enter customer information
5. Select payment method
6. Process payment
7. Verify order appears in kitchen display
8. Check order status updates
9. Verify receipt generation

**Expected Results**:
- Order created successfully
- Items properly calculated
- Payment processed correctly
- Kitchen display shows order
- Receipt generated with correct information
- Inventory quantities updated

#### Test Case 2: Kitchen Display Integration
**Objective**: Test real-time kitchen order management

**Steps**:
1. Create multiple orders
2. Open kitchen display page
3. Verify orders appear in real-time
4. Update order status to "preparing"
5. Mark order as "ready"
6. Complete order
7. Verify status updates across system

**Expected Results**:
- Orders appear immediately in kitchen display
- Status updates reflect in real-time
- Order progression works correctly
- Completed orders are archived properly

#### Test Case 3: Payment Processing
**Objective**: Test various payment methods and cash drawer integration

**Steps**:
1. Open cash drawer with initial amount
2. Process cash payment
3. Process card payment
4. Process digital wallet payment
5. Verify payment method tracking
6. Close cash drawer
7. Check variance calculation

**Expected Results**:
- All payment methods process correctly
- Cash drawer tracks accurately
- Variance calculation is correct
- Payment reconciliation works

### Stock Management Testing

#### Test Case 4: Warehouse Stock Management
**Objective**: Test central warehouse inventory control

**Steps**:
1. Login as super admin
2. Navigate to warehouse stock management
3. Add new stock items
4. Update existing stock quantities
5. Set low stock thresholds
6. Test low stock alerts
7. Verify stock movement tracking

**Expected Results**:
- Stock items added successfully
- Quantities updated correctly
- Low stock alerts triggered appropriately
- Stock movements tracked accurately

#### Test Case 5: Stock Distribution Workflow
**Objective**: Test complete stock distribution process

**Steps**:
1. Branch admin requests stock distribution
2. Super admin reviews distribution request
3. Super admin approves distribution
4. Stock dispatched to branch
5. Branch admin receives and confirms stock
6. Verify stock quantities updated
7. Check distribution status tracking

**Expected Results**:
- Distribution request created successfully
- Approval process works correctly
- Stock quantities updated at both locations
- Status tracking accurate throughout process

### Revenue Reconciliation Testing

#### Test Case 6: Cash Drawer Management
**Objective**: Test complete cash drawer workflow

**Steps**:
1. Branch admin opens cash drawer
2. Record opening cash amount
3. Process multiple orders throughout day
4. Close cash drawer
5. Enter actual cash count
6. Verify variance calculation
7. Complete reconciliation
8. Check reporting accuracy

**Expected Results**:
- Cash drawer opens/closes correctly
- Variance calculation accurate
- Reconciliation process works
- Reports show correct data

#### Test Case 7: Multi-Branch Revenue Monitoring
**Objective**: Test super admin revenue oversight

**Steps**:
1. Login as super admin
2. Navigate to revenue reconciliation
3. View all branch reconciliations
4. Check branch comparison reports
5. Verify force reconciliation capability
6. Test variance analysis

**Expected Results**:
- All branch data visible to super admin
- Comparison reports accurate
- Force reconciliation works
- Variance analysis comprehensive

### Staff Scheduling Testing

#### Test Case 8: Shift Schedule Creation
**Objective**: Test staff scheduling functionality

**Steps**:
1. Login as branch admin
2. Navigate to shift schedule management
3. Create new shift schedule
4. Select staff member (verify only branch staff shown)
5. Set shift times and type
6. Save schedule
7. Verify schedule appears in list

**Expected Results**:
- Schedule created successfully
- Only branch staff shown in dropdown (no super admin)
- Schedule details saved correctly
- Schedule appears in schedule list

#### Test Case 9: Attendance Tracking
**Objective**: Test staff attendance management

**Steps**:
1. Staff member starts shift
2. Mark attendance start time
3. Take break (optional)
4. End break (optional)
5. End shift
6. Verify hours calculation
7. Check labor cost computation

**Expected Results**:
- Attendance tracking works correctly
- Hours calculated accurately
- Break time tracked properly
- Labor costs computed correctly

#### Test Case 10: Labor Cost Management
**Objective**: Test labor cost calculation and reporting

**Steps**:
1. Complete several shifts with different staff
2. Verify automatic labor cost calculation
3. Test overtime calculation
4. Review labor cost reports
5. Test approval workflow
6. Verify multi-branch labor cost overview

**Expected Results**:
- Labor costs calculated automatically
- Overtime detected and calculated
- Reports show accurate data
- Approval workflow functions
- Multi-branch overview comprehensive

## 🔒 Security Testing

### Authentication & Authorization

#### Test Case 11: Access Control
**Objective**: Verify role-based access control

**Steps**:
1. Test direct URL access without login
2. Login with different user roles
3. Attempt to access unauthorized pages
4. Test session timeout
5. Verify logout functionality

**Expected Results**:
- Unauthorized access blocked
- Role-based permissions enforced
- Session management works correctly
- Logout functions properly

#### Test Case 12: Data Security
**Objective**: Test data protection and validation

**Steps**:
1. Test SQL injection prevention
2. Test XSS prevention
3. Test input validation
4. Test output escaping
5. Verify data sanitization

**Expected Results**:
- SQL injection attempts blocked
- XSS attacks prevented
- Input validation working
- Output properly escaped
- Data sanitization effective

## ⚡ Performance Testing

### Load Testing

#### Test Case 13: Concurrent Users
**Objective**: Test system performance under load

**Tools**: Apache Bench (ab)
```bash
# Test main dashboard
ab -n 1000 -c 10 http://your-domain/almaida/admin/index.php

# Test API endpoints
ab -n 500 -c 5 http://your-domain/almaida/api/get_items.php

# Test order processing
ab -n 200 -c 5 -p order_data.txt http://your-domain/almaida/api/process_order.php
```

**Expected Results**:
- Response times under 2 seconds
- No server errors
- System remains stable
- Database performance acceptable

#### Test Case 14: Database Performance
**Objective**: Test database query performance

**SQL Tests**:
```sql
-- Test order queries
EXPLAIN SELECT * FROM orders WHERE branch_id = 1 AND created_at >= '2024-01-01';

-- Test stock queries
EXPLAIN SELECT * FROM stock_distributions WHERE status = 'pending';

-- Check index usage
SHOW INDEX FROM orders;
SHOW INDEX FROM stock_distributions;
```

**Expected Results**:
- Queries use appropriate indexes
- Response times acceptable
- No full table scans
- Database optimization effective

## 🌐 Compatibility Testing

### Browser Testing

#### Test Case 15: Cross-Browser Compatibility
**Objective**: Test system across different browsers

**Browsers to Test**:
- ✅ Chrome 80+
- ✅ Firefox 75+
- ✅ Safari 13+
- ✅ Edge 80+
- ✅ Mobile browsers (iOS Safari, Chrome Mobile)

**Test Areas**:
- Order processing interface
- Admin dashboards
- Kitchen display
- Mobile responsiveness
- Touch interactions

**Expected Results**:
- Consistent functionality across browsers
- Proper styling and layout
- JavaScript functions correctly
- Mobile interface usable

### Device Testing

#### Test Case 16: Responsive Design
**Objective**: Test system on different device sizes

**Device Sizes**:
- Desktop (1920x1080)
- Laptop (1366x768)
- Tablet (768x1024)
- Mobile (375x667)

**Test Areas**:
- Navigation menus
- Form inputs
- Data tables
- Button interactions
- Text readability

**Expected Results**:
- Layout adapts to screen size
- Touch targets appropriately sized
- Text remains readable
- Functionality preserved

## 🐛 Error Handling Testing

### Database Errors

#### Test Case 17: Database Connection Failure
**Objective**: Test graceful handling of database issues

**Steps**:
1. Simulate database connection failure
2. Test error message display
3. Verify system doesn't crash
4. Test recovery after connection restored

**Expected Results**:
- User-friendly error messages
- System remains stable
- Recovery works correctly
- No data corruption

#### Test Case 18: File Permission Errors
**Objective**: Test handling of file system issues

**Steps**:
1. Remove file permissions
2. Test error handling
3. Restore permissions
4. Verify system recovery

**Expected Results**:
- Clear error messages
- Guidance for resolution
- System recovers properly
- No permanent damage

## 📊 Data Integrity Testing

### Transaction Testing

#### Test Case 19: Order Processing Transaction
**Objective**: Test data consistency during transactions

**Steps**:
1. Start order processing
2. Simulate failure during processing
3. Verify rollback occurs
4. Check data consistency
5. Test successful completion

**Expected Results**:
- Failed transactions rollback completely
- Data remains consistent
- Successful transactions commit properly
- No partial updates

#### Test Case 20: Stock Distribution Transaction
**Objective**: Test inventory consistency during distributions

**Steps**:
1. Start stock distribution
2. Simulate failure during distribution
3. Verify inventory quantities unchanged
4. Test successful distribution
5. Verify quantities updated correctly

**Expected Results**:
- Failed distributions don't affect inventory
- Successful distributions update correctly
- No inventory discrepancies
- Audit trail maintained

## 🔄 Regression Testing

### Automated Regression Tests

#### Test Case 21: Feature Regression
**Objective**: Ensure new changes don't break existing functionality

**Areas to Test**:
- Order processing workflow
- Stock management operations
- Revenue reconciliation
- Staff scheduling
- User authentication

**Expected Results**:
- All existing features work correctly
- No new bugs introduced
- Performance maintained or improved
- User experience preserved

### Manual Regression Tests

#### Test Case 22: User Workflow Regression
**Objective**: Test complete user workflows after changes

**Workflows to Test**:
- Complete order processing cycle
- Stock distribution workflow
- Revenue reconciliation process
- Staff scheduling workflow
- Multi-branch operations

**Expected Results**:
- Workflows complete successfully
- No broken functionality
- User experience consistent
- Data integrity maintained

## 📋 Testing Checklist

### Pre-Testing Setup
- [ ] Test environment configured
- [ ] Test data prepared
- [ ] User accounts created for all roles
- [ ] Database backup created
- [ ] Testing tools available

### Core Functionality
- [ ] Order processing works
- [ ] Kitchen display updates
- [ ] Payment processing functions
- [ ] Receipt generation works
- [ ] Stock management operates
- [ ] Revenue reconciliation functions
- [ ] Staff scheduling works
- [ ] Attendance tracking functions

### Security & Access
- [ ] Authentication works
- [ ] Authorization enforced
- [ ] Session management functions
- [ ] Input validation works
- [ ] Output escaping functions
- [ ] SQL injection prevented
- [ ] XSS attacks blocked

### Performance & Compatibility
- [ ] System performs under load
- [ ] Database queries optimized
- [ ] Cross-browser compatibility
- [ ] Mobile responsiveness
- [ ] Error handling graceful
- [ ] Data integrity maintained

### Post-Testing Cleanup
- [ ] Test data cleaned up
- [ ] Test accounts disabled
- [ ] System restored to normal state
- [ ] Test results documented
- [ ] Issues reported and tracked

## 📊 Test Results Documentation

### Test Report Template
```
Test Case: [Test Case Name]
Date: [Test Date]
Tester: [Tester Name]
Environment: [Test Environment]

Objective:
[Test objective]

Steps Executed:
1. [Step 1]
2. [Step 2]
...

Expected Results:
[Expected outcomes]

Actual Results:
[Actual outcomes]

Status: [PASS/FAIL/BLOCKED]
Issues Found: [List any issues]
Recommendations: [Any recommendations]
```

### Issue Tracking
- **Critical**: System crashes, data loss, security vulnerabilities
- **High**: Major functionality broken, performance issues
- **Medium**: Minor functionality issues, usability problems
- **Low**: Cosmetic issues, minor improvements

## 🎯 Testing Success Criteria

### Functional Requirements
- ✅ All features work as specified
- ✅ User workflows complete successfully
- ✅ Data integrity maintained
- ✅ Performance meets requirements

### Quality Requirements
- ✅ System stable under normal load
- ✅ Error handling graceful
- ✅ Security measures effective
- ✅ User experience satisfactory

### Compatibility Requirements
- ✅ Works across supported browsers
- ✅ Responsive design functional
- ✅ Mobile interface usable
- ✅ Cross-platform compatibility

---

**Testing Guide Version**: 1.0  
**Last Updated**: January 2024  
**For**: Almaida POS System v2.0

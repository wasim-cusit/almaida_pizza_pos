# Stock Distribution Workflow

This document explains the enhanced stock distribution workflow implemented in the POS system.

## Overview

The stock distribution system allows super admins to distribute stock from the main warehouse to different branches, with proper tracking and branch admin confirmation of received goods.

## Workflow Steps

### 1. Super Admin Creates Distribution
- **Location**: Super Admin Panel → Stock Distributions
- **Action**: Create new distribution
- **Details**: Select branch, add items with quantities and unit costs
- **Status**: `pending`

### 2. Super Admin Approves Distribution
- **Action**: Approve the distribution
- **What happens**:
  - Warehouse stock is reduced
  - Distribution status changes to `approved`
  - Branch admin receives notification
- **Status**: `approved`

### 3. Super Admin Dispatches Goods
- **Action**: Mark goods as dispatched
- **What happens**:
  - Distribution status changes to `dispatched`
  - Branch admin receives notification that goods are on the way
- **Status**: `dispatched`

### 4. Branch Admin Receives Goods
- **Location**: Admin Panel → Received Stock
- **Actions available**:
  - View distribution details
  - Update received quantities for individual items
  - Mark entire distribution as received
- **What happens when received**:
  - Branch stock is updated
  - Stock movements are recorded
  - Distribution status changes to `received`
  - Super admin receives notification

## User Roles and Permissions

### Super Admin
- Create distributions
- Approve distributions
- Dispatch goods
- Mark as received (override capability)
- View all distributions across all branches

### Branch Admin
- View distributions assigned to their branch only
- Update received quantities
- Mark distributions as received
- Cannot create or approve distributions

## Database Tables

### Core Tables
- `stock_distributions` - Main distribution records
- `stock_distribution_items` - Individual items in each distribution
- `stock_movements` - Track all stock movements
- `warehouse_movements` - Track warehouse stock changes
- `notifications` - System notifications

### Key Fields
- `status`: pending → approved → dispatched → received
- `received_at`: Timestamp when goods were received
- `received_by`: User ID who confirmed receipt
- `approved_quantity`, `dispatched_quantity`, `received_quantity`: Track quantities at each stage

## API Endpoints

### Super Admin Endpoints
- `create_distribution` - Create new distribution
- `approve_distribution` - Approve distribution
- `dispatch_distribution` - Mark as dispatched
- `receive_distribution` - Mark as received (override)

### Branch Admin Endpoints
- `get_branch_distributions` - Get distributions for current branch
- `get_distribution_items` - Get items in specific distribution
- `receive_goods` - Mark goods as received
- `update_received_quantity` - Update individual item quantities

## Notifications

The system sends notifications at key workflow points:
- Distribution approved → Notify branch admin
- Goods dispatched → Notify branch admin
- Goods received → Notify super admin

## Installation

1. Run the database migration:
   ```sql
   SOURCE database_stock_receive_update.sql;
   ```

2. Ensure proper user roles are set:
   - Super admins should have `user_role = 'super_admin'`
   - Branch admins should have `user_role = 'admin'` and `branch_id` set

## Usage Examples

### Creating a Distribution (Super Admin)
1. Go to Super Admin Panel → Stock Distributions
2. Click "Create Distribution"
3. Select target branch
4. Add items from warehouse stock
5. Set quantities and unit costs
6. Save distribution

### Receiving Goods (Branch Admin)
1. Go to Admin Panel → Received Stock
2. View pending distributions
3. Click "View Details" to see items
4. Update received quantities if different from dispatched
5. Click "Mark as Received" to confirm

## Status Flow

```
pending → approved → dispatched → received
   ↓         ↓          ↓          ↓
 Created   Warehouse   Goods    Branch
          Stock      Shipped   Stock
          Reduced             Updated
```

## Error Handling

- Insufficient warehouse stock prevents approval
- Branch validation ensures admins only see their branch's distributions
- Transaction rollback on any errors
- Comprehensive error logging

## Future Enhancements

- Email notifications
- SMS alerts for urgent distributions
- Barcode scanning for item verification
- Mobile app for receiving goods
- Integration with delivery tracking systems

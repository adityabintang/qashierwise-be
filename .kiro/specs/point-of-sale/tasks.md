# Implementation Plan

## Phase 1: Database Foundation

- [x] 1. Create database migrations for POS tables






  - [x] 1.1 Create roles table migration

    - Define id, name, permissions (json), timestamps
    - _Requirements: 7.1_

  - [x] 1.2 Create stores table migration

    - Define id, name, code (unique), address, phone, is_active, timestamps
    - _Requirements: 5.1, 5.2_

  - [x] 1.3 Create pos_users table migration

    - Define id, user_id (FK), store_id (FK), role_id (FK), is_active, timestamps
    - _Requirements: 7.1_

  - [x] 1.4 Create tables table migration

    - Define id, store_id (FK), number, capacity, status (enum), timestamps
    - _Requirements: 6.1_

  - [x] 1.5 Create categories table migration

    - Define id, name, slug (unique), description, is_active, timestamps
    - _Requirements: 2.1_

  - [x] 1.6 Create products table migration

    - Define id, category_id (FK), name, sku (unique), description, price, stock_quantity, is_active, soft deletes, timestamps
    - _Requirements: 1.1, 1.3_

  - [x] 1.7 Create orders table migration

    - Define id, store_id (FK), table_id (FK nullable), pos_user_id (FK), order_number (unique), status (enum), subtotal, tax_amount, discount_amount, total, timestamps
    - _Requirements: 3.1_
  - [x] 1.8 Create order_items table migration


    - Define id, order_id (FK), product_id (FK), quantity, unit_price, subtotal, timestamps
    - _Requirements: 3.2_

  - [x] 1.9 Create payments table migration

    - Define id, order_id (FK), method (enum), amount, reference, metadata (json), timestamps
    - _Requirements: 4.2, 4.3_

## Phase 2: Eloquent Models

- [x] 2. Create Eloquent models with relationships



  - [x] 2.1 Create Role model


    - Define fillable, casts for permissions json
    - Add posUsers relationship
    - _Requirements: 7.1_

  - [x] 2.2 Create Store model

    - Define fillable, casts
    - Add tables, orders, posUsers relationships
    - _Requirements: 5.1_

  - [x] 2.3 Write property test for Store serialization round-trip

    - **Property 10: Store Serialization Round-Trip**
    - **Validates: Requirements 5.5, 5.6**
  - [x] 2.4 Create PosUser model


    - Define fillable, casts
    - Add user, store, role relationships
    - _Requirements: 7.1_


  - [x] 2.5 Write property test for PosUser serialization round-trip
    - **Property 14: Staff User Serialization Round-Trip**
    - **Validates: Requirements 7.5, 7.6**
  - [x] 2.6 Create Table model


    - Define fillable, casts, status constants
    - Add store, orders relationships
    - _Requirements: 6.1_


  - [x] 2.7 Write property test for Table serialization round-trip
    - **Property 13: Table Serialization Round-Trip**
    - **Validates: Requirements 6.5, 6.6**
  - [x] 2.8 Create Category model


    - Define fillable, casts

    - Add products relationship, slug generation
    - _Requirements: 2.1_
  - [x] 2.9 Write property test for Category serialization round-trip
    - **Property 2: Category Serialization Round-Trip**
    - **Validates: Requirements 2.5, 2.6**
  - [x] 2.10 Create Product model with SoftDeletes


    - Define fillable, casts
    - Add category, orderItems relationships
    - _Requirements: 1.1, 1.3_


  - [x] 2.11 Write property test for Product serialization round-trip
    - **Property 1: Product Serialization Round-Trip**
    - **Validates: Requirements 1.6, 1.7**
  - [x] 2.12 Create Order model


    - Define fillable, casts, status constants

    - Add store, table, posUser, items, payments relationships
    - _Requirements: 3.1_
  - [x] 2.13 Write property test for Order serialization round-trip
    - **Property 4: Order Serialization Round-Trip**
    - **Validates: Requirements 3.7, 3.8**
  - [x] 2.14 Create OrderItem model


    - Define fillable, casts
    - Add order, product relationships
    - _Requirements: 3.2_

  - [x] 2.15 Create Payment model

    - Define fillable, casts, method constants

    - Add order relationship
    - _Requirements: 4.2_

  - [x] 2.16 Write property test for Payment serialization round-trip

    - **Property 9: Payment Serialization Round-Trip**
    - **Validates: Requirements 4.6, 4.7**

- [x] 3. Checkpoint - Ensure all tests pass





  - Ensure all tests pass, ask the user if questions arise.
  - **Manual Testing Steps:**
    1. **Database Check:** Run `php artisan migrate` and verify tables created in database
    2. **Database Verification:** Use DB client (TablePlus/phpMyAdmin) to check:
       - `roles`, `stores`, `pos_users`, `tables`, `categories`, `products`, `orders`, `order_items`, `payments` tables exist
       - Foreign key constraints are properly set
    3. **Model Testing via Tinker:** Run `php artisan tinker` and test:
       ```php
       // Test Store model
       $store = \App\Models\Store::create(['name' => 'Test Store', 'code' => 'TST001', 'address' => 'Test Address', 'is_active' => true]);
       $store->toArray(); // Verify serialization
       
       // Test Category model
       $category = \App\Models\Category::create(['name' => 'Test Category', 'is_active' => true]);
       $category->slug; // Verify slug generation
       
       // Test Product model
       $product = \App\Models\Product::create(['category_id' => $category->id, 'name' => 'Test Product', 'price' => 10000, 'stock_quantity' => 100, 'is_active' => true]);
       $product->sku; // Verify SKU generation
       ```
    4. **Run PHPUnit Tests:** `php artisan test --filter=Property`

## Phase 3: Service Layer

- [x] 4. Implement ProductService






  - [x] 4.1 Create ProductService class

    - Implement create, update, delete methods
    - Implement SKU generation logic
    - Implement search functionality
    - _Requirements: 1.1, 1.2, 1.3, 1.4_

  - [x] 4.2 Write unit tests for ProductService

    - Test CRUD operations
    - Test SKU generation uniqueness
    - _Requirements: 1.1, 1.2_

- [x] 5. Implement CategoryService





  - [x] 5.1 Create CategoryService class


    - Implement create with slug generation
    - Implement delete with product check
    - _Requirements: 2.1, 2.4_

  - [x] 5.2 Write unit tests for CategoryService

    - Test slug generation
    - Test deletion prevention with products
    - _Requirements: 2.1, 2.4_

- [x] 6. Implement OrderService






  - [x] 6.1 Create OrderService class

    - Implement create with order number generation
    - Implement addItem, removeItem methods
    - Implement calculateTotals method
    - Implement applyDiscount method
    - _Requirements: 3.1, 3.2, 3.3, 3.4_

  - [x] 6.2 Write property test for Order total invariant

    - **Property 3: Order Total Invariant**
    - **Validates: Requirements 3.2, 3.3, 3.4**
  - [x] 6.3 Implement complete and cancel methods

    - Update inventory on completion
    - Restore inventory on cancellation
    - Update table status
    - _Requirements: 3.5, 3.6_

  - [x] 6.4 Write property test for inventory consistency

    - **Property 5: Inventory Consistency on Order Completion**
    - **Validates: Requirements 3.5**

  - [x] 6.5 Write property test for inventory restoration

    - **Property 6: Inventory Restoration on Order Cancellation**
    - **Validates: Requirements 3.6**

  - [x] 6.6 Write property test for table status transition

    - **Property 12: Table Status State Transition**
    - **Validates: Requirements 6.2, 6.3**

- [x] 7. Implement PaymentService




  - [x] 7.1 Create PaymentService class


    - Implement processPayment method
    - Implement calculateChange method
    - Implement splitPayment method
    - _Requirements: 4.2, 4.3, 4.4, 4.5_

  - [x] 7.2 Write property test for change calculation

    - **Property 7: Payment Change Calculation**
    - **Validates: Requirements 4.2**
  - [x] 7.3 Write property test for split payment invariant


    - **Property 8: Split Payment Total Invariant**
    - **Validates: Requirements 4.4, 4.5**

- [x] 8. Implement StoreService






  - [x] 8.1 Create StoreService class

    - Implement create, update, deactivate methods
    - Implement order creation validation for inactive stores
    - _Requirements: 5.1, 5.2, 5.3_
  - [x] 8.2 Write property test for inactive store order rejection


    - **Property 11: Inactive Store Order Rejection**
    - **Validates: Requirements 5.3**

- [x] 9. Implement ReportService






  - [x] 9.1 Create ReportService class

    - Implement dailySales method
    - Implement salesByRange method
    - Implement topProducts method
    - Implement store filtering
    - _Requirements: 8.1, 8.2, 8.3, 8.4_

  - [x] 9.2 Write property test for date range aggregation

    - **Property 15: Report Date Range Aggregation**
    - **Validates: Requirements 8.1, 8.2**

  - [x] 9.3 Write property test for store filter consistency

    - **Property 16: Report Store Filter Consistency**
    - **Validates: Requirements 8.3**

- [x] 10. Implement TransactionService





  - [x] 10.1 Create TransactionService class


    - Implement search by order number
    - Implement date filtering
    - _Requirements: 9.2, 9.3_

  - [x] 10.2 Write property test for transaction search accuracy

    - **Property 17: Transaction Search Accuracy**
    - **Validates: Requirements 9.2**

  - [x] 10.3 Write property test for transaction date filter

    - **Property 18: Transaction Date Filter Consistency**
    - **Validates: Requirements 9.3**

- [x] 11. Checkpoint - Ensure all tests pass





  - Ensure all tests pass, ask the user if questions arise.
  - **Manual Testing Steps:**
    1. **Run All Tests:** `php artisan test`
    2. **Test Services via Tinker:** Run `php artisan tinker`:
       ```php
       // Test ProductService
       $productService = app(\App\Services\ProductService::class);
       $product = $productService->create(['name' => 'Test', 'category_id' => 1, 'price' => 15000, 'stock_quantity' => 50]);
       
       // Test OrderService
       $orderService = app(\App\Services\OrderService::class);
       $order = $orderService->create(['store_id' => 1, 'pos_user_id' => 1]);
       $orderService->addItem($order, $product, 2);
       $orderService->calculateTotals($order); // Verify calculations
       
       // Test PaymentService
       $paymentService = app(\App\Services\PaymentService::class);
       $change = $paymentService->calculateChange(50000, 30000); // Should return 20000
       ```
    3. **Verify Inventory Updates:**
       ```php
       $initialStock = $product->stock_quantity;
       $orderService->complete($order);
       $product->refresh();
       // stock_quantity should be reduced by ordered quantity
       ```

## Phase 4: API Controllers

- [x] 12. Create API Controllers





  - [x] 12.1 Create ProductController


    - Implement index, store, show, update, destroy, search endpoints
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5_
  - [x] 12.2 Create CategoryController


    - Implement index, store, show, update, destroy endpoints
    - _Requirements: 2.1, 2.2, 2.3, 2.4_

  - [x] 12.3 Create OrderController

    - Implement index, store, show, addItem, removeItem, applyDiscount, complete, cancel endpoints
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6_

  - [x] 12.4 Create PaymentController

    - Implement store, splitPayment endpoints
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5_

  - [x] 12.5 Create StoreController

    - Implement index, store, show, update, deactivate endpoints
    - _Requirements: 5.1, 5.2, 5.3, 5.4_

  - [x] 12.6 Create TableController

    - Implement index, store, show, update, destroy endpoints
    - _Requirements: 6.1, 6.2, 6.3, 6.4_

  - [x] 12.7 Create PosUserController

    - Implement index, store, show, update, deactivate endpoints
    - _Requirements: 7.1, 7.2, 7.3_

  - [x] 12.8 Create ReportController

    - Implement dailySales, salesByRange, topProducts endpoints
    - _Requirements: 8.1, 8.2, 8.3, 8.4_

  - [x] 12.9 Create TransactionController

    - Implement index, show, search endpoints
    - _Requirements: 9.1, 9.2, 9.3, 9.4_

- [x] 13. Register API routes






  - [x] 13.1 Add POS API routes to routes/api.php

    - Group routes under /api/pos prefix
    - Apply auth:sanctum middleware
    - _Requirements: 10.1, 10.2_
  - **Manual API Testing with Postman:**
    1. **Setup:** Import collection or create requests manually
    2. **Authentication:** Get token via `POST /api/login` with credentials
    3. **Test Product API:**
       - `GET /api/pos/products` - List products (expect 200 with paginated data)
       - `POST /api/pos/products` - Create product (expect 201)
       - `GET /api/pos/products/{id}` - Get single product (expect 200)
       - `PUT /api/pos/products/{id}` - Update product (expect 200)
       - `DELETE /api/pos/products/{id}` - Soft delete (expect 200)
    4. **Test Order API:**
       - `POST /api/pos/orders` - Create order (expect 201 with order_number)
       - `POST /api/pos/orders/{id}/items` - Add item (expect 200 with updated totals)
       - `POST /api/pos/orders/{id}/complete` - Complete order (expect 200)
    5. **Test Payment API:**
       - `POST /api/pos/payments` - Process payment (expect 201)
       - Verify order status changes to 'paid' when payment >= total
    6. **Test Reports API:**
       - `GET /api/pos/reports/daily?date=2025-12-04` - Daily sales
       - `GET /api/pos/reports/range?start=2025-12-01&end=2025-12-04` - Range report

## Phase 5: Dashboard Views

- [x] 14. Update Dashboard Sidebar





  - [x] 14.1 Add POS menu items to sidebar


    - Add Orders, Payment under main section
    - Add Products, Categories under INVENTORY group
    - Add Stores, Tables, Users under OPERATIONS group
    - Add Reports, Transactions under ANALYTICS group
    - **IMPORTANT:** Only add new menu items, do not modify existing menu structure
    - _Requirements: 10.1, 10.2, 10.4_

  - [x] 14.2 Write property test for permission-based menu visibility

    - **Property 19: Permission-Based Menu Visibility**
    - **Validates: Requirements 10.3**
  - **Backward Compatibility Check:**
    1. Verify existing menu items (Dashboard, Contacts, Messages, Templates, Business Profile) still work
    2. Test sidebar collapse/expand functionality
    3. Test mobile hamburger menu

- [x] 15. Create Dashboard Views (Mobile-First, Responsive)





  - [x] 15.1 Create products.blade.php view


    - Display product list with pagination
    - Add create/edit modal (full-screen on mobile)
    - Add search functionality
    - Use card-based layout on mobile, table on desktop
    - _Requirements: 1.1, 1.2, 1.4, 1.5, 11.1, 11.5_
  - [x] 15.2 Create categories.blade.php view


    - Display category list with product counts
    - Add create/edit modal
    - Responsive grid layout
    - _Requirements: 2.1, 2.3, 11.1_

  - [x] 15.3 Create orders.blade.php view

    - Display order list with status
    - Add order creation interface
    - Touch-friendly item selection on mobile
    - _Requirements: 3.1, 3.2, 11.1_

  - [x] 15.4 Create payment.blade.php view

    - Display payment interface
    - Support multiple payment methods
    - Large touch targets for mobile
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 11.1_

  - [x] 15.5 Create stores.blade.php view

    - Display store list
    - Add create/edit modal
    - _Requirements: 5.1, 5.4, 11.1_

  - [x] 15.6 Create tables.blade.php view

    - Display table layout with status (grid view)
    - Add create/edit modal
    - Visual table status indicators
    - _Requirements: 6.1, 6.4, 11.1_

  - [x] 15.7 Create pos-users.blade.php view

    - Display staff user list
    - Add create/edit modal with role selection
    - _Requirements: 7.1, 7.2, 11.1_

  - [x] 15.8 Create reports.blade.php view

    - Display sales reports with charts
    - Add date range filter
    - Add store filter
    - Responsive charts (resize on viewport change)
    - _Requirements: 8.1, 8.2, 8.3, 8.4, 11.1, 11.4_

  - [x] 15.9 Create transactions.blade.php view

    - Display transaction history with pagination
    - Add search and date filter
    - Card-based on mobile, table on desktop
    - _Requirements: 9.1, 9.2, 9.3, 9.4, 11.1, 11.5_
  - **Responsive Testing for Each View:**
    1. Test on Chrome DevTools mobile emulator (iPhone SE, iPhone 12, Pixel 5)
    2. Test on tablet viewport (iPad, iPad Pro)
    3. Test on desktop (1920x1080, 1366x768)
    4. Verify touch interactions work on mobile
    5. Verify modals display correctly on all sizes

- [x] 16. Create Web Routes






  - [x] 16.1 Add POS web routes to routes/web.php

    - Add routes for all dashboard views
    - Apply auth middleware
    - _Requirements: 10.1, 10.2_

- [ ] 17. Final Checkpoint - Ensure all tests pass





  - Ensure all tests pass, ask the user if questions arise.
  - **Complete Manual Testing Checklist:**
  
  **A. Database Verification:**
  1. Open database client (TablePlus/phpMyAdmin/DBeaver)
  2. Verify all 9 POS tables exist with correct columns
  3. Check foreign key relationships are working
  4. Verify sample data is correctly stored
  
  **B. Backend API Testing (Postman):**
  1. Import Postman collection or test manually
  2. Test all CRUD endpoints for each entity
  3. Test order workflow: create → add items → payment → complete
  4. Test reports with date filters
  5. Test error cases (invalid data, unauthorized access)
  
  **C. Frontend Browser Testing:**
  1. Start server: `php artisan serve`
  2. Login to dashboard at `http://localhost:8000/dashboard`
  3. **Sidebar Navigation:**
     - Verify all POS menu items appear (Orders, Payment, Products, Categories, Stores, Tables, Users, Reports, Transactions)
     - Click each menu item and verify page loads
  4. **Products Page:**
     - View product list with pagination
     - Create new product via modal
     - Edit existing product
     - Delete product (soft delete)
     - Search products by name/SKU
  5. **Categories Page:**
     - View categories with product counts
     - Create/edit categories
     - Verify cannot delete category with products
  6. **Orders Page:**
     - Create new order
     - Add items to order
     - Apply discount
     - Complete order
     - Verify inventory decreases
  7. **Payment Page:**
     - Process cash payment (verify change calculation)
     - Process card payment
     - Test split payment
  8. **Stores & Tables:**
     - Create store
     - Add tables to store
     - Verify table status changes with orders
  9. **Reports Page:**
     - View daily sales report
     - Filter by date range
     - Filter by store
     - View top products
  10. **Transactions Page:**
      - View transaction history
      - Search by order number
      - Filter by date
  
  **D. Run All Automated Tests:**
  ```bash
  php artisan test
  php artisan test --filter=Property
  ```
  
  **E. Responsive Testing (All POS Pages):**
  1. Open Chrome DevTools (F12) → Toggle Device Toolbar (Ctrl+Shift+M)
  2. Test each POS page on:
     - **Mobile:** iPhone SE (375x667), iPhone 12 Pro (390x844)
     - **Tablet:** iPad (768x1024), iPad Pro (1024x1366)
     - **Desktop:** 1366x768, 1920x1080
  3. For each page verify:
     - Layout adapts correctly
     - No horizontal scroll on mobile
     - Touch targets are at least 44x44px
     - Modals display correctly
     - Tables convert to cards on mobile
  
  **F. Backward Compatibility Verification:**
  1. **Existing Pages Still Work:**
     - Navigate to `/dashboard` - verify dashboard loads
     - Navigate to `/dashboard/contacts` - verify contacts page works
     - Navigate to `/dashboard/messages` - verify messages work
     - Navigate to `/dashboard/templates` - verify templates work
     - Navigate to `/dashboard/profile` - verify profile works
  2. **Existing API Endpoints:**
     - Test `POST /api/login` - should work
     - Test `GET /api/whatsapp/contacts` - should work
     - Test `GET /api/whatsapp/messages` - should work
  3. **Database Integrity:**
     - Verify existing tables (users, whatsapp_*) are unchanged
     - Verify existing data is intact
  4. **No Console Errors:**
     - Open browser console on each existing page
     - Verify no JavaScript errors
     - Verify no 404 errors for assets

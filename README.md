# UMBC447-DOORDASH 🍕

A complete, full-stack food delivery platform built with PHP and MySQL. This project implements a multi-role system supporting customers, dashers, restaurant owners, and administrators with real-time order tracking and management.

---

## 🎯 Project Overview

UMBC447-DOORDASH is a comprehensive web application that simulates a food delivery service platform. The system provides complete functionality for:

- **Customers** - Browse restaurants, order food, track deliveries
- **Restaurant Owners** - Manage menu items and orders
- **Dashers** - Accept and deliver orders
- **Administrators** - Monitor system-wide activity

---

## ✨ Core Features

### 🛒 Shopping Cart System
- Add items to cart with custom quantities
- Update and remove items
- Persistent cart storage across sessions
- Real-time cart count badge
- Multi-restaurant cart support

### 📋 Order Management
- Complete checkout process with delivery address
- Multi-restaurant order splitting
- Order confirmation with tracking
- Order history for customers
- Real-time status updates

### 🍽️ Restaurant Dashboard
- View pending orders requiring action
- Accept or decline incoming orders
- Mark orders as preparing
- Mark orders as ready for pickup
- View active and completed order history

### 🚗 Delivery System
- Dasher availability toggle
- View available deliveries
- Accept delivery assignments
- Update order status (picked up, delivered)
- Track delivery completion

### 📊 Order Status Tracking
- 7 distinct order statuses:
  - **pending** ⏳ - Awaiting restaurant acceptance
  - **accepted** ✓ - Restaurant confirmed order
  - **preparing** 👨‍🍳 - Food being prepared
  - **ready** 🔔 - Ready for dasher pickup
  - **picked_up** 🚗 - Out for delivery
  - **delivered** ✓ - Successfully delivered
  - **cancelled** ✗ - Order declined or cancelled

- Visual progress tracker (6 steps)
- Mini tracker on customer dashboard
- Status icons for quick reference

### 👤 User Roles
- **Customer** - Browse, order, and track deliveries
- **Dasher** - Accept and complete deliveries
- **Restaurant Owner** - Manage orders and menu
- **Admin** - System-wide monitoring and management

---

## 🚀 Installation & Setup

### Prerequisites
- **XAMPP** (or similar LAMP/WAMP stack)
  - Apache 2.4+
  - MySQL 5.7+ or MariaDB
  - PHP 8.0+
- Modern web browser (Chrome, Firefox, Edge)

### Step 1: Install XAMPP
1. Download XAMPP from [apachefriends.org](https://www.apachefriends.org/)
2. Install XAMPP
3. Start Apache and MySQL from XAMPP Control Panel

### Step 2: Setup Project Files
1. Extract project files to:
   ```
   C:\xampp\htdocs\UMBC447-DOORDASH\
   ```

2. Verify file structure:
   ```
   UMBC447-DOORDASH/
   ├── index.php
   ├── config.php
   ├── dashboard.php
   ├── checkout.php
   ├── order-confirmation.php
   ├── restaurant-dashboard.php
   ├── (and all other PHP files)
   ├── style.css
   ├── script.js
   ├── schema.sql
   └── README.md
   ```

### Step 3: Create Database
1. Open phpMyAdmin: `http://localhost/phpmyadmin`
2. Click "Import" tab
3. Choose file: `schema.sql`
4. Click "Go"
5. Verify database `umbc447_doordash` was created with 7 tables:
   - users
   - restaurants
   - menu_items
   - orders
   - order_items
   - cart
   - dasher_availability

### Step 4: Create Admin Account
1. Navigate to: `http://localhost/UMBC447-DOORDASH/create-admin.php`
2. Admin account will be created with:
   - **Email:** admin@umbc447.com
   - **Password:** Admin123
   - **Role:** admin

### Step 5: Launch Application
1. Open browser and go to: `http://localhost/UMBC447-DOORDASH/`
2. Login with admin account or register new users

---

## 📖 User Guide

### For Customers

#### 1. Browse Restaurants
- Login as customer
- View list of available restaurants on dashboard
- Click any restaurant to view their menu

#### 2. Add Items to Cart
- Select quantity (1-99)
- Click "Add to Cart" button
- Cart icon appears showing item count
- Continue shopping or click cart icon

#### 3. Checkout
- Click cart icon or "View Cart"
- Review items and update quantities if needed
- Click "Proceed to Checkout"
- Enter delivery address (required)
- Add special instructions (optional)
- Click "Place Order"

#### 4. Track Your Order
- View order confirmation page
- See 6-step progress tracker
- Return to dashboard to see order status
- Watch status update in real-time:
  1. Order Placed ✓
  2. Restaurant Accepts
  3. Preparing Food
  4. Ready for Pickup
  5. Out for Delivery
  6. Delivered

### For Restaurant Owners

#### 1. Setup Restaurant Account
Restaurant accounts must be created manually in database:
```sql
-- Create restaurant
INSERT INTO restaurants (name, description, image_url)
VALUES ('Your Restaurant', 'Description', 'image.jpg');

-- Create user and link to restaurant
INSERT INTO users (name, email, password_hash, role, restaurant_id)
VALUES ('Owner Name', 'owner@email.com', '$2y$10$...', 'restaurant', 1);
```

#### 2. Manage Orders
- Login automatically redirects to restaurant dashboard
- **Pending Orders** section shows orders needing response
  - Click "Accept Order" to begin processing
  - Click "Decline Order" to reject
- **Active Orders** section shows accepted orders
  - Click "Start Preparing" when you begin cooking
  - Click "Mark as Ready" when order is complete
- **Completed Orders** table shows order history

#### 3. Order Workflow
```
New Order (pending) 
    ↓
Accept Order
    ↓
Start Preparing (preparing)
    ↓
Mark as Ready (ready)
    ↓
[Dasher picks up]
    ↓
[Order delivered]
```

### For Dashers

#### 1. Toggle Availability
- Login as dasher
- Click "Go Online" to start receiving orders
- Click "Go Offline" when done

#### 2. Accept Deliveries
- View "Available Deliveries" section
- See orders ready for pickup (status: ready)
- Click "Accept Delivery" to take order
- Order moves to "My Deliveries"

#### 3. Complete Delivery
- Click "Mark as Picked Up" after getting food
- Navigate to customer address
- Click "Mark as Delivered" after drop-off

### For Administrators

#### Login and Overview
- Login with admin credentials
- View system-wide statistics
- Monitor all orders from all restaurants
- See user accounts and activity

---

## 🔧 Configuration

### Database Configuration
Edit `config.php`:
```php
$host = "localhost";
$user = "root";
$pass = "";               // Default XAMPP password
$db   = "umbc447_doordash";
```

### URL Configuration
If not using default path, update all file links:
```php
// Current: /UMBC447-DOORDASH/
// Change to: /your-path/
header("Location: /your-path/dashboard.php");
```

---

## 📊 Database Schema

### Users Table
Stores all user accounts with roles
```sql
users (
  id, name, email, password_hash, 
  role (customer|dasher|admin|restaurant),
  restaurant_id, created_at
)
```

### Orders Table
Tracks all order information and status
```sql
orders (
  id, customer_id, restaurant_id, dasher_id,
  total_amount, status, delivery_address,
  notes, created_at, updated_at
)
```

### Order Items Table
Stores individual items in each order
```sql
order_items (
  id, order_id, menu_item_id,
  menu_item_name, quantity, price
)
```

### Cart Table
Persistent shopping cart storage
```sql
cart (
  id, customer_id, menu_item_id,
  quantity, added_at
)
```

See `schema.sql` for complete database structure.

---

## 🎨 Project Structure

```
UMBC447-DOORDASH/
│
├── Authentication
│   ├── index.php              # Login/register page
│   ├── login-simple.php       # Login handler
│   ├── register.php           # Registration handler
│   └── logout.php             # Logout handler
│
├── Customer Features
│   ├── dashboard.php          # Main dashboard (all roles)
│   ├── menu.php               # Restaurant menu view
│   ├── view-cart.php          # Shopping cart
│   ├── checkout.php           # Checkout process
│   └── order-confirmation.php # Order success page
│
├── Cart Management
│   ├── add-to-cart.php        # Add items to cart
│   ├── update-cart.php        # Update quantities
│   └── remove-from-cart.php   # Remove items
│
├── Restaurant Features
│   ├── restaurant-dashboard.php # Restaurant order management
│   └── restaurant-action.php    # Order action handler
│
├── Dasher Features
│   ├── accept-order.php         # Accept delivery
│   ├── update-order-status.php  # Update delivery status
│   └── update-availability.php  # Toggle online/offline
│
├── Configuration & Assets
│   ├── config.php             # Database configuration
│   ├── style.css              # All styling
│   ├── script.js              # JavaScript functions
│   └── schema.sql             # Database schema
│
└── Utilities
    ├── create-admin.php       # Admin account creator
    ├── session_boot.php       # Session handler
    └── session-test.php       # Session testing
```

---

## 🔐 Security Features

### Authentication & Authorization
- ✅ Session-based authentication
- ✅ Password hashing with PHP's `password_hash()`
- ✅ Role-based access control (RBAC)
- ✅ Session regeneration on login
- ✅ Protected pages require login

### Data Protection
- ✅ Prepared SQL statements (prevents SQL injection)
- ✅ XSS prevention with `htmlspecialchars()`
- ✅ Input validation and sanitization
- ✅ Database transactions for data integrity
- ✅ Status transition validation

### Order Security
- ✅ Customers can only view their own orders
- ✅ Restaurants can only manage their orders
- ✅ Dashers can only update assigned orders
- ✅ Order ownership verification on every action

---

## 🧪 Testing Guide

### Test Customer Workflow
1. **Register** as customer: customer@test.com / Test123
2. **Browse** restaurants from dashboard
3. **Add** 2 pizzas from Pizza Palace to cart
4. **Add** 3 burgers from Burger Barn to cart
5. **View cart** - verify 2 separate restaurant sections
6. **Checkout** - enter address "123 Test St, Baltimore, MD"
7. **Place order** - verify 2 separate orders created
8. **Check dashboard** - see orders with status "pending"

### Test Restaurant Workflow
1. **Create** restaurant owner account (see User Guide)
2. **Login** as restaurant owner
3. **View** pending order in dashboard
4. **Accept** order - status changes to "accepted"
5. **Start preparing** - status changes to "preparing"
6. **Mark ready** - status changes to "ready"
7. **Verify** order appears in dasher's available deliveries

### Test Dasher Workflow
1. **Register** as dasher: dasher@test.com / Test123
2. **Toggle** availability to "Online"
3. **View** available deliveries (status: ready)
4. **Accept** delivery - order assigned to you
5. **Mark picked up** - status changes to "picked_up"
6. **Mark delivered** - status changes to "delivered"
7. **Verify** customer sees delivered status

### Test Status Tracking
1. **Customer** places order → status: pending
2. **Restaurant** accepts → status: accepted
3. **Restaurant** prepares → status: preparing
4. **Restaurant** marks ready → status: ready
5. **Dasher** picks up → status: picked_up
6. **Dasher** delivers → status: delivered
7. **Verify** progress tracker shows all 6 steps complete

---

## 🐛 Troubleshooting

### Common Issues

**Issue:** Can't login / "Invalid credentials"
- **Solution:** Verify email and password are correct
- Run `create-admin.php` again to reset admin account
- Check `users` table in phpMyAdmin for account

**Issue:** Cart icon not showing
- **Solution:** Cart only shows when items exist
- Add at least one item to cart
- Clear browser cache (Ctrl + F5)

**Issue:** "Add to Cart" buttons missing
- **Solution:** Must be logged in as customer (not dasher/admin)
- Logout and login with customer account

**Issue:** Restaurant dashboard blank
- **Solution:** Restaurant owner account must have `restaurant_id` set
- Check database: `SELECT * FROM users WHERE role='restaurant'`
- Set restaurant_id: `UPDATE users SET restaurant_id=1 WHERE id=X`

**Issue:** Orders not appearing
- **Solution:** Check order status and user role
- Customers see their orders
- Restaurants see orders for their restaurant only
- Dashers see orders with status "ready"

**Issue:** Can't update order status
- **Solution:** Verify correct role and order ownership
- Check status transition is valid (e.g., can't skip from pending to ready)
- Verify user has permission for that order

**Issue:** Database connection failed
- **Solution:** Check MySQL is running in XAMPP
- Verify credentials in `config.php`
- Test connection in phpMyAdmin

**Issue:** Page shows PHP errors
- **Solution:** Check PHP error logs
- Verify all required files exist
- Check file permissions
- Ensure database tables exist

---

## 📱 Mobile Support

All pages are fully responsive and work on:
- ✅ Desktop (1920px+)
- ✅ Laptop (1366px - 1920px)
- ✅ Tablet (768px - 1366px)
- ✅ Mobile (320px - 768px)

Mobile optimizations include:
- Stacked layouts for forms
- Touch-friendly button sizes
- Responsive tables
- Horizontal scrolling for wide content
- Readable text at all sizes

---

## 🎓 Technical Details

### Technologies Used
- **Backend:** PHP 8.0+
- **Database:** MySQL 5.7+ / MariaDB
- **Frontend:** HTML5, CSS3, JavaScript
- **Server:** Apache 2.4+
- **Architecture:** MVC-inspired pattern

### Key Features Implementation

**Database Transactions**
```php
$conn->begin_transaction();
try {
    // Multiple database operations
    $stmt->execute();
    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
}
```

**Prepared Statements**
```php
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
```

**Password Security**
```php
$hash = password_hash($password, PASSWORD_DEFAULT);
password_verify($password, $hash);
```

**Session Management**
```php
session_start();
session_regenerate_id(true);
$_SESSION['user_id'] = $id;
```

---

## 🚀 Performance Optimization

### Database Optimization
- Indexed foreign keys
- Optimized JOIN queries
- Single query for cart count
- Efficient status filtering

### Caching Strategy
- Session-based user data caching
- Minimize database queries
- Efficient data retrieval

### Code Organization
- Reusable functions
- DRY principles
- Separation of concerns
- Clean code structure

---

## 🔮 Future Enhancements

### Potential Features
- [ ] Email notifications for status changes
- [ ] Real-time updates with WebSockets
- [ ] Payment gateway integration (Stripe/PayPal)
- [ ] Customer ratings and reviews
- [ ] Restaurant menu management interface
- [ ] Advanced search and filters
- [ ] Promo codes and discounts
- [ ] Order scheduling (future orders)
- [ ] Multiple delivery addresses
- [ ] Order history export
- [ ] Analytics dashboard for restaurants
- [ ] Push notifications
- [ ] Live chat support
- [ ] Driver location tracking (GPS)
- [ ] Estimated delivery time

---

## 📈 Project Statistics

- **Total Files:** 21 PHP files + 1 CSS + 1 JS + 1 SQL
- **Lines of Code:** 2,500+ lines
- **Database Tables:** 7 tables
- **User Roles:** 4 distinct roles
- **Order Statuses:** 7 status states
- **Features:** 20+ major features
- **Security Measures:** 10+ security implementations
- **Responsive Breakpoints:** 4 screen sizes

---

## 👥 User Roles Summary

| Role | Capabilities | Dashboard View |
|------|-------------|----------------|
| **Customer** | Browse, order, track | Restaurants + order history |
| **Dasher** | Accept deliveries, deliver | Available orders + my deliveries |
| **Restaurant** | Accept orders, prepare | Pending, active, completed orders |
| **Admin** | View all data, monitor | System-wide statistics |

---

## 📝 Order Status Reference

| Status | Icon | Meaning | Who Controls |
|--------|------|---------|--------------|
| pending | ⏳ | Awaiting restaurant | Restaurant |
| accepted | ✓ | Restaurant confirmed | Restaurant |
| preparing | 👨‍🍳 | Food being made | Restaurant |
| ready | 🔔 | Ready for pickup | Dasher |
| picked_up | 🚗 | Out for delivery | Dasher |
| delivered | ✓ | Successfully delivered | System |
| cancelled | ✗ | Order declined | System |

---

## 🤝 Contributing

This is a student project for UMBC447. If you'd like to extend it:

1. Fork the repository
2. Create a feature branch
3. Test thoroughly
4. Submit pull request with documentation

---

## 📄 License

This project is created for educational purposes as part of UMBC447 coursework.

---

## 📧 Support

For questions or issues:
- Check the Troubleshooting section above
- Review documentation in `/docs` folder
- Contact course instructor
- Post in course discussion board

---

## ✅ Deployment Checklist

Before deploying or submitting:
- [ ] All files in correct directory
- [ ] Database imported successfully
- [ ] Admin account created
- [ ] Can register new users
- [ ] Can place orders as customer
- [ ] Can manage orders as restaurant
- [ ] Can deliver as dasher
- [ ] Status tracking works
- [ ] Mobile view tested
- [ ] No PHP errors showing
- [ ] All links working
- [ ] Documentation complete

---

## 🎉 Acknowledgments

Developed for UMBC447 as a comprehensive full-stack web application demonstrating:
- Database design and implementation
- User authentication and authorization
- Role-based access control
- Order lifecycle management
- Real-time status tracking
- Responsive web design
- Security best practices
- Professional UI/UX design

---

## 📚 Additional Documentation

For more detailed information, see:
- `schema.sql` - Complete database structure
- Code comments in individual PHP files
- CSS class documentation in `style.css`

---

**Project Status:** ✅ Complete and Production Ready

**Last Updated:** November 2025

**Version:** 2.0 (with full order fulfillment and tracking)

---

*Built with ❤️ for UMBC447*

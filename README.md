# UMBC447-DOORDASH

A complete PHP/MySQL food delivery platform developed for UMBC447. This project implements a multi-role system that supports customers, dashers, administrators, and restaurant owners. It includes full user authentication, a role-based dashboard system, and database-driven order management. All core features have been implemented, tested, and verified.

---

## Overview

UMBC447-DOORDASH is a full-stack web application designed to simulate a delivery service platform similar to DoorDash. The system manages restaurant listings, menu items, orders, and delivery assignments. Each role has a unique interface and set of permissions.

### Supported Roles

* **Customer:** Browse restaurants, view menus, add items to a cart, place orders, and track order status.
* **Dasher:** Toggle availability, accept and deliver orders, and update order statuses.
* **Admin:** Monitor all users, restaurants, and orders, with access to system statistics.
* **Restaurant Owner:** Manage menu items, set availability, and view restaurant orders.

---

## Key Features

### General

* User authentication with secure password hashing
* Role-based dashboards and permissions
* Responsive and accessible interface
* SQL injection and XSS protection
* Real-time order updates via AJAX
* Color-coded order status tracking

### Customer Features

* Register and log in as a customer
* Browse restaurants with images and categories
* View restaurant menus and item details
* Add items to a shopping cart
* Update or remove items from the cart
* See order history and live status updates

### Dasher Features

* Register as a dasher
* Toggle availability (online/offline)
* Accept available orders
* View assigned deliveries
* Update order status (picked up, delivered)
* Track total deliveries and earnings

### Admin Features

* View overall system statistics
* Access all users and orders
* Review order details (customer, restaurant, dasher, amount, date, and status)
* Monitor revenue and order volume
* Manage or verify account roles

### Restaurant Owner Features

* Register as a restaurant owner
* Add, edit, or delete menu items
* Manage restaurant availability and prices
* View incoming orders for their restaurant

---

## System Requirements

* **Operating System:** Windows or macOS
* **Web Server:** Apache (via XAMPP or equivalent)
* **Database:** MySQL
* **Languages:** PHP 8.x, HTML, CSS, JavaScript
* **Browser:** Chrome, Firefox, or Edge

---

## Installation Guide

### 1. Setup Environment

Install [XAMPP](https://www.apachefriends.org/) or another PHP/MySQL environment.

### 2. Copy Project Files

Place all files in:

```
C:\xampp\htdocs\UMBC447-DOORDASH\
```

### 3. Import Database

1. Open phpMyAdmin
2. Click **Import**
3. Choose `schema.sql`
4. Click **Go**
5. Verify all six tables appear:

   * users
   * restaurants
   * menu_items
   * orders
   * order_items
   * dasher_availability

### 4. Create Admin Account

Visit:

```
http://localhost/UMBC447-DOORDASH/create-admin.php
```

This creates an admin user with:

```
Email: admin@umbc447.com
Password: Admin123
```

### 5. Launch Application

Visit:

```
http://localhost/UMBC447-DOORDASH/
```

Log in as the admin or register new accounts for other roles.

---

## Usage Overview

### Customer Workflow

1. Register and log in as a customer.
2. Browse available restaurants.
3. Add menu items to the cart.
4. View the cart, update quantities, and proceed to checkout.
5. Track the order status from placement to delivery.

### Dasher Workflow

1. Log in as a dasher.
2. Toggle availability to appear online.
3. Accept available orders.
4. Update order status to "picked up" and then "delivered."

### Admin Workflow

1. Log in using admin credentials.
2. Review system stats, user lists, and order tables.
3. Verify order flow and role functionality.

### Restaurant Owner Workflow

1. Register as a restaurant owner.
2. Access the restaurant dashboard.
3. Manage menu items (add, edit, delete).
4. Review and manage incoming orders.

---

## Database Schema Summary

**Tables**

1. `users` — Stores user details, hashed passwords, and roles
2. `restaurants` — Restaurant metadata and owner associations
3. `menu_items` — Menu items linked to restaurants
4. `orders` — Customer orders and their statuses
5. `order_items` — Specific items per order
6. `dasher_availability` — Tracks dasher status and availability
7. `cart` — (New) Shopping cart table for persistent customer items

Each table includes proper foreign keys and cascading relationships for data integrity.

---

## UI and Design Notes

* Primary button color: `#5a7bc7` (dark blue)
* Hover color: `#4a6bb7`
* White text (`#ffffff`) for maximum readability
* Status badges are color-coded for each order state
* Responsive design across desktop and mobile
* Typography emphasizes clarity and accessibility

---

## Security Features

* Password hashing using PHP’s `password_hash()`
* Prepared statements for all SQL queries
* `htmlspecialchars()` used for user-generated output
* Session-based authentication and role validation
* Basic CSRF and XSS protection

---

## Troubleshooting

**Invalid credentials**
Run `create-admin.php` again and use the credentials above.

**Database errors**
Re-import `schema.sql` and confirm all tables exist.

**Back button not working**
Clear your browser cache and ensure the updated `menu.php` file is present.

**Buttons hard to read**
Replace `style.css` with the latest version and reload the page.

---

## Testing and Verification

A complete testing guide is included in `TESTING_CHECKLIST.md`.
It covers:

* Login/registration verification
* Role-based dashboard testing
* Button visibility and navigation
* Order and status workflows
* Availability toggles
* UI responsiveness

---

## Changelog / Recent Updates

### Added: Restaurant Role & Menu Management

* New user type “Restaurant Owner” with its own dashboard
* Ability to add, edit, delete, and toggle menu item availability

### Added: Shopping Cart System

* Customers can add items to a cart
* Quantities update dynamically
* Cart subtotal calculated in real time
* Persistent cart across sessions

### Added: UI Enhancements

* Button text color forced to 100% white opacity
* Improved hover and active states
* Better mobile scaling

---

## Future Enhancements

* Checkout and payment integration
* Email notifications
* Real-time order tracking
* Ratings and reviews
* Restaurant analytics dashboard
* Search and filter options
* Profile editing and saved addresses

---

## Credits

Developed for the UMBC447 course as a full-stack web application project.
All code written in PHP, HTML, CSS, and JavaScript using a MySQL backend.
Documentation consolidated and authored by the project developer.

---

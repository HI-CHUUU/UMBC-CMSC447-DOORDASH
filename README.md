# UMBC Campus Delivery Platform

## Project Overview

This project is a full-stack web application designed to simulate a localized food delivery service specifically for the UMBC campus. The platform facilitates interactions between four distinct user roles: Customers, Dashers (delivery workers), Restaurant Owners, and Administrators.

The system is built using raw PHP and MySQL to demonstrate core backend concepts, including relational database design, session management, role-based access control (RBAC), and secure transaction handling. Unlike commercial alternatives, this platform enforces strict business logic to limit service to specific campus buildings and utilizes a simulated payment gateway suitable for an academic environment.

## Technical Architecture

* **Backend:** PHP 8.0+ (Procedural style with separation of concerns)
* **Database:** MySQL / MariaDB (InnoDB engine)
* **Frontend:** HTML5, CSS3, Vanilla JavaScript (AJAX for polling)
* **Environment:** XAMPP (Apache HTTP Server)

## Key Features

### Core Systems
* **Shopping Cart System:** Persistent cart storage allowing items from multiple restaurants with real-time total calculation.
* **Role-Based Access Control (RBAC):** Strict permission enforcement preventing unauthorized access to specific dashboards.
* **Security & Validation:** All user inputs are sanitized, and sensitive data (passwords) is hashed using `bcrypt` via `password_hash()`.

### User Verification Workflows
To ensure quality control and security, the platform implements approval queues for privileged roles:
* **Dashers:** New delivery workers can register but are placed in a "Pending" state. They cannot view or accept orders until an Administrator approves their account.
* **Restaurant Owners:** New owners must select their specific dining venue (e.g., "Chick-fil-A", "The Halal Shack") during registration. These accounts also start as "Pending" and require Admin approval.

### Advanced Order Management

* **Lifecycle State Machine:** Orders progress through defined states: Pending -> Accepted -> Preparing -> Ready -> Picked Up -> Delivered.
* **Time Tracking:** The system captures precise timestamps for every major event (Ordered At, Accepted At, Ready At, Picked Up At, Delivered At) to provide detailed history logs.
* **Auto-Refresh Dashboards:** The Customer, Dasher, and Admin dashboards automatically refresh (AJAX polling) to display live status updates without manual reloading.

### Restaurant Autonomy
* **Menu Management:** Restaurant owners have a dedicated interface to add new items, update prices, or remove items from their menu in real-time.
* **Order History:** A history log displays the last 20 completed orders, showing final status and delivery times for record-keeping.

### Campus-Specific Constraints
* **Location Validation:** Delivery addresses are validated against a strict allowlist of real UMBC residential and academic buildings (e.g., Patapsco Hall, ITE Building).
* **Real-World Data:** The database is seeded with actual UMBC dining venues and accurate menu pricing based on 2025 campus data.

## Installation and Setup

### Prerequisites
* XAMPP installed (Apache and MySQL services running).
* A modern web browser.

### Step 1: File Deployment
Extract the project files into your web server's document root.
* **Path:** `C:\xampp\htdocs\UMBC447-DOORDASH\`

### Step 2: Database Initialization
1.  Open phpMyAdmin (usually `http://localhost/phpmyadmin`).
2.  Import the provided `schema.sql` file.
3.  This script will:
    * Create the `umbc447_doordash` database.
    * Create all necessary tables (users, orders, restaurants, etc.).
    * Seed the database with 11 restaurants and their menus.

### Step 3: Admin Account Creation
Navigate to the setup script in your browser to generate the initial Administrator account.
* **URL:** `http://localhost/UMBC447-DOORDASH/create-admin.php`
* **Default Credentials:**
    * Email: `admin@umbc447.com`
    * Password: `Admin123`

## Usage Guide & Testing

### Testing Multiple Roles
**Important:** PHP sessions are shared across tabs in the same browser. To test interactions between multiple users simultaneously (e.g., a Customer placing an order and a Restaurant accepting it), you must use isolated environments.

* **Option A:** Use one browser for the Customer and a Private/Incognito window for the Admin/Restaurant.
* **Option B:** Use two different browsers (e.g., Chrome for Customer, Firefox for Dasher).

### Workflow Example
1.  **Registration:** A new user registers as a "Restaurant Owner" and claims "The Halal Shack".
2.  **Approval:** Log in as Admin. Navigate to the Admin Panel and locate the "Pending Restaurant Approvals" table. Click "Approve".
3.  **Menu Edit:** The approved Restaurant Owner logs in, scrolls to "Manage Menu," and adds a new item.
4.  **Ordering:** Log in as a Customer. Add the new item to the cart, select "Susquehanna Hall," and checkout.
5.  **Fulfillment:** The Restaurant Owner accepts the pending order and marks it as "Ready".
6.  **Delivery:** An approved Dasher toggles status to "Online," accepts the delivery, and marks it as "Delivered".
7.  **Verification:** The Customer Dashboard updates automatically to show the "Delivered" status and the final delivery timestamp.

## Troubleshooting

**Issue: "Weird Characters" or Glitchy Text**
* **Fix:** Ensure your browser is interpreting the page as UTF-8. This project forces `Content-Type: text/html; charset=utf-8` in `config.php` to handle emojis and special characters. Try a hard refresh (Ctrl+F5) to clear cached headers.

**Issue: Admin/Dasher Login Loops**
* **Fix:** PHP sessions cannot handle multiple users in the same browser tab context. You must use an Incognito window or a different browser to log in as a second user simultaneously.

**Issue: Database Connection Error**
* **Fix:** Open `config.php` and verify that the `$user` and `$pass` variables match your local XAMPP MySQL settings (Default user is usually `root` with no password).

## Implementation Notes & Constraints

### Directory Structure Requirement
This application uses absolute path routing for security. You must name the project folder `UMBC447-DOORDASH` inside your `htdocs` directory.
* **Correct:** `C:\xampp\htdocs\UMBC447-DOORDASH\`
* **Incorrect:** `C:\xampp\htdocs\my_project\`

If you change the folder name, you must manually update the path references in `session_boot.php` and redirect headers in all PHP files.

### Feature Scoping (Prototype Status)
As this is an academic prototype, certain features are simulated:
* **Notifications:** Alerts are "passive" (user must refresh or wait for the auto-refresh script) rather than using WebSockets for "active" push notifications.
* **Scheduling:** Worker availability is "On-Demand" (toggle switch) rather than a calendar-based future scheduling system.
* **Payments:** The credit card form is a UI simulation; sensitive financial data is never stored.

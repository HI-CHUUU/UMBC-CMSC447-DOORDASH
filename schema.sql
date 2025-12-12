/*
 * Database Schema for UMBC447-DOORDASH
 * * This script handles the initialization of the relational database structure.
 * * It enforces referential integrity via foreign keys and sets up the necessary
 * * enums for role-based access control (RBAC) and order lifecycle management.
 * * * * UPDATE 2025-12-11:
 * * - Refreshed Restaurant list to match actual UMBC Dining Services locations.
 * * - Updated Menu Items with realistic 2025 campus pricing.
 */

-- Initialize database with UTF-8 support for emoji/special char compatibility
CREATE DATABASE IF NOT EXISTS umbc447_doordash CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE umbc447_doordash;

-- 1. Restaurants Entity
CREATE TABLE IF NOT EXISTS restaurants (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  description TEXT,
  image_url VARCHAR(255),
  owner_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Users Entity
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('customer','dasher','admin','restaurant') NOT NULL DEFAULT 'customer',
  restaurant_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Menu Items Entity
CREATE TABLE IF NOT EXISTS menu_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  name VARCHAR(150) NOT NULL,
  description TEXT,
  price DECIMAL(10, 2) NOT NULL,
  image_url VARCHAR(255),
  category VARCHAR(50),
  available BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Dasher Availability
CREATE TABLE IF NOT EXISTS dasher_availability (
  id INT AUTO_INCREMENT PRIMARY KEY,
  dasher_id INT NOT NULL,
  is_available BOOLEAN DEFAULT FALSE,
  last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (dasher_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY unique_dasher (dasher_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Shopping Cart
CREATE TABLE IF NOT EXISTS cart (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  menu_item_id INT NOT NULL,
  quantity INT NOT NULL DEFAULT 1,
  added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE CASCADE,
  UNIQUE KEY unique_cart_item (customer_id, menu_item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Orders Entity
CREATE TABLE IF NOT EXISTS orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  restaurant_id INT NOT NULL,
  dasher_id INT NULL,
  total_amount DECIMAL(10, 2) NOT NULL,
  tip_amount DECIMAL(10, 2) DEFAULT 0.00,
  payment_status ENUM('paid', 'unpaid') DEFAULT 'unpaid',
  status ENUM('pending','accepted','preparing','ready','picked_up','delivered','cancelled') NOT NULL DEFAULT 'pending',
  delivery_address TEXT NOT NULL,
  estimated_delivery_time DATETIME NULL,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE,
  FOREIGN KEY (dasher_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Order Items (Line Items)
CREATE TABLE IF NOT EXISTS order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  menu_item_id INT NOT NULL,
  menu_item_name VARCHAR(150) NOT NULL,
  quantity INT NOT NULL DEFAULT 1,
  price DECIMAL(10, 2) NOT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Notifications Entity
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- DATA SEEDING (REAL UMBC RESTAURANTS)
-- =============================================

-- Seed Restaurants
INSERT INTO restaurants (name, description, image_url) VALUES
('Piccola Italia', 'Authentic Italian pizza, pasta, and subs. A campus favorite for hearty meals.', 'piccola.jpg'),
('Wild Greens', 'Fresh, made-to-order salads and wraps with premium ingredients.', 'wild-greens.jpg'),
('Dunkin''', 'America runs on Dunkin. Donuts, coffee, and breakfast sandwiches.', 'dunkin.jpg'),
('Sushi Do', 'Freshly rolled sushi, poke bowls, and Japanese sides.', 'sushi-do.jpg'),
('Copperhead Jacks', 'Tex-Mex burritos, bowls, and tacos with fresh salsas.', 'copperhead.jpg'),
('Absurd Bird Burgers', 'Crispy chicken tenders, wings, and smash burgers.', 'absurd-bird.jpg'),
('Indian Kitchen (Masala)', 'Traditional Indian curries, tandoori dishes, and naan.', 'indian-kitchen.jpg'),
('Einstein Bros. Bagels', 'Fresh-baked bagels, schmear, and egg sandwiches.', 'einstein.jpg'),
('Chick-fil-A', 'Original chicken sandwiches, nuggets, and waffle fries.', 'chick-fil-a.jpg'),
('Starbucks', 'Premium coffee, espressos, frappuccinos, and pastries.', 'starbucks.jpg'),
('The Halal Shack', 'Middle Eastern street food. Rice bowls, naanaritos, and fries.', 'halal-shack.jpg')
ON DUPLICATE KEY UPDATE name=name;

-- 1. Piccola Italia
INSERT INTO menu_items (restaurant_id, name, description, price, category, image_url) VALUES
(1, 'Cheese Pizza Slice', 'Classic NY style cheese slice.', 3.99, 'Pizza', 'pizza-slice.jpg'),
(1, 'Pepperoni Pizza Slice', 'Classic slice topped with pepperoni.', 4.59, 'Pizza', 'pep-slice.jpg'),
(1, 'Italian Sub', 'Ham, salami, provolone, lettuce, tomato, onions, and dressing.', 10.99, 'Subs', 'italian-sub.jpg'),
(1, 'Chicken Parm Sub', 'Breaded chicken cutlet with marinara and melted mozzarella.', 11.99, 'Subs', 'chick-parm.jpg'),
(1, 'Baked Ziti', 'Penne pasta baked with ricotta, mozzarella, and marinara.', 12.99, 'Pasta', 'ziti.jpg'),
(1, 'Cannoli', 'Crispy pastry shell filled with sweet ricotta cream.', 6.99, 'Dessert', 'cannoli.jpg');

-- 2. Wild Greens
INSERT INTO menu_items (restaurant_id, name, description, price, category, image_url) VALUES
(2, 'Create Your Own Salad', 'Choose your greens, 4 toppings, and dressing.', 10.99, 'Salads', 'byo-salad.jpg'),
(2, 'Chicken Caesar Salad', 'Romaine, grilled chicken, parmesan, croutons, caesar dressing.', 11.49, 'Salads', 'caesar.jpg'),
(2, 'Caprese Wrap', 'Fresh mozzarella, tomato, basil, balsamic glaze in a wrap.', 8.99, 'Wraps', 'caprese-wrap.jpg'),
(2, 'Southwest Chicken Salad', 'Greens, corn, black beans, chicken, tortilla strips, chipotle ranch.', 12.49, 'Salads', 'southwest.jpg');

-- 3. Dunkin\'
INSERT INTO menu_items (restaurant_id, name, description, price, category, image_url) VALUES
(3, 'Iced Coffee (Medium)', 'Freshly brewed iced coffee with your choice of flavor.', 3.79, 'Coffee', 'iced-coffee.jpg'),
(3, 'Glazed Donut', 'Classic soft, yeast donut with glaze.', 1.69, 'Donuts', 'glazed.jpg'),
(3, 'Boston Kreme Donut', 'Yeast shell with bavarian creme and chocolate icing.', 1.69, 'Donuts', 'boston-kreme.jpg'),
(3, 'Bacon Egg & Cheese Bagel', 'Bacon, egg, and cheese on a toasted plain bagel.', 5.29, 'Sandwiches', 'bec-bagel.jpg'),
(3, 'Munchkins (10 ct)', 'Assorted donut hole treats.', 4.49, 'Donuts', 'munchkins.jpg');

-- 4. Sushi Do
INSERT INTO menu_items (restaurant_id, name, description, price, category, image_url) VALUES
(4, 'California Roll', 'Crab stick, avocado, and cucumber.', 7.99, 'Rolls', 'cali-roll.jpg'),
(4, 'Spicy Tuna Roll', 'Minced tuna with spicy mayo and cucumber.', 8.99, 'Rolls', 'spicy-tuna.jpg'),
(4, 'Salmon Avocado Roll', 'Fresh salmon and avocado.', 9.49, 'Rolls', 'salmon-avo.jpg'),
(4, 'Poke Bowl', 'Tuna or Salmon over rice with edamame, seaweed salad, and sauce.', 13.99, 'Bowls', 'poke.jpg'),
(4, 'Shrimp Tempura Roll', 'Fried shrimp tempura with eel sauce.', 10.99, 'Rolls', 'shrimp-temp.jpg');

-- 5. Copperhead Jacks
INSERT INTO menu_items (restaurant_id, name, description, price, category, image_url) VALUES
(5, 'Chicken Burrito', 'Grilled chicken, rice, beans, salsa, and cheese in a flour tortilla.', 10.99, 'Burritos', 'chick-burrito.jpg'),
(5, 'Steak Bowl', 'Marinated steak served over rice and beans with toppings.', 12.49, 'Bowls', 'steak-bowl.jpg'),
(5, 'Veggie Nachos', 'Tortilla chips topped with queso, beans, salsa, and guacamole.', 10.49, 'Nachos', 'nachos.jpg'),
(5, 'Chips & Queso', 'Warm tortilla chips with a side of white queso.', 4.99, 'Sides', 'chips-queso.jpg');

-- 6. Absurd Bird Burgers
INSERT INTO menu_items (restaurant_id, name, description, price, category, image_url) VALUES
(6, '3pc Chicken Tenders', 'Crispy hand-breaded tenders with fries.', 10.99, 'Tenders', '3pc-tenders.jpg'),
(6, 'Classic Smash Burger', 'Beef patty, american cheese, lettuce, tomato, absurd sauce.', 9.99, 'Burgers', 'burger.jpg'),
(6, 'Chicken Sandwich', 'Fried chicken breast on a brioche bun with pickles.', 8.99, 'Sandwiches', 'chick-sand.jpg'),
(6, 'Traditional Wings (6pc)', 'Bone-in wings tossed in Buffalo or BBQ sauce.', 11.49, 'Wings', 'wings.jpg'),
(6, 'Seasoned Fries', 'Crispy fries with signature seasoning.', 4.49, 'Sides', 'fries.jpg');

-- 7. Indian Kitchen (Masala)
INSERT INTO menu_items (restaurant_id, name, description, price, category, image_url) VALUES
(7, 'Chicken Tikka Masala', 'Roasted chicken chunks in a creamy spiced tomato sauce. Served with rice.', 14.99, 'Entrees', 'tikka.jpg'),
(7, 'Butter Chicken', 'Tender chicken in a mild, buttery curry sauce. Served with rice.', 14.99, 'Entrees', 'butter-chick.jpg'),
(7, 'Vegetable Samosa (2pc)', 'Crispy pastry filled with spiced potatoes and peas.', 5.99, 'Appetizers', 'samosa.jpg'),
(7, 'Garlic Naan', 'Oven-baked flatbread topped with garlic and cilantro.', 3.99, 'Sides', 'naan.jpg'),
(7, 'Mango Lassi', 'Sweet yogurt-based mango drink.', 4.99, 'Drinks', 'lassi.jpg');

-- 8. Einstein Bros. Bagels
INSERT INTO menu_items (restaurant_id, name, description, price, category, image_url) VALUES
(8, 'Farmhouse Sandwich', 'Eggs, bacon, ham, cheddar, and pepper shmear on a hashbrown bagel.', 8.29, 'Sandwiches', 'farmhouse.jpg'),
(8, 'Bagel & Shmear', 'Fresh baked bagel with your choice of cream cheese.', 4.19, 'Bagels', 'bagel-shmear.jpg'),
(8, 'Nova Lox Sandwich', 'Smoked salmon, cream cheese, capers, onion, and tomato.', 11.99, 'Sandwiches', 'lox.jpg'),
(8, 'Blueberry Muffin', 'Freshly baked blueberry muffin.', 3.49, 'Sweets', 'muffin.jpg'),
(8, 'Cold Brew Coffee', 'Smooth cold brew coffee.', 4.29, 'Coffee', 'cold-brew.jpg');

-- 9. Chick-fil-A
INSERT INTO menu_items (restaurant_id, name, description, price, category, image_url) VALUES
(9, 'Chicken Sandwich', 'A boneless breast of chicken seasoned to perfection on a toasted buttered bun.', 5.99, 'Sandwiches', 'cfa-sand.jpg'),
(9, 'Chick-fil-A Nuggets (8ct)', 'Bite-sized pieces of boneless chicken breast.', 6.19, 'Entrees', 'nuggets.jpg'),
(9, 'Spicy Chicken Sandwich', 'Boneless breast of chicken seasoned with a spicy blend of peppers.', 6.49, 'Sandwiches', 'spicy-sand.jpg'),
(9, 'Waffle Potato Fries', 'Waffle-cut potatoes cooked in canola oil.', 3.29, 'Sides', 'fries.jpg'),
(9, 'Milkshake', 'Hand-spun milkshake (Chocolate, Vanilla, Strawberry).', 5.19, 'Treats', 'shake.jpg');
/*
 * Database Schema for UMBC447-DOORDASH
 * * FINAL VERSION
 * * Features Included:
 * * - Multi-role Users (Customer, Dasher, Admin, Restaurant)
 * * - Dasher Approval Workflow (is_approved column)
 * * - Financials (Tips, Mock Payment Status)
 * * - Real-time Tracking (Estimated Delivery Time)
 * * - Notification System
 * * - Real UMBC Dining Venues & Pricing
 */

-- Initialize database with UTF-8 support for emoji/special char compatibility
CREATE DATABASE IF NOT EXISTS umbc447_doordash CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE umbc447_doordash;

-- 1. Restaurants Entity
CREATE TABLE IF NOT EXISTS restaurants (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  description TEXT,
  image_url VARCHAR(255),
  owner_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Users Entity
-- Updated with is_approved for Dasher verification workflow
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('customer','dasher','admin','restaurant') NOT NULL DEFAULT 'customer',
  restaurant_id INT NULL,
  is_approved BOOLEAN DEFAULT TRUE, -- Customers/Owners auto-approved, Dashers must be approved by Admin
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Menu Items Entity
CREATE TABLE IF NOT EXISTS menu_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  name VARCHAR(150) NOT NULL,
  description TEXT,
  price DECIMAL(10, 2) NOT NULL,
  image_url VARCHAR(255),
  category VARCHAR(50),
  available BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Dasher Availability
CREATE TABLE IF NOT EXISTS dasher_availability (
  id INT AUTO_INCREMENT PRIMARY KEY,
  dasher_id INT NOT NULL,
  is_available BOOLEAN DEFAULT FALSE,
  last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (dasher_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY unique_dasher (dasher_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Shopping Cart
CREATE TABLE IF NOT EXISTS cart (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  menu_item_id INT NOT NULL,
  quantity INT NOT NULL DEFAULT 1,
  added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE CASCADE,
  UNIQUE KEY unique_cart_item (customer_id, menu_item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Orders Entity
-- Updated with Financials and ETA
CREATE TABLE IF NOT EXISTS orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  restaurant_id INT NOT NULL,
  dasher_id INT NULL,
  total_amount DECIMAL(10, 2) NOT NULL,
  tip_amount DECIMAL(10, 2) DEFAULT 0.00,
  payment_status ENUM('paid', 'unpaid') DEFAULT 'unpaid',
  status ENUM('pending','accepted','preparing','ready','picked_up','delivered','cancelled') NOT NULL DEFAULT 'pending',
  delivery_address TEXT NOT NULL,
  estimated_delivery_time DATETIME NULL,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE,
  FOREIGN KEY (dasher_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Order Items (Line Items)
CREATE TABLE IF NOT EXISTS order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  menu_item_id INT NOT NULL,
  menu_item_name VARCHAR(150) NOT NULL,
  quantity INT NOT NULL DEFAULT 1,
  price DECIMAL(10, 2) NOT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Notifications Entity
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- SEED DATA: REAL UMBC RESTAURANTS
-- =============================================

INSERT INTO restaurants (name, description, image_url) VALUES
('Piccola Italia', 'Authentic Italian pizza, pasta, and subs. A campus favorite for hearty meals.', 'piccola.jpg'),
('Wild Greens', 'Fresh, made-to-order salads and wraps with premium ingredients.', 'wild-greens.jpg'),
('Dunkin''', 'America runs on Dunkin. Donuts, coffee, and breakfast sandwiches.', 'dunkin.jpg'),
('Sushi Do', 'Freshly rolled sushi, poke bowls, and Japanese sides.', 'sushi-do.jpg'),
('Copperhead Jacks', 'Tex-Mex burritos, bowls, and tacos with fresh salsas.', 'copperhead.jpg'),
('Absurd Bird Burgers', 'Crispy chicken tenders, wings, and smash burgers.', 'absurd-bird.jpg'),
('Indian Kitchen (Masala)', 'Traditional Indian curries, tandoori dishes, and naan.', 'indian-kitchen.jpg'),
('Einstein Bros. Bagels', 'Fresh-baked bagels, schmear, and egg sandwiches.', 'einstein.jpg'),
('Chick-fil-A', 'Original chicken sandwiches, nuggets, and waffle fries.', 'chick-fil-a.jpg'),
('Starbucks', 'Premium coffee, espressos, frappuccinos, and pastries.', 'starbucks.jpg'),
('The Halal Shack', 'Middle Eastern street food. Rice bowls, naanaritos, and fries.', 'halal-shack.jpg')
ON DUPLICATE KEY UPDATE name=name;

-- 1. Piccola Italia
INSERT INTO menu_items (restaurant_id, name, description, price, category, image_url) VALUES
(1, 'Cheese Pizza Slice', 'Classic NY style cheese slice.', 3.99, 'Pizza', 'pizza-slice.jpg'),
(1, 'Pepperoni Pizza Slice', 'Classic slice topped with pepperoni.', 4.59, 'Pizza', 'pep-slice.jpg'),
(1, 'Italian Sub', 'Ham, salami, provolone, lettuce, tomato, onions, and dressing.', 10.99, 'Subs', 'italian-sub.jpg'),
(1, 'Chicken Parm Sub', 'Breaded chicken cutlet with marinara and melted mozzarella.', 11.99, 'Subs', 'chick-parm.jpg'),
(1, 'Baked Ziti', 'Penne pasta baked with ricotta, mozzarella, and marinara.', 12.99, 'Pasta', 'ziti.jpg'),
(1, 'Cannoli', 'Crispy pastry shell filled with sweet ricotta cream.', 6.99, 'Dessert', 'cannoli.jpg');

-- 2. Wild Greens
INSERT INTO menu_items (restaurant_id, name, description, price, category, image_url) VALUES
(2, 'Create Your Own Salad', 'Choose your greens, 4 toppings, and dressing.', 10.99, 'Salads', 'byo-salad.jpg'),
(2, 'Chicken Caesar Salad', 'Romaine, grilled chicken, parmesan, croutons, caesar dressing.', 11.49, 'Salads', 'caesar.jpg'),
(2, 'Caprese Wrap', 'Fresh mozzarella, tomato, basil, balsamic glaze in a wrap.', 8.99, 'Wraps', 'caprese-wrap.jpg'),
(2, 'Southwest Chicken Salad', 'Greens, corn, black beans, chicken, tortilla strips, chipotle ranch.', 12.49, 'Salads', 'southwest.jpg');

-- 3. Dunkin\'
INSERT INTO menu_items (restaurant_id, name, description, price, category, image_url) VALUES
(3, 'Iced Coffee (Medium)', 'Freshly brewed iced coffee with your choice of flavor.', 3.79, 'Coffee', 'iced-coffee.jpg'),
(3, 'Glazed Donut', 'Classic soft, yeast donut with glaze.', 1.69, 'Donuts', 'glazed.jpg'),
(3, 'Boston Kreme Donut', 'Yeast shell with bavarian creme and chocolate icing.', 1.69, 'Donuts', 'boston-kreme.jpg'),
(3, 'Bacon Egg & Cheese Bagel', 'Bacon, egg, and cheese on a toasted plain bagel.', 5.29, 'Sandwiches', 'bec-bagel.jpg'),
(3, 'Munchkins (10 ct)', 'Assorted donut hole treats.', 4.49, 'Donuts', 'munchkins.jpg');

-- 4. Sushi Do
INSERT INTO menu_items (restaurant_id, name, description, price, category, image_url) VALUES
(4, 'California Roll', 'Crab stick, avocado, and cucumber.', 7.99, 'Rolls', 'cali-roll.jpg'),
(4, 'Spicy Tuna Roll', 'Minced tuna with spicy mayo and cucumber.', 8.99, 'Rolls', 'spicy-tuna.jpg'),
(4, 'Salmon Avocado Roll', 'Fresh salmon and avocado.', 9.49, 'Rolls', 'salmon-avo.jpg'),
(4, 'Poke Bowl', 'Tuna or Salmon over rice with edamame, seaweed salad, and sauce.', 13.99, 'Bowls', 'poke.jpg'),
(4, 'Shrimp Tempura Roll', 'Fried shrimp tempura with eel sauce.', 10.99, 'Rolls', 'shrimp-temp.jpg');

-- 5. Copperhead Jacks
INSERT INTO menu_items (restaurant_id, name, description, price, category, image_url) VALUES
(5, 'Chicken Burrito', 'Grilled chicken, rice, beans, salsa, and cheese in a flour tortilla.', 10.99, 'Burritos', 'chick-burrito.jpg'),
(5, 'Steak Bowl', 'Marinated steak served over rice and beans with toppings.', 12.49, 'Bowls', 'steak-bowl.jpg'),
(5, 'Veggie Nachos', 'Tortilla chips topped with queso, beans, salsa, and guacamole.', 10.49, 'Nachos', 'nachos.jpg'),
(5, 'Chips & Queso', 'Warm tortilla chips with a side of white queso.', 4.99, 'Sides', 'chips-queso.jpg');

-- 6. Absurd Bird Burgers
INSERT INTO menu_items (restaurant_id, name, description, price, category, image_url) VALUES
(6, '3pc Chicken Tenders', 'Crispy hand-breaded tenders with fries.', 10.99, 'Tenders', '3pc-tenders.jpg'),
(6, 'Classic Smash Burger', 'Beef patty, american cheese, lettuce, tomato, absurd sauce.', 9.99, 'Burgers', 'burger.jpg'),
(6, 'Chicken Sandwich', 'Fried chicken breast on a brioche bun with pickles.', 8.99, 'Sandwiches', 'chick-sand.jpg'),
(6, 'Traditional Wings (6pc)', 'Bone-in wings tossed in Buffalo or BBQ sauce.', 11.49, 'Wings', 'wings.jpg'),
(6, 'Seasoned Fries', 'Crispy fries with signature seasoning.', 4.49, 'Sides', 'fries.jpg');

-- 7. Indian Kitchen (Masala)
INSERT INTO menu_items (restaurant_id, name, description, price, category, image_url) VALUES
(7, 'Chicken Tikka Masala', 'Roasted chicken chunks in a creamy spiced tomato sauce. Served with rice.', 14.99, 'Entrees', 'tikka.jpg'),
(7, 'Butter Chicken', 'Tender chicken in a mild, buttery curry sauce. Served with rice.', 14.99, 'Entrees', 'butter-chick.jpg'),
(7, 'Vegetable Samosa (2pc)', 'Crispy pastry filled with spiced potatoes and peas.', 5.99, 'Appetizers', 'samosa.jpg'),
(7, 'Garlic Naan', 'Oven-baked flatbread topped with garlic and cilantro.', 3.99, 'Sides', 'naan.jpg'),
(7, 'Mango Lassi', 'Sweet yogurt-based mango drink.', 4.99, 'Drinks', 'lassi.jpg');

-- 8. Einstein Bros. Bagels
INSERT INTO menu_items (restaurant_id, name, description, price, category, image_url) VALUES
(8, 'Farmhouse Sandwich', 'Eggs, bacon, ham, cheddar, and pepper shmear on a hashbrown bagel.', 8.29, 'Sandwiches', 'farmhouse.jpg'),
(8, 'Bagel & Shmear', 'Fresh baked bagel with your choice of cream cheese.', 4.19, 'Bagels', 'bagel-shmear.jpg'),
(8, 'Nova Lox Sandwich', 'Smoked salmon, cream cheese, capers, onion, and tomato.', 11.99, 'Sandwiches', 'lox.jpg'),
(8, 'Blueberry Muffin', 'Freshly baked blueberry muffin.', 3.49, 'Sweets', 'muffin.jpg'),
(8, 'Cold Brew Coffee', 'Smooth cold brew coffee.', 4.29, 'Coffee', 'cold-brew.jpg');

-- 9. Chick-fil-A
INSERT INTO menu_items (restaurant_id, name, description, price, category, image_url) VALUES
(9, 'Chicken Sandwich', 'A boneless breast of chicken seasoned to perfection on a toasted buttered bun.', 5.99, 'Sandwiches', 'cfa-sand.jpg'),
(9, 'Chick-fil-A Nuggets (8ct)', 'Bite-sized pieces of boneless chicken breast.', 6.19, 'Entrees', 'nuggets.jpg'),
(9, 'Spicy Chicken Sandwich', 'Boneless breast of chicken seasoned with a spicy blend of peppers.', 6.49, 'Sandwiches', 'spicy-sand.jpg'),
(9, 'Waffle Potato Fries', 'Waffle-cut potatoes cooked in canola oil.', 3.29, 'Sides', 'fries.jpg'),
(9, 'Milkshake', 'Hand-spun milkshake (Chocolate, Vanilla, Strawberry).', 5.19, 'Treats', 'shake.jpg');

-- 10. Starbucks
INSERT INTO menu_items (restaurant_id, name, description, price, category, image_url) VALUES
(10, 'Caffe Latte (Grande)', 'Dark, rich espresso balanced with steamed milk and a light layer of foam.', 5.75, 'Espresso', 'latte.jpg'),
(10, 'Caramel Macchiato (Grande)', 'Freshly steamed milk with vanilla-flavored syrup marked with espresso and caramel drizzle.', 6.25, 'Espresso', 'macchiato.jpg'),
(10, 'Frappuccino (Grande)', 'Blended coffee beverage (Mocha, Caramel, or Java Chip).', 6.75, 'Blended', 'frap.jpg'),
(10, 'Bacon & Gouda Sandwich', 'Sizzling bacon, cage-free egg and aged Gouda on an artisan roll.', 6.45, 'Food', 'bacon-gouda.jpg'),
(10, 'Cake Pop', 'Vanilla cake mixed with buttercream, dipped in pink chocolaty icing.', 3.95, 'Food', 'cakepop.jpg');

-- 11. The Halal Shack
INSERT INTO menu_items (restaurant_id, name, description, price, category, image_url) VALUES
(11, 'Chicken Rice Bowl', 'Marinated chicken over turmeric rice with lettuce, onions, and white sauce.', 12.99, 'Bowls', 'chicken-rice.jpg'),
(11, 'Beef Gyro Rice Bowl', 'Savory beef gyro over turmeric rice with fresh toppings and white sauce.', 13.99, 'Bowls', 'beef-bowl.jpg'),
(11, 'Falafel Rice Bowl', 'Crispy falafel over turmeric rice with hummus and tahini.', 12.49, 'Bowls', 'falafel-bowl.jpg'),
(11, 'Chicken Naanarito', 'Chicken and rice wrapped in fresh naan bread like a burrito.', 11.99, 'Wraps', 'naanarito.jpg'),
(11, 'Seasoned Fries', 'Crispy fries tossed in Halal Shack signature seasoning.', 4.99, 'Sides', 'fries.jpg');
-- 10. Starbucks
INSERT INTO menu_items (restaurant_id, name, description, price, category, image_url) VALUES
(10, 'Caffe Latte (Grande)', 'Dark, rich espresso balanced with steamed milk and a light layer of foam.', 5.75, 'Espresso', 'latte.jpg'),
(10, 'Caramel Macchiato (Grande)', 'Freshly steamed milk with vanilla-flavored syrup marked with espresso and caramel drizzle.', 6.25, 'Espresso', 'macchiato.jpg'),
(10, 'Frappuccino (Grande)', 'Blended coffee beverage (Mocha, Caramel, or Java Chip).', 6.75, 'Blended', 'frap.jpg'),
(10, 'Bacon & Gouda Sandwich', 'Sizzling bacon, cage-free egg and aged Gouda on an artisan roll.', 6.45, 'Food', 'bacon-gouda.jpg'),
(10, 'Cake Pop', 'Vanilla cake mixed with buttercream, dipped in pink chocolaty icing.', 3.95, 'Food', 'cakepop.jpg');

-- 11. The Halal Shack
INSERT INTO menu_items (restaurant_id, name, description, price, category, image_url) VALUES
(11, 'Chicken Rice Bowl', 'Marinated chicken over turmeric rice with lettuce, onions, and white sauce.', 12.99, 'Bowls', 'chicken-rice.jpg'),
(11, 'Beef Gyro Rice Bowl', 'Savory beef gyro over turmeric rice with fresh toppings and white sauce.', 13.99, 'Bowls', 'beef-bowl.jpg'),
(11, 'Falafel Rice Bowl', 'Crispy falafel over turmeric rice with hummus and tahini.', 12.49, 'Bowls', 'falafel-bowl.jpg'),
(11, 'Chicken Naanarito', 'Chicken and rice wrapped in fresh naan bread like a burrito.', 11.99, 'Wraps', 'naanarito.jpg'),
(11, 'Seasoned Fries', 'Crispy fries tossed in Halal Shack signature seasoning.', 4.99, 'Sides', 'fries.jpg');
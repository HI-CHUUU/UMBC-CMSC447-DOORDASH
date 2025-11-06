-- Create and use the database
CREATE DATABASE IF NOT EXISTS umbc447_doordash CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE umbc447_doordash;

-- 1. Restaurants table (No dependencies)
CREATE TABLE IF NOT EXISTS restaurants (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  description TEXT,
  image_url VARCHAR(255),
  owner_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Users table (Depends on 'restaurants')
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

-- 3. Menu items table (Depends on 'restaurants')
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

-- 4. Dasher availability table (Depends on 'users')
CREATE TABLE IF NOT EXISTS dasher_availability (
  id INT AUTO_INCREMENT PRIMARY KEY,
  dasher_id INT NOT NULL,
  is_available BOOLEAN DEFAULT FALSE,
  last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (dasher_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY unique_dasher (dasher_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Shopping cart table (Depends on 'users' and 'menu_items')
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

-- 6. Orders table (Depends on 'users' and 'restaurants')
CREATE TABLE IF NOT EXISTS orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  restaurant_id INT NOT NULL,
  dasher_id INT NULL,
  total_amount DECIMAL(10, 2) NOT NULL,
  status ENUM('pending','accepted','preparing','ready','picked_up','delivered','cancelled') NOT NULL DEFAULT 'pending',
  delivery_address TEXT NOT NULL,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE,
  FOREIGN KEY (dasher_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Order items table (Depends on 'orders' and 'menu_items')
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

-- Seed sample restaurants
INSERT INTO restaurants (name, description, image_url) VALUES
('Pizza Palace', 'Authentic Italian pizza with fresh ingredients', 'pizza-palace.jpg'),
('Burger Barn', 'Classic American burgers and fries', 'burger-barn.jpg'),
('Sushi Station', 'Fresh sushi and Japanese cuisine', 'sushi-station.jpg'),
('Taco Town', 'Mexican food with bold flavors', 'taco-town.jpg'),
('Pasta Paradise', 'Homemade pasta and Italian dishes', 'pasta-paradise.jpg')
ON DUPLICATE KEY UPDATE name=name;

-- Seed menu items for Pizza Palace
INSERT INTO menu_items (restaurant_id, name, description, price, category, image_url) VALUES
(1, 'Margherita Pizza', 'Classic tomato sauce, mozzarella, and basil', 12.99, 'Pizza', 'margherita.jpg'),
(1, 'Pepperoni Pizza', 'Loaded with pepperoni and cheese', 14.99, 'Pizza', 'pepperoni.jpg'),
(1, 'Veggie Supreme', 'Fresh vegetables with mozzarella', 13.99, 'Pizza', 'veggie.jpg'),
(1, 'Garlic Bread', 'Toasted bread with garlic butter', 5.99, 'Appetizer', 'garlic-bread.jpg')
ON DUPLICATE KEY UPDATE name=name;

-- Seed menu items for Burger Barn
INSERT INTO menu_items (restaurant_id, name, description, price, category, image_url) VALUES
(2, 'Classic Cheeseburger', 'Beef patty with cheese, lettuce, and tomato', 9.99, 'Burgers', 'cheeseburger.jpg'),
(2, 'Bacon Burger', 'Double bacon with cheese', 11.99, 'Burgers', 'bacon-burger.jpg'),
(2, 'Veggie Burger', 'Plant-based patty with fresh toppings', 10.99, 'Burgers', 'veggie-burger.jpg'),
(2, 'French Fries', 'Crispy golden fries', 3.99, 'Sides', 'fries.jpg'),
(2, 'Onion Rings', 'Beer-battered onion rings', 4.99, 'Sides', 'onion-rings.jpg')
ON DUPLICATE KEY UPDATE name=name;

-- Seed menu items for Sushi Station
INSERT INTO menu_items (restaurant_id, name, description, price, category, image_url) VALUES
(3, 'California Roll', 'Crab, avocado, and cucumber', 8.99, 'Rolls', 'california-roll.jpg'),
(3, 'Spicy Tuna Roll', 'Fresh tuna with spicy mayo', 10.99, 'Rolls', 'spicy-tuna.jpg'),
(3, 'Salmon Nigiri', 'Fresh salmon over rice (2 pieces)', 6.99, 'Nigiri', 'salmon-nigiri.jpg'),
(3, 'Miso Soup', 'Traditional Japanese soup', 3.99, 'Soup', 'miso-soup.jpg')
ON DUPLICATE KEY UPDATE name=name;

-- Seed menu items for Taco Town
INSERT INTO menu_items (restaurant_id, name, description, price, category, image_url) VALUES
(4, 'Beef Tacos', 'Three seasoned beef tacos', 8.99, 'Tacos', 'beef-tacos.jpg'),
(4, 'Chicken Quesadilla', 'Grilled chicken with melted cheese', 9.99, 'Quesadillas', 'chicken-quesadilla.jpg'),
(4, 'Guacamole & Chips', 'Fresh guacamole with tortilla chips', 6.99, 'Appetizers', 'guacamole.jpg'),
(4, 'Burrito Bowl', 'Rice, beans, meat, and toppings', 11.99, 'Bowls', 'burrito-bowl.jpg')
ON DUPLICATE KEY UPDATE name=name;

-- Seed menu items for Pasta Paradise
INSERT INTO menu_items (restaurant_id, name, description, price, category, image_url) VALUES
(5, 'Spaghetti Carbonara', 'Creamy sauce with bacon and parmesan', 13.99, 'Pasta', 'carbonara.jpg'),
(5, 'Fettuccine Alfredo', 'Rich and creamy alfredo sauce', 12.99, 'Pasta', 'alfredo.jpg'),
(5, 'Lasagna', 'Layers of pasta, meat, and cheese', 14.99, 'Pasta', 'lasagna.jpg'),
(5, 'Caesar Salad', 'Romaine lettuce with caesar dressing', 7.99, 'Salads', 'caesar-salad.jpg')
ON DUPLICATE KEY UPDATE name=name;
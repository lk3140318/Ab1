-- Create the database if it doesn't exist
CREATE DATABASE IF NOT EXISTS movie_site;
USE movie_site;

-- Table for storing movie/series posts
CREATE TABLE posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    image_url VARCHAR(512) NOT NULL, -- Can be URL or relative path if uploading
    link_480p VARCHAR(512),
    link_720p VARCHAR(512),
    link_1080p VARCHAR(512),
    view_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table for storing comments
CREATE TABLE comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    username VARCHAR(100) NOT NULL,
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE -- Delete comments if post is deleted
);

-- Table for admin users
CREATE TABLE admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL, -- Store hashed passwords!
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Optional: Create an initial admin user (Replace 'your_secure_password' with a strong password)
-- Generate the hash using PHP: password_hash('your_secure_password', PASSWORD_BCRYPT);
-- Example hash (DO NOT USE THIS EXACT HASH): $2y$10$exampleHashString...
-- INSERT INTO admin_users (username, password) VALUES ('admin', 'PASTE_BCRYPT_HASH_HERE');

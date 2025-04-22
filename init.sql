-- Create the noteapp database if it doesn't exist
CREATE DATABASE IF NOT EXISTS noteapp;

-- Create the users table
CREATE TABLE IF NOT EXISTS noteapp.users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    is_activated TINYINT DEFAULT 0,
    activation_token VARCHAR(255),
    reset_otp VARCHAR(6),
    reset_token VARCHAR(255),
    reset_expiry DATETIME,
    avatar VARCHAR(255),
    preferences JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create or update the noteapp_user
CREATE USER IF NOT EXISTS 'noteapp_user'@'%' IDENTIFIED WITH mysql_native_password BY 'YourStrong@Passw0rd';
GRANT ALL PRIVILEGES ON noteapp.* TO 'noteapp_user'@'%';
FLUSH PRIVILEGES;
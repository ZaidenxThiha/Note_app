CREATE TABLE users (
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
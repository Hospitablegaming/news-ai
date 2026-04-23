CREATE DATABASE news_ai;
USE news_ai;

CREATE TABLE roles(
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) UNIQUE NOT NULL,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO roles(role_name, description) VALUES
('member', 'Regular member'),
('admin', 'Administrator with full access');

CREATE TABLE users(
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50),
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    email VARCHAR(120),
    password_hash VARCHAR(255),
    profile_pic VARCHAR(255),
    role_id INT DEFAULT 1,
    subscription_id INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id)
);

CREATE TABLE prompts(
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    prompt TEXT,
    response LONGTEXT,
    is_public BOOLEAN DEFAULT 0,
    conversation_id INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE analytics(
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    tokens_used INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE subscriptions(
    id INT AUTO_INCREMENT PRIMARY KEY,
    plan_name VARCHAR(50),
    monthly_price DECIMAL(6,2),
    prompt_limit INT
);

INSERT INTO subscriptions(plan_name, monthly_price, prompt_limit) VALUES
('Free', 0, 10),
('Student', 5, 100),
('Researcher', 15, 500);
CREATE TABLE password_resets(
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(120) NOT NULL,
    verification_code VARCHAR(6) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP DEFAULT (CURRENT_TIMESTAMP + INTERVAL 15 MINUTE),
    verified BOOLEAN DEFAULT 0
);

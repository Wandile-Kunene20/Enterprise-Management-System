-- Create database
CREATE DATABASE IF NOT EXISTS enterprise_system;
USE enterprise_system;

-- Users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'manager', 'employee') NOT NULL,
    department VARCHAR(50),
    position VARCHAR(50),
    hire_date DATE,
    profile_image VARCHAR(255),
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
);

-- Departments table
CREATE TABLE departments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    description TEXT,
    manager_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Projects table
CREATE TABLE projects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    manager_id INT NOT NULL,
    department_id INT,
    start_date DATE,
    end_date DATE,
    status ENUM('planning', 'ongoing', 'completed', 'on_hold') DEFAULT 'planning',
    budget DECIMAL(15,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tasks table
CREATE TABLE tasks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    project_id INT,
    assigned_to INT,
    assigned_by INT,
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    status ENUM('pending', 'in_progress', 'completed', 'deferred') DEFAULT 'pending',
    due_date DATE,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Attendance table
CREATE TABLE attendance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    date DATE NOT NULL,
    check_in TIME,
    check_out TIME,
    total_hours DECIMAL(5,2),
    status ENUM('present', 'absent', 'late', 'leave', 'half_day') DEFAULT 'present',
    notes TEXT,
    UNIQUE KEY unique_attendance (user_id, date)
);

-- Insert default admin user (password: Admin@123)
INSERT INTO users (username, password, email, full_name, role, department, position, hire_date) 
VALUES (
    'admin',
    'Admin@123', -- Use password_hash('Admin@123', PASSWORD_DEFAULT) in PHP
    'admin@enterprise.com',
    'System Administrator',
    'admin',
    'IT',
    'System Admin',
    CURDATE()
);

-- Insert default manager
INSERT INTO users (username, password, email, full_name, role, department, position, hire_date) 
VALUES (
    'manager',
    'Manager@123', -- Use password_hash('Manager@123', PASSWORD_DEFAULT)
    'manager@enterprise.com',
    'Department Manager',
    'manager',
    'Operations',
    'Operations Manager',
    CURDATE()
);

-- Insert default employee
INSERT INTO users (username, password, email, full_name, role, department, position, hire_date) 
VALUES (
    'employee',
    'Employee@123', -- Use password_hash('Employee@123', PASSWORD_DEFAULT)
    'employee@enterprise.com',
    'John Doe',
    'employee',
    'Sales',
    'Sales Executive',
    CURDATE()
);

-- Insert sample departments
INSERT INTO departments (name, description, manager_id) VALUES
('IT', 'Information Technology Department', 1),
('Operations', 'Operations and Management', 2),
('Sales', 'Sales and Marketing', NULL),
('HR', 'Human Resources', NULL),
('Finance', 'Finance and Accounting', NULL);

-- Insert sample projects
INSERT INTO projects (name, description, manager_id, department_id, start_date, end_date, status, budget) VALUES
('Website Redesign', 'Complete website redesign project', 2, 1, '2024-01-01', '2024-06-30', 'ongoing', 50000.00),
('Sales Campaign Q1', 'Quarter 1 sales campaign', 2, 3, '2024-01-15', '2024-03-31', 'ongoing', 25000.00),
('HR System Implementation', 'New HR management system', 2, 4, '2024-02-01', '2024-12-31', 'planning', 75000.00);

-- Insert sample tasks
INSERT INTO tasks (title, description, project_id, assigned_to, assigned_by, priority, status, due_date) VALUES
('Design Homepage', 'Create new homepage design', 1, 3, 2, 'high', 'in_progress', '2024-02-15'),
('Contact Clients', 'Reach out to potential clients', 2, 3, 2, 'medium', 'pending', '2024-02-10'),
('System Requirements', 'Gather HR system requirements', 3, 1, 2, 'low', 'pending', '2024-02-28');  



-- Add phone, address, and date_of_birth to users table if not exists
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS phone VARCHAR(20),
ADD COLUMN IF NOT EXISTS address TEXT,
ADD COLUMN IF NOT EXISTS date_of_birth DATE;

-- Attendance table (already exists, but adding sample data)
INSERT INTO attendance (user_id, date, check_in, check_out, total_hours, status) VALUES
(3, CURDATE(), '09:00:00', '17:00:00', 8.0, 'present');

-- Add actual_cost column to projects table if not exists
ALTER TABLE projects 
ADD COLUMN IF NOT EXISTS actual_cost DECIMAL(15,2);

-- Reports table: store employee-submitted reports
CREATE TABLE IF NOT EXISTS reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(255),
    body TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_reports_user (user_id),
    CONSTRAINT fk_reports_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
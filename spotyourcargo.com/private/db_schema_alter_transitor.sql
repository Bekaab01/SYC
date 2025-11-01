-- Migration to add 'transitor' to user_type ENUM in users table
-- Run this SQL on your database to update the schema

ALTER TABLE users MODIFY COLUMN user_type ENUM('carrier', 'shipper', 'transitor', 'admin') NOT NULL DEFAULT 'shipper';

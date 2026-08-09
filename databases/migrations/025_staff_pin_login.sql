-- Active: 1785849373366@@127.0.0.1@3306@2in1_db
-- 025_staff_pin_login.sql
-- Staff log in with a short PIN at a shared terminal instead of email +
-- password. Owners are unaffected (still email + password).

ALTER TABLE users
    ADD COLUMN pin_hash VARCHAR(255) NULL AFTER password_hash,
    ADD COLUMN position VARCHAR(100) NULL AFTER pin_hash;

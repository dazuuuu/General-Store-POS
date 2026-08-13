-- Active: 1785849373366@@127.0.0.1@3306@duaqabe_db
-- Store the buying cost of a whole package/carton separately from the
-- per-content buying cost used for retail piece/litre/kg sales.

ALTER TABLE products
    ADD COLUMN package_buying_price DECIMAL(12,2) NULL AFTER pack_price;

ALTER TABLE store_products
    ADD COLUMN package_buying_price DECIMAL(12,2) NULL AFTER buying_price;

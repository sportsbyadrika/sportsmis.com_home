-- Idempotent: adds Razorpay (ePayment) columns to the existing
-- event_registration_payments transactions table.
-- Safe to paste into phpMyAdmin → SQL and re-run.

DROP PROCEDURE IF EXISTS sportsmis_add_razorpay_columns;
DELIMITER $$
CREATE PROCEDURE sportsmis_add_razorpay_columns()
BEGIN
    DECLARE has_col INT DEFAULT 0;
    DECLARE has_idx INT DEFAULT 0;

    SELECT COUNT(*) INTO has_col FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE()
       AND TABLE_NAME  ='event_registration_payments'
       AND COLUMN_NAME ='payment_method';
    IF has_col = 0 THEN
        ALTER TABLE event_registration_payments
          ADD COLUMN payment_method ENUM('manual','epayment') NOT NULL DEFAULT 'manual' AFTER status;
    END IF;

    SELECT COUNT(*) INTO has_col FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE()
       AND TABLE_NAME  ='event_registration_payments'
       AND COLUMN_NAME ='razorpay_order_id';
    IF has_col = 0 THEN
        ALTER TABLE event_registration_payments
          ADD COLUMN razorpay_order_id VARCHAR(255) NULL AFTER payment_method;
    END IF;

    SELECT COUNT(*) INTO has_col FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE()
       AND TABLE_NAME  ='event_registration_payments'
       AND COLUMN_NAME ='razorpay_payment_id';
    IF has_col = 0 THEN
        ALTER TABLE event_registration_payments
          ADD COLUMN razorpay_payment_id VARCHAR(255) NULL AFTER razorpay_order_id;
    END IF;

    SELECT COUNT(*) INTO has_col FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE()
       AND TABLE_NAME  ='event_registration_payments'
       AND COLUMN_NAME ='razorpay_signature';
    IF has_col = 0 THEN
        ALTER TABLE event_registration_payments
          ADD COLUMN razorpay_signature VARCHAR(512) NULL AFTER razorpay_payment_id;
    END IF;

    SELECT COUNT(*) INTO has_idx FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA=DATABASE()
       AND TABLE_NAME  ='event_registration_payments'
       AND INDEX_NAME  ='uq_rzp_order';
    IF has_idx = 0 THEN
        ALTER TABLE event_registration_payments
          ADD UNIQUE KEY uq_rzp_order (razorpay_order_id);
    END IF;
END$$
DELIMITER ;

CALL sportsmis_add_razorpay_columns();
DROP PROCEDURE sportsmis_add_razorpay_columns;

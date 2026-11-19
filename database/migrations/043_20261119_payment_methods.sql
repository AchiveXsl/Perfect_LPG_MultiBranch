-- Perfect LPG - payment methods
-- Adds Raast / Bank transfer, JazzCash, Easypaisa, UPaisa, NayaPay and SadaPay next to Cash and Online.
-- Cheque stays available as before.
-- Idempotent: safe to run more than once.
-- Note for editors: the migration runner splits on semicolons, so comments must not contain one.

ALTER TABLE sale_payments MODIFY payment_mode ENUM('cash','cheque','online','credit','raast','jazzcash','easypaisa','upaisa','nayapay','sadapay') NOT NULL;

ALTER TABLE purchase_payments MODIFY payment_mode ENUM('cash','cheque','online','credit','raast','jazzcash','easypaisa','upaisa','nayapay','sadapay') NOT NULL;

ALTER TABLE customer_receipts MODIFY payment_mode ENUM('cash','cheque','online','raast','jazzcash','easypaisa','upaisa','nayapay','sadapay') NOT NULL;

ALTER TABLE supplier_payments MODIFY payment_mode ENUM('cash','cheque','online','raast','jazzcash','easypaisa','upaisa','nayapay','sadapay') NOT NULL;

ALTER TABLE shop_settings MODIFY default_payment_mode ENUM('cash','cheque','online','credit','raast','jazzcash','easypaisa','upaisa','nayapay','sadapay') NOT NULL DEFAULT 'cash';

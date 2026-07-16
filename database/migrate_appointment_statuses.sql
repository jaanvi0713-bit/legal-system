-- Migrate appointment statuses to six calendar-aligned values.
ALTER TABLE appointments
    MODIFY status ENUM('pending','accepted','rejected','cancelled','completed','scheduled','confirmed','rescheduled') NOT NULL DEFAULT 'pending';

UPDATE appointments SET status = 'confirmed' WHERE status = 'accepted';
UPDATE appointments SET status = 'cancelled' WHERE status = 'rejected';

ALTER TABLE appointments
    MODIFY status ENUM('scheduled','confirmed','rescheduled','pending','completed','cancelled') NOT NULL DEFAULT 'pending';

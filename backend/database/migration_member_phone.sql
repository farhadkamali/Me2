-- Run once on the EXISTING production database before deploying the new APKs.
-- Existing accounts and password hashes are preserved. Old accounts get NULL phone
-- until their owner/admin assigns a verified mobile number.
ALTER TABLE members
  ADD COLUMN first_name VARCHAR(60) NULL AFTER username,
  ADD COLUMN last_name VARCHAR(60) NULL AFTER first_name,
  ADD COLUMN phone VARCHAR(11) NULL AFTER last_name;

ALTER TABLE members
  ADD UNIQUE KEY uq_members_phone (phone);

-- IMPORTANT: replace the example values with the real admin account ID and phone.
-- Do not leave the example values in place. Find the admin row first:
-- SELECT id, username, role, phone FROM members WHERE role='admin';
-- Then run, for example:
-- UPDATE members SET phone='09XXXXXXXXX' WHERE id=YOUR_ADMIN_ID AND role='admin';
-- Use the admin's real 11-digit mobile number; each phone must be unique.

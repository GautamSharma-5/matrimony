-- Add missing columns for email and phone verification
ALTER TABLE users
ADD COLUMN IF NOT EXISTS email_verification_token VARCHAR(255),
ADD COLUMN IF NOT EXISTS email_verification_expiry DATETIME,
ADD COLUMN IF NOT EXISTS phone_verification_code VARCHAR(6),
ADD COLUMN IF NOT EXISTS phone_code_expiry DATETIME,
ADD COLUMN IF NOT EXISTS phone_verification_attempts INT DEFAULT 0;

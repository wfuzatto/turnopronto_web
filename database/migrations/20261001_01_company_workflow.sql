-- TurnoPronto Web - upgrade W1
-- Execute uma única vez em instalações existentes que não serão recriadas pelo install.php.

ALTER TABLE tp_shifts
  ADD COLUMN IF NOT EXISTS acceptance_mode VARCHAR(20) NOT NULL DEFAULT 'automatic' AFTER checkin_pin;

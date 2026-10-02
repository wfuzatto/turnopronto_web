-- TurnoPronto Web - documentos/KYC
ALTER TABLE tp_documents ADD COLUMN IF NOT EXISTS file_path VARCHAR(500) NULL AFTER status;
ALTER TABLE tp_documents ADD COLUMN IF NOT EXISTS original_name VARCHAR(255) NULL AFTER file_path;
ALTER TABLE tp_documents ADD COLUMN IF NOT EXISTS mime_type VARCHAR(100) NULL AFTER original_name;
ALTER TABLE tp_documents ADD COLUMN IF NOT EXISTS rejection_reason VARCHAR(500) NULL AFTER mime_type;

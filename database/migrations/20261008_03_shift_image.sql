-- TurnoPronto - foto própria por vaga
ALTER TABLE tp_shifts
  ADD COLUMN image_path VARCHAR(255) NULL AFTER description;

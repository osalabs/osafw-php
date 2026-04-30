-- avoid using BIT columns
ALTER TABLE demos MODIFY COLUMN fyesno            TINYINT UNSIGNED NOT NULL DEFAULT 0;

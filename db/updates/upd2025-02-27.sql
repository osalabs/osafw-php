-- oauth scopes support
ALTER TABLE users ADD COLUMN oauth_scopes TEXT after id;

ALTER TABLE att
    -- Add new columns
    ADD COLUMN icode VARCHAR(64) CHARACTER SET ascii NOT NULL DEFAULT '' AFTER id,
    ADD COLUMN storage TINYINT UNSIGNED DEFAULT 0 AFTER item_id,
    ADD COLUMN raw LONGBLOB AFTER storage,

    -- Modify existing columns
    MODIFY COLUMN fsize BIGINT UNSIGNED DEFAULT 0,

    -- Drop old columns that are no longer needed
    DROP COLUMN is_s3,
    DROP COLUMN is_inline,

    -- Reorder columns to match the new structure (optional, MySQL doesn't strictly enforce order)
    MODIFY COLUMN iname VARCHAR(255) NOT NULL DEFAULT '' AFTER raw,
    MODIFY COLUMN fname VARCHAR(255) NOT NULL DEFAULT '' AFTER iname,
    MODIFY COLUMN ext VARCHAR(16) NOT NULL DEFAULT '' AFTER fname,
    MODIFY COLUMN is_image TINYINT DEFAULT 0 AFTER ext;
    
-- Add unique index if needed
-- ALTER TABLE att ADD UNIQUE INDEX UX_att_icode (icode);

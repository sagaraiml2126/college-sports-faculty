-- Add the mother name required by the Engineering players identity card.
ALTER TABLE `students`
    ADD COLUMN IF NOT EXISTS `mother_name` VARCHAR(160) NULL AFTER `full_name`;

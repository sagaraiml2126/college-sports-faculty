-- Add the Polytechnic roll number used by the eligibility-form export.
ALTER TABLE `students`
    ADD COLUMN IF NOT EXISTS `roll_no` VARCHAR(40) NULL AFTER `enrollment_no`;

-- Add assigned lawyer to court hearings
ALTER TABLE court_hearings ADD COLUMN lawyer_id INT UNSIGNED DEFAULT NULL AFTER case_id;
ALTER TABLE court_hearings ADD CONSTRAINT fk_court_hearing_lawyer FOREIGN KEY (lawyer_id) REFERENCES users(id) ON DELETE SET NULL;
UPDATE court_hearings h JOIN cases c ON c.id = h.case_id SET h.lawyer_id = c.lawyer_id WHERE h.lawyer_id IS NULL AND c.lawyer_id IS NOT NULL;

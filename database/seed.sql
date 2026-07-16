-- Seed data for Legal Case Management System
USE `legal_system`;

-- Demo logins (username or email + password):
-- admin / admin@admin.mu  → admin123
-- lawyer01                → lawyer01
-- yeshna                  → yeshna

INSERT INTO users (id, role, first_name, last_name, username, email, password, phone, address, specialization, bar_number, company_name, is_active, availability, assigned_lawyer_id) VALUES
(1, 'admin', 'Admin', 'User', 'admin', 'admin@admin.mu', '$2y$12$8Fcach8LZDVYjdr7R5wWPurvATwAc8PA9Fwz9HmJKO0WXaA.ZmY7O', '+971-50-100-0001', 'Dubai International Financial Centre', NULL, NULL, 'Lexora Legal Partners', 1, 'available', NULL),
(2, 'lawyer', 'James', 'Carter', 'lawyer01', 'lawyer01@lexora.law', '$2y$12$Z.bxEKTVw8humvB.AxQVAOe4JkDGrvywmMVptmCZB78HXRCq7WkkW', '+971-50-200-0002', 'DIFC Gate Village', 'Corporate Law', 'BAR-UE-2041', NULL, 1, 'available', NULL),
(3, 'lawyer', 'Amira', 'Hassan', 'amira', 'amira.hassan@lexora.law', '$2y$12$Z.bxEKTVw8humvB.AxQVAOe4JkDGrvywmMVptmCZB78HXRCq7WkkW', '+971-50-200-0003', 'Business Bay', 'Litigation', 'BAR-UE-3188', NULL, 1, 'busy', NULL),
(4, 'lawyer', 'Daniel', 'Okoro', 'daniel', 'daniel.okoro@lexora.law', '$2y$12$Z.bxEKTVw8humvB.AxQVAOe4JkDGrvywmMVptmCZB78HXRCq7WkkW', '+971-50-200-0004', 'JLT Cluster', 'Family Law', 'BAR-UE-4520', NULL, 1, 'available', NULL),
(5, 'staff', 'Nina', 'Patel', 'nina', 'nina.patel@lexora.law', '$2y$12$Z.bxEKTVw8humvB.AxQVAOe4JkDGrvywmMVptmCZB78HXRCq7WkkW', '+971-50-300-0005', 'DIFC', NULL, NULL, NULL, 1, 'available', NULL),
(6, 'client', 'Yeshna', 'Client', 'yeshna', 'yeshna@email.com', '$2y$12$fvodPtEffBg0snZt1zE8Qee/9v2xkOk2r8EtHtnrF/CZtIB.hmxJ2', '+971-55-400-0006', 'Palm Jumeirah', NULL, NULL, 'Yeshna Holdings', 1, 'available', 2),
(7, 'client', 'Elena', 'Vasquez', 'elena', 'elena.client@email.com', '$2y$12$fvodPtEffBg0snZt1zE8Qee/9v2xkOk2r8EtHtnrF/CZtIB.hmxJ2', '+971-55-400-0007', 'Marina Walk', NULL, NULL, 'Vasquez Trading LLC', 1, 'available', 3),
(8, 'client', 'Raj', 'Sharma', 'raj', 'raj.client@email.com', '$2y$12$fvodPtEffBg0snZt1zE8Qee/9v2xkOk2r8EtHtnrF/CZtIB.hmxJ2', '+971-55-400-0008', 'Al Quoz', NULL, NULL, 'Sharma Logistics', 1, 'available', 2);

INSERT INTO cases (id, case_number, title, description, case_type, status, priority, client_id, lawyer_id, court_name, court_location, filing_date, next_hearing_date, created_by) VALUES
(1, 'CASE-2026-001', 'Al Maktoum Holdings v. Northwind Corp', 'Commercial dispute regarding supply contract breach and damages claim.', 'Commercial', 'active', 'high', 6, 2, 'Dubai Courts - Commercial Circuit', 'Dubai', '2026-01-15', '2026-07-20', 1),
(2, 'CASE-2026-002', 'Vasquez Employment Dispute', 'Wrongful termination claim and severance negotiation.', 'Employment', 'pending', 'medium', 7, 3, 'Labour Court Dubai', 'Dubai', '2026-03-02', '2026-07-25', 1),
(3, 'CASE-2026-003', 'Sharma Logistics Contract Review', 'Ongoing contract drafting and vendor agreement review.', 'Corporate', 'open', 'low', 8, 2, NULL, NULL, '2026-05-10', NULL, 1),
(4, 'CASE-2026-004', 'Al Maktoum Property Transfer', 'Real estate title transfer and escrow coordination.', 'Real Estate', 'closed', 'medium', 6, 4, 'Dubai Land Department', 'Dubai', '2025-08-01', NULL, 1);

UPDATE cases SET closed_at = '2026-02-28 16:00:00' WHERE id = 4;

INSERT INTO case_notes (case_id, user_id, note, is_private) VALUES
(1, 2, 'Initial consultation completed. Client provided supply invoices and correspondence.', 0),
(1, 2, 'Draft statement of claim prepared pending client approval.', 1),
(2, 3, 'Client requested mediation before formal hearing.', 0),
(3, 2, 'Vendor agreement template shared with client for review.', 0);

INSERT INTO appointments (title, description, appointment_type, case_id, client_id, lawyer_id, scheduled_at, duration_minutes, location, status, created_by) VALUES
('Case Strategy Meeting', 'Review evidence and next steps for commercial dispute.', 'meeting', 1, 6, 2, '2026-07-14 10:00:00', 60, 'Lexora Office - Boardroom A', 'confirmed', 1),
('Labour Hearing Prep', 'Prepare client for labour court appearance.', 'consultation', 2, 7, 3, '2026-07-15 14:30:00', 45, 'Virtual - Zoom', 'pending', 3),
('Contract Signing', 'Finalize logistics vendor agreement.', 'meeting', 3, 8, 2, '2026-07-16 11:00:00', 30, 'Lexora Office - Room 2', 'confirmed', 2),
('Commercial Hearing', 'First hearing - Commercial Circuit.', 'hearing', 1, 6, 2, '2026-07-20 09:00:00', 120, 'Dubai Courts', 'rescheduled', 1);

INSERT INTO court_hearings (case_id, hearing_date, court_name, court_location, judge_name, hearing_type, outcome, notes, status, created_by) VALUES
(1, '2026-07-20 09:00:00', 'Dubai Courts - Commercial Circuit', 'Dubai', 'Hon. Judge Al Rashid', 'Preliminary', NULL, 'Bring original contracts and witness list.', 'scheduled', 1),
(2, '2026-07-25 10:30:00', 'Labour Court Dubai', 'Dubai', 'Hon. Judge Farah', 'Mediation', NULL, 'Client to attend with employment file.', 'scheduled', 3),
(3, '2026-08-05 11:00:00', 'Supreme Court Complex', 'Port Louis', 'Hon. Judge Morel', 'Directions', NULL, 'File amended pleadings before hearing.', 'scheduled', 2),
(4, '2026-08-12 14:15:00', 'Intermediate Court', 'Port Louis', 'Hon. Judge Seebaluck', 'Case Management', NULL, 'Bring title deed copies and survey plan.', 'scheduled', 1),
(4, '2025-11-12 11:00:00', 'Dubai Land Department', 'Dubai', NULL, 'Transfer Hearing', 'Title transferred successfully.', 'Completed without objection.', 'completed', 4);

INSERT INTO invoices (invoice_number, case_id, client_id, title, description, amount, tax, total, status, due_date, issued_at, created_by) VALUES
('INV-2026-001', 1, 6, 'Retainer - Commercial Dispute', 'Initial retainer and filing fees', 15000.00, 750.00, 15750.00, 'partial', '2026-02-15', '2026-01-20', 1),
('INV-2026-002', 2, 7, 'Employment Matter Fees', 'Consultation and mediation preparation', 8000.00, 400.00, 8400.00, 'sent', '2026-07-30', '2026-06-15', 1),
('INV-2026-003', 3, 8, 'Contract Drafting Services', 'Vendor agreement drafting package', 4500.00, 225.00, 4725.00, 'paid', '2026-06-01', '2026-05-12', 1),
('INV-2026-004', 4, 6, 'Property Transfer Fees', 'Real estate transfer legal services', 12000.00, 600.00, 12600.00, 'paid', '2026-01-31', '2025-12-15', 1);

INSERT INTO payments (invoice_id, client_id, amount, payment_method, reference_number, receipt_number, notes, paid_at, recorded_by) VALUES
(1, 6, 10000.00, 'bank_transfer', 'TRX-99102', 'RCP-2026-001', 'Partial retainer payment', '2026-01-25 12:00:00', 1),
(3, 8, 4725.00, 'card', 'CARD-4421', 'RCP-2026-002', 'Full payment received', '2026-05-20 09:30:00', 1),
(4, 6, 12600.00, 'bank_transfer', 'TRX-88301', 'RCP-2026-003', 'Final settlement', '2026-01-10 15:00:00', 1);

INSERT INTO notifications (user_id, title, message, type, link, is_read, created_by) VALUES
(1, 'notify.new_client', 'notify.msg.new_client::{"name":"Raj Sharma"}', 'info', 'clients.php', 0, 1),
(1, 'notify.payment_received', 'notify.msg.payment_received::{"amount":"Rs 4,725.00","from":"Sharma Logistics"}', 'success', 'finance.php', 1, 1),
(2, 'notify.case_assigned', 'notify.msg.case_assigned::{"number":"CASE-2026-001"}', 'case', 'cases.php?id=1', 0, 1),
(2, 'notify.hearing_reminder', 'notify.msg.hearing_reminder::{"date":"20 Jul 2026","title":"Commercial hearing"}', 'reminder', 'court.php', 0, 1),
(3, 'notify.appointment_pending', 'notify.msg.appointment_pending::{"title":"Labour Hearing Prep"}', 'appointment', 'appointments.php', 0, 1),
(6, 'notify.document_requested', 'notify.msg.document_requested_named::{"doc":"the original supply contract"}', 'document', 'documents.php', 0, 2),
(6, 'notify.payment_reminder', 'notify.msg.payment_reminder::{"amount":"Rs 5,750.00","invoice":"INV-2026-001"}', 'payment', 'payments.php', 0, 1),
(7, 'notify.case_update', 'Mediation date confirmed for 25 Jul 2026.', 'case', 'cases.php?id=2', 0, 3);

INSERT INTO messages (sender_id, receiver_id, case_id, subject, body, is_read) VALUES
(6, 2, 1, 'Evidence package', 'I have scanned the remaining invoices. When can I upload them?', 0),
(2, 6, 1, 'Re: Evidence package', 'Please upload via the Documents section by Thursday.', 1),
(7, 3, 2, 'Question about mediation', 'Do I need to bring HR witnesses to the mediation session?', 0);

INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description, ip_address) VALUES
(1, 'login', 'user', 1, 'Admin logged in', '127.0.0.1'),
(1, 'create', 'case', 1, 'Created case CASE-2026-001', '127.0.0.1'),
(2, 'update', 'case', 1, 'Updated case notes for CASE-2026-001', '127.0.0.1'),
(1, 'payment', 'payment', 1, 'Recorded payment RCP-2026-001', '127.0.0.1'),
(3, 'create', 'appointment', 2, 'Created Labour Hearing Prep appointment', '127.0.0.1');

INSERT INTO settings (setting_key, setting_value) VALUES
('company_name', 'LEGAL PRO'),
('company_email', 'contact@lexora.law'),
('company_phone', '+971-4-555-0100'),
('company_address', 'Gate Village Building 4, DIFC, Dubai, UAE'),
('branding_primary', '#023e8a'),
('branding_accent', '#023e8a'),
('theme', 'light'),
('payment_currency', 'MUR'),
('app_language', 'en'),
('ai_enabled', '1'),
('ai_welcome_admin', 'You are the LEGAL PRO admin AI assistant. Help with firm operations, case summaries, and legal drafting guidance. Amounts are in Mauritian rupees (MUR). Respond in the user''s language (English or French).'),
('ai_welcome_lawyer', 'You are the LEGAL PRO lawyer AI assistant. Help with case analysis, document drafting, and hearing preparation. Respond in the user''s language (English or French).'),
('ai_welcome_client', 'You are the LEGAL PRO client AI assistant. Explain the client''s own documents and case status in plain language. Never reveal other clients'' information. Respond in the user''s language (English or French).');

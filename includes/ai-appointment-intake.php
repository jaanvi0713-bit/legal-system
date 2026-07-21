<?php
/**
 * Guided AI appointment schedule / delete flows (multi-turn).
 * Uses the same session draft store as client intake.
 */

function ai_appt_draft_blank(string $portal): array
{
    return [
        'type' => 'appointment',
        'portal' => $portal,
        'fields' => [
            'when' => '',
            'title' => '',
            'duration' => '60',
            'location' => '',
            'client_id' => '',
            'client_name' => '',
            'lawyer_id' => '',
            'lawyer_name' => '',
            'case_id' => '',
            'case_number' => '',
            'description' => '',
        ],
        'awaiting_field' => '',
        'created_at' => time(),
        'updated_at' => time(),
    ];
}

function ai_appt_delete_draft_blank(string $portal, string $mode = 'delete'): array
{
    return [
        'type' => 'appointment_delete',
        'portal' => $portal,
        'mode' => $mode, // delete | cancel
        'appointment_id' => 0,
        'candidates' => [],
        'awaiting_field' => 'pick',
        'created_at' => time(),
        'updated_at' => time(),
    ];
}

function ai_appt_message_is_reply(string $message, string $q, array $draft): bool
{
    if (($draft['type'] ?? '') !== 'appointment') {
        return false;
    }
    $trim = trim($message);
    if ($trim === '') {
        return false;
    }
    if (ai_actions_wants($q, [
        'confirm', 'yes', 'yes schedule', 'schedule now', 'book it', 'go ahead', 'oui', 'confirmer',
        'cancel', 'abort', 'stop', 'annuler',
    ])) {
        return true;
    }
    $awaiting = (string) ($draft['awaiting_field'] ?? '');
    if ($awaiting !== '') {
        return true;
    }
    // Continuing answers that look like schedule details
    if (ai_actions_extract_datetime($message)
        || preg_match('/\b(client|lawyer|avocat|title|titre|duration|location|lieu)\b/iu', $q)
        || preg_match('/\b(tomorrow|today|demain|aujourd|am|pm|\d{1,2}:\d{2})\b/iu', $q)) {
        return true;
    }
    return false;
}

function ai_appt_delete_message_is_reply(string $message, string $q, array $draft): bool
{
    if (($draft['type'] ?? '') !== 'appointment_delete') {
        return false;
    }
    $trim = trim($message);
    if ($trim === '') {
        return false;
    }
    if (ai_actions_wants($q, [
        'confirm', 'yes', 'yes delete', 'delete it', 'go ahead', 'oui', 'confirmer',
        'cancel', 'abort', 'stop', 'annuler',
    ])) {
        return true;
    }
    if (preg_match('/^\s*#?\d+\s*$/', $trim) || preg_match('/\b(?:appointment|id|#)\s*[:=]?\s*\d+\b/i', $trim)) {
        return true;
    }
    $awaiting = (string) ($draft['awaiting_field'] ?? '');
    return $awaiting !== '';
}

/**
 * @return list<string>
 */
function ai_appt_missing_fields(array $fields, string $portal): array
{
    $missing = [];
    if (trim((string) ($fields['when'] ?? '')) === '') {
        $missing[] = 'when';
    }
    if ($portal === 'admin' || $portal === 'staff') {
        if ((int) ($fields['client_id'] ?? 0) <= 0) {
            $missing[] = 'client';
        }
        if ((int) ($fields['lawyer_id'] ?? 0) <= 0) {
            $missing[] = 'lawyer';
        }
    } elseif ($portal === 'lawyer') {
        if ((int) ($fields['client_id'] ?? 0) <= 0) {
            $missing[] = 'client';
        }
    } elseif ($portal === 'client') {
        if ((int) ($fields['lawyer_id'] ?? 0) <= 0) {
            $missing[] = 'lawyer';
        }
    }
    return $missing;
}

function ai_appt_ask_prompt(string $field, string $portal): string
{
    return match ($field) {
        'when' => "What **date and time** should I schedule?\n\nExamples: `tomorrow at 10:00`, `2026-07-22 14:30`, `next Monday at 3pm`.",
        'client' => $portal === 'lawyer'
            ? "Which **client** is this for? Reply with their full name."
            : "Which **client**? Reply with their full name (must already exist in the system).",
        'lawyer' => $portal === 'client'
            ? "Which **lawyer** should I request? Reply with their name (or say **my lawyer** if you have one assigned)."
            : "Which **lawyer**? Reply with their full name.",
        'title' => "What should the appointment be called? (or say **skip** to use “Consultation”)",
        'confirm' => '',
        default => "Please provide the **{$field}**.",
    };
}

/**
 * @param array<string, string> $fields
 */
function ai_appt_summary(array $fields, string $portal): string
{
    $when = (string) ($fields['when'] ?? '');
    $whenLabel = $when !== '' ? format_datetime($when) : '—';
    $title = trim((string) ($fields['title'] ?? '')) ?: 'Consultation';
    $duration = max(15, (int) ($fields['duration'] ?? 60));
    $lines = [
        '📋 **Appointment draft**',
        '',
        '• Title: **' . $title . '**',
        '• When: **' . $whenLabel . '**',
        '• Duration: ' . $duration . ' min',
        '• Client: **' . ((string) ($fields['client_name'] ?? '') ?: '—') . '**',
        '• Lawyer: **' . ((string) ($fields['lawyer_name'] ?? '') ?: '—') . '**',
    ];
    if (!empty($fields['case_number'])) {
        $lines[] = '• Case: ' . $fields['case_number'];
    }
    if (!empty($fields['location'])) {
        $lines[] = '• Location: ' . $fields['location'];
    }
    if ($portal === 'client') {
        $lines[] = '• Status: **pending** (awaits lawyer confirmation)';
    } else {
        $lines[] = '• Status: **scheduled**';
    }
    $lines[] = '';
    $lines[] = 'Reply **confirm** to book it, or tell me what to change. Say **cancel** to abort.';
    return implode("\n", $lines);
}

/**
 * Merge extracted details from a free-text reply into fields.
 *
 * @param array<string, string> $fields
 * @return array<string, string>
 */
function ai_appt_parse_reply(PDO $pdo, array $user, string $portal, string $message, array $fields, string $awaiting): array
{
    $kv = ai_actions_parse_kv($message);
    $q = ai_actions_normalize($message);
    $trim = trim($message);

    if ($awaiting === 'title' || preg_match('/\b(title|titre|subject)\s*[:=]/iu', $message)) {
        $title = $kv['title'] ?? $kv['subject'] ?? ai_actions_extract_quoted($message, ['title', 'subject']);
        if (!$title && $awaiting === 'title' && !ai_actions_wants($q, ['skip', 'default', 'consultation'])) {
            $title = preg_replace('/^(title|titre|subject)\s*[:=]\s*/iu', '', $trim) ?? $trim;
            $title = trim($title, " \t\"'");
        }
        if ($title && !ai_actions_wants(ai_actions_normalize($title), ['skip', 'default'])) {
            $fields['title'] = $title;
        } elseif (ai_actions_wants($q, ['skip', 'default', 'consultation'])) {
            $fields['title'] = 'Consultation';
        }
    }

    if (isset($kv['duration'])) {
        $fields['duration'] = (string) max(15, (int) $kv['duration']);
    } elseif (preg_match('/\b(\d{2,3})\s*(?:min|mins|minutes)\b/iu', $message, $m)) {
        $fields['duration'] = (string) max(15, (int) $m[1]);
    }

    if (!empty($kv['location']) || !empty($kv['lieu'])) {
        $fields['location'] = (string) ($kv['location'] ?? $kv['lieu']);
    }

    $when = ai_actions_extract_datetime($message);
    if ($when) {
        $fields['when'] = $when;
    } elseif ($awaiting === 'when' && $trim !== '') {
        $ts = strtotime($trim);
        if ($ts !== false) {
            $fields['when'] = date('Y-m-d H:i:s', $ts);
        }
    }

    // Client resolution
    if ($portal !== 'client') {
        $wantClient = $awaiting === 'client'
            || !empty($kv['client'])
            || (bool) preg_match('/\bclient\b/iu', $message)
            || (bool) preg_match('/\bwith\b.+\b(and|lawyer|avocat)\b/iu', $message);

        $client = null;
        if ($wantClient || $awaiting === 'client') {
            if (!empty($kv['client'])) {
                $client = ai_actions_resolve_user($pdo, 'client', (string) $kv['client'], 'client');
            }
            if (!$client) {
                $client = ai_actions_resolve_user($pdo, 'client', $message, 'client');
            }
            // When awaiting client, treat the whole reply as the name
            if (!$client && $awaiting === 'client') {
                $client = ai_actions_resolve_user($pdo, 'client', $trim, 'client');
            }
        }

        if ($client && $portal === 'lawyer' && !lawyer_can_access_client($pdo, (int) $user['id'], (int) $client['id'])) {
            $fields['_error'] = 'That client is not assigned to you.';
        } elseif ($client) {
            $fields['client_id'] = (string) (int) $client['id'];
            $fields['client_name'] = full_name($client);
        } elseif ($awaiting === 'client' || ($wantClient && trim($message) !== '')) {
            // Only error when we clearly tried to resolve a name
            $looksLikeName = (bool) preg_match('/[A-Za-zÀ-ÖØ-öø-ÿ]{2,}/u', $trim)
                && !ai_actions_extract_datetime($message);
            if ($awaiting === 'client' || $looksLikeName) {
                $fields['_error'] = 'I could not find that client. Check the spelling or create the client first.';
            }
        }
    }

    // Lawyer resolution
    if ($portal !== 'lawyer') {
        if (ai_actions_wants($q, ['my lawyer', 'assigned lawyer', 'mon avocat'])) {
            if ($portal === 'client' && !empty($user['assigned_lawyer_id'])) {
                $stmt = $pdo->prepare('SELECT * FROM users WHERE id=? AND role="lawyer"');
                $stmt->execute([(int) $user['assigned_lawyer_id']]);
                $lawyer = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
                if ($lawyer) {
                    $fields['lawyer_id'] = (string) (int) $lawyer['id'];
                    $fields['lawyer_name'] = full_name($lawyer);
                } else {
                    $fields['_error'] = 'No assigned lawyer was found on your profile.';
                }
            }
        } else {
            $wantLawyer = $awaiting === 'lawyer'
                || !empty($kv['lawyer'])
                || !empty($kv['avocat'])
                || (bool) preg_match('/\b(lawyer|avocat)\b/iu', $message);

            $lawyer = null;
            if ($wantLawyer || $awaiting === 'lawyer') {
                if (!empty($kv['lawyer']) || !empty($kv['avocat'])) {
                    $lawyer = ai_actions_resolve_user($pdo, 'lawyer', (string) ($kv['lawyer'] ?? $kv['avocat']), 'lawyer');
                }
                if (!$lawyer) {
                    $lawyer = ai_actions_resolve_user($pdo, 'lawyer', $message, 'lawyer');
                }
                if (!$lawyer && $awaiting === 'lawyer') {
                    $lawyer = ai_actions_resolve_user($pdo, 'lawyer', $trim, 'lawyer');
                }
            }

            if ($lawyer) {
                $fields['lawyer_id'] = (string) (int) $lawyer['id'];
                $fields['lawyer_name'] = full_name($lawyer);
            } elseif ($awaiting === 'lawyer' || ($wantLawyer && trim($message) !== '')) {
                $looksLikeName = (bool) preg_match('/[A-Za-zÀ-ÖØ-öø-ÿ]{2,}/u', $trim)
                    && !ai_actions_extract_datetime($message);
                if ($awaiting === 'lawyer' || $looksLikeName) {
                    $fields['_error'] = 'I could not find that lawyer. Check the spelling.';
                }
            }
        }
    }

    $case = ai_actions_find_case($pdo, $portal, $user, $message);
    if ($case) {
        $fields['case_id'] = (string) (int) $case['id'];
        $fields['case_number'] = (string) ($case['case_number'] ?? ('#' . $case['id']));
        if ((int) ($fields['client_id'] ?? 0) <= 0 && !empty($case['client_id'])) {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE id=? AND role="client"');
            $stmt->execute([(int) $case['client_id']]);
            $c = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($c) {
                $fields['client_id'] = (string) (int) $c['id'];
                $fields['client_name'] = full_name($c);
            }
        }
        if ((int) ($fields['lawyer_id'] ?? 0) <= 0 && !empty($case['lawyer_id'])) {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE id=? AND role="lawyer"');
            $stmt->execute([(int) $case['lawyer_id']]);
            $l = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($l) {
                $fields['lawyer_id'] = (string) (int) $l['id'];
                $fields['lawyer_name'] = full_name($l);
            }
        }
    }

    $title = $kv['title'] ?? ai_actions_extract_quoted($message, ['title', 'subject']);
    if (!$title && preg_match('/\btitle\s+[:=]?\s*(.+?)(?:\s+with\s+|\s+for\s+|\s+client\b|\s+lawyer\b|$)/iu', $message, $m)) {
        $title = trim($m[1], " \t\"'");
    }
    if ($title) {
        $fields['title'] = $title;
    }

    return $fields;
}

function ai_appt_seed_from_user(PDO $pdo, array $user, string $portal, array $fields): array
{
    if ($portal === 'lawyer') {
        $fields['lawyer_id'] = (string) (int) $user['id'];
        $fields['lawyer_name'] = full_name($user);
    }
    if ($portal === 'client') {
        $fields['client_id'] = (string) (int) $user['id'];
        $fields['client_name'] = full_name($user);
        if (!empty($user['assigned_lawyer_id']) && (int) ($fields['lawyer_id'] ?? 0) <= 0) {
            $fields['lawyer_id'] = (string) (int) $user['assigned_lawyer_id'];
            ai_appt_resolve_lawyer_name($pdo, $fields);
        }
    }
    return $fields;
}

function ai_appt_resolve_lawyer_name(PDO $pdo, array &$fields): void
{
    $lid = (int) ($fields['lawyer_id'] ?? 0);
    if ($lid > 0 && trim((string) ($fields['lawyer_name'] ?? '')) === '') {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id=? AND role="lawyer"');
        $stmt->execute([$lid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $fields['lawyer_name'] = full_name($row);
        } else {
            $fields['lawyer_id'] = '';
        }
    }
}

function ai_action_schedule_appointment_guided(
    PDO $pdo,
    array $user,
    string $portal,
    string $message,
    string $q,
    int $sessionId
): string {
    if ($sessionId <= 0) {
        return 'Open an AI chat session first, then ask me to schedule an appointment.';
    }

    $draft = ai_client_draft_get($sessionId);
    $startedFresh = false;
    if (!$draft || ($draft['type'] ?? '') !== 'appointment') {
        $draft = ai_appt_draft_blank($portal);
        $startedFresh = true;
    }

    if (ai_actions_wants($q, ['cancel', 'abort', 'stop', 'annuler'])
        && !ai_actions_wants($q, ['cancel appointment', 'cancel meeting'])) {
        // Only cancel the draft when we're mid-wizard, not a "cancel appointment" intent.
        if (!$startedFresh || (string) ($draft['awaiting_field'] ?? '') !== '') {
            ai_client_draft_clear($sessionId);
            return 'Appointment scheduling cancelled. Nothing was booked.';
        }
    }

    $fields = ai_appt_seed_from_user($pdo, $user, $portal, $draft['fields'] ?? []);
    ai_appt_resolve_lawyer_name($pdo, $fields);

    $isConfirm = ai_actions_wants($q, ['confirm', 'yes', 'yes schedule', 'schedule now', 'book it', 'go ahead', 'oui', 'confirmer']);

    if (!$isConfirm || $startedFresh) {
        $awaiting = (string) ($draft['awaiting_field'] ?? '');
        $fields = ai_appt_parse_reply($pdo, $user, $portal, $message, $fields, $awaiting);
        if (!empty($fields['_error'])) {
            $err = (string) $fields['_error'];
            unset($fields['_error']);
            $draft['fields'] = $fields;
            $draft['awaiting_field'] = $awaiting !== '' ? $awaiting : (ai_appt_missing_fields($fields, $portal)[0] ?? 'when');
            ai_client_draft_set($sessionId, $draft);
            return '⚠️ ' . $err . "\n\n" . ai_appt_ask_prompt($draft['awaiting_field'], $portal);
        }
        unset($fields['_error']);
    }

    if (trim((string) ($fields['title'] ?? '')) === '' && !$startedFresh && (string) ($draft['awaiting_field'] ?? '') !== 'title') {
        // leave empty until confirm — default applied on book
    }

    $missing = ai_appt_missing_fields($fields, $portal);
    $draft['fields'] = $fields;

    if ($missing) {
        $next = $missing[0];
        $draft['awaiting_field'] = $next;
        ai_client_draft_set($sessionId, $draft);
        $intro = $startedFresh
            ? "I'll schedule this step by step.\n\n"
            : '';
        return $intro . ai_appt_ask_prompt($next, $portal);
    }

    // Verify parties still exist before offering confirm / booking.
    $clientCheck = ai_appt_load_party($pdo, 'client', (int) ($fields['client_id'] ?? 0), (string) ($fields['client_name'] ?? ''));
    $lawyerCheck = ai_appt_load_party($pdo, 'lawyer', (int) ($fields['lawyer_id'] ?? 0), (string) ($fields['lawyer_name'] ?? ''));
    if ($portal !== 'client' && !$clientCheck) {
        $fields['client_id'] = '';
        $fields['client_name'] = '';
        $draft['fields'] = $fields;
        $draft['awaiting_field'] = 'client';
        ai_client_draft_set($sessionId, $draft);
        return "That client is not in the system (or was removed).\n\n" . ai_appt_ask_prompt('client', $portal);
    }
    if ($portal !== 'lawyer' && !$lawyerCheck) {
        $fields['lawyer_id'] = '';
        $fields['lawyer_name'] = '';
        $draft['fields'] = $fields;
        $draft['awaiting_field'] = 'lawyer';
        ai_client_draft_set($sessionId, $draft);
        return "That lawyer is not in the system (or was removed).\n\n" . ai_appt_ask_prompt('lawyer', $portal);
    }
    if ($clientCheck) {
        $fields['client_id'] = (string) (int) $clientCheck['id'];
        $fields['client_name'] = full_name($clientCheck);
    }
    if ($lawyerCheck) {
        $fields['lawyer_id'] = (string) (int) $lawyerCheck['id'];
        $fields['lawyer_name'] = full_name($lawyerCheck);
    }
    $draft['fields'] = $fields;

    // All required present — ask confirm unless already confirming
    if (!$isConfirm) {
        $draft['awaiting_field'] = 'confirm';
        ai_client_draft_set($sessionId, $draft);
        return ai_appt_summary($fields, $portal);
    }

    // Confirm → book
    $title = trim((string) ($fields['title'] ?? '')) ?: 'Consultation';
    $when = (string) $fields['when'];
    $duration = max(15, (int) ($fields['duration'] ?? 60));
    $location = trim((string) ($fields['location'] ?? '')) ?: null;
    $status = $portal === 'client' ? 'pending' : 'scheduled';
    $uid = (int) $user['id'];

    // Re-validate parties against DB (draft IDs can go stale / be wrong).
    $client = ai_appt_load_party($pdo, 'client', (int) ($fields['client_id'] ?? 0), (string) ($fields['client_name'] ?? ''));
    $lawyer = ai_appt_load_party($pdo, 'lawyer', (int) ($fields['lawyer_id'] ?? 0), (string) ($fields['lawyer_name'] ?? ''));

    if (!$client) {
        $fields['client_id'] = '';
        $fields['client_name'] = '';
        $draft['fields'] = $fields;
        $draft['awaiting_field'] = 'client';
        ai_client_draft_set($sessionId, $draft);
        return "I couldn't verify that client in the system anymore.\n\n" . ai_appt_ask_prompt('client', $portal);
    }
    if (!$lawyer) {
        $fields['lawyer_id'] = '';
        $fields['lawyer_name'] = '';
        $draft['fields'] = $fields;
        $draft['awaiting_field'] = 'lawyer';
        ai_client_draft_set($sessionId, $draft);
        return "I couldn't verify that lawyer in the system anymore.\n\n" . ai_appt_ask_prompt('lawyer', $portal);
    }

    $clientId = (int) $client['id'];
    $lawyerId = (int) $lawyer['id'];
    $fields['client_id'] = (string) $clientId;
    $fields['client_name'] = full_name($client);
    $fields['lawyer_id'] = (string) $lawyerId;
    $fields['lawyer_name'] = full_name($lawyer);

    $caseId = (int) ($fields['case_id'] ?? 0);
    if ($caseId > 0) {
        $caseStmt = $pdo->prepare('SELECT id, case_number, client_id, lawyer_id FROM cases WHERE id=? LIMIT 1');
        $caseStmt->execute([$caseId]);
        $caseRow = $caseStmt->fetch(PDO::FETCH_ASSOC);
        if (!$caseRow) {
            $caseId = null;
            $fields['case_id'] = '';
            $fields['case_number'] = '';
        } else {
            $caseId = (int) $caseRow['id'];
            $fields['case_number'] = (string) ($caseRow['case_number'] ?? ('#' . $caseId));
        }
    } else {
        $caseId = null;
    }

    if (strtotime($when) === false || strtotime($when) < time() - 300) {
        $draft['awaiting_field'] = 'when';
        $draft['fields'] = $fields;
        $draft['fields']['when'] = '';
        ai_client_draft_set($sessionId, $draft);
        return "That date/time looks invalid or in the past. " . ai_appt_ask_prompt('when', $portal);
    }

    $slotCheck = validate_lawyer_appointment_slot($pdo, $lawyerId, $when, $duration, null, false);
    $slotWarning = '';
    if (empty($slotCheck['ok'])) {
        $cntStmt = $pdo->prepare('SELECT COUNT(*) FROM lawyer_availability_slots WHERE lawyer_id=?');
        $cntStmt->execute([$lawyerId]);
        $hasAnySlots = (int) $cntStmt->fetchColumn();
        if ($portal === 'admin' || $hasAnySlots === 0) {
            $slotWarning = "\n\n⚠️ Note: " . ($slotCheck['message'] ?? 'Outside published availability') . ' — booked anyway.';
        } else {
            $draft['awaiting_field'] = 'when';
            $draft['fields'] = $fields;
            $draft['fields']['when'] = '';
            ai_client_draft_set($sessionId, $draft);
            $reason = $slotCheck['message'] ?? 'Selected slot is not available.';
            return "⚠️ Could not schedule: {$reason}\n\nPick another time.";
        }
    }

    try {
        $pdo->prepare(
            'INSERT INTO appointments (title, description, appointment_type, case_id, client_id, lawyer_id, scheduled_at, duration_minutes, location, status, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $title,
            trim((string) ($fields['description'] ?? '')) !== '' ? $fields['description'] : null,
            'meeting',
            $caseId,
            $clientId,
            $lawyerId,
            $when,
            $duration,
            $location,
            $status,
            $uid > 0 ? $uid : null,
        ]);
    } catch (Throwable $e) {
        $draft['fields'] = $fields;
        $draft['awaiting_field'] = 'confirm';
        ai_client_draft_set($sessionId, $draft);
        return "⚠️ Could not save the appointment (" . $e->getMessage() . ").\n\n"
            . ai_appt_summary($fields, $portal);
    }
    $apptId = (int) $pdo->lastInsertId();

    create_notification($pdo, $lawyerId, 'notify.appointment_scheduled', $title, 'appointment', '../lawyer/appointments.php', $uid);
    create_notification($pdo, $clientId, 'notify.appointment_scheduled', $title, 'appointment', '../client/appointments.php', $uid);
    log_activity($pdo, $uid, 'create', 'appointment', $apptId, 'Scheduled appointment via AI');
    ai_client_draft_clear($sessionId);

    $url = ai_actions_portal_url($portal, 'appointments.php');
    return "✅ Appointment " . ($status === 'pending' ? 'requested' : 'scheduled') . " (#{$apptId}).\n\n"
        . "• Title: **{$title}**\n"
        . "• When: " . format_datetime($when) . " ({$duration} min)\n"
        . "• Client: **" . full_name($client) . "**\n"
        . "• Lawyer: **" . full_name($lawyer) . "**\n"
        . "• Status: {$status}\n"
        . (!empty($fields['case_number']) ? '• Case: ' . $fields['case_number'] . "\n" : '')
        . "\n" . ai_actions_md_link('Open appointments', $url)
        . $slotWarning;
}

/**
 * Load a client/lawyer by id, falling back to name resolve.
 *
 * @return array<string, mixed>|null
 */
function ai_appt_load_party(PDO $pdo, string $role, int $id, string $name = ''): ?array
{
    if ($id > 0) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id=? AND role=? LIMIT 1');
        $stmt->execute([$id, $role]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return $row;
        }
    }
    $name = trim($name);
    if ($name !== '') {
        $resolved = ai_actions_resolve_user($pdo, $role, $name, $role);
        if ($resolved) {
            return $resolved;
        }
    }
    return null;
}

/**
 * @return list<array<string, mixed>>
 */
function ai_appt_list_candidates(PDO $pdo, array $user, string $portal, ?string $when = null, int $limit = 8): array
{
    $uid = (int) $user['id'];
    if ($portal === 'lawyer') {
        $sql = "SELECT a.*, CONCAT(c.first_name,' ',c.last_name) AS client_name
                FROM appointments a
                LEFT JOIN users c ON c.id=a.client_id
                WHERE a.lawyer_id=? AND a.status IN ('pending','scheduled','confirmed','rescheduled')";
        $params = [$uid];
    } elseif ($portal === 'client') {
        $sql = "SELECT a.*, CONCAT(l.first_name,' ',l.last_name) AS lawyer_name
                FROM appointments a
                LEFT JOIN users l ON l.id=a.lawyer_id
                WHERE a.client_id=? AND a.status IN ('pending','scheduled','confirmed','rescheduled')";
        $params = [$uid];
    } else {
        $sql = "SELECT a.*,
                       CONCAT(c.first_name,' ',c.last_name) AS client_name,
                       CONCAT(l.first_name,' ',l.last_name) AS lawyer_name
                FROM appointments a
                LEFT JOIN users c ON c.id=a.client_id
                LEFT JOIN users l ON l.id=a.lawyer_id
                WHERE a.status IN ('pending','scheduled','confirmed','rescheduled')";
        $params = [];
    }
    if ($when) {
        $sql .= ' AND DATE(a.scheduled_at)=DATE(?)';
        $params[] = $when;
    }
    $sql .= ' ORDER BY a.scheduled_at ASC LIMIT ' . max(1, min(20, $limit));
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function ai_appt_format_candidate_line(array $appt): string
{
    $id = (int) ($appt['id'] ?? 0);
    $title = (string) ($appt['title'] ?? 'Appointment');
    $when = !empty($appt['scheduled_at']) ? format_datetime($appt['scheduled_at']) : '—';
    $extra = '';
    if (!empty($appt['client_name'])) {
        $extra .= ' · ' . $appt['client_name'];
    }
    if (!empty($appt['lawyer_name'])) {
        $extra .= ' · ' . $appt['lawyer_name'];
    }
    return "• **#{$id}** — {$title} — {$when}{$extra}";
}

function ai_action_delete_appointment_guided(
    PDO $pdo,
    array $user,
    string $portal,
    string $message,
    string $q,
    int $sessionId,
    string $mode = 'delete'
): string {
    if ($sessionId <= 0) {
        return 'Open an AI chat session first, then ask me to delete an appointment.';
    }

    // Clients/lawyers cancel; admin/staff can hard-delete.
    $canHardDelete = ai_actions_can_admin($portal, $user);
    if ($mode === 'delete' && !$canHardDelete) {
        $mode = 'cancel';
    }

    $draft = ai_client_draft_get($sessionId);
    $startedFresh = false;
    if (!$draft || ($draft['type'] ?? '') !== 'appointment_delete') {
        $draft = ai_appt_delete_draft_blank($portal, $mode);
        $startedFresh = true;
    } else {
        $mode = (string) ($draft['mode'] ?? $mode);
    }

    if (ai_actions_wants($q, ['cancel', 'abort', 'stop', 'annuler'])
        && !ai_actions_wants($q, ['cancel appointment', 'cancel meeting', 'delete appointment'])) {
        if (!$startedFresh || (string) ($draft['awaiting_field'] ?? '') !== 'pick' || !empty($draft['appointment_id'])) {
            ai_client_draft_clear($sessionId);
            return 'Appointment ' . ($mode === 'delete' ? 'delete' : 'cancel') . ' aborted. Nothing was changed.';
        }
    }

    $isConfirm = ai_actions_wants($q, ['confirm', 'yes', 'yes delete', 'delete it', 'go ahead', 'oui', 'confirmer']);
    $apptId = (int) ($draft['appointment_id'] ?? 0);

    // Parse id from message
    if (preg_match('/\b(?:appointment|rdv|rendez-vous|id|#)\s*[#:=]?\s*(\d+)\b/i', $message, $m)
        || preg_match('/^\s*#?(\d+)\s*$/', trim($message), $m)) {
        $apptId = (int) $m[1];
    }

    $when = ai_actions_extract_datetime($message);

    if ($apptId <= 0 && empty($draft['candidates'])) {
        $candidates = ai_appt_list_candidates($pdo, $user, $portal, $when, 8);
        if (!$candidates) {
            ai_client_draft_clear($sessionId);
            return 'I could not find any upcoming appointments'
                . ($when ? ' on that date' : '')
                . ". Try **delete appointment #12** with the ID from the appointments list.";
        }
        if (count($candidates) === 1) {
            $apptId = (int) $candidates[0]['id'];
            $draft['appointment_id'] = $apptId;
            $draft['candidates'] = $candidates;
            $draft['awaiting_field'] = 'confirm';
            ai_client_draft_set($sessionId, $draft);
            $verb = $mode === 'delete' ? 'delete' : 'cancel';
            return "Found this appointment:\n\n"
                . ai_appt_format_candidate_line($candidates[0]) . "\n\n"
                . "Reply **confirm** to {$verb} it, or send another **#id**. Say **cancel** to abort.";
        }
        $draft['candidates'] = array_map(static fn($r) => ['id' => (int) $r['id']], $candidates);
        $draft['awaiting_field'] = 'pick';
        ai_client_draft_set($sessionId, $draft);
        $lines = ["I found several appointments. Reply with the **#id** to " . ($mode === 'delete' ? 'delete' : 'cancel') . ":\n"];
        foreach ($candidates as $c) {
            $lines[] = ai_appt_format_candidate_line($c);
        }
        $lines[] = "\nSay **cancel** to abort.";
        return implode("\n", $lines);
    }

    if ($apptId <= 0) {
        $draft['awaiting_field'] = 'pick';
        ai_client_draft_set($sessionId, $draft);
        return 'Which appointment? Reply with **#id** (example: `#12`).';
    }

    $stmt = $pdo->prepare('SELECT a.*, CONCAT(c.first_name," ",c.last_name) AS client_name, CONCAT(l.first_name," ",l.last_name) AS lawyer_name
        FROM appointments a
        LEFT JOIN users c ON c.id=a.client_id
        LEFT JOIN users l ON l.id=a.lawyer_id
        WHERE a.id=?');
    $stmt->execute([$apptId]);
    $appt = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$appt) {
        $draft['appointment_id'] = 0;
        $draft['awaiting_field'] = 'pick';
        ai_client_draft_set($sessionId, $draft);
        return "Appointment **#{$apptId}** was not found. Send another #id.";
    }

    $uid = (int) $user['id'];
    if ($portal === 'lawyer' && (int) $appt['lawyer_id'] !== $uid) {
        return 'That appointment is not assigned to you.';
    }
    if ($portal === 'client' && (int) $appt['client_id'] !== $uid) {
        return 'That appointment does not belong to you.';
    }
    if ($portal === 'admin' && !ai_actions_can_admin($portal, $user)) {
        return 'You do not have permission to manage appointments.';
    }

    $draft['appointment_id'] = $apptId;
    if (!$isConfirm) {
        $draft['awaiting_field'] = 'confirm';
        ai_client_draft_set($sessionId, $draft);
        $verb = $mode === 'delete' ? 'permanently delete' : 'cancel';
        return "Ready to {$verb}:\n\n"
            . ai_appt_format_candidate_line($appt) . "\n"
            . "• Status: " . ($appt['status'] ?? '—') . "\n\n"
            . "Reply **confirm** to proceed, or **cancel** to abort.";
    }

    if ($mode === 'delete' && $canHardDelete) {
        $pdo->prepare('DELETE FROM appointments WHERE id=?')->execute([$apptId]);
        log_activity($pdo, $uid, 'delete', 'appointment', $apptId, 'Deleted appointment via AI');
        $done = "✅ Appointment **#{$apptId}** deleted permanently.";
    } else {
        if (($appt['status'] ?? '') === 'cancelled') {
            ai_client_draft_clear($sessionId);
            return 'That appointment is already cancelled.';
        }
        $pdo->prepare('UPDATE appointments SET status="cancelled" WHERE id=?')->execute([$apptId]);
        log_activity($pdo, $uid, 'update', 'appointment', $apptId, 'Cancelled appointment via AI');
        if (!empty($appt['lawyer_id'])) {
            create_notification($pdo, (int) $appt['lawyer_id'], 'Appointment cancelled', (string) $appt['title'], 'appointment', '../lawyer/appointments.php', $uid);
        }
        if (!empty($appt['client_id'])) {
            create_notification($pdo, (int) $appt['client_id'], 'Appointment cancelled', (string) $appt['title'], 'appointment', '../client/appointments.php', $uid);
        }
        $done = "✅ Appointment **#{$apptId}** cancelled.";
    }

    ai_client_draft_clear($sessionId);
    $url = ai_actions_portal_url($portal, 'appointments.php');
    return $done . "\n\n"
        . "• Was: **" . (string) $appt['title'] . "**\n"
        . "• Scheduled: " . format_datetime($appt['scheduled_at']) . "\n\n"
        . ai_actions_md_link('Open appointments', $url);
}

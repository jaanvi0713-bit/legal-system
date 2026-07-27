<?php
/**
 * AI email draft picker, topic templates, and result cards.
 */

require_once __DIR__ . '/ai-mauritius-law.php';

/**
 * @return array<string, array{label:string,hint:string,subject:string,guide:string}>
 */
function ai_email_draft_topics(string $portal): array
{
    $fr = ai_mauritius_law_is_fr();

    $admin = [
        'hearing_reminder' => [
            'label' => $fr ? 'Rappel d\'audience' : 'Hearing reminder',
            'hint' => $fr ? 'Rappeler la date, l\'heure et les documents à apporter.' : 'Remind them of the date, time, and what to bring.',
            'subject' => $fr ? 'Rappel — audience à venir' : 'Reminder — upcoming hearing',
            'guide' => $fr
                ? 'Rédigez un email professionnel et rassurant. Rappelez la date et l\'heure de l\'audience, le lieu/tribunal si connu, les documents à apporter, et invitez le client à contacter le cabinet en cas d\'empêchement.'
                : 'Write a professional, reassuring email. Remind the client of the hearing date and time, court/venue if known, documents to bring, and ask them to contact the firm if they cannot attend.',
        ],
        'case_update' => [
            'label' => $fr ? 'Mise à jour du dossier' : 'Case progress update',
            'hint' => $fr ? 'Partager où en est le dossier et les prochaines étapes.' : 'Share where the matter stands and what happens next.',
            'subject' => $fr ? 'Mise à jour de votre dossier' : 'Update on your matter',
            'guide' => $fr
                ? 'Rédigez un email clair sur l\'état actuel du dossier, ce qui a été fait récemment, et les prochaines étapes. Ton professionnel mais accessible.'
                : 'Draft a clear email on the current status of the matter, recent progress, and next steps. Professional but approachable tone.',
        ],
        'document_request' => [
            'label' => $fr ? 'Demande de documents' : 'Request documents',
            'hint' => $fr ? 'Lister les pièces nécessaires et comment les envoyer.' : 'List what you need and how to send it.',
            'subject' => $fr ? 'Documents requis pour votre dossier' : 'Documents needed for your matter',
            'guide' => $fr
                ? 'Demandez poliment les documents nécessaires. Listez chaque pièce, expliquez pourquoi elle est utile, et indiquez comment les transmettre (portail, email, rendez-vous).'
                : 'Politely request required documents. List each item, briefly explain why it is needed, and say how to submit them (portal, email, appointment).',
        ],
        'payment_reminder' => [
            'label' => $fr ? 'Facture / paiement' : 'Invoice or payment',
            'hint' => $fr ? 'Rappeler une facture ou confirmer un paiement reçu.' : 'Remind about an invoice or confirm payment received.',
            'subject' => $fr ? 'Concernant votre facture' : 'Regarding your invoice',
            'guide' => $fr
                ? 'Rédigez un email courtois sur une facture en attente ou un paiement. Mentionnez le numéro de facture ou de dossier si pertinent, le montant si connu, et les moyens de règlement.'
                : 'Draft a courteous email about an outstanding invoice or payment. Mention invoice or case reference if relevant, amount if known, and how to pay.',
        ],
        'appointment_confirm' => [
            'label' => $fr ? 'Confirmation de rendez-vous' : 'Appointment confirmation',
            'hint' => $fr ? 'Confirmer date, heure et objet du rendez-vous.' : 'Confirm date, time, and purpose of the meeting.',
            'subject' => $fr ? 'Confirmation de rendez-vous' : 'Appointment confirmation',
            'guide' => $fr
                ? 'Confirmez le rendez-vous (date, heure, lieu ou lien). Résumez brièvement l\'objet et ce que le client peut préparer.'
                : 'Confirm the appointment (date, time, location or link). Briefly state the purpose and anything the client should prepare.',
        ],
        'welcome_client' => [
            'label' => $fr ? 'Bienvenue nouveau client' : 'Welcome new client',
            'hint' => $fr ? 'Accueillir un client et expliquer la suite.' : 'Welcome them and explain what happens next.',
            'subject' => $fr ? 'Bienvenue — prochaines étapes' : 'Welcome — next steps',
            'guide' => $fr
                ? 'Accueillez chaleureusement le nouveau client. Présentez le cabinet, résumez le dossier, expliquez comment communiquer et quelles sont les prochaines étapes.'
                : 'Warmly welcome the new client. Introduce the firm, summarise the matter, explain how to stay in touch, and outline next steps.',
        ],
        'follow_up' => [
            'label' => $fr ? 'Relance amiable' : 'Friendly follow-up',
            'hint' => $fr ? 'Reprendre contact après un silence ou une action en attente.' : 'Check in after silence or a pending action.',
            'subject' => $fr ? 'Suite à notre dernier échange' : 'Following up',
            'guide' => $fr
                ? 'Rédigez un email de relance courtois. Rappelez le contexte, ce qui est en attente, et proposez une date ou un moyen simple de répondre.'
                : 'Draft a polite follow-up. Recap the context, what is pending, and offer an easy way or time to respond.',
        ],
        'custom' => [
            'label' => $fr ? 'Autre sujet' : 'Custom topic',
            'hint' => $fr ? 'Obligatoire : décrivez le sujet dans « Points à inclure ».' : 'Required: describe the subject in “Anything to include”.',
            'subject' => $fr ? 'Concernant votre dossier' : 'Regarding your matter',
            'guide' => $fr
                ? 'Rédigez un email professionnel adapté aux notes fournies par l\'utilisateur.'
                : 'Draft a professional email tailored to the user\'s notes below.',
        ],
    ];

    $lawyer = $admin;

    $client = [
        'case_status' => [
            'label' => $fr ? 'Statut de mon dossier' : 'Ask about case status',
            'hint' => $fr ? 'Demander où en est mon dossier.' : 'Ask for an update on my matter.',
            'subject' => $fr ? 'Demande de mise à jour — mon dossier' : 'Request for update on my matter',
            'guide' => $fr
                ? 'Rédigez un email poli adressé à mon avocat pour demander où en est mon dossier et les prochaines étapes.'
                : 'Draft a polite email to my lawyer asking for a status update and next steps on my matter.',
        ],
        'appointment_request' => [
            'label' => $fr ? 'Demander un rendez-vous' : 'Request an appointment',
            'hint' => $fr ? 'Proposer des créneaux ou demander un appel.' : 'Ask to schedule a call or meeting.',
            'subject' => $fr ? 'Demande de rendez-vous' : 'Appointment request',
            'guide' => $fr
                ? 'Rédigez un email courtois pour demander un rendez-vous ou un appel, en mentionnant la disponibilité si indiquée.'
                : 'Draft a courteous email requesting a meeting or call, mentioning availability if provided.',
        ],
        'document_question' => [
            'label' => $fr ? 'Question sur un document' : 'Question about a document',
            'hint' => $fr ? 'Demander une clarification sur un document ou une pièce.' : 'Ask for clarification on a document.',
            'subject' => $fr ? 'Question concernant un document' : 'Question about a document',
            'guide' => $fr
                ? 'Rédigez un email clair posant une question sur un document ou une pièce jointe.'
                : 'Draft a clear email asking a question about a document or attachment.',
        ],
        'payment_question' => [
            'label' => $fr ? 'Question de facturation' : 'Billing question',
            'hint' => $fr ? 'Poser une question sur une facture ou un paiement.' : 'Ask about an invoice or payment.',
            'subject' => $fr ? 'Question concernant une facture' : 'Question about an invoice',
            'guide' => $fr
                ? 'Rédigez un email poli posant une question sur une facture, un paiement ou des honoraires.'
                : 'Draft a polite email asking about an invoice, payment, or fees.',
        ],
        'general_message' => [
            'label' => $fr ? 'Message général' : 'General message',
            'hint' => $fr ? 'Écrire un message libre à mon avocat.' : 'Write a general message to my lawyer.',
            'subject' => $fr ? 'Message concernant mon dossier' : 'Message regarding my matter',
            'guide' => $fr
                ? 'Rédigez un email professionnel mais naturel à mon avocat, basé sur les notes fournies.'
                : 'Draft a professional but natural email to my lawyer based on the notes provided.',
        ],
    ];

    return match ($portal) {
        'client' => $client,
        'lawyer' => $lawyer,
        default => $admin,
    };
}

function ai_email_draft_topic(string $portal, string $topicId): ?array
{
    $topics = ai_email_draft_topics($portal);
    return $topics[$topicId] ?? null;
}

/**
 * @return array<string, mixed>
 */
function ai_email_draft_picker_payload(string $portal): array
{
    $fr = ai_mauritius_law_is_fr();
    $topics = [];
    foreach (ai_email_draft_topics($portal) as $id => $topic) {
        $topics[] = [
            'id' => $id,
            'label' => $topic['label'],
            'hint' => $topic['hint'],
        ];
    }

    $title = match ($portal) {
        'client' => $fr ? 'Écrire à mon avocat' : 'Message my lawyer',
        'lawyer' => $fr ? 'Écrire à un client' : 'Write to a client',
        default => $fr ? 'Écrire à un client' : 'Write to a client',
    };

    return [
        'portal' => $portal,
        'title' => $title,
        'subtitle' => $fr
            ? 'Choisissez un sujet — je rédige un email professionnel que vous pourrez relire et envoyer.'
            : 'Pick a topic — I\'ll draft a professional email you can review and send.',
        'topics' => $topics,
        'labels' => [
            'topic' => $fr ? 'Sujet de l\'email' : 'Email topic',
            'topic_ph' => $fr ? 'Sélectionner un sujet…' : 'Select a topic…',
            'client' => $fr ? 'Nom du client' : 'Client name',
            'client_ph' => $fr ? 'ex. Marie Dupont (optionnel)' : 'e.g. Jane Smith (optional)',
            'case' => $fr ? 'N° de dossier' : 'Case number',
            'case_ph' => $fr ? 'ex. CASE-2026-001 (optionnel)' : 'e.g. CASE-2026-001 (optional)',
            'notes' => $fr ? 'Points à inclure' : 'Anything to include',
            'notes_ph' => $fr ? 'De quoi parle l\'email ? Dates, montants, documents…' : 'What is this email about? Dates, amounts, documents…',
            'generate' => $fr ? 'Rédiger l\'email' : 'Draft email',
        ],
        'client_mode' => $portal === 'client',
    ];
}

function ai_email_draft_picker_reply(string $portal): string
{
    $fr = ai_mauritius_law_is_fr();
    $payload = ai_email_draft_picker_payload($portal);
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
    $lead = $fr
        ? '**' . $payload['title'] . '**'
        : '**' . $payload['title'] . '**';

    return $lead . "\n\n" . $payload['subtitle']
        . "\n\n[[AI_EMAIL_DRAFT_CARD]]\n" . $json . "\n[[/AI_EMAIL_DRAFT_CARD]]";
}

function ai_email_draft_needs_picker(string $message, string $q, array $kv): bool
{
    if (!empty($kv['topic'])) {
        return false;
    }
    if (preg_match('/\btopic\s*[=:]/iu', $message)) {
        return false;
    }
    $trimmed = trim($q);
    if ($trimmed === '') {
        return true;
    }
    if (preg_match('/^(draft|write|compose|redige|rediger|ecrire|écrire)\s+(a\s+)?(professional\s+)?(email|mail|letter)\s*$/iu', $trimmed)) {
        return true;
    }
    if (preg_match('/^(client\s+)?email\s+(draft|template)?\s*$/iu', $trimmed)) {
        return true;
    }
    if (preg_match('/\b(write|message|email)\s+(to\s+)?(my\s+)?(client|lawyer|avocat)\s*$/iu', $trimmed)) {
        return true;
    }
    if (strlen($trimmed) < 28 && !preg_match('/\b(hearing|invoice|document|appointment|case|dossier|about|regarding|subject)\b/iu', $trimmed)) {
        return true;
    }
    return false;
}

/**
 * @return array<string, mixed>
 */
function ai_email_draft_result_payload(
    string $portal,
    string $topicId,
    string $recipient,
    string $toEmail,
    string $subject,
    string $body,
    ?string $caseLine = null
): array {
    $topic = ai_email_draft_topic($portal, $topicId);
    return [
        'portal' => $portal,
        'topic_id' => $topicId,
        'topic_label' => $topic['label'] ?? '',
        'to_name' => $recipient,
        'to_email' => $toEmail,
        'subject' => $subject,
        'body' => $body,
        'case_line' => $caseLine,
        'labels' => [
            'eyebrow' => ai_mauritius_law_is_fr() ? 'Email prêt à relire' : 'Email ready to review',
            'to' => ai_mauritius_law_is_fr() ? 'Destinataire' : 'To',
            'subject' => ai_mauritius_law_is_fr() ? 'Objet' : 'Subject',
            'body' => ai_mauritius_law_is_fr() ? 'Message' : 'Message',
            'copy_subject' => ai_mauritius_law_is_fr() ? 'Copier l\'objet' : 'Copy subject',
            'copy_body' => ai_mauritius_law_is_fr() ? 'Copier l\'email' : 'Copy email',
            'copy_all' => ai_mauritius_law_is_fr() ? 'Copier tout' : 'Copy all',
            'case' => ai_mauritius_law_is_fr() ? 'Dossier' : 'Case',
            'approve_send' => ai_mauritius_law_is_fr() ? 'Approuver et envoyer' : 'Approve & send',
            'sent_badge' => ai_mauritius_law_is_fr() ? 'Envoyé' : 'Sent',
            'review_hint' => ai_mauritius_law_is_fr()
                ? 'Relisez le brouillon, puis cliquez **Approuver et envoyer** pour l\'envoyer à l\'adresse indiquée.'
                : 'Review the draft, then click **Approve & send** to deliver it to the recipient\'s email.',
        ],
        'can_send' => false,
        'sent' => false,
        'receiver_id' => 0,
        'case_id' => 0,
    ];
}

function ai_email_draft_is_valid_email(string $email): bool
{
    $email = trim(strtolower($email));
    if ($email === '' || str_contains($email, 'recipient@email.com')) {
        return false;
    }
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

function ai_email_draft_can_send(string $portal, array $user): bool
{
    $role = (string) ($user['role'] ?? '');
    if ($portal === 'client') {
        return $role === 'client';
    }
    if ($portal === 'lawyer') {
        return $role === 'lawyer';
    }
    return ai_actions_can_admin($portal, $user);
}

/**
 * @param array<string, mixed> $payload
 * @return array{ok:bool,message:string,payload?:array<string,mixed>}
 */
function ai_email_draft_send_approved(PDO $pdo, array $user, string $portal, array $payload): array
{
    $fr = ai_mauritius_law_is_fr();
    if (!ai_email_draft_can_send($portal, $user)) {
        return ['ok' => false, 'message' => $fr ? 'Permission refusée.' : 'You do not have permission to send this email.'];
    }

    $toEmail = trim(strtolower((string) ($payload['to_email'] ?? '')));
    $toName = trim((string) ($payload['to_name'] ?? ''));
    $subject = trim((string) ($payload['subject'] ?? ''));
    $body = ai_email_draft_finalize_body(trim((string) ($payload['body'] ?? '')));
    $receiverId = (int) ($payload['receiver_id'] ?? 0);
    $caseId = (int) ($payload['case_id'] ?? 0);
    $caseId = $caseId > 0 ? $caseId : null;
    $toUser = null;

    if (!ai_email_draft_is_valid_email($toEmail)) {
        return ['ok' => false, 'message' => $fr ? 'Adresse email du destinataire invalide.' : 'Invalid recipient email address.'];
    }
    if ($subject === '') {
        $subject = $fr ? 'Message du cabinet' : 'Message from the firm';
    }
    if ($body === '') {
        return ['ok' => false, 'message' => $fr ? 'Le message est vide.' : 'Email body is empty.'];
    }

    if ($receiverId > 0) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id=?');
        $stmt->execute([$receiverId]);
        $toUser = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($toUser && strtolower(trim((string) ($toUser['email'] ?? ''))) !== $toEmail) {
            $toUser = null;
            $receiverId = 0;
        }
    }
    if ($receiverId <= 0) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE LOWER(email)=? LIMIT 1');
        $stmt->execute([$toEmail]);
        $toUser = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($toUser) {
            $receiverId = (int) $toUser['id'];
        }
    }

    $from = trim((string) get_setting($pdo, 'smtp_from', ''));
    $headers = "Content-Type: text/plain; charset=UTF-8\r\n";
    if ($from !== '') {
        $headers .= 'From: ' . $from . "\r\n";
    }
    $mailOk = @mail($toEmail, $subject, $body, $headers);

    $inAppOk = false;
    if ($receiverId > 0) {
        try {
            $senderId = (int) $user['id'];
            ensure_contact_message_columns($pdo);
            if ($portal === 'client') {
                $threadId = contact_create_thread($pdo, $senderId, $receiverId, $caseId, $subject, $body);
                $notifyLink = '../lawyer/contact.php?thread=' . $threadId;
            } else {
                $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, case_id, subject, body, status) VALUES (?,?,?,?,?,?)')
                    ->execute([$senderId, $receiverId, $caseId, $subject, $body, 'open']);
                $threadId = (int) $pdo->lastInsertId();
                $pdo->prepare('UPDATE messages SET thread_id=? WHERE id=?')->execute([$threadId, $threadId]);
                $role = (string) (($toUser['role'] ?? '') ?: 'client');
                $notifyLink = $role === 'lawyer'
                    ? '../lawyer/contact.php?thread=' . $threadId
                    : '../client/contact.php?thread=' . $threadId;
            }
            create_notification(
                $pdo,
                $receiverId,
                $fr ? 'Nouveau message' : 'New message',
                $subject,
                'message',
                $notifyLink,
                $senderId
            );
            $inAppOk = true;
        } catch (Throwable $e) {
            $inAppOk = false;
        }
    }

    if (!$mailOk && !$inAppOk) {
        return [
            'ok' => false,
            'message' => $fr
                ? 'Échec de l\'envoi. Vérifiez les paramètres SMTP dans Paramètres → Email.'
                : 'Delivery failed. Check SMTP settings under Settings → Email.',
        ];
    }

    $parts = [];
    if ($mailOk) {
        $label = $toName !== '' ? "{$toName} <{$toEmail}>" : $toEmail;
        $parts[] = $fr ? "Email envoyé à **{$label}**." : "Email sent to **{$label}**.";
    }
    if ($inAppOk) {
        $parts[] = $fr ? 'Message in-app enregistré.' : 'In-app message saved for the recipient.';
    }
    if (!$mailOk && $inAppOk) {
        $parts[] = $fr
            ? '_(Email externe non délivré — seul le message in-app a été enregistré.)_'
            : '_(External email could not be delivered — in-app message was saved.)_';
    }

    $payload['sent'] = true;
    $payload['can_send'] = false;

    return [
        'ok' => true,
        'message' => implode(' ', $parts),
        'payload' => $payload,
    ];
}

function ai_email_draft_result_reply(array $payload, string $storedNote = ''): string
{
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
    $fr = ai_mauritius_law_is_fr();
    $lead = $fr
        ? 'Voici votre brouillon d\'email professionnel.'
        : 'Here\'s your professional email draft.';

    return $lead . $storedNote . "\n\n[[AI_EMAIL_RESULT_CARD]]\n" . $json . "\n[[/AI_EMAIL_RESULT_CARD]]";
}

function ai_email_draft_format_person_name(string $name): string
{
    $name = trim(preg_replace('/\s+/u', ' ', $name));
    if ($name === '') {
        return $name;
    }
    if (function_exists('mb_convert_case')) {
        return mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
    }
    return ucwords(strtolower($name));
}

/**
 * Human-readable topic line for the email body — never the picker label for custom/general.
 */
function ai_email_draft_resolve_topic_text(string $topicId, array $topicDef, ?string $notes): string
{
    $notes = trim((string) $notes);
    if ($notes !== '') {
        $first = preg_split('/\R/u', $notes, 2)[0] ?? $notes;
        $first = trim((string) preg_replace('/[.!?]+$/', '', trim($first)));
        if ($first !== '') {
            return $first;
        }
    }
    if (in_array($topicId, ['custom', 'general_message'], true)) {
        return '';
    }
    return trim((string) ($topicDef['label'] ?? ''));
}

/**
 * Turn free-text notes into a readable block (bullets, capitalisation).
 */
function ai_email_draft_format_notes_block(string $notes): string
{
    $notes = trim(str_replace(["\r\n", "\r"], "\n", $notes));
    if ($notes === '') {
        return '';
    }
    $lines = array_values(array_filter(array_map('trim', explode("\n", $notes)), static fn($l) => $l !== ''));
    $out = [];
    foreach ($lines as $line) {
        $line = preg_replace('/^[-*•]\s*/u', '', $line) ?? $line;
        if ($line === '') {
            continue;
        }
        if (function_exists('mb_strtoupper')) {
            $first = mb_substr($line, 0, 1);
            $rest = mb_substr($line, 1);
            $line = mb_strtoupper($first) . $rest;
        } else {
            $line = ucfirst($line);
        }
        $out[] = '• ' . $line;
    }
    return implode("\n", $out);
}

/** Normalise line breaks for email copy/paste. */
function ai_email_draft_finalize_body(string $body): string
{
    $body = str_replace(["\r\n", "\r"], "\n", $body);
    $body = (string) preg_replace("/[ \t]+\n/u", "\n", $body);
    $body = (string) preg_replace("/\n{3,}/u", "\n\n", trim($body));
    return $body;
}

function ai_email_draft_resolve_subject(string $topicId, array $topicDef, ?string $notes, ?array $case, bool $fr): string
{
    $notes = trim((string) $notes);
    if (in_array($topicId, ['custom', 'general_message'], true) && $notes !== '') {
        $snippet = trim((string) preg_replace('/[.!?]+$/', '', preg_split('/\R/u', $notes, 2)[0] ?? $notes));
        if ($snippet !== '') {
            if (function_exists('mb_strlen') && mb_strlen($snippet) > 72) {
                $snippet = rtrim((string) mb_substr($snippet, 0, 69)) . '…';
            } elseif (strlen($snippet) > 72) {
                $snippet = rtrim(substr($snippet, 0, 69)) . '…';
            }
            return $fr ? ('Concernant : ' . $snippet) : ('Re: ' . $snippet);
        }
    }
    if ($case && !empty($case['case_number']) && !in_array($topicId, ['custom', 'general_message'], true)) {
        $base = trim((string) ($topicDef['subject'] ?? ''));
        if ($base !== '') {
            return $base . ' — ' . $case['case_number'];
        }
    }
    return trim((string) ($topicDef['subject'] ?? ($fr ? 'Concernant votre dossier' : 'Regarding your matter')));
}

function ai_email_draft_parse_subject_body(string $draftBody): array
{
    $subject = '';
    $body = trim($draftBody);
    if (preg_match('/^Subject:\s*(.+)$/mi', $draftBody, $sm)) {
        $subject = trim($sm[1]);
        $body = trim((string) preg_replace('/^Subject:\s*.+\R+/mi', '', $draftBody, 1));
    }
    return [$subject, $body];
}

function ai_email_draft_fallback_body(
    string $portal,
    string $topicId,
    string $recipient,
    string $sender,
    string $company,
    string $topicText,
    ?array $case,
    ?string $notes
): array {
    $topicDef = ai_email_draft_topic($portal, $topicId) ?? ai_email_draft_topic($portal, 'custom');
    $fr = ai_mauritius_law_is_fr();
    $recipient = ai_email_draft_format_person_name($recipient);
    $subject = ai_email_draft_resolve_subject($topicId, $topicDef ?: [], $notes, $case, $fr);
    $caseRef = $case && !empty($case['case_number'])
        ? ($fr ? 'dossier ' . $case['case_number'] : 'matter ' . $case['case_number'])
        : ($fr ? 'votre dossier' : 'your matter');
    $caseTitle = trim((string) ($case['title'] ?? ''));
    $caseLine = $case
        ? ($fr ? 'Dossier : ' : 'Case: ') . ($case['case_number'] ?? '') . ($caseTitle !== '' ? ' — ' . $caseTitle : '')
        : '';
    $notesLine = trim((string) $notes);
    $notesBlock = ai_email_draft_format_notes_block($notesLine);
    $topicText = trim($topicText);

    $salutation = $portal === 'client'
        ? ($fr ? "Bonjour,\n\n" : "Dear Counsel,\n\n")
        : ($fr ? "Bonjour {$recipient},\n\n" : "Dear {$recipient},\n\n");

    $regarding = static function () use ($fr, $topicText, $caseRef, $topicId): string {
        if ($topicText !== '') {
            return $fr
                ? "Je vous écris au sujet de {$topicText}"
                : "I am writing regarding {$topicText}";
        }
        if (in_array($topicId, ['custom', 'general_message'], true)) {
            return $fr
                ? "Je me permets de vous contacter concernant {$caseRef}"
                : "I am writing to you regarding {$caseRef}";
        }
        return $fr
            ? "Je me permets de vous écrire concernant {$caseRef}"
            : "I am writing to you regarding {$caseRef}";
    };

    $close = ($fr ? "Cordialement,\n\n" : "Kind regards,\n\n") . "{$sender}\n{$company}";

    $body = match ($topicId) {
        'hearing_reminder' => $salutation
            . ($fr ? 'Je me permets de vous rappeler votre prochaine audience' : 'I am writing to remind you of your upcoming hearing')
            . ($case && !empty($case['next_hearing_date'])
                ? ($fr ? ', prévue le ' : ', scheduled for ')
                    . date($fr ? 'l j F Y' : 'l, F j, Y', strtotime((string) $case['next_hearing_date']))
                : '')
            . ($caseTitle !== '' ? ($fr ? " dans le cadre de « {$caseTitle} »." : " in connection with “{$caseTitle}”.") : '.')
            . "\n\n"
            . ($fr
                ? "Merci de vous présenter quelques minutes à l'avance avec les documents demandés. Si vous ne pouvez pas vous déplacer, contactez-nous dès que possible afin que nous puissions vous conseiller."
                : "Please arrive a few minutes early with any documents we have requested. If you are unable to attend, please contact us as soon as possible so we can advise on next steps.")
            . ($notesBlock !== '' ? "\n\n" . $notesBlock : ''),
        'case_update' => $salutation
            . $regarding() . '.'
            . "\n\n"
            . ($fr
                ? "Voici un bref point sur l'état actuel du dossier :\n\n"
                    . ($notesBlock !== '' ? $notesBlock : "• [Indiquer les avancées récentes]\n• [Prochaines étapes]")
                : "Here is a brief update on where things stand:\n\n"
                    . ($notesBlock !== '' ? $notesBlock : "• [Recent progress]\n• [Next steps]"))
            . "\n\n"
            . ($fr
                ? "N'hésitez pas à me répondre si vous souhaitez en discuter."
                : "Please reply if you would like to discuss any of the above."),
        'document_request' => $salutation
            . ($fr
                ? "Pour faire avancer votre dossier, nous avons besoin des documents suivants :"
                : "To progress your matter, we need the following documents:")
            . "\n\n"
            . ($notesBlock !== ''
                ? $notesBlock
                : ($fr
                    ? "• [Préciser les pièces requises]"
                    : "• [Specify required items]"))
            . "\n\n"
            . ($fr
                ? "Vous pouvez les transmettre via le portail client ou en réponse à cet email."
                : "You may upload them through the client portal or reply to this email with attachments."),
        'payment_reminder' => $salutation
            . ($fr ? 'Je me permets de revenir vers vous concernant une facture en attente' : 'I am writing regarding an outstanding invoice')
            . ($case && !empty($case['case_number']) ? ($fr ? ' relative au dossier ' : ' relating to matter ') . $case['case_number'] . '.' : '.')
            . "\n\n"
            . ($notesBlock !== ''
                ? $notesBlock
                : ($fr
                    ? 'Merci de nous indiquer si le règlement a déjà été effectué ou, le cas échéant, la date prévue.'
                    : 'Please let us know if payment has already been made, or when we can expect settlement.')),
        'appointment_confirm' => $salutation
            . ($fr ? 'Je confirme notre rendez-vous' : 'This email confirms our appointment')
            . ($notesBlock !== '' ? ":\n\n" . $notesBlock : '.')
            . "\n\n"
            . ($fr
                ? "Si la date ne vous convient pas, merci de me le faire savoir afin que nous puissions reprogrammer."
                : "If this time no longer suits you, please let me know so we can reschedule."),
        'welcome_client' => $salutation
            . ($fr ? 'Bienvenue au cabinet. Nous sommes ravis de vous accompagner' : 'Welcome to our firm. We are pleased to be assisting you')
            . ($caseTitle !== '' ? ($fr ? " pour « {$caseTitle} »." : " with “{$caseTitle}”.") : '.')
            . "\n\n"
            . ($notesBlock !== ''
                ? $notesBlock
                : ($fr
                    ? "Nous reviendrons vers vous prochainement avec les prochaines étapes. En attendant, n'hésitez pas à nous contacter via le portail ou par email."
                    : "We will be in touch shortly with next steps. In the meantime, please reach out through the portal or by email if you have any questions.")),
        'follow_up' => $salutation
            . ($fr ? "Je me permets de revenir vers vous suite à notre dernier échange" : "I am following up on our last conversation")
            . ($case && !empty($case['case_number']) ? ($fr ? ' concernant le dossier ' : ' regarding matter ') . $case['case_number'] . '.' : '.')
            . "\n\n"
            . ($notesBlock !== ''
                ? $notesBlock
                : ($fr
                    ? "Pourriez-vous me faire savoir où en sont les points en attention de votre côté ? Je reste disponible pour en discuter."
                    : "Could you let me know the status of any outstanding items on your side? I am happy to discuss at a time that suits you.")),
        'case_status', 'appointment_request', 'document_question', 'payment_question', 'general_message' => $salutation
            . $regarding() . '.'
            . "\n\n"
            . ($notesBlock !== ''
                ? $notesBlock
                : ($fr
                    ? "Pourriez-vous me faire un retour dès que possible ? Je reste à votre disposition pour toute question."
                    : "Could you please let me know at your earliest convenience? I am happy to answer any questions you may have.")),
        default => $salutation
            . $regarding() . '.'
            . "\n\n"
            . ($notesBlock !== ''
                ? $notesBlock
                : ($fr
                    ? "N'hésitez pas à me faire savoir si vous avez des questions ou souhaitez convenir d'un moment pour en discuter."
                    : "Please let me know if you have any questions or would like to arrange a time to discuss.")),
    };

    $body = ai_email_draft_finalize_body(str_replace('**', '', $body) . "\n\n" . $close);
    return [$subject, $body];
}

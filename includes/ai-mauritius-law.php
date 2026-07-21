<?php
/**
 * Built-in Mauritius law overview + legal-system glossary for the offline AI assistant.
 * Educational reference only — not exhaustive statute text and not formal legal advice.
 */

function ai_mauritius_law_is_fr(): bool
{
    return current_lang() === 'fr';
}

/**
 * @return array<string, string>
 */
function ai_legal_glossary(): array
{
    require_once __DIR__ . '/ai-legal-glossary-data.php';

    if (ai_mauritius_law_is_fr()) {
        // French definitions where available; English fills remaining terms.
        return array_merge(ai_legal_glossary_en(), ai_legal_glossary_fr());
    }

    return ai_legal_glossary_en();
}

function ai_legal_glossary_count(): int
{
    return count(ai_legal_glossary());
}

function ai_mauritius_sources_of_law(): string
{
    if (ai_mauritius_law_is_fr()) {
        return "Sources du droit à Maurice (hiérarchie indicative)\n"
            . "1. Constitution de Maurice — norme suprême\n"
            . "2. Lois du Parlement (Acts) — législation primaire\n"
            . "3. Législation subsidiaire — règlements, ordres, notices pris sous une loi\n"
            . "4. Jurisprudence — décisions des cours (rôle important en common law)\n"
            . "5. Principes généraux / equity — là où la tradition de common law s’applique\n"
            . "6. Coutume — seulement si reconnue et compatible avec la loi écrite\n"
            . "7. Droit international — traités selon leur réception en droit interne";
    }

    return "Sources of law in Mauritius (indicative hierarchy)\n"
        . "1. Constitution of Mauritius — supreme law\n"
        . "2. Acts of Parliament — primary legislation\n"
        . "3. Subsidiary legislation — regulations, orders, notices made under an Act\n"
        . "4. Case law — court decisions (especially important in common-law areas)\n"
        . "5. General principles / equity — where the common-law tradition applies\n"
        . "6. Custom — only where recognised and compatible with written law\n"
        . "7. International law — treaties according to how they are received domestically";
}

function ai_mauritius_legal_system_overview(): string
{
    if (ai_mauritius_law_is_fr()) {
        return "Système juridique mauricien (aperçu)\n"
            . "• Système hybride : forte influence du droit civil français (droit privé) et de la common law anglaise (procédure, certaines matières commerciales/pénales).\n"
            . "• Langue des textes et des procédures : souvent l’anglais ; la terminologie civiliste française reste présente.\n"
            . "• Séparation des pouvoirs : législatif (Assemblée nationale), exécutif, judiciaire.\n"
            . "• La Constitution garantit des droits fondamentaux et organise les institutions.";
    }

    return "Mauritian legal system (overview)\n"
        . "• Hybrid system: strong French civil-law influence (private law) and English common-law influence (procedure and several commercial/criminal areas).\n"
        . "• Language of statutes and proceedings: commonly English; French civil-law terminology remains influential.\n"
        . "• Separation of powers: legislature (National Assembly), executive, and judiciary.\n"
        . "• The Constitution protects fundamental rights and organises State institutions.";
}

function ai_mauritius_courts_overview(): string
{
    if (ai_mauritius_law_is_fr()) {
        return "Organisation judiciaire (aperçu)\n"
            . "• Cour suprême — juridiction supérieure (y compris chambres d’appel civil et pénal selon l’organisation en vigueur)\n"
            . "• Cour intermédiaire — affaires civiles/pénales d’importance intermédiaire\n"
            . "• District Courts — contentieux de première instance / valeur moindre\n"
            . "• Industrial Court — litiges du travail\n"
            . "• Juridictions / tribunaux spécialisés — selon les lois particulières (fiscal, réglementaire, etc.)";
    }

    return "Court structure (overview)\n"
        . "• Supreme Court — superior court (including appellate divisions as organised by law)\n"
        . "• Intermediate Court — mid-level civil/criminal matters\n"
        . "• District Courts — first-instance / lower-value matters\n"
        . "• Industrial Court — employment disputes\n"
        . "• Specialist courts/tribunals — created by particular statutes (tax, regulatory, etc.)";
}

function ai_mauritius_main_law_areas(): string
{
    if (ai_mauritius_law_is_fr()) {
        return "Principales familles de règles / codes et lois types (liste indicative, non exhaustive)\n"
            . "• Droit constitutionnel — Constitution ; lois organiques / institutionnelles\n"
            . "• Droit civil — Code civil (tradition napoléonienne adaptée) : personnes, biens, obligations, contrats, famille\n"
            . "• Procédure civile — Code / règles de procédure civile\n"
            . "• Droit pénal — Code pénal et lois pénales spéciales\n"
            . "• Procédure pénale — règles de procédure pénale et de preuve\n"
            . "• Droit des sociétés / commercial — Companies Act et textes connexes\n"
            . "• Droit du travail — Employment Rights Act et lois sociales connexes\n"
            . "• Droit de la propriété / fonciers — titres, transcription, hypothèques\n"
            . "• Droit de la famille — mariage, divorce, filiation, pension alimentaire\n"
            . "• Droit fiscal — Income Tax Act et textes fiscaux\n"
            . "• Droit de la consommation / concurrence — protection du consommateur et marchés\n"
            . "• Droit de l’environnement — lois environnementales et planification\n"
            . "• Droit bancaire / financier — banques, titres, anti-blanchiment\n"
            . "• Droit administratif — contrôle des actes administratifs\n"
            . "• Droit international privé — conflits de lois et de juridictions\n\n"
            . "Important : Maurice publie des centaines d’Acts et de règlements. Personne ne peut « lister toutes les lois » hors du recueil officiel (Government Gazette / législateur). Ci-dessus : carte des grandes catégories.";
    }

    return "Main families of rules / typical codes and Acts (indicative, not exhaustive)\n"
        . "• Constitutional law — Constitution; institutional statutes\n"
        . "• Civil law — Civil Code (Napoleonic tradition, adapted): persons, property, obligations, contracts, family\n"
        . "• Civil procedure — codes/rules of civil procedure\n"
        . "• Criminal law — Criminal Code and special penal statutes\n"
        . "• Criminal procedure — criminal procedure and evidence rules\n"
        . "• Company / commercial law — Companies Act and related statutes\n"
        . "• Employment / labour law — Employment Rights Act and related social legislation\n"
        . "• Property / land law — title, registration, mortgages/charges\n"
        . "• Family law — marriage, divorce, parentage, maintenance\n"
        . "• Tax law — Income Tax Act and other revenue statutes\n"
        . "• Consumer / competition law — consumer protection and market rules\n"
        . "• Environmental law — environmental and planning statutes\n"
        . "• Banking / financial law — banks, securities, anti-money laundering\n"
        . "• Administrative law — review of administrative action\n"
        . "• Private international law — conflict of laws and jurisdiction\n\n"
        . "Important: Mauritius has hundreds of Acts and regulations. No assistant can list every statute outside the official corpus (Government Gazette / legislature). The list above is a map of the main categories.";
}

function ai_legal_glossary_formatted(): string
{
    $glossary = ai_legal_glossary();
    if (function_exists('mb_strtoupper')) {
        uksort($glossary, static fn(string $a, string $b): int => strcasecmp(ltrim($a, '— '), ltrim($b, '— ')));
    } else {
        ksort($glossary, SORT_STRING | SORT_FLAG_CASE);
    }

    $count = count($glossary);
    $fr = ai_mauritius_law_is_fr();
    $lines = [
        $fr
            ? "Glossaire juridique — {$count} termes (A–Z). Demandez « define … » ou « what is … » pour un terme précis."
            : "Legal glossary — {$count} terms (A–Z). Ask « define … » or « what is … » for any specific term.",
        '',
    ];

    $currentLetter = '';
    foreach ($glossary as $term => $def) {
        $first = strtoupper((string) preg_replace('/^[^a-zA-Z]+/u', '', $term)[0] ?? '#');
        if ($first !== $currentLetter) {
            $currentLetter = $first;
            $lines[] = '— ' . $currentLetter . ' —';
        }
        $lines[] = '• ' . $term . ' — ' . $def;
    }

    return implode("\n", $lines);
}

function ai_wants_legal_definition(string $message): bool
{
    $q = mb_strtolower(trim($message));
    if ($q === '') {
        return false;
    }

    if (preg_match('/\b(our|we|my|firm|dashboard|revenue|invoice|payment|client|case|appointment|how many|total|summarize)\b/iu', $q)) {
        return false;
    }

    if (preg_match(
        '/\b((all\s+)?(the\s+)?(core\s+|essential\s+|main\s+|legal\s+)?definitions|legal\s+glossary|glossary\s+of\s+law|d[eé]finitions?\s+(du|de\s+la|d[\'’]un|essentielles?|juridiques?)|glossaire\s+juridique|toutes\s+les\s+d[eé]finitions)\b/iu',
        $q
    )) {
        return true;
    }

    return (bool) preg_match(
        '/\b(define|definition\s+of|meaning\s+of|what\s+is|what\'s|what\s+does\s+.+\s+mean|explain|qu[\'’]?est-ce|d[eé]finis|d[eé]finition\s+d[e\'’]|signification\s+d[e\'’])\b/iu',
        $q
    );
}

function ai_glossary_display_term(string $term): string
{
    $main = trim((string) preg_split('/\s*\/\s*/', $term)[0]);
    if ($main === '') {
        return $term;
    }
    if (function_exists('mb_convert_case')) {
        return mb_convert_case($main, MB_CASE_TITLE, 'UTF-8');
    }

    return ucwords(strtolower($main));
}

function ai_format_definition_reply(string $term, string $definition, ?string $cite = null, bool $shortDisclaimer = false): string
{
    $fr = ai_mauritius_law_is_fr();
    $lines = [ai_glossary_display_term($term), '', $definition];
    if ($cite !== null && $cite !== '') {
        $lines[] = '';
        $lines[] = ($fr ? 'En droit mauricien : ' : 'Under Mauritian law: ') . $cite;
    }
    $lines[] = '';
    $lines[] = $shortDisclaimer || ($cite === null || $cite === '')
        ? ai_mauritius_law_disclaimer_short()
        : ai_mauritius_law_disclaimer();

    return implode("\n", $lines);
}

/**
 * Extract the term the user wants defined, if phrasing matches.
 */
function ai_legal_glossary_extract_term(string $message): ?string
{
    $q = mb_strtolower(trim($message));
    $q = preg_replace('/[?؟!.]+$/u', '', $q) ?? $q;

    $patterns = [
        '/\b(?:what\s+is|what\'s)\s+(?:a|an|the)?\s*(.+)$/iu',
        '/\b(?:define|explain)\s+(?:a|an|the|me)?\s*(.+)$/iu',
        '/\bdefinition\s+of\s+(?:a|an|the)?\s*(.+)$/iu',
        '/\bmeaning\s+of\s+(?:a|an|the)?\s*(.+)$/iu',
        '/\bwhat\s+does\s+(.+?)\s+mean\b/iu',
        '/\b(?:can\s+you|please)\s+(?:define|explain)\s+(?:a|an|the)?\s*(.+)$/iu',
        '/\bqu[\'’]?est-ce\s+qu[\'’]?(?:un|une|le|la|l[\'’])?\s*(.+)$/iu',
        '/\bd[eé]finis?\s+(?:le|la|l[\'’]|un|une|moi)?\s*(.+)$/iu',
        '/\bd[eé]finition\s+d[e\'’]\s*(?:un|une|le|la|l[\'’])?\s*(.+)$/iu',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $q, $m)) {
            $term = trim((string) ($m[1] ?? ''));
            $term = preg_replace('/\s+(for\s+me|please|in\s+mauritius|in\s+mauritian\s+law)$/iu', '', $term) ?? $term;
            if ($term !== '') {
                return $term;
            }
        }
    }

    return null;
}

function ai_legal_glossary_match_term(string $needle, array $glossary): ?array
{
    $needle = mb_strtolower(trim($needle));
    if ($needle === '') {
        return null;
    }

    $best = null;
    $bestLen = 0;
    $bestScore = 0;

    foreach ($glossary as $key => $def) {
        $aliases = preg_split('/\s*\/\s*/', mb_strtolower($key)) ?: [mb_strtolower($key)];
        foreach ($aliases as $alias) {
            $alias = trim((string) $alias);
            if ($alias === '') {
                continue;
            }
            $score = 0;
            if ($needle === $alias) {
                $score = 100 + mb_strlen($alias);
            } elseif (preg_match('/\b' . preg_quote($alias, '/') . '\b/iu', $needle)) {
                $score = 80 + mb_strlen($alias);
            } elseif (preg_match('/\b' . preg_quote($needle, '/') . '\b/iu', $alias)) {
                $score = 70 + mb_strlen($needle);
            } elseif (str_contains($needle, $alias) || str_contains($alias, $needle)) {
                $score = 40 + min(mb_strlen($alias), mb_strlen($needle));
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestLen = mb_strlen($alias);
                $best = ['term' => $key, 'definition' => $def];
            }
        }
    }

    return $bestScore >= 40 ? $best : null;
}

/**
 * Suggest closest glossary terms when lookup fails.
 *
 * @return list<string>
 */
function ai_legal_glossary_suggest(string $needle, int $limit = 5): array
{
    $needle = mb_strtolower(trim($needle));
    if ($needle === '') {
        return [];
    }

    $scores = [];
    foreach (ai_legal_glossary() as $key => $def) {
        $aliases = preg_split('/\s*\/\s*/', mb_strtolower($key)) ?: [mb_strtolower($key)];
        $max = 0;
        foreach ($aliases as $alias) {
            $alias = trim((string) $alias);
            if ($alias === '') {
                continue;
            }
            similar_text($needle, $alias, $pct);
            if ($pct > $max) {
                $max = $pct;
            }
            if (str_contains($alias, $needle) || str_contains($needle, $alias)) {
                $max = max($max, 55.0);
            }
        }
        if ($max >= 45) {
            $scores[$key] = $max;
        }
    }

    arsort($scores, SORT_NUMERIC);

    return array_slice(array_keys($scores), 0, max(1, $limit));
}

function ai_legal_glossary_lookup(string $message): ?string
{
    $q = mb_strtolower(trim($message));
    $glossary = ai_legal_glossary();

    $extracted = ai_legal_glossary_extract_term($message);
    if ($extracted !== null) {
        $match = ai_legal_glossary_match_term($extracted, $glossary);
        if ($match !== null) {
            return ai_format_definition_reply($match['term'], $match['definition']);
        }
    }

    $wantsDef = ai_wants_legal_definition($message);
    if ($wantsDef) {
        foreach ($glossary as $key => $def) {
            $aliases = preg_split('/\s*\/\s*/', mb_strtolower($key)) ?: [mb_strtolower($key)];
            foreach ($aliases as $alias) {
                $alias = trim((string) $alias);
                if ($alias !== '' && preg_match('/\b' . preg_quote($alias, '/') . '\b/iu', $q)) {
                    return ai_format_definition_reply($key, $def);
                }
            }
        }
    }

    return null;
}

/**
 * Answer definition requests from the legal glossary (and related sources).
 */
function ai_try_legal_definition_reply(string $message): ?string
{
    if (!ai_wants_legal_definition($message)) {
        return null;
    }

    $q = mb_strtolower(trim($message));

    $wantsFullGlossary = (bool) preg_match(
        '/\b((all\s+)?(the\s+)?(core\s+|essential\s+|main\s+|legal\s+)?definitions|legal\s+glossary|glossary\s+of\s+law|d[eé]finitions?\s+(du|de\s+la|d[\'’]un|essentielles?|juridiques?)|glossaire\s+juridique|toutes\s+les\s+d[eé]finitions)\b/iu',
        $q
    );
    if ($wantsFullGlossary) {
        return ai_legal_glossary_formatted() . "\n\n" . ai_mauritius_law_disclaimer();
    }

    $lookup = ai_legal_glossary_lookup($message);
    if ($lookup !== null) {
        return $lookup;
    }

    return null;
}

function ai_mauritius_law_disclaimer_short(): string
{
    if (ai_mauritius_law_is_fr()) {
        return 'Avertissement : aperçu éducatif uniquement — pas un conseil juridique formel.';
    }

    return 'Disclaimer: educational overview only — not formal legal advice.';
}

function ai_mauritius_law_disclaimer(): string
{
    if (ai_mauritius_law_is_fr()) {
        return "Avertissement : aperçu éducatif pour ce cabinet. Ce n’est pas un conseil juridique formel, ni le texte officiel complet des lois. Vérifiez toujours la Constitution, les Acts à jour et la Government Gazette.";
    }
    return "Disclaimer: educational overview for this firm workspace. This is not formal legal advice and not the full official text of every statute. Always verify against the Constitution, current Acts, and the Government Gazette.";
}

/**
 * Returns a Mauritius-law / legal-definitions reply, or null if the message is unrelated.
 */
function ai_try_mauritius_law_reply(string $message): ?string
{
    $q = mb_strtolower(trim($message));
    if ($q === '') {
        return null;
    }

    // Prefer a single-term definition answer when clearly asked.
    $lookup = ai_legal_glossary_lookup($message);
    $wantsFullGlossary = (bool) preg_match(
        '/\b((all\s+)?(the\s+)?(core\s+|essential\s+|main\s+)?definitions|legal\s+definitions|legal\s+glossary|glossary\s+of\s+law|d[eé]finitions?\s+(du|de\s+la|d[\'’]un|essentielles?)|glossaire\s+juridique|toutes\s+les\s+d[eé]finitions)\b/iu',
        $q
    );
    $wantsMauritiusLaws = (bool) (
        preg_match('/\b(mauritius|mauritian|maurice|mauricien(?:ne)?s?)\b/iu', $q)
        && preg_match('/\b(law|laws|legal|legislation|statute|statutes|code|codes|rule|rules|droit|loi|lois|l[eé]gislation|r[eè]gles?)\b/iu', $q)
    );
    $wantsListLaws = (bool) preg_match(
        '/\b(list\s+(all\s+)?(the\s+)?(laws|rules|codes|statutes|law\s+rules)|all\s+(the\s+)?(laws|law\s+rules|rules\s+of\s+law)|toutes\s+les\s+lois|liste\s+(des\s+)?lois|tous\s+les\s+codes)\b/iu',
        $q
    );
    $wantsSystem = (bool) preg_match(
        '/\b(sources\s+of\s+law|court\s+structure|hierarchy\s+of\s+(laws|norms)|sources\s+du\s+droit|organisation\s+judiciaire|hi[eé]rarchie\s+des\s+normes)\b/iu',
        $q
    );
    $wantsLegalSystem = (bool) preg_match('/\b(legal\s+system|syst[eè]me\s+juridique)\b/iu', $q);
    $wantsBranches = (bool) preg_match(
        '/\b(branches\s+of\s+law|areas\s+of\s+law|types\s+of\s+law|familles\s+de\s+droit|branches\s+du\s+droit)\b/iu',
        $q
    );

    // Definitions-only asks (including “definitions … legal system”)
    if ($wantsFullGlossary && !$wantsMauritiusLaws && !$wantsListLaws && !$wantsSystem && !$wantsBranches) {
        $parts = [ai_legal_glossary_formatted()];
        if ($wantsLegalSystem) {
            array_unshift($parts, ai_mauritius_legal_system_overview());
        }
        return implode("\n\n", $parts) . "\n\n" . ai_mauritius_law_disclaimer();
    }

    if (!$lookup && !$wantsFullGlossary && !$wantsMauritiusLaws && !$wantsListLaws && !$wantsSystem && !$wantsBranches && !$wantsLegalSystem) {
        return null;
    }

    // Single definition only (no Mauritius/system dump) when that is all they asked.
    if ($lookup && !$wantsFullGlossary && !$wantsMauritiusLaws && !$wantsListLaws && !$wantsSystem && !$wantsBranches && !$wantsLegalSystem) {
        return $lookup;
    }

    $parts = [];
    if ($wantsMauritiusLaws || $wantsListLaws || $wantsSystem || $wantsBranches || ($wantsLegalSystem && !$wantsFullGlossary)) {
        $parts[] = ai_mauritius_legal_system_overview();
        $parts[] = ai_mauritius_sources_of_law();
        $parts[] = ai_mauritius_courts_overview();
        $parts[] = ai_mauritius_main_law_areas();
    }
    if ($wantsFullGlossary) {
        $parts[] = ai_legal_glossary_formatted();
    } elseif ($lookup) {
        $parts[] = $lookup;
    } elseif ($wantsLegalSystem && !$wantsMauritiusLaws && !$wantsListLaws && !$wantsSystem && !$wantsBranches) {
        // Bare “legal system” → overview + glossary
        $parts = [
            ai_mauritius_legal_system_overview(),
            ai_mauritius_sources_of_law(),
            ai_legal_glossary_formatted(),
        ];
    }

    if (!$parts) {
        return null;
    }

    return implode("\n\n", $parts) . "\n\n" . ai_mauritius_law_disclaimer();
}

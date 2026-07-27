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
        return "**Sources du droit à Maurice**\n"
            . "_Hiérarchie indicative — de la norme la plus élevée à la plus accessoire :_\n\n"
            . "1. **Constitution de Maurice** — norme suprême ; droits fondamentaux et organisation de l'État\n"
            . "2. **Lois du Parlement (Acts)** — législation primaire adoptée par l'Assemblée nationale\n"
            . "3. **Législation subsidiaire** — règlements, ordres et notices pris en vertu d'une loi habilitante\n"
            . "4. **Jurisprudence** — décisions des cours ; rôle central en common law et en interprétation\n"
            . "5. **Principes généraux et equity** — là où la tradition de common law s'applique\n"
            . "6. **Coutume** — uniquement si reconnue et compatible avec la loi écrite\n"
            . "7. **Droit international** — traités et conventions selon leur réception en droit interne";
    }

    return "**Sources of Law in Mauritius**\n"
        . "_Indicative hierarchy — highest authority first:_\n\n"
        . "1. **Constitution of Mauritius** — supreme law; fundamental rights and State institutions\n"
        . "2. **Acts of Parliament** — primary legislation enacted by the National Assembly\n"
        . "3. **Subsidiary legislation** — regulations, orders, and notices made under an enabling Act\n"
        . "4. **Case law** — court decisions; especially important in common-law areas and interpretation\n"
        . "5. **General principles and equity** — where the English common-law tradition applies\n"
        . "6. **Custom** — only where recognised and compatible with written law\n"
        . "7. **International law** — treaties and conventions as received into domestic law";
}

function ai_mauritius_legal_system_overview(): string
{
    if (ai_mauritius_law_is_fr()) {
        return "**Système juridique mauricien — Aperçu**\n\n"
            . "Maurice applique un **système juridique hybride** : le droit privé s'inspire fortement de la tradition civiliste française, tandis que la procédure et plusieurs matières commerciales et pénales relèvent davantage de la common law anglaise.\n\n"
            . "**Points essentiels**\n"
            . "• **Influence civiliste** — Code civil adapté : personnes, biens, obligations, contrats, famille\n"
            . "• **Influence common law** — procédure, preuve, droit pénal et commercial dans de larges mesures\n"
            . "• **Langues** — l'anglais domine les textes législatifs et les procédures ; la terminologie civiliste française reste présente\n"
            . "• **Séparation des pouvoirs** — Assemblée nationale (législatif), Gouvernement (exécutif), magistrature (judiciaire)\n"
            . "• **Constitution** — garantit les droits fondamentaux et encadre les institutions publiques";
    }

    return "**Mauritian Legal System — Overview**\n\n"
        . "Mauritius operates a **hybrid legal system**: private law draws heavily on the French civil-law tradition, while procedure and several commercial and criminal areas follow English common-law methods.\n\n"
        . "**Key characteristics**\n"
        . "• **Civil-law influence** — adapted Civil Code: persons, property, obligations, contracts, family\n"
        . "• **Common-law influence** — procedure, evidence, and much of commercial and criminal law\n"
        . "• **Language** — statutes and court proceedings are commonly in English; French civil-law terminology remains influential\n"
        . "• **Separation of powers** — National Assembly (legislature), Government (executive), judiciary\n"
        . "• **Constitution** — protects fundamental rights and organises State institutions";
}

function ai_mauritius_courts_overview(): string
{
    if (ai_mauritius_law_is_fr()) {
        return "**Organisation judiciaire**\n"
            . "_Structure indicative des juridictions principales :_\n\n"
            . "• **Cour suprême** — juridiction supérieure ; divisions d'appel et de première instance selon la loi\n"
            . "• **Cour intermédiaire (Intermediate Court)** — affaires civiles et pénales de importance intermédiaire\n"
            . "• **District Courts** — première instance et contentieux de moindre valeur\n"
            . "• **Industrial Court** — litiges du travail et relations employeur–salarié\n"
            . "• **Juridictions spécialisées** — tribunaux créés par des lois particulières (fiscal, réglementaire, etc.)";
    }

    return "**Court Structure**\n"
        . "_Indicative layout of the main courts:_\n\n"
        . "• **Supreme Court** — superior court of record; appellate and first-instance divisions as organised by law\n"
        . "• **Intermediate Court** — mid-level civil and criminal matters\n"
        . "• **District Courts** — first-instance and lower-value disputes\n"
        . "• **Industrial Court** — employment and industrial-relations disputes\n"
        . "• **Specialist courts and tribunals** — established under particular statutes (tax, regulatory, and other specialised areas)";
}

function ai_mauritius_main_law_areas(): string
{
    if (ai_mauritius_law_is_fr()) {
        return "**Principales branches du droit**\n"
            . "_Carte indicative — liste non exhaustive des familles de règles et textes types :_\n\n"
            . "**Droit public et institutionnel**\n"
            . "• Droit constitutionnel — Constitution ; lois organiques et institutionnelles\n"
            . "• Droit pénal — Code pénal et lois pénales spéciales\n"
            . "• Procédure pénale — règles de procédure et de preuve\n"
            . "• Droit administratif — contrôle et recours contre l'action administrative\n"
            . "• Droit fiscal — Income Tax Act et autres textes fiscaux\n\n"
            . "**Droit privé et civil**\n"
            . "• Droit civil — Code civil (tradition napoléonienne adaptée)\n"
            . "• Procédure civile — codes et règles de procédure civile\n"
            . "• Droit de la famille — mariage, divorce, filiation, pension alimentaire\n"
            . "• Droit de la propriété — titres, transcription, hypothèques et charges\n"
            . "• Droit international privé — conflits de lois et de juridictions\n\n"
            . "**Droit économique et réglementaire**\n"
            . "• Droit des sociétés et commercial — Companies Act et textes connexes\n"
            . "• Droit du travail — Employment Rights Act et législation sociale\n"
            . "• Droit bancaire et financier — banques, titres, anti-blanchiment\n"
            . "• Consommation et concurrence — protection du consommateur et des marchés\n"
            . "• Environnement et urbanisme — lois environnementales et de planification\n\n"
            . "**Note importante** — Maurice compte des centaines d'Acts et de règlements. Cette liste est une **carte thématique**, pas le recueil officiel. Vérifiez toujours les textes à jour sur [legislation.govmu.org](https://legislation.govmu.org) ou dans la **Government Gazette**.";
    }

    return "**Main Branches of Law**\n"
        . "_Indicative map — not an exhaustive list of every statute:_\n\n"
        . "**Public and institutional law**\n"
        . "• Constitutional law — Constitution; institutional and organic statutes\n"
        . "• Criminal law — Criminal Code and special penal statutes\n"
        . "• Criminal procedure — procedure and rules of evidence\n"
        . "• Administrative law — review and challenge of administrative action\n"
        . "• Tax law — Income Tax Act and other revenue statutes\n\n"
        . "**Private and civil law**\n"
        . "• Civil law — Civil Code (adapted Napoleonic tradition)\n"
        . "• Civil procedure — codes and rules of civil procedure\n"
        . "• Family law — marriage, divorce, parentage, maintenance\n"
        . "• Property and land law — title, registration, mortgages, and charges\n"
        . "• Private international law — conflict of laws and jurisdiction\n\n"
        . "**Commercial and regulatory law**\n"
        . "• Company and commercial law — Companies Act and related statutes\n"
        . "• Employment and labour law — Employment Rights Act and social legislation\n"
        . "• Banking and financial law — banks, securities, anti-money laundering\n"
        . "• Consumer and competition law — consumer protection and market regulation\n"
        . "• Environmental and planning law — environmental and land-use statutes\n\n"
        . "**Important note** — Mauritius has hundreds of Acts and regulations. This is a **thematic map**, not the official corpus. Always verify current text on [legislation.govmu.org](https://legislation.govmu.org) or in the **Government Gazette**.";
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
        return "**Avertissement** — Aperçu éducatif pour ce cabinet. Ce n'est pas un conseil juridique formel ni le texte officiel intégral des lois. Vérifiez toujours la Constitution, les Acts à jour et la Government Gazette.";
    }

    return "**Disclaimer** — Educational overview for this firm workspace. This is not formal legal advice and not the full official text of every statute. Always verify against the Constitution, current Acts, and the Government Gazette.";
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

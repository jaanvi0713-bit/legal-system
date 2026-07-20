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
 * @return array<string, string> term => definition
 */
function ai_legal_glossary(): array
{
    if (ai_mauritius_law_is_fr()) {
        return [
            'constitution' => 'Loi fondamentale suprême de l’État. Toute autre norme doit y être conforme.',
            'loi' => 'Texte adopté par le Parlement (National Assembly). En Maurice, les lois sont souvent publiées en anglais.',
            'règlement / législation subsidiaire' => 'Normes prises sous l’autorité d’une loi (règlements, ordres, notices) par le pouvoir exécutif.',
            'jurisprudence' => 'Ensemble des décisions judiciaires. Elle guide l’interprétation, surtout dans les matières de common law.',
            'précédent' => 'Décision antérieure pouvant orienter ou lier des affaires semblables (surtout dans la tradition de common law).',
            'droit civil' => 'Branche régissant les rapports entre personnes privées (contrats, biens, famille, responsabilité civile).',
            'droit pénal' => 'Branche définissant les infractions et les peines.',
            'droit commercial' => 'Règles applicables aux entreprises, sociétés, commerce et insolvabilité.',
            'droit du travail' => 'Règles relatives à l’emploi, aux contrats de travail, aux salaires et aux relations collectives.',
            'droit administratif' => 'Règles régissant l’action de l’administration et le contrôle de ses actes.',
            'droit constitutionnel' => 'Organisation des pouvoirs publics et protection des droits fondamentaux.',
            'droit international' => 'Règles entre États et organisations internationales ; traités et coutume internationale.',
            'procédure civile' => 'Règles de forme pour saisir un tribunal civil et mener un procès.',
            'procédure pénale' => 'Règles de forme pour enquêter, poursuivre et juger les infractions.',
            'demandeur / plaignant' => 'Partie qui engage une action en justice.',
            'défendeur' => 'Partie contre laquelle l’action est dirigée.',
            'plaideur' => 'Toute partie à une instance.',
            'avocat' => 'Professionnel du droit qui conseille et représente les clients.',
            'magistrat / juge' => 'Autorité judiciaire qui tranche les litiges.',
            'tribunal / cour' => 'Institution chargée de rendre la justice.',
            'compétence' => 'Pouvoir d’une juridiction de connaître d’une affaire (matière, territoire, degré).',
            'pourvoi / appel' => 'Voie de recours contre une décision devant une juridiction supérieure.',
            'jugement / arrêt' => 'Décision rendue par une juridiction.',
            'injonction' => 'Ordonnance du tribunal obligeant ou interdisant un comportement.',
            'contrat' => 'Accord de volontés créant des obligations juridiquement sanctionnées.',
            'obligation' => 'Lien de droit obligeant une personne à donner, faire ou ne pas faire quelque chose.',
            'responsabilité civile' => 'Devoir de réparer un dommage causé à autrui (contractuelle ou délictuelle).',
            'délit civil (tort)' => 'Fait fautif causant un préjudice ouvrant droit à réparation (tradition de common law).',
            'dommages-intérêts' => 'Somme allouée pour réparer un préjudice.',
            'preuve' => 'Éléments produits pour établir un fait devant le juge.',
            'charge de la preuve' => 'Obligation pour une partie de prouver les faits qu’elle allègue.',
            'prescription' => 'Extinction d’un droit ou d’une action par l’écoulement du temps.',
            'prescription extinctive' => 'Perte du droit d’agir en justice après un délai légal.',
            'propriété' => 'Droit de jouir et disposer d’un bien dans les limites de la loi.',
            'sûreté / garantie' => 'Mécanisme protégeant un créancier (hypothèque, nantissement, caution, etc.).',
            'société' => 'Personne morale formée pour exercer une activité selon le droit des sociétés.',
            'faillite / insolvabilité' => 'Situation où le débiteur ne peut plus faire face à ses dettes exigibles.',
            'equity' => 'Ensemble de principes d’équité issus de la common law anglaise, complétant le droit strict.',
            'habeas corpus' => 'Recours protégeant contre une détention illégale.',
            'due process / équité procédurale' => 'Garantie d’une procédure juste et équitable.',
            'common law' => 'Tradition juridique anglaise fondée sur la jurisprudence et l’équité.',
            'civil law (tradition romaniste)' => 'Tradition issue du droit romain / codes écrits (forte influence française à Maurice pour le droit privé).',
        ];
    }

    return [
        'constitution' => 'The supreme law of the State. All other laws and acts must conform to it.',
        'act / statute / law' => 'Primary legislation passed by Parliament (the National Assembly). In Mauritius, statutes are commonly published in English.',
        'subsidiary legislation / regulations' => 'Rules made under authority of an Act (regulations, orders, notices) by the executive.',
        'case law / jurisprudence' => 'Body of judicial decisions. It guides interpretation, especially in common-law areas.',
        'precedent' => 'An earlier decision that may guide or bind later similar cases (especially in the common-law tradition).',
        'civil law (subject)' => 'Rules governing private relations (contracts, property, family, civil liability).',
        'criminal law' => 'Rules defining offences and penalties.',
        'commercial / company law' => 'Rules for businesses, companies, trade, and insolvency.',
        'employment / labour law' => 'Rules on employment contracts, wages, and workplace relations.',
        'administrative law' => 'Rules governing public administration and review of administrative action.',
        'constitutional law' => 'Organisation of public powers and protection of fundamental rights.',
        'international law' => 'Rules between States and international organisations; treaties and custom.',
        'civil procedure' => 'Formal rules for starting and conducting civil proceedings.',
        'criminal procedure' => 'Formal rules for investigation, prosecution, and trial of offences.',
        'plaintiff / claimant' => 'The party who brings a civil claim.',
        'defendant' => 'The party against whom a claim or charge is brought.',
        'litigant' => 'Any party to court proceedings.',
        'attorney / counsel / barrister / solicitor' => 'Legal professionals who advise and represent clients (titles vary by practice and jurisdiction).',
        'judge / magistrate' => 'Judicial officer who decides disputes.',
        'court / tribunal' => 'Institution empowered to administer justice.',
        'jurisdiction' => 'Authority of a court to hear a matter (subject-matter, territory, level).',
        'appeal' => 'Challenge of a decision before a higher court.',
        'judgment / order' => 'Decision issued by a court.',
        'injunction' => 'Court order requiring or prohibiting conduct.',
        'contract' => 'Agreement creating legally enforceable obligations.',
        'obligation' => 'Legal duty to give, do, or refrain from doing something.',
        'civil liability' => 'Duty to repair harm caused to another (contractual or delictual/tortious).',
        'tort' => 'Civil wrong (other than breach of contract) that may give rise to damages.',
        'damages' => 'Money awarded to compensate loss or injury.',
        'evidence' => 'Material presented to prove facts before a court.',
        'burden of proof' => 'Duty of a party to prove the facts it alleges.',
        'limitation / prescription' => 'Time limit after which a claim may no longer be brought.',
        'property / ownership' => 'Right to use and dispose of a thing within the limits of the law.',
        'security / charge' => 'Device protecting a creditor (mortgage, pledge, guarantee, etc.).',
        'company / corporation' => 'Legal person formed to carry on business under company law.',
        'insolvency / bankruptcy' => 'Situation where a debtor cannot meet due debts.',
        'equity' => 'Body of fairness principles from English law that supplement strict legal rules.',
        'habeas corpus' => 'Remedy protecting against unlawful detention.',
        'due process / natural justice' => 'Guarantee of a fair and impartial procedure.',
        'common law' => 'English legal tradition based largely on judicial decisions and equity.',
        'civil law (tradition)' => 'Romanist/codified tradition (strong French influence on Mauritian private law).',
    ];
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
    $lines = [];
    foreach (ai_legal_glossary() as $term => $def) {
        $lines[] = '• ' . $term . ' — ' . $def;
    }
    $head = ai_mauritius_law_is_fr()
        ? "Définitions essentielles d’un système juridique\n"
        : "Core definitions used in a legal system\n";
    return $head . implode("\n", $lines);
}

function ai_legal_glossary_lookup(string $message): ?string
{
    $q = mb_strtolower(trim($message));
    $q = preg_replace('/[?؟!.]+$/u', '', $q) ?? $q;

    // "what is X" / "define X" / "definition of X" / "qu'est-ce que X"
    $term = null;
    if (preg_match('/^(?:what\s+is\s+(?:a|an|the)?\s*|define\s+(?:the\s+)?|definition\s+of\s+(?:a|an|the)?\s*|meaning\s+of\s+(?:a|an|the)?\s*|qu[\'’]?est-ce\s+qu[\'’]?(?:un|une|le|la|l[\'’])?\s*|d[eé]finis?\s+(?:le|la|l[\'’]|un|une)?\s*|d[eé]finition\s+d[e\'’]\s*(?:un|une|le|la|l[\'’])?\s*)(.+)$/iu', $q, $m)) {
        $term = trim($m[1]);
    }

    $glossary = ai_legal_glossary();
    if ($term !== null && $term !== '') {
        foreach ($glossary as $key => $def) {
            $keyLower = mb_strtolower($key);
            $aliases = preg_split('/\s*\/\s*/', $keyLower) ?: [$keyLower];
            foreach ($aliases as $alias) {
                $alias = trim($alias);
                if ($alias !== '' && (mb_strpos($term, $alias) !== false || mb_strpos($alias, $term) !== false || $term === $alias)) {
                    $label = ai_mauritius_law_is_fr() ? 'Définition' : 'Definition';
                    return $label . ': ' . $key . "\n" . $def;
                }
            }
        }
    }

    // Free-text contains a glossary term with define/meaning intent nearby
    $wantsDef = (bool) preg_match('/\b(define|definition|meaning|glossary|d[eé]finition|d[eé]finir|signification|glossaire|qu[\'’]?est-ce)\b/iu', $q);
    if ($wantsDef) {
        foreach ($glossary as $key => $def) {
            $aliases = preg_split('/\s*\/\s*/', mb_strtolower($key)) ?: [mb_strtolower($key)];
            foreach ($aliases as $alias) {
                $alias = trim((string) $alias);
                if ($alias !== '' && preg_match('/\b' . preg_quote($alias, '/') . '\b/iu', $q)) {
                    $label = ai_mauritius_law_is_fr() ? 'Définition' : 'Definition';
                    return $label . ': ' . $key . "\n" . $def;
                }
            }
        }
    }

    return null;
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
        return $lookup . "\n\n" . ai_mauritius_law_disclaimer();
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

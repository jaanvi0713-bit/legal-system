<?php
/**
 * Built-in searchable Mauritius law corpus — section-level citations without external AI.
 * Educational reference only; verify against the official Government Gazette.
 */

require_once __DIR__ . '/ai-mauritius-law.php';

/**
 * @return list<array{act:string,section:string,title:string,summary:string,summary_fr:string,keywords:list<string>}>
 */
function ai_mauritius_law_corpus(): array
{
    return [
        [
            'act' => 'Constitution of Mauritius',
            'section' => 's. 1',
            'title' => 'The State and sovereignty',
            'summary' => 'Mauritius is a sovereign democratic State. The Constitution is the supreme law; any other law inconsistent with it is void to the extent of the inconsistency.',
            'summary_fr' => 'Maurice est un État souverain démocratique. La Constitution est la loi suprême ; toute loi incompatible avec elle est nulle dans la mesure de l’incompatibilité.',
            'keywords' => ['constitution', 'supreme law', 'sovereign', 'state', 'loi suprême', 'souveraineté'],
        ],
        [
            'act' => 'Constitution of Mauritius',
            'section' => 's. 3–19 (Chapter II)',
            'title' => 'Protection of fundamental rights and freedoms of the individual',
            'summary' => 'Chapter II protects fundamental rights including: right to life (s. 3); freedom from slavery and forced labour (s. 4–6); protection from arbitrary arrest and detention, habeas corpus (s. 5); freedom of conscience, expression, assembly, and association (s. 9–13); protection from discrimination (s. 16); protection of property (s. 8); right to fair hearing / natural justice (s. 10). Restrictions must be reasonably justified in a democratic society and authorised by law.',
            'summary_fr' => 'Le chapitre II protège les droits fondamentaux : droit à la vie (art. 3) ; liberté d’expression, de réunion, d’association (art. 9–13) ; interdiction de la discrimination (art. 16) ; protection de la propriété (art. 8) ; droit à un procès équitable (art. 10). Les restrictions doivent être prévues par la loi et justifiées dans une société démocratique.',
            'keywords' => ['fundamental rights', 'human rights', 'freedom of expression', 'discrimination', 'habeas corpus', 'natural justice', 'fair hearing', 'droits fondamentaux', 'liberté d\'expression', 'discrimination', 'constitution chapter 2'],
        ],
        [
            'act' => 'Constitution of Mauritius',
            'section' => 's. 80–81',
            'title' => 'Parliament and law-making',
            'summary' => 'Parliament (the National Assembly) has power to make laws for Mauritius. Bills become Acts when passed and assented to by the President. Subsidiary legislation is made under authority of an Act.',
            'summary_fr' => 'Le Parlement (Assemblée nationale) a le pouvoir de légiférer. Les projets de loi deviennent des Acts après adoption et sanction. La législation subsidiaire est prise sous l’autorité d’une loi.',
            'keywords' => ['parliament', 'national assembly', 'legislation', 'acts', 'law making', 'parlement', 'assemblée nationale', 'lois'],
        ],
        [
            'act' => 'Civil Code (Mauritius)',
            'section' => 'Art. 1134',
            'title' => 'Agreements legally entered into',
            'summary' => 'Agreements legally entered into take the place of law for those who have made them. They may be revoked only by mutual consent or for causes authorised by law. This is the foundational rule of contractual binding force in Mauritian civil law.',
            'summary_fr' => 'Les conventions légalement formées tiennent lieu de loi à ceux qui les ont faits. Elles ne peuvent être révoquées que de consentement mutuel ou pour causes que la loi autorise. Règle fondamentale de la force obligatoire des contrats.',
            'keywords' => ['contract', 'agreement', 'obligation', 'binding', 'civil code', '1134', 'contrat', 'convention', 'force obligatoire'],
        ],
        [
            'act' => 'Civil Code (Mauritius)',
            'section' => 'Art. 1147',
            'title' => 'Breach of contract — damages',
            'summary' => 'When a debtor fails to perform an obligation without lawful excuse, the creditor may claim damages for loss suffered. Contractual liability arises from non-performance or defective performance of agreed obligations.',
            'summary_fr' => 'Lorsque le débiteur n’exécute pas son obligation sans excuse légitime, le créancier peut demander des dommages-intérêts pour le préjudice subi.',
            'keywords' => ['breach of contract', 'damages', 'non-performance', '1147', 'inexécution', 'dommages-intérêts', 'contrat'],
        ],
        [
            'act' => 'Civil Code (Mauritius)',
            'section' => 'Art. 1382 (delictual liability tradition)',
            'title' => 'Civil liability for fault',
            'summary' => 'Under the French civil-law tradition applied in Mauritius, any act of a person causing damage to another obliges the person at fault to repair the damage. This underpins tort/delict claims alongside common-law tort principles.',
            'summary_fr' => 'Tout fait quelconque de l’homme qui cause à autrui un dommage oblige celui par la faute duquel il est arrivé à le réparer. Base de la responsabilité délictuelle.',
            'keywords' => ['tort', 'delict', 'civil liability', 'fault', 'negligence', '1382', 'responsabilité civile', 'faute', 'délit'],
        ],
        [
            'act' => 'Civil Code (Mauritius)',
            'section' => 'Art. 544',
            'title' => 'Ownership (property)',
            'summary' => 'Ownership is the right to enjoy and dispose of a thing in the most absolute manner, provided it is not used in a way prohibited by laws or regulations.',
            'summary_fr' => 'La propriété est le droit de jouir et disposer des choses de la manière la plus absolue, pourvu qu’on n’en fasse pas un usage prohibé par les lois.',
            'keywords' => ['property', 'ownership', '544', 'propriété', 'land', 'title'],
        ],
        [
            'act' => 'Civil Code (Mauritius)',
            'section' => 'Arts. 144–148 (marriage)',
            'title' => 'Marriage — consent and capacity',
            'summary' => 'Marriage requires free consent of both parties and compliance with capacity rules. Lack of consent or legal impediments may affect validity. Family matters are governed by the Civil Code and related Acts.',
            'summary_fr' => 'Le mariage exige le consentement libre des deux parties et le respect des conditions de capacité. Les questions familiales relèvent du Code civil et des lois connexes.',
            'keywords' => ['marriage', 'family', 'divorce', 'mariage', 'famille', 'consent'],
        ],
        [
            'act' => 'Limitation Act 1969',
            'section' => 's. 4–5',
            'title' => 'Limitation periods for civil actions',
            'summary' => 'Civil claims are subject to limitation periods after which they may become time-barred. The Limitation Act sets default periods (commonly 6 years for contract and tort claims, with variations for specific matters). The clock generally runs from when the cause of action accrues.',
            'summary_fr' => 'Les actions civiles sont soumises à des délais de prescription. La Limitation Act fixe les délais (souvent 6 ans pour contrats et délits, avec variations). Le délai court généralement à partir de la naissance de l’action.',
            'keywords' => ['limitation', 'prescription', 'time bar', '6 years', 'statute of limitations', 'prescription', 'délai', 'limitation act'],
        ],
        [
            'act' => 'Employment Rights Act 2008',
            'section' => 's. 3–4',
            'title' => 'Scope and application',
            'summary' => 'The Employment Rights Act 2008 governs employment relationships in Mauritius, setting minimum standards for workers including terms of employment, termination, and dispute resolution through the Industrial Court.',
            'summary_fr' => 'L’Employment Rights Act 2008 régit les relations de travail à Maurice, fixant des normes minimales et le recours à l’Industrial Court.',
            'keywords' => ['employment rights act', 'era 2008', 'employment', 'worker', 'travail', 'droit du travail'],
        ],
        [
            'act' => 'Employment Rights Act 2008',
            'section' => 's. 45–49',
            'title' => 'Unfair dismissal / termination of employment',
            'summary' => 'An employer must not terminate employment unfairly. Termination is unfair if not based on a valid reason related to capacity, conduct, operational requirements, or other grounds recognised by the Act. An employee dismissed unfairly may claim reinstatement, re-engagement, or compensation before the Industrial Court. Written reasons for dismissal may be required on request.',
            'summary_fr' => 'L’employeur ne doit pas mettre fin au contrat de manière abusive. Un licenciement est injustifié s’il n’est pas fondé sur un motif valable (capacité, conduite, nécessités opérationnelles, etc.). Le salarié peut saisir l’Industrial Court pour réintégration ou indemnisation.',
            'keywords' => ['unfair dismissal', 'wrongful dismissal', 'termination', 'dismissal', 'fired', 'sacked', 'licenciement', 'licenciement abusif', 'employment rights', 'section 45', 'section 46', 'section 47', 'section 48', 'section 49', 'industrial court'],
        ],
        [
            'act' => 'Employment Rights Act 2008',
            'section' => 's. 30–35',
            'title' => 'Remuneration and wages',
            'summary' => 'The Act protects workers’ right to remuneration, regulates deductions from wages, and sets rules on payment periods. Failure to pay lawful wages may give rise to claims before the Industrial Court or relevant authorities.',
            'summary_fr' => 'L’Act protège le droit à la rémunération, encadre les retenues sur salaire et les périodes de paiement.',
            'keywords' => ['wages', 'salary', 'remuneration', 'pay', 'salaire', 'rémunération'],
        ],
        [
            'act' => 'Employment Rights Act 2008',
            'section' => 's. 50–52',
            'title' => 'Redundancy',
            'summary' => 'Where employment ends for operational reasons (redundancy), the employer must follow fair procedure including consultation and, where applicable, redundancy payments according to the Act and any applicable regulations.',
            'summary_fr' => 'En cas de licenciement pour motif économique, l’employeur doit respecter une procédure loyale incluant consultation et, le cas échéant, indemnités de licenciement.',
            'keywords' => ['redundancy', 'retrenchment', 'operational requirements', 'licenciement économique', 'restructuration'],
        ],
        [
            'act' => 'Employment Rights Act 2008',
            'section' => 's. 60–65',
            'title' => 'Industrial Court jurisdiction',
            'summary' => 'The Industrial Court has exclusive jurisdiction over disputes arising under the Employment Rights Act, including claims for unfair dismissal, unpaid wages, and other employment rights violations.',
            'summary_fr' => 'L’Industrial Court a compétence exclusive pour les litiges relevant de l’Employment Rights Act, y compris licenciement abusif et salaires impayés.',
            'keywords' => ['industrial court', 'employment dispute', 'tribunal du travail', 'litige travail'],
        ],
        [
            'act' => 'Criminal Code (Mauritius)',
            'section' => 's. 250',
            'title' => 'Murder',
            'summary' => 'Murder is the unlawful killing of a person with intent to kill or cause grievous bodily harm. It is among the most serious offences under the Criminal Code, punishable by life imprisonment under Mauritian law.',
            'summary_fr' => 'Le meurtre est le homicide volontaire avec intention de tuer ou de causer des blessures graves. Infraction très grave punie notamment de l’emprisonnement à perpétuité.',
            'keywords' => ['murder', 'homicide', 'criminal', 'killing', 'meurtre', 'homicide', 'criminal code'],
        ],
        [
            'act' => 'Criminal Code (Mauritius)',
            'section' => 's. 263–266',
            'title' => 'Assault and bodily harm',
            'summary' => 'Assault and acts causing bodily harm are criminal offences. Penalties vary by severity. Victims may also pursue civil claims for damages in parallel or subsequently.',
            'summary_fr' => 'Les voies de fait et blessures sont des infractions pénales. Les victimes peuvent aussi engager une action civile en dommages-intérêts.',
            'keywords' => ['assault', 'bodily harm', 'violence', 'voies de fait', 'agression'],
        ],
        [
            'act' => 'Criminal Code (Mauritius)',
            'section' => 's. 317–330',
            'title' => 'Theft and related offences',
            'summary' => 'Theft (dishonest appropriation of property belonging to another) and related offences such as receiving stolen property are defined and punished under the Criminal Code.',
            'summary_fr' => 'Le vol (soustraction frauduleuse de biens) et les infractions connexes sont définis et punis par le Code pénal.',
            'keywords' => ['theft', 'stealing', 'vol', 'larceny', 'criminal'],
        ],
        [
            'act' => 'Criminal Code (Mauritius)',
            'section' => 's. 383–396',
            'title' => 'Fraud and false pretences',
            'summary' => 'Obtaining property or advantage by false pretences, fraud, or deception constitutes criminal offences with penalties depending on the value and circumstances.',
            'summary_fr' => 'Obtenir un bien ou un avantage par manœuvres frauduleuses ou faux prétextes constitue une infraction pénale.',
            'keywords' => ['fraud', 'false pretences', 'escroquerie', 'fraude'],
        ],
        [
            'act' => 'Criminal Code (Mauritius)',
            'section' => 'General Part',
            'title' => 'Elements of a criminal offence',
            'summary' => 'Generally, a criminal offence requires actus reus (guilty act) and mens rea (guilty mind/intention or negligence as required by the specific offence). Defences may include lack of intent, self-defence, duress, and insanity where applicable.',
            'summary_fr' => 'Une infraction requiert généralement une acte fautif et une intention (ou négligence selon l’infraction). Défenses possibles : légitime défense, contrainte, etc.',
            'keywords' => ['criminal offence', 'mens rea', 'actus reus', 'defence', 'infraction', 'éléments', 'intention'],
        ],
        [
            'act' => 'Companies Act 2001',
            'section' => 's. 17–22',
            'title' => 'Incorporation of companies',
            'summary' => 'A company is incorporated by registration with the Registrar of Companies. On incorporation it becomes a body corporate with separate legal personality, distinct from its shareholders and directors.',
            'summary_fr' => 'Une société est constituée par enregistrement auprès du Registrar of Companies. Elle acquiert une personnalité juridique distincte de ses actionnaires et administrateurs.',
            'keywords' => ['company registration', 'incorporation', 'companies act', 'body corporate', 'création société', 'enregistrement'],
        ],
        [
            'act' => 'Companies Act 2001',
            'section' => 's. 132–136',
            'title' => 'Directors’ duties',
            'summary' => 'Directors must act in good faith, in the best interests of the company, with the care and diligence of a reasonable director. Breach may lead to personal liability, removal, or regulatory action.',
            'summary_fr' => 'Les administrateurs doivent agir de bonne foi, dans l’intérêt de la société, avec diligence. La violation peut entraîner responsabilité personnelle.',
            'keywords' => ['directors duties', 'director liability', 'corporate governance', 'devoirs administrateurs', 'gouvernance'],
        ],
        [
            'act' => 'Companies Act 2001',
            'section' => 's. 162–165',
            'title' => 'Winding up / liquidation',
            'summary' => 'A company may be wound up voluntarily or by court order (e.g. insolvency, just and equitable grounds). A liquidator collects assets, pays creditors, and distributes surplus to members.',
            'summary_fr' => 'Une société peut être liquidée volontairement ou judiciairement. Le liquidateur réalise l’actif, paie les créanciers et distribue le surplus.',
            'keywords' => ['winding up', 'liquidation', 'insolvency', 'liquidation', 'faillite société'],
        ],
        [
            'act' => 'Insolvency Act 2009',
            'section' => 'Part II–III',
            'title' => 'Insolvency of companies and individuals',
            'summary' => 'The Insolvency Act governs corporate and personal insolvency procedures in Mauritius, including administration, receivership, and bankruptcy, with rules on creditor priority and asset distribution.',
            'summary_fr' => 'L’Insolvency Act régit l’insolvabilité des sociétés et des personnes, avec règles de priorité entre créanciers.',
            'keywords' => ['insolvency', 'bankruptcy', 'insolvabilité', 'faillite', 'creditor'],
        ],
        [
            'act' => 'Income Tax Act 1995',
            'section' => 's. 5–8',
            'title' => 'Charge to income tax',
            'summary' => 'Income tax is charged on the chargeable income of persons (individuals and companies) resident or deriving Mauritius-source income, at rates prescribed by the Act and annual Finance Acts.',
            'summary_fr' => 'L’impôt sur le revenu frappe les revenus imposables des personnes résidentes ou percevant des revenus de source mauricienne, aux taux fixés par la loi.',
            'keywords' => ['income tax', 'tax', 'chargeable income', 'impôt sur le revenu', 'fiscal'],
        ],
        [
            'act' => 'Income Tax Act 1995',
            'section' => 's. 73–80',
            'title' => 'Returns and assessment',
            'summary' => 'Taxpayers must file returns. The Mauritius Revenue Authority (MRA) assesses tax due. Failure to file or pay may attract penalties and interest under the Act.',
            'summary_fr' => 'Les contribuables doivent déposer des déclarations. La MRA établit l’impôt dû. Retards et omissions peuvent entraîner pénalités.',
            'keywords' => ['tax return', 'mra', 'assessment', 'déclaration fiscale', 'revenue authority'],
        ],
        [
            'act' => 'Value Added Tax Act 1998',
            'section' => 's. 7–9',
            'title' => 'VAT registration and supply',
            'summary' => 'VAT applies to taxable supplies of goods and services. Registered persons charge VAT on supplies and may claim input tax credits. Thresholds and rates are set under the Act.',
            'summary_fr' => 'La TVA s’applique aux livraisons de biens et services taxables. Les assujettis facturent la TVA et peuvent récupérer la taxe en amont.',
            'keywords' => ['vat', 'value added tax', 'tva', 'tax'],
        ],
        [
            'act' => 'Data Protection Act 2017',
            'section' => 's. 3–5',
            'title' => 'Purpose and lawful processing',
            'summary' => 'Personal data must be processed lawfully, fairly, and for specified purposes. Data controllers must comply with principles including purpose limitation, data minimisation, accuracy, and security.',
            'summary_fr' => 'Les données personnelles doivent être traitées légalement, loyalement et pour des finalités déterminées (limitation, minimisation, exactitude, sécurité).',
            'keywords' => ['data protection', 'personal data', 'privacy', 'gdpr mauritius', 'données personnelles', 'protection des données'],
        ],
        [
            'act' => 'Data Protection Act 2017',
            'section' => 's. 27–30',
            'title' => 'Rights of data subjects',
            'summary' => 'Data subjects have rights including access to their personal data, rectification, erasure in certain cases, and objection to processing. Complaints may be lodged with the Data Protection Commissioner.',
            'summary_fr' => 'Les personnes concernées ont des droits d’accès, de rectification, d’effacement et d’opposition. Réclamations possibles auprès du Data Protection Commissioner.',
            'keywords' => ['data subject rights', 'access request', 'rectification', 'droits des personnes'],
        ],
        [
            'act' => 'Consumer Protection (Price and Supplies Control) Act',
            'section' => 'Various',
            'title' => 'Consumer protection',
            'summary' => 'Mauritius has consumer protection legislation prohibiting unfair trade practices, false advertising, and regulating certain prices and supplies. Consumers may seek remedies through courts or designated authorities.',
            'summary_fr' => 'La législation mauricienne interdit les pratiques commerciales déloyales et la publicité mensongère. Recours devant les tribunaux ou autorités compétentes.',
            'keywords' => ['consumer protection', 'unfair trade', 'consumer rights', 'protection consommateur'],
        ],
        [
            'act' => 'Occupiers Liability Act 1985',
            'section' => 's. 3–4',
            'title' => 'Duty of care to visitors',
            'summary' => 'An occupier of premises owes a duty of care to lawful visitors to take reasonable care to ensure they are reasonably safe. This supports tort claims for injuries on property.',
            'summary_fr' => 'L’occupant des locaux doit prendre des mesures raisonnables pour la sécurité des visiteurs autorisés.',
            'keywords' => ['occupiers liability', 'premises', 'slip and fall', 'negligence', 'responsabilité occupant'],
        ],
        [
            'act' => 'Code of Civil Procedure (Mauritius)',
            'section' => 'Part I–II',
            'title' => 'Commencement of civil proceedings',
            'summary' => 'Civil proceedings are commenced by summons or other originating process filed in the competent court. The defendant must be served. Pleadings, discovery, and trial follow prescribed procedure.',
            'summary_fr' => 'Les procédures civiles commencent par assignation ou acte introductif déposé devant la juridiction compétente. Signification au défendeur, puis procédure écrite et audience.',
            'keywords' => ['civil procedure', 'summons', 'litigation', 'procédure civile', 'assignation'],
        ],
        [
            'act' => 'Code of Criminal Procedure (Mauritius)',
            'section' => 'Part I',
            'title' => 'Investigation and prosecution',
            'summary' => 'Criminal procedure governs arrest, police investigation, charge, bail, committal, and trial. The Director of Public Prosecutions (DPP) oversees public prosecutions.',
            'summary_fr' => 'La procédure pénale régit l’arrestation, l’enquête, l’inculpation, la mise en liberté provisoire et le procès. Le DPP supervise les poursuites.',
            'keywords' => ['criminal procedure', 'arrest', 'bail', 'prosecution', 'procédure pénale', 'dpp'],
        ],
        [
            'act' => 'Supreme Court Act',
            'section' => 'Various',
            'title' => 'Supreme Court jurisdiction',
            'summary' => 'The Supreme Court is the superior court of record. It hears serious civil and criminal matters at first instance and appeals. It also exercises constitutional jurisdiction including judicial review.',
            'summary_fr' => 'La Cour suprême est la juridiction supérieure : affaires civiles et pénales importantes, appels, et contrôle de constitutionnalité / révision judiciaire.',
            'keywords' => ['supreme court', 'appeal', 'judicial review', 'cour suprême', 'appel'],
        ],
        [
            'act' => 'Intermediate Court Act',
            'section' => 'Various',
            'title' => 'Intermediate Court jurisdiction',
            'summary' => 'The Intermediate Court handles mid-level civil claims and criminal cases within its statutory limits, below the Supreme Court in the hierarchy.',
            'summary_fr' => 'La Cour intermédiaire connaît des affaires civiles et pénales de importance intermédiaire, dans les limites fixées par la loi.',
            'keywords' => ['intermediate court', 'cour intermédiaire', 'jurisdiction'],
        ],
        [
            'act' => 'District Courts Act',
            'section' => 'Various',
            'title' => 'District Courts jurisdiction',
            'summary' => 'District Courts are courts of first instance for lower-value civil disputes and minor criminal matters in their geographic districts.',
            'summary_fr' => 'Les District Courts sont des juridictions de première instance pour les litiges de moindre valeur et infractions mineures.',
            'keywords' => ['district court', 'magistrate', 'tribunal district'],
        ],
        [
            'act' => 'Industrial Court Act',
            'section' => 'Various',
            'title' => 'Industrial Court — employment disputes',
            'summary' => 'The Industrial Court is a specialist tribunal for employment and labour disputes under the Employment Rights Act and related legislation.',
            'summary_fr' => 'L’Industrial Court est un tribunal spécialisé pour les litiges du travail.',
            'keywords' => ['industrial court', 'labour court', 'employment tribunal'],
        ],
        [
            'act' => 'Protection from Domestic Violence Act 1997',
            'section' => 's. 3–8',
            'title' => 'Protection orders',
            'summary' => 'Victims of domestic violence may apply to the court for protection orders restraining the abuser from contact, violence, or occupation of the home. Breach is a criminal offence.',
            'summary_fr' => 'Les victimes de violence domestique peuvent obtenir des ordonnances de protection interdisant contact ou violence. Violation = infraction pénale.',
            'keywords' => ['domestic violence', 'protection order', 'violence domestique', 'ordonnance protection'],
        ],
        [
            'act' => 'Road Traffic Act',
            'section' => 'Various',
            'title' => 'Road traffic offences',
            'summary' => 'The Road Traffic Act regulates licensing, vehicle standards, and traffic offences including dangerous driving, drink-driving, and speeding, with penalties including fines and imprisonment.',
            'summary_fr' => 'La Road Traffic Act régit le permis, les véhicules et les infractions routières (conduite dangereuse, alcool, excès de vitesse).',
            'keywords' => ['road traffic', 'driving offence', 'traffic', 'route', 'conduite'],
        ],
        [
            'act' => 'Environmental Protection Act',
            'section' => 'Various',
            'title' => 'Environmental protection',
            'summary' => 'Environmental legislation regulates pollution, waste, environmental impact assessments, and enforcement. Contraventions may attract administrative penalties and prosecution.',
            'summary_fr' => 'La législation environnementale encadre pollution, déchets et études d’impact. Sanctions administratives et pénales possibles.',
            'keywords' => ['environment', 'pollution', 'eia', 'environnement'],
        ],
        [
            'act' => 'Financial Intelligence and Anti-Money Laundering Act 2002',
            'section' => 'Various',
            'title' => 'Anti-money laundering',
            'summary' => 'AML/CFT laws require reporting entities to conduct customer due diligence, report suspicious transactions, and maintain records. Breaches attract severe penalties.',
            'summary_fr' => 'Les lois AML/CFT imposent vigilance client, déclaration de soupçon et conservation de documents. Sanctions sévères en cas de violation.',
            'keywords' => ['aml', 'money laundering', 'anti-money laundering', 'blanchiment'],
        ],
        [
            'act' => 'Interpretation and General Clauses Act',
            'section' => 'Various',
            'title' => 'Statutory interpretation',
            'summary' => 'This Act provides default rules for interpreting Mauritian statutes, including definitions of common terms, computation of time, and effect of repeals.',
            'summary_fr' => 'Cette loi fixe les règles d’interprétation des textes législatifs mauriciens (définitions, calcul des délais, abrogations).',
            'keywords' => ['interpretation', 'statutory construction', 'interprétation', 'general clauses'],
        ],
    ];
}

function ai_mauritius_normalize_query(string $text): string
{
    $text = mb_strtolower(trim($text));
    $text = preg_replace('/[?؟!.]+$/u', '', $text) ?? $text;
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
    return $text;
}

/**
 * @return list<string>
 */
function ai_mauritius_query_tokens(string $query): array
{
    $query = ai_mauritius_normalize_query($query);
    $query = preg_replace('/[^a-z0-9àâäéèêëïîôùûüç\s\-\'’]/iu', ' ', $query) ?? $query;
    $parts = preg_split('/\s+/u', $query, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $stop = ['the', 'a', 'an', 'is', 'are', 'what', 'which', 'where', 'how', 'does', 'do', 'under', 'about', 'tell', 'me', 'please', 'mauritius', 'mauritian', 'law', 'laws', 'legal', 'le', 'la', 'les', 'un', 'une', 'de', 'du', 'des', 'dans', 'quelle', 'quel', 'quoi', 'comment', 'est', 'sont', 'droit', 'loi', 'lois', 'maurice'];
    $tokens = [];
    foreach ($parts as $part) {
        if (strlen($part) < 2 || in_array($part, $stop, true)) {
            continue;
        }
        $tokens[] = $part;
    }
    return $tokens;
}

function ai_mauritius_extract_section_ref(string $query): ?string
{
    if (preg_match('/\b(?:section|s\.?|sec\.?|article|art\.?)\s*(\d+[a-z]?)\b/iu', $query, $m)) {
        return strtolower($m[1]);
    }
    if (preg_match('/\b(\d+[a-z]?)\s*(?:of|du|de|dans)\b/iu', $query, $m)) {
        return strtolower($m[1]);
    }
    return null;
}

function ai_mauritius_is_legal_question(string $message): bool
{
    $q = ai_mauritius_normalize_query($message);
    if ($q === '') {
        return false;
    }

    if (ai_mauritius_extract_section_ref($q) !== null) {
        return true;
    }

    // Firm workspace / operations questions — defer to built-in portal handlers.
    if (preg_match('/\b(our|we|my|firm|dashboard|revenue|invoice|payment|client|case|appointment|lawyer|staff|how many|total|summarize|show|list my|upcoming|overdue|cabinet|dossier|facture|honoraire)\b/iu', $q)
        && !preg_match('/\b(section|article|under which|what law|what act|which law|which act|quelle loi|quelle section|sous quelle)\b/iu', $q)) {
        return false;
    }

    $patterns = [
        '/\b(act|statute|code|regulation|article|section|provision|offence|offense|liability|contract|tort|dismissal|wrongful|employment rights|criminal|civil|constitution|court|tribunal|company act|insolvency|marriage|divorce|property|prescription|limitation)\b/iu',
        '/\b(loi|code|article|section|infraction|responsabilité|responsabilite|contrat|licenciement|travail|pénal|penal|civil|constitution|tribunal|impôt sur|société|societe|mariage|divorce|propriété|propriete|prescription)\b/iu',
        '/\b(what\s+(?:law|act|section|does)|under\s+which|which\s+section|which\s+law|what\s+does\s+(?:the|section))\b/iu',
        '/\b(quelle\s+(?:loi|section)|sous\s+quelle|dans\s+quelle\s+section|que\s+dit)\b/iu',
        '/\b(unfair\s+dismissal|wrongful\s+dismissal|fundamental\s+rights|habeas\s+corpus|licenciement\s+abusif|droits\s+fondamentaux)\b/iu',
        '/\b(covers?|governs?|deals?\s+with|applies?\s+to|provides?\s+for|states?|say\s+about|portes?\s+sur|r[eé]git)\b/iu',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $q)) {
            return true;
        }
    }

    return false;
}

/**
 * @param array{act:string,section:string,title:string,summary:string,summary_fr:string,keywords:list<string>} $entry
 */
function ai_mauritius_score_entry(array $entry, string $query, array $tokens, ?string $sectionRef): float
{
    $score = 0.0;
    $haystack = mb_strtolower(
        $entry['act'] . ' ' . $entry['section'] . ' ' . $entry['title'] . ' ' . $entry['summary'] . ' ' . $entry['summary_fr'] . ' ' . implode(' ', $entry['keywords'])
    );

    if ($sectionRef !== null && preg_match('/\b' . preg_quote($sectionRef, '/') . '\b/i', $entry['section'])) {
        $score += 50;
    }

    foreach ($tokens as $token) {
        if (strlen($token) < 3) {
            continue;
        }
        if (preg_match('/\b' . preg_quote($token, '/') . '\b/iu', $haystack)) {
            $score += 4;
        } elseif (str_contains($haystack, $token)) {
            $score += 2;
        }
    }

    foreach ($entry['keywords'] as $keyword) {
        $kw = mb_strtolower($keyword);
        if ($kw === '') {
            continue;
        }
        if (str_contains($query, $kw)) {
            $score += 12;
        } elseif (preg_match('/\b' . preg_quote($kw, '/') . '\b/iu', $query)) {
            $score += 8;
        }
    }

    // Boost when act name appears in query
    $actShort = mb_strtolower(preg_replace('/\s+\d{4}$/', '', $entry['act']) ?? $entry['act']);
    if ($actShort !== '' && str_contains($query, $actShort)) {
        $score += 15;
    }

    return $score;
}

/**
 * @return list<array{act:string,section:string,title:string,summary:string,summary_fr:string,keywords:list<string>,score:float}>
 */
function ai_mauritius_corpus_search(string $message, int $limit = 3): array
{
    $query = ai_mauritius_normalize_query($message);
    $tokens = ai_mauritius_query_tokens($message);
    $sectionRef = ai_mauritius_extract_section_ref($message);
    $results = [];

    foreach (ai_mauritius_law_corpus() as $entry) {
        $score = ai_mauritius_score_entry($entry, $query, $tokens, $sectionRef);
        if ($score >= 6) {
            $entry['score'] = $score;
            $results[] = $entry;
        }
    }

    usort($results, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);

    return array_slice($results, 0, max(1, $limit));
}

function ai_mauritius_format_entry_line(array $entry, bool $fr, bool $withSummary = false): string
{
    $cite = $entry['act'] . ', ' . $entry['section'];
    $line = $cite . ' — ' . $entry['title'];
    if ($withSummary) {
        $summary = $fr ? ($entry['summary_fr'] ?: $entry['summary']) : $entry['summary'];
        return $line . ".\n\n" . $summary;
    }
    return $line;
}

function ai_mauritius_question_wants_specific_law(string $question): bool
{
    $q = ai_mauritius_normalize_query($question);
    return (bool) preg_match(
        '/\b(section|sections|article|articles|under\s+which|which\s+section|which\s+law|which\s+act|what\s+law|what\s+act|what\s+section|what\s+does|quelle\s+(?:loi|section)|sous\s+quelle|quelle\s+article|covers?|governs?|deals?\s+with)\b/iu',
        $q
    );
}

function ai_mauritius_format_corpus_results(array $results, string $question = ''): string
{
    if (!$results) {
        return '';
    }

    $fr = ai_mauritius_law_is_fr();
    $lines = [];
    $top = $results[0];
    $specificAsk = ai_mauritius_question_wants_specific_law($question);

    if ($specificAsk || count($results) === 1) {
        $lines[] = ai_mauritius_format_entry_line($top, $fr, true);
        if (count($results) > 1) {
            $lines[] = '';
            $lines[] = $fr ? 'Autres dispositions pertinentes :' : 'Other relevant provisions:';
            for ($i = 1, $n = count($results); $i < $n; $i++) {
                $lines[] = '• ' . ai_mauritius_format_entry_line($results[$i], $fr, false);
            }
        }
    } else {
        $lines[] = $fr
            ? 'Voici les principales lois et dispositions applicables :'
            : 'The main applicable laws and provisions are:';
        foreach ($results as $entry) {
            $lines[] = '';
            $lines[] = '• ' . ai_mauritius_format_entry_line($entry, $fr, true);
        }
    }

    $lines[] = '';
    $lines[] = ai_mauritius_law_disclaimer();

    return implode("\n", $lines);
}

/**
 * Format a corpus entry as a definitional answer.
 *
 * @param array{act:string,section:string,title:string,summary:string,summary_fr:string} $entry
 */
function ai_mauritius_format_definition_from_corpus(array $entry): string
{
    $fr = ai_mauritius_law_is_fr();
    $summary = $fr ? ($entry['summary_fr'] ?: $entry['summary']) : $entry['summary'];
    $cite = $entry['act'] . ', ' . $entry['section'];

    return ai_format_definition_reply($entry['title'], $summary, $cite);
}

/**
 * Built-in legal Q&A — searches the Mauritius law corpus without any external API.
 */
function ai_try_mauritius_corpus_reply(string $message): ?string
{
    // Definition-style questions are handled by the glossary first.
    if (ai_wants_legal_definition($message)) {
        return null;
    }
    if (!ai_mauritius_is_legal_question($message)) {
        return null;
    }

    $results = ai_mauritius_corpus_search($message, 3);
    if ($results) {
        return ai_mauritius_format_corpus_results($results, $message);
    }

    // Legal question but no corpus match — give helpful guidance
    $fr = ai_mauritius_law_is_fr();
    if ($fr) {
        return "Je n’ai pas trouvé de section précise pour cette question.\n\n"
            . "Précisez le nom de la loi (ex. Employment Rights Act 2008, Code civil, Code pénal) ou le numéro de section.\n\n"
            . ai_mauritius_main_law_areas() . "\n\n"
            . ai_mauritius_law_disclaimer();
    }

    return "I could not find a specific section for that question.\n\n"
        . "Try naming the Act (e.g. Employment Rights Act 2008, Civil Code, Criminal Code) or the section number.\n\n"
        . ai_mauritius_main_law_areas() . "\n\n"
        . ai_mauritius_law_disclaimer();
}

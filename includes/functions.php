<?php
/**
 * Shared helper functions:
 *  - session / authentication helpers
 *  - CSRF protection helpers
 *  - the keyword-matching "AI" engine
 */

if (session_status() === PHP_SESSION_NONE) {
    $secureCookie = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'httponly' => true,
        'secure'   => $secureCookie,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/* ------------------------------------------------------------
 * AUTH HELPERS
 * ------------------------------------------------------------ */

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
    // Suspended accounts are logged out on their next request.
    global $pdo;
    if (isset($pdo)) {
        $st = $pdo->prepare('SELECT status FROM users WHERE id = ?');
        $st->execute([$_SESSION['user_id']]);
        if ($st->fetchColumn() === 'suspended') {
            session_unset();
            session_destroy();
            session_start();
            $_SESSION['auth_error'] = 'Your account has been suspended. Please contact the administrator.';
            header('Location: login.php');
            exit;
        }
    }
}

/**
 * API variant: block suspended users from AJAX endpoints with a 403.
 * Call after is_logged_in().
 */
function require_active(): void
{
    if (!is_logged_in()) {
        return;
    }
    global $pdo;
    if (!isset($pdo)) {
        return;
    }
    $st = $pdo->prepare('SELECT status FROM users WHERE id = ?');
    $st->execute([current_user_id()]);
    if ($st->fetchColumn() === 'suspended') {
        session_unset();
        session_destroy();
        http_response_code(403);
        echo json_encode(['error' => 'Your account has been suspended. Please contact the administrator.']);
        exit;
    }
}

function require_admin(): void
{
    require_login();
    if (($_SESSION['user_role'] ?? 'citizen') !== 'admin') {
        http_response_code(403);
        die('Access denied. Administrator account required.');
    }
}

function current_user_id(): ?int
{
    return $_SESSION['user_id'] ?? null;
}

function current_user_name(): string
{
    return $_SESSION['user_name'] ?? 'Citizen';
}

function current_user_role(): string
{
    return $_SESSION['user_role'] ?? 'citizen';
}

function current_user_avatar(): string
{
    return $_SESSION['user_avatar'] ?? '';
}

function is_admin(): bool
{
    return ($_SESSION['user_role'] ?? 'citizen') === 'admin';
}

/**
 * Append an entry to the activity_logs audit trail.
 * Falls back to the logged-in session user; pass $forceUser/$forceName
 * for events that happen outside an active session (e.g. failed logins).
 */
function log_activity(PDO $pdo, string $action, string $details = '', ?string $forceUser = null, ?string $forceName = null): void
{
    $pdo->prepare(
        'INSERT INTO activity_logs (user_id, user_name, action, details, ip_address) VALUES (?, ?, ?, ?, ?)'
    )->execute([
        $forceUser !== null ? (int) $forceUser : ($_SESSION['user_id'] ?? null),
        $forceName !== null ? $forceName : ($_SESSION['user_name'] ?? null),
        mb_substr($action, 0, 50),
        mb_substr($details, 0, 500),
        $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}

function sanitize(string $value): string
{
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

/**
 * Parse the report date range from GET params.
 * - explicit from/to (validated, swapped if reversed) win
 * - no params at all -> last 30 days
 * - period preset (7/30/90/365) -> that many days ending today
 * - period=0 -> all time (both null)
 * Returns [from, to] as 'Y-m-d' strings, or [null, null] for all time.
 */
function report_range(): array
{
    $from = trim($_GET['from'] ?? '');
    $to   = trim($_GET['to'] ?? '');
    $period = (int) ($_GET['period'] ?? 0);
    $hasParams = isset($_GET['period']) || isset($_GET['from']) || isset($_GET['to']);
    $isDate = fn($d) => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);

    if ($isDate($from) && $isDate($to)) {
        if (strtotime($from) > strtotime($to)) {
            $tmp = $from; $from = $to; $to = $tmp;
        }
        return [$from, $to];
    }

    if (!$hasParams) {
        return [date('Y-m-d', strtotime('-30 days')), date('Y-m-d')];
    }
    if ($period === 0) {
        return [null, null];
    }
    $days = in_array($period, [7, 30, 90, 365], true) ? $period : 30;
    return [date('Y-m-d', strtotime("-$days days")), date('Y-m-d')];
}

/* ------------------------------------------------------------
 * CSRF PROTECTION
 * Every state-changing form / AJAX call must carry a token.
 * ------------------------------------------------------------ */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function csrf_verify(): bool
{
    $sent = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($sent) && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $sent);
}

function require_csrf(): void
{
    if (!csrf_verify()) {
        http_response_code(419);
        echo json_encode(['error' => 'Invalid security token. Please refresh the page and try again.']);
        exit;
    }
}

/* ------------------------------------------------------------
 * GOOGLE / GMAIL LOGIN (OAuth 2.0)
 * ------------------------------------------------------------ */

function google_auth_enabled(): bool
{
    return defined('GOOGLE_CLIENT_ID') && GOOGLE_CLIENT_ID !== ''
        && defined('GOOGLE_CLIENT_SECRET') && GOOGLE_CLIENT_SECRET !== '';
}

/** cURL helper: GET or POST JSON, returns decoded array or null. */
function http_json_request(string $url, array $post = []): ?array
{
    if (!function_exists('curl_init')) {
        return null;
    }
    $ch = curl_init($url);
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ];
    if ($post) {
        $options[CURLOPT_POST]       = true;
        $options[CURLOPT_POSTFIELDS] = http_build_query($post);
    }
    curl_setopt_array($ch, $options);
    $body = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $httpCode >= 400) {
        return null;
    }
    $data = json_decode($body, true);
    return is_array($data) ? $data : null;
}

/** Exchange the authorization code for an access token + id_token. */
function google_exchange_code_for_token(string $code): ?array
{
    return http_json_request('https://oauth2.googleapis.com/token', [
        'code'          => $code,
        'client_id'     => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri'  => GOOGLE_REDIRECT_URI,
        'grant_type'    => 'authorization_code',
    ]);
}

/**
 * Validate an id_token against Google and return a clean profile,
 * or null if the token is invalid/forged.
 */
function google_validate_id_token(string $idToken): ?array
{
    $res = http_json_request('https://oauth2.googleapis.com/tokeninfo', ['id_token' => $idToken]);
    if (!$res) {
        return null;
    }
    if (($res['aud'] ?? '') !== GOOGLE_CLIENT_ID) {
        return null;
    }
    if (($res['exp'] ?? 0) < time()) {
        return null;
    }
    if (!in_array($res['iss'] ?? '', ['https://accounts.google.com', 'accounts.google.com'], true)) {
        return null;
    }
    if (empty($res['sub']) || empty($res['email'])) {
        return null;
    }

    return [
        'google_id'      => (string) $res['sub'],
        'email'          => (string) $res['email'],
        'name'           => trim((string) ($res['name'] ?? '')),
        'avatar'         => (string) ($res['picture'] ?? ''),
        'email_verified' => !empty($res['email_verified']),
    ];
}

/**
 * Log in or auto-register a user from a verified Google profile.
 *
 * 1. google_id already linked  -> update profile, log in.
 * 2. same email already exists -> link the Google account, log in.
 * 3. brand new                 -> create a citizen account.
 *
 * New Google accounts get a random, unknown password so the
 * password login path can never be used to hijack them.
 *
 * Returns ['new' => bool, 'user' => [id, name, email, role, avatar]]
 */
function login_or_register_with_google(PDO $pdo, array $profile): array
{
    $googleId = $profile['google_id'];
    $email    = mb_strtolower(trim($profile['email']));
    $name     = $profile['name'] !== '' ? $profile['name'] : explode('@', $email)[0];
    $avatar   = $profile['avatar'];

    // 1. Already linked to Google
    $stmt = $pdo->prepare('SELECT id, name, email, role, avatar FROM users WHERE google_id = ? LIMIT 1');
    $stmt->execute([$googleId]);
    $user = $stmt->fetch();
    if ($user) {
        $pdo->prepare('UPDATE users SET name = ?, avatar = ? WHERE id = ?')
            ->execute([$name, $avatar, $user['id']]);
        return ['new' => false, 'user' => $user];
    }

    // 2. Existing account with the same email -> link Google
    $stmt = $pdo->prepare('SELECT id, name, email, role, avatar FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user) {
        $pdo->prepare('UPDATE users SET google_id = ?, avatar = ?, name = ? WHERE id = ?')
            ->execute([$googleId, $avatar, $name, $user['id']]);
        return ['new' => false, 'user' => $user];
    }

    // 3. Brand-new Google account
    $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO users (name, email, password_hash, role, google_id, avatar)
                   VALUES (?, ?, ?, 'citizen', ?, ?)")
        ->execute([$name, $email, $hash, $googleId, $avatar]);
    $userId = (int) $pdo->lastInsertId();

    return [
        'new'  => true,
        'user' => ['id' => $userId, 'name' => $name, 'email' => $email, 'role' => 'citizen', 'avatar' => $avatar],
    ];
}

/* ------------------------------------------------------------
 * KEYWORD-MATCHING "AI" ENGINE
 *
 * Lightweight, fully offline FAQ/keyword matcher (no external
 * AI API). Pipeline:
 *
 *   1. NORMALIZE  - lowercase, strip punctuation, remove Tagalog
 *                   stop words ("po", "ba", "yung", ...) so that
 *                   "Magkano po ba ang business permit?" works.
 *   2. STEM/FUZZY - light suffix stemming (requirements ->
 *                   requirement) plus Levenshtein fallback for
 *                   typos, so spelling mistakes still match.
 *   3. TOPIC      - which permit is being asked about? Scored via
 *                   word-overlap against each permit's "overview"
 *                   keyword synonyms + unique signal words
 *                   ("renew" -> Renewal).
 *   4. INTENT     - requirements / steps / fees / deadline /
 *                   processing_time / where, via signal words.
 *   5. CONTEXT    - if the topic is unclear but the citizen was
 *                   just asking about a permit, follow up on that
 *                   same topic ("what about the fees?").
 *   6. FALLBACK   - "did you mean" style suggestions when nothing
 *                   scores high enough.
 *
 * Answer text lives in the kb_entries table - add/edit permits
 * and answers from the admin panel, no PHP changes required.
 * ------------------------------------------------------------ */

// Common Filipino function words removed before matching.
const STOP_WORDS = [
    'ang', 'ng', 'nga', 'mga', 'at', 'ay', 'sa', 'na', 'ko', 'mo', 'ni',
    'din', 'rin', 'po', 'opo', 'ba', 'ka', 'e', 'eh', 'kasi', 'naman',
    'ano', 'yung', 'yang', 'ito', 'iyan', 'baka', 'pag', 'kapag', 'kung',
    'kong', 'kayo', 'kami', 'tayo', 'sila', 'lang', 'lamang', 'raw', 'daw',
    'pa', 'pero', 'ganyan', 'gayon', 'muna', 'dito', 'doon', 'bakit',
    'sino', 'kanino', 'para', 'pala', 'dati', 'ngayon', 'buong', 'ninyo',
    'inyo', 'amin', 'atin', 'kanila', 'pong', 'bang', 'nyo', 'mo', 'nung',
];

function normalize_text(string $text): string
{
    $text = mb_strtolower($text);
    $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
    $text = preg_replace('/\s+/', ' ', $text);
    return trim($text);
}

/** Normalized text with Tagalog stop words removed. */
function normalize_text_filtered(string $text): string
{
    $words = array_filter(explode(' ', normalize_text($text)), fn ($w) => !in_array($w, STOP_WORDS, true));
    return implode(' ', $words);
}

/** Light suffix stemming + Tagalog verb-prefix stripping. */
function light_stem(string $w): string
{
    if (strlen($w) <= 3) {
        return $w;
    }
    foreach (['ments', 'ation', 'ings', 'ions', 'ment', 'ions', 'ing', 'ers', 'ies', 'ness', 'nes', 'al', 'es', 's'] as $suffix) {
        if (str_ends_with($w, $suffix) && strlen($w) - strlen($suffix) >= 3) {
            $stem = substr($w, 0, -strlen($suffix));
            if ($suffix === 'ies') {
                $stem .= 'y';
            }
            return $stem;
        }
    }
    // Tagalog verb prefixes: magrenew -> renew, nagpaparehistro -> rehistro
    foreach (['magpapa', 'nagpapa', 'magpa', 'nagpa', 'magka', 'nagka', 'mag', 'nag', 'pag', 'mang', 'pang', 'nang', 'ma', 'na'] as $prefix) {
        if (str_starts_with($w, $prefix) && strlen($w) - strlen($prefix) >= 3) {
            return substr($w, strlen($prefix));
        }
    }
    return $w;
}

/** True if two words are equal after stemming, or close enough (typos). */
function words_are_close(string $a, string $b): bool
{
    $sa = light_stem($a);
    $sb = light_stem($b);
    if ($sa === $sb) {
        return true;
    }
    $len = max(strlen($sa), strlen($sb));
    // Distance budget scales with length so short Tagalog words
    // (e.g. "paano" vs "saan") don't false-positive.
    $budget = max(1, (int) floor($len / 4));
    return levenshtein($sa, $sb) <= $budget;
}

function tokenize(string $text): array
{
    return array_values(array_filter(explode(' ', normalize_text_filtered($text)), fn ($w) => $w !== ''));
}

// Words that signal WHICH KIND of question is being asked.
// Checked in this priority order - the first category with a hit wins.
const INTENT_SIGNAL_WORDS = [
    'requirements'    => ['requirement', 'requirements', 'need', 'kailangan', 'document', 'documents', 'papers', 'bring', 'checklist', 'kailanganin', 'requirement', 'required'],
    'fees'            => ['fee', 'fees', 'cost', 'price', 'magkano', 'presyo', 'bayad', 'payment', 'gastos', 'much'],
    'deadline'        => ['deadline', 'kailan', 'hanggang'],
    'validity'        => ['valid', 'validity', 'expire', 'expiry', 'expiration'],
    'processing_time' => ['long', 'duration', 'matagal', 'days', 'hours', 'ilang'],
    'where'           => ['where', 'saan', 'location', 'office', 'kung saan', 'san'],
    'steps'           => ['step', 'steps', 'paano', 'process', 'procedure', 'apply', 'application', 'how', 'hakbang', 'proseso', 'ano ang proseso'],
];

// Words unique enough to a specific permit to break ties between
// topics that otherwise share generic words like "business"/"permit".
const TOPIC_SIGNAL_WORDS = [
    'business_permit_renewal' => ['renew', 'renewal', 'existing', 'update'],
    'new_business_permit'     => ['new', 'start', 'starting', 'open', 'opening', 'register', 'registration'],
    'barangay_clearance'      => ['barangay'],
    'cedula'                  => ['cedula', 'ctc', 'community tax'],
    'police_clearance'        => ['police', 'nbi', 'criminal record'],
    'building_permit'         => ['building', 'construction', 'renovation', 'renovate', 'rebuild'],
    'civil_registry'          => ['birth', 'marriage', 'death', 'civil registry', 'psa'],
];

/**
 * Word-overlap score between a message and a comma-separated
 * keyword/phrase list, with stemming + typo tolerance.
 * Higher score = stronger match.
 */
function overlap_score(string $normalizedMessage, string $keywordsCsv): float
{
    $score = 0;
    $messageTokens = tokenize($normalizedMessage);

    $phrases = array_map('trim', explode(',', mb_strtolower($keywordsCsv)));

    foreach ($phrases as $phrase) {
        if ($phrase === '') {
            continue;
        }
        $phraseWords = tokenize($phrase);
        if (empty($phraseWords)) {
            continue;
        }

        $matchCount = 0;
        foreach ($phraseWords as $pw) {
            foreach ($messageTokens as $mw) {
                if (words_are_close($mw, $pw)) {
                    $matchCount++;
                    break;
                }
            }
        }

        // Multi-word phrases must match completely; partial matches
        // are too noisy (e.g. "what" alone must not trigger help_menu,
        // "mayor" alone must not trigger new_business_permit).
        if ($matchCount === 0 || (count($phraseWords) > 1 && $matchCount < count($phraseWords))) {
            continue;
        }

        $score += $matchCount * 2;

        // Bonus: every word of a multi-word phrase was found
        if (count($phraseWords) > 1 && $matchCount === count($phraseWords)) {
            $score += 3;
        }

        // Extra bonus: the exact phrase appears contiguously in the message
        if (str_contains($normalizedMessage, $phrase)) {
            $score += 2 * count($phraseWords);
        }
    }

    return $score;
}

/**
 * Detect which kind of question is being asked, based on signal words.
 * Defaults to 'overview' if nothing specific is detected.
 */
function detect_intent(string $normalizedMessage): string
{
    $messageTokens = tokenize($normalizedMessage);

    foreach (INTENT_SIGNAL_WORDS as $intent => $signals) {
        foreach ($signals as $signal) {
            $signalTokens = tokenize($signal);
            foreach ($signalTokens as $st) {
                foreach ($messageTokens as $mt) {
                    if (words_are_close($mt, $st)) {
                        return $intent;
                    }
                }
            }
        }
    }

    return 'overview';
}

/** Heuristic score -> 0-100 confidence. */
function confidence_from_score(float $score): int
{
    if ($score < 1) return 5;
    if ($score < 4) return (int) round($score * 8);
    return (int) max(35, min(97, round(35 + ($score - 4) * 11)));
}

/**
 * Generate canned answers straight from a permit row (the single
 * source of truth). Only intents with actual data are returned;
 * null means "no generated answer - fall back to kb_entries".
 *
 * @param array $permit a permit row (with description, office,
 *                      address, contact, requirements, steps, fees,
 *                      processing_time, validity)
 * @return array intent => ?string
 */
function generate_kb_answers(array $permit): array
{
    $lines = function (string $text): array {
        return array_values(array_filter(array_map('trim', explode("\n", $text)), fn ($l) => $l !== ''));
    };

    $requirements = $lines($permit['requirements'] ?? '');
    $steps = $lines($permit['steps'] ?? '');
    $office = trim($permit['office'] ?? '');
    $address = trim($permit['address'] ?? '');
    $contact = trim($permit['contact'] ?? '');
    $description = trim($permit['description'] ?? '');

    $answers = [];

    if ($description !== '') {
        $answers['overview'] = $description;
    }

    if ($requirements) {
        $answers['requirements'] = "Requirements:\n" . implode("\n", array_map(
            fn ($r) => '- ' . preg_replace('/^\d+\.\s*/', '', $r),
            $requirements
        ));
    }

    if ($steps) {
        $answers['steps'] = "Steps:\n" . implode("\n", array_map(
            fn ($s, $i) => ($i + 1) . '. ' . preg_replace('/^\d+\.\s*/', '', $s),
            $steps,
            array_keys($steps)
        ));
    }

    if (trim($permit['fees'] ?? '') !== '') {
        $answers['fees'] = trim($permit['fees']);
    }

    if (trim($permit['processing_time'] ?? '') !== '') {
        $answers['processing_time'] = trim($permit['processing_time']);
    }

    if (trim($permit['validity'] ?? '') !== '') {
        $answers['validity'] = trim($permit['validity']);
    }

    if ($office !== '') {
        $where = 'Visit the ' . $office . ($address !== '' ? ' at ' . $address : '') . '.';
        if ($contact !== '') {
            $where .= ' Contact: ' . $contact;
        }
        $answers['where'] = $where;
    }

    return $answers;
}

/**
 * Main entry point: find the best-matching answer for a citizen's message.
 *
 * @param string|null $lastTopicCode permit code of the previous assistant
 *                                   reply, used for follow-up questions.
 * Returns:
 *   ['answer' => string, 'topic' => ?string, 'topic_code' => ?string,
 *    'intent' => string, 'confidence' => int, 'follow_up' => bool,
 *    'suggestions' => string[]]
 */
function get_ai_response(PDO $pdo, string $userMessage, ?string $lastTopicCode = null): array
{
    $normalized = normalize_text($userMessage);
    $messageTokens = tokenize($normalized);
    // Stemmed word set so Tagalog verb prefixes match ("magrenew" hits "renew")
    $messageWordSet = array_flip(array_map('light_stem', $messageTokens));

    $stmt = $pdo->query("
        SELECT k.id, k.permit_id, k.intent, k.keywords, k.answer, k.priority,
               p.name AS permit_name, p.code AS permit_code,
               p.description, p.office, p.address, p.contact,
               p.requirements, p.steps, p.fees, p.processing_time, p.validity
        FROM kb_entries k
        LEFT JOIN permits p ON p.id = k.permit_id
    ");
    $entries = $stmt->fetchAll();

    $topics = [];
    $generalEntries = [];

    foreach ($entries as $e) {
        if ($e['permit_id'] === null) {
            if ($e['intent'] !== 'fallback') {
                $generalEntries[] = $e;
            }
            continue;
        }

        $pid = $e['permit_id'];
        if (!isset($topics[$pid])) {
            $topics[$pid] = [
                'name'              => $e['permit_name'],
                'code'              => $e['permit_code'],
                'overview_keywords' => '',
                'byIntent'          => [],
            ];
        }
        $topics[$pid]['byIntent'][$e['intent']] = $e['answer'];
        if ($e['intent'] === 'overview') {
            $topics[$pid]['overview_keywords'] = $e['keywords'];
        }
    }

    // Single source of truth: answers generated from the permits
    // table take priority; kb_entries only fills the gaps.
    foreach ($topics as $pid => $topic) {
        $permitRow = null;
        foreach ($entries as $e) {
            if ($e['permit_id'] === $pid) {
                $permitRow = $e;
                break;
            }
        }
        if ($permitRow === null) {
            continue;
        }
        foreach (generate_kb_answers($permitRow) as $intent => $answer) {
            if ($answer !== null) {
                $topics[$pid]['byIntent'][$intent] = $answer;
            }
        }
    }

    $topicNames = array_column($topics, 'name');
    $fallbackSuggestions = array_map(fn ($n) => "Tell me about " . $n, $topicNames);

    // --- Stage 0: best matching GENERAL (small-talk) entry ---
    $bestGeneralScore = 0;
    $bestGeneral = null;
    foreach ($generalEntries as $e) {
        $score = overlap_score($normalized, $e['keywords']) + $e['priority'] * 0.1;
        if ($score > $bestGeneralScore) {
            $bestGeneralScore = $score;
            $bestGeneral = $e;
        }
    }

    // --- Stage 1: best matching TOPIC (which permit) ---
    $bestTopicScore = 0;
    $bestTopicId = null;
    foreach ($topics as $pid => $topic) {
        $score = overlap_score($normalized, $topic['overview_keywords']);

        if (isset(TOPIC_SIGNAL_WORDS[$topic['code']])) {
            foreach (TOPIC_SIGNAL_WORDS[$topic['code']] as $signal) {
                $signalWords = array_map('light_stem', tokenize($signal));
                $allFound = true;
                foreach ($signalWords as $sw) {
                    if (!isset($messageWordSet[$sw])) {
                        $allFound = false;
                        break;
                    }
                }
                if ($allFound) {
                    $score += 10;
                    break;
                }
            }
        }

        if ($score > $bestTopicScore) {
            $bestTopicScore = $score;
            $bestTopicId = $pid;
        }
    }

    $CONFIDENCE_THRESHOLD = 4;

    // General small-talk wins if it scores at least as well as any topic match
    if ($bestGeneral !== null && $bestGeneralScore >= $CONFIDENCE_THRESHOLD && $bestGeneralScore >= $bestTopicScore) {
        return [
            'answer'      => $bestGeneral['answer'],
            'topic'       => null,
            'topic_code'  => null,
            'intent'      => $bestGeneral['intent'],
            'confidence'  => confidence_from_score($bestGeneralScore),
            'follow_up'   => false,
            'suggestions' => $fallbackSuggestions,
        ];
    }

    // --- Stage 2: INTENT within the matched topic ---
    if ($bestTopicId !== null && $bestTopicScore >= $CONFIDENCE_THRESHOLD) {
        $topic = $topics[$bestTopicId];
        $intent = detect_intent($normalized);
        $answer = $topic['byIntent'][$intent] ?? $topic['byIntent']['overview'] ?? null;

        if ($answer !== null) {
            return [
                'answer'      => $answer,
                'topic'       => $topic['name'],
                'topic_code'  => $topic['code'],
                'intent'      => $intent,
                'confidence'  => confidence_from_score($bestTopicScore),
                'follow_up'   => false,
                'suggestions' => [
                    "What are the requirements?",
                    "How do I apply?",
                    "How much does it cost?",
                    "How long does it take?",
                ],
            ];
        }
    }

    // --- Stage 3: FOLLOW-UP context - same topic, new question ---
    if ($lastTopicCode !== null) {
        foreach ($topics as $pid => $topic) {
            if ($topic['code'] === $lastTopicCode) {
                $intent = detect_intent($normalized);
                $answer = $topic['byIntent'][$intent] ?? $topic['byIntent']['overview'] ?? null;

                if ($answer !== null) {
                    return [
                        'answer'      => $answer,
                        'topic'       => $topic['name'],
                        'topic_code'  => $topic['code'],
                        'intent'      => $intent,
                        'confidence'  => confidence_from_score(max($bestTopicScore, 4)),
                        'follow_up'   => true,
                        'suggestions' => [
                            "What are the requirements?",
                            "How do I apply?",
                            "How much does it cost?",
                        ],
                    ];
                }
            }
        }
    }

    // --- Stage 4: FALLBACK with "did you mean" suggestions ---
    $fallbackStmt = $pdo->prepare("SELECT answer FROM kb_entries WHERE intent = 'fallback' LIMIT 1");
    $fallbackStmt->execute();
    $fallback = $fallbackStmt->fetchColumn();

    return [
        'answer'      => $fallback ?: "I'm not sure I understood that. Try asking about Barangay Clearance, New Business Permit, or Business Permit Renewal.",
        'topic'       => null,
        'topic_code'  => null,
        'intent'      => 'fallback',
        'confidence'  => confidence_from_score($bestTopicScore),
        'follow_up'   => false,
        'suggestions' => $fallbackSuggestions,
    ];
}
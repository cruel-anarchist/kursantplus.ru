<?php

declare(strict_types=1);

$config = require __DIR__ . '/config.php';

$originAllowed = handleCors($config['cors']['allowed_origins'] ?? []);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (($_SERVER['HTTP_ORIGIN'] ?? '') !== '' && !$originAllowed) {
    respondJson(['ok' => false, 'message' => 'Origin is not allowed.'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondJson(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$honeypot = trim((string)($_POST['_honey'] ?? ''));
if ($honeypot !== '') {
    respondJson(['ok' => true, 'message' => 'Request accepted.']);
}

$name = cleanText($_POST['name'] ?? '');
$phone = cleanText($_POST['phone'] ?? '');
$email = cleanText($_POST['email'] ?? '');
$topic = cleanText($_POST['topic'] ?? '');
$message = cleanText($_POST['message'] ?? '');
$privacyConsent = strtolower(trim((string)($_POST['privacy_consent'] ?? '')));
$sourcePage = cleanText($_POST['page_url'] ?? '');

if ($name === '' || $phone === '' || $topic === '' || $message === '') {
    respondJson(['ok' => false, 'message' => 'Заполните обязательные поля формы.'], 422);
}

if (!in_array($privacyConsent, ['yes', 'on', '1', 'true'], true)) {
    respondJson(['ok' => false, 'message' => 'Нужно подтвердить ознакомление с политикой конфиденциальности.'], 422);
}

if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    respondJson(['ok' => false, 'message' => 'Проверьте корректность email.'], 422);
}

$requestOrigin = cleanText($_SERVER['HTTP_ORIGIN'] ?? '');
$referer = cleanText($_SERVER['HTTP_REFERER'] ?? '');
$ipAddress = detectIp();
$userAgent = cleanText($_SERVER['HTTP_USER_AGENT'] ?? '');
$requestId = '';
$savedToDatabase = false;

try {
    $pdo = createPdo($config['db']);
    ensureSchema($pdo);

    $statement = $pdo->prepare(
        'INSERT INTO contact_requests
            (name, phone, email, topic, message, privacy_consent, source_page, request_origin, referer, ip_address, user_agent)
         VALUES
            (:name, :phone, :email, :topic, :message, :privacy_consent, :source_page, :request_origin, :referer, :ip_address, :user_agent)'
    );

    $statement->execute([
        ':name' => $name,
        ':phone' => $phone,
        ':email' => $email !== '' ? $email : null,
        ':topic' => $topic,
        ':message' => $message,
        ':privacy_consent' => 1,
        ':source_page' => $sourcePage !== '' ? $sourcePage : null,
        ':request_origin' => $requestOrigin !== '' ? $requestOrigin : null,
        ':referer' => $referer !== '' ? $referer : null,
        ':ip_address' => $ipAddress !== '' ? $ipAddress : null,
        ':user_agent' => $userAgent !== '' ? $userAgent : null,
    ]);

    $requestId = (string)$pdo->lastInsertId();
    $savedToDatabase = true;
} catch (Throwable $exception) {
    logBackendError('database', $exception);
}

$mailSent = false;

try {
    $mailSent = sendLeadEmail($config['mail'], [
        'id' => $requestId !== '' ? $requestId : 'not-saved',
        'name' => $name,
        'phone' => $phone,
        'email' => $email,
        'topic' => $topic,
        'message' => $message,
        'source_page' => $sourcePage,
        'request_origin' => $requestOrigin,
        'referer' => $referer,
        'ip_address' => $ipAddress,
        'user_agent' => $userAgent,
    ]);
} catch (Throwable $exception) {
    logBackendError('mail', $exception);
    $mailSent = false;
}

writeLeadMirrorLog([
    'id' => $requestId !== '' ? $requestId : null,
    'name' => $name,
    'phone' => $phone,
    'email' => $email !== '' ? $email : null,
    'topic' => $topic,
    'message' => $message,
    'privacy_consent' => true,
    'source_page' => $sourcePage !== '' ? $sourcePage : null,
    'request_origin' => $requestOrigin !== '' ? $requestOrigin : null,
    'referer' => $referer !== '' ? $referer : null,
    'ip_address' => $ipAddress !== '' ? $ipAddress : null,
    'user_agent' => $userAgent !== '' ? $userAgent : null,
    'saved_to_database' => $savedToDatabase,
    'mail_sent' => $mailSent,
]);

if (!$savedToDatabase && !$mailSent) {
    respondJson([
        'ok' => false,
        'message' => 'Не удалось отправить заявку. Попробуйте ещё раз чуть позже или свяжитесь с автошколой по телефону.',
    ], 500);
}

if ($savedToDatabase && !$mailSent) {
    respondJson([
        'ok' => true,
        'message' => 'Заявка сохранена. Если письмо не уйдёт автоматически, мы всё равно увидим её в базе данных.',
    ]);
}

respondJson([
    'ok' => true,
    'message' => 'Сообщение отправлено. Мы получили заявку и свяжемся с вами по указанным контактам.',
]);

function handleCors(array $allowedOrigins): bool
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Accept');
        return true;
    }

    return $origin === '';
}

function createPdo(array $dbConfig): PDO
{
    $host = $dbConfig['host'] ?? '127.0.0.1';
    $port = (int)($dbConfig['port'] ?? 3306);
    $database = $dbConfig['database'] ?? '';
    $charset = $dbConfig['charset'] ?? 'utf8mb4';

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $database, $charset);

    return new PDO(
        $dsn,
        (string)($dbConfig['username'] ?? ''),
        (string)($dbConfig['password'] ?? ''),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
}

function ensureSchema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS contact_requests (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(120) NOT NULL,
            phone VARCHAR(40) NOT NULL,
            email VARCHAR(190) DEFAULT NULL,
            topic VARCHAR(120) NOT NULL,
            message TEXT NOT NULL,
            privacy_consent TINYINT(1) NOT NULL DEFAULT 0,
            source_page VARCHAR(255) DEFAULT NULL,
            request_origin VARCHAR(255) DEFAULT NULL,
            referer VARCHAR(255) DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent TEXT DEFAULT NULL,
            status VARCHAR(32) NOT NULL DEFAULT "new",
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_contact_requests_created_at (created_at),
            KEY idx_contact_requests_status (status),
            KEY idx_contact_requests_topic (topic)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function cleanText(mixed $value): string
{
    return trim((string)$value);
}

function logBackendError(string $channel, Throwable $exception): void
{
    $basePath = dirname(__DIR__, 2);
    $logDirectory = $basePath . DIRECTORY_SEPARATOR . 'logs';

    if (!is_dir($logDirectory)) {
        return;
    }

    $logFile = $logDirectory . DIRECTORY_SEPARATOR . 'kursantplus-form.log';
    $message = sprintf(
        "[%s] [%s] %s in %s:%d\n",
        date('Y-m-d H:i:s'),
        $channel,
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine()
    );

    error_log($message, 3, $logFile);
}

function writeLeadMirrorLog(array $lead): void
{
    $basePath = dirname(__DIR__, 2);
    $logDirectory = $basePath . DIRECTORY_SEPARATOR . 'logs';

    if (!is_dir($logDirectory)) {
        return;
    }

    $logFile = $logDirectory . DIRECTORY_SEPARATOR . 'kursantplus-leads.jsonl';
    $payload = [
        'received_at' => date('c'),
        'lead' => $lead,
    ];

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($json === false) {
        return;
    }

    error_log($json . PHP_EOL, 3, $logFile);
}

function detectIp(): string
{
    $candidates = [
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
        $_SERVER['REMOTE_ADDR'] ?? '',
    ];

    foreach ($candidates as $candidate) {
        if ($candidate === '') {
            continue;
        }

        $first = trim(explode(',', $candidate)[0]);
        if (filter_var($first, FILTER_VALIDATE_IP)) {
            return $first;
        }
    }

    return '';
}

function respondJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function sendLeadEmail(array $mailConfig, array $lead): bool
{
    $transport = $mailConfig['transport'] ?? 'mail';

    return match ($transport) {
        'smtp' => sendViaSmtp($mailConfig, $lead),
        default => sendViaMail($mailConfig, $lead),
    };
}

function sendViaMail(array $mailConfig, array $lead): bool
{
    $recipient = (string)($mailConfig['recipient'] ?? '');
    $fromEmail = (string)($mailConfig['from_email'] ?? 'noreply@kursantplus.ru');
    $fromName = (string)($mailConfig['from_name'] ?? 'Kursant+');

    if ($recipient === '') {
        throw new RuntimeException('Mail recipient is empty.');
    }

    $subject = encodeHeader('Новая заявка с сайта Курсант +');
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'From: ' . formatMailbox($fromEmail, $fromName),
    ];

    if ($lead['email'] !== '') {
        $headers[] = 'Reply-To: ' . formatMailbox($lead['email'], $lead['name']);
    }

    return mail($recipient, $subject, buildLeadMessage($lead), implode("\r\n", $headers));
}

function sendViaSmtp(array $mailConfig, array $lead): bool
{
    $smtp = $mailConfig['smtp'] ?? [];
    $host = (string)($smtp['host'] ?? '');
    $port = (int)($smtp['port'] ?? 465);
    $encryption = (string)($smtp['encryption'] ?? 'ssl');
    $username = (string)($smtp['username'] ?? '');
    $password = (string)($smtp['password'] ?? '');
    $timeout = (int)($smtp['timeout'] ?? 20);
    $recipient = (string)($mailConfig['recipient'] ?? '');
    $fromEmail = (string)($mailConfig['from_email'] ?? $username);
    $fromName = (string)($mailConfig['from_name'] ?? 'Kursant+');

    if ($host === '' || $username === '' || $password === '' || $recipient === '') {
        throw new RuntimeException('SMTP config is incomplete.');
    }

    $prefix = $encryption === 'ssl' ? 'ssl://' : '';
    $socket = @stream_socket_client(
        $prefix . $host . ':' . $port,
        $errorNumber,
        $errorMessage,
        $timeout,
        STREAM_CLIENT_CONNECT
    );

    if (!is_resource($socket)) {
        throw new RuntimeException('SMTP connection failed: ' . $errorMessage);
    }

    stream_set_timeout($socket, $timeout);

    smtpExpect($socket, 220);
    smtpCommand($socket, 'EHLO api.kursantplus.ru', 250);
    smtpCommand($socket, 'AUTH LOGIN', 334);
    smtpCommand($socket, base64_encode($username), 334);
    smtpCommand($socket, base64_encode($password), 235);
    smtpCommand($socket, 'MAIL FROM:<' . $fromEmail . '>', 250);
    smtpCommand($socket, 'RCPT TO:<' . $recipient . '>', 250);
    smtpCommand($socket, 'DATA', 354);

    $headers = [
        'Date: ' . date(DATE_RFC2822),
        'From: ' . formatMailbox($fromEmail, $fromName),
        'To: ' . $recipient,
        'Subject: ' . encodeHeader('Новая заявка с сайта Курсант +'),
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ];

    if ($lead['email'] !== '') {
        $headers[] = 'Reply-To: ' . formatMailbox($lead['email'], $lead['name']);
    }

    $payload = implode("\r\n", $headers) . "\r\n\r\n" . dotStuff(buildLeadMessage($lead)) . "\r\n.";
    fwrite($socket, $payload . "\r\n");
    smtpExpect($socket, 250);
    smtpCommand($socket, 'QUIT', 221);
    fclose($socket);

    return true;
}

function smtpCommand($socket, string $command, int $expectedCode): string
{
    fwrite($socket, $command . "\r\n");
    return smtpExpect($socket, $expectedCode);
}

function smtpExpect($socket, int $expectedCode): string
{
    $response = '';

    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }

    if ((int)substr($response, 0, 3) !== $expectedCode) {
        throw new RuntimeException('Unexpected SMTP response: ' . trim($response));
    }

    return $response;
}

function buildLeadMessage(array $lead): string
{
    $lines = [
        'Новая заявка с сайта Курсант +',
        'ID заявки: ' . $lead['id'],
        '',
        'Имя: ' . $lead['name'],
        'Телефон: ' . $lead['phone'],
        'Email: ' . ($lead['email'] !== '' ? $lead['email'] : 'не указан'),
        'Тема: ' . $lead['topic'],
        '',
        'Сообщение:',
        $lead['message'],
    ];

    if ($lead['source_page'] !== '') {
        $lines[] = '';
        $lines[] = 'Страница: ' . $lead['source_page'];
    }

    if ($lead['request_origin'] !== '') {
        $lines[] = 'Origin: ' . $lead['request_origin'];
    }

    if ($lead['referer'] !== '') {
        $lines[] = 'Referer: ' . $lead['referer'];
    }

    if ($lead['ip_address'] !== '') {
        $lines[] = 'IP: ' . $lead['ip_address'];
    }

    if ($lead['user_agent'] !== '') {
        $lines[] = 'User-Agent: ' . $lead['user_agent'];
    }

    return implode("\r\n", $lines);
}

function encodeHeader(string $value): string
{
    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

function formatMailbox(string $email, string $name): string
{
    $cleanName = trim($name);
    if ($cleanName === '') {
        return $email;
    }

    return encodeHeader($cleanName) . ' <' . $email . '>';
}

function dotStuff(string $text): string
{
    $normalized = preg_replace("/\r\n|\r|\n/", "\r\n", $text) ?? $text;
    return preg_replace('/^\./m', '..', $normalized) ?? $normalized;
}

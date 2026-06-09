<?php

declare(strict_types=1);

$config = require dirname(__DIR__) . '/config.php';

enforceBasicAuth($config['admin'] ?? []);

$currentView = resolveView($_GET['view'] ?? 'active');
$searchQuery = cleanSearch($_GET['q'] ?? '');
$notice = resolveNotice($_GET['message'] ?? '');
$databaseError = null;
$logError = null;
$source = 'database';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $leadId = (string)($_POST['lead_id'] ?? '');
    $redirectQuery = http_build_query([
        'view' => $currentView,
        'q' => $searchQuery !== '' ? $searchQuery : null,
    ]);

    if (in_array($action, ['archive', 'progress', 'restore'], true) && ctype_digit($leadId)) {
        try {
            updateLeadStatus(
                $config['db'] ?? [],
                (int)$leadId,
                match ($action) {
                    'archive' => 'archived',
                    'progress' => 'in_progress',
                    'restore' => 'new',
                }
            );

            $message = match ($action) {
                'archive' => 'archived',
                'progress' => 'progress',
                'restore' => 'restored',
            };

            header('Location: /admin/?' . $redirectQuery . '&message=' . $message);
            exit;
        } catch (Throwable $exception) {
            $notice = [
                'type' => 'error',
                'text' => 'Не удалось обновить статус заявки. Попробуйте ещё раз.',
            ];
            $databaseError = $exception->getMessage();
        }
    }
}

$leads = fetchDatabaseLeads($config['db'] ?? [], $currentView, $searchQuery, $databaseError);

if ($leads === null) {
    $source = 'log';
    $leads = $currentView === 'active' ? fetchMirrorLogLeads($searchQuery, $logError) : [];
}

$summary = buildSummary($leads);
$counts = fetchLeadCounts($config['db'] ?? [], $searchQuery, $databaseError);

if ($counts === null) {
    $counts = [
        'active' => $currentView === 'active' ? count($leads) : 0,
        'archived' => 0,
    ];
}

function enforceBasicAuth(array $adminConfig): void
{
    $expectedUser = (string)($adminConfig['username'] ?? '');
    $expectedPassword = (string)($adminConfig['password'] ?? '');

    $providedUser = $_SERVER['PHP_AUTH_USER'] ?? '';
    $providedPassword = $_SERVER['PHP_AUTH_PW'] ?? '';

    if (
        $expectedUser === '' ||
        $expectedPassword === '' ||
        !hash_equals($expectedUser, $providedUser) ||
        !hash_equals($expectedPassword, $providedPassword)
    ) {
        header('WWW-Authenticate: Basic realm="Kursant+ Admin"');
        header('HTTP/1.1 401 Unauthorized');
        echo 'Authorization required.';
        exit;
    }
}

function resolveView(mixed $value): string
{
    return $value === 'archive' ? 'archive' : 'active';
}

function resolveNotice(mixed $value): ?array
{
    return match ((string)$value) {
        'archived' => [
            'type' => 'success',
            'text' => 'Заявка отправлена в архив.',
        ],
        'progress' => [
            'type' => 'success',
            'text' => 'Заявка переведена в работу.',
        ],
        'restored' => [
            'type' => 'success',
            'text' => 'Заявка возвращена в активные.',
        ],
        default => null,
    };
}

function cleanSearch(mixed $value): string
{
    return trim((string)$value);
}

function createPdo(array $dbConfig): PDO
{
    return new PDO(
        sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $dbConfig['host'] ?? '127.0.0.1',
            (int)($dbConfig['port'] ?? 3306),
            $dbConfig['database'] ?? '',
            $dbConfig['charset'] ?? 'utf8mb4'
        ),
        (string)($dbConfig['username'] ?? ''),
        (string)($dbConfig['password'] ?? ''),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
}

function updateLeadStatus(array $dbConfig, int $leadId, string $status): void
{
    $pdo = createPdo($dbConfig);
    $statement = $pdo->prepare('UPDATE contact_requests SET status = :status WHERE id = :id');
    $statement->execute([
        ':status' => $status,
        ':id' => $leadId,
    ]);
}

function fetchDatabaseLeads(array $dbConfig, string $view, string $searchQuery, ?string &$error = null): ?array
{
    try {
        $pdo = createPdo($dbConfig);
        $params = [];
        $conditions = [];

        if ($view === 'archive') {
            $conditions[] = 'status = :archived_status';
            $params[':archived_status'] = 'archived';
        } else {
            $conditions[] = 'status <> :archived_status';
            $params[':archived_status'] = 'archived';
        }

        if ($searchQuery !== '') {
            $conditions[] = '(name LIKE :search_name OR phone LIKE :search_phone OR email LIKE :search_email OR topic LIKE :search_topic OR message LIKE :search_message)';
            $searchValue = '%' . $searchQuery . '%';
            $params[':search_name'] = $searchValue;
            $params[':search_phone'] = $searchValue;
            $params[':search_email'] = $searchValue;
            $params[':search_topic'] = $searchValue;
            $params[':search_message'] = $searchValue;
        }

        $statement = $pdo->prepare(
            'SELECT id, name, phone, email, topic, message, privacy_consent, source_page, request_origin, referer, ip_address, user_agent, status, created_at
             FROM contact_requests
             WHERE ' . implode(' AND ', $conditions) . '
             ORDER BY created_at DESC
             LIMIT 100'
        );
        $statement->execute($params);

        $rows = $statement->fetchAll();

        return array_map(static function (array $row): array {
            return [
                'received_at' => (string)($row['created_at'] ?? ''),
                'id' => (string)($row['id'] ?? ''),
                'name' => (string)($row['name'] ?? ''),
                'phone' => (string)($row['phone'] ?? ''),
                'email' => (string)($row['email'] ?? ''),
                'topic' => (string)($row['topic'] ?? ''),
                'message' => (string)($row['message'] ?? ''),
                'privacy_consent' => (bool)($row['privacy_consent'] ?? false),
                'source_page' => (string)($row['source_page'] ?? ''),
                'request_origin' => (string)($row['request_origin'] ?? ''),
                'referer' => (string)($row['referer'] ?? ''),
                'ip_address' => (string)($row['ip_address'] ?? ''),
                'user_agent' => (string)($row['user_agent'] ?? ''),
                'saved_to_database' => true,
                'mail_sent' => null,
                'status' => (string)($row['status'] ?? 'new'),
                'source_type' => 'database',
            ];
        }, $rows);
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
        return null;
    }
}

function fetchLeadCounts(array $dbConfig, string $searchQuery, ?string &$error = null): ?array
{
    try {
        $pdo = createPdo($dbConfig);
        $params = [];
        $searchSql = '';

        if ($searchQuery !== '') {
            $searchSql = 'WHERE (
                name LIKE :search_name OR
                phone LIKE :search_phone OR
                email LIKE :search_email OR
                topic LIKE :search_topic OR
                message LIKE :search_message
            )';
            $searchValue = '%' . $searchQuery . '%';
            $params = [
                ':search_name' => $searchValue,
                ':search_phone' => $searchValue,
                ':search_email' => $searchValue,
                ':search_topic' => $searchValue,
                ':search_message' => $searchValue,
            ];
        }

        $statement = $pdo->prepare(
            "SELECT
                SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END) AS archived_count,
                SUM(CASE WHEN status <> 'archived' THEN 1 ELSE 0 END) AS active_count
             FROM contact_requests
             $searchSql"
        );
        $statement->execute($params);
        $row = $statement->fetch() ?: [];

        return [
            'active' => (int)($row['active_count'] ?? 0),
            'archived' => (int)($row['archived_count'] ?? 0),
        ];
    } catch (Throwable $exception) {
        $error = $error ?? $exception->getMessage();
        return null;
    }
}

function fetchMirrorLogLeads(string $searchQuery, ?string &$error = null): array
{
    try {
        $logPath = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'kursantplus-leads.jsonl';

        if (!is_file($logPath)) {
            return [];
        }

        $lines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return [];
        }

        $items = [];

        foreach (array_reverse($lines) as $line) {
            $decoded = json_decode($line, true);

            if (!is_array($decoded) || !isset($decoded['lead']) || !is_array($decoded['lead'])) {
                continue;
            }

            $lead = $decoded['lead'];
            $haystack = implode("\n", [
                (string)($lead['name'] ?? ''),
                (string)($lead['phone'] ?? ''),
                (string)($lead['email'] ?? ''),
                (string)($lead['topic'] ?? ''),
                (string)($lead['message'] ?? ''),
            ]);

            if ($searchQuery !== '' && mb_stripos($haystack, $searchQuery) === false) {
                continue;
            }

            $items[] = [
                'received_at' => (string)($decoded['received_at'] ?? ''),
                'id' => (string)($lead['id'] ?? ''),
                'name' => (string)($lead['name'] ?? ''),
                'phone' => (string)($lead['phone'] ?? ''),
                'email' => (string)($lead['email'] ?? ''),
                'topic' => (string)($lead['topic'] ?? ''),
                'message' => (string)($lead['message'] ?? ''),
                'privacy_consent' => (bool)($lead['privacy_consent'] ?? false),
                'source_page' => (string)($lead['source_page'] ?? ''),
                'request_origin' => (string)($lead['request_origin'] ?? ''),
                'referer' => (string)($lead['referer'] ?? ''),
                'ip_address' => (string)($lead['ip_address'] ?? ''),
                'user_agent' => (string)($lead['user_agent'] ?? ''),
                'saved_to_database' => (bool)($lead['saved_to_database'] ?? false),
                'mail_sent' => isset($lead['mail_sent']) ? (bool)$lead['mail_sent'] : null,
                'status' => $lead['saved_to_database'] ?? false ? 'new' : 'pending',
                'source_type' => 'log',
            ];
        }

        return array_slice($items, 0, 100);
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
        return [];
    }
}

function buildSummary(array $leads): array
{
    $total = count($leads);
    $withEmail = 0;
    $saved = 0;
    $mailSent = 0;

    foreach ($leads as $lead) {
        if (($lead['email'] ?? '') !== '') {
            $withEmail++;
        }

        if (($lead['saved_to_database'] ?? false) === true) {
            $saved++;
        }

        if (($lead['mail_sent'] ?? false) === true) {
            $mailSent++;
        }
    }

    return [
        'total' => $total,
        'with_email' => $withEmail,
        'saved' => $saved,
        'mail_sent' => $mailSent,
    ];
}

function escapeHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function formatDateTime(string $value): string
{
    if ($value === '') {
        return 'Не указано';
    }

    try {
        return (new DateTimeImmutable($value))->format('d.m.Y H:i');
    } catch (Throwable) {
        return $value;
    }
}

function renderFlag(?bool $flag, string $positive, string $negative, string $neutral = 'Неизвестно'): string
{
    if ($flag === true) {
        return $positive;
    }

    if ($flag === false) {
        return $negative;
    }

    return $neutral;
}

function renderStatusLabel(string $status): string
{
    return match ($status) {
        'archived' => 'В архиве',
        'in_progress' => 'В работе',
        'pending' => 'Резервный лог',
        default => 'Активная',
    };
}
?>
<!doctype html>
<html lang="ru">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Заявки Курсант+ | Admin</title>
    <style>
      :root {
        color-scheme: light;
      }

      * {
        box-sizing: border-box;
      }

      body {
        margin: 0;
        font-family: "Segoe UI", Tahoma, sans-serif;
        color: #14213f;
        background:
          radial-gradient(circle at top right, rgba(227, 194, 99, 0.18), transparent 24%),
          radial-gradient(circle at bottom left, rgba(179, 32, 37, 0.08), transparent 26%),
          linear-gradient(180deg, #fbfaf6, #f2efe8);
      }

      .page {
        width: min(100%, 1240px);
        margin: 0 auto;
        padding: 2rem 1rem 3rem;
      }

      .hero {
        display: grid;
        gap: 1rem;
        grid-template-columns: minmax(0, 1.7fr) minmax(280px, 0.9fr);
        align-items: start;
      }

      .hero__main,
      .hero__aside,
      .summary-card,
      .lead-card,
      .notice {
        border-radius: 28px;
        background: rgba(255, 255, 255, 0.94);
        box-shadow: 0 20px 50px rgba(20, 33, 63, 0.08);
      }

      .hero__main {
        padding: 2rem 2rem 1.8rem;
      }

      .hero__aside {
        padding: 1.5rem;
      }

      .eyebrow {
        margin: 0 0 0.8rem;
        color: #b32025;
        font-size: 0.8rem;
        font-weight: 800;
        letter-spacing: 0.14em;
        text-transform: uppercase;
      }

      h1 {
        margin: 0 0 0.9rem;
        font-family: Georgia, "Times New Roman", serif;
        font-size: clamp(2.2rem, 1.9rem + 1.4vw, 4rem);
        line-height: 1.02;
      }

      h2,
      h3 {
        margin: 0;
        font-family: Georgia, "Times New Roman", serif;
      }

      .hero__text {
        margin: 0;
        max-width: 54rem;
        line-height: 1.7;
        color: rgba(20, 33, 63, 0.8);
      }

      .hero__meta {
        margin-top: 1.2rem;
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
      }

      .chip {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        min-height: 2.7rem;
        padding: 0.55rem 0.95rem;
        border-radius: 999px;
        background: rgba(20, 33, 63, 0.06);
        color: #14213f;
        font-size: 0.95rem;
        font-weight: 700;
      }

      .chip--accent {
        background: linear-gradient(135deg, #d8a73c, #f0cb67);
      }

      .chip--dark {
        background: #18254b;
        color: #fff;
      }

      .hero__aside h2 {
        font-size: clamp(1.7rem, 1.45rem + 0.8vw, 2.5rem);
        line-height: 1.04;
        margin-bottom: 0.85rem;
      }

      .hero__aside p {
        margin: 0;
        line-height: 1.65;
        color: rgba(20, 33, 63, 0.78);
      }

      .summary {
        margin-top: 1rem;
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(4, minmax(0, 1fr));
      }

      .summary-card {
        padding: 1.35rem 1.2rem;
      }

      .summary-card strong {
        display: block;
        font-family: Georgia, "Times New Roman", serif;
        font-size: clamp(1.9rem, 1.7rem + 0.6vw, 2.7rem);
        line-height: 1;
        margin-bottom: 0.35rem;
      }

      .summary-card span {
        color: rgba(20, 33, 63, 0.74);
        font-size: 0.95rem;
      }

      .toolbar {
        margin-top: 1rem;
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        align-items: center;
        justify-content: space-between;
      }

      .toolbar__left {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        align-items: center;
        flex: 1 1 46rem;
      }

      .tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
      }

      .tab {
        display: inline-flex;
        align-items: center;
        gap: 0.55rem;
        padding: 0.85rem 1.15rem;
        border-radius: 999px;
        border: 1px solid rgba(20, 33, 63, 0.1);
        background: rgba(255, 255, 255, 0.75);
        color: #14213f;
        text-decoration: none;
        font-weight: 700;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
      }

      .tab:hover {
        transform: translateY(-1px);
        box-shadow: 0 10px 24px rgba(20, 33, 63, 0.1);
      }

      .tab--active {
        background: #18254b;
        color: #fff;
        border-color: transparent;
      }

      .tab__count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 1.8rem;
        height: 1.8rem;
        padding: 0 0.5rem;
        border-radius: 999px;
        background: rgba(20, 33, 63, 0.08);
        font-size: 0.85rem;
      }

      .tab--active .tab__count {
        background: rgba(255, 255, 255, 0.18);
      }

      .toolbar__note {
        flex: 0 1 26rem;
        margin-left: auto;
        color: rgba(20, 33, 63, 0.74);
        font-size: 0.95rem;
        text-align: right;
      }

      .search-form {
        display: flex;
        flex-wrap: nowrap;
        gap: 0.65rem;
        align-items: center;
        flex: 1 1 27rem;
      }

      .search-form__field {
        position: relative;
        flex: 1 1 21rem;
        min-width: 16rem;
      }

      .search-form__input {
        width: 100%;
        min-height: 3rem;
        padding: 0.8rem 2.8rem 0.8rem 1rem;
        border-radius: 999px;
        border: 1px solid rgba(20, 33, 63, 0.15);
        background: rgba(255, 255, 255, 0.82);
        color: #14213f;
        font: inherit;
      }

      .search-form__input::-webkit-search-cancel-button {
        -webkit-appearance: none;
        appearance: none;
      }

      .search-form__clear {
        position: absolute;
        top: 50%;
        right: 0.85rem;
        transform: translateY(-50%);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.6rem;
        height: 1.6rem;
        border-radius: 999px;
        color: rgba(20, 33, 63, 0.56);
        text-decoration: none;
        font-size: 1rem;
        font-weight: 700;
        line-height: 1;
      }

      .search-form__clear:hover {
        background: rgba(20, 33, 63, 0.08);
        color: #14213f;
      }

      .search-form__button {
        appearance: none;
        border: 0;
        cursor: pointer;
        min-height: 3rem;
        padding: 0.8rem 1.15rem;
        border-radius: 999px;
        background: #18254b;
        color: #fff;
        font: inherit;
        font-weight: 700;
        white-space: nowrap;
        flex: 0 0 auto;
      }

      .search-form__button:hover {
        transform: translateY(-1px);
        box-shadow: 0 10px 24px rgba(20, 33, 63, 0.18);
      }

      .notice {
        margin-top: 1rem;
        padding: 1rem 1.2rem;
        font-weight: 700;
      }

      .notice--success {
        border-left: 5px solid #d8a73c;
      }

      .notice--error {
        border-left: 5px solid #b32025;
      }

      .lead-list {
        margin-top: 1rem;
        display: grid;
        gap: 1rem;
      }

      .lead-card {
        overflow: hidden;
      }

      .lead-card__head {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        gap: 1rem;
        align-items: start;
        padding: 1.35rem 1.5rem 1rem;
      }

      .lead-card__name {
        display: grid;
        gap: 0.3rem;
      }

      .lead-card__name h3 {
        font-size: clamp(1.45rem, 1.25rem + 0.5vw, 2rem);
      }

      .lead-card__meta {
        color: rgba(20, 33, 63, 0.72);
        font-size: 0.95rem;
      }

      .lead-card__chips {
        display: flex;
        flex-wrap: wrap;
        gap: 0.55rem;
      }

      .lead-card__body {
        padding: 0 1.5rem 1.5rem;
      }

      .lead-card__topic {
        margin: 0 0 0.85rem;
        color: #b32025;
        font-weight: 800;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        font-size: 0.88rem;
      }

      .lead-card__message {
        margin: 0;
        padding: 1rem 1.05rem;
        border-radius: 18px;
        background: rgba(20, 33, 63, 0.05);
        line-height: 1.7;
      }

      .lead-card__grid {
        margin-top: 1rem;
        display: grid;
        gap: 0.75rem;
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }

      .lead-card__field {
        padding: 0.9rem 1rem;
        border-radius: 18px;
        background: rgba(20, 33, 63, 0.035);
      }

      .lead-card__field small {
        display: block;
        margin-bottom: 0.35rem;
        color: rgba(20, 33, 63, 0.56);
        font-size: 0.82rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        font-weight: 700;
      }

      .lead-card__field span,
      .lead-card__field a {
        color: #14213f;
        text-decoration: none;
        word-break: break-word;
      }

      .lead-card__actions {
        margin-top: 1rem;
        display: flex;
        justify-content: flex-end;
        flex-wrap: wrap;
        gap: 0.75rem;
      }

      .lead-card__button {
        appearance: none;
        border: 0;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 3rem;
        padding: 0.85rem 1.35rem;
        border-radius: 999px;
        background: linear-gradient(135deg, #d8a73c, #f0cb67);
        color: #14213f;
        font-weight: 800;
        font-size: 0.95rem;
        box-shadow: 0 12px 24px rgba(216, 167, 60, 0.22);
      }

      .lead-card__button:hover {
        transform: translateY(-1px);
      }

      .lead-card__button--dark {
        background: #18254b;
        color: #fff;
        box-shadow: 0 12px 24px rgba(20, 33, 63, 0.16);
      }

      .lead-card__button--light {
        background: rgba(20, 33, 63, 0.08);
        color: #14213f;
        box-shadow: none;
      }

      .empty-state {
        margin-top: 1rem;
        padding: 1.8rem;
        border-radius: 28px;
        background: rgba(255, 255, 255, 0.94);
        box-shadow: 0 20px 50px rgba(20, 33, 63, 0.08);
      }

      .empty-state p {
        margin: 0;
        line-height: 1.7;
        color: rgba(20, 33, 63, 0.78);
      }

      @media (max-width: 980px) {
        .hero,
        .summary {
          grid-template-columns: 1fr;
        }

        .lead-card__grid {
          grid-template-columns: 1fr;
        }

        .toolbar {
          align-items: stretch;
        }

        .toolbar__left {
          width: 100%;
        }

        .toolbar__note {
          flex-basis: 100%;
          margin-left: 0;
          text-align: left;
        }

        .search-form {
          flex-wrap: wrap;
        }

        .search-form__field {
          min-width: 100%;
        }
      }
    </style>
  </head>
  <body>
    <main class="page">
      <section class="hero">
        <div class="hero__main">
          <p class="eyebrow">Закрытый раздел</p>
          <h1>Заявки с сайта Курсант+</h1>
          <p class="hero__text">
            Здесь собраны входящие обращения с формы сайта: запись на обучение,
            пробные уроки, вопросы по тарифам и дополнительные заявки. Приоритет
            у базы данных, а если она недоступна — страница умеет показывать
            резервный зеркальный лог.
          </p>
          <div class="hero__meta">
            <span class="chip chip--accent">Основной сайт: kursantplus.ru</span>
            <span class="chip <?= $source === 'database' ? 'chip--dark' : '' ?>">
              Источник: <?= $source === 'database' ? 'база данных' : 'резервный лог' ?>
            </span>
          </div>
        </div>
        <aside class="hero__aside">
          <p class="eyebrow">Статус</p>
          <h2><?= $source === 'database' ? 'База подключена' : 'Резервный режим' ?></h2>
          <p>
            <?php if ($source === 'database'): ?>
              Заявки читаются напрямую из MySQL. Это основной рабочий режим.
            <?php else: ?>
              База временно недоступна, поэтому страница показывает резервные записи
              из зеркального server-log.
            <?php endif; ?>
          </p>
          <?php if ($databaseError !== null): ?>
            <p style="margin-top: 0.8rem; color: #8b1e24;">
              Ошибка БД при загрузке заявок. Включён резервный режим отображения.
            </p>
          <?php endif; ?>
          <?php if ($logError !== null): ?>
            <p style="margin-top: 0.8rem; color: #8b1e24;">
              Ошибка лога: <?= escapeHtml($logError) ?>
            </p>
          <?php endif; ?>
        </aside>
      </section>

      <section class="summary">
        <article class="summary-card">
          <strong><?= $summary['total'] ?></strong>
          <span><?= $currentView === 'archive' ? 'заявок в архиве' : 'активных заявок в списке' ?></span>
        </article>
        <article class="summary-card">
          <strong><?= $summary['saved'] ?></strong>
          <span>сохранено в БД</span>
        </article>
        <article class="summary-card">
          <strong><?= $summary['with_email'] ?></strong>
          <span>с указанным email</span>
        </article>
        <article class="summary-card">
          <strong><?= $summary['mail_sent'] ?></strong>
          <span>с отправленным email</span>
        </article>
      </section>

      <section class="toolbar">
        <div class="toolbar__left">
          <nav class="tabs" aria-label="Фильтр заявок">
            <a class="tab <?= $currentView === 'active' ? 'tab--active' : '' ?>" href="/admin/?view=active<?= $searchQuery !== '' ? '&q=' . rawurlencode($searchQuery) : '' ?>">
              <span>Активные</span>
              <span class="tab__count"><?= $counts['active'] ?></span>
            </a>
            <a class="tab <?= $currentView === 'archive' ? 'tab--active' : '' ?>" href="/admin/?view=archive<?= $searchQuery !== '' ? '&q=' . rawurlencode($searchQuery) : '' ?>">
              <span>Архив</span>
              <span class="tab__count"><?= $counts['archived'] ?></span>
            </a>
          </nav>
          <form class="search-form" method="get" action="/admin/">
            <input type="hidden" name="view" value="<?= escapeHtml($currentView) ?>" />
            <div class="search-form__field">
              <input
                class="search-form__input"
                type="search"
                name="q"
                value="<?= escapeHtml($searchQuery) ?>"
                placeholder="Поиск по имени, телефону, email"
              />
              <?php if ($searchQuery !== ''): ?>
                <a
                  class="search-form__clear"
                  href="/admin/?view=<?= escapeHtml($currentView) ?>"
                  aria-label="Сбросить поиск"
                  title="Сбросить поиск"
                >&times;</a>
              <?php endif; ?>
            </div>
            <button class="search-form__button" type="submit">Найти</button>
          </form>
        </div>
        <div class="toolbar__note">
          <?= $currentView === 'archive'
              ? 'Здесь лежат уже обработанные заявки.'
              : 'Новые и рабочие заявки, которые ещё требуют внимания.' ?>
        </div>
      </section>

      <?php if ($notice !== null): ?>
        <section class="notice notice--<?= escapeHtml($notice['type']) ?>">
          <?= escapeHtml($notice['text']) ?>
        </section>
      <?php endif; ?>

      <?php if ($leads === []): ?>
        <section class="empty-state">
          <p>
            <?php if ($searchQuery !== ''): ?>
              <?php if ($currentView === 'active' && $counts['archived'] > 0): ?>
                По этому запросу в активных заявках ничего не найдено. Совпадения есть во вкладке «Архив»: <?= $counts['archived'] ?>.
              <?php elseif ($currentView === 'archive' && $counts['active'] > 0): ?>
                По этому запросу в архиве ничего не найдено. Совпадения есть во вкладке «Активные»: <?= $counts['active'] ?>.
              <?php else: ?>
                По вашему запросу ничего не найдено. Попробуйте изменить формулировку или очистить поиск.
              <?php endif; ?>
            <?php else: ?>
              <?= $currentView === 'archive'
                  ? 'Архив пока пуст. Как только первая заявка будет обработана, она появится здесь.'
                  : 'Пока нет доступных записей для показа. Как только придёт новая заявка, она появится здесь автоматически.' ?>
            <?php endif; ?>
          </p>
        </section>
      <?php else: ?>
        <section class="lead-list">
          <?php foreach ($leads as $lead): ?>
            <article class="lead-card">
              <div class="lead-card__head">
                <div class="lead-card__name">
                  <h3><?= escapeHtml($lead['name']) ?></h3>
                  <div class="lead-card__meta">
                    #<?= escapeHtml($lead['id'] !== '' ? $lead['id'] : '—') ?>
                    · <?= escapeHtml(formatDateTime($lead['received_at'])) ?>
                  </div>
                </div>
                <div class="lead-card__chips">
                  <span class="chip"><?= escapeHtml(renderFlag($lead['saved_to_database'] ?? null, 'В БД', 'Не в БД')) ?></span>
                  <span class="chip"><?= escapeHtml(renderFlag($lead['mail_sent'] ?? null, 'Email ушёл', 'Email не ушёл', 'Email не проверялся')) ?></span>
                  <span class="chip"><?= escapeHtml(renderStatusLabel((string)($lead['status'] ?? 'new'))) ?></span>
                </div>
              </div>
              <div class="lead-card__body">
                <p class="lead-card__topic"><?= escapeHtml($lead['topic']) ?></p>
                <p class="lead-card__message"><?= nl2br(escapeHtml($lead['message'])) ?></p>
                <div class="lead-card__grid">
                  <div class="lead-card__field">
                    <small>Телефон</small>
                    <span><?= escapeHtml($lead['phone']) ?></span>
                  </div>
                  <div class="lead-card__field">
                    <small>Email</small>
                    <?php if (($lead['email'] ?? '') !== ''): ?>
                      <a href="mailto:<?= escapeHtml($lead['email']) ?>"><?= escapeHtml($lead['email']) ?></a>
                    <?php else: ?>
                      <span>Не указан</span>
                    <?php endif; ?>
                  </div>
                  <div class="lead-card__field">
                    <small>Страница</small>
                    <span><?= escapeHtml($lead['source_page'] !== '' ? $lead['source_page'] : 'Не указана') ?></span>
                  </div>
                  <div class="lead-card__field">
                    <small>IP</small>
                    <span><?= escapeHtml($lead['ip_address'] !== '' ? $lead['ip_address'] : 'Не указан') ?></span>
                  </div>
                </div>

                <?php if ($source === 'database' && ctype_digit((string)($lead['id'] ?? ''))): ?>
                  <div class="lead-card__actions">
                    <?php if ($currentView === 'active' && ($lead['status'] ?? '') !== 'archived'): ?>
                      <?php if (($lead['status'] ?? '') !== 'in_progress'): ?>
                        <form method="post" action="/admin/?view=active<?= $searchQuery !== '' ? '&q=' . rawurlencode($searchQuery) : '' ?>">
                          <input type="hidden" name="action" value="progress" />
                          <input type="hidden" name="lead_id" value="<?= escapeHtml((string)$lead['id']) ?>" />
                          <button class="lead-card__button lead-card__button--dark" type="submit">В работу</button>
                        </form>
                      <?php endif; ?>
                      <form method="post" action="/admin/?view=active<?= $searchQuery !== '' ? '&q=' . rawurlencode($searchQuery) : '' ?>">
                        <input type="hidden" name="action" value="archive" />
                        <input type="hidden" name="lead_id" value="<?= escapeHtml((string)$lead['id']) ?>" />
                        <button class="lead-card__button" type="submit">Обработано</button>
                      </form>
                    <?php elseif ($currentView === 'archive'): ?>
                      <form method="post" action="/admin/?view=archive<?= $searchQuery !== '' ? '&q=' . rawurlencode($searchQuery) : '' ?>">
                        <input type="hidden" name="action" value="restore" />
                        <input type="hidden" name="lead_id" value="<?= escapeHtml((string)$lead['id']) ?>" />
                        <button class="lead-card__button lead-card__button--light" type="submit">Вернуть в активные</button>
                      </form>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
              </div>
            </article>
          <?php endforeach; ?>
        </section>
      <?php endif; ?>
    </main>
  </body>
</html>

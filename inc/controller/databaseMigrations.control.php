<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../../config.php';
require_once ABSPATH . '/inc/Databases/dbconn.php';
require_once ABSPATH . '/inc/Classes/DatabaseMigrationManager.php';

$response = static function (array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
};

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        $response(['success' => false, 'message' => 'طريقة الطلب غير مسموح بها.'], 405);
    }

    if ((int)($_SESSION['ADMIN_TYPE'] ?? 0) !== 1) {
        $response(['success' => false, 'message' => 'هذه الشاشة متاحة لحساب Admin فقط.'], 403);
    }

    $sessionToken = (string)($_SESSION['DB_MIGRATIONS_CSRF'] ?? '');
    $requestToken = (string)($_POST['csrf_token'] ?? '');
    if ($sessionToken === '' || $requestToken === '' || !hash_equals($sessionToken, $requestToken)) {
        $response(['success' => false, 'message' => 'انتهت صلاحية الطلب. حدّث الصفحة وحاول مرة أخرى.'], 419);
    }

    $database = new dbconn();
    $db = $database->getConnection(DB_HOST, DB_PORT, DB_USER, DB_PASSWORD, DB_NAME, DB_CHARSET);
    $manager = new DatabaseMigrationManager($db, ABSPATH);
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'list') {
        $response([
            'success' => true,
            'environment' => $manager->getEnvironment(),
            'migrations' => $manager->listMigrations(),
        ]);
    }

    $file = (string)($_POST['file'] ?? '');
    if ($action === 'preview') {
        $response(['success' => true, 'preview' => $manager->preview($file)]);
    }

    if ($action === 'run') {
        $result = $manager->run(
            $file,
            (string)($_POST['confirm_name'] ?? ''),
            (int)($_SESSION['USER_ID'] ?? 0)
        );
        $response(['success' => true] + $result);
    }

    $response(['success' => false, 'message' => 'العملية المطلوبة غير معروفة.'], 400);
} catch (Throwable $e) {
    error_log('[DatabaseMigrations] ' . $e->getMessage());
    $response(['success' => false, 'message' => $e->getMessage()], 400);
}

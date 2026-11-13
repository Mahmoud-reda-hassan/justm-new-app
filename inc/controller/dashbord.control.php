<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

function dashboardResponse($success, $message, $data = [], $status = 200) {
    http_response_code($status);
    echo json_encode([
        'success' => (bool)$success,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    dashboardResponse(false, 'طريقة الطلب غير مسموح بها.', [], 405);
}

if (!isset($_SESSION['USER_ID']) || (int)($_SESSION['ADMIN_TYPE'] ?? 0) !== 1) {
    dashboardResponse(false, 'غير مصرح لك بالوصول إلى لوحة الإدارة.', [], 401);
}

$userScript = @unserialize($_SESSION['USER_SCRIPT'] ?? '', ['allowed_classes' => false]);
$adminPermissions = is_array($userScript) ? ($userScript['Admin'] ?? []) : [];
$dashboardPermissions = $adminPermissions['dashboard'] ?? [];

$allowedRoles = [];
foreach (['Admin', 'CustomerService1', 'CustomerService2', 'Storage', 'Delivery'] as $role) {
    $value = $dashboardPermissions[$role] ?? false;
    if ($value === true || $value === 1 || $value === '1' || $value === 'true') {
        $allowedRoles[] = $role;
    }
}

if (!$allowedRoles) {
    dashboardResponse(false, 'لا تمتلك صلاحية عرض لوحة الإدارة.', [], 403);
}

try {
    require_once(__DIR__ . '/../../config.php');
    require_once(ABSPATH . '/inc/Databases/dbconn.php');
    require_once(ABSPATH . '/inc/controller/BackEnd/dashbord_BE.php');

    $dbConnection = new dbconn();
    $db = $dbConnection->getConnection(DB_HOST, DB_PORT, DB_USER, DB_PASSWORD, DB_NAME, DB_CHARSET);

    $dashboard = new dashbord_BE($db);
    $data = $dashboard->getDashboardData(
        $allowedRoles,
        $adminPermissions,
        (int)$_SESSION['USER_ID'],
        (int)($_SESSION['BRANCH_ID'] ?? 0)
    );

    dashboardResponse(true, 'تم تحميل البيانات.', $data);
} catch (Throwable $e) {
    error_log('Dashboard controller error: ' . $e->getMessage());
    dashboardResponse(false, 'حدث خطأ أثناء تجهيز بيانات لوحة التحكم.', [], 500);
} finally {
    if (isset($db)) {
        $db = null;
    }
}

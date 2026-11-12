<?php
// تحديد الحجم من GET (بالبايت)
$size = isset($_GET['size']) ? intval($_GET['size']) : 102400; // default 100KB

// حد أقصى للحماية (مثلاً 10MB)
$maxSize = 10 * 1024 * 1024;
if ($size > $maxSize) {
    $size = $maxSize;
}

header('Content-Type: application/octet-stream');
header('Content-Length: ' . $size);
header('Cache-Control: no-cache, no-store, must-revalidate');

// إرسال البيانات chunk عشان نحافظ على الميموري
$chunkSize = 1024 * 1024; // 1MB
$sent = 0;
$data = str_repeat("0", $chunkSize);

while ($sent < $size) {
    $remaining = $size - $sent;
    echo ($remaining >= $chunkSize) ? $data : str_repeat("0", $remaining);
    $sent += min($chunkSize, $remaining);
    flush();
}
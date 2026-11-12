<?php
/**
 * LogManager - نظام متكامل لإدارة سجلات النظام
 * 
 * هذا الكلاس مسؤول عن:
 * - إنشاء وكتابة السجلات
 * - تنظيف السجلات القديمة
 * - عرض السجلات بطريقة احترافية
 * - إدارة ملفات السجلات
 */
class LogManager {
    private $baseLogDir;
    private $defaultLogType;
    private $hasErrorsOrWarnings = false;
    private $tempLogs = [];
    /**
     * Constructor
     * 
     * @param string $baseLogDir المسار الأساسي لمجلد السجلات
     * @param string $defaultLogType نوع السجل الافتراضي
     */

    public function __construct($baseLogDir = null, $defaultLogType = 'application') {
        $this->baseLogDir = $baseLogDir ?: __DIR__ . '../../../App_Logs';
        $this->defaultLogType = $defaultLogType;
        $this->ensureDirectory($this->baseLogDir);
    }
    /**
     * التأكد من وجود المجلد
     */
    private function ensureDirectory($dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
     
    /**
     * كتابة سجل جديد
     */
    private function writeToFile($message, $level = 'INFO', $logType = null, $timestamp = null) {
        $logType = $logType ?: $this->defaultLogType;
        $logDir = $this->baseLogDir . '/' . $logType;
        $logFile = $logDir . '/' . $logType . '_' . date('Y-m-d') . '.log';
        
        try {
            $this->ensureDirectory($logDir);
            
            $timestamp = $timestamp ?: date('Y-m-d H:i:s');
            $formattedMessage = "[$timestamp] [$level] $message" . PHP_EOL;
            
            return file_put_contents($logFile, $formattedMessage, FILE_APPEND | LOCK_EX) !== false;
            
        } catch (Exception $e) {
            error_log("LogManager Error: " . $e->getMessage());
            return false;
        }
    }
    /**
     * تخزين اللوج مؤقتا
     */
    public function write($message, $level, $logType = null) {
        $logType = $logType ?: $this->defaultLogType;
        
        // إذا كان خطأ أو تحذير، نرفع العلم
        if (in_array($level, ['ERROR', 'EXCEPTION', 'FATAL', 'WARNING', 'SUCCESS'])) {
            $this->hasErrorsOrWarnings = true;
        }
        
        // نخزن كل اللوجز مؤقتاً
        $this->tempLogs[] = [
            'message' => $message,
            'level' => $level,
            'logType' => $logType,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        return true;
    }
    
    /**
     * إنهاء العملية - الكتابة فقط إذا كان هناك أخطاء
     */
    public function finalize() {
        if ($this->hasErrorsOrWarnings) {
            // كتابة كل اللوجز إلى الملف
            $this->writeToFile('------------', 'START', '', '');
            foreach ($this->tempLogs as $log) {
                $this->writeToFile($log['message'], $log['level'], $log['logType'], $log['timestamp']);
            }
        }
        
        // مسح الذاكرة المؤقتة
        $this->tempLogs = [];
        $this->hasErrorsOrWarnings = false;
    }
    
    /**
     * كتابة سجل معلومات
     */
    public function info($message, $logType = null) {
        return $this->write($message, 'INFO', $logType);
    }
    
    /**
     * كتابة سجل خطأ
     */
    public function error($message, $logType = null) {
        return $this->write($message, 'ERROR', $logType);
    }
    
    /**
     * كتابة سجل تحذير
     */
    public function warning($message, $logType = null) {
        return $this->write($message, 'WARNING', $logType);
    }
    
    /**
     * كتابة سجل خطأ
     */
    public function debug($message, $logType = null) {
        return $this->write($message, 'DEBUG', $logType);
    }
    
    /**
     * كتابة سجل نجاح
     */
    public function success($message, $logType = null) {
        return $this->write($message, 'SUCCESS', $logType);
    }
    
    /**
     * كتابة سجل الاستثناء 
     */
    public function exception($message, $logType = null) {
        return $this->write($message, 'EXCEPTION', $logType);
    }
    
    /**
     * تنظيف السجلات القديمة
     */
    public function cleanup($daysToKeep = 30, $logType = 'all') {
        $result = [
            'success' => true,
            'deleted_count' => 0,
            'deleted_files' => [],
            'errors' => []
        ];
        
        try {
            $logTypes = $logType === 'all' ? $this->getAllLogTypes() : [$logType];
            
            foreach ($logTypes as $type) {
                $logDir = $this->baseLogDir . '/' . $type;
                
                if (!is_dir($logDir)) continue;
                
                $files = glob($logDir . '/*.log');
                $currentTime = time();
                
                foreach ($files as $file) {
                    if (is_file($file) && ($currentTime - filemtime($file)) / 86400 > $daysToKeep) {
                        if (unlink($file)) {
                            $result['deleted_count']++;
                            $result['deleted_files'][] = basename($file);
                        } else {
                            $result['errors'][] = "Failed to delete: " . basename($file);
                        }
                    }
                }
            }
            
            $this->info("تم تنظيف السجلات - تم حذف {$result['deleted_count']} ملف", 'system');
            
        } catch (Exception $e) {
            $result['success'] = false;
            $result['errors'][] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * الحصول على جميع أنواع السجلات
     */
    private function getAllLogTypes() {
        $types = [];
        if (is_dir($this->baseLogDir)) {
            $items = scandir($this->baseLogDir);
            foreach ($items as $item) {
                if ($item !== '.' && $item !== '..' && is_dir($this->baseLogDir . '/' . $item)) {
                    $types[] = $item;
                }
            }
        }
        return $types;
    }
    
    /**
     * عرض السجلات بطريقة HTML
     */
    public function display($logType = null, $date = 'latest', $lines = 1000, $filter = 'all') {
        $logType = $logType ?: $this->defaultLogType;
        
        try {
            if ($date === 'latest') {
                $logDir = $this->baseLogDir . '/' . $logType;
                $files = glob($logDir . '/*.log');
                if (empty($files)) return $this->generateHtml([], $logType, 'No log files');
                rsort($files);
                $logFile = $files[0];
            } else {
                $logFile = $this->baseLogDir . '/' . $logType . '/' . $logType . '_' . $date . '.log';
            }
            
            if (!file_exists($logFile)) {
                return $this->generateHtml([], $logType, 'ملف السجل غير موجود');
            }
            
            $content = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($content === false) {
                return $this->generateHtml([], $logType, 'فشل في قراءة الملف');
            }
            
            $filteredContent = [];
            foreach ($content as $line) {
                if ($this->applyFilter($line, $filter)) {
                    $filteredContent[] = $this->parseLine($line);
                }
            }
            
            if ($lines > 0) {
                $filteredContent = array_slice($filteredContent, -$lines);
            }
            
            return $this->generateHtml($filteredContent, $logType, basename($logFile));
            
        } catch (Exception $e) {
            return $this->generateHtml([], $logType, 'خطأ: ' . $e->getMessage());
        }
    }
    
    /**
     * تطبيق الفلتر
     */
    private function applyFilter($line, $filter) {
        if ($filter === 'all') return true;
        return stripos($line, "[$filter]") !== false;
    }
    
    /**
     * تحليل سطر السجل
     */
    private function parseLine($line) {
        preg_match('/\[(.*?)\] \[(.*?)\] (.*)/', $line, $matches);
        return [
            'timestamp' => $matches[1] ?? '',
            'level' => $matches[2] ?? 'UNKNOWN',
            'message' => $matches[3] ?? $line
        ];
    }
    
    /**
     * توليد HTML
     */
    private function generateHtml($entries, $logType, $filename) {
        ob_start();
        ?>
	    <!DOCTYPE html>
	    <html lang="ar" dir="rtl">
	    <head>
	        <meta charset="UTF-8">
	        <meta name="viewport" content="width=device-width, initial-scale=1.0">
	        <title>عرض اللوج - <?= htmlspecialchars($logType) ?></title>
	        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
	        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
	        <style>
	            .log-container {
	                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
	                font-size: 14px;
	            }
	            .log-entry {
	                border-left: 4px solid transparent;
	                padding: 8px 12px;
	                margin-bottom: 2px;
	                border-radius: 4px;
	            }
	            .log-entry:hover {
	                background-color: #f8f9fa;
	            }
	            .level-error { border-left-color: #dc3545; background-color: #fff5f5; }
	            .level-warning { border-left-color: #ffc107; background-color: #fffbf0; }
	            .level-info { border-left-color: #0dcaf0; background-color: #f0f9ff; }
	            .level-success { border-left-color: #198754; background-color: #f0fff4; }
	            .level-debug { border-left-color: #6c757d; background-color: #f8f9fa; }
	            .timestamp {
	                font-family: 'Courier New', monospace;
	                font-size: 12px;
	                color: #6c757d;
	            }
	            .search-box {
	                position: sticky;
	                top: 0;
	                background: white;
	                z-index: 1000;
	                padding: 15px;
	                border-bottom: 1px solid #dee2e6;
	            }
	        </style>
	    </head>
	    <body>
	        <div class="container-fluid log-container">
	            <div class="search-box">
	                <div class="row align-items-center">
	                    <div class="col-md-6">
	                        <h4>
	                            <i class="fas fa-file-alt"></i>
	                            سجلات النظام - <?= htmlspecialchars(ucfirst($logType)) ?>
	                        </h4>
	                        <small class="text-muted"><?= htmlspecialchars($filename) ?></small>
	                    </div>
	                    <div class="col-md-6">
	                        <div class="input-group">
	                            <input type="text" id="logSearch" class="form-control" placeholder="بحث في السجلات...">
	                            <button class="btn btn-outline-secondary" type="button" onclick="filterLogs()">
	                                <i class="fas fa-search"></i>
	                            </button>
	                        </div>
	                    </div>
	                </div>
	            </div>
	
	            <div class="table-responsive">
	                <table class="table table-hover">
	                    <thead>
	                        <tr>
	                            <th width="150">الوقت</th>
	                            <th width="100">المستوى</th>
	                            <th>الرسالة</th>
	                        </tr>
	                    </thead>
	                    <tbody>
	                        <?php if (empty($logEntries)): ?>
	                            <tr>
	                                <td colspan="3" class="text-center text-muted py-4">
	                                    <i class="fas fa-info-circle"></i>
	                                    لا توجد سجلات لعرضها
	                                </td>
	                            </tr>
	                        <?php else: ?>
	                            <?php foreach ($logEntries as $entry): ?>
	                                <?php
	                                $levelClass = 'level-' . strtolower($entry['level']);
	                                $badgeClass = 'bg-' . ($levelColors[$entry['level']] ?? 'secondary');
	                                ?>
	                                <tr class="log-entry <?= $levelClass ?>" data-level="<?= strtolower($entry['level']) ?>">
	                                    <td class="timestamp"><?= htmlspecialchars($entry['timestamp']) ?></td>
	                                    <td>
	                                        <span class="badge <?= $badgeClass ?>">
	                                            <?= htmlspecialchars($entry['level']) ?>
	                                        </span>
	                                    </td>
	                                    <td>
	                                        <div style="white-space: pre-wrap; font-family: 'Courier New', monospace;">
	                                            <?= htmlspecialchars($entry['message']) ?>
	                                        </div>
	                                    </td>
	                                </tr>
	                            <?php endforeach; ?>
	                        <?php endif; ?>
	                    </tbody>
	                </table>
	            </div>
	        </div>
	
	        <script>
	            function filterLogs() {
	                const searchText = document.getElementById('logSearch').value.toLowerCase();
	                const rows = document.querySelectorAll('tbody tr');
	                
	                rows.forEach(row => {
	                    const message = row.cells[2].textContent.toLowerCase();
	                    const level = row.getAttribute('data-level');
	                    
	                    const matchesSearch = message.includes(searchText) || level.includes(searchText);
	                    row.style.display = matchesSearch ? '' : 'none';
	                });
	            }
	            
	            // البحث أثناء الكتابة
	            document.getElementById('logSearch').addEventListener('input', filterLogs);
	            
	            // أزرار快捷键
	            document.addEventListener('keydown', function(e) {
	                if (e.ctrlKey && e.key === 'f') {
	                    e.preventDefault();
	                    document.getElementById('logSearch').focus();
	                }
	            });
	        </script>
	    </body>
	    </html>
	    <?php
        return ob_get_clean();
    }
    
    /**
     * الحصول على قائمة الملفات
     */
    public function getLogFiles($logType = null) {
        $logType = $logType ?: $this->defaultLogType;
        $logDir = $this->baseLogDir . '/' . $logType;
        $files = [];
        
        if (is_dir($logDir)) {
            $logFiles = glob($logDir . '/*.log');
            foreach ($logFiles as $file) {
                $files[] = [
                    'name' => basename($file),
                    'size' => filesize($file),
                    'modified' => date('Y-m-d H:i:s', filemtime($file)),
                    'path' => $file
                ];
            }
        }
        
        usort($files, function($a, $b) {
            return strtotime($b['modified']) - strtotime($a['modified']);
        });
        
        return $files;
    }
    
    /**
     * تحميل ملف سجل
     */
    public function download($logType, $filename) {
        $filePath = $this->baseLogDir . '/' . $logType . '/' . $filename;
        
        if (file_exists($filePath)) {
            header('Content-Type: text/plain');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            readfile($filePath);
            exit;
        }
        
        return false;
    }
    
    /**
     * API للاستخدام
     */
    public function handleApiRequest($request) {
        $action = $request['action'] ?? 'view';
        $logType = $request['type'] ?? $this->defaultLogType;
        
        switch ($action) {
            case 'cleanup':
                $days = (int) ($request['days'] ?? 30);
                return $this->cleanup($days, $logType);
                
            case 'download':
                $filename = $request['file'] ?? '';
                return $this->download($logType, $filename);
                
            case 'list':
                return $this->getLogFiles($logType);
                
            case 'view':
            default:
                $date = $request['date'] ?? 'latest';
                $lines = (int) ($request['lines'] ?? 1000);
                $filter = $request['filter'] ?? 'all';
                return $this->display($logType, $date, $lines, $filter);
        }
    }
}
/*
 * مثال للاستخدام خارج الكلاس
 * 
if (isset($_GET['log_api'])) {
    $logManager = new LogManager();
    
    @action string = $request['action'] ;
    @logType string = $request['type'] ?? $this->defaultLogType;
        
    echo $logManager->handleApiRequest($_GET);
    exit;
}


كيفية الاستخدام:

1. إنشاء الكائن:

,,,
require_once 'LogManager.php';
**
 *@path مسار حفظ ملف اللوج
 *@name_file نوع اللوج ويأخذ كأسم للملف
**
$logManager = new LogManager('/path/to/logs', 'shipping');
```

2. أمثلة الاستخدام:

,,,
// الكتابة في السجلات
$logManager->info("اضافة معلومه ");
$logManager->warning("اضافة تحذير");
$logManager->error("فشل في تحديث المنتج");
$logManager->success("اضافة نجاح العملية");
$logManager->write("", type, file_log_name);


// تنظيف السجلات (احتفظ بآخر شهرين فقط)
$result = $logManager->cleanup(2, 'shipping');

// عرض السجلات
$html = $logManager->display('shipping', 'latest', 500, 'error');

// الحصول على قائمة الملفات
$files = $logManager->getLogFiles('shipping');
```

3. استخدام API:

,,,
https://example.com/LogManager.php?log_api=true&action=cleanup&type=shipping&months=2
https://example.com/LogManager.php?log_api=true&action=view&type=shipping&filter=error
https://example.com/LogManager.php?log_api=true&action=list&type=all
```

هذا الملف الآن متكامل ويحذف تلقائياً جميع الملفات الأقدم من شهرين!

*/
?>
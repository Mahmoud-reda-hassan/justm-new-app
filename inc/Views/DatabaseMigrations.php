<?php
if ((int)($_SESSION['ADMIN_TYPE'] ?? 0) !== 1) {
    http_response_code(403);
    echo '<div class="alert alert-danger">غير مصرح بعرض هذه الصفحة.</div>';
    return;
}
if (empty($_SESSION['DB_MIGRATIONS_CSRF'])) {
    $_SESSION['DB_MIGRATIONS_CSRF'] = bin2hex(random_bytes(32));
}
$databaseMigrationToken = (string)$_SESSION['DB_MIGRATIONS_CSRF'];
?>
<section class="content" id="databaseMigrationsPage" data-csrf="<?php echo htmlspecialchars($databaseMigrationToken, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="container-fluid">
        <div class="alert alert-warning">
            <strong><i class="fas fa-exclamation-triangle"></i> تنبيه مهم:</strong>
            خذ نسخة احتياطية كاملة من قاعدة البيانات قبل تنفيذ أي ترحيل. تنفيذ بعض أوامر SQL البنيوية لا يمكن التراجع عنه تلقائيًا.
            استخدم الشاشة على قاعدة اختبار منفصلة أولًا.
        </div>

        <div class="card card-outline card-primary">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-database ml-1"></i> حالة قاعدة البيانات</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-sm btn-outline-primary" id="refreshMigrations">
                        <i class="fas fa-sync-alt"></i> تحديث الحالة
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="description-block border-left">
                            <span class="description-text">قاعدة البيانات الحالية</span>
                            <h5 class="description-header" id="migrationDatabaseName">...</h5>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="description-block border-left">
                            <span class="description-text">إصدار MariaDB / MySQL</span>
                            <h5 class="description-header" id="migrationDatabaseVersion">...</h5>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="description-block">
                            <span class="description-text">سجل الترحيلات</span>
                            <h5 class="description-header" id="migrationLedgerStatus">...</h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">ملفات تحديث قاعدة البيانات</h3>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-bordered table-hover text-center" id="migrationsTable">
                    <thead>
                        <tr>
                            <th>المرحلة</th>
                            <th>الوصف</th>
                            <th>الحالة</th>
                            <th>آخر تنفيذ</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody id="migrationsTableBody">
                        <tr><td colspan="5">جارٍ تحميل بيانات الترحيلات...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card card-outline card-secondary" id="migrationPreviewCard" style="display:none">
            <div class="card-header">
                <h3 class="card-title" id="migrationPreviewTitle">مراجعة الترحيل</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" id="closeMigrationPreview" aria-label="إغلاق">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="alert alert-info" id="migrationPreviewWarning"></div>
                <p class="mb-2"><strong>بصمة الملف:</strong> <code id="migrationPreviewChecksum"></code></p>
                <p class="mb-2"><strong>عدد أوامر SQL:</strong> <span id="migrationPreviewStatementCount"></span></p>
                <pre id="migrationPreviewSql" dir="ltr" style="max-height:420px;overflow:auto;text-align:left;white-space:pre-wrap;background:#f7f7f7;padding:12px;border:1px solid #ddd"></pre>
                <div class="alert alert-danger mb-0">
                    التنفيذ يتم على قاعدة البيانات الظاهرة بالأعلى. تأكد من اسم القاعدة قبل الضغط على تنفيذ.
                </div>
            </div>
        </div>
    </div>
</section>

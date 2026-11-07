(function ($) {
    'use strict';

    var $page = $('#databaseMigrationsPage');
    if (!$page.length) return;

    var endpoint = '/inc/controller/databaseMigrations.control.php';
    var csrfToken = String($page.data('csrf') || '');
    var currentDatabase = '';

    function escapeHtml(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function notify(message, isError) {
        if (window.Swal && typeof Swal.fire === 'function') {
            Swal.fire({
                icon: isError ? 'error' : 'success',
                text: message,
                confirmButtonText: 'حسنًا'
            });
        } else {
            window.alert(message);
        }
    }

    function request(action, values) {
        var data = $.extend({
            action: action,
            csrf_token: csrfToken
        }, values || {});

        return $.ajax({
            url: endpoint,
            method: 'POST',
            data: data,
            dataType: 'json',
            cache: false
        });
    }

    function statusLabel(status) {
        var labels = {
            PENDING: '<span class="badge badge-secondary">لم ينفذ</span>',
            RUNNING: '<span class="badge badge-warning">جارٍ التنفيذ</span>',
            APPLIED: '<span class="badge badge-success">تم التنفيذ</span>',
            FAILED: '<span class="badge badge-danger">فشل التنفيذ</span>',
            CHANGED: '<span class="badge badge-danger">الملف تغير بعد التنفيذ</span>',
            MISSING: '<span class="badge badge-dark">الملف غير موجود</span>'
        };
        return labels[status] || '<span class="badge badge-light">' + escapeHtml(status) + '</span>';
    }

    function loadMigrations() {
        $('#migrationsTableBody').html('<tr><td colspan="5">جارٍ تحميل البيانات...</td></tr>');
        request('list')
            .done(function (response) {
                if (!response || !response.success) {
                    notify(response && response.message ? response.message : 'تعذر تحميل الترحيلات.', true);
                    return;
                }

                var env = response.environment || {};
                currentDatabase = env.database || '';
                var executionAllowed = !!env.can_execute;
                $('#migrationDatabaseName').text((currentDatabase || 'غير معروف') + ' — ' + (env.host || ''));
                $('#migrationDatabaseVersion').text(env.version || 'غير معروف');
                $('#migrationLedgerStatus').text(env.ledger_exists ? 'موجود' : 'سيُنشأ عند أول تنفيذ');
                if (!executionAllowed) {
                    $('#migrationLedgerStatus').append(' — التنفيذ مقفول خارج بيئة الاختبار');
                }

                var rows = '';
                (response.migrations || []).forEach(function (item) {
                    var actions = '<button type="button" class="btn btn-sm btn-outline-info migration-preview" data-file="' +
                        escapeHtml(item.file) + '"><i class="fas fa-eye"></i> مراجعة SQL</button> ';

                    if (item.enabled && item.status !== 'APPLIED' && executionAllowed) {
                        actions += '<button type="button" class="btn btn-sm btn-danger migration-run" data-file="' +
                            escapeHtml(item.file) + '"><i class="fas fa-play"></i> تنفيذ</button>';
                    } else if (item.enabled && item.status !== 'APPLIED' && !executionAllowed) {
                        actions += '<span class="text-muted small">المراجعة فقط — التنفيذ غير متاح على هذا النطاق</span>';
                    } else if (!item.enabled && item.status !== 'APPLIED') {
                        actions += '<span class="text-muted small">التنفيذ موقوف للمراجعة</span>';
                    } else {
                        actions += '<span class="text-success small">لا يلزم إعادة التنفيذ</span>';
                    }

                    var details = '<div>' + escapeHtml(item.description || '') + '</div>';
                    if (item.warning) {
                        details += '<small class="text-warning d-block mt-1">' + escapeHtml(item.warning) + '</small>';
                    }
                    if (item.error) {
                        details += '<small class="text-danger d-block mt-1">' + escapeHtml(item.error) + '</small>';
                    }

                    rows += '<tr>' +
                        '<td class="text-left"><strong>' + escapeHtml(item.file) + '</strong><br><small>' +
                        escapeHtml(item.title || '') + '</small></td>' +
                        '<td class="text-right">' + details + '</td>' +
                        '<td>' + statusLabel(item.status) + '</td>' +
                        '<td>' + escapeHtml(item.applied_at || '—') + '</td>' +
                        '<td class="text-nowrap">' + actions + '</td>' +
                        '</tr>';
                });

                $('#migrationsTableBody').html(rows || '<tr><td colspan="5">لا توجد ملفات ترحيل.</td></tr>');
            })
            .fail(function (xhr) {
                var message = xhr.responseJSON && xhr.responseJSON.message
                    ? xhr.responseJSON.message : 'تعذر الاتصال بخدمة الترحيلات.';
                notify(message, true);
            });
    }

    $(document).on('click', '.migration-preview', function () {
        var file = String($(this).data('file') || '');
        request('preview', { file: file })
            .done(function (response) {
                if (!response || !response.success) {
                    notify(response && response.message ? response.message : 'تعذر عرض ملف الترحيل.', true);
                    return;
                }
                var preview = response.preview;
                $('#migrationPreviewTitle').text('مراجعة: ' + preview.title + ' (' + preview.file + ')');
                $('#migrationPreviewWarning').text(preview.warning || '');
                $('#migrationPreviewChecksum').text(preview.checksum || '');
                $('#migrationPreviewStatementCount').text(preview.statement_count || 0);
                $('#migrationPreviewSql').text(preview.sql || '');
                $('#migrationPreviewCard').show();
                $('html, body').animate({ scrollTop: $('#migrationPreviewCard').offset().top - 20 }, 250);
            })
            .fail(function (xhr) {
                notify(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'تعذر عرض ملف الترحيل.', true);
            });
    });

    $(document).on('click', '.migration-run', function () {
        var file = String($(this).data('file') || '');
        var promptText = 'قاعدة البيانات: ' + currentDatabase + '\n\nسيتم تنفيذ أوامر SQL على هذه القاعدة.\n' +
            'تأكد من وجود نسخة احتياطية، ثم اكتب اسم الملف كاملًا للتأكيد:\n' + file;
        var confirmation = window.prompt(promptText, '');
        if (confirmation === null) return;
        if (confirmation !== file) {
            notify('لم يتم التنفيذ؛ نص التأكيد غير مطابق لاسم الملف.', true);
            return;
        }

        var $button = $(this).prop('disabled', true).text('جارٍ التنفيذ...');
        request('run', { file: file, confirm_name: confirmation })
            .done(function (response) {
                if (!response || !response.success) {
                    notify(response && response.message ? response.message : 'فشل تنفيذ الترحيل.', true);
                    return;
                }
                notify(response.message || 'تم تنفيذ الترحيل.');
                $('#migrationPreviewCard').hide();
                loadMigrations();
            })
            .fail(function (xhr) {
                notify(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'حدث خطأ أثناء تنفيذ الترحيل.', true);
            })
            .always(function () {
                $button.prop('disabled', false).html('<i class="fas fa-play"></i> تنفيذ');
            });
    });

    $('#refreshMigrations').on('click', loadMigrations);
    $('#closeMigrationPreview').on('click', function () {
        $('#migrationPreviewCard').hide();
    });

    loadMigrations();
})(jQuery);

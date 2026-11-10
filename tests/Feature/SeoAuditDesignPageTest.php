<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoAuditDesignPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_seo_audit_design_page_is_available_to_authenticated_users(): void
    {
        $user = User::query()->create([
            'name' => 'UI Preview User',
            'username' => 'ui-preview-'.uniqid(),
            'password' => 'secure-password',
            'account_type' => 'admin',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('admin.seo-audit.design'))
            ->assertOk()
            ->assertSee('مراجعة صفحات الموقع')
            ->assertSee('لا توجد صفحات لعرضها')
            ->assertSee('معاينة تصميم فقط');
    }

    public function test_seo_audit_design_page_requires_authentication(): void
    {
        $this->get(route('admin.seo-audit.design'))
            ->assertRedirect(route('login'));
    }
}

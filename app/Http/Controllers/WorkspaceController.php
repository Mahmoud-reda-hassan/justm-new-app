<?php

namespace App\\Http\\Controllers;

use App\\Http\\Controllers\\Controller;
use App\\Models\\Party;
use Illuminate\\Http\\RedirectResponse;
use Illuminate\\Http\\Request;
use Illuminate\\View\\View;

class WorkspaceController extends Controller
{
    private const WORKSPACES = [
        'admin' => ['title' => 'منطقة الإداريين', 'description' => 'إدارة النظام والمستخدمين والإعدادات والعمليات المصرح بها.', 'icon' => 'fas fa-user-shield'],
        'marketer' => ['title' => 'منطقة المسوقين', 'description' => 'متابعة العملاء والطلبات والعمولات الخاصة بالمسوق.', 'icon' => 'fas fa-bullhorn'],
        'intermediary' => ['title' => 'منطقة الوسطاء', 'description' => 'متابعة عمليات البيع والطلبات والعمولات الخاصة بالوسيط.', 'icon' => 'fas fa-handshake'],
        'delivery' => ['title' => 'منطقة المندوبين', 'description' => 'مهام التوصيل والتسليم والتحصيل والعهدة والتسويات.', 'icon' => 'fas fa-truck'],
    ];

    public function index(Request $request): RedirectResponse|View
    {
        $workspaces = $this->availableWorkspaces($request);

        if (count($workspaces) === 0) {
            return redirect()->route('dashboard')->with('status', 'لا يوجد لهذا الحساب دور تشغيلي مخصص حتى الآن.');
        }

        if (count($workspaces) === 1) {
            return redirect()->route('workspaces.enter', ['workspace' => array_key_first($workspaces)]);
        }

        return view('workspaces.index', ['workspaces' => $workspaces]);
    }

    public function enter(Request $request, string $workspace): RedirectResponse|View
    {
        $workspaces = $this->availableWorkspaces($request);

        abort_unless(array_key_exists($workspace, $workspaces), 403);

        if ($workspace === 'admin') {
            return redirect()->route('dashboard');
        }

        return view('workspaces.pending', [
            'workspace' => $workspaces[$workspace],
            'workspaceKey' => $workspace,
        ]);
    }

    public function admin(Request $request): RedirectResponse
    {
        abort_unless($this->hasAdminAccess($request), 403);

        return redirect()->route('dashboard');
    }

    private function availableWorkspaces(Request $request): array
    {
        $user = $request->user();
        $available = [];

        if ($this->hasAdminAccess($request)) {
            $available['admin'] = self::WORKSPACES['admin'];
        }

        $party = $user->party()->where('is_active', true)->first();
        if ($party) {
            $roleMap = [
                'marketer' => 'marketer',
                'sales_intermediary' => 'intermediary',
                'delivery_agent' => 'delivery',
            ];

            $assigned = $party->partyRoles()->whereIn('role', Party::LOGIN_ROLES)->pluck('role')->all();
            foreach ($roleMap as $partyRole => $workspaceKey) {
                if (in_array($partyRole, $assigned, true)) {
                    $available[$workspaceKey] = self::WORKSPACES[$workspaceKey];
                }
            }
        }

        return $available;
    }

    private function hasAdminAccess(Request $request): bool
    {
        $user = $request->user();

        return $user->hasPermission('users.view')
            || $user->hasPermission('permissions.groups.view')
            || $user->hasPermission('structure.scope.manage_all');
    }
}

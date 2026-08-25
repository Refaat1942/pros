<?php

/**
 * تجميعات نظرة عامة الإدارة — ربط كل ويدجت بصلاحيات موجودة (افتراضي: الرفض للحساس).
 *
 * أي_of: يكفي تحقق شرط واحد داخل المجموعة.
 * all_of: يجب تحقق كل الشروط.
 *
 * dashboards  → User::canAccessDashboard()
 * admin_pages → User::canViewDashboardPage('admin', page)
 * desk_pages  → [[dashboard, page], ...]
 * permissions → User::hasPermission() (إجراءات مثل view-costs)
 */
return [
    'bundles' => [
        // ── دورة العمل — بطاقات الطوابير ─────────────────────────────────────
        'cycle_reception' => [
            'kind' => 'cycle_card',
            'cycle_key' => 'reception',
            'any_of' => [
                ['dashboards' => ['reception']],
                ['admin_pages' => ['patient-tracks', 'cases']],
                ['desk_pages' => [['reception', 'appointments'], ['reception', 'statistics']]],
            ],
        ],
        'cycle_doctor' => [
            'kind' => 'cycle_card',
            'cycle_key' => 'doctor',
            'any_of' => [
                ['dashboards' => ['doctor']],
                ['admin_pages' => ['cases', 'patient-tracks']],
                ['desk_pages' => [['doctor', 'queue'], ['doctor', 'records']]],
            ],
        ],
        'cycle_spec' => [
            'kind' => 'cycle_card',
            'cycle_key' => 'spec',
            'any_of' => [
                ['dashboards' => ['spec']],
                ['admin_pages' => ['spec-edit-requests', 'cases']],
                ['desk_pages' => [['spec', 'orders'], ['spec', 'spec']]],
            ],
        ],
        'cycle_adjustments' => [
            'kind' => 'cycle_card',
            'cycle_key' => 'adjustments',
            'any_of' => [
                ['dashboards' => ['adjustments']],
                ['admin_pages' => ['spec-edit-requests', 'cases']],
                ['desk_pages' => [['adjustments', 'adjustments']]],
            ],
        ],
        'cycle_operations' => [
            'kind' => 'cycle_card',
            'cycle_key' => 'operations',
            'any_of' => [
                ['dashboards' => ['operations']],
                ['admin_pages' => ['cases', 'contracts']],
                ['desk_pages' => [['operations', 'pending'], ['operations', 'quotes-awaiting']]],
            ],
        ],
        'cycle_production_assignment' => [
            'kind' => 'cycle_card',
            'cycle_key' => 'production-assignment',
            'any_of' => [
                ['admin_pages' => ['workshop-tracking', 'workshop-sections']],
                ['dashboards' => ['workshop']],
                ['permissions' => ['view-workshop-tracking', 'manage-workshop-sections']],
            ],
        ],
        'cycle_cashier' => [
            'kind' => 'cycle_card',
            'cycle_key' => 'cashier',
            'any_of' => [
                ['dashboards' => ['cashier']],
                ['desk_pages' => [['cashier', 'payments'], ['cashier', 'statistics']]],
                ['permissions' => ['confirm-cash-payment']],
            ],
        ],
        'cycle_workshop' => [
            'kind' => 'cycle_card',
            'cycle_key' => 'workshop',
            'any_of' => [
                ['dashboards' => ['workshop']],
                ['admin_pages' => ['workshop-tracking', 'workshop-sections']],
                ['permissions' => ['view-workshop-tracking', 'manage-workshop-sections']],
            ],
        ],
        'cycle_warehouse' => [
            'kind' => 'cycle_card',
            'cycle_key' => 'inventory',
            'any_of' => [
                ['dashboards' => ['technical']],
                ['admin_pages' => ['dispense-approvals', 'inventory-overview']],
                ['desk_pages' => [['technical', 'bom'], ['technical', 'inventory']]],
                ['permissions' => ['approve-dispense', 'view-inventory-overview']],
            ],
        ],

        // ── شريط متابعة الحالات ───────────────────────────────────────────────
        'case_strip_waiting_return' => [
            'kind' => 'case_strip',
            'strip_key' => 'waiting_return',
            'any_of' => [
                ['dashboards' => ['reception', 'operations']],
                ['admin_pages' => ['cases', 'contracts']],
                ['desk_pages' => [['reception', 'quote'], ['operations', 'pending']]],
            ],
        ],
        'case_strip_awaiting_cashier' => [
            'kind' => 'case_strip',
            'strip_key' => 'awaiting_cashier',
            'inherits' => 'cycle_cashier',
        ],
        'case_strip_awaiting_assignment' => [
            'kind' => 'case_strip',
            'strip_key' => 'awaiting_assignment',
            'inherits' => 'cycle_production_assignment',
        ],
        'case_strip_in_progress' => [
            'kind' => 'case_strip',
            'strip_key' => 'in_progress',
            'any_of' => [
                ['dashboards' => ['workshop', 'operations']],
                ['admin_pages' => ['workshop-tracking', 'cases']],
                ['permissions' => ['view-workshop-tracking']],
            ],
        ],
        'case_strip_delivered' => [
            'kind' => 'case_strip',
            'strip_key' => 'delivered',
            'any_of' => [
                ['admin_pages' => ['patient-tracks', 'cases']],
                ['desk_pages' => [['reception', 'delivery']]],
            ],
        ],

        // ── لوحات BI ──────────────────────────────────────────────────────────
        'bi_board1_patients' => [
            'kind' => 'bi_board',
            'board_key' => 'board1',
            'any_of' => [
                ['admin_pages' => ['patient-tracks', 'cases']],
            ],
        ],
        'bi_board2_inventory' => [
            'kind' => 'bi_board',
            'board_key' => 'board2',
            'all_of' => [
                ['permissions' => ['view-costs']],
                [
                    'any_of' => [
                        ['admin_pages' => ['inventory-overview', 'catalog']],
                        ['permissions' => ['view-inventory-overview', 'manage-inventory']],
                    ],
                ],
            ],
        ],
        'bi_board3_operations' => [
            'kind' => 'bi_board',
            'board_key' => 'board3',
            'any_of' => [
                ['dashboards' => ['operations', 'workshop']],
                ['admin_pages' => ['workshop-tracking', 'dispense-approvals', 'cases']],
                ['permissions' => ['view-workshop-tracking', 'approve-dispense']],
            ],
        ],
        'bi_board5_purchasing' => [
            'kind' => 'bi_board',
            'board_key' => 'board5',
            'all_of' => [
                ['permissions' => ['view-costs']],
                [
                    'any_of' => [
                        ['admin_pages' => ['catalog', 'suppliers', 'inventory-overview']],
                        ['permissions' => ['manage-inventory', 'view-inventory-overview']],
                    ],
                ],
            ],
        ],

        // ── لوحة الجهات والتكاليف — أقسام مستقلة (board4) ─────────────────────
        'finance_cash' => [
            'kind' => 'finance_section',
            'section_key' => 'cash',
            'any_of' => [
                ['dashboards' => ['cashier']],
                ['desk_pages' => [['cashier', 'payments'], ['cashier', 'statistics']]],
                ['permissions' => ['confirm-cash-payment']],
            ],
        ],
        'finance_civilian_debt' => [
            'kind' => 'finance_section',
            'section_key' => 'civilian_debt',
            'any_of' => [
                ['admin_pages' => ['civilian-debts']],
                ['permissions' => ['collect-civilian-debt']],
            ],
        ],
        'finance_revenue_cost' => [
            'kind' => 'finance_section',
            'section_key' => 'revenue_cost',
            'any_of' => [
                ['permissions' => ['view-revenue', 'view-costs']],
            ],
        ],
        'finance_military' => [
            'kind' => 'finance_section',
            'section_key' => 'military',
            'any_of' => [
                ['admin_pages' => ['military-debts']],
                ['permissions' => ['collect-military-debt', 'view-military-profit']],
            ],
        ],
        'finance_contracts_companies' => [
            'kind' => 'finance_section',
            'section_key' => 'contracts_companies',
            'any_of' => [
                ['admin_pages' => ['contracts', 'companies']],
            ],
        ],

        // ── تصدير — مؤشرات إضافية ─────────────────────────────────────────────
        'export_finance_revenue_kpis' => [
            'kind' => 'export',
            'inherits' => 'finance_revenue_cost',
        ],
        'export_finance_civilian_debt_kpis' => [
            'kind' => 'export',
            'inherits' => 'finance_civilian_debt',
        ],
        'export_finance_cash_kpis' => [
            'kind' => 'export',
            'inherits' => 'finance_cash',
        ],
        'export_finance_military_kpis' => [
            'kind' => 'export',
            'inherits' => 'finance_military',
        ],
        'export_inventory_kpis' => [
            'kind' => 'export',
            'inherits' => 'bi_board2_inventory',
        ],
        'export_operations_kpis' => [
            'kind' => 'export',
            'inherits' => 'bi_board3_operations',
        ],
        'export_bom_detail' => [
            'kind' => 'export',
            'any_of' => [
                ['admin_pages' => ['dispense-approvals', 'workshop-tracking', 'cases']],
                ['permissions' => ['view-costs', 'approve-dispense', 'view-workshop-tracking']],
            ],
        ],
    ],

    'cycle_total_active' => [
        'any_bundle_kinds' => ['cycle_card', 'case_strip', 'bi_board', 'finance_section'],
    ],
];

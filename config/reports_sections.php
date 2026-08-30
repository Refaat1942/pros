<?php

/**
 * تفويض أقسام مركز التقارير — ربط كل section بصلاحيات موجودة (افتراضي: الرفض).
 *
 * any_of / all_of / admin_pages / desk_pages / permissions / dashboards
 * — نفس منطق overview_widgets.
 */
return [
    'sections' => [
        'cash-income' => [
            'any_of' => [
                ['dashboards' => ['cashier']],
                ['desk_pages' => [['cashier', 'payments'], ['cashier', 'statistics']]],
                ['permissions' => ['confirm-cash-payment']],
            ],
        ],
        'financial' => [
            'any_of' => [
                ['permissions' => ['view-revenue', 'view-costs']],
            ],
        ],
        'inventory' => [
            'any_of' => [
                ['admin_pages' => ['catalog', 'inventory-overview']],
                ['permissions' => ['view-inventory-overview', 'manage-inventory']],
            ],
        ],
        'inventory-valuation' => [
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
        'item-margins' => [
            'inherits' => 'inventory-valuation',
        ],
        'price-tier-balances' => [
            'inherits' => 'inventory-valuation',
        ],
        'multi-price-items' => [
            'inherits' => 'inventory-valuation',
        ],
        'inventory-reconciliation' => [
            'all_of' => [
                ['permissions' => ['view-costs']],
                ['permissions' => ['view-revenue']],
            ],
        ],
        'opening-balance' => [
            'any_of' => [
                ['permissions' => ['view-costs']],
            ],
        ],
        'closing-balance' => [
            'inherits' => 'opening-balance',
        ],
        'profitability' => [
            'inherits' => 'opening-balance',
        ],
        'civilian-debts' => [
            'any_of' => [
                ['admin_pages' => ['civilian-debts']],
                ['permissions' => ['collect-civilian-debt']],
            ],
        ],
        'contracts' => [
            'any_of' => [
                ['admin_pages' => ['contracts']],
            ],
        ],
        'companies' => [
            'any_of' => [
                ['admin_pages' => ['companies']],
            ],
        ],
        'patient-tracks' => [
            'any_of' => [
                ['admin_pages' => ['patient-tracks']],
            ],
        ],
        'cases' => [
            'any_of' => [
                ['admin_pages' => ['cases']],
            ],
        ],
        'operations' => [
            'any_of' => [
                ['dashboards' => ['operations', 'workshop']],
                ['admin_pages' => ['workshop-tracking', 'dispense-approvals', 'cases']],
                ['permissions' => ['view-workshop-tracking', 'approve-dispense']],
            ],
        ],
        'bom' => [
            'all_of' => [
                ['permissions' => ['view-costs']],
                [
                    'any_of' => [
                        ['admin_pages' => ['dispense-approvals', 'workshop-tracking', 'cases']],
                        ['permissions' => ['approve-dispense', 'view-workshop-tracking']],
                        ['desk_pages' => [['technical', 'bom']]],
                    ],
                ],
            ],
        ],
        'catalog' => [
            'any_of' => [
                ['admin_pages' => ['catalog']],
                ['permissions' => ['manage-inventory']],
            ],
        ],
        'inventory-overview' => [
            'any_of' => [
                ['admin_pages' => ['inventory-overview']],
                ['permissions' => ['view-inventory-overview']],
            ],
        ],
        'audit' => [
            'any_of' => [
                ['admin_pages' => ['audit']],
            ],
        ],
        'services-approvals' => [
            'any_of' => [
                ['admin_pages' => ['services-approvals']],
                ['permissions' => ['approve-services']],
            ],
        ],
        'authorizations' => [
            'any_of' => [
                ['admin_pages' => ['dispense-approvals']],
                ['permissions' => ['approve-dispense']],
                ['admin_pages' => ['spec-edit-requests']],
                ['admin_pages' => ['services-approvals']],
                ['permissions' => ['approve-services']],
                ['admin_pages' => ['contracts']],
            ],
        ],
        'dispense-approvals' => [
            'any_of' => [
                ['admin_pages' => ['dispense-approvals']],
                ['permissions' => ['approve-dispense']],
            ],
        ],
        'returns' => [
            'any_of' => [
                ['admin_pages' => ['returns']],
            ],
        ],
        'production-assignment' => [
            'any_of' => [
                ['admin_pages' => ['workshop-tracking', 'workshop-sections']],
                ['dashboards' => ['workshop']],
                ['permissions' => ['view-workshop-tracking', 'manage-workshop-sections']],
            ],
        ],
        'workshop-tracking' => [
            'any_of' => [
                ['admin_pages' => ['workshop-tracking']],
                ['permissions' => ['view-workshop-tracking']],
            ],
        ],
        'workshop-sections' => [
            'any_of' => [
                ['admin_pages' => ['workshop-sections']],
                ['permissions' => ['manage-workshop-sections']],
            ],
        ],
        'spec-edit-requests' => [
            'any_of' => [
                ['admin_pages' => ['spec-edit-requests']],
            ],
        ],
        'visit-types' => [
            'any_of' => [
                ['admin_pages' => ['visit-types']],
            ],
        ],
        'stock-categories' => [
            'any_of' => [
                ['admin_pages' => ['stock-categories']],
            ],
        ],
        'suppliers' => [
            'any_of' => [
                ['admin_pages' => ['suppliers']],
            ],
        ],
        'stock-kits' => [
            'any_of' => [
                ['admin_pages' => ['stock-kits']],
                ['permissions' => ['manage-stock-kits']],
            ],
        ],
        'catalog-list-settings' => [
            'any_of' => [
                ['admin_pages' => ['catalog-list-settings']],
            ],
        ],
    ],
];

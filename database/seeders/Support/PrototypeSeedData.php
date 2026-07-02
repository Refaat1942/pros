<?php

namespace Database\Seeders\Support;

use Carbon\Carbon;

/**
 * بيانات DEFAULT المنسوخة حرفياً من assets/js/shared/*.js
 */
class PrototypeSeedData
{
  /** @var array<string, string> */
  private const STORE_CLASS_MAP = [
    'مفاصل' => 'قطع خام',
    'أقدام' => 'قطع خام',
    'محولات' => 'قطع خام',
    'بطانات' => 'مواد مساعدة',
    'إكسسوارات' => 'أدوات مساعدة',
  ];

  public static function parseDate(?string $value): ?Carbon
  {
    if (! $value) {
      return null;
    }
    $parts = preg_split('/[\s\/]+/', trim($value));
    if (count($parts) < 3) {
      return null;
    }

    return Carbon::createFromDate((int) $parts[2], (int) $parts[1], (int) $parts[0])->startOfDay();
  }

  public static function parseDateTime(?string $value): ?Carbon
  {
    if (! $value) {
      return null;
    }
    $parts = preg_split('/\s+/', trim($value), 2);
    $date = self::parseDate($parts[0]);
    if (! $date) {
      return null;
    }
    if (isset($parts[1]) && preg_match('/^(\d{1,2}):(\d{2})$/', $parts[1], $m)) {
      $date->setTime((int) $m[1], (int) $m[2]);
    }

    return $date;
  }

  public static function deriveBarcode(string $code): string
  {
    $digits = preg_replace('/\D/', '', $code);

    return 'BC-'.$digits;
  }

  public static function deriveStoreClass(?string $category): string
  {
    return self::STORE_CLASS_MAP[$category] ?? 'مواد خام';
  }

  public static function derivePatientType(array $row): string
  {
    if (($row['patientType'] ?? null) === 'military' || ($row['patientType'] ?? null) === 'civilian') {
      return $row['patientType'];
    }
    $company = (string) ($row['company'] ?? '');
    if (preg_match('/قوات|مسلح|عسكر|الدفاع الجوي|الحرس|سياد/u', $company)) {
      return 'military';
    }

    return 'civilian';
  }

  public static function derivePatientId(string $caseNo, string $patientType): string
  {
    if (preg_match('/(\d+)/', $caseNo, $m)) {
      $seq = str_pad(substr($m[1], -4), 4, '0', STR_PAD_LEFT);
    } else {
      $seq = '0000';
    }
    $prefix = $patientType === 'military' ? 'MIL' : 'CIV';

    return 'PT-'.$prefix.'-'.$seq;
  }

  /** جهات التعاقد — اتحاد أسماء DEFAULT_DEBTS + cases + pricing-queue */
  public static function contractCompanies(): array
  {
    $names = [];

    foreach (self::contractDebts() as $debt) {
      $names[$debt['company']] = true;
    }
    foreach (self::cases() as $case) {
      $names[$case['company']] = true;
    }
    foreach (self::pricingRequests() as $pr) {
      $names[$pr['company']] = true;
    }

    $list = [];
    $i = 1;
    foreach (array_keys($names) as $name) {
      $list[] = array_merge(
        self::contractCompanyProfile($name),
        [
          'company_code' => 'CO-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
          'name'         => $name,
        ],
      );
      $i++;
    }

    return $list;
  }

  /**
   * @return array{is_military: bool, is_contracted: bool, discount_percent: float}
   */
  public static function contractCompanyProfile(string $name): array
  {
    $isMilitary = (bool) preg_match('/قوات|مسلح|عسكر|سياد/u', $name);

    if ($isMilitary) {
      return [
        'is_military'      => true,
        'is_contracted'    => false,
        'discount_percent' => 0,
      ];
    }

    $discounts = [
      'التأمين الصحي'           => 10,
      'هيئة التأمين الصحي'      => 10,
      'التأمين الوطني'          => 15,
      'شركة التأمين الوطني'     => 15,
      'مصر للتأمين'             => 10,
      'شركة مصر للتأمين'        => 10,
      'صندوق ذوي الإعاقة'       => 20,
      'صندوق رعاية ذوي الإعاقة' => 20,
      'مجلس الدفاع المدني'      => 0,
      'وزارة الداخلية — التأمين'=> 5,
    ];

    return [
      'is_military'      => false,
      'is_contracted'    => true,
      'discount_percent' => (float) ($discounts[$name] ?? 5),
    ];
  }

  /** credit-notes.js DEFAULT_DEBTS */
  public static function contractDebts(): array
  {
    return [
      ['company' => 'شركة التأمين الوطني', 'due' => 485000, 'collected' => 485000, 'status' => 'paid'],
      ['company' => 'هيئة التأمين الصحي', 'due' => 270000, 'collected' => 450000, 'status' => 'partial'],
      ['company' => 'التأمين الصحي', 'due' => 270000, 'collected' => 450000, 'status' => 'partial'],
      ['company' => 'مجلس الدفاع المدني', 'due' => 156000, 'collected' => 156000, 'status' => 'paid'],
      ['company' => 'شركة مصر للتأمين', 'due' => 890000, 'collected' => 890000, 'status' => 'paid'],
      ['company' => 'صندوق رعاية ذوي الإعاقة', 'due' => 340000, 'collected' => 340000, 'status' => 'paid'],
      ['company' => 'وزارة الداخلية — التأمين', 'due' => 275000, 'collected' => 275000, 'status' => 'paid'],
    ];
  }

  /** stock-catalog.js DEFAULT */
  public static function stockItems(): array
  {
    return [
      [
        'code' => 'ITM-001', 'name' => 'ركبة هيدروليكية', 'spec' => 'Medium — Ottobock',
        'qty' => 10, 'reserved' => 0, 'category' => 'مفاصل', 'status' => 'ok', 'lastMoved' => null,
        'attributes' => [
          'joint_type'     => 'knee',
          'mechanism'      => 'hydraulic',
          'activity_level' => 'K3',
          'side'           => 'universal',
          'uom'            => 'قطعة',
        ],
        'prices' => [
          ['id' => 'PR-001-1', 'label' => 'دفعة محلية', 'supplier' => 'النيل للتوريدات', 'supplierType' => 'محلي', 'itemCode' => 'ITM-001-01', 'amount' => 1000],
        ],
      ],
      [
        'code' => 'ITM-002', 'name' => 'ركبة Polycentric', 'spec' => 'Large',
        'qty' => 3, 'reserved' => 1, 'category' => 'مفاصل', 'status' => 'low', 'lastMoved' => null,
        'attributes' => [
          'joint_type'     => 'knee',
          'mechanism'      => 'polycentric',
          'activity_level' => 'K2',
          'side'           => 'right',
          'uom'            => 'قطعة',
        ],
        'prices' => [
          ['id' => 'PR-002-1', 'label' => 'Össur', 'supplier' => 'Össur Middle East', 'supplierType' => 'مستورد', 'itemCode' => 'ITM-002-01', 'amount' => 72000],
          ['id' => 'PR-002-2', 'label' => 'Proteor', 'supplier' => 'Proteor France', 'supplierType' => 'OEM', 'itemCode' => 'ITM-002-02', 'amount' => 68000],
        ],
      ],
      [
        'code' => 'ITM-003', 'name' => 'قدم Carbon Spring', 'spec' => '8 طبقات',
        'qty' => 12, 'reserved' => 3, 'category' => 'أقدام', 'status' => 'ok', 'lastMoved' => null,
        'attributes' => [
          'foot_type'          => 'carbon_spring',
          'size_class'         => 'M',
          'max_patient_weight' => 90,
          'uom'                => 'قطعة',
        ],
        'prices' => [
          ['id' => 'PR-003-1', 'label' => 'Blatchford', 'supplier' => 'Blatchford Group', 'supplierType' => 'مستورد', 'itemCode' => 'ITM-003-01', 'amount' => 55000],
        ],
      ],
      [
        'code' => 'ITM-004', 'name' => 'بطانة Silicone', 'spec' => 'Medium',
        'qty' => 24, 'reserved' => 0, 'category' => 'بطانات', 'status' => 'ok', 'lastMoved' => null,
        'attributes' => [
          'liner_type' => 'silicone',
          'size'       => 'M',
          'uom'        => 'قطعة',
        ],
        'prices' => [
          ['id' => 'PR-004-1', 'label' => 'محلي', 'supplier' => 'الإسكندرية الطبية', 'supplierType' => 'محلي', 'itemCode' => 'ITM-004-01', 'amount' => 8500],
          ['id' => 'PR-004-2', 'label' => 'Ottobock', 'supplier' => 'Ottobock Egypt', 'supplierType' => 'OEM', 'itemCode' => 'ITM-004-02', 'amount' => 12000],
        ],
      ],
      [
        'code' => 'ITM-005', 'name' => 'محول Pyramidal', 'spec' => 'Standard',
        'qty' => 18, 'reserved' => 2, 'category' => 'محولات', 'status' => 'ok', 'lastMoved' => null,
        'attributes' => [
          'adapter_type'    => 'pyramidal',
          'connector_size'  => '30mm',
          'uom'             => 'قطعة',
        ],
        'prices' => [
          ['id' => 'PR-005-1', 'label' => 'Standard', 'supplier' => 'Ottobock Egypt', 'supplierType' => 'موزّع', 'itemCode' => 'ITM-005-01', 'amount' => 15000],
        ],
      ],
      [
        'code' => 'ITM-006', 'name' => 'Pin Lock', 'spec' => '30mm',
        'qty' => 2, 'reserved' => 1, 'category' => 'إكسسوارات', 'status' => 'low', 'lastMoved' => null,
        'attributes' => [
          'accessory_type' => 'pin_lock',
          'uom'            => 'قطعة',
        ],
        'prices' => [
          ['id' => 'PR-006-1', 'label' => 'محلي', 'supplier' => 'النيل للتوريدات', 'supplierType' => 'محلي', 'itemCode' => 'ITM-006-01', 'amount' => 3200],
          ['id' => 'PR-006-2', 'label' => 'Ottobock', 'supplier' => 'Ottobock Egypt', 'supplierType' => 'OEM', 'itemCode' => 'ITM-006-02', 'amount' => 5800],
        ],
      ],
      [
        'code' => 'ITM-007', 'name' => 'غطاء تجميلي', 'spec' => 'Wide',
        'qty' => 12, 'reserved' => 0, 'category' => 'إكسسوارات', 'status' => 'ok', 'lastMoved' => '20/10/2025',
        'attributes' => [
          'accessory_type' => 'cosmetic_cover',
          'uom'            => 'قطعة',
        ],
        'prices' => [
          ['id' => 'PR-007-1', 'label' => 'Wide Cover', 'supplier' => 'Proteor France', 'supplierType' => 'مستورد', 'itemCode' => 'ITM-007-01', 'amount' => 18000],
        ],
      ],
      [
        'code' => 'ITM-008', 'name' => 'بطانة Gel', 'spec' => 'Medium',
        'qty' => 8, 'reserved' => 1, 'category' => 'بطانات', 'status' => 'ok', 'lastMoved' => null,
        'attributes' => [
          'liner_type' => 'gel',
          'size'       => 'M',
          'uom'        => 'قطعة',
        ],
        'prices' => [
          ['id' => 'PR-008-1', 'label' => 'Gel Liner', 'supplier' => 'Össur Middle East', 'supplierType' => 'مستورد', 'itemCode' => 'ITM-008-01', 'amount' => 9500],
        ],
      ],
      [
        'code' => 'ITM-009', 'name' => 'جوارب تجويف', 'spec' => '3 أزواج',
        'qty' => 56, 'reserved' => 0, 'category' => 'بطانات', 'status' => 'ok', 'lastMoved' => '02/09/2025',
        'attributes' => [
          'liner_type' => 'stump_sock',
          'size'       => 'M',
          'uom'        => 'طقم',
        ],
        'prices' => [
          ['id' => 'PR-009-1', 'label' => 'محلي', 'supplier' => 'الإسكندرية الطبية', 'supplierType' => 'محلي', 'itemCode' => 'ITM-009-01', 'amount' => 450],
        ],
      ],
      [
        'code' => 'ITM-010', 'name' => 'مفصل كوع', 'spec' => 'Small — Mechanical',
        'qty' => 10, 'reserved' => 0, 'category' => 'مفاصل', 'status' => 'ok', 'lastMoved' => null,
        'attributes' => [
          'joint_type'     => 'elbow',
          'mechanism'      => 'mechanical',
          'activity_level' => 'K2',
          'side'           => 'universal',
          'uom'            => 'قطعة',
        ],
        'prices' => [
          ['id' => 'PR-010-1', 'label' => 'Mechanical', 'supplier' => 'Ottobock Egypt', 'supplierType' => 'OEM', 'itemCode' => 'ITM-010-01', 'amount' => 1000],
        ],
      ],
    ];
  }

  public static function highestUnitPrice(string $code): float
  {
    foreach (self::stockItems() as $item) {
      if ($item['code'] !== $code) {
        continue;
      }
      $amounts = array_column($item['prices'], 'amount');

      return (float) max($amounts ?: [0]);
    }

    return 0;
  }

  /** cases-workflow.js DEFAULT */
  public static function cases(): array
  {
    return [
      ['id' => 'CASE-2026-001', 'orderRef' => 'ORD-2026-0847', 'patient' => 'محمود عبد الرحمن', 'company' => 'التأمين الوطني', 'stageKey' => 'admin_approval', 'quoteId' => null, 'quoteDate' => null, 'quoteTotal' => 162000, 'approvalDate' => null, 'approvalConfirmedAt' => null, 'manufacturingStage' => null, 'totalCost' => 162000, 'paid' => 0, 'deliveredAt' => null, 'createdAt' => '08/06/2026', 'pricingQueueId' => 'QT-PENDING-001', 'path' => 'standard'],
      ['id' => 'CASE-2026-002', 'orderRef' => 'ORD-2026-0845', 'patient' => 'فاطمة حسين محمد', 'company' => 'التأمين الصحي', 'stageKey' => 'admin_approval', 'quoteId' => null, 'quoteDate' => null, 'quoteTotal' => 77800, 'approvalDate' => null, 'approvalConfirmedAt' => null, 'manufacturingStage' => null, 'totalCost' => 77800, 'paid' => 0, 'deliveredAt' => null, 'createdAt' => '07/06/2026', 'pricingQueueId' => 'QT-PENDING-002', 'path' => 'standard'],
      ['id' => 'CASE-2026-003', 'orderRef' => 'ORD-2026-0839', 'patient' => 'مريم خالد إبراهيم', 'company' => 'مصر للتأمين', 'stageKey' => 'waiting_return', 'quoteId' => 'QT-2026-0839', 'quoteDate' => '28/05/2026', 'quoteTotal' => 55450, 'approvalDate' => null, 'approvalConfirmedAt' => null, 'manufacturingStage' => null, 'totalCost' => 55450, 'paid' => 0, 'deliveredAt' => null, 'createdAt' => '25/05/2026', 'pricingQueueId' => 'QT-PENDING-003', 'path' => 'standard'],
      ['id' => 'CASE-2026-004', 'orderRef' => 'ORD-2026-0821', 'patient' => 'يوسف عمر محسن', 'company' => 'إدارة القوات المسلحة الطبية', 'patientType' => 'military', 'rank' => 'نقيب', 'sovereignEntity' => 'القوات المسلحة', 'stageKey' => 'manufacturing', 'quoteId' => 'QT-2026-0821', 'quoteDate' => '20/05/2026', 'quoteTotal' => 95000, 'approvalDate' => '21/05/2026', 'approvalConfirmedAt' => '21/05/2026 08:30', 'manufacturingStage' => 'assembly', 'workOrderNo' => 'WO-2026-0821', 'totalCost' => 95000, 'paid' => 0, 'deliveredAt' => null, 'createdAt' => '18/05/2026', 'pricingQueueId' => null, 'path' => 'military', 'recommendations' => [['name' => 'ركبة هيدروليكية', 'code' => 'ITM-001', 'qty' => 1], ['name' => 'محول Pyramidal', 'code' => 'ITM-005', 'qty' => 1]]],
      ['id' => 'CASE-2026-005', 'orderRef' => 'ORD-2026-0810', 'patient' => 'سارة أحمد فؤاد', 'company' => 'التأمين الوطني', 'stageKey' => 'manufacturing', 'quoteId' => 'QT-2026-0810', 'quoteDate' => '15/05/2026', 'quoteTotal' => 110500, 'approvalDate' => '22/05/2026', 'approvalConfirmedAt' => '22/05/2026 11:20', 'manufacturingStage' => 'workshop', 'totalCost' => 110500, 'paid' => 50000, 'deliveredAt' => null, 'createdAt' => '10/05/2026', 'pricingQueueId' => null, 'path' => 'standard', 'recommendations' => [['name' => 'ركبة هيدروليكية', 'code' => 'ITM-001', 'qty' => 1], ['name' => 'قدم Carbon Spring', 'code' => 'ITM-003', 'qty' => 1], ['name' => 'بطانة Silicone', 'code' => 'ITM-004', 'qty' => 1]]],
      ['id' => 'CASE-2026-006', 'orderRef' => 'ORD-2026-0798', 'patient' => 'هدى محمود سعيد', 'company' => 'صندوق ذوي الإعاقة', 'stageKey' => 'manufacturing', 'quoteId' => 'QT-2026-0798', 'quoteDate' => '01/05/2026', 'quoteTotal' => 88500, 'approvalDate' => '08/05/2026', 'approvalConfirmedAt' => '08/05/2026 09:45', 'manufacturingStage' => 'warehouse', 'totalCost' => 88500, 'paid' => 0, 'deliveredAt' => null, 'createdAt' => '28/04/2026', 'pricingQueueId' => null, 'path' => 'standard', 'recommendations' => [['name' => 'ركبة Polycentric', 'code' => 'ITM-002', 'qty' => 1], ['name' => 'Pin Lock', 'code' => 'ITM-006', 'qty' => 1]]],
      ['id' => 'CASE-2026-009', 'orderRef' => 'ORD-2026-0785', 'patient' => 'ليلى حسام الدين', 'company' => 'التأمين الوطني', 'stageKey' => 'manufacturing', 'quoteId' => 'QT-2026-0785', 'quoteDate' => '28/05/2026', 'quoteTotal' => 10400, 'approvalDate' => '05/06/2026', 'approvalConfirmedAt' => '05/06/2026 10:00', 'manufacturingStage' => 'warehouse', 'totalCost' => 10400, 'paid' => 0, 'deliveredAt' => null, 'createdAt' => '25/05/2026', 'pricingQueueId' => null, 'path' => 'standard', 'recommendations' => [['name' => 'بطانة Gel', 'code' => 'ITM-008', 'qty' => 1], ['name' => 'جوارب تجويف', 'code' => 'ITM-009', 'qty' => 2]]],
      ['id' => 'CASE-2026-010', 'orderRef' => 'ORD-2026-0772', 'patient' => 'عبدالله سامي رشاد', 'company' => 'صندوق ذوي الإعاقة', 'stageKey' => 'manufacturing', 'quoteId' => 'QT-2026-0772', 'quoteDate' => '20/05/2026', 'quoteTotal' => 53000, 'approvalDate' => '01/06/2026', 'approvalConfirmedAt' => '01/06/2026 14:30', 'manufacturingStage' => 'fitting', 'totalCost' => 53000, 'paid' => 20000, 'deliveredAt' => null, 'createdAt' => '15/05/2026', 'pricingQueueId' => null, 'path' => 'standard', 'recommendations' => [['name' => 'مفصل كوع', 'code' => 'ITM-010', 'qty' => 1], ['name' => 'محول Pyramidal', 'code' => 'ITM-005', 'qty' => 1]]],
      ['id' => 'CASE-2026-007', 'orderRef' => 'ORD-2026-0755', 'patient' => 'كريم محمد علي', 'company' => 'التأمين الصحي', 'stageKey' => 'delivered', 'quoteId' => 'QT-2026-0755', 'quoteDate' => '10/04/2026', 'quoteTotal' => 72000, 'approvalDate' => '18/04/2026', 'approvalConfirmedAt' => '18/04/2026 14:00', 'manufacturingStage' => 'quality', 'totalCost' => 72000, 'paid' => 45000, 'deliveredAt' => '02/05/2026', 'createdAt' => '05/04/2026', 'pricingQueueId' => null, 'path' => 'standard'],
      ['id' => 'CASE-2026-008', 'orderRef' => 'ORD-2026-0742', 'patient' => 'أحمد فاروق نبيل', 'company' => 'مصر للتأمين', 'stageKey' => 'delivered', 'quoteId' => 'QT-2026-0742', 'quoteDate' => '20/03/2026', 'quoteTotal' => 98500, 'approvalDate' => '28/03/2026', 'approvalConfirmedAt' => '28/03/2026 10:30', 'manufacturingStage' => 'quality', 'totalCost' => 98500, 'paid' => 98500, 'deliveredAt' => '15/04/2026', 'createdAt' => '15/03/2026', 'pricingQueueId' => null, 'path' => 'standard'],
      ['id' => 'CASE-2026-011', 'orderRef' => 'ORD-2026-0855', 'patient' => 'منى إبراهيم حسن', 'company' => 'التأمين الوطني', 'stageKey' => 'manufacturing', 'quoteId' => 'QT-2026-0855', 'quoteDate' => '15/05/2026', 'quoteTotal' => 89800, 'approvalDate' => '20/05/2026', 'approvalConfirmedAt' => '20/05/2026 09:30', 'manufacturingStage' => 'quality', 'totalCost' => 89800, 'paid' => 40000, 'deliveredAt' => null, 'createdAt' => '10/05/2026', 'pricingQueueId' => null, 'path' => 'standard', 'recommendations' => [['name' => 'ركبة Polycentric', 'code' => 'ITM-002', 'qty' => 1], ['name' => 'بطانة Silicone', 'code' => 'ITM-004', 'qty' => 1], ['name' => 'Pin Lock', 'code' => 'ITM-006', 'qty' => 1]]],
    ];
  }

  /** pricing-queue.js DEFAULT */
  public static function pricingRequests(): array
  {
    return [
      ['id' => 'QT-PENDING-001', 'orderRef' => 'ORD-2026-0847', 'patient' => 'محمود عبد الرحمن', 'company' => 'التأمين الوطني', 'date' => '08/06/2026', 'items' => 3, 'doctor' => 'د. سارة عبدالله', 'recommendations' => [['name' => 'ركبة هيدروليكية', 'code' => 'ITM-001', 'qty' => 1], ['name' => 'قدم Carbon Spring', 'code' => 'ITM-003', 'qty' => 1], ['name' => 'بطانة Silicone', 'code' => 'ITM-004', 'qty' => 1]], 'patientType' => 'civilian', 'statusKey' => 'awaiting_admin_approval', 'statusLabel' => 'بانتظار الاعتماد', 'step' => 2, 'approvedAt' => null, 'approvedBy' => null],
      ['id' => 'QT-PENDING-002', 'orderRef' => 'ORD-2026-0845', 'patient' => 'فاطمة حسين محمد', 'company' => 'التأمين الصحي', 'date' => '07/06/2026', 'items' => 2, 'doctor' => 'د. سارة عبدالله', 'recommendations' => [['name' => 'ركبة Polycentric', 'code' => 'ITM-002', 'qty' => 1], ['name' => 'Pin Lock', 'code' => 'ITM-006', 'qty' => 1]], 'patientType' => 'civilian', 'statusKey' => 'awaiting_admin_approval', 'statusLabel' => 'بانتظار الاعتماد', 'step' => 2, 'approvedAt' => null, 'approvedBy' => null],
      ['id' => 'QT-PENDING-003', 'orderRef' => 'ORD-2026-0839', 'patient' => 'مريم خالد إبراهيم', 'company' => 'مصر للتأمين', 'date' => '07/06/2026', 'items' => 2, 'doctor' => 'د. سارة عبدالله', 'recommendations' => [['name' => 'قدم Carbon Spring', 'code' => 'ITM-003', 'qty' => 1], ['name' => 'جوارب تجويف', 'code' => 'ITM-009', 'qty' => 1]], 'patientType' => 'civilian', 'statusKey' => 'sent_to_reception', 'statusLabel' => 'تم الإرسال للاستقبال', 'step' => 3, 'approvedAt' => '07/06/2026 14:30', 'approvedBy' => 'أحمد محمود'],
    ];
  }

  /** bom-inventory.js DEFAULT */
  public static function boms(): array
  {
    return [
      ['id' => 'BOM-001', 'caseId' => 'CASE-2026-006', 'orderRef' => 'ORD-2026-0798', 'patient' => 'هدى محمود سعيد', 'quoteId' => 'QT-2026-0798', 'stage' => 'raw', 'items' => [['code' => 'ITM-002', 'name' => 'ركبة Polycentric', 'qty' => 1, 'unitCost' => 72000], ['code' => 'ITM-006', 'name' => 'Pin Lock', 'qty' => 1, 'unitCost' => 5800]], 'createdAt' => '08/05/2026', 'releasedAt' => null, 'finishedAt' => null],
      ['id' => 'BOM-005', 'caseId' => 'CASE-2026-009', 'orderRef' => 'ORD-2026-0785', 'patient' => 'ليلى حسام الدين', 'quoteId' => 'QT-2026-0785', 'stage' => 'raw', 'items' => [['code' => 'ITM-008', 'name' => 'بطانة Gel', 'qty' => 1, 'unitCost' => 9500], ['code' => 'ITM-009', 'name' => 'جوارب تجويف', 'qty' => 2, 'unitCost' => 450]], 'createdAt' => '05/06/2026', 'releasedAt' => null, 'finishedAt' => null],
      ['id' => 'BOM-002', 'caseId' => 'CASE-2026-005', 'orderRef' => 'ORD-2026-0810', 'patient' => 'سارة أحمد فؤاد', 'quoteId' => 'QT-2026-0810', 'stage' => 'wip', 'items' => [['code' => 'ITM-001', 'name' => 'ركبة هيدروليكية', 'qty' => 1, 'unitCost' => 95000, 'issuedQty' => 1, 'returnedQty' => 0], ['code' => 'ITM-003', 'name' => 'قدم Carbon Spring', 'qty' => 1, 'unitCost' => 55000, 'issuedQty' => 1, 'returnedQty' => 0], ['code' => 'ITM-004', 'name' => 'بطانة Silicone', 'qty' => 1, 'unitCost' => 12000, 'issuedQty' => 1, 'returnedQty' => 0]], 'createdAt' => '22/05/2026', 'releasedAt' => '22/05/2026 11:20', 'finishedAt' => null],
      ['id' => 'BOM-006', 'caseId' => 'CASE-2026-010', 'orderRef' => 'ORD-2026-0772', 'patient' => 'عبدالله سامي رشاد', 'quoteId' => 'QT-2026-0772', 'stage' => 'wip', 'items' => [['code' => 'ITM-010', 'name' => 'مفصل كوع', 'qty' => 1, 'unitCost' => 38000, 'issuedQty' => 1, 'returnedQty' => 0], ['code' => 'ITM-005', 'name' => 'محول Pyramidal', 'qty' => 1, 'unitCost' => 15000, 'issuedQty' => 1, 'returnedQty' => 0]], 'createdAt' => '01/06/2026', 'releasedAt' => '03/06/2026 09:15', 'finishedAt' => null],
      ['id' => 'BOM-003', 'caseId' => 'CASE-2026-007', 'orderRef' => 'ORD-2026-0755', 'patient' => 'كريم محمد علي', 'quoteId' => 'QT-2026-0755', 'stage' => 'finished', 'items' => [['code' => 'ITM-002', 'name' => 'ركبة Polycentric', 'qty' => 1, 'unitCost' => 72000], ['code' => 'ITM-003', 'name' => 'قدم Carbon Spring', 'qty' => 1, 'unitCost' => 55000]], 'createdAt' => '18/04/2026', 'releasedAt' => '18/04/2026 14:00', 'finishedAt' => '02/05/2026'],
      ['id' => 'BOM-004', 'caseId' => 'CASE-2026-008', 'orderRef' => 'ORD-2026-0742', 'patient' => 'أحمد فاروق نبيل', 'quoteId' => 'QT-2026-0742', 'stage' => 'finished', 'items' => [['code' => 'ITM-001', 'name' => 'ركبة هيدروليكية', 'qty' => 1, 'unitCost' => 95000], ['code' => 'ITM-005', 'name' => 'محول Pyramidal', 'qty' => 1, 'unitCost' => 15000], ['code' => 'ITM-004', 'name' => 'بطانة Silicone', 'qty' => 1, 'unitCost' => 12000]], 'createdAt' => '28/03/2026', 'releasedAt' => '28/03/2026 10:30', 'finishedAt' => '15/04/2026'],
      ['id' => 'BOM-007', 'caseId' => 'CASE-2026-011', 'orderRef' => 'ORD-2026-0855', 'patient' => 'منى إبراهيم حسن', 'quoteId' => 'QT-2026-0855', 'stage' => 'finished', 'items' => [['code' => 'ITM-002', 'name' => 'ركبة Polycentric', 'qty' => 1, 'unitCost' => 72000], ['code' => 'ITM-004', 'name' => 'بطانة Silicone', 'qty' => 1, 'unitCost' => 12000], ['code' => 'ITM-006', 'name' => 'Pin Lock', 'qty' => 1, 'unitCost' => 5800]], 'createdAt' => '20/05/2026', 'releasedAt' => '25/05/2026 10:00', 'finishedAt' => '07/06/2026'],
      ['id' => 'BOM-008', 'caseId' => 'CASE-2026-004', 'orderRef' => 'ORD-2026-0821', 'patient' => 'يوسف عمر محسن', 'quoteId' => 'QT-2026-0821', 'stage' => 'wip', 'items' => [['code' => 'ITM-001', 'name' => 'ركبة هيدروليكية', 'qty' => 1, 'unitCost' => 95000], ['code' => 'ITM-005', 'name' => 'محول Pyramidal', 'qty' => 1, 'unitCost' => 15000]], 'createdAt' => '21/05/2026', 'releasedAt' => '21/05/2026 09:00', 'finishedAt' => null],
    ];
  }

  /** inventory-returns.js DEFAULT */
  public static function returnNotes(): array
  {
    return [
      [
        'id' => 'RTN-001',
        'bomId' => 'BOM-006',
        'caseId' => 'CASE-2026-010',
        'orderRef' => 'ORD-2026-0772',
        'patient' => 'عبدالله سامي رشاد',
        'workOrderNo' => 'WO-2026-0288',
        'status' => 'authorized',
        'lines' => [['code' => 'ITM-005', 'name' => 'محول Pyramidal', 'qtyRequested' => 1, 'qtyReturned' => 0, 'reason' => 'فائض عن الحاجة في الورشة']],
        'createdAt' => '06/06/2026 10:00',
        'authorizedAt' => '06/06/2026 10:15',
        'completedAt' => null,
        'createdBy' => 'محمد فتحي',
        'auditTrail' => [],
      ],
    ];
  }

  /** credit-notes.js DEFAULT_NOTES */
  public static function creditNotes(): array
  {
    return [
      [
        'id' => 'CN-001',
        'caseId' => 'CASE-2026-007',
        'orderRef' => 'ORD-2026-0755',
        'patient' => 'كريم محمد علي',
        'company' => 'التأمين الصحي',
        'type' => 'partial',
        'amount' => 15000,
        'originalTotal' => 72000,
        'reason' => 'رفض جزئي — بطانة غير مطابقة للمواصفات',
        'status' => 'pending',
        'createdAt' => '08/06/2026 09:00',
        'approvedAt' => null,
        'approvedBy' => null,
      ],
    ];
  }

  /** توصيات الحالة من pricing-queue إن وُجدت */
  public static function recommendationsForCase(string $caseNo, string $orderRef): array
  {
    foreach (self::cases() as $case) {
      if ($case['id'] === $caseNo && ! empty($case['recommendations'])) {
        return $case['recommendations'];
      }
    }
    foreach (self::pricingRequests() as $pr) {
      if ($pr['orderRef'] === $orderRef) {
        return $pr['recommendations'];
      }
    }
    foreach (self::boms() as $bom) {
      if ($bom['caseId'] === $caseNo) {
        return array_map(fn ($it) => ['name' => $it['name'], 'code' => $it['code'], 'qty' => $it['qty']], $bom['items']);
      }
    }

    return [];
  }

  /** أسماء الموردين الفريدة من دفعات أسعار المخزون — stock-catalog.js */
  public static function supplierNamesFromStock(): array
  {
    $names = [];

    foreach (self::stockItems() as $item) {
      foreach ($item['prices'] as $price) {
        $names[$price['supplier']] = true;
      }
    }

    return array_keys($names);
  }

  /** موردون إضافيون من admin-dashboard.js غير موجودين في stock-catalog */
  public static function extraSupplierNames(): array
  {
    return [
      'شركة المستقبل الطبي',
      'Fillauer LLC',
      'شركة النيل للتوريدات',
      'شركة الإسكندرية الطبية',
    ];
  }
}

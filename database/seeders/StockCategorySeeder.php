<?php

namespace Database\Seeders;

use App\Models\StockCategory;
use App\Services\StockCategorySchemaService;
use Illuminate\Database\Seeder;

/**
 * أقسام كatalog الأصناف — متوافقة مع مسارات BOM والمخزن في مركز الأطراف الصناعية.
 *
 * كل قسم له حقول ديناميكية (field_key) تُستخدم عند إضافة/تعديل الصنf وفي استيراد CSV.
 * حقل uom يربط وحدة القياس بجدول stock_items.
 */
class StockCategorySeeder extends Seeder
{
    public function run(): void
    {
        $schema = app(StockCategorySchemaService::class);

        $sections = [
            'مفاصل' => [
                [
                    'label'     => 'نوع المفصل',
                    'type'      => 'list',
                    'field_key' => 'joint_type',
                    'required'  => true,
                    'options'   => [
                        ['value' => 'knee', 'label' => 'ركبة'],
                        ['value' => 'elbow', 'label' => 'كوع'],
                        ['value' => 'hip', 'label' => 'ورك'],
                        ['value' => 'shoulder', 'label' => 'كتف'],
                    ],
                ],
                [
                    'label'     => 'آلية التشغيل',
                    'type'      => 'list',
                    'field_key' => 'mechanism',
                    'required'  => true,
                    'options'   => [
                        ['value' => 'hydraulic', 'label' => 'هيدروليكي'],
                        ['value' => 'mechanical', 'label' => 'ميكانيكي'],
                        ['value' => 'polycentric', 'label' => 'Polycentric'],
                        ['value' => 'microprocessor', 'label' => 'ذكي (Microprocessor)'],
                    ],
                ],
                [
                    'label'     => 'مستوى النشاط',
                    'type'      => 'list',
                    'field_key' => 'activity_level',
                    'required'  => false,
                    'options'   => [
                        ['value' => 'K1', 'label' => 'K1 — حركة محدودة'],
                        ['value' => 'K2', 'label' => 'K2 — مشي يومي'],
                        ['value' => 'K3', 'label' => 'K3 — نشاط متوسط'],
                        ['value' => 'K4', 'label' => 'K4 — رياضي / عالي'],
                    ],
                ],
                [
                    'label'     => 'جانب التركيب',
                    'type'      => 'radio',
                    'field_key' => 'side',
                    'required'  => false,
                    'options'   => [
                        ['value' => 'right', 'label' => 'يمين'],
                        ['value' => 'left', 'label' => 'يسار'],
                        ['value' => 'universal', 'label' => 'للجنسين'],
                    ],
                ],
                [
                    'label'     => 'وحدة القياس',
                    'type'      => 'list',
                    'field_key' => 'uom',
                    'required'  => true,
                    'options'   => [
                        ['value' => 'قطعة', 'label' => 'قطعة'],
                    ],
                ],
            ],

            'أقدام' => [
                [
                    'label'     => 'نوع القدم',
                    'type'      => 'list',
                    'field_key' => 'foot_type',
                    'required'  => true,
                    'options'   => [
                        ['value' => 'carbon_spring', 'label' => 'Carbon Spring'],
                        ['value' => 'sach', 'label' => 'SACH'],
                        ['value' => 'dynamic', 'label' => 'Dynamic Response'],
                        ['value' => 'multi_axial', 'label' => 'Multi-Axial'],
                        ['value' => 'single_axis', 'label' => 'Single Axis'],
                    ],
                ],
                [
                    'label'     => 'فئة المقاس',
                    'type'      => 'list',
                    'field_key' => 'size_class',
                    'required'  => true,
                    'options'   => [
                        ['value' => 'S', 'label' => 'صغير (S)'],
                        ['value' => 'M', 'label' => 'متوسط (M)'],
                        ['value' => 'L', 'label' => 'كبير (L)'],
                        ['value' => 'XL', 'label' => 'كبير جداً (XL)'],
                    ],
                ],
                [
                    'label'     => 'الوزن الأقصى للمريض (كجم)',
                    'type'      => 'number',
                    'field_key' => 'max_patient_weight',
                    'required'  => false,
                    'config'    => ['min' => 30, 'max' => 150, 'step' => 1],
                ],
                [
                    'label'     => 'وحدة القياس',
                    'type'      => 'list',
                    'field_key' => 'uom',
                    'required'  => true,
                    'options'   => [
                        ['value' => 'قطعة', 'label' => 'قطعة'],
                    ],
                ],
            ],

            'بطانات' => [
                [
                    'label'     => 'نوع البطانة',
                    'type'      => 'list',
                    'field_key' => 'liner_type',
                    'required'  => true,
                    'options'   => [
                        ['value' => 'silicone', 'label' => 'Silicone'],
                        ['value' => 'gel', 'label' => 'Gel'],
                        ['value' => 'polyurethane', 'label' => 'Polyurethane'],
                        ['value' => 'stump_sock', 'label' => 'جوارب تجويف'],
                    ],
                ],
                [
                    'label'     => 'المقاس',
                    'type'      => 'list',
                    'field_key' => 'size',
                    'required'  => true,
                    'options'   => [
                        ['value' => 'XS', 'label' => 'XS'],
                        ['value' => 'S', 'label' => 'S'],
                        ['value' => 'M', 'label' => 'M'],
                        ['value' => 'L', 'label' => 'L'],
                        ['value' => 'XL', 'label' => 'XL'],
                    ],
                ],
                [
                    'label'     => 'وحدة القياس',
                    'type'      => 'list',
                    'field_key' => 'uom',
                    'required'  => true,
                    'options'   => [
                        ['value' => 'قطعة', 'label' => 'قطعة'],
                        ['value' => 'طقم', 'label' => 'طقم'],
                    ],
                ],
            ],

            'محولات' => [
                [
                    'label'     => 'نوع المحول',
                    'type'      => 'list',
                    'field_key' => 'adapter_type',
                    'required'  => true,
                    'options'   => [
                        ['value' => 'pyramidal', 'label' => 'Pyramidal'],
                        ['value' => 'rotator', 'label' => 'Rotator'],
                        ['value' => 'tube', 'label' => 'Tube Adapter'],
                        ['value' => 'side_connector', 'label' => 'Side Connector'],
                        ['value' => 'clamp', 'label' => 'Clamp'],
                    ],
                ],
                [
                    'label'     => 'مقاس التوصيل',
                    'type'      => 'text',
                    'field_key' => 'connector_size',
                    'required'  => false,
                    'config'    => ['placeholder' => 'مثال: 30mm / M12', 'max_length' => 32],
                ],
                [
                    'label'     => 'وحدة القياس',
                    'type'      => 'list',
                    'field_key' => 'uom',
                    'required'  => true,
                    'options'   => [
                        ['value' => 'قطعة', 'label' => 'قطعة'],
                    ],
                ],
            ],

            'إكسسوارات' => [
                [
                    'label'     => 'نوع الإكسسوار',
                    'type'      => 'list',
                    'field_key' => 'accessory_type',
                    'required'  => true,
                    'options'   => [
                        ['value' => 'pin_lock', 'label' => 'Pin Lock'],
                        ['value' => 'vacuum', 'label' => 'Vacuum Valve'],
                        ['value' => 'strap', 'label' => 'حزام تثبيت'],
                        ['value' => 'cosmetic_cover', 'label' => 'غطاء تجميلي'],
                        ['value' => 'suspension', 'label' => 'سوار تعليق'],
                    ],
                ],
                [
                    'label'     => 'وحدة القياس',
                    'type'      => 'list',
                    'field_key' => 'uom',
                    'required'  => true,
                    'options'   => [
                        ['value' => 'قطعة', 'label' => 'قطعة'],
                        ['value' => 'طقم', 'label' => 'طقم'],
                    ],
                ],
            ],

            'أقمشة ومواد خام' => [
                [
                    'label'     => 'نوع المادة',
                    'type'      => 'list',
                    'field_key' => 'material_type',
                    'required'  => true,
                    'options'   => [
                        ['value' => 'fiberglass', 'label' => 'Fiberglass'],
                        ['value' => 'carbon_fiber', 'label' => 'Carbon Fiber'],
                        ['value' => 'epoxy', 'label' => 'Epoxy Resin'],
                        ['value' => 'foam', 'label' => 'Foam Block'],
                        ['value' => 'perlon', 'label' => 'Perlon / Nylon'],
                        ['value' => 'fabric', 'label' => 'قماش Lamination'],
                    ],
                ],
                [
                    'label'     => 'وحدة القياس',
                    'type'      => 'list',
                    'field_key' => 'uom',
                    'required'  => true,
                    'options'   => [
                        ['value' => 'متر', 'label' => 'متر'],
                        ['value' => 'كيلو', 'label' => 'كيلو'],
                        ['value' => 'لفة', 'label' => 'لفة'],
                    ],
                ],
                [
                    'label'     => 'اللون / التدرج',
                    'type'      => 'color',
                    'field_key' => 'color',
                    'required'  => false,
                    'config'    => ['default' => '#334155'],
                ],
            ],

            'مسامير وربط' => [
                [
                    'label'     => 'نوع المسمار',
                    'type'      => 'list',
                    'field_key' => 'fastener_type',
                    'required'  => true,
                    'options'   => [
                        ['value' => 'hex_bolt', 'label' => 'مسمار سداسي'],
                        ['value' => 'socket', 'label' => 'Allen / Socket'],
                        ['value' => 'set_screw', 'label' => 'Set Screw'],
                        ['value' => 'rivet', 'label' => 'برشام'],
                        ['value' => 'nut_bolt_kit', 'label' => 'طقم صامولة ومسمار'],
                    ],
                ],
                [
                    'label'     => 'المقاس',
                    'type'      => 'text',
                    'field_key' => 'thread_size',
                    'required'  => false,
                    'config'    => ['placeholder' => 'مثال: M6 × 20mm', 'max_length' => 24],
                ],
                [
                    'label'     => 'المادة',
                    'type'      => 'list',
                    'field_key' => 'material',
                    'required'  => false,
                    'options'   => [
                        ['value' => 'stainless', 'label' => 'Stainless Steel'],
                        ['value' => 'titanium', 'label' => 'Titanium'],
                        ['value' => 'steel', 'label' => 'Steel'],
                    ],
                ],
                [
                    'label'     => 'وحدة القياس',
                    'type'      => 'list',
                    'field_key' => 'uom',
                    'required'  => true,
                    'options'   => [
                        ['value' => 'قطعة', 'label' => 'قطعة'],
                        ['value' => 'طقم', 'label' => 'طقم'],
                    ],
                ],
            ],
        ];

        foreach ($sections as $name => $fields) {
            $category = StockCategory::query()->firstOrCreate(['name' => $name]);
            $schema->syncFields($category, $fields);
        }
    }
}

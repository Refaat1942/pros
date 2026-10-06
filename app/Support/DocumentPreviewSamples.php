<?php

namespace App\Support;

use App\Models\BomItem;
use App\Models\CaseRecord;
use App\Models\Patient;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\TechOrderSpec;
use App\Models\TechOrderSpecItem;

/** بيانات تجريبية لمعاينة الوثائق الأصلية (نفس قوالب الطباعة في النظام). */
final class DocumentPreviewSamples
{
    public static function quote(): Quote
    {
        $case = new CaseRecord([
            'case_no' => 'CASE-DEMO',
            'order_ref' => 'REF-DEMO',
            'patient_type' => Patient::TYPE_CIVILIAN,
            'stage_key' => CaseRecord::STAGE_QUOTE,
        ]);
        $case->id = 0;
        $case->setRelation('patient', new Patient([
            'name' => 'مريض تجريبي — معاينة القالب',
            'national_id' => '28001010100000',
        ]));
        $case->setRelation('techOrderSpec', new TechOrderSpec([
            'written_items' => "السوكيت والاينر: سوكيت خارجي كربون فيبر - سوكيت داخلي جيل لاينر\n"
                ."القدم: قدم ديناميك موشن\n"
                .'الوصلات: عدد 2 وصلة ماسورة',
        ]));
        $case->setRelation('medicalRecords', collect());
        $case->setRelation('bom', null);

        $quote = new Quote([
            'quote_no' => 'QT-DEMO-001',
            'order_ref' => 'REF-DEMO',
            'patient_name' => 'مريض تجريبي — معاينة القالب',
            'company_name' => 'جهة تعاقد تجريبية',
            'quote_date' => now(),
            'total' => 15000,
            'status' => Quote::STATUS_ISSUED,
        ]);
        $quote->id = 0;
        $quote->setRelation('caseRecord', $case);

        $items = collect([
            new QuoteItem([
                'name' => 'طرف صناعي ركبة — مواصفات تجريبية',
                'source' => BomItem::SOURCE_SPEC,
                'qty' => 1,
            ]),
            new QuoteItem([
                'name' => 'صيانة وتعديل',
                'source' => BomItem::SOURCE_SPEC,
                'qty' => 1,
            ]),
        ]);
        $quote->setRelation('items', $items);

        return $quote;
    }

    public static function techOrderSpec(): TechOrderSpec
    {
        $case = new CaseRecord([
            'case_no' => 'CASE-DEMO',
            'order_ref' => 'REF-DEMO',
            'stage_key' => CaseRecord::STAGE_TECHNICAL,
        ]);
        $case->id = 0;

        $spec = new TechOrderSpec([
            'order_ref' => 'REF-DEMO',
            'patient_name' => 'مريض تجريبي — معاينة',
            'company_name' => 'جهة تجريبية',
            'doctor_name' => 'د. تجريبي',
            'tech_notes' => 'ملاحظة فنية للمعاينة.',
            'written_items' => 'بند مكتوب تجريبي للعرض.',
            'submitted_at' => now(),
            'locked' => true,
        ]);
        $spec->id = 0;
        $spec->setRelation('caseRecord', $case);

        $items = collect([
            new TechOrderSpecItem([
                'stock_item_code' => 'RM-001',
                'name' => 'صنف تجريبي 1',
                'qty' => 2,
            ]),
            new TechOrderSpecItem([
                'stock_item_code' => 'RM-002',
                'name' => 'صنف تجريبي 2',
                'qty' => 1,
            ]),
        ]);
        $spec->setRelation('items', $items);

        return $spec;
    }
}

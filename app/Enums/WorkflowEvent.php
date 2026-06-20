<?php

namespace App\Enums;

/**
 * أحداث محرك التدفق — مصدر الحقيقة الوحيد لانتقالات stage_key.
 */
enum WorkflowEvent: string
{
    case ExamApproved              = 'exam_approved';
    case SpecSaved                 = 'spec_saved';
    case PricingCompletedCivilian  = 'pricing_completed_civilian';
    case PricingCompletedMilitary  = 'pricing_completed_military';
    case ApprovalScanned           = 'approval_scanned';
    case BomDispensed              = 'bom_dispensed';
    case BomFinished               = 'bom_finished';
    case Delivered                 = 'delivered';
}

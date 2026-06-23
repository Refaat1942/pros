<?php

namespace App\Enums;

/**
 * أحداث محرك التدفق — مصدر الحقيقة الوحيد لانتقالات stage_key.
 *
 * التسلسل الصارم للمسار:
 *   reception → [exam] → technical → adjustments → cost_calc → quote
 *             → operations → manufacturing → ready_delivery → delivered
 */
enum WorkflowEvent: string
{
    case ExamApproved          = 'exam_approved';          // exam → technical
    case ExamSkipped           = 'exam_skipped';           // reception → technical (الكشف اختياري)
    case SpecSaved             = 'spec_saved';             // technical → adjustments
    case AdjustmentsCompleted  = 'adjustments_completed';  // adjustments → cost_calc
    case CostingCompleted      = 'costing_completed';      // cost_calc → quote
    case QuoteIssued           = 'quote_issued';           // quote → operations
    case OperationsApproved    = 'operations_approved';    // operations → manufacturing (warehouse)
    case ReturnedToAdjustments = 'returned_to_adjustments';// operations → adjustments
    case ReturnedToTechnical   = 'returned_to_technical';  // operations/adjustments → technical
    case BomDispensed          = 'bom_dispensed';          // manufacturing (warehouse → issue)
    case BomFinished           = 'bom_finished';           // manufacturing → ready_delivery
    case Delivered             = 'delivered';              // ready_delivery → delivered
}

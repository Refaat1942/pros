<?php

namespace App\Http\Controllers\Concerns;

use App\Models\CaseRecord;
use App\Services\DocumentTemplateService;
use App\Support\DocumentPrintContext;

trait PreparesDocumentPrint
{
    /** @return array<string, mixed> */
    protected function documentTemplateForPrint(string $documentKey, ?CaseRecord $case = null): array
    {
        $ctx = DocumentPrintContext::fromRequest(request(), $case);

        return app(DocumentTemplateService::class)->for(
            $documentKey,
            $ctx->department,
            $ctx->stage,
        );
    }
}

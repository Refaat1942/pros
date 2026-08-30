<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCustomDocumentRequest;
use App\Http\Requests\Admin\UploadCustomDocumentReferenceRequest;
use App\Models\CustomDocument;
use App\Services\CustomDocumentService;
use Illuminate\Http\JsonResponse;

class CustomDocumentController extends Controller
{
    public function __construct(private readonly CustomDocumentService $customDocuments) {}

    public function store(StoreCustomDocumentRequest $request): JsonResponse
    {
        $doc = $this->customDocuments->create(
            $request->validated(),
            $request->user(),
            $request->file('reference'),
        );

        return response()->json([
            'message' => 'تم إنشاء الوثيقة — يمكنك تخصيصها الآن.',
            'document' => [
                'key' => $doc->key,
                'title' => $doc->title,
                'edit_url' => route('admin.documents-hub.edit', $doc->key),
            ],
        ], 201);
    }

    public function uploadReference(
        UploadCustomDocumentReferenceRequest $request,
        CustomDocument $customDocument,
    ): JsonResponse {
        $doc = $this->customDocuments->storeReference($customDocument, $request->file('reference'));

        return response()->json([
            'message' => 'تم رفع النموذج المرجعي.',
            'reference_url' => $doc->referenceUrl(),
        ]);
    }

    public function destroy(CustomDocument $customDocument): JsonResponse
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $this->customDocuments->delete($customDocument);

        return response()->json(['message' => 'تم حذف الوثيقة المخصصة.']);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WorkshopSection;
use App\Services\WorkshopSectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkshopSectionController extends Controller
{
    public function __construct(
        private readonly WorkshopSectionService $sections,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->sections->listForAdmin(),
            'technicians' => $this->sections->workshopTechnicians(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'code' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:500'],
            'active' => ['nullable', 'boolean'],
            'technician_ids' => ['nullable', 'array'],
            'technician_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $section = $this->sections->create(
            $validated,
            $validated['technician_ids'] ?? [],
        );

        return response()->json([
            'message' => 'تم إضافة قسم الورشة.',
            'section' => $this->sections->listForAdmin()[0] ?? $section,
        ], 201);
    }

    public function update(Request $request, WorkshopSection $workshopSection): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'min:2', 'max:100'],
            'code' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:500'],
            'active' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'integer', 'min:0'],
            'technician_ids' => ['nullable', 'array'],
            'technician_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $technicianIds = array_key_exists('technician_ids', $validated)
            ? ($validated['technician_ids'] ?? [])
            : null;

        unset($validated['technician_ids']);

        $section = $this->sections->update($workshopSection, $validated, $technicianIds);

        return response()->json([
            'message' => 'تم تحديث قسم الورشة.',
            'section' => $section->only(['id', 'name', 'code', 'sort', 'active', 'description']),
        ]);
    }

    public function destroy(WorkshopSection $workshopSection): JsonResponse
    {
        $this->sections->delete($workshopSection);

        return response()->json(['message' => 'تم حذف قسم الورشة.']);
    }

    /** قائمة مختصرة لاستخدامها في مكتب التشغيل. */
    public function options(): JsonResponse
    {
        return response()->json([
            'sections' => collect($this->sections->listActive())->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'code' => $s->code,
                'technicians' => $s->technicians->map(fn ($u) => $u->only(['id', 'name']))->values(),
            ])->values(),
        ]);
    }
}

<?php

namespace App\Services;

use App\Models\StockItem;
use App\Models\Supplier;
use App\Support\ExportCsvFormat;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SupplierService
{
    public function queryForAdmin(?string $search = null, ?string $from = null, ?string $to = null, ?string $debtFilter = null): Builder
    {
        $query = Supplier::query()
            ->with(['debt'])
            ->withCount([
                'stockItems as linked_items_count',
                'stockMovements as movements_count',
            ])
            ->orderByDesc('id');

        if ($search) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('fax', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('tax_number', 'like', "%{$search}%")
                    ->orWhere('commercial_registry', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('bank_name', 'like', "%{$search}%")
                    ->orWhere('iban', 'like', "%{$search}%");
            });
        }

        if ($from) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to) {
            $query->whereDate('created_at', '<=', $to);
        }

        if ($debtFilter === 'with_debt') {
            $query->whereHas('debt', fn (Builder $q) => $q->whereRaw('due > collected'));
        } elseif ($debtFilter === 'no_debt') {
            $query->where(function (Builder $q) {
                $q->whereDoesntHave('debt')
                    ->orWhereHas('debt', fn (Builder $d) => $d->whereRaw('due <= collected'));
            });
        }

        return $query;
    }

    /** @return list<Supplier> */
    public function listForAdmin(?string $search = null, ?string $from = null, ?string $to = null, ?string $debtFilter = null): Collection
    {
        return $this->queryForAdmin($search, $from, $to, $debtFilter)
            ->limit((int) config('dashboards.table_fetch_limit', 1000))
            ->get()
            ->each(fn (Supplier $supplier) => $this->hydrateStats($supplier));
    }

    public function hydrateStats(Supplier $supplier): Supplier
    {
        $supplier->setAttribute('debt_total', $supplier->debtRemaining());
        $supplier->setAttribute('debt_items_count', $supplier->debtItemsCount());
        $supplier->setAttribute('can_delete', $supplier->canHardDelete());

        return $supplier;
    }

    /** @param  list<int>  $stockItemIds */
    public function syncStockItems(Supplier $supplier, array $stockItemIds): void
    {
        $ids = StockItem::query()
            ->whereIn('id', $stockItemIds)
            ->pluck('id')
            ->all();

        $supplier->stockItems()->sync($ids);
    }

    public function attachStockItem(Supplier $supplier, StockItem $item): void
    {
        $supplier->stockItems()->syncWithoutDetaching([$item->id]);
    }

    public function canDelete(Supplier $supplier): bool
    {
        return $supplier->canHardDelete();
    }

    public function deleteReason(Supplier $supplier): ?string
    {
        if ($supplier->stockMovements()->exists()) {
            return 'لا يمكن حذف المورد — له حركات مخزنية/مالية مسجّلة.';
        }

        if ($supplier->debtRemaining() > 0) {
            return 'لا يمكن حذف المورد — عليه مديونية مستحقة.';
        }

        if ($supplier->stockItemPrices()->exists()) {
            return 'لا يمكن حذف المورد — مرتبط بأسعار شراء مسجّلة.';
        }

        return null;
    }

    /**
     * @return array{title: string, period_label: string, headers: list<string>, rows: list<list<string>>}
     */
    public function exportReport(?string $search = null, ?string $from = null, ?string $to = null, ?string $debtFilter = null): array
    {
        $suppliers = $this->listForAdmin($search, $from, $to, $debtFilter);

        $periodParts = [];
        if ($from) {
            $periodParts[] = 'من '.Carbon::parse($from)->format('Y-m-d');
        }
        if ($to) {
            $periodParts[] = 'إلى '.Carbon::parse($to)->format('Y-m-d');
        }

        $rows = $suppliers->map(function (Supplier $s) {
            return ExportCsvFormat::row([
                $s->name,
                $s->phone ?? '—',
                $s->fax ?? '—',
                $s->email ?? '—',
                $s->address ?? '—',
                $s->tax_number ?? '—',
                $s->commercial_registry ?? '—',
                $s->bank_name ?? '—',
                $s->bank_branch ?? '—',
                $s->bank_account ?? '—',
                $s->iban ?? '—',
                (string) ($s->linked_items_count ?? 0),
                $s->created_at?->format('Y-m-d') ?? '—',
            ]);
        })->all();

        return [
            'title' => 'تقرير الموردين',
            'period_label' => $periodParts !== [] ? implode(' ', $periodParts) : 'كل الفترات',
            'headers' => [
                'اسم المورد / الشركة',
                'الهاتف',
                'الفاكس',
                'البريد',
                'العنوان',
                'الرقم الضريبي',
                'السجل التجاري',
                'اسم البنك',
                'فرع البنك',
                'رقم الحساب',
                'IBAN',
                'عدد الأصناف المرتبطة',
                'تاريخ الإضافة',
            ],
        ] + ['rows' => $rows];
    }
}

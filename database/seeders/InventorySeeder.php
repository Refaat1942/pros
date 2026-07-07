<?php

namespace Database\Seeders;

use App\Models\StockCategory;
use App\Models\StockItem;
use App\Models\StockItemPrice;
use App\Services\StockCatalogService;
use App\Services\StockCategorySchemaService;
use Database\Seeders\Support\PrototypeSeedData;
use Database\Seeders\Support\SeedRegistry;
use Illuminate\Database\Seeder;

class InventorySeeder extends Seeder
{
    public function __construct(
        private readonly StockCategorySchemaService $categorySchema,
        private readonly StockCatalogService $catalog,
    ) {}

    public function run(): void
    {
        foreach (PrototypeSeedData::stockItems() as $row) {
            $categoryId = StockCategory::query()
                ->where('name', $row['category'])
                ->value('id');

            $attributes = $row['attributes'] ?? [];
            $uom = is_string($attributes['uom'] ?? null) && $attributes['uom'] !== ''
                ? $attributes['uom']
                : 'قطعة';

            $item = StockItem::query()->create([
                'code' => $row['code'],
                'name' => $row['name'],
                'spec' => $row['spec'],
                'category_id' => $categoryId,
                'store_class' => PrototypeSeedData::deriveStoreClass($row['category']),
                'uom' => $uom,
                'barcode' => PrototypeSeedData::deriveBarcode($row['code']),
                'qty' => $row['qty'],
                'reserved' => $row['reserved'],
                'status' => $row['status'],
                'last_moved_at' => PrototypeSeedData::parseDate($row['lastMoved'] ?? '01/06/2026'),
            ]);

            if ($categoryId && $attributes !== []) {
                $this->categorySchema->syncItemAttributes($item, $categoryId, $attributes);
            }

            SeedRegistry::$stockItems[$row['code']] = $item->id;

            $supplierIds = [];

            foreach ($row['prices'] as $price) {
                $supplierId = SeedRegistry::$suppliers[$price['supplier']] ?? null;

                if (! $supplierId) {
                    continue;
                }

                $supplierIds[] = $supplierId;

                StockItemPrice::query()->create([
                    'stock_item_id' => $item->id,
                    'price_ref' => $price['id'],
                    'label' => $price['label'],
                    'supplier_id' => $supplierId,
                    'supplier_type' => $price['supplierType'],
                    'supplier_item_code' => $price['itemCode'],
                    'amount' => $price['amount'],
                    'qty' => max(1, (int) ($price['qty'] ?? 1)),
                    'received_at' => PrototypeSeedData::parseDate($row['lastMoved'] ?? '01/06/2026')?->toDateString(),
                ]);
            }

            if ($supplierIds !== []) {
                $this->catalog->syncSuppliers($item, array_values(array_unique($supplierIds)));
            }

            $highest = collect($row['prices'])->max('amount');
            if ($highest > 0 && (int) $row['qty'] > 0) {
                $item->update([
                    'price' => (float) $highest,
                    'wac' => (float) $highest,
                ]);
            }
        }
    }
}

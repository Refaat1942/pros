<?php

namespace Database\Seeders;

use App\Models\StockItem;
use App\Models\StockItemPrice;
use Database\Seeders\Support\PrototypeSeedData;
use Database\Seeders\Support\SeedRegistry;
use Illuminate\Database\Seeder;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (PrototypeSeedData::stockItems() as $row) {
            $item = StockItem::query()->create([
                'code' => $row['code'],
                'name' => $row['name'],
                'spec' => $row['spec'],
                'category' => $row['category'],
                'store_class' => PrototypeSeedData::deriveStoreClass($row['category']),
                'uom' => 'قطعة',
                'barcode' => PrototypeSeedData::deriveBarcode($row['code']),
                'qty' => $row['qty'],
                'reserved' => $row['reserved'],
                'status' => $row['status'],
                'last_moved_at' => PrototypeSeedData::parseDate($row['lastMoved'] ?? '01/06/2026'),
            ]);

            SeedRegistry::$stockItems[$row['code']] = $item->id;

            foreach ($row['prices'] as $price) {
                $supplierId = SeedRegistry::$suppliers[$price['supplier']] ?? null;

                if (! $supplierId) {
                    continue;
                }

                StockItemPrice::query()->create([
                    'stock_item_id' => $item->id,
                    'price_ref' => $price['id'],
                    'label' => $price['label'],
                    'supplier_id' => $supplierId,
                    'supplier_type' => $price['supplierType'],
                    'supplier_item_code' => $price['itemCode'],
                    'amount' => $price['amount'],
                ]);
            }
        }
    }
}

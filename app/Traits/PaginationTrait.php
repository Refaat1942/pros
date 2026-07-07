<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait PaginationTrait
{
    protected function perPage(): int
    {
        return (int) config('dashboards.table_per_page', 10);
    }

    protected function dashboardFetchLimit(): int
    {
        return (int) config('dashboards.table_fetch_limit', 1000);
    }

    /**
     * جلب صفوف الجدول للوحات — التصفح يتم في المتصفح (table-pagination.js).
     *
     * @param  Builder|\Illuminate\Database\Query\Builder  $query
     */
    protected function fetchForDashboard($query)
    {
        return $query->limit($this->dashboardFetchLimit())->get();
    }

    public function paginationModel($col)
    {
        return [
            'total_items' => $col->total(),
            'count_items' => (int) $col->count(),
            'per_page' => $col->perPage(),
            'total_pages' => $col->lastPage(),
            'current_page' => $col->currentPage(),
            'next_page_url' => (string) $col->nextPageUrl(),
            'perv_page_url' => (string) $col->previousPageUrl(),
        ];

    }
}

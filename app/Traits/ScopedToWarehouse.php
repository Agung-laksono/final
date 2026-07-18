<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait ScopedToWarehouse
{
    /**
     * Boot the ScopedToWarehouse trait for a model.
     *
     * @return void
     */
    protected static function bootScopedToWarehouse()
    {
        static::addGlobalScope('warehouse_scope', function (Builder $builder) {
            // Ensure we are in a web context with an authenticated user
            if (auth()->check()) {
                $user = auth()->user();
                
                // Roles that bypass the warehouse filter
                if ($user->hasAnyRole(['Super Admin', 'Manager'])) {
                    return;
                }

                // Get the warehouse IDs assigned to this user
                // Get the warehouse IDs assigned to this user using DB::table to prevent infinite loop
                // (if we use Eloquent, it triggers this global scope again on Warehouse model)
                $allowedWarehouseIds = \Illuminate\Support\Facades\DB::table('user_warehouse')
                    ->where('user_id', $user->id)
                    ->pluck('warehouse_id')
                    ->toArray();
                
                // Determine which column to filter by. Defaults to 'warehouse_id'.
                // A model can override this by defining a WAREHOUSE_SCOPE_COLUMN constant.
                $column = defined('static::WAREHOUSE_SCOPE_COLUMN') ? static::WAREHOUSE_SCOPE_COLUMN : 'warehouse_id';

                // Apply the whereIn filter
                $builder->whereIn($builder->getModel()->getTable() . '.' . $column, $allowedWarehouseIds);
            }
        });
    }
}

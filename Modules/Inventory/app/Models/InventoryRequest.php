<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class InventoryRequest extends Model
{
    protected $guarded = ['id'];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function routedBy()
    {
        return $this->belongsTo(User::class, 'routed_by');
    }
}

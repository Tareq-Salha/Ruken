<?php

namespace App\Models;

use App\Traits\HasOrderStatus;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasOrderStatus;

    protected $fillable = [
        'customer_name',
        'size',
        'notes',
        'status',
        'cancellation_reason',
    ];

    protected $attributes = [
        'status' => self::STATUS_NEW,
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
        ];
    }
}

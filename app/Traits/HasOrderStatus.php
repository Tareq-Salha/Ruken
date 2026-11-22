<?php

namespace App\Traits;

trait HasOrderStatus
{
    public const STATUS_NEW = 'new';
    public const STATUS_PREPARING = 'preparing';
    public const STATUS_READY = 'ready';
    public const STATUS_CANCELLED = 'cancelled';

    public static function statuses(): array
    {
        return [
            self::STATUS_NEW,
            self::STATUS_PREPARING,
            self::STATUS_READY,
            self::STATUS_CANCELLED,
        ];
    }
}

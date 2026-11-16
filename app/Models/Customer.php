<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    public const UID_PREFIX = 'CUS_';
    public const FIRST_UID_NUMBER = 1001;

    protected $guarded = [];

    protected static function booted(): void
    {
        // Every customer, however created (register, Google, login, admin),
        // automatically gets the next UID: CUS_1001, CUS_1002, ...
        static::creating(function (Customer $customer) {
            if (empty($customer->customer_uid)) {
                $customer->customer_uid = static::nextCustomerUid();
            }
        });
    }

    public static function nextCustomerUid(): string
    {
        // Longest then highest string == highest number (CUS_999 < CUS_1000).
        $last = static::query()
            ->where('customer_uid', 'like', self::UID_PREFIX . '%')
            ->orderByRaw('LENGTH(customer_uid) DESC')
            ->orderBy('customer_uid', 'desc')
            ->value('customer_uid');

        $max = $last ? (int) substr($last, strlen(self::UID_PREFIX)) : 0;

        return self::UID_PREFIX . max(self::FIRST_UID_NUMBER, $max + 1);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

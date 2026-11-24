<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GpsLocation extends Model
{
    protected $fillable = [
        'user_id',
        'latitude',
        'longitude',
        'accuracy',
        'recorded_at',
        'is_valid',
        'reject_reason',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'accuracy' => 'float',
            'recorded_at' => 'datetime',
            'is_valid' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

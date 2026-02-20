<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PriceLevel extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function product()
    {
        return $this->belongsTo(Product::class, 'prod_code', 'prod_code');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->u_id)) {
                $model->u_id = (string) Str::uuid();
            }
            if (auth()->check()) {
                $model->modified_user = auth()->id();
            }
        });

        static::updating(function ($model) {
            if (auth()->check()) {
                $model->modified_user = auth()->id();
            }
        });
    }
}

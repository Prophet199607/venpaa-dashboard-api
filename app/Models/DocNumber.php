<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocNumber extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function getDocCode()
    {
        $nextId = $this->last_id + 1;
        $code = $this->prefix . str_pad($nextId, $this->length, '0', STR_PAD_LEFT);
        return ['code' => $code, 'id' => $nextId];
    }

    public function incrementLastId()
    {
        $this->increment('last_id');
    }
}

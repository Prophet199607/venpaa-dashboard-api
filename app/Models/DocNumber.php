<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocNumber extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function getDocCode(?string $loca_code = null, bool $useSeparator = false)
    {
        $nextId = $this->last_id + 1;
        $padded = str_pad($nextId, $this->length, '0', STR_PAD_LEFT);
        $middle = $loca_code ? ($useSeparator ? $loca_code : $loca_code) : '';
        $code = $this->prefix . $middle . $padded;
        return ['code' => $code, 'id' => $nextId];
    }

    public function incrementLastId()
    {
        $this->increment('last_id');
    }

    public static function generate(string $type, string $prefix, int $length = 8, ?string $loca_code = null, bool $useSeparator = false)
    {
        $docNumber = self::firstOrCreate(
            ['type' => $type],
            ['prefix' => $prefix, 'length' => $length, 'last_id' => 0]
        );

        $docCode = $docNumber->getDocCode($loca_code, $useSeparator);

        $docNumber->incrementLastId();

        return $docCode['code'];
    }
}

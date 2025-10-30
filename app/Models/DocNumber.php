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

    public static function generate(string $type, string $prefix, int $length = 8, ?string $loca_code = null, bool $useSeparator = false)
    {
        // If a location code is provided, create a location-specific type and prefix.
        if ($loca_code) {
            $type = $useSeparator ? $type . '_' . $loca_code : $type . $loca_code;
            $prefix = $prefix . $loca_code;
        }

        $docNumber = self::firstOrCreate(
            ['type' => $type],
            ['prefix' => $prefix, 'length' => $length, 'last_id' => 0]
        );

        $docCode = $docNumber->getDocCode();

        // Increment last_id immediately for temporary codes
        $docNumber->incrementLastId();

        return $docCode['code'];
    }
}

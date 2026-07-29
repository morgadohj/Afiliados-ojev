<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Affiliate extends Model
{
    use HasFactory;

    protected $fillable = [
        'folio',
        'application_date',
        'first_name',
        'paternal_last_name',
        'maternal_last_name',
        'curp',
        'birth_date',
        'address_street',
        'neighborhood',
        'locality',
        'municipality',
        'state',
        'postal_code',
        'home_phone',
        'mobile_phone',
        'email',
        'occupation',
        'livestock_association',
        'oje_v_branch',
        'profile_photo_path',
        'ine_front_path',
        'ine_back_path',
        'signature_name',
        'consent_accepted_at',
        'status',
        'ocr_metadata',
    ];

    protected function casts(): array
    {
        return [
            'application_date' => 'date',
            'birth_date' => 'date',
            'consent_accepted_at' => 'datetime',
            'ocr_metadata' => 'array',
        ];
    }
}

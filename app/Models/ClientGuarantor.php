<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientGuarantor extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'name',
        'address',
        'phone',
        'alternative_phone',
        'proof_of_address',
        'id_photo',
        'job',
        'relation',
        'receipt_number',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}

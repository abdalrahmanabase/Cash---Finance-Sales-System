<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'phone',
        'secondary_phone',
        'proof_of_address',
        'id_photo',
        'job',
        'receipt_number',
    ];

    public function guarantors()
    {
        return $this->hasMany(ClientGuarantor::class);
    }
    
    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;

class ProviderBill extends Model
{
    use HasFactory;

    protected $fillable = [
        'image_path',
        'notes',
        'provider_id',
        'total_amount',
    ];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute()
    {
        if (!$this->image_path) return null;
        
        return Storage::disk('public')->exists($this->image_path)
            ? Storage::disk('public')->url($this->image_path)
            : null;
    }

    public function provider()
    {
        return $this->belongsTo(Provider::class);
    }
}
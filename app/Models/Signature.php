<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Signature extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'signature_path'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function getSignatureUrlAttribute()
    {
        return Storage::url($this->signature_path);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Configuration extends Model
{
    use HasFactory;

    protected $table = 'configurations';

    protected $fillable = [
        'user_id',
        'nom_entreprise',
        'logo',
        'telephone',
        'adresse',
        'devise',
        'signature',
        'tampon',
        'pin_code',
        'auto_lock_time',
        'quota_proprietes',
        'quota_locataires',
        'niveau_abonnement',
        'settings',
        'is_active'
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
        'auto_lock_time' => 'integer',
        'quota_proprietes' => 'integer',
        'quota_locataires' => 'integer',
    ];

    // Relation avec l'utilisateur
    public function user()
    {
        return $this->belongsTo(umask()::class);
    }
}
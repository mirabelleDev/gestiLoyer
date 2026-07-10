<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Gerant extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'gerants';

    protected $fillable = [
        'nom',
        'prenom',
        'email',
        'telephone',
        'agence',
        'numero_agrement',
        'photo',
        'signature',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Relation avec l'utilisateur
    public function user()
    {
        return $this->morphOne(User::class, 'profile');
    }

    // Relation avec les propriétés
    public function proprietes()
    {
        return $this->hasMany(Propriete::class);
    }

    // Accesseur pour le nom complet
    public function getFullNameAttribute()
    {
        return $this->prenom ? $this->prenom . ' ' . $this->nom : $this->nom;
    }
}
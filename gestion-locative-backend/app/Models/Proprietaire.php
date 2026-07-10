<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Proprietaire extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'proprietaires';

    protected $fillable = [
        'nom',
        'prenom',
        'email',
        'telephone',
        'adresse',
        'photo',
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

    // Relation avec les revenus (via les paiements)
    public function revenus()
    {
        return $this->hasManyThrough(
            Paiement::class,
            Propriete::class,
            'proprietaire_id',
            'contrat_id',
            'id',
            'id'
        );
    }

    // Accesseur pour le nom complet
    public function getFullNameAttribute()
    {
        return $this->prenom ? $this->prenom . ' ' . $this->nom : $this->nom;
    }
}
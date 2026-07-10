<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Demande extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'demandes';

    protected $fillable = [
        'locataire_id',
        'unite_locative_id',
        'titre',
        'description',
        'type',
        'statut',
        'date_resolution',
        'commentaire_resolution',
        'photo',
        'traite_par',
        'is_active'
    ];

    protected $casts = [
        'date_resolution' => 'datetime',
        'is_active' => 'boolean',
    ];

    // Relation avec le locataire
    public function locataire()
    {
        return $this->belongsTo(Locataire::class);
    }

    // Relation avec l'unité locative
    public function unite()
    {
        return $this->belongsTo(UniteLocative::class, 'unite_locative_id');
    }

    // Relation avec l'utilisateur qui a traité
    public function traitePar()
    {
        return $this->belongsTo(User::class, 'traite_par');
    }

    // Accesseur pour le type libellé
    public function getTypeLibelleAttribute()
    {
        $types = [
            'reparation' => 'Réparation',
            'entretien' => 'Entretien',
            'plainte' => 'Plainte',
            'autre' => 'Autre'
        ];
        return $types[$this->type] ?? $this->type;
    }

    // Accesseur pour le statut libellé
    public function getStatutLibelleAttribute()
    {
        $statuts = [
            'en_attente' => 'En attente',
            'en_cours' => 'En cours',
            'resolu' => 'Résolu',
            'rejete' => 'Rejeté'
        ];
        return $statuts[$this->statut] ?? $this->statut;
    }

    // Vérifier si la demande est résolue
    public function getEstResolueAttribute()
    {
        return in_array($this->statut, ['resolu', 'rejete']);
    }
}
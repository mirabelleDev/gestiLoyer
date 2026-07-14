<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Locataire extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'locataires';

    protected $fillable = [
        'nom',
        'prenom',
        'email',
        'password',
        'telephone',
        'telephone_urgence',
        'whatsapp',
        'adresse',
        'piece_identite',
        'numero_piece',
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

    // Relation avec les contrats
    public function contrats()
    {
        return $this->hasMany(Contrat::class);
    }

    // Relation avec les paiements
    public function paiements()
    {
        return $this->hasMany(Paiement::class);
    }

    // Relation avec les demandes
    public function demandes()
    {
        return $this->hasMany(Demande::class);
    }

    // Accesseur pour le nom complet
    public function getFullNameAttribute()
    {
        return $this->prenom ? $this->prenom . ' ' . $this->nom : $this->nom;
    }

    // Calcul du solde
    public function getSoldeAttribute()
    {
        $totalPaye = $this->paiements()->sum('montant');
        $contratActif = $this->contrats()->where('statut', 'actif')->first();
        
        if (!$contratActif) {
            return 0;
        }

        $totalDu = $contratActif->loyer_mensuel * 3; // 3 mois par défaut
        return $totalPaye - $totalDu;
    }
}
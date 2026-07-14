<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionMobile extends Model
{
    protected $table = 'transactions_mobile';

    protected $fillable = [
        'contrat_id',
        'locataire_id',
        'paiement_id',
        'reference',
        'transaction_id',
        'montant',
        'operateur',
        'numero_telephone',
        'periode_mois',
        'periode_annee',
        'statut',
        'date_initiation',
        'date_confirmation',
        'message_erreur'
    ];

    protected $casts = [
        'date_initiation' => 'datetime',
        'date_confirmation' => 'datetime',
        'montant' => 'decimal:2'
    ];

    public function contrat()
    {
        return $this->belongsTo(Contrat::class);
    }

    public function locataire()
    {
        return $this->belongsTo(Locataire::class);
    }

    public function paiement()
    {
        return $this->belongsTo(Paiement::class);
    }

    public function getStatutLibelleAttribute()
    {
        $statuts = [
            'en_attente' => 'En attente',
            'en_cours' => 'En cours',
            'confirme' => 'Confirmé',
            'echoue' => 'Échoué'
        ];
        return $statuts[$this->statut] ?? $this->statut;
    }

    public function getOperateurLibelleAttribute()
    {
        $operateurs = [
            'orange_money' => 'Orange Money',
            'mtn_money' => 'MTN Mobile Money',
            'moov_money' => 'Moov Money'
        ];
        return $operateurs[$this->operateur] ?? $this->operateur;
    }
}
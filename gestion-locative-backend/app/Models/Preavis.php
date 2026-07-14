<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Preavis extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'contrat_id',
        'locataire_id',
        'date_depart',
        'motif',
        'statut',
        'date_approbation',
        'motif_rejet',
        'commentaire',
        'notifie_proprietaire'
    ];

    protected $casts = [
        'date_depart' => 'date',
        'date_approbation' => 'datetime',
        'notifie_proprietaire' => 'boolean'
    ];

    // Relations
    public function contrat()
    {
        return $this->belongsTo(Contrat::class);
    }

    public function locataire()
    {
        return $this->belongsTo(Locataire::class);
    }

    // Accesseurs
    public function getStatutLibelleAttribute()
    {
        $statuts = [
            'en_attente' => 'En attente',
            'approuve' => 'Approuvé',
            'rejete' => 'Rejeté'
        ];
        return $statuts[$this->statut] ?? $this->statut;
    }

    public function getJoursRestantsAttribute()
    {
        if ($this->statut === 'approuve') {
            return now()->diffInDays($this->date_depart);
        }
        return null;
    }

    public function getEstUrgentAttribute()
    {
        if ($this->statut === 'approuve') {
            return now()->diffInDays($this->date_depart) <= 15;
        }
        return false;
    }
}
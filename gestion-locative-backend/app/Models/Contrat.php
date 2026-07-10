<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contrat extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'locataire_id',
        'unite_locative_id',
        'date_debut',
        'date_fin',
        'duree_mois',
        'loyer_mensuel',
        'devise',
        'caution',
        'avance_loyer',
        'statut',
        'date_resiliation',
        'motif_resiliation',
        'conditions_speciales',
        'document_pdf',
        'is_active'
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'date_resiliation' => 'date',
        'loyer_mensuel' => 'decimal:2',
        'caution' => 'decimal:2',
        'avance_loyer' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function locataire()
    {
        return $this->belongsTo(Locataire::class);
    }

    public function unite()
    {
        return $this->belongsTo(UniteLocative::class, 'unite_locative_id');
    }

    public function paiements()
    {
        return $this->hasMany(Paiement::class);
    }

    public function getMontantTotalAttribute()
    {
        return $this->loyer_mensuel * $this->duree_mois;
    }

    public function getMoisRestantsAttribute()
    {
        if ($this->statut !== 'actif' || !$this->date_fin) {
            return 0;
        }
        return now()->diffInMonths($this->date_fin, false);
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Paiement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'contrat_id',
        'locataire_id',
        'montant',
        'devise',
        'periode_mois',
        'periode_annee',
        'mode_paiement',
        'reference',
        'statut',
        'reste_a_payer',
        'date_paiement',
        'numero_recu',
        'qr_code_data',
        'valide_par',
        'notes',
        'recu_pdf',
        'is_active'
    ];

    protected $casts = [
        'montant' => 'decimal:2',
        'reste_a_payer' => 'decimal:2',
        'date_paiement' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function contrat()
    {
        return $this->belongsTo(Contrat::class);
    }

    public function locataire()
    {
        return $this->belongsTo(Locataire::class);
    }

    public function validePar()
    {
        return $this->belongsTo(User::class, 'valide_par');
    }

    public function getPeriodeAttribute()
    {
        return $this->periode_mois . '/' . $this->periode_annee;
    }
}
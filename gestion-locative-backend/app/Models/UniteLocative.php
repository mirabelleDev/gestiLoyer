<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UniteLocative extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'propriete_id',
        'numero',
        'etage',
        'type', // 'appartement', 'chambre', 'boutique', 'bureau'
        'superficie',
        'loyer_nominal',
        'devise',
        'statut', // 'disponible', 'occupe', 'travaux'
        'description',
        'is_active'
    ];

    public function propriete()
    {
        return $this->belongsTo(Propriete::class);
    }

    public function contratActuel()
    {
        return $this->hasOne(Contrat::class)->where('statut', 'actif');
    }

    public function contrats()
    {
        return $this->hasMany(Contrat::class);
    }
}



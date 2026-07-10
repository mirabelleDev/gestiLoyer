<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Propriete extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'nom',
        'adresse',
        'ville',
        'quartier',
        'code_postal',
        'type', // 'immeuble', 'villa', 'residence'
        'nombre_unites',
        'description',
        'gerant_id',
        'proprietaire_id',
        'is_active'
    ];

    public function gerant()
    {
        return $this->belongsTo(Gerant::class);
    }

    public function proprietaire()
    {
        return $this->belongsTo(Proprietaire::class);
    }

    public function unites()
    {
        return $this->hasMany(UniteLocative::class);
    }
}

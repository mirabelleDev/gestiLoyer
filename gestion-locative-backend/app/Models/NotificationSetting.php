<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationSetting extends Model
{
    protected $fillable = [
        'user_id',
        'email_notifications',
        'sms_notifications',
        'push_notifications',
        'retard_paiement',
        'contrat_expiration',
        'nouvelle_demande',
        'preavis_recu',
        'retard_alerte_jours',
        'contrat_rappel_jours',
        'notification_heure',
        'preferences'
    ];

    protected $casts = [
        'email_notifications' => 'boolean',
        'sms_notifications' => 'boolean',
        'push_notifications' => 'boolean',
        'retard_paiement' => 'boolean',
        'contrat_expiration' => 'boolean',
        'nouvelle_demande' => 'boolean',
        'preavis_recu' => 'boolean',
        'retard_alerte_jours' => 'integer',
        'contrat_rappel_jours' => 'integer',
        'notification_heure' => 'datetime',
        'preferences' => 'array'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Créer les paramètres par défaut pour un nouvel utilisateur
    public static function createDefaults($userId)
    {
        return self::create([
            'user_id' => $userId,
            'email_notifications' => true,
            'sms_notifications' => false,
            'push_notifications' => true,
            'retard_paiement' => true,
            'contrat_expiration' => true,
            'nouvelle_demande' => true,
            'preavis_recu' => true,
            'retard_alerte_jours' => 5,
            'contrat_rappel_jours' => 30,
            'notification_heure' => '08:00:00'
        ]);
    }
}
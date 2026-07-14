<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contrat;
use App\Models\Paiement;
use App\Models\Locataire;
use App\Models\Propriete;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationController extends Controller
{
    // 5.1 LISTE DES NOTIFICATIONS
    public function index(Request $request)
    {
        $user = $request->user();
        
        // Simuler des notifications (à remplacer par une vraie table)
        $notifications = $this->getNotifications($user);
        
        return response()->json([
            'success' => true,
            'data' => $notifications,
            'non_lues' => $notifications->where('lu', false)->count()
        ]);
    }

    // 5.2 DÉTECTION DES RETARDS DE PAIEMENT
    public function detecterRetards(Request $request)
    {
        $user = $request->user();
        
        $contrats = Contrat::where('statut', 'actif')
            ->when($user->profile_type === 'proprietaire', function($query) use ($user) {
                return $query->whereHas('unite.propriete', function($q) use ($user) {
                    $q->where('proprietaire_id', $user->profile_id);
                });
            })
            ->when($user->profile_type === 'gerant', function($query) use ($user) {
                return $query->whereHas('unite.propriete', function($q) use ($user) {
                    $q->where('gerant_id', $user->profile_id);
                });
            })
            ->get();

        $retards = [];
        $moisActuel = now()->month;
        $anneeActuel = now()->year;

        foreach ($contrats as $contrat) {
            // Vérifier si le paiement du mois a été effectué
            $paye = Paiement::where('contrat_id', $contrat->id)
                ->where('periode_mois', $moisActuel)
                ->where('periode_annee', $anneeActuel)
                ->exists();

            if (!$paye) {
                $joursRetard = now()->diffInDays(Carbon::create($anneeActuel, $moisActuel, 5));
                
                $retards[] = [
                    'contrat_id' => $contrat->id,
                    'locataire' => $contrat->locataire->full_name,
                    'unite' => $contrat->unite->numero,
                    'propriete' => $contrat->unite->propriete->nom,
                    'montant' => $contrat->loyer_mensuel,
                    'mois' => $moisActuel,
                    'annee' => $anneeActuel,
                    'jours_retard' => $joursRetard,
                    'est_retard' => $joursRetard > 5,
                    'telephone' => $contrat->locataire->telephone,
                    'email' => $contrat->locataire->email,
                    'niveau' => $this->getNiveauRetard($joursRetard)
                ];
            }
        }

        // Envoyer des notifications si nécessaire
        if (!empty($retards)) {
            $this->envoyerNotificationsRetard($retards);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'total_retards' => count($retards),
                'retards' => $retards,
                'derniere_verification' => now()->format('d/m/Y H:i:s')
            ]
        ]);
    }

    // 5.3 ENVOYER UNE NOTIFICATION DE RETARD
    public function envoyerNotificationRetard(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'contrat_id' => 'required|exists:contrats,id',
            'type' => 'required|in:sms,email,both'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $contrat = Contrat::with(['locataire', 'unite', 'unite.propriete'])
            ->find($request->contrat_id);

        if (!$contrat) {
            return response()->json([
                'success' => false,
                'message' => 'Contrat non trouvé'
            ], 404);
        }

        $moisActuel = now()->month;
        $anneeActuel = now()->year;
        $joursRetard = now()->diffInDays(Carbon::create($anneeActuel, $moisActuel, 5));

        $message = $this->getMessageRetard($contrat, $joursRetard, $moisActuel, $anneeActuel);

        $notifications = [];

        // Envoyer par SMS (simulé)
        if (in_array($request->type, ['sms', 'both'])) {
            $smsEnvoye = $this->sendSMS($contrat->locataire->telephone, $message);
            $notifications['sms'] = [
                'envoye' => $smsEnvoye,
                'destinataire' => $contrat->locataire->telephone,
                'message' => $message
            ];
        }

        // Envoyer par Email
        if (in_array($request->type, ['email', 'both'])) {
            $emailEnvoye = $this->sendEmail($contrat->locataire->email, $message, $contrat);
            $notifications['email'] = [
                'envoye' => $emailEnvoye,
                'destinataire' => $contrat->locataire->email,
                'sujet' => 'Retard de paiement - ' . $this->getMoisLibelle($moisActuel) . ' ' . $anneeActuel,
                'message' => $message
            ];
        }

        // Enregistrer l'historique
        Log::info('Notification de retard envoyée', [
            'contrat_id' => $contrat->id,
            'type' => $request->type,
            'locataire' => $contrat->locataire->full_name
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notification envoyée avec succès',
            'data' => [
                'contrat' => $contrat,
                'notifications' => $notifications,
                'message' => $message
            ]
        ]);
    }

    // 5.4 RAPPEL AUTOMATIQUE (CRON)
    public function rappelAutomatique()
    {
        $moisActuel = now()->month;
        $anneeActuel = now()->year;

        // Récupérer tous les contrats actifs
        $contrats = Contrat::where('statut', 'actif')->get();
        $notificationsEnvoyees = [];

        foreach ($contrats as $contrat) {
            // Vérifier si le paiement du mois a été effectué
            $paye = Paiement::where('contrat_id', $contrat->id)
                ->where('periode_mois', $moisActuel)
                ->where('periode_annee', $anneeActuel)
                ->exists();

            if (!$paye) {
                $joursRetard = now()->diffInDays(Carbon::create($anneeActuel, $moisActuel, 5));
                
                // Envoyer un rappel si le retard est > 5 jours
                if ($joursRetard > 5) {
                    $contrat->load(['locataire', 'unite', 'unite.propriete']);
                    
                    $message = $this->getMessageRetard($contrat, $joursRetard, $moisActuel, $anneeActuel);
                    
                    // Envoyer par SMS
                    $this->sendSMS($contrat->locataire->telephone, $message);
                    
                    // Envoyer par Email
                    $this->sendEmail($contrat->locataire->email, $message, $contrat);

                    $notificationsEnvoyees[] = [
                        'contrat_id' => $contrat->id,
                        'locataire' => $contrat->locataire->full_name,
                        'telephone' => $contrat->locataire->telephone,
                        'email' => $contrat->locataire->email,
                        'montant' => $contrat->loyer_mensuel,
                        'jours_retard' => $joursRetard
                    ];
                }
            }
        }

        Log::info('Rappels automatiques envoyés', [
            'total' => count($notificationsEnvoyees),
            'date' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Rappels automatiques envoyés',
            'data' => [
                'total_notifications' => count($notificationsEnvoyees),
                'notifications' => $notificationsEnvoyees,
                'date_execution' => now()->format('d/m/Y H:i:s')
            ]
        ]);
    }

    // 5.5 NOTIFIER LE PROPRIÉTAIRE
    public function notifierProprietaire(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'proprietaire_id' => 'required|exists:proprietaires,id',
            'type' => 'required|in:preavis,renouvellement,retard,autre',
            'sujet' => 'required|string',
            'message' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $proprietaire = \App\Models\Proprietaire::find($request->proprietaire_id);
        $user = User::where('profile_type', 'App\\Models\\Proprietaire')
            ->where('profile_id', $proprietaire->id)
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun compte utilisateur associé à ce propriétaire'
            ], 404);
        }

        // Envoyer l'email
        $emailEnvoye = $this->sendEmail($user->email, $request->message, null, $request->sujet);

        return response()->json([
            'success' => true,
            'message' => 'Notification envoyée au propriétaire',
            'data' => [
                'proprietaire' => $proprietaire,
                'email_envoye' => $emailEnvoye,
                'type' => $request->type,
                'sujet' => $request->sujet,
                'message' => $request->message,
                'envoye_le' => now()->format('d/m/Y H:i:s')
            ]
        ]);
    }

    // 5.6 MARQUER UNE NOTIFICATION COMME LUE
    public function marquerLue(Request $request, $id)
    {
        // Simuler (à remplacer par une vraie table de notifications)
        return response()->json([
            'success' => true,
            'message' => 'Notification marquée comme lue'
        ]);
    }

    // 5.7 OBTENIR LES NOTIFICATIONS (Simulé)
    private function getNotifications($user)
    {
        $notifications = collect();
        
        // Vérifier les retards de paiement
        $retards = $this->detecterRetards(new Request())->getData()->data->retards ?? [];
        
        foreach ($retards as $retard) {
            if (is_array($retard)) {
                $notifications->push((object)[
                    'id' => uniqid(),
                    'type' => 'retard_paiement',
                    'titre' => 'Retard de paiement',
                    'message' => $retard['locataire'] . ' - ' . $retard['jours_retard'] . ' jours de retard',
                    'lu' => false,
                    'date' => now()->subMinutes(rand(1, 60)),
                    'data' => $retard
                ]);
            }
        }
        
        // Vérifier les contrats expirant bientôt
        $contratsExpirant = Contrat::where('statut', 'actif')
            ->whereDate('date_fin', '<=', now()->addDays(30))
            ->get();
            
        foreach ($contratsExpirant as $contrat) {
            $notifications->push((object)[
                'id' => uniqid(),
                'type' => 'contrat_expirant',
                'titre' => 'Contrat expirant bientôt',
                'message' => $contrat->locataire->full_name . ' - Expire le ' . $contrat->date_fin->format('d/m/Y'),
                'lu' => false,
                'date' => now()->subMinutes(rand(1, 60)),
                'data' => [
                    'contrat_id' => $contrat->id,
                    'locataire' => $contrat->locataire->full_name,
                    'date_fin' => $contrat->date_fin->format('d/m/Y'),
                    'jours_restants' => now()->diffInDays($contrat->date_fin)
                ]
            ]);
        }

        return $notifications->sortByDesc('date')->values();
    }

    // 5.8 MÉTHODES UTILITAIRES
    private function getNiveauRetard($joursRetard)
    {
        if ($joursRetard <= 5) return 'faible';
        if ($joursRetard <= 15) return 'moyen';
        if ($joursRetard <= 30) return 'eleve';
        return 'critique';
    }

    private function getMessageRetard($contrat, $joursRetard, $mois, $annee)
    {
        $message = "Bonjour {$contrat->locataire->full_name},\n\n";
        $message .= "Nous constatons un retard de paiement pour le loyer du mois de " . 
                   $this->getMoisLibelle($mois) . " {$annee}.\n\n";
        $message .= "Montant dû : " . number_format($contrat->loyer_mensuel, 0, ',', ' ') . " XOF\n";
        $message .= "Retard : {$joursRetard} jours\n";
        $message .= "Unité : {$contrat->unite->numero} - {$contrat->unite->propriete->nom}\n\n";
        $message .= "Veuillez régulariser votre situation dans les plus brefs délais.\n\n";
        $message .= "Merci de votre compréhension.\n";
        $message .= "---\n";
        $message .= "Gestiloyer - Gestion Locative";
        
        return $message;
    }

    private function getMoisLibelle($mois)
    {
        $mois_liste = [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
        ];
        return $mois_liste[$mois] ?? 'Inconnu';
    }

    private function sendSMS($telephone, $message)
    {
        // Simulation d'envoi de SMS
        Log::info('SMS envoyé', [
            'telephone' => $telephone,
            'message' => $message
        ]);
        return true;
    }

    private function sendEmail($email, $message, $contrat = null, $sujet = null)
    {
        // Si pas de sujet, créer un sujet par défaut
        if (!$sujet) {
            $sujet = 'Notification Gestiloyer';
        }

        // Simulation d'envoi d'email
        Log::info('Email envoyé', [
            'email' => $email,
            'sujet' => $sujet,
            'message' => $message
        ]);
        
        return true;
    }
}
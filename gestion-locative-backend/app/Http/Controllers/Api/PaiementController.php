<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Paiement;
use App\Models\Contrat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class PaiementController extends Controller
{
    // 4.1 LISTE DES PAIEMENTS
    public function index(Request $request)
    {
        $user = $request->user();
        
        $paiements = Paiement::with([
            'locataire',
            'contrat',
            'contrat.unite',
            'contrat.unite.propriete',
            'validePar'
        ])
        ->when($user->profile_type === 'locataire', function($query) use ($user) {
            return $query->where('locataire_id', $user->profile_id);
        })
        ->when($user->profile_type === 'proprietaire', function($query) use ($user) {
            return $query->whereHas('contrat.unite.propriete', function($q) use ($user) {
                $q->where('proprietaire_id', $user->profile_id);
            });
        })
        ->when($user->profile_type === 'gerant', function($query) use ($user) {
            return $query->whereHas('contrat.unite.propriete', function($q) use ($user) {
                $q->where('gerant_id', $user->profile_id);
            });
        })
        ->orderBy('created_at', 'desc')
        ->get();

        return response()->json([
            'success' => true,
            'data' => $paiements
        ]);
    }

    // 4.2 CRÉER UN PAIEMENT
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'contrat_id' => 'required|exists:contrats,id',
            'montant' => 'required|numeric|min:0',
            'mode_paiement' => 'required|in:especes,mobile_money,virement,cheque,autre',
            'periode_mois' => 'required|integer|between:1,12',
            'periode_annee' => 'required|integer|min:2020',
            'reference' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $contrat = Contrat::with(['locataire', 'unite'])->find($request->contrat_id);

            if ($contrat->statut !== 'actif') {
                return response()->json([
                    'success' => false,
                    'message' => 'Le contrat n\'est pas actif'
                ], 400);
            }

            // Calcul du solde
            $totalPayeMois = Paiement::where('contrat_id', $contrat->id)
                ->where('periode_mois', $request->periode_mois)
                ->where('periode_annee', $request->periode_annee)
                ->sum('montant');

            $resteAPayer = max(0, $contrat->loyer_mensuel - ($totalPayeMois + $request->montant));

            $statut = 'complet';
            if ($resteAPayer > 0) {
                $statut = 'partiel';
            } elseif ($request->montant > $contrat->loyer_mensuel) {
                $statut = 'avance';
            }

            // Génération du numéro de reçu
            $numeroRecu = 'REC-' . date('Y') . '-' . str_pad(
                Paiement::whereYear('created_at', date('Y'))->count() + 1,
                4,
                '0',
                STR_PAD_LEFT
            );

            $paiement = Paiement::create([
                'contrat_id' => $contrat->id,
                'locataire_id' => $contrat->locataire_id,
                'montant' => $request->montant,
                'devise' => 'XOF',
                'periode_mois' => $request->periode_mois,
                'periode_annee' => $request->periode_annee,
                'mode_paiement' => $request->mode_paiement,
                'reference' => $request->reference,
                'statut' => $statut,
                'reste_a_payer' => $resteAPayer,
                'date_paiement' => now(),
                'numero_recu' => $numeroRecu,
                'notes' => $request->notes,
                'valide_par' => $request->user()->id,
                'is_active' => true
            ]);

            // Générer le reçu PDF
            $this->genererReçu($paiement);

            Log::info('Paiement enregistré', [
                'paiement_id' => $paiement->id,
                'contrat_id' => $contrat->id,
                'montant' => $request->montant
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Paiement enregistré avec succès',
                'data' => $paiement->load(['locataire', 'contrat', 'validePar'])
            ], 201);

        } catch (\Exception $e) {
            Log::error('Erreur enregistrement paiement: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'enregistrement du paiement',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // 4.3 DÉTAIL D'UN PAIEMENT
    public function show($id)
    {
        $paiement = Paiement::with([
            'locataire',
            'contrat',
            'contrat.unite',
            'contrat.unite.propriete',
            'validePar'
        ])->find($id);

        if (!$paiement) {
            return response()->json([
                'success' => false,
                'message' => 'Paiement non trouvé'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $paiement
        ]);
    }

    // 4.4 VALIDER UN PAIEMENT
    public function valider($id, Request $request)
    {
        $paiement = Paiement::find($id);

        if (!$paiement) {
            return response()->json([
                'success' => false,
                'message' => 'Paiement non trouvé'
            ], 404);
        }

        if ($paiement->statut === 'complet') {
            return response()->json([
                'success' => false,
                'message' => 'Ce paiement est déjà validé'
            ], 400);
        }

        $paiement->update([
            'statut' => 'complet',
            'reste_a_payer' => 0,
            'valide_par' => $request->user()->id
        ]);

        // Regénérer le reçu avec le nouveau statut
        $this->genererReçu($paiement);

        Log::info('Paiement validé', [
            'paiement_id' => $paiement->id,
            'validateur' => $request->user()->id
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Paiement validé avec succès',
            'data' => $paiement
        ]);
    }

    // 4.5 ANNULER UN PAIEMENT
    public function annuler($id, Request $request)
    {
        $paiement = Paiement::find($id);

        if (!$paiement) {
            return response()->json([
                'success' => false,
                'message' => 'Paiement non trouvé'
            ], 404);
        }

        if ($paiement->statut === 'annule') {
            return response()->json([
                'success' => false,
                'message' => 'Ce paiement est déjà annulé'
            ], 400);
        }

        $paiement->update([
            'statut' => 'annule',
            'is_active' => false,
            'notes' => ($paiement->notes ? $paiement->notes . ' | ' : '') . 'Annulé le ' . now()
        ]);

        Log::info('Paiement annulé', [
            'paiement_id' => $paiement->id,
            'annulateur' => $request->user()->id
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Paiement annulé avec succès',
            'data' => $paiement
        ]);
    }

    // 4.6 GÉNÉRER LE REÇU PDF
    public function genererReçu($paiement)
    {
        // Vérifier le répertoire
        if (!Storage::disk('public')->exists('recus')) {
            Storage::disk('public')->makeDirectory('recus');
        }

        // Formatage des données
        $data = [
            'paiement' => $paiement,
            'locataire' => $paiement->locataire,
            'contrat' => $paiement->contrat,
            'unite' => $paiement->contrat->unite,
            'propriete' => $paiement->contrat->unite->propriete,
            'date' => now()->format('d/m/Y'),
            'mois' => $paiement->periode_mois . '/' . $paiement->periode_annee,
            'montant_lettres' => $this->nombreEnLettres($paiement->montant),
            'numero_recu' => $paiement->numero_recu
        ];

        // Générer le PDF
        $pdf = Pdf::loadView('pdfs.recu', $data);
        
        // Sauvegarder
        $filename = 'recu-' . $paiement->numero_recu . '.pdf';
        $path = 'recus/' . $filename;
        Storage::disk('public')->put($path, $pdf->output());
        
        // Mettre à jour le champ
        $paiement->update(['recu_pdf' => $path]);

        return $path;
    }

    // 4.7 TÉLÉCHARGER LE REÇU
    public function downloadRecu($id)
    {
        $paiement = Paiement::find($id);

        if (!$paiement) {
            return response()->json([
                'success' => false,
                'message' => 'Paiement non trouvé'
            ], 404);
        }

        if (!$paiement->recu_pdf || !Storage::disk('public')->exists($paiement->recu_pdf)) {
            // Regénérer le PDF s'il n'existe pas
            $this->genererReçu($paiement);
        }

        $path = storage_path('app/public/' . $paiement->recu_pdf);
        
        return response()->download($path, 'recu-' . $paiement->numero_recu . '.pdf');
    }

    // 4.8 HISTORIQUE DES PAIEMENTS
    public function historique(Request $request)
    {
        $user = $request->user();
        
        $validator = Validator::make($request->all(), [
            'mois' => 'nullable|integer|between:1,12',
            'annee' => 'nullable|integer|min:2020',
            'locataire_id' => 'nullable|exists:locataires,id',
            'contrat_id' => 'nullable|exists:contrats,id',
            'statut' => 'nullable|in:complet,partiel,avance,annule'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $query = Paiement::with([
            'locataire',
            'contrat',
            'contrat.unite',
            'contrat.unite.propriete'
        ]);

        // Filtres
        if ($request->has('mois') && $request->has('annee')) {
            $query->where('periode_mois', $request->mois)
                  ->where('periode_annee', $request->annee);
        }

        if ($request->has('locataire_id')) {
            $query->where('locataire_id', $request->locataire_id);
        }

        if ($request->has('contrat_id')) {
            $query->where('contrat_id', $request->contrat_id);
        }

        if ($request->has('statut')) {
            $query->where('statut', $request->statut);
        }

        // Permissions
        if ($user->profile_type === 'locataire') {
            $query->where('locataire_id', $user->profile_id);
        } elseif ($user->profile_type === 'proprietaire') {
            $query->whereHas('contrat.unite.propriete', function($q) use ($user) {
                $q->where('proprietaire_id', $user->profile_id);
            });
        } elseif ($user->profile_type === 'gerant') {
            $query->whereHas('contrat.unite.propriete', function($q) use ($user) {
                $q->where('gerant_id', $user->profile_id);
            });
        }

        $paiements = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $paiements
        ]);
    }

    // 4.9 STATISTIQUES DES PAIEMENTS
    public function stats(Request $request)
    {
        $user = $request->user();

        $query = Paiement::query();

        if ($user->profile_type === 'locataire') {
            $query->where('locataire_id', $user->profile_id);
        } elseif ($user->profile_type === 'proprietaire') {
            $query->whereHas('contrat.unite.propriete', function($q) use ($user) {
                $q->where('proprietaire_id', $user->profile_id);
            });
        }

        $stats = [
            'total_global' => $query->sum('montant'),
            'total_mois' => $query->whereMonth('date_paiement', now()->month)
                ->whereYear('date_paiement', now()->year)
                ->sum('montant'),
            'total_annee' => $query->whereYear('date_paiement', now()->year)
                ->sum('montant'),
            'nb_paiements' => $query->count(),
            'moyenne' => $query->avg('montant') ?? 0,
            'par_mode' => $query->select('mode_paiement', 
                    \DB::raw('count(*) as total'),
                    \DB::raw('sum(montant) as montant_total')
                )
                ->groupBy('mode_paiement')
                ->get()
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    // 4.10 CONVERTIR UN NOMBRE EN LETTRES
    private function nombreEnLettres($nombre)
    {
        $unites = ['', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf'];
        $dizaines = ['', 'dix', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante', 'soixante-dix', 'quatre-vingt', 'quatre-vingt-dix'];
        
        if ($nombre == 0) return 'zéro';
        
        $nombre = intval($nombre);
        $mille = intval($nombre / 1000);
        $reste = $nombre % 1000;
        
        $lettres = '';
        
        if ($mille > 0) {
            if ($mille > 1) {
                $lettres .= $this->nombreEnLettres($mille) . ' mille ';
            } else {
                $lettres .= 'mille ';
            }
        }
        
        $centaine = intval($reste / 100);
        $reste = $reste % 100;
        
        if ($centaine > 0) {
            if ($centaine > 1) {
                $lettres .= $unites[$centaine] . ' cents ';
            } else {
                $lettres .= 'cent ';
            }
        }
        
        if ($reste > 0) {
            if ($reste < 10) {
                $lettres .= $unites[$reste];
            } elseif ($reste < 70) {
                $diz = intval($reste / 10);
                $unit = $reste % 10;
                $lettres .= $dizaines[$diz];
                if ($unit > 0) {
                    $lettres .= ($diz > 1 ? '-' : '') . $unites[$unit];
                }
            } elseif ($reste < 80) {
                $lettres .= 'soixante';
                if ($reste > 70) {
                    $lettres .= '-' . $unites[$reste - 60];
                }
            } elseif ($reste < 90) {
                $lettres .= 'quatre-vingt';
                if ($reste > 80) {
                    $lettres .= '-' . $unites[$reste - 80];
                }
            } else {
                $lettres .= 'quatre-vingt-dix';
                if ($reste > 90) {
                    $lettres .= '-' . $unites[$reste - 90];
                }
            }
        }
        
        return trim($lettres);
    }
}
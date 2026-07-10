<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Paiement;
use App\Models\Contrat;
use App\Models\Locataire;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PaiementController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        
        $paiements = Paiement::with(['locataire', 'contrat', 'contrat.unite', 'validePar'])
            ->when($user->profile_type === 'locataire', function($query) use ($user) {
                return $query->where('locataire_id', $user->profile_id);
            })
            ->when($user->profile_type === 'gerant', function($query) {
                // Les gérants voient tous les paiements
                return $query;
            })
            ->when($user->profile_type === 'proprietaire', function($query) use ($user) {
                return $query->whereHas('contrat.unite.propriete', function($q) use ($user) {
                    $q->where('proprietaire_id', $user->profile_id);
                });
            })
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $paiements
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'contrat_id' => 'required|exists:contrats,id',
            'montant' => 'required|numeric|min:0',
            'periode_mois' => 'required|integer|between:1,12',
            'periode_annee' => 'required|integer|min:2020',
            'mode_paiement' => 'required|in:especes,mobile_money,virement,cheque,autre',
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
            
            if (!$contrat) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contrat non trouvé'
                ], 404);
            }

            if ($contrat->statut !== 'actif') {
                return response()->json([
                    'success' => false,
                    'message' => 'Le contrat n\'est pas actif'
                ], 400);
            }

            // Calcul du solde
            $paiementsMois = Paiement::where('contrat_id', $contrat->id)
                ->where('periode_mois', $request->periode_mois)
                ->where('periode_annee', $request->periode_annee)
                ->sum('montant');
            
            $montantTotal = $contrat->loyer_mensuel;
            $resteAPayer = max(0, $montantTotal - $paiementsMois - $request->montant);

            $statut = 'complet';
            if ($resteAPayer > 0) {
                $statut = 'partiel';
            } elseif ($request->montant > $montantTotal) {
                $statut = 'avance';
            }

            // Génération du numéro de reçu
            $numeroRecu = 'REC-' . date('Y') . '-' . Str::upper(Str::random(8));

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
            ]);

            // Génération du QR Code
            $qrData = [
                'recu' => $numeroRecu,
                'montant' => $request->montant,
                'mois' => $request->periode_mois,
                'annee' => $request->periode_annee,
                'locataire' => $contrat->locataire->nom,
                'unite' => $contrat->unite->numero,
            ];
            
            $paiement->update([
                'qr_code_data' => json_encode($qrData)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Paiement enregistré avec succès',
                'data' => $paiement->load('locataire', 'contrat', 'validePar')
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'enregistrement du paiement',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        $paiement = Paiement::with(['locataire', 'contrat', 'contrat.unite', 'validePar'])
            ->find($id);

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

    public function getStats(Request $request)
    {
        $user = $request->user();
        
        $query = Paiement::query();
        
        // Filtrer par type d'utilisateur
        if ($user->profile_type === 'locataire') {
            $query->where('locataire_id', $user->profile_id);
        } elseif ($user->profile_type === 'proprietaire') {
            $query->whereHas('contrat.unite.propriete', function($q) use ($user) {
                $q->where('proprietaire_id', $user->profile_id);
            });
        }

        // Statistiques du mois en cours
        $month = now()->month;
        $year = now()->year;
        
        $totalMois = $query->where('periode_mois', $month)
            ->where('periode_annee', $year)
            ->sum('montant');
            
        $totalGlobal = $query->sum('montant');
        
        $impayes = $query->where('reste_a_payer', '>', 0)
            ->where('periode_mois', $month)
            ->where('periode_annee', $year)
            ->sum('reste_a_payer');

        $recouvrement = $totalGlobal > 0 ? round(($totalMois / $totalGlobal) * 100, 2) : 0;

        // Statistiques par mode de paiement
        $modes = $query->select('mode_paiement')
            ->selectRaw('SUM(montant) as total')
            ->where('periode_mois', $month)
            ->where('periode_annee', $year)
            ->groupBy('mode_paiement')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_mois' => $totalMois,
                'total_global' => $totalGlobal,
                'impayes' => $impayes,
                'taux_recouvrement' => $recouvrement,
                'modes_paiement' => $modes,
                'periode' => [
                    'mois' => $month,
                    'annee' => $year
                ]
            ]
        ]);
    }
}
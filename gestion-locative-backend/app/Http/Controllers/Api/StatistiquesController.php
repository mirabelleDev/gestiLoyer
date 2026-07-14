<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Propriete;
use App\Models\UniteLocative;
use App\Models\Contrat;
use App\Models\Paiement;
use App\Models\Locataire;
use App\Models\Demande;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatistiquesController extends Controller
{
    // 6.1 STATISTIQUES GLOBALES
    public function index(Request $request)
    {
        $user = $request->user();
        
        $stats = [
            'proprietes' => $this->getProprietesStats($user),
            'unites' => $this->getUnitesStats($user),
            'locataires' => $this->getLocatairesStats($user),
            'contrats' => $this->getContratsStats($user),
            'paiements' => $this->getPaiementsStats($user),
            'demandes' => $this->getDemandesStats($user),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    // 6.2 STATISTIQUES DES PROPRIÉTÉS
    private function getProprietesStats($user)
    {
        $query = Propriete::query();

        if ($user->profile_type === 'proprietaire') {
            $query->where('proprietaire_id', $user->profile_id);
        } elseif ($user->profile_type === 'gerant') {
            $query->where('gerant_id', $user->profile_id);
        }

        $total = $query->count();
        $actives = $query->where('is_active', true)->count();
        
        // Statistiques des unités
        $unites = UniteLocative::whereIn('propriete_id', $query->pluck('id'));
        $totalUnites = $unites->count();
        $unitesOccupees = $unites->where('statut', 'occupe')->count();

        return [
            'total' => $total,
            'actives' => $actives,
            'total_unites' => $totalUnites,
            'unites_occupees' => $unitesOccupees,
            'taux_occupation' => $totalUnites > 0 ? round(($unitesOccupees / $totalUnites) * 100, 2) : 0
        ];
    }

    // 6.3 STATISTIQUES DES UNITÉS
    private function getUnitesStats($user)
    {
        $query = UniteLocative::query();

        if ($user->profile_type === 'proprietaire') {
            $query->whereHas('propriete', function($q) use ($user) {
                $q->where('proprietaire_id', $user->profile_id);
            });
        } elseif ($user->profile_type === 'gerant') {
            $query->whereHas('propriete', function($q) use ($user) {
                $q->where('gerant_id', $user->profile_id);
            });
        }

        return [
            'total' => $query->count(),
            'disponibles' => $query->where('statut', 'disponible')->count(),
            'occupees' => $query->where('statut', 'occupe')->count(),
            'travaux' => $query->where('statut', 'travaux')->count(),
            'reserves' => $query->where('statut', 'reserve')->count(),
            'par_type' => $query->select('type', DB::raw('count(*) as total'))
                ->groupBy('type')
                ->get()
                ->pluck('total', 'type')
                ->toArray()
        ];
    }

    // 6.4 STATISTIQUES DES LOCATAIRES
    private function getLocatairesStats($user)
    {
        $query = Locataire::query();

        if ($user->profile_type === 'proprietaire') {
            $query->whereHas('contrats.unite.propriete', function($q) use ($user) {
                $q->where('proprietaire_id', $user->profile_id);
            });
        } elseif ($user->profile_type === 'gerant') {
            $query->whereHas('contrats.unite.propriete', function($q) use ($user) {
                $q->where('gerant_id', $user->profile_id);
            });
        } elseif ($user->profile_type === 'locataire') {
            $query->where('id', $user->profile_id);
        }

        return [
            'total' => $query->count(),
            'actifs' => $query->whereHas('contrats', function($q) {
                $q->where('statut', 'actif');
            })->count(),
            'nouveaux_mois' => $query->whereMonth('created_at', now()->month)->count(),
            'nouveaux_annee' => $query->whereYear('created_at', now()->year)->count()
        ];
    }

    // 6.5 STATISTIQUES DES CONTRATS
    private function getContratsStats($user)
    {
        $query = Contrat::query();

        if ($user->profile_type === 'proprietaire') {
            $query->whereHas('unite.propriete', function($q) use ($user) {
                $q->where('proprietaire_id', $user->profile_id);
            });
        } elseif ($user->profile_type === 'gerant') {
            $query->whereHas('unite.propriete', function($q) use ($user) {
                $q->where('gerant_id', $user->profile_id);
            });
        } elseif ($user->profile_type === 'locataire') {
            $query->where('locataire_id', $user->profile_id);
        }

        $expirantDans30Jours = $query->where('date_fin', '<=', now()->addDays(30))
            ->where('date_fin', '>=', now())
            ->count();

        return [
            'total' => $query->count(),
            'actifs' => $query->where('statut', 'actif')->count(),
            'suspendus' => $query->where('statut', 'suspendu')->count(),
            'resilies' => $query->where('statut', 'resilie')->count(),
            'termines' => $query->where('statut', 'termine')->count(),
            'expirant_dans_30_jours' => $expirantDans30Jours,
            'duree_moyenne' => round($query->avg('duree_mois') ?? 0, 2)
        ];
    }

    // 6.6 STATISTIQUES DES PAIEMENTS
    private function getPaiementsStats($user)
    {
        $query = Paiement::query();

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

        $moisActuel = now()->month;
        $anneeActuel = now()->year;

        return [
            'total_mois' => $query->where('periode_mois', $moisActuel)
                ->where('periode_annee', $anneeActuel)
                ->sum('montant'),
            'total_global' => $query->sum('montant'),
            'impayes' => $this->getImpayes($user),
            'moyenne_mensuelle' => round($query->avg('montant') ?? 0, 2),
            'par_mode' => $query->select('mode_paiement', 
                    DB::raw('count(*) as total'),
                    DB::raw('sum(montant) as montant_total')
                )
                ->where('periode_mois', $moisActuel)
                ->where('periode_annee', $anneeActuel)
                ->groupBy('mode_paiement')
                ->get()
                ->mapWithKeys(function($item) {
                    return [
                        $item->mode_paiement => [
                            'total' => $item->total,
                            'montant_total' => $item->montant_total
                        ]
                    ];
                })
                ->toArray(),
            'taux_recouvrement' => $this->calculerTauxRecouvrement($user, $moisActuel, $anneeActuel)
        ];
    }

    // 6.7 CALCUL DES IMPAYÉS
    private function getImpayes($user)
    {
        $moisActuel = now()->month;
        $anneeActuel = now()->year;

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

        $totalImpayes = 0;

        foreach ($contrats as $contrat) {
            $paye = Paiement::where('contrat_id', $contrat->id)
                ->where('periode_mois', $moisActuel)
                ->where('periode_annee', $anneeActuel)
                ->sum('montant');

            if ($paye < $contrat->loyer_mensuel) {
                $totalImpayes += ($contrat->loyer_mensuel - $paye);
            }
        }

        return $totalImpayes;
    }

    // 6.8 CALCUL DU TAUX DE RECOUVREMENT
    private function calculerTauxRecouvrement($user, $mois, $annee)
    {
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

        $totalAttendu = $contrats->sum('loyer_mensuel');
        
        if ($totalAttendu == 0) return 0;

        $totalPaye = Paiement::whereIn('contrat_id', $contrats->pluck('id'))
            ->where('periode_mois', $mois)
            ->where('periode_annee', $annee)
            ->sum('montant');

        return round(($totalPaye / $totalAttendu) * 100, 2);
    }

    // 6.9 STATISTIQUES DES DEMANDES
    private function getDemandesStats($user)
    {
        $query = Demande::query();

        if ($user->profile_type === 'locataire') {
            $query->where('locataire_id', $user->profile_id);
        } elseif ($user->profile_type === 'proprietaire') {
            $query->whereHas('unite.propriete', function($q) use ($user) {
                $q->where('proprietaire_id', $user->profile_id);
            });
        } elseif ($user->profile_type === 'gerant') {
            $query->whereHas('unite.propriete', function($q) use ($user) {
                $q->where('gerant_id', $user->profile_id);
            });
        }

        return [
            'total' => $query->count(),
            'en_attente' => $query->where('statut', 'en_attente')->count(),
            'en_cours' => $query->where('statut', 'en_cours')->count(),
            'resolues' => $query->where('statut', 'resolu')->count(),
            'rejetees' => $query->where('statut', 'rejete')->count(),
            'par_type' => $query->select('type', DB::raw('count(*) as total'))
                ->groupBy('type')
                ->get()
                ->pluck('total', 'type')
                ->toArray()
        ];
    }

    // 6.10 RAPPORT MENSUEL
    public function rapportMensuel(Request $request)
    {
        $mois = $request->get('mois', now()->month);
        $annee = $request->get('annee', now()->year);
        $user = $request->user();

        // Récupérer les contrats actifs
        $contratsQuery = Contrat::where('statut', 'actif');
        
        if ($user->profile_type === 'proprietaire') {
            $contratsQuery->whereHas('unite.propriete', function($q) use ($user) {
                $q->where('proprietaire_id', $user->profile_id);
            });
        } elseif ($user->profile_type === 'gerant') {
            $contratsQuery->whereHas('unite.propriete', function($q) use ($user) {
                $q->where('gerant_id', $user->profile_id);
            });
        }

        $contrats = $contratsQuery->get();
        $contratIds = $contrats->pluck('id');

        // Paiements du mois
        $paiements = Paiement::whereIn('contrat_id', $contratIds)
            ->where('periode_mois', $mois)
            ->where('periode_annee', $annee)
            ->get();

        $totalPaye = $paiements->sum('montant');
        $totalAttendu = $contrats->sum('loyer_mensuel');
        $tauxRecouvrement = $totalAttendu > 0 ? round(($totalPaye / $totalAttendu) * 100, 2) : 0;

        // Graphiques
        $graphiques = [
            'revenus_mensuels' => $this->getRevenusMensuels($user, 12),
            'impayes_mensuels' => $this->getImpayesMensuels($user, 12),
            'occupation' => $this->getOccupationData($user),
            'modes_paiement' => $this->getModesPaiementData($user, $mois, $annee)
        ];

        $rapport = [
            'periode' => [
                'mois' => $mois,
                'annee' => $annee,
                'libelle' => $this->getMoisLibelle($mois) . ' ' . $annee
            ],
            'resume' => [
                'total_attendu' => $totalAttendu,
                'total_encaisse' => $totalPaye,
                'taux_recouvrement' => $tauxRecouvrement,
                'nombre_contrats' => $contrats->count(),
                'nombre_paiements' => $paiements->count(),
                'moyenne_paiement' => $paiements->count() > 0 ? round($totalPaye / $paiements->count(), 2) : 0,
                'impayes' => $totalAttendu - $totalPaye
            ],
            'details_paiements' => $paiements->map(function($paiement) {
                return [
                    'id' => $paiement->id,
                    'locataire' => $paiement->locataire->full_name,
                    'unite' => $paiement->contrat->unite->numero,
                    'montant' => $paiement->montant,
                    'mode' => $paiement->mode_paiement,
                    'date' => $paiement->date_paiement->format('d/m/Y'),
                    'statut' => $paiement->statut
                ];
            }),
            'contrats_sans_paiement' => $contrats->filter(function($contrat) use ($mois, $annee) {
                return !$contrat->paiements()
                    ->where('periode_mois', $mois)
                    ->where('periode_annee', $annee)
                    ->exists();
            })->map(function($contrat) {
                return [
                    'locataire' => $contrat->locataire->full_name,
                    'unite' => $contrat->unite->numero,
                    'montant' => $contrat->loyer_mensuel,
                    'contact' => $contrat->locataire->telephone,
                    'email' => $contrat->locataire->email
                ];
            })->values(),
            'graphiques' => $graphiques
        ];

        return response()->json([
            'success' => true,
            'data' => $rapport
        ]);
    }

    // 6.11 RÉCUPÉRER LES PROPRIÉTÉS AUTORISÉES
    public function proprietesAutorisees(Request $request)
    {
        $user = $request->user();
        
        $proprietes = Propriete::with(['unites', 'unites.contratActif'])
            ->when($user->profile_type === 'proprietaire', function($query) use ($user) {
                return $query->where('proprietaire_id', $user->profile_id);
            })
            ->when($user->profile_type === 'gerant', function($query) use ($user) {
                return $query->where('gerant_id', $user->profile_id);
            })
            ->when($user->profile_type === 'locataire', function($query) use ($user) {
                return $query->whereHas('unites.contrats', function($q) use ($user) {
                    $q->where('locataire_id', $user->profile_id)
                      ->where('statut', 'actif');
                });
            })
            ->get();

        return response()->json([
            'success' => true,
            'data' => $proprietes
        ]);
    }

    // 6.12 MÉTHODES UTILITAIRES POUR LES GRAPHIQUES

    private function getRevenusMensuels($user, $nbMois = 12)
    {
        $data = [];
        
        for ($i = $nbMois - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $mois = $date->month;
            $annee = $date->year;

            $query = Paiement::query();
            
            if ($user->profile_type === 'proprietaire') {
                $query->whereHas('contrat.unite.propriete', function($q) use ($user) {
                    $q->where('proprietaire_id', $user->profile_id);
                });
            } elseif ($user->profile_type === 'gerant') {
                $query->whereHas('contrat.unite.propriete', function($q) use ($user) {
                    $q->where('gerant_id', $user->profile_id);
                });
            } elseif ($user->profile_type === 'locataire') {
                $query->where('locataire_id', $user->profile_id);
            }

            $montant = $query->where('periode_mois', $mois)
                ->where('periode_annee', $annee)
                ->sum('montant');

            $data[] = [
                'mois' => $date->format('M'),
                'annee' => $annee,
                'montant' => $montant
            ];
        }

        return $data;
    }

    private function getImpayesMensuels($user, $nbMois = 12)
    {
        $data = [];
        
        for ($i = $nbMois - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $mois = $date->month;
            $annee = $date->year;

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

            $totalImpayes = 0;
            foreach ($contrats as $contrat) {
                $paye = Paiement::where('contrat_id', $contrat->id)
                    ->where('periode_mois', $mois)
                    ->where('periode_annee', $annee)
                    ->sum('montant');

                if ($paye < $contrat->loyer_mensuel) {
                    $totalImpayes += ($contrat->loyer_mensuel - $paye);
                }
            }

            $data[] = [
                'mois' => $date->format('M'),
                'annee' => $annee,
                'montant' => $totalImpayes
            ];
        }

        return $data;
    }

    private function getOccupationData($user)
    {
        $query = UniteLocative::query();

        if ($user->profile_type === 'proprietaire') {
            $query->whereHas('propriete', function($q) use ($user) {
                $q->where('proprietaire_id', $user->profile_id);
            });
        } elseif ($user->profile_type === 'gerant') {
            $query->whereHas('propriete', function($q) use ($user) {
                $q->where('gerant_id', $user->profile_id);
            });
        }

        return [
            'occupees' => $query->where('statut', 'occupe')->count(),
            'disponibles' => $query->where('statut', 'disponible')->count(),
            'travaux' => $query->where('statut', 'travaux')->count(),
            'reserves' => $query->where('statut', 'reserve')->count()
        ];
    }

    private function getModesPaiementData($user, $mois, $annee)
    {
        $query = Paiement::query();

        if ($user->profile_type === 'proprietaire') {
            $query->whereHas('contrat.unite.propriete', function($q) use ($user) {
                $q->where('proprietaire_id', $user->profile_id);
            });
        } elseif ($user->profile_type === 'gerant') {
            $query->whereHas('contrat.unite.propriete', function($q) use ($user) {
                $q->where('gerant_id', $user->profile_id);
            });
        }

        return $query->select('mode_paiement', DB::raw('count(*) as total'))
            ->where('periode_mois', $mois)
            ->where('periode_annee', $annee)
            ->groupBy('mode_paiement')
            ->get()
            ->pluck('total', 'mode_paiement')
            ->toArray();
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
}
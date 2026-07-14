<!-- resources/views/pdfs/recu.blade.php -->
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reçu de paiement</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            padding: 20px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .header .subtitle {
            font-size: 14px;
            color: #666;
        }
        .info {
            margin-bottom: 20px;
        }
        .info table {
            width: 100%;
        }
        .info td {
            padding: 5px 0;
        }
        .info .label {
            font-weight: bold;
            width: 150px;
        }
        .montant {
            text-align: center;
            font-size: 28px;
            font-weight: bold;
            padding: 20px;
            background: #f5f5f5;
            margin: 20px 0;
        }
        .montant .devise {
            font-size: 18px;
        }
        .footer {
            margin-top: 40px;
            border-top: 1px solid #ddd;
            padding-top: 20px;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
        .signature {
            margin-top: 30px;
            text-align: right;
        }
        .qr-code {
            text-align: center;
            margin: 20px 0;
        }
        .details {
            margin: 20px 0;
        }
        .details table {
            width: 100%;
            border-collapse: collapse;
        }
        .details td, .details th {
            padding: 8px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .details th {
            background: #f5f5f5;
        }
        .statut {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 4px;
            font-weight: bold;
        }
        .statut-complet {
            background: #d4edda;
            color: #155724;
        }
        .statut-partiel {
            background: #fff3cd;
            color: #856404;
        }
        .statut-avance {
            background: #cce5ff;
            color: #004085;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>REÇU DE PAIEMENT</h1>
        <div class="subtitle">N° {{ $numero_recu }}</div>
    </div>

    <div class="info">
        <table>
            <tr>
                <td class="label">Date :</td>
                <td>{{ $date }}</td>
            </tr>
            <tr>
                <td class="label">Période :</td>
                <td>{{ $mois }}</td>
            </tr>
            <tr>
                <td class="label">Locataire :</td>
                <td>{{ $locataire->full_name }}</td>
            </tr>
            <tr>
                <td class="label">Propriété :</td>
                <td>{{ $propriete->nom }}</td>
            </tr>
            <tr>
                <td class="label">Unité :</td>
                <td>{{ $unite->numero }}</td>
            </tr>
            <tr>
                <td class="label">Mode de paiement :</td>
                <td>{{ ucfirst($paiement->mode_paiement) }}</td>
            </tr>
            <tr>
                <td class="label">Statut :</td>
                <td>
                    <span class="statut statut-{{ $paiement->statut }}">
                        {{ ucfirst($paiement->statut) }}
                    </span>
                </td>
            </tr>
        </table>
    </div>

    <div class="montant">
        {{ number_format($paiement->montant, 0, ',', ' ') }} <span class="devise">XOF</span>
    </div>
    <div style="text-align: center; font-size: 14px; color: #666;">
        {{ $montant_lettres }} francs CFA
    </div>

    <div class="details">
        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Montant</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Loyer du mois</td>
                    <td>{{ number_format($contrat->loyer_mensuel, 0, ',', ' ') }} XOF</td>
                </tr>
                @if($paiement->reste_a_payer > 0)
                <tr>
                    <td style="color: #856404;">Reste à payer</td>
                    <td style="color: #856404;">{{ number_format($paiement->reste_a_payer, 0, ',', ' ') }} XOF</td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>

    <div class="qr-code">
        {!! $paiement->qr_code !!}
    </div>

    <div class="signature">
        <p>Cachet et signature du gestionnaire</p>
        <p style="margin-top: 30px;">___________________________</p>
    </div>

    <div class="footer">
        <p>Ce reçu est délivré par Gestiloyer SARL</p>
        <p>Document valable sans signature</p>
    </div>
</body>
</html>
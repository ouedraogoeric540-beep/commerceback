<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Facture de la commande #{{ $order->id }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #333;
            font-size: 14px;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #C1573A;
            padding-bottom: 10px;
        }
        .header h1 {
            color: #C1573A;
            margin: 0;
            font-size: 24px;
        }
        .order-info {
            display: table;
            width: 100%;
            margin-bottom: 30px;
        }
        .order-info-left, .order-info-right {
            display: table-cell;
            width: 50%;
        }
        .order-info-right {
            text-align: right;
        }
        .shop-section {
            margin-bottom: 40px;
            page-break-inside: avoid;
        }
        .shop-header {
            background-color: #FAF7F3;
            padding: 10px;
            border: 1px solid #E5DED5;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .shop-header h3 {
            margin: 0 0 5px 0;
            color: #26221E;
        }
        .shop-header p {
            margin: 2px 0;
            font-size: 12px;
            color: #6B655D;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        table th, table td {
            border: 1px solid #E5DED5;
            padding: 10px;
            text-align: left;
        }
        table th {
            background-color: #FAF7F3;
            color: #6B655D;
            font-size: 12px;
            text-transform: uppercase;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .total-section {
            width: 100%;
            display: table;
            margin-top: 30px;
        }
        .total-box {
            display: table-cell;
            text-align: right;
            padding-top: 20px;
            border-top: 2px solid #26221E;
        }
        .total-box p {
            margin: 5px 0;
            font-size: 16px;
        }
        .total-box .grand-total {
            font-size: 20px;
            font-weight: bold;
            color: #C1573A;
        }
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 12px;
            color: #6B655D;
            border-top: 1px solid #E5DED5;
            padding-top: 20px;
        }
    </style>
</head>
<body>

    <div class="header">
        <!-- Logo de la plateforme / application -->
        <h1 style="font-weight: 900; letter-spacing: -1px;">E-COMMERCE PLATEFORME</h1>
        <h2 style="color: #6B655D; margin-top: 5px; font-size: 18px;">Facture / Reçu de Paiement</h2>
        <p>Référence: #{{ $order->id }}</p>
    </div>

    <div class="order-info">
        <div class="order-info-left">
            <strong>Facturé à :</strong><br>
            {{ $order->first_name }} {{ $order->last_name }}<br>
            {{ $order->email }}
        </div>
        <div class="order-info-right">
            <strong>Détails de la commande :</strong><br>
            Date: {{ $order->created_at->format('d/m/Y H:i') }}<br>
            Méthode de paiement: {{ ucfirst($order->payment_method) }}<br>
            Statut: {{ ucfirst($order->status) }}
        </div>
    </div>

    @foreach($shops as $shopData)
        @php $shop = $shopData['shop']; @endphp
        <div class="shop-section">
            <div class="shop-header">
                <table style="width: 100%; border: none; margin: 0;">
                    <tr>
                        <td style="border: none; padding: 0; width: 70%;">
                            <h3>Boutique : {{ $shop->name }}</h3>
                            <p>
                                @if($shop->address) {{ $shop->address }}, @endif
                                @if($shop->city) {{ $shop->city }} @endif
                                @if($shop->country) - {{ $shop->country }} @endif
                            </p>
                            @if($shop->support_phone) <p>Tél: {{ $shop->support_phone }}</p> @endif
                            @if($shop->support_email) <p>Email: {{ $shop->support_email }}</p> @endif
                            @if($shop->registration_number) <p>SIRET/Reg: {{ $shop->registration_number }}</p> @endif
                            @if($shop->vat_number) <p>TVA: {{ $shop->vat_number }}</p> @endif
                        </td>
                        <td style="border: none; padding: 0; width: 30%; text-align: right; vertical-align: top;">
                            @if($shop->logo)
                                <!-- Utilisation de base64 pour s'assurer que dompdf charge l'image correctement, ou public_path -->
                                <img src="{{ public_path('storage/' . $shop->logo) }}" alt="Logo {{ $shop->name }}" style="max-height: 80px; max-width: 120px; object-fit: contain; border-radius: 4px;">
                            @endif
                        </td>
                    </tr>
                </table>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Produit</th>
                        <th class="text-center">Quantité</th>
                        <th class="text-right">Prix Unitaire</th>
                        <th class="text-right">Sous-total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($shopData['items'] as $item)
                        <tr>
                            <td>{{ $item->product ? $item->product->title : 'Produit inconnu' }}</td>
                            <td class="text-center">{{ $item->quantity }}</td>
                            <td class="text-right">{{ number_format($item->price, 2, ',', ' ') }} FCFA</td>
                            <td class="text-right">{{ number_format($item->price * $item->quantity, 2, ',', ' ') }} FCFA</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach

    <div class="total-section">
        <div class="total-box">
            @if($order->promo_code)
                <p>Code Promo appliqué : {{ $order->promo_code }}</p>
            @endif
            <p class="grand-total">Total payé : {{ number_format($order->total_amount, 2, ',', ' ') }} FCFA</p>
        </div>
    </div>

    <div class="footer">
        Ceci est une preuve d'achat générée automatiquement.<br>
        Merci de votre confiance.
    </div>

</body>
</html>

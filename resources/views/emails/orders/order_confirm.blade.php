<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Confirmation de commande</title>
</head>

<body style="margin:0; padding:0; background-color:#f4f4f4; font-family:Segoe UI, sans-serif;">

    <table width="100%" cellpadding="0" cellspacing="0" style="padding: 30px 0;">
        <tr>
            <td align="center">

                <table width="600" cellpadding="0" cellspacing="0"
                    style="background:#ffffff; border-radius:10px; padding:30px;">

                    <!-- LOGO -->
                    <tr>
                        <td align="center" style="padding-bottom:20px;">
                            <img src="https://backoffice.torahdiffusion.ci/static/media/logo.2de02bb2e7c86204209a.png"
                                width="120">
                        </td>
                    </tr>

                    <!-- TITLE -->
                    <tr>
                        <td style="text-align:center;">
                            <h2 style="color:#2d6cdf;">Shalom {{ $order->nom }},</h2>
                        </td>
                    </tr>

                    <!-- MESSAGE -->
                    <tr>
                        <td style="font-size:16px; line-height:1.6; color:#333;">

                            <p>
                                Nous avons bien reçu votre commande.
                                Votre paiement a été confirmé avec succès.
                            </p>

                            <p>
                                Voici le récapitulatif de votre commande :
                            </p>

                        </td>
                    </tr>

                    <!-- TABLE -->
                    <tr>
                        <td>
                            <table width="100%" cellpadding="8" cellspacing="0"
                                style="border-collapse:collapse; margin-top:15px;">

                                <thead>
                                    <tr style="background:#f0f4ff;">
                                        <th align="left">Produit</th>
                                        <th align="center">Qté</th>
                                        <th align="right">Prix</th>
                                        <th align="right">Total</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    @foreach($order->metadata['cart_details'] ?? [] as $item)
                                        <tr style="border-bottom:1px solid #eee;">
                                            <td>{{ $item['title'] }}</td>
                                            <td align="center">{{ $item['quantity'] }}</td>
                                            <td align="right">{{ number_format($item['price'], 0, ',', ' ') }} FCFA</td>
                                            <td align="right">
                                                {{ number_format($item['price'] * $item['quantity'], 0, ',', ' ') }} FCFA
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>

                            </table>
                        </td>
                    </tr>

                    <!-- TOTAL -->
                    <tr>
                        <td style="text-align:right; padding-top:15px; font-size:16px;">
                            <strong>Total : {{ number_format($transaction->amount, 0, ',', ' ') }} FCFA</strong>
                        </td>
                    </tr>

                    <!-- RESSOURCES -->
                    @if(!empty($resources))
                        <tr>
                            <td style="padding-top:25px;">
                                <h3 style="color:#2d6cdf;">📚 Vos ebooks</h3>

                                @foreach($resources as $res)
                                    <div style="margin-bottom:15px; padding:10px; background:#f9f9f9; border-radius:6px;">
                                        <p style="margin:0 0 8px 0; font-weight:bold;">
                                            {{ $res['title'] }}
                                        </p>

                                        <a href="{{ $res['link'] }}"
                                            style="display:inline-block; padding:8px 14px; background:#2d6cdf; color:#fff; text-decoration:none; border-radius:5px; font-size:14px;">
                                            Accéder au fichier
                                        </a>
                                    </div>
                                @endforeach
                            </td>
                        </tr>
                    @endif

                    @if(empty($resources))
                        <tr>

                            <p style="margin-top:15px;">
                                Vous recevrez vos éléments très bientôt.
                            </p>
                        </tr>
                    @endif

                    <!-- INFO -->
                    <tr>
                        <td style="padding-top:25px; font-size:15px; color:#333;">

                            <p>
                                📞 Vous serez contacté pour la livraison dans un délai de <strong>1 à 3 jours
                                    maximum</strong>.
                            </p>

                            <p>
                                🚚 Les frais de livraison/expédition sont à votre charge et vous seront communiqués lors
                                de l’appel.
                            </p>

                        </td>
                    </tr>

                    <!-- FOOTER -->
                    <tr>
                        <td style="padding-top:30px; text-align:center; font-size:14px; color:#666;">
                            Merci pour votre confiance 🙏<br>
                            {{ config('app.name') }}
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>

</html>
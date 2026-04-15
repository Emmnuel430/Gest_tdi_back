<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Envoi d'ebooks</title>
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
                                alt="Logo" width="120" style="display:block;">
                        </td>
                    </tr>

                    <!-- TITLE -->
                    <tr>
                        <td style="text-align:center;">
                            <h2 style="color:#2d6cdf; margin-bottom:20px;">
                                Merci pour votre commande 🙏
                            </h2>
                        </td>
                    </tr>

                    <!-- MESSAGE -->
                    <tr>
                        <td style="font-size:16px; line-height:1.6; color:#333;">

                            <p>Bonjour <strong>{{ $order->nom }}</strong>,</p>

                            <p>
                                Votre paiement a été confirmé avec succès.
                                Merci pour votre confiance 🙏
                            </p>

                        </td>
                    </tr>

                    <!-- PRODUITS -->
                    @if(!empty($order->metadata['cart_details']))
                        <tr>
                            <td style="padding-top:20px;">
                                <h3 style="color:#2d6cdf;">🛒 Vos articles</h3>

                                <table width="100%" cellpadding="8" cellspacing="0" style="border-collapse:collapse;">
                                    @foreach($order->metadata['cart_details'] as $item)
                                        <tr style="border-bottom:1px solid #eee;">
                                            <td style="font-size:14px; color:#333;">
                                                {{ $item['title'] ?? 'Produit' }}
                                            </td>
                                            <td align="right" style="font-size:14px; color:#666;">
                                                x {{ $item['quantity'] ?? 1 }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </table>
                            </td>
                        </tr>
                    @endif

                    <!-- RESSOURCES -->
                    @if(!empty($resources))
                        <tr>
                            <td style="padding-top:25px;">
                                <h3 style="color:#2d6cdf;">📚 Vos accès</h3>

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
                        <p style="margin-top:15px;">
                            Vous recevrez vos éléments très bientôt.
                        </p>
                    @endif

                    <!-- FOOTER -->
                    <tr>
                        <td style="padding-top:30px; font-size:14px; color:#666; text-align:center;">
                            <p style="margin-bottom:5px;">
                                Si vous avez une question, n'hésitez pas à nous contacter.
                            </p>

                            <p>
                                {{ config('app.name') }} — Tous droits réservés
                            </p>
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>

</html>
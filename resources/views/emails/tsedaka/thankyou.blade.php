<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Merci pour votre don</title>
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
                                alt="Logo" width="120">
                        </td>
                    </tr>

                    <!-- TITLE -->
                    <tr>
                        <td style="text-align:center;">
                            <h2 style="color:#2d6cdf;">
                                Shalom {{ $tsedaka->anonymous ? 'cher Donateur' : $tsedaka->prenom }},
                            </h2>
                        </td>
                    </tr>

                    <!-- CONTENT -->
                    <tr>
                        <td style="font-size:16px; line-height:1.6; color:#333;">

                            <p>
                                Nous vous remercions sincèrement pour votre don de
                                <strong>{{ number_format($tsedaka->montant, 0, ',', ' ') }} FCFA</strong>.
                            </p>

                            <p>
                                Votre générosité contribue directement à soutenir nos actions
                                et à diffuser la lumière autour de nous.
                            </p>

                            <p>
                                Chaque geste compte, et le vôtre a une réelle valeur.
                            </p>

                            @if($tsedaka->message)
                                <p style="margin-top:20px;">
                                    <strong>Votre message :</strong><br>
                                    "{{ $tsedaka->message }}"
                                </p>
                            @endif

                            <p style="margin-top:20px;">
                                Que cette tsedaka vous apporte bénédiction, paix et réussite.
                            </p>

                        </td>
                    </tr>

                    <!-- FOOTER -->
                    <tr>
                        <td style="padding-top:30px; font-size:14px; color:#666; text-align:center;">
                            Shalom,<br>
                            {{ config('app.name') }}
                            <br>Reference : {{ $tsedaka->reference }}
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>

</html>
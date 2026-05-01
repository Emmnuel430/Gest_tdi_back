<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Compte validé</title>
</head>

<body style="margin:0; padding:0; background-color:#f4f4f4; font-family:Segoe UI, sans-serif;">

    <table width="100%" cellpadding="0" cellspacing="0" style="padding: 30px 0;">
        <tr>
            <td align="center">

                <table width="600" cellpadding="0" cellspacing="0"
                    style="background:#ffffff; border-radius:10px; padding:30px;">

                    <tr>
                        <td align="center" style="padding-bottom:20px;">
                            <img src="https://backoffice.torahdiffusion.ci/static/media/logo.2de02bb2e7c86204209a.png"
                                alt="Logo" width="120">
                        </td>
                    </tr>

                    <tr>
                        <td style="text-align:center;">
                            <h2 style="color:#2d6cdf;">
                                Shalom {{ $adherent->prenom }},
                            </h2>
                        </td>
                    </tr>

                    <tr>
                        <td style="font-size:16px; color:#333; line-height:1.6;">

                            <p>
                                Votre compte a été <strong>validé avec succès</strong>.
                            </p>

                            <p>
                                Vous pouvez désormais accéder à votre espace et profiter de votre abonnement.
                            </p>

                            <p>
                                Que cette nouvelle étape soit remplie de bénédictions et de réussite 🙏
                            </p>

                        </td>
                    </tr>

                    <tr>
                        <td style="padding-top:30px; text-align:center; font-size:14px; color:#666;">
                            Shalom,<br>
                            {{ config('app.name') }}
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>

</html>
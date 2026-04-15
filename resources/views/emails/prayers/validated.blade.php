<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Demande de prière reçue</title>
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
                                Shalom {{ $prayer->prenom }},
                            </h2>
                        </td>
                    </tr>

                    <!-- MESSAGE -->
                    <tr>
                        <td style="font-size:16px; line-height:1.6; color:#333;">

                            <p>
                                Nous avons bien reçu votre demande de prière intitulée
                                <strong>"{{ $prayer->objet }}"</strong>.
                            </p>

                            <p>
                                Soyez assuré que celle-ci sera portée avec attention et sincérité.
                                Un rabbin priera pour vous, en élevant votre intention avec foi et bienveillance.
                            </p>

                            <p>
                                Dans ces moments, sachez que vous n’êtes pas seul.
                                Que la paix, la force et la lumière vous accompagnent.
                            </p>

                        </td>
                    </tr>

                    <!-- FOOTER -->
                    <tr>
                        <td style="padding-top:30px; font-size:14px; color:#666; text-align:center;">
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
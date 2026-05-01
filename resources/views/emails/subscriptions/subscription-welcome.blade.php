<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Abonnement activé !</title>
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
                                Shalom {{ $adherent->prenom }},
                            </h2>
                        </td>
                    </tr>

                    <!-- CONTENT -->
                    <tr>
                        <td style="font-size:16px; color:#333; line-height:1.6;">

                            <p>
                                Votre inscription a bien été validée 🎉
                            </p>

                            <p>
                                Vous êtes désormais inscrit au plan :
                                <strong>{{ $plan->name }}</strong>
                            </p>

                            @if($plan->is_student_plan)
                                <p>
                                    Votre formation se déroulera sur <strong>toute l'année</strong> avec des mensualités à
                                    payer ({{ $plan->total_payments }} au total).
                                    Chaque mensualité vous permettra de continuer votre parcours.
                                </p>
                            @else
                                <p>
                                    Votre abonnement est actif et vous donne accès à nos contenus selon votre formule.
                                </p>
                            @endif

                            <p>
                                📅 Début : {{ \Carbon\Carbon::parse($subscription->starts_at)->format('d/m/Y') }}
                            </p>

                            @if($subscription->ends_at)
                                <p>
                                    ⏳ Fin : {{ \Carbon\Carbon::parse($subscription->ends_at)->format('d/m/Y') }}
                                </p>
                            @endif

                            <p>
                                Nous vous souhaitons une excellente expérience parmi nous 🙏
                            </p>

                        </td>
                    </tr>

                    <!-- CTA -->
                    <tr>
                        <td align="center" style="padding-top:20px;">
                            <a href="{{ config('app.app_members_url') }}/adherent/login"
                                style="background:#2d6cdf; color:#fff; padding:12px 20px; border-radius:6px; text-decoration:none;">
                                Accéder à mon espace
                            </a>
                        </td>
                    </tr>

                    <!-- FOOTER -->
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
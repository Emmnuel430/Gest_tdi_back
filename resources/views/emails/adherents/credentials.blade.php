<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8" />
    <title>Identifiants d'accès</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #f4f4f4;
            padding: 30px;
            color: #333;
        }

        .container {
            background: #fff;
            padding: 30px;
            max-width: 600px;
            margin: auto;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        h1 {
            color: #2d6cdf;
            font-size: 24px;
            margin-bottom: 10px;
        }

        .info {
            font-size: 16px;
            margin: 15px 0;
        }

        .credentials {
            background-color: #f1f1f1;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }

        .credentials p {
            margin: 5px 0;
        }

        .button {
            display: inline-block;
            background-color: #2d6cdf;
            color: white;
            padding: 12px 25px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
        }

        .footer {
            margin-top: 30px;
            font-size: 14px;
            color: #666;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Bienvenue {{ $adherent->prenom }},</h1>

        <p class="info">
            Votre demande d’adhésion a été <strong>validée</strong>. Voici vos
            identifiants de connexion à notre plateforme :
        </p>

        <div class="credentials">
            <p><strong>Pseudo :</strong> {{ $adherent->pseudo }}</p>
            <p><strong>Mot de passe :</strong> {{ $passwordClair }}</p>
        </div>

        <p>👉 Veuillez les conserver précieusement.</p>

        <p style="text-align: center; margin-top: 30px;">
            <a href="{{ config('app.members_url') }}" class="button">Accéder à la plateforme</a>
        </p>

        <p class="footer">
        <p>Shalom,<br>{{ config('app.name') }}</p>
        </p>
    </div>
</body>

</html>
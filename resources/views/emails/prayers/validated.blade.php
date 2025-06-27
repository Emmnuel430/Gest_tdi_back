<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Demande de prière validée</title>
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

        .footer {
            margin-top: 30px;
            font-size: 14px;
            color: #666;
        }

        .button {
            background: #2d6cdf;
            color: #fff;
            padding: 12px 25px;
            text-decoration: none;
            border-radius: 5px;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Shalom {{ $prayer->prenom }},</h1>
        <p>Votre demande de prière intitulée <strong>"{{ $prayer->objet }}"</strong> a été <strong>validée</strong>.</p>
        <p>Un membre de notre communauté priera pour votre intention.</p>
        <p style="text-align: center; margin-top: 30px;">
            <a href="{{ config('app.members_url') }}" class="button">Accéder à la plateforme</a>
        </p>
        <p class="footer">Shalom,<br>{{ config('app.name') }}</p>
    </div>
</body>

</html>
Bienvenue {{ $adherent->prenom }},

Votre demande d’adhésion a été validée.

Voici vos identifiants de connexion à notre plateforme :

- Pseudo : {{ $adherent->pseudo }}
- Mot de passe : {{ $passwordClair }}

Veuillez les conserver précieusement.

Accédez à la plateforme : {{ config('app.members_url') }}

Shalom,
{{ config('app.name') }}
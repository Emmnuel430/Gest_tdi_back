Shalom {{ $tsedaka->anonymous ? 'cher Donateur' : $tsedaka->prenom }},

Nous vous remercions pour votre don de {{ number_format($tsedaka->montant, 0, ',', ' ') }} FCFA.

Votre générosité contribue à soutenir nos actions.

Chaque geste compte, et le vôtre a une réelle valeur.

@if($tsedaka->message)
    Votre message :
    "{{ $tsedaka->message }}"
@endif

Que cette tsedaka vous apporte bénédiction et réussite.

Shalom,
{{ config('app.name') }}
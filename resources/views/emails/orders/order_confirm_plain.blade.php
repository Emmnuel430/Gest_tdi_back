Shalom {{ $order->nom }},

Votre commande a bien été reçue et votre paiement a été confirmé.

Récapitulatif :

@foreach($order->metadata['cart_details'] ?? [] as $item)
    - {{ $item['title'] ?? 'Article' }} x{{ $item['quantity'] ?? 1 }} : {{ number_format(((float) ($item['price'] ?? 0)) * ((int) ($item['quantity'] ?? 1)), 0, ',', ' ') }}
    FCFA
@endforeach

Total : {{ number_format((float) ($transaction->amount ?? 0), 0, ',', ' ') }} FCFA

@if(!empty($resources))
Vos ressources numériques :
@foreach($resources as $res)
- {{ $res['title'] }} : {{ $res['link'] }}
@endforeach

@endif
Vous serez contacté pour la livraison dans un délai de 1 à 3 jours maximum.
Les frais de livraison sont à votre charge.

Merci pour votre confiance,
{{ config('app.name') }}
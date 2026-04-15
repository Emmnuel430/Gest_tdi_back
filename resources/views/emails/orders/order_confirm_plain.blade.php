Shalom {{ $order->nom }},

Votre commande a bien été reçue et votre paiement a été confirmé.

Récapitulatif :

@foreach($order->metadata['cart_details'] ?? [] as $item)
    - {{ $item['title'] }} x{{ $item['quantity'] }} : {{ number_format($item['price'] * $item['quantity'], 0, ',', ' ') }}
    FCFA
@endforeach

Total : {{ number_format($order->amount, 0, ',', ' ') }} FCFA

Vous serez contacté pour la livraison dans un délai de 1 à 3 jours maximum.
Les frais de livraison sont à votre charge.

Merci pour votre confiance,
{{ config('app.name') }}
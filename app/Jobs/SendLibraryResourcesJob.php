<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\Subsection;
use App\Mail\OrderConfirm;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendLibraryResourcesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [60, 300, 900];

    public Order $order;
    public ?string $overrideEmail;

    /**
     * Create a new job instance.
     *
     * @param Order $order
     * @param string|null $overrideEmail
     */
    public function __construct(Order $order, ?string $overrideEmail = null)
    {
        $this->order = $order;
        $this->overrideEmail = $overrideEmail;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $recipient = $this->overrideEmail ?: $this->order->email;
        $customFields = $this->order->metadata ?? [];
        $resources = [];

        foreach ($customFields['cart_details'] ?? [] as $item) {
            if (!empty($item['product_id'])) {
                $sub = Subsection::find($item['product_id']);
                if ($sub && $sub->type === 'ressource' && $sub->link) {
                    $resources[] = [
                        'title' => $sub->title,
                        'link' => $sub->link
                    ];
                }
            }
        }

        // Si aucun article n'est une ressource numérique téléchargeable
        if (empty($resources)) {
            $this->order->update([
                'resource_delivery_status' => 'not_applicable',
            ]);
            Log::info("Commande #{$this->order->id} : Aucune ressource numérique à expédier.");
            return;
        }

        $transaction = $this->order->transactions()->latest()->first();

        try {
            Mail::to($recipient)->send(new OrderConfirm($this->order, $resources, $transaction));

            $this->order->update([
                'resource_delivery_status' => 'sent',
                'resource_recipient_email' => $recipient,
                'resource_sent_at' => now(),
                'delivery_attempts' => ($this->order->delivery_attempts ?? 0) + 1,
                'delivery_error' => null,
            ]);

            Log::info("Ressources de la commande #{$this->order->id} envoyées avec succès à {$recipient}.");

        } catch (\Throwable $e) {
            $this->order->update([
                'resource_delivery_status' => 'failed',
                'resource_recipient_email' => $recipient,
                'delivery_attempts' => ($this->order->delivery_attempts ?? 0) + 1,
                'delivery_error' => $e->getMessage(),
            ]);

            Log::error("Échec d'envoi des ressources pour la commande #{$this->order->id} vers {$recipient}: " . $e->getMessage());
            throw $e;
        }
    }
}

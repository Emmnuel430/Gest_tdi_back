<?php

namespace App\Mail;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderConfirm extends Mailable
{
    use Queueable, SerializesModels;

    public $order;
    public $resources;

    public function __construct($order, $resources = [])
    {
        $this->order = $order;
        $this->resources = $resources;
    }

    public function build()
    {
        return $this
            ->subject("Votre commande a été confirmée !")
            ->view('emails.orders.order_confirm')
            ->text('emails.orders.order_confirm_plain');
    }
}

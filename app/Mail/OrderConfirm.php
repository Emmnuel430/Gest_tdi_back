<?php

namespace App\Mail;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderConfirm extends Mailable
{
    use Queueable, SerializesModels;

    public $order;
    public $resources;
    public $transaction;

    public function __construct($order, $resources = [], $transaction)
    {
        $this->order = $order;
        $this->resources = $resources;
        $this->transaction = $transaction;
    }

    public function build()
    {
        return $this
            ->subject("Votre commande a été confirmée !")
            ->view('emails.orders.order_confirm')
            ->text('emails.orders.order_confirm_plain');
    }
}

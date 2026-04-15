<?php

namespace App\Mail;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderResourceSend extends Mailable
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
            ->subject("Voici les ebooks demandés !")
            ->view('emails.orders.order_resource_send');
    }
}

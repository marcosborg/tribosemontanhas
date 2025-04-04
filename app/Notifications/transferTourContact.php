<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class transferTourContact extends Notification
{
    use Queueable;

    private $TransferForm;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($TransferForm)
    {
        $this->TransferForm = $TransferForm;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
                    ->line('Existe um pedido de contacto em Transfer´s / Tour.')
                    ->action('Ir para pedido', url('http://Tribos&Montanhas.pt/admin/admin/transfer-forms'))
                    ->line('Nome: ' . $this->TransferForm->name)
                    ->line('Email: ' . $this->TransferForm->email)
                    ->line('Telefone: ' . $this->TransferForm->phone);
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}

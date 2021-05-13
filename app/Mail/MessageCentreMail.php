<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MessageCentreMail extends Mailable
{
    use Queueable, SerializesModels;

    protected $template;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($template)
    {
        $this->template = $template;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $mail = $this->markdown('emails.messagecentre')
        ->subject($this->template->subject)
        ->with([
            'template' => $this->template,
        ]);
        if(isset($this->template->pdf))
        {
            foreach($this->template->pdf as $pdfView)
            {
                $mail->attachData($pdfView->output(), "invoice.pdf");
            }
        }
        return $mail;
    }
}

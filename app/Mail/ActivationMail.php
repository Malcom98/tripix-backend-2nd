<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class ActivationMail extends Mailable
{
    use Queueable, SerializesModels;
  
    public $details;
    public $subject;
    
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($details)
    {
        $this->details = $details;
        $this->subject = "Tripix - Activation email";
    }
   
    /**
     * Build the message.
     *
     * @return $this
     */
     public function build()
    {
        return $this->view('emails.activationEmail');
    }
}

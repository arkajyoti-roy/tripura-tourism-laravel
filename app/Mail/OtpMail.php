<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public $otp;

    // Create a new message instance.
    public function __construct($otp)
    {
        $this->otp = $otp;
    }

    // Get the message envelope.
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Administration Portal OTP Verification Code',
        );
    }

    // Get the message content definition.
    public function content(): Content
    {
        return new Content(
            htmlString: "
                <div style='font-family: Helvetica, Arial, sans-serif; line-height: 2; padding: 20px; background-color: #fcf9f6;'>
                  <div style='margin: 30px auto; width: 90%; max-width: 500px; padding: 30px; background: #ffffff; border: 1px solid #cfbdaf; border-radius: 8px; box-shadow: 0 4px 12px rgba(88,36,25,0.05);'>
                    <div style='border-bottom: 2px solid #b4212c; padding-bottom: 15px; margin-bottom: 20px;'>
                      <span style='font-size: 1.5em; color: #b4212c; text-decoration: none; font-weight: 700; letter-spacing: -0.5px;'>Tripura Tourism</span>
                    </div>
                    <p style='font-size: 1.1em; color: #282322; margin-top: 0;'>Hi Admin,</p>
                    <p style='color: #555555; font-size: 0.95em; line-height: 1.5;'>Use the following One-Time Password (OTP) to sign in to your Administration Dashboard. This code is valid for 5 minutes.</p>
                    <div style='text-align: center; margin: 30px 0;'>
                      <h2 style='background: #fff7f4; display: inline-block; margin: 0 auto; letter-spacing: 4px; padding: 12px 30px; color: #b4212c; border: 1.5px dashed #cfbdaf; border-radius: 6px; font-size: 2em; font-weight: bold;'>{$this->otp}</h2>
                    </div>
                    <p style='color: #555555; font-size: 0.95em; line-height: 1.5;'>If you did not request this login attempt, please secure your administrative access parameters immediately.</p>
                    <p style='font-size: 0.95em; color: #282322; margin-top: 25px;'>Regards,<br /><strong style='color: #b4212c;'>Tripura Tourism Department</strong></p>
                    <hr style='border: none; border-top: 1px solid #cfbdaf; margin: 25px 0 15px 0;' />
                    <div style='color: #756b67; font-size: 0.8em; line-height: 1.4; text-align: center;'>
                      <p style='margin: 0;'>Tripura Tourism Development Corporation Ltd.</p>
                      <p style='margin: 4px 0 0 0;'>Agartala, West Tripura, India</p>
                    </div>
                  </div>
                </div>
            "
        );
    }

    // Get the attachments for the message.
    // @return array<int, \Illuminate\Mail\Mailables\Attachment>
    public function attachments(): array
    {
        return [];
    }
}

<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VerificationStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $owner;
    public string $status;

    /**
     * @param  string  $status  'approved' or 'rejected'
     */
    public function __construct(User $owner, string $status)
    {
        $this->owner  = $owner;
        $this->status = $status;
    }

    public function build()
    {
        $approved = $this->status === 'approved';
        $subject  = $approved
            ? 'Your Picklepass account has been verified'
            : 'Your Picklepass verification was not approved';

        return $this->subject($subject)
            ->view('emails.verification-status')
            ->with([
                'name'     => $this->owner->name,
                'approved' => $approved,
            ]);
    }
}

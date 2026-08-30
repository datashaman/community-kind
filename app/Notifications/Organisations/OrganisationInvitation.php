<?php

namespace App\Notifications\Organisations;

use App\Models\OrganisationInvitation as OrganisationInvitationModel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrganisationInvitation extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public OrganisationInvitationModel $invitation,
        public string $token,
        public bool $existingUser,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $organisation = $this->invitation->organisation;
        $inviter = $this->invitation->inviter;

        $route = $this->existingUser ? 'login' : 'register';
        $action = $this->existingUser ? __('Log in') : __('Create account');

        return (new MailMessage)
            ->subject(__("You've been invited to join :organisationName", ['organisationName' => $organisation->name]))
            ->line(__(':inviterName has invited you to join the :organisationName organisation.', [
                'inviterName' => $inviter->name,
                'organisationName' => $organisation->name,
            ]))
            ->when(
                $this->invitation->offers_ownership,
                fn (MailMessage $message) => $message->line(__('This invitation also asks you to accept Organisation Owner responsibility.')),
            )
            ->line($this->existingUser
                ? __('Log in with this invitation to accept it, or decline it from your dashboard.')
                : __('Create your account with this invitation, then verify your email address to accept it.'))
            ->action(
                $action,
                route($route, ['invitation' => $this->token]),
            );
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'invitation_id' => $this->invitation->id,
            'organisation_id' => $this->invitation->organisation_id,
            'organisation_name' => $this->invitation->organisation->name,
            'role' => $this->invitation->role->value,
        ];
    }
}

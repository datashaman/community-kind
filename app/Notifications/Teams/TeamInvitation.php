<?php

namespace App\Notifications\Teams;

use App\Models\TeamInvitation as TeamInvitationModel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeamInvitation extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public TeamInvitationModel $invitation,
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
        $team = $this->invitation->team;
        $inviter = $this->invitation->inviter;

        $route = $this->existingUser ? 'login' : 'register';
        $action = $this->existingUser ? __('Log in') : __('Create account');

        return (new MailMessage)
            ->subject(__("You've been invited to join :teamName", ['teamName' => $team->name]))
            ->line(__(':inviterName has invited you to join the :teamName team.', [
                'inviterName' => $inviter->name,
                'teamName' => $team->name,
            ]))
            ->line($this->existingUser
                ? __('Log in and visit your dashboard to accept or decline this invitation.')
                : __('Create your account with this invitation, then verify your email address to continue.'))
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
            'team_id' => $this->invitation->team_id,
            'team_name' => $this->invitation->team->name,
            'role' => $this->invitation->role->value,
        ];
    }
}

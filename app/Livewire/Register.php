<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Concerns\SharesContact;
use App\Telegram\TelegramAuth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class Register extends Component
{
    use SharesContact;

    /** Receive the phone the Telegram contact prompt returned and finish sign-up. */
    public function savePhone(string $phone): void
    {
        if ($this->persistPhone($phone) === null) {
            $this->dispatch('toast', message: __("That number didn't look right — please try sharing your contact again."), type: 'error');

            return;
        }

        $this->dispatch('haptic', type: 'notification', style: 'success');
        $this->redirect(route('home'), navigate: true);
    }

    /**
     * Polled fallback: Telegram also delivers the shared contact to the bot
     * webhook, which saves the phone server-side. When that lands, move on.
     */
    public function checkRegistered(): void
    {
        $player = app(TelegramAuth::class)->player();
        if ($player !== null && $player->phone) {
            $this->redirect(route('home'), navigate: true);
        }
    }

    public function render()
    {
        $auth = app(TelegramAuth::class);

        // Already registered (or not a Telegram user) → no reason to be here.
        $player = $auth->player();
        if ($player !== null && $player->phone) {
            $this->redirect(route('home'), navigate: true);
        }

        return view('livewire.register', [
            'name' => $auth->name(),
        ]);
    }
}

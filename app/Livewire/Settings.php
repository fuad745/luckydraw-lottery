<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Concerns\SharesContact;
use App\Telegram\TelegramAuth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class Settings extends Component
{
    use SharesContact;

    public string $locale = 'en';

    public function mount(): void
    {
        $this->locale = app()->getLocale();
    }

    public function setLanguage(string $locale)
    {
        if (! in_array($locale, ['en', 'am'], true)) {
            return null;
        }

        $this->locale = $locale;
        session(['locale' => $locale]);

        $player = app(TelegramAuth::class)->player();
        $player?->update(['locale' => $locale]);

        // Reload so every component re-renders in the new language.
        return $this->redirect(route('settings'), navigate: true);
    }

    /** Update the saved phone from a fresh Telegram contact share. */
    public function updatePhone(string $phone): void
    {
        if ($this->persistPhone($phone) === null) {
            $this->dispatch('toast', message: __("That number didn't look right — please try again."), type: 'error');

            return;
        }

        $this->dispatch('haptic', type: 'notification', style: 'success');
        $this->dispatch('toast', message: __('Contact updated.'), type: 'success');
    }

    public function render()
    {
        return view('livewire.settings', [
            'player' => app(TelegramAuth::class)->player(),
        ]);
    }
}

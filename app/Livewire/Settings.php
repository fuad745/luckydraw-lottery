<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Telegram\TelegramAuth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class Settings extends Component
{
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

    public function render()
    {
        return view('livewire.settings');
    }
}

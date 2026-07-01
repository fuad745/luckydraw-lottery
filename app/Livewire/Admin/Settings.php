<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Services\AdminCredentials;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin')]
final class Settings extends Component
{
    public string $username = '';

    public string $current_password = '';

    public string $new_password = '';

    public string $new_password_confirmation = '';

    public string $flash = '';

    public function mount(AdminCredentials $credentials): void
    {
        $this->username = $credentials->username();
    }

    public function save(AdminCredentials $credentials): void
    {
        $this->validate([
            'username' => ['required', 'string', 'min:3', 'max:64'],
            'current_password' => ['required', 'string'],
            'new_password' => ['nullable', 'string', 'min:8', 'max:128', 'confirmed'],
        ]);

        // Re-authenticate: the current password must be correct to change anything,
        // so a hijacked session can't silently lock out the owner.
        if (! $credentials->verifyPassword($this->current_password)) {
            $this->addError('current_password', 'Current password is incorrect.');

            return;
        }

        $changedPassword = $this->new_password !== '';
        $credentials->update($this->username, $changedPassword ? $this->new_password : null);

        $this->reset('current_password', 'new_password', 'new_password_confirmation');
        $this->flash = $changedPassword ? 'Username and password updated.' : 'Username updated.';
    }

    public function render()
    {
        return view('livewire.admin.settings');
    }
}

<?php

declare(strict_types=1);

namespace App\Telegram;

use App\Models\Player;
use App\Services\PlayerService;

/**
 * Request-scoped holder for the currently authenticated Telegram user.
 * Populated by the ResolveTelegramUser middleware; consumed by Livewire
 * components and controllers.
 */
final class TelegramAuth
{
    /** @var array<string,mixed>|null */
    private ?array $user = null;

    private ?Player $player = null;

    /** @param array<string,mixed>|null $user The decoded Telegram `user` object. */
    public function setUser(?array $user): void
    {
        $this->user = $user;
        $this->player = null;
    }

    public function check(): bool
    {
        return $this->user !== null && isset($this->user['id']);
    }

    public function id(): ?int
    {
        return isset($this->user['id']) ? (int) $this->user['id'] : null;
    }

    public function name(): ?string
    {
        if ($this->user === null) {
            return null;
        }

        $name = trim(($this->user['first_name'] ?? '').' '.($this->user['last_name'] ?? ''));

        return $name !== '' ? $name : ($this->user['username'] ?? null);
    }

    public function username(): ?string
    {
        return $this->user['username'] ?? null;
    }

    public function isAdmin(): bool
    {
        return $this->check() && in_array(
            (string) $this->id(),
            (array) config('lottery.admin_telegram_ids', []),
            true,
        );
    }

    /** Resolve (and persist) the Player record for the authenticated user. */
    public function player(): ?Player
    {
        if (! $this->check()) {
            return null;
        }

        return $this->player ??= app(PlayerService::class)->resolve(
            telegramId: $this->id(),
            name: $this->name() ?? 'Player',
            username: $this->username(),
            // Telegram initData never carries a phone; only present for local dev impersonation.
            phone: $this->user['phone'] ?? null,
        );
    }
}

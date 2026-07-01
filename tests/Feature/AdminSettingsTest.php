<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Admin\Settings;
use App\Services\AdminCredentials;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'lottery.admin_panel.username' => 'kichner',
            'lottery.admin_panel.password' => 'blackflag',
            'lottery.admin_panel.password_hash' => null,
        ]);
        // Settings page sits behind the admin session guard.
        session(['admin_authenticated' => true]);
    }

    public function test_credentials_fall_back_to_config_when_unset(): void
    {
        $creds = app(AdminCredentials::class);

        $this->assertSame('kichner', $creds->username());
        $this->assertTrue($creds->matches('kichner', 'blackflag'));
        $this->assertFalse($creds->matches('kichner', 'wrong'));
    }

    public function test_admin_can_change_username_and_password(): void
    {
        Livewire::test(Settings::class)
            ->set('username', 'newadmin')
            ->set('new_password', 's3cretpass')
            ->set('new_password_confirmation', 's3cretpass')
            ->set('current_password', 'blackflag')
            ->call('save')
            ->assertHasNoErrors();

        $creds = app(AdminCredentials::class);
        $this->assertSame('newadmin', $creds->username());
        $this->assertTrue($creds->matches('newadmin', 's3cretpass'));
        // Old credentials no longer work.
        $this->assertFalse($creds->matches('kichner', 'blackflag'));
    }

    public function test_wrong_current_password_blocks_the_change(): void
    {
        Livewire::test(Settings::class)
            ->set('username', 'hacker')
            ->set('current_password', 'not-the-password')
            ->call('save')
            ->assertHasErrors('current_password');

        // Nothing was persisted.
        $this->assertSame('kichner', app(AdminCredentials::class)->username());
    }

    public function test_username_only_change_keeps_the_existing_password(): void
    {
        Livewire::test(Settings::class)
            ->set('username', 'renamed')
            ->set('current_password', 'blackflag')
            ->call('save')
            ->assertHasNoErrors();

        $creds = app(AdminCredentials::class);
        $this->assertTrue($creds->matches('renamed', 'blackflag'));
    }

    public function test_login_uses_updated_db_credentials(): void
    {
        app(AdminCredentials::class)->update('dbuser', 'dbpassword1');

        $this->post(route('admin.login.attempt'), ['username' => 'dbuser', 'password' => 'dbpassword1'])
            ->assertRedirect(route('admin'));
    }
}

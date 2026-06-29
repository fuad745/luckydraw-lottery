<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminAuthTest extends TestCase
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
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));
        $this->get('/admin/players')->assertRedirect(route('admin.login'));
        $this->get('/admin/transactions/export')->assertRedirect(route('admin.login'));
    }

    public function test_valid_credentials_log_in(): void
    {
        $this->post(route('admin.login.attempt'), ['username' => 'kichner', 'password' => 'blackflag'])
            ->assertRedirect(route('admin'));

        $this->get('/admin')->assertOk()->assertSee('Dashboard');
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $this->post(route('admin.login.attempt'), ['username' => 'kichner', 'password' => 'wrong'])
            ->assertSessionHasErrors('username');

        $this->get('/admin')->assertRedirect(route('admin.login'));
    }

    public function test_logout_ends_the_session(): void
    {
        $this->post(route('admin.login.attempt'), ['username' => 'kichner', 'password' => 'blackflag']);
        $this->get('/admin')->assertOk();

        $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));
        $this->get('/admin')->assertRedirect(route('admin.login'));
    }
}

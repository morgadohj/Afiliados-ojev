<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrators_can_view_users(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);

        $this->actingAs($administrator)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/users/index')
                ->has('users.data', 1)
                ->where('users.data.0.role', UserRole::Administrator->value)
                ->where('roles.0.value', UserRole::Administrator->value)
                ->where('roles.1.value', UserRole::AffiliateRegistrar->value));
    }

    public function test_affiliate_registrars_cannot_manage_users(): void
    {
        $affiliateRegistrar = User::factory()->create(['role' => UserRole::AffiliateRegistrar]);

        $this->actingAs($affiliateRegistrar)
            ->get(route('admin.users.index'))
            ->assertForbidden();

        $this->actingAs($affiliateRegistrar)
            ->post(route('admin.users.store'), $this->validPayload())
            ->assertForbidden();
    }

    public function test_administrators_can_create_a_user_with_a_profile(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);

        $this->actingAs($administrator)
            ->post(route('admin.users.store'), $this->validPayload())
            ->assertRedirect(route('admin.users.index'));

        $user = User::query()->where('email', 'afiliador@ojev.org')->firstOrFail();

        $this->assertSame('Personal de afiliación', $user->name);
        $this->assertSame(UserRole::AffiliateRegistrar, $user->role);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('Clave-segura-2026', $user->password));
    }

    public function test_user_creation_validates_unique_email_role_and_password_confirmation(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        User::factory()->create(['email' => 'afiliador@ojev.org']);

        $payload = $this->validPayload();
        $payload['role'] = 'superusuario';
        $payload['password_confirmation'] = 'different-password';

        $this->actingAs($administrator)
            ->post(route('admin.users.store'), $payload)
            ->assertSessionHasErrors(['email', 'role', 'password']);
    }

    /**
     * @return array<string, string>
     */
    private function validPayload(): array
    {
        return [
            'name' => 'Personal de afiliación',
            'email' => 'afiliador@ojev.org',
            'role' => UserRole::AffiliateRegistrar->value,
            'password' => 'Clave-segura-2026',
            'password_confirmation' => 'Clave-segura-2026',
        ];
    }
}

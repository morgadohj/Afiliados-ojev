<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('summary.total', 0)
                ->has('affiliates.data', 0));
    }

    public function test_dashboard_lists_affiliates_and_the_user_who_registered_them()
    {
        $viewer = User::factory()->create();
        $registrar = User::factory()->create(['name' => 'Capturista OJEV']);

        $this->createAffiliate([
            'curp' => 'GOMJ900101HVZMRS09',
            'email' => 'publico@example.com',
        ]);

        $administrativeAffiliate = $this->createAffiliate([
            'first_name' => 'María Elena',
            'curp' => 'LOPM920202MVZPRS08',
            'email' => 'maria@example.com',
            'created_by_user_id' => $registrar->id,
        ]);

        $this->actingAs($viewer)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('summary.total', 2)
                ->where('summary.administrative', 1)
                ->where('summary.public', 1)
                ->has('affiliates.data', 2)
                ->where('affiliates.data.0.id', $administrativeAffiliate->id)
                ->where('affiliates.data.0.full_name', 'María Elena Gómez Martínez')
                ->where('affiliates.data.0.registered_by.name', 'Capturista OJEV')
                ->where('affiliates.data.1.registered_by', null));
    }

    private function createAffiliate(array $overrides = []): Affiliate
    {
        return Affiliate::query()->create([
            'folio' => null,
            'application_date' => '2026-08-11',
            'first_name' => 'Juan Carlos',
            'paternal_last_name' => 'Gómez',
            'maternal_last_name' => 'Martínez',
            'curp' => 'GOMJ900101HVZMRS09',
            'birth_date' => '1990-01-01',
            'address_street' => 'Calle Principal 123',
            'neighborhood' => 'Centro',
            'locality' => 'Xalapa',
            'municipality' => 'Xalapa',
            'state' => 'Veracruz',
            'postal_code' => '91000',
            'home_phone' => null,
            'mobile_phone' => '2281234567',
            'email' => 'juan@example.com',
            'occupation' => 'Ganadero',
            'livestock_association' => null,
            'oje_v_branch' => 'Delegación Xalapa',
            'profile_photo_path' => null,
            'ine_front_path' => 'affiliates/ine/front.enc',
            'ine_back_path' => 'affiliates/ine/back.enc',
            'signature_name' => 'Juan Carlos Gómez Martínez',
            'consent_accepted_at' => now(),
            'status' => 'submitted',
            'ocr_metadata' => null,
            ...$overrides,
        ]);
    }
}

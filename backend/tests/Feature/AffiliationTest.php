<?php

use App\Models\Affiliate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function validAffiliatePayload(array $overrides = []): array
{
    return [
        'application_date' => '2026-07-29',
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
        'home_phone' => '',
        'mobile_phone' => '2281234567',
        'email' => 'juan@example.com',
        'occupation' => 'Ganadero',
        'livestock_association' => 'Asociación local',
        'oje_v_branch' => 'Delegación Xalapa',
        'signature_name' => 'Juan Carlos Gómez Martínez',
        'consent' => '1',
        'ine_front' => UploadedFile::fake()->image('ine-frente.jpg', 1200, 760),
        'ine_back' => UploadedFile::fake()->image('ine-reverso.jpg', 1200, 760),
        'profile_photo' => UploadedFile::fake()->image('perfil.jpg', 600, 800),
        'ocr_metadata' => json_encode([
            'curp' => ['confidence' => 0.98, 'source' => 'ine_ocr'],
        ], JSON_THROW_ON_ERROR),
        ...$overrides,
    ];
}

it('shows the public affiliation form', function () {
    $this->get('/afiliacion')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('affiliation/create')
            ->has('ocrAvailable'));
});

it('stores an affiliation with encrypted private documents and a folio', function () {
    Storage::fake('local');

    $response = $this->postJson('/afiliacion', validAffiliatePayload());

    $response
        ->assertCreated()
        ->assertJson([
            'message' => 'Tu solicitud de afiliación fue recibida.',
            'folio' => 'OJEV-2026-000001',
        ]);

    $affiliate = Affiliate::query()->sole();

    expect($affiliate->status)->toBe('submitted')
        ->and($affiliate->created_by_user_id)->toBeNull()
        ->and($affiliate->consent_accepted_at)->not->toBeNull()
        ->and($affiliate->ine_front_path)->toEndWith('.enc')
        ->and($affiliate->ine_back_path)->toEndWith('.enc');

    Storage::disk('local')->assertExists($affiliate->ine_front_path);
    Storage::disk('local')->assertExists($affiliate->ine_back_path);

    expect(Storage::disk('local')->get($affiliate->ine_front_path))
        ->not->toContain('JFIF');
});

it('protects the administrative affiliation form', function () {
    $this->get('/administracion/afiliar')
        ->assertRedirect('/login');
});

it('shows the administrative affiliation form to an authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/administracion/afiliar')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/affiliation/create')
            ->has('ocrAvailable'));
});

it('records which authenticated user registered an affiliate', function () {
    Storage::fake('local');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/administracion/afiliar', validAffiliatePayload())
        ->assertCreated()
        ->assertJson([
            'message' => 'La afiliación fue registrada correctamente.',
            'folio' => 'OJEV-2026-000001',
        ]);

    $affiliate = Affiliate::query()->sole();

    expect($affiliate->created_by_user_id)->toBe($user->id)
        ->and($affiliate->createdBy->is($user))->toBeTrue()
        ->and($user->registeredAffiliates()->whereKey($affiliate)->exists())->toBeTrue();
});

it('requires both INE images and consent', function () {
    $this->postJson('/afiliacion', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'ine_front',
            'ine_back',
            'curp',
            'consent',
        ]);
});

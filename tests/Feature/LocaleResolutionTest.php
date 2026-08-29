<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\WithClient;
use Tests\TestCase;

/**
 * Guest locale resolution. A client without a public_default_locale keeps the
 * historical 'fr' default; exercised via the guest login page, which carries
 * the shared 'locale' prop.
 */
class LocaleResolutionTest extends TestCase
{
    use RefreshDatabase;
    use WithClient;

    public function test_a_client_without_a_public_default_locale_falls_back_to_french(): void
    {
        $this->asClient('directonderweg');

        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('locale', 'fr'));
    }

    public function test_a_client_public_default_locale_applies_to_guests(): void
    {
        $this->asClient('directonderweg');
        config(['client.public_default_locale' => 'en']);

        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));
    }
}

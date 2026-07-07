<?php

namespace Tests\Feature\Reception;

use App\Models\ContractCompany;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class ReceptionContractCompanyTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_reception_can_add_non_contracted_company(): void
    {
        $recep = $this->userWithRole('reception');

        $response = $this->actingAs($recep)
            ->postJson('/reception/lookup/companies', [
                'name' => 'جهة مرجعية جديدة',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'جهة مرجعية جديدة')
            ->assertJsonPath('data.is_contracted', false)
            ->assertJsonPath('data.is_military', false);

        $this->assertDatabaseHas('contract_companies', [
            'name' => 'جهة مرجعية جديدة',
            'is_contracted' => false,
            'is_military' => false,
        ]);
    }

    public function test_reception_add_returns_existing_non_contracted_company(): void
    {
        $recep = $this->userWithRole('reception');

        $existing = ContractCompany::create([
            'company_code' => 'CO-NC-01',
            'name' => 'جهة قائمة مسبقاً',
            'is_military' => false,
            'is_contracted' => false,
        ]);

        $this->actingAs($recep)
            ->postJson('/reception/lookup/companies', ['name' => 'جهة قائمة مسبقاً'])
            ->assertOk()
            ->assertJsonPath('data.id', $existing->id);

        $this->assertSame(1, ContractCompany::where('name', 'جهة قائمة مسبقاً')->count());
    }

    public function test_reception_cannot_add_name_already_used_by_contracted_company(): void
    {
        $recep = $this->userWithRole('reception');
        $this->civilianCompany('شركة متعاقدة');

        $this->actingAs($recep)
            ->postJson('/reception/lookup/companies', ['name' => 'شركة متعاقدة'])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'هذه الجهة مسجّلة كمتعاقدة — اخترها من القائمة تحت «متعاقد».']);
    }

    public function test_new_company_appears_in_lookup_list(): void
    {
        $recep = $this->userWithRole('reception');

        $this->actingAs($recep)
            ->postJson('/reception/lookup/companies', ['name' => 'جهة للقائمة'])
            ->assertCreated();

        $this->actingAs($recep)
            ->getJson('/reception/lookup/companies?all=1')
            ->assertOk()
            ->assertJsonFragment(['name' => 'جهة للقائمة']);
    }
}

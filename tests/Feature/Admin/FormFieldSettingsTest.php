<?php

namespace Tests\Feature\Admin;

use App\Services\FormFieldPolicyService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class FormFieldSettingsTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_admin_can_save_form_field_policies(): void
    {
        $admin = $this->userWithRole('admin');

        $payload = [
            'fields' => [
                'reception' => [
                    'phone' => true,
                    'national_id' => false,
                    'contract_company_id' => true,
                    'military_number' => true,
                    'seniority_number' => false,
                    'military_weapon' => true,
                ],
                'spec' => [
                    'written_items' => true,
                    'tech_notes' => false,
                ],
                'appointment' => [
                    'phone' => true,
                ],
            ],
        ];

        $this->actingAs($admin)
            ->putJson(route('admin.form-field-settings.update'), $payload)
            ->assertOk()
            ->assertJsonPath('fields.reception.0.field', 'phone');

        $policy = app(FormFieldPolicyService::class);
        $this->assertTrue($policy->isRequired('reception', 'phone'));
        $this->assertTrue($policy->isRequired('spec', 'written_items'));
    }
}

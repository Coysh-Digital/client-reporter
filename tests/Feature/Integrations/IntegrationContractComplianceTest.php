<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Integrations\IntegrationRegistry;
use App\Integrations\Testing\IntegrationContractAssertions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every bundled integration must satisfy the integration contract. Third-party
 * packages can reuse the same trait to verify compatibility.
 */
class IntegrationContractComplianceTest extends TestCase
{
    use IntegrationContractAssertions;
    use RefreshDatabase;

    public function test_all_bundled_integrations_satisfy_the_contract(): void
    {
        $integrations = app(IntegrationRegistry::class)->all();

        $this->assertNotEmpty($integrations);

        foreach ($integrations as $integration) {
            $this->assertValidIntegration($integration);
        }
    }
}

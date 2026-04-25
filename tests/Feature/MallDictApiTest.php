<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class MallDictApiTest extends TestCase
{
    public function test_dict_requires_codes(): void
    {
        $this->getJson('/api/mall/dict')
            ->assertOk()
            ->assertJsonPath('errorCode', 101);

        $this->getJson('/api/mall/dict?codes=')
            ->assertOk()
            ->assertJsonPath('errorCode', 101);
    }

    public function test_dict_returns_points_hold_state(): void
    {
        $this->getJson('/api/mall/dict?codes=points_hold_state')
            ->assertOk()
            ->assertJsonPath('errorCode', 0)
            ->assertJsonPath('data.points_hold_state.0.v', '10')
            ->assertJsonPath('data.points_hold_state.0.k', 'try pending');
    }

    public function test_dict_ignores_unknown_codes(): void
    {
        $this->getJson('/api/mall/dict?codes=unknown_code,points_hold_state')
            ->assertOk()
            ->assertJsonPath('errorCode', 0)
            ->assertJsonMissingPath('data.unknown_code')
            ->assertJsonPath('data.points_hold_state.0.v', '10');
    }
}

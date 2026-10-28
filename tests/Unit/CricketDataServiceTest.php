<?php

namespace Tests\Unit;

use App\Services\CricketDataService;
use Tests\TestCase;

class CricketDataServiceTest extends TestCase
{
    protected CricketDataService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CricketDataService();
    }

    public function test_map_status_with_match_ended_flag(): void
    {
        $status = $this->service->mapStatus('Sunrisers Hyderabad won by 67 runs', true, true);
        $this->assertEquals('completed', $status);
    }

    public function test_map_status_with_winning_text_without_flags(): void
    {
        $this->assertEquals('completed', $this->service->mapStatus('India won by 5 wickets'));
        $this->assertEquals('completed', $this->service->mapStatus('Australia won by 10 runs'));
        $this->assertEquals('completed', $this->service->mapStatus('Match tied'));
        $this->assertEquals('completed', $this->service->mapStatus('Match drawn'));
    }

    public function test_map_status_with_abandoned(): void
    {
        $this->assertEquals('abandoned', $this->service->mapStatus('Match abandoned due to rain'));
        $this->assertEquals('abandoned', $this->service->mapStatus('Match abandoned without a ball bowled', false, true));
        $this->assertEquals('abandoned', $this->service->mapStatus('No result'));
    }

    public function test_map_status_with_scheduled(): void
    {
        $this->assertEquals('scheduled', $this->service->mapStatus('Match not started', false, false));
        $this->assertEquals('scheduled', $this->service->mapStatus('Upcoming'));
    }

    public function test_map_status_with_live(): void
    {
        $this->assertEquals('live', $this->service->mapStatus('Innings Break', true, false));
        $this->assertEquals('live', $this->service->mapStatus('India opt to bowl', true, false));
        $this->assertEquals('live', $this->service->mapStatus('Rain delay', true, false));
        $this->assertEquals('live', $this->service->mapStatus('Day 1: Stumps', true, false));
    }

    public function test_live_status_override_even_if_match_ended_flag_is_true(): void
    {
        // Testing matches like New Zealand A vs India A where status contains active session/runs needed
        $status = $this->service->mapStatus('Day 4: 3rd Session - New Zealand A need 149 runs', true, true);
        $this->assertEquals('live', $status);
    }

    public function test_normalize_score(): void
    {
        $rawScores = [
            ['r' => 109, 'w' => 10, 'o' => 30.3, 'inning' => 'Kuwait Inning 1'],
            ['r' => 211, 'w' => 10, 'o' => 48.4, 'inning' => 'Denmark Inning 1']
        ];

        $normalized = $this->service->normalizeScore($rawScores);

        $this->assertCount(2, $normalized);
        $this->assertEquals('Kuwait Inning 1', $normalized[0]['batting_team']);
        $this->assertEquals(109, $normalized[0]['runs']);
        $this->assertEquals(10, $normalized[0]['wickets']);
        $this->assertEquals('30.3', $normalized[0]['overs']);

        $this->assertEquals('Denmark Inning 1', $normalized[1]['batting_team']);
        $this->assertEquals(211, $normalized[1]['runs']);
        $this->assertEquals(10, $normalized[1]['wickets']);
        $this->assertEquals('48.4', $normalized[1]['overs']);
    }
}

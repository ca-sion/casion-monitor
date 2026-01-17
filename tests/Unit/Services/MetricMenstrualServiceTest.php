<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\Metric;
use App\Models\Athlete;
use App\Enums\MetricType;
use Illuminate\Support\Carbon;
use App\Services\MetricTrendsService;
use PHPUnit\Framework\Attributes\Test;
use App\Services\MetricMenstrualService;
use App\Services\MetricReadinessService;
use App\Services\MetricCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MetricMenstrualServiceTest extends TestCase
{
    use RefreshDatabase;

    protected MetricMenstrualService $service;

    protected $calculationService;

    protected $trendsService;

    protected $readinessService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculationService = $this->createMock(MetricCalculationService::class);
        $this->trendsService = $this->createMock(MetricTrendsService::class);
        $this->readinessService = $this->createMock(MetricReadinessService::class);

        $this->service = new MetricMenstrualService(
            $this->calculationService,
            $this->trendsService,
            $this->readinessService
        );
    }

    #[Test]
    public function it_deduces_menstrual_phase_regular_cycle()
    {
        Carbon::setTestNow('2026-01-20');
        $athlete = Athlete::factory()->create();

        // Regular cycle: J1 every 28 days
        // Last J1: 2026-01-15 (Day 6 of cycle today)
        // Previous J1: 2025-12-18
        $this->createJ1($athlete, '2026-01-15');
        $this->createJ1($athlete, '2025-12-18');

        $result = $this->service->deduceMenstrualCyclePhase($athlete);

        $this->assertEquals('Folliculaire', $result['phase']);
        $this->assertEquals(6, $result['days_in_phase']);
        $this->assertEquals(28, $result['cycle_length_avg']);
    }

    #[Test]
    public function it_deduces_menstrual_phase_menstruelle()
    {
        Carbon::setTestNow('2026-01-17');
        $athlete = Athlete::factory()->create();

        // Last J1: 2026-01-15 (Day 3 of cycle today)
        $this->createJ1($athlete, '2026-01-15');
        $this->createJ1($athlete, '2025-12-18');

        $result = $this->service->deduceMenstrualCyclePhase($athlete);

        $this->assertEquals('Menstruelle', $result['phase']);
        $this->assertEquals(3, $result['days_in_phase']);
    }

    #[Test]
    public function it_deduces_menstrual_phase_ovulatoire()
    {
        Carbon::setTestNow('2026-01-28');
        $athlete = Athlete::factory()->create();

        // Last J1: 2026-01-14 (Day 15 of cycle today, exactly middle of 28 day cycle)
        $this->createJ1($athlete, '2026-01-14');
        $this->createJ1($athlete, '2025-12-17');

        $result = $this->service->deduceMenstrualCyclePhase($athlete);

        $this->assertEquals('Ovulatoire', $result['phase']);
        $this->assertEquals(15, $result['days_in_phase']);
    }

    #[Test]
    public function it_deduces_menstrual_phase_luteale()
    {
        Carbon::setTestNow('2026-02-05');
        $athlete = Athlete::factory()->create();

        // Last J1: 2026-01-15 (Day 22 of cycle today)
        $this->createJ1($athlete, '2026-01-15');
        $this->createJ1($athlete, '2025-12-18');

        $result = $this->service->deduceMenstrualCyclePhase($athlete);

        $this->assertEquals('Lutéale', $result['phase']);
        $this->assertEquals(22, $result['days_in_phase']);
    }

    #[Test]
    public function it_detects_oligomenorrhee_long_cycle()
    {
        Carbon::setTestNow('2026-01-20');
        $athlete = Athlete::factory()->create();

        // Cycle length 40 days (> 35)
        $this->createJ1($athlete, '2026-01-15');
        $this->createJ1($athlete, '2025-12-06');

        $result = $this->service->deduceMenstrualCyclePhase($athlete);

        $this->assertEquals('Oligoménorrhée', $result['phase']);
        $this->assertStringContainsString('hors de la plage normale', $result['reason']);
    }

    #[Test]
    public function it_detects_amenorrhee()
    {
        Carbon::setTestNow('2026-04-20');
        $athlete = Athlete::factory()->create();

        // Last J1 was 100 days ago, avg cycle is 28
        $this->createJ1($athlete, '2026-01-10');
        $this->createJ1($athlete, '2025-12-13');

        $result = $this->service->deduceMenstrualCyclePhase($athlete);

        $this->assertEquals('Aménorrhée', $result['phase']);
    }

    #[Test]
    public function it_detects_delayed_cycle()
    {
        Carbon::setTestNow('2026-01-20');
        $athlete = Athlete::factory()->create();

        // Avg cycle 28 days, last J1 was 31 days ago
        $this->createJ1($athlete, '2025-12-20');
        $this->createJ1($athlete, '2025-11-22');

        $result = $this->service->deduceMenstrualCyclePhase($athlete);

        $this->assertEquals('Potentiel retard ou cycle long', $result['phase']);
    }

    #[Test]
    public function it_handles_insufficient_data()
    {
        Carbon::setTestNow('2026-01-20');
        $athlete = Athlete::factory()->create();

        // Only one J1
        $this->createJ1($athlete, '2026-01-10');

        $result = $this->service->deduceMenstrualCyclePhase($athlete);

        $this->assertEquals('Inconnue', $result['phase']);
        $this->assertStringContainsString('Enregistrez au moins deux J1', $result['reason']);
    }

    #[Test]
    public function it_gives_correct_recommendations()
    {
        $athlete = Athlete::factory()->create();

        $rec = $this->service->getPhaseSpecificRecommendation($athlete, 'Folliculaire');
        $this->assertEquals('optimal', $rec['status']);
        $this->assertEquals('GO', $rec['action']);

        $rec = $this->service->getPhaseSpecificRecommendation($athlete, 'Lutéale');
        $this->assertEquals('moderate', $rec['status']);

        $rec = $this->service->getPhaseSpecificRecommendation($athlete, 'Aménorrhée');
        $this->assertEquals('critical', $rec['status']);
        $this->assertStringContainsString('STOP!', $rec['action']);
    }

    private function createJ1(Athlete $athlete, string $date): void
    {
        Metric::create([
            'athlete_id'  => $athlete->id,
            'metric_type' => MetricType::MORNING_FIRST_DAY_PERIOD->value,
            'date'        => $date,
            'value'       => 1,
        ]);
    }
}

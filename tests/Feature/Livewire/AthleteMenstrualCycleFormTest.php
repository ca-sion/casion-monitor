<?php

namespace Tests\Feature\Livewire;

use Tests\TestCase;
use App\Models\Metric;
use Livewire\Livewire;
use App\Models\Athlete;
use App\Enums\MetricType;
use PHPUnit\Framework\Attributes\Test;
use App\Livewire\AthleteMenstrualCycleForm;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AthleteMenstrualCycleFormTest extends TestCase
{
    use RefreshDatabase;

    protected Athlete $athlete;

    protected function setUp(): void
    {
        parent::setUp();
        $this->athlete = Athlete::factory()->create(['gender' => 'w']);
    }

    #[Test]
    public function it_can_render_the_form()
    {
        $this->actingAs($this->athlete, 'athlete');

        Livewire::test(AthleteMenstrualCycleForm::class)
            ->assertStatus(200)
            ->assertSee('Dates du premier jour des règles');
    }

    #[Test]
    public function it_fills_form_with_existing_data()
    {
        Metric::create([
            'athlete_id'  => $this->athlete->id,
            'metric_type' => MetricType::MORNING_FIRST_DAY_PERIOD,
            'date'        => '2026-01-15',
            'value'       => 1,
        ]);

        $this->actingAs($this->athlete, 'athlete');

        Livewire::test(AthleteMenstrualCycleForm::class)
            ->assertSchemaStateSet(function (array $state) {
                $dates = collect($state['menstrual_cycle_dates'])->pluck('date')->values()->toArray();

                return $dates === ['2026-01-15'];
            }, 'form');
    }

    #[Test]
    public function it_can_save_new_menstrual_dates()
    {
        $this->actingAs($this->athlete, 'athlete');

        Livewire::test(AthleteMenstrualCycleForm::class)
            ->fillForm([
                'menstrual_cycle_dates' => [
                    ['date' => '2026-01-10'],
                    ['date' => '2025-12-12'],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        // Using partial match for date to avoid timestamp issues in different DB drivers
        $this->assertDatabaseHas('metrics', [
            'athlete_id'  => $this->athlete->id,
            'metric_type' => MetricType::MORNING_FIRST_DAY_PERIOD->value,
            'date'        => '2026-01-10 00:00:00',
            'value'       => 1,
        ]);

        $this->assertDatabaseHas('metrics', [
            'athlete_id'  => $this->athlete->id,
            'metric_type' => MetricType::MORNING_FIRST_DAY_PERIOD->value,
            'date'        => '2025-12-12 00:00:00',
            'value'       => 1,
        ]);
    }

    #[Test]
    public function it_can_delete_menstrual_dates()
    {
        Metric::create([
            'athlete_id'  => $this->athlete->id,
            'metric_type' => MetricType::MORNING_FIRST_DAY_PERIOD,
            'date'        => '2026-01-15',
            'value'       => 1,
        ]);

        $this->actingAs($this->athlete, 'athlete');

        Livewire::test(AthleteMenstrualCycleForm::class)
            ->fillForm([
                'menstrual_cycle_dates' => [], // Clear dates
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseMissing('metrics', [
            'athlete_id'  => $this->athlete->id,
            'metric_type' => MetricType::MORNING_FIRST_DAY_PERIOD->value,
            'date'        => '2026-01-15',
        ]);
    }
}

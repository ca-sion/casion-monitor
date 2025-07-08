<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\MetricStatisticsService;
use App\Models\Metric;
use App\Models\Athlete;
use App\Models\TrainingPlanWeek;
use App\Enums\MetricType;
use Carbon\Carbon;
use Illuminate\Support\Collection;

use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertIsArray;
use function PHPUnit\Framework\assertNotNull;
use function PHPUnit\Framework\assertNull;

beforeEach(function () {
    $this->service = new MetricStatisticsService();
});

it('can calculate SBM for a collection of daily metrics', function () {
    $dailyMetrics = new Collection([
        (object)['metric_type' => MetricType::MORNING_SLEEP_QUALITY, 'value' => 8],
        (object)['metric_type' => MetricType::MORNING_GENERAL_FATIGUE, 'value' => 2],
        (object)['metric_type' => MetricType::MORNING_PAIN, 'value' => 1],
        (object)['metric_type' => MetricType::MORNING_MOOD_WELLBEING, 'value' => 7],
    ]);

    $sbm = $this->service->calculateSbmForCollection($dailyMetrics);

    // SBM = 8 + (10-2) + (10-1) + 7 = 8 + 8 + 9 + 7 = 32
    assertEquals(32.0, $sbm);
});

it('returns null SBM if no relevant metrics are present', function () {
    $dailyMetrics = new Collection([
        (object)['metric_type' => MetricType::POST_SESSION_SESSION_LOAD, 'value' => 50],
    ]);

    $sbm = $this->service->calculateSbmForCollection($dailyMetrics);

    assertNull($sbm);
});

it('can calculate SBM with partial daily metrics', function () {
    // Scenario 1: MORNING_PAIN is missing
    $dailyMetrics = new Collection([
        (object)['metric_type' => MetricType::MORNING_SLEEP_QUALITY, 'value' => 8],
        (object)['metric_type' => MetricType::MORNING_GENERAL_FATIGUE, 'value' => 2],
        // MORNING_PAIN is missing
        (object)['metric_type' => MetricType::MORNING_MOOD_WELLBEING, 'value' => 7],
    ]);

    $sbm = $this->service->calculateSbmForCollection($dailyMetrics);
    // sbmSum = 8 + (10-2) + 7 = 23
    // maxPossibleSbm = 10 + 10 + 10 = 30
    // SBM = (23 / 30) * 40 = 30.666...
    assertEquals(30.67, round($sbm, 2));

    // Scenario 2: All SBM related metrics are missing
    $dailyMetrics = new Collection([
        (object)['metric_type' => MetricType::POST_SESSION_SESSION_LOAD, 'value' => 50],
        (object)['metric_type' => MetricType::MORNING_BODY_WEIGHT_KG, 'value' => 70],
    ]);

    $sbm = $this->service->calculateSbmForCollection($dailyMetrics);
    assertNull($sbm);

    // Scenario 3: MORNING_SLEEP_QUALITY and MORNING_PAIN are missing
    $dailyMetrics = new Collection([
        (object)['metric_type' => MetricType::MORNING_GENERAL_FATIGUE, 'value' => 5], // contribution 5
        (object)['metric_type' => MetricType::MORNING_MOOD_WELLBEING, 'value' => 5], // contribution 5
    ]);

    $sbm = $this->service->calculateSbmForCollection($dailyMetrics);
    // sbmSum = (10-5) + 5 = 10
    // maxPossibleSbm = 10 + 10 = 20
    // SBM = (10 / 20) * 40 = 20
    assertEquals(20.0, $sbm);
});

it('can get start date from period', function () {
    Carbon::setTestNow(Carbon::parse('2025-07-01 12:00:00'));

    $startDate7Days = $this->service->getStartDateFromPeriod('last_7_days');
    assertEquals(Carbon::parse('2025-06-24 00:00:00'), $startDate7Days);

    $startDate30Days = $this->service->getStartDateFromPeriod('last_30_days');
    assertEquals(Carbon::parse('2025-06-01 00:00:00'), $startDate30Days);

    $startDateAllTime = $this->service->getStartDateFromPeriod('all_time');
    assertEquals(Carbon::createFromTimestamp(0), $startDateAllTime);

    Carbon::setTestNow(); // Reset Carbon
});

it('can calculate CPH', function () {
    $planWeek = new TrainingPlanWeek();
    $planWeek->volume_planned = 100;
    $planWeek->intensity_planned = 7;

    $cph = $this->service->calculateCph($planWeek);

    // CPH = 100 * (7 / 10) = 70
    assertEquals(70.0, $cph);
});

it('returns 0 CPH if volume or intensity is null', function () {
    $planWeek = new TrainingPlanWeek();
    $planWeek->volume_planned = null;
    $planWeek->intensity_planned = 7;

    $cph = $this->service->calculateCph($planWeek);
    assertEquals(0.0, $cph);

    $planWeek->volume_planned = 100;
    $planWeek->intensity_planned = null;
    $cph = $this->service->calculateCph($planWeek);
    assertEquals(0.0, $cph);
});

it('can format metric value', function () {
    $metricType = MetricType::MORNING_BODY_WEIGHT_KG; // precision 1, unit kg
    $formattedValue = $this->service->formatMetricValue(75.56, $metricType);
    assertEquals('75.56 kg', $formattedValue);

    $metricType = MetricType::MORNING_GENERAL_FATIGUE; // precision 0, no unit
    $formattedValue = $this->service->formatMetricValue(8.2, $metricType);
    assertEquals('8/10', $formattedValue);

    $metricType = MetricType::MORNING_PAIN; // note type
    $formattedValue = $this->service->formatMetricValue('Note text', $metricType);
    assertEquals('Note text', $formattedValue);

    $formattedValue = $this->service->formatMetricValue(null, $metricType);
    assertEquals('N/A', $formattedValue);
});

it('can prepare chart data for a single metric', function () {
    $metricType = MetricType::MORNING_HRV;
    $metrics = new Collection([
        (object)['date' => Carbon::parse('2025-07-01'), 'metric_type' => $metricType, 'value' => 50],
        (object)['date' => Carbon::parse('2025-07-02'), 'metric_type' => $metricType, 'value' => 55],
        (object)['date' => Carbon::parse('2025-07-03'), 'metric_type' => $metricType, 'value' => 48],
    ]);

    $chartData = $this->service->prepareChartDataForSingleMetric($metrics, $metricType);

    assertIsArray($chartData);
    assertEquals(['2025-07-01', '2025-07-02', '2025-07-03'], $chartData['labels']);
    assertEquals([50.0, 55.0, 48.0], $chartData['data']);
    assertNotNull($chartData['labels_and_data']);
    assertEquals($metricType->getUnit(), $chartData['unit']);
    assertEquals($metricType->getLabel(), $chartData['label']);
});

it('can calculate evolution trend from numeric collection', function () {
    $dataCollection = new Collection([
        (object)['date' => Carbon::parse('2025-07-01'), 'value' => 10],
        (object)['date' => Carbon::parse('2025-07-02'), 'value' => 12],
        (object)['date' => Carbon::parse('2025-07-03'), 'value' => 15],
        (object)['date' => Carbon::parse('2025-07-04'), 'value' => 13],
        (object)['date' => Carbon::parse('2025-07-05'), 'value' => 18],
        (object)['date' => Carbon::parse('2025-07-06'), 'value' => 20],
    ]);

    $trend = $this->service->calculateTrendFromNumericCollection($dataCollection);
    assertEquals('increasing', $trend['trend']);
    assertNotNull($trend['change']);

    $dataCollection = new Collection([
        (object)['date' => Carbon::parse('2025-07-01'), 'value' => 20],
        (object)['date' => Carbon::parse('2025-07-02'), 'value' => 18],
        (object)['date' => Carbon::parse('2025-07-03'), 'value' => 15],
        (object)['date' => Carbon::parse('2025-07-04'), 'value' => 17],
        (object)['date' => Carbon::parse('2025-07-05'), 'value' => 12],
        (object)['date' => Carbon::parse('2025-07-06'), 'value' => 10],
    ]);

    $trend = $this->service->calculateTrendFromNumericCollection($dataCollection);
    assertEquals('decreasing', $trend['trend']);
    assertNotNull($trend['change']);

    $dataCollection = new Collection([
        (object)['date' => Carbon::parse('2025-07-01'), 'value' => 10],
        (object)['date' => Carbon::parse('2025-07-02'), 'value' => 10.1],
        (object)['date' => Carbon::parse('2025-07-03'), 'value' => 9.9],
    ]);

    $trend = $this->service->calculateTrendFromNumericCollection($dataCollection);
    assertEquals('decreasing', $trend['trend']);
    assertNotNull($trend['change']);

    $dataCollection = new Collection([
        (object)['date' => Carbon::parse('2025-07-01'), 'value' => 10],
    ]);

    $trend = $this->service->calculateTrendFromNumericCollection($dataCollection);
    assertEquals('N/A', $trend['trend']);
    assertNull($trend['change']);
});
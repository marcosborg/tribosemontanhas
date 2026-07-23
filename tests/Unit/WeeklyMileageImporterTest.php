<?php

namespace Tests\Unit;

use App\Models\TvdeWeek;
use App\Services\WeeklyMileageImporter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use RuntimeException;
use Tests\TestCase;

class WeeklyMileageImporterTest extends TestCase
{
    /** @dataProvider teslaDelimiterProvider */
    public function test_it_reads_tesla_csv_with_supported_delimiters(string $delimiter): void
    {
        $path = tempnam(sys_get_temp_dir(), 'tesla-mileage-');
        $content = "\xEF\xBB\xBF" . implode($delimiter, ['VIN', 'Matrícula', 'Quilómetros']) . "\n"
            . implode($delimiter, ['VIN123', 'AA-00-AA', '165 992 km no conta-quilómetros']) . "\n"
            . 'Este ficheiro contém informação confidencial' . $delimiter . $delimiter . "\n";
        file_put_contents($path, $content);

        try {
            $rows = app(WeeklyMileageImporter::class)->readTeslaRows($path);
            $this->assertCount(1, $rows);
            $this->assertSame('AA-00-AA', $rows[0]['license_plate']);
            $this->assertSame(165992.0, $rows[0]['odometer_end']);
        } finally {
            @unlink($path);
        }
    }

    public function teslaDelimiterProvider(): array
    {
        return [[';'], [',']];
    }

    public function test_it_reads_cartrack_xls_and_preserves_decimal_values(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'cartrack-mileage-') . '.xls';
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['Data início: 2026-07-13 00:00:00+0100 - Data fim: 2026-07-19 23:59:59+0100'],
            [],
            ['Matrícula', 'Descrição', 'Início', '', 'Fim', '', 'Distância'],
            ['BR-94-EB', '', 136506.92, '', 138424.38, '', 1917.46],
            ['Total:', '', '', '', '', '', 1917.46],
        ]);
        (new Xls($spreadsheet))->save($path);

        $week = new TvdeWeek();
        $week->setRawAttributes(['id' => 1, 'start_date' => '2026-07-13', 'end_date' => '2026-07-19']);

        try {
            $rows = app(WeeklyMileageImporter::class)->readCarTrackRows($path, $week);
            $this->assertCount(1, $rows);
            $this->assertSame(136506.92, $rows[0]['odometer_start']);
            $this->assertSame(138424.38, $rows[0]['odometer_end']);
            $this->assertSame(1917.46, $rows[0]['distance_km']);
        } finally {
            @unlink($path);
        }
    }

    public function test_it_rejects_a_cartrack_period_that_does_not_match_the_week(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'cartrack-period-') . '.xls';
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray([
            ['Data início: 2026-07-06 00:00:00+0100 - Data fim: 2026-07-12 23:59:59+0100'],
            ['Matrícula', 'Descrição', 'Início', '', 'Fim', '', 'Distância'],
            ['BR-94-EB', '', 1, '', 2, '', 1],
        ]);
        (new Xls($spreadsheet))->save($path);
        $week = new TvdeWeek();
        $week->setRawAttributes(['id' => 1, 'start_date' => '2026-07-13', 'end_date' => '2026-07-19']);

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('não corresponde à semana');
            app(WeeklyMileageImporter::class)->readCarTrackRows($path, $week);
        } finally {
            @unlink($path);
        }
    }
}

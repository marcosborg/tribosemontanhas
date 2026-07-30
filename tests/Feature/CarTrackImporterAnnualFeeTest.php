<?php

namespace Tests\Feature;

use App\Models\CarTrack;
use App\Models\CompanyExpense;
use App\Services\CarTrackClassificationService;
use App\Services\CarTrackImporter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class CarTrackImporterAnnualFeeTest extends TestCase
{
    use DatabaseTransactions;

    private string $filePath;
    private int $weekId;
    private int $companyId;
    private int $driverId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyId = DB::table('companies')->insertGetId([
            'name' => 'Empresa Via Verde Teste',
            'vat' => '599999990',
            'address' => 'Rua Teste',
            'zip' => '1000-001',
            'location' => 'Lisboa',
            'email' => 'via-verde-test@example.test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->driverId = DB::table('drivers')->insertGetId([
            'code' => 'VV-TEST',
            'name' => 'Motorista Via Verde Teste',
            'company_id' => $this->companyId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $vehicleId = DB::table('vehicle_items')->insertGetId([
            'year' => '2035',
            'license_plate' => 'ZZ-99-XY',
            'company_id' => $this->companyId,
            'vehicle_type' => 'fleet',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('vehicle_usages')->insert([
            'start_date' => '2035-01-01 00:00:00',
            'end_date' => null,
            'driver_id' => $this->driverId,
            'vehicle_item_id' => $vehicleId,
            'usage_exceptions' => 'usage',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->weekId = DB::table('tvde_weeks')->insertGetId([
            'number' => 1,
            'start_date' => '2035-01-01',
            'end_date' => '2035-01-07',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->filePath = tempnam(sys_get_temp_dir(), 'via-verde-annual-fee-') . '.xlsx';
        $this->writeWorkbook($this->filePath);
    }

    protected function tearDown(): void
    {
        if (isset($this->filePath) && is_file($this->filePath)) {
            unlink($this->filePath);
        }

        parent::tearDown();
    }

    public function test_annual_fee_is_company_expense_and_other_movements_remain_driver_billable(): void
    {
        $summary = app(CarTrackImporter::class)->import($this->filePath, 'Via Verde teste.xlsx', $this->weekId);

        $annualFee = CarTrack::where('classification_reason', CarTrackImporter::ANNUAL_FEE_REASON)->firstOrFail();
        $this->assertSame(CarTrackClassificationService::STATUS_COMPANY, $annualFee->classification_status);
        $this->assertNull($annualFee->driver_id);
        $this->assertSame($this->companyId, $annualFee->company_id);

        $companyExpense = CompanyExpense::where('source_type', CarTrackImporter::COMPANY_EXPENSE_SOURCE_TYPE)->firstOrFail();
        $this->assertSame($this->companyId, $companyExpense->company_id);
        $this->assertSame('17.49', $companyExpense->value);
        $this->assertSame('23.49', $companyExpense->invoice_value);
        $this->assertSame('Via Verde teste.xlsx', $companyExpense->source_filename);
        $this->assertSame(2, $companyExpense->source_row_number);

        $normalMovement = CarTrack::where('market_description', 'Portagens')->firstOrFail();
        $this->assertSame(CarTrackClassificationService::STATUS_DRIVER, $normalMovement->classification_status);
        $this->assertSame($this->driverId, $normalMovement->driver_id);
        $this->assertSame(1, $summary['company_expenses']);
    }

    public function test_reimport_is_idempotent_and_historical_movements_are_not_modified(): void
    {
        $historicalId = DB::table('car_tracks')->insertGetId([
            'tvde_week_id' => $this->weekId,
            'license_plate' => 'HIST-01',
            'date' => '2035-01-02 09:00:00',
            'value' => 9.99,
            'classification_status' => CarTrackClassificationService::STATUS_DRIVER,
            'classification_reason' => 'historical_test',
            'created_at' => '2035-01-02 10:00:00',
            'updated_at' => '2035-01-02 10:00:00',
        ]);

        $importer = app(CarTrackImporter::class);
        $first = $importer->import($this->filePath, 'Via Verde teste.xlsx', $this->weekId);
        $trackCount = CarTrack::where('tvde_week_id', $this->weekId)->count();
        $second = $importer->import($this->filePath, 'Via Verde teste.xlsx', $this->weekId);

        $this->assertSame(2, $first['inserted']);
        $this->assertSame(0, $second['inserted']);
        $this->assertSame(2, $second['duplicates']);
        $this->assertSame($trackCount, CarTrack::where('tvde_week_id', $this->weekId)->count());
        $this->assertSame(1, CompanyExpense::where('source_type', CarTrackImporter::COMPANY_EXPENSE_SOURCE_TYPE)->count());

        $historical = CarTrack::withTrashed()->findOrFail($historicalId);
        $this->assertSame('HIST-01', $historical->license_plate);
        $this->assertSame('9.99', $historical->value);
        $this->assertSame('historical_test', $historical->classification_reason);
        $this->assertNull($historical->deleted_at);
        $this->assertSame('2035-01-02 10:00:00', $historical->updated_at->format('Y-m-d H:i:s'));
    }

    private function writeWorkbook(string $path): void
    {
        $headers = [
            'License Plate', 'IAI', 'OBU', 'Service', 'Service Description', 'Market',
            'Market Description', 'Entry Date', 'Exit Date', 'Entry Point', 'Exit Point',
            'Value', 'Is Paid', 'Payment Date', 'Invoice', 'Discount', 'Tax', 'Liquid Value',
        ];
        $annualFee = [
            'ZZ-99-XY', '1', '2', '-1', 'Modalidades e Acessórios', '41',
            '  aNuIdAdE Via Verde Mobilidade Anual  ', '2035-01-02 08:35:32',
            '2035-01-02 08:35:32', 'Anuidade VV', 'Anuidade VV', 23.49, true,
            '2035-01-03', '100', 0, 0, 17.49,
        ];
        $toll = [
            'ZZ-99-XY', '1', '2', '5', 'Autoestradas', '1', 'Portagens',
            '2035-01-02 12:00:00', '2035-01-02 12:05:00', 'Entrada', 'Saída',
            2.25, true, '2035-01-03', '100', 0, 0, 2.25,
        ];

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray([$headers, $annualFee, $toll]);
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
    }
}

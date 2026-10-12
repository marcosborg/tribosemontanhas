<?php

namespace Tests\Feature;

use App\Models\CarTrack;
use App\Services\CarTrackClassificationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CarTrackManualReclassificationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_correcting_a_manual_license_plate_makes_the_movement_billable_to_the_driver(): void
    {
        $companyId = DB::table('companies')->insertGetId([
            'name' => 'Empresa reclassificacao Via Verde',
            'vat' => '599999991',
            'address' => 'Rua Teste',
            'zip' => '1000-002',
            'location' => 'Lisboa',
            'email' => 'via-verde-reclassificacao@example.test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $driverId = DB::table('drivers')->insertGetId([
            'code' => 'VV-RECLASS',
            'name' => 'Rafael Viana Teste',
            'company_id' => $companyId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $vehicleId = DB::table('vehicle_items')->insertGetId([
            'year' => '2035',
            'license_plate' => 'ZZ-RE-35',
            'company_id' => $companyId,
            'vehicle_type' => 'fleet',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('vehicle_usages')->insert([
            'start_date' => '2035-01-01 00:00:00',
            'end_date' => null,
            'driver_id' => $driverId,
            'vehicle_item_id' => $vehicleId,
            'usage_exceptions' => 'usage',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $weekId = DB::table('tvde_weeks')->insertGetId([
            'number' => 2,
            'start_date' => '2035-01-01',
            'end_date' => '2035-01-07',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $track = CarTrack::create([
            'tvde_week_id' => $weekId,
            'license_plate' => 'MATRICULA-EM-FALTA',
            'date' => '2035-01-02 12:00:00',
            'value' => 7.5,
            'classification_status' => CarTrackClassificationService::STATUS_MANUAL,
            'classification_reason' => 'missing_vehicle',
        ]);

        $track->update(['license_plate' => 'ZZ-RE-35']);
        app(CarTrackClassificationService::class)->reclassify($track);

        $this->assertSame(CarTrackClassificationService::STATUS_DRIVER, $track->classification_status);
        $this->assertSame('normal_driver', $track->classification_reason);
        $this->assertSame($vehicleId, $track->vehicle_item_id);
        $this->assertSame($driverId, $track->driver_id);
        $this->assertNull($track->company_id);
    }
}

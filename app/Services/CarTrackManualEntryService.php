<?php

namespace App\Services;

use App\Models\{CarTrack, CompanyExpense};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CarTrackManualEntryService
{
    public function delete(CarTrack $track): void
    {
        DB::transaction(function () use ($track) {
            if ($track->source_fingerprint) {
                CompanyExpense::where('source_type', CarTrackImporter::COMPANY_EXPENSE_SOURCE_TYPE)
                    ->where('source_fingerprint', $track->source_fingerprint)->delete();
            }
            $track->delete();
            app(CarTrackImporter::class)->rebuildCompanyParkAggregates((int) $track->tvde_week_id);
        });
    }

    public function save(array $data, ?CarTrack $track = null): CarTrack
    {
        return DB::transaction(function () use ($data, $track) {
            $classifier = app(CarTrackClassificationService::class);
            $destination = $data['destination'] ?? 'driver';
            if ($destination === 'company') {
                $classification = $classifier->classifyCompanyExpense($data['license_plate'] ?? null, $data['reason']);
                if (!empty($data['company_id'])) {
                    $classification = array_merge($classification, ['classification_status' => 'company', 'classification_reason' => $data['reason'], 'company_id' => $data['company_id'], 'driver_id' => null]);
                }
            } else {
                $classification = $classifier->classify($data['license_plate'] ?? null, $data['date']);
            }
            if ($classification['classification_status'] !== $destination) {
                throw ValidationException::withMessages([$destination === 'company' ? 'company_id' : 'license_plate' => $destination === 'company'
                    ? 'Selecione a empresa ou indique uma viatura associada à empresa.'
                    : 'Não foi encontrado um motorista imputável à viatura nesta data. Verifique a matrícula e a utilização da viatura.']);
            }
            $oldWeek = $track ? $track->tvde_week_id : null;
            $track = $track ?? new CarTrack();
            $track->fill(array_intersect_key($data, array_flip(['date', 'license_plate', 'value', 'tvde_week_id'])));
            $track->fill($classification);
            $track->save();
            if (!$track->source_fingerprint) {
                $track->update(['source_fingerprint' => hash('sha256', 'manual-via-verde:' . $track->id)]);
            }
            $key = ['source_type' => CarTrackImporter::COMPANY_EXPENSE_SOURCE_TYPE, 'source_fingerprint' => $track->source_fingerprint];
            if ($destination === 'company' && $data['reason'] === CarTrackImporter::ANNUAL_FEE_REASON) {
                $expense = CompanyExpense::withTrashed()->firstOrNew($key);
                $date = Carbon::parse($data['date'])->format('Y-m-d');
                $expense->fill(array_merge($key, [
                    'company_id' => $track->company_id, 'expense_mode' => CompanyExpense::MODE_ACCOUNTING,
                    'expense_type' => 'Portagens', 'name' => 'Anuidade Via Verde',
                    'description' => 'Anuidade Via Verde — ' . $track->license_plate,
                    'date' => $date, 'start_date' => $date, 'end_date' => $date, 'qty' => 1,
                    'value' => $track->value, 'weekly_value' => $track->value, 'pay_to' => 'Via Verde',
                    'deleted_at' => null,
                ]));
                if (!$expense->exists) {
                    $expense->fill(['vat' => 23, 'is_paid' => true, 'paid_at' => $data['date']]);
                }
                if ($expense->exists && $expense->isDirty('value')) {
                    $expense->final_value = round((float) $track->value * (1 + (float) $expense->vat / 100), 2);
                }
                $expense->save();
            } else {
                CompanyExpense::where($key)->delete();
            }
            $importer = app(CarTrackImporter::class);
            foreach (array_unique(array_filter([$oldWeek, $track->tvde_week_id])) as $week) {
                $importer->rebuildCompanyParkAggregates((int) $week);
            }
            return $track;
        });
    }
}

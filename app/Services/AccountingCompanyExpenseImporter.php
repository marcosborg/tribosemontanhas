<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CompanyExpense;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AccountingCompanyExpenseImporter
{
    private const HEADERS = [
        'date' => ['data', 'column2'],
        'description' => ['descricao banco', 'descrição banco', 'descricao', 'descrição'],
        'value' => ['valor'],
        'expense_type' => ['nt', 'tipo', 'tipo despesa', 'tipo de despesa'],
    ];

    public function import(string $path, string $originalName, ?int $defaultCompanyId = null): array
    {
        $rows = $this->readRows($path, $originalName);
        if (count($rows) < 2) {
            throw new RuntimeException('O ficheiro nao contem linhas para importar.');
        }

        $columns = $this->resolveColumns(array_shift($rows));
        $types = collect(CompanyExpense::EXPENSE_TYPE_RADIO)
            ->flatMap(fn ($label, $key) => [$key, $label])
            ->mapWithKeys(fn ($value) => [$this->key($value) => (string) $value]);
        $imported = 0;
        $failed = [];

        DB::transaction(function () use ($rows, $columns, $types, $defaultCompanyId, &$imported, &$failed) {
            foreach ($rows as $index => $row) {
                if ($this->emptyRow($row)) {
                    continue;
                }

                $line = $index + 2;
                $typeRaw = $this->text($row[$columns['expense_type']] ?? null);
                $type = $typeRaw ? $types->get($this->key($typeRaw)) : null;
                $date = $this->date($row[$columns['date']] ?? null);
                $value = $this->amount($row[$columns['value']] ?? null);
                $vat = $columns['vat'] !== null ? $this->amount($row[$columns['vat']] ?? null, false) : 23.0;
                $invoiceValue = $columns['final_value'] !== null ? $this->amount($row[$columns['final_value']] ?? null) : null;
                $companyId = $defaultCompanyId ?: $this->companyId($row[$columns['company']] ?? null);
                $errors = [];

                if (!$companyId) $errors[] = 'Empresa inexistente';
                if (!$type) $errors[] = 'Tipo de despesa inexistente';
                if (!$date) $errors[] = 'Data invalida';
                if (!$value || $value <= 0) $errors[] = 'Valor invalido';
                if ($vat === null) $errors[] = 'IVA invalido';

                if (!$errors && CompanyExpense::query()
                    ->where('expense_mode', CompanyExpense::MODE_ACCOUNTING)
                    ->where('company_id', $companyId)
                    ->where('expense_type', $type)
                    ->whereDate('date', Carbon::createFromFormat(config('panel.date_format'), $date)->format('Y-m-d'))
                    ->where('value', number_format($value, 2, '.', ''))
                    ->exists()) {
                    $errors[] = 'Despesa ja existente';
                }

                if ($errors) {
                    $failed[] = ['line' => $line, 'company' => $this->text($row[$columns['company']] ?? null), 'expense_type' => $typeRaw, 'value' => $row[$columns['value']] ?? null, 'reason' => implode('; ', $errors)];
                    continue;
                }

                $description = $this->text($row[$columns['description']] ?? null);
                CompanyExpense::create([
                    'company_id' => $companyId,
                    'expense_mode' => CompanyExpense::MODE_ACCOUNTING,
                    'expense_type' => $type,
                    'date' => $date,
                    'description' => $description,
                    'value' => $value,
                    'invoice_value' => $invoiceValue,
                    'vat' => $vat,
                    'is_paid' => true,
                    'paid_at' => Carbon::createFromFormat(config('panel.date_format'), $date)->startOfDay(),
                    'payment_reference' => $description,
                    // Legacy required columns remain populated for backwards compatibility.
                    'name' => $type,
                    'weekly_value' => $value,
                    'start_date' => $date,
                    'end_date' => $date,
                    'qty' => 1,
                ]);
                $imported++;
            }
        });

        return compact('imported', 'failed');
    }

    private function readRows(string $path, string $name): array
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (in_array($extension, ['csv', 'txt'], true)) {
            $delimiter = $this->delimiter($path);
            $handle = fopen($path, 'r');
            if (!$handle) throw new RuntimeException('Nao foi possivel abrir o ficheiro.');
            $rows = [];
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) $rows[] = $row;
            fclose($handle);
            if (isset($rows[0][0])) $rows[0][0] = preg_replace('/^\xEF\xBB\xBF/', '', $rows[0][0]);
            return $rows;
        }

        if (!in_array($extension, ['xls', 'xlsx'], true)) {
            throw new RuntimeException('Formato nao suportado. Usa CSV, TXT, XLS ou XLSX.');
        }
        $copy = sys_get_temp_dir() . '/' . uniqid('company-expenses-', true) . '.' . $extension;
        copy($path, $copy);
        try {
            $reader = new \SpreadsheetReader($copy);
            return iterator_to_array($reader, false);
        } finally {
            @unlink($copy);
        }
    }

    private function resolveColumns(array $header): array
    {
        $normalized = [];
        foreach ($header as $index => $label) $normalized[$this->key($label)] = $index;
        $columns = [];
        foreach (self::HEADERS as $field => $candidates) {
            foreach ($candidates as $candidate) {
                if (array_key_exists($this->key($candidate), $normalized)) {
                    $columns[$field] = $normalized[$this->key($candidate)];
                    break;
                }
            }
            if (!isset($columns[$field])) throw new RuntimeException('Coluna obrigatoria em falta: ' . $field);
        }
        foreach (['company' => ['empresa', 'company'], 'vat' => ['iva', 'vat'], 'final_value' => ['valor final', 'valor total', 'total']] as $field => $candidates) {
            $columns[$field] = null;
            foreach ($candidates as $candidate) if (array_key_exists($this->key($candidate), $normalized)) $columns[$field] = $normalized[$this->key($candidate)];
        }
        return $columns;
    }

    private function companyId($value): ?int
    {
        $value = $this->text($value);
        if (!$value) return null;
        if (ctype_digit($value) && Company::whereKey((int) $value)->exists()) return (int) $value;
        return Company::whereRaw('LOWER(name) = ?', [mb_strtolower($value)])->value('id');
    }

    private function date($value): ?string
    {
        if ($value === null || $value === '') return null;
        if (is_numeric($value)) return Carbon::createFromDate(1899, 12, 30)->addDays((int) floor($value))->format(config('panel.date_format'));
        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y', 'd-m-y', 'd/m/y'] as $format) {
            try { return Carbon::createFromFormat($format, trim($value))->format(config('panel.date_format')); } catch (\Throwable $e) {}
        }
        return null;
    }

    private function amount($value, bool $absolute = true): ?float
    {
        if ($value === null || $value === '') return null;
        if (is_numeric($value)) return $absolute ? abs((float) $value) : (float) $value;
        $value = preg_replace('/[^0-9,.\-]/', '', str_replace(' ', '', (string) $value));
        if (str_contains($value, ',')) $value = str_replace(',', '.', str_replace('.', '', $value));
        return is_numeric($value) ? ($absolute ? abs((float) $value) : (float) $value) : null;
    }

    private function text($value): ?string { $value = trim(strip_tags((string) $value)); return $value === '' ? null : $value; }
    private function key($value): string { return mb_strtolower(trim((string) preg_replace('/\s+/', ' ', $value))); }
    private function emptyRow(array $row): bool { return count(array_filter($row, fn ($v) => $v !== null && trim((string) $v) !== '')) === 0; }
    private function delimiter(string $path): string { $line = (string) file($path)[0]; $scores = [',' => substr_count($line, ','), ';' => substr_count($line, ';'), "\t" => substr_count($line, "\t")]; arsort($scores); return (string) array_key_first($scores); }
}

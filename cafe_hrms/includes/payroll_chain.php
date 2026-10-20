<?php
/**
 * Payroll Chain - Cryptographic Integrity Verification for Payroll Runs
 *
 * Implements SHA-256 tamper-detection chain for the BHR -> BA -> BM approval flow.
 */

if (!function_exists('calculate_payroll_checksum')) {
    /**
     * Calculates the SHA-256 checksum and total centavos for a payroll run or record.
     *
     * Checksum formula:
     *   sha256 of concatenated "id:employee_id:gross_pay:deductions:net_pay" ordered by payroll ID.
     * Total Centavos:
     *   sum of net_pay in integer centavos (e.g. 100.50 -> 10050)
     *
     * @param PDO $pdo Database connection
     * @param int $payroll_run_id Run ID or Payroll ID
     * @return array [
     *     'checksum' => string|null,
     *     'total_centavos' => int,
     *     'records_count' => int,
     *     'records' => array
     * ]
     */
    function calculate_payroll_checksum($pdo, $payroll_run_id) {
        $runId = (int)$payroll_run_id;
        if ($runId <= 0) {
            return [
                'checksum' => null,
                'total_centavos' => 0,
                'records_count' => 0,
                'records' => []
            ];
        }

        // 1. Try querying records belonging to a run_id
        $stmt = $pdo->prepare("SELECT id, employee_id, gross_pay, deductions, net_pay FROM payroll WHERE run_id = ? ORDER BY id ASC");
        $stmt->execute([$runId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Fallback: If no records found by run_id, check if $payroll_run_id is an individual payroll record id
        if (empty($rows)) {
            $stmt = $pdo->prepare("SELECT id, employee_id, gross_pay, deductions, net_pay FROM payroll WHERE id = ? ORDER BY id ASC");
            $stmt->execute([$runId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        if (empty($rows)) {
            return [
                'checksum' => null,
                'total_centavos' => 0,
                'records_count' => 0,
                'records' => []
            ];
        }

        $entries = [];
        $totalCentavos = 0;

        foreach ($rows as $r) {
            $gross = number_format((float)$r['gross_pay'], 2, '.', '');
            $deductions = number_format((float)$r['deductions'], 2, '.', '');
            $net = number_format((float)$r['net_pay'], 2, '.', '');

            $entries[] = "{$r['id']}:{$r['employee_id']}:{$gross}:{$deductions}:{$net}";
            $totalCentavos += (int)round((float)$r['net_pay'] * 100);
        }

        $rawString = implode('|', $entries);
        $checksum = hash('sha256', $rawString);

        return [
            'checksum' => $checksum,
            'total_centavos' => $totalCentavos,
            'records_count' => count($rows),
            'records' => $rows
        ];
    }
}

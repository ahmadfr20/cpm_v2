<?php

namespace App\Controllers\FinishedGood;

use App\Controllers\BaseController;

class DeliveryControlBoardController extends BaseController
{
    /* ─────────────────────────────────────────────────────────────────────
    ───────────────────────────────────────────────────────────────────── */
    public static function getFixedDataPublic(): array
    {
        return [
            [
                'customer_name' => 'DENSO MANUFACTURING INDONESIA, PT',
                'parts' => [
                    'HOLDER-7100', 'HOLDER-7110', 'HOLDER-7690', 'HOLDER-7700', 'HOLDER-7710',
                    'HOLDER-7720', 'HOLDER-7791', 'HOLDER-7590', 'HOLDER-7600', 'HOLDER-7610',
                    'HOLDER-7620', 'HOLDER-9690', 'HOLDER-9700', 'HOLDER-9710', 'HOLDER-9720',
                    'HOLDER-9730', 'HOLDER-9740', 'HOLDER-9750', 'HOLDER-9760', 'HOLDER-0580',
                    'HOLDER-0590', 'HOLDER-0600', 'HOLDER-0610', 'HOLDER-1220', 'HOLDER-1230',
                    'HOLDER-1240', 'HOLDER-1250', 'HOLDER-1320', 'HOLDER-1330', 'HOLDER-1340',
                    'HOLDER-1350', 'HOLDER-1470', 'HOLDER-1481', 'HOLDER-1490', 'HOLDER-1501',
                    'HOLDER-1650', 'HOLDER-1660', 'HOLDER-1670', 'HOLDER-1680', 'HOLDER-1750',
                    'HOLDER-1760', 'HOLDER-1910', 'HOLDER-1920', 'HOLDER-2090', 'HOLDER-2100',
                    'HOLDER-2110', 'HOLDER-2120', 'Housing-0160', 'Housing-9710 C',
                ],
            ],
            [
                'customer_name' => 'SUZUKI INDOMOBIL MOTOR, PT',
                'parts' => [
                    'Case Comp Thermostat', 'CGSL APV', 'CWO', 'Bracket YR9', 'Cov.Gear Case 4JA',
                ],
            ],
            [
                'customer_name' => 'MESIN ISUZU INDONESIA, PT',
                'parts' => [
                    'Duct Thermostat', 'Duct Asm Water', 'Bracket Asm Generator', 'Plate MSG',
                ],
            ],
        ];
    }

    private function fixedKey(string $customer, string $part): string
    {
        return strtolower(trim($customer)) . '::' . strtolower(trim($part));
    }

    public function index()
    {
        $db   = db_connect();
        $date = $this->request->getGet('date') ?? date('Y-m-d');

        /* ── Load customers and products ── */
        $customers = $db->tableExists('customers')
            ? $db->table('customers')->orderBy('customer_name', 'ASC')->get()->getResultArray()
            : [];
        $custMap = [];
        foreach ($customers as $c) {
            $custMap[(int)$c['id']] = $c['customer_name'];
        }

        $products = $db->tableExists('products')
            ? $db->table('products')->where('is_active', 1)->orderBy('part_name', 'ASC')->get()->getResultArray()
            : [];
        $productByName = [];
        $productByNo   = [];
        $prodNameMap   = [];
        foreach ($products as $p) {
            $nameKey = preg_replace('/[^a-z0-9]/', '', strtolower($p['part_name']));
            $productByName[$nameKey] = (int)$p['id'];
            if (!empty($p['part_no'])) {
                $noKey = preg_replace('/[^a-z0-9]/', '', strtolower($p['part_no']));
                $productByNo[$noKey] = (int)$p['id'];
            }
            $prodNameMap[(int)$p['id']] = ($p['part_no'] ? $p['part_no'] . ' — ' : '') . $p['part_name'];
        }

        /* ── Load schedule for this date ── */
        $schedules = [];
        if ($db->tableExists('fg_delivery_schedules')) {
            $schedules = $db->table('fg_delivery_schedules')
                ->where('schedule_date', $date)
                ->get()->getResultArray();
        }

        $scheduleMap = []; // Group by customer_name::part_name or customer_id::product_id
        $customSchedules = [];
        foreach ($schedules as $s) {
            $cName = $custMap[(int)$s['customer_id']] ?? '';
            $pName = $prodNameMap[(int)$s['product_id']] ?? '';
            
            // Extract the core part name for matching fixed template (without Part No)
            $corePartName = '';
            foreach ($products as $p) {
                if ((int)$p['id'] === (int)$s['product_id']) {
                    $corePartName = $p['part_name'];
                    break;
                }
            }

            $k = $this->fixedKey($cName, $corePartName);
            $scheduleMap[$k] = $s;
            
            // Also keep original for custom items
            $customSchedules[] = [
                'sKey' => $k,
                'customer_name' => $cName,
                'part_name'     => $corePartName ?: $pName,
                'data' => $s
            ];
        }

        $displayGroups = [];   

        // 1) Fixed groups
        $usedSchedKeys = [];
        foreach (self::getFixedDataPublic() as $group) {
            $custName = $group['customer_name'];
            $displayGroups[$custName] = $displayGroups[$custName] ?? [];

            foreach ($group['parts'] as $partName) {
                $k      = $this->fixedKey($custName, $partName);
                $schedEntry = $scheduleMap[$k] ?? null;
                $usedSchedKeys[] = $k;

                $pid = 0;
                if ($schedEntry) {
                    $pid = (int)$schedEntry['product_id'];
                } else {
                    $searchKey = preg_replace('/[^a-z0-9]/', '', strtolower($partName));
                    $pid = $productByName[$searchKey] ?? $productByNo[$searchKey] ?? 0;
                }

                $displayGroups[$custName][] = [
                    'is_fixed'      => 1,
                    'customer_name' => $custName,
                    'product_id'    => $pid,
                    'part_name'     => $partName,
                    'sched'         => $schedEntry
                ];
            }
        }

        // 2) Custom row schedules (if an item is in schedule but not in fixed)
        foreach ($customSchedules as $cs) {
            if (in_array($cs['sKey'], $usedSchedKeys)) continue; // Already generated in fixed

            $cName = $cs['customer_name'];
            $displayGroups[$cName][] = [
                'is_fixed'      => 0,
                'customer_name' => $cName,
                'product_id'    => (int)$cs['data']['product_id'],
                'part_name'     => $cs['part_name'],
                'sched'         => $cs['data']
            ];
        }

        /* ── Actual delivery per product_id per RIT for this date ── */
        $actualMap = [];
        if ($db->tableExists('fg_delivery_items') && $db->tableExists('fg_deliveries')) {
            $ritCol = $db->fieldExists('rit', 'fg_delivery_items') ? 'di.rit' : "'RIT-1'";
            $rows = $db->query("
                SELECT di.product_id, {$ritCol} AS rit, SUM(di.qty) AS total_qty
                FROM fg_delivery_items di
                JOIN fg_deliveries d ON d.id = di.fg_delivery_id
                WHERE d.delivery_date = ?
                GROUP BY di.product_id, {$ritCol}
            ", [$date])->getResultArray();

            foreach ($rows as $r) {
                $pid = (int)$r['product_id'];
                $rit = str_replace(' ', '', strtoupper($r['rit'])) ?: 'RIT-1';
                $actualMap[$pid][$rit] = (int)$r['total_qty'];
            }
        }

        return view('finished_good/delivery_control_board/index', [
            'date'           => $date,
            'displayGroups'  => $displayGroups,
            'actualMap'      => $actualMap,
            'fixedCustomers' => array_column(self::getFixedDataPublic(), 'customer_name'),
            'logged_in'      => (bool) session()->get('logged_in'),
        ]);
    }
}

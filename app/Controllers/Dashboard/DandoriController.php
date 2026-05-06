<?php

namespace App\Controllers\Dashboard;

use App\Controllers\BaseController;

class DandoriController extends BaseController
{
    public function index()
    {
        $db = db_connect();

        // --- Resolusi Rentang Tanggal ---
        $dateRange  = $this->request->getGet('date_range') ?? 'today';
        $dateFrom   = $this->request->getGet('date_from') ?? date('Y-m-d');
        $dateTo     = $this->request->getGet('date_to')   ?? date('Y-m-d');

        switch ($dateRange) {
            case 'last5':
                $dateFrom = date('Y-m-d', strtotime('-4 days'));
                $dateTo   = date('Y-m-d');
                break;
            case 'last7':
                $dateFrom = date('Y-m-d', strtotime('-6 days'));
                $dateTo   = date('Y-m-d');
                break;
            case 'last14':
                $dateFrom = date('Y-m-d', strtotime('-13 days'));
                $dateTo   = date('Y-m-d');
                break;
            case 'last30':
                $dateFrom = date('Y-m-d', strtotime('-29 days'));
                $dateTo   = date('Y-m-d');
                break;
            case 'custom':
                // dateFrom & dateTo dari GET sudah di-set di atas
                // Validasi agar dateFrom <= dateTo
                if ($dateFrom > $dateTo) {
                    $tmp      = $dateFrom;
                    $dateFrom = $dateTo;
                    $dateTo   = $tmp;
                }
                break;
            default: // 'today'
                $dateRange = 'today';
                $dateFrom  = date('Y-m-d');
                $dateTo    = date('Y-m-d');
                break;
        }

        // Data Dandori Die Casting
        $builderDC = $db->table('die_casting_dandori dcd')
            ->select("
                'Die Casting' as section,
                dcd.dandori_date,
                s.shift_name,
                m.machine_code,
                m.line_position,
                p.part_no,
                p.part_name,
                ts.time_start,
                ts.time_end,
                dcd.dandori_minute,
                dcd.activity
            ")
            ->join('shifts s', 's.id = dcd.shift_id')
            ->join('machines m', 'm.id = dcd.machine_id', 'left')
            ->join('products p', 'p.id = dcd.product_id', 'left')
            ->join('time_slots ts', 'ts.id = dcd.time_slot_id', 'left')
            ->where('dcd.dandori_date >=', $dateFrom)
            ->where('dcd.dandori_date <=', $dateTo);

        // Data Dandori Machining
        $builderMC = $db->table('machining_dandori mcd')
            ->select("
                'Machining' as section,
                mcd.dandori_date,
                s.shift_name,
                m.machine_code,
                m.line_position,
                p.part_no,
                p.part_name,
                ts.time_start,
                ts.time_end,
                mcd.dandori_minute,
                mcd.activity
            ")
            ->join('shifts s', 's.id = mcd.shift_id')
            ->join('machines m', 'm.id = mcd.machine_id', 'left')
            ->join('products p', 'p.id = mcd.product_id', 'left')
            ->join('time_slots ts', 'ts.id = mcd.time_slot_id', 'left')
            ->where('mcd.dandori_date >=', $dateFrom)
            ->where('mcd.dandori_date <=', $dateTo);

        // Fetch Data
        try {
            $dcData = $builderDC->orderBy('dcd.dandori_date', 'DESC')->get()->getResultArray();
        } catch (\Exception $e) {
            $dcData = [];
        }

        try {
            $mcData = $builderMC->orderBy('mcd.dandori_date', 'DESC')->get()->getResultArray();
        } catch (\Exception $e) {
            $mcData = [];
        }

        $allData = array_merge($dcData, $mcData);

        // Sort gabungan: tanggal DESC
        usort($allData, function ($a, $b) {
            return strcmp($b['dandori_date'] ?? '', $a['dandori_date'] ?? '');
        });

        return view('dashboard/dandori/index', [
            'date_range' => $dateRange,
            'date_from'  => $dateFrom,
            'date_to'    => $dateTo,
            'data'       => $allData
        ]);
    }
}

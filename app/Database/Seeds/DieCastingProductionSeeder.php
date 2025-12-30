<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class DieCastingProductionSeeder extends Seeder
{
    public function run()
    {
        $db = db_connect();

        $today = date('Y-m-d');

        // 1️⃣ Ambil schedule Die Casting HARI INI
        $schedules = $db->table('daily_schedule_items dsi')
            ->select('
                ds.schedule_date,
                ds.shift_id,
                dsi.machine_id,
                dsi.product_id,
                dsi.target_per_shift
            ')
            ->join('daily_schedules ds', 'ds.id = dsi.daily_schedule_id')
            ->where('LOWER(ds.section)', 'die casting')
            ->where('ds.schedule_date', $today)
            ->get()
            ->getResultArray();

        // 2️⃣ Kalau tidak ada hari ini → ambil schedule TERAKHIR
        if (empty($schedules)) {
            $schedules = $db->table('daily_schedule_items dsi')
                ->select('
                    ds.schedule_date,
                    ds.shift_id,
                    dsi.machine_id,
                    dsi.product_id,
                    dsi.target_per_shift
                ')
                ->join('daily_schedules ds', 'ds.id = dsi.daily_schedule_id')
                ->where('LOWER(ds.section)', 'die casting')
                ->orderBy('ds.schedule_date', 'DESC')
                ->get()
                ->getResultArray();
        }

        // 3️⃣ Kalau MASIH kosong → buat dummy minimal
        if (empty($schedules)) {
            echo "⚠️ Tidak ada Daily Schedule Die Casting sama sekali, membuat dummy data...\n";

            $machines = $db->table('machines')->limit(3)->get()->getResultArray();
            $products = $db->table('products')->limit(3)->get()->getResultArray();
            $shifts   = $db->table('shifts')->get()->getResultArray();

            if (empty($machines) || empty($products) || empty($shifts)) {
                echo "❌ Data master (machine/product/shift) belum lengkap\n";
                return;
            }

            $rows = [];
            foreach ($machines as $m) {
                foreach ($shifts as $s) {
                    $qty_a  = rand(600, 1000);
                    $qty_ng = rand(10, 40);

                    $rows[] = [
                        'production_date' => $today,
                        'shift_id'        => $s['id'],
                        'machine_id'      => $m['id'],
                        'product_id'      => $products[array_rand($products)]['id'],
                        'qty_p'           => $qty_a + $qty_ng,
                        'qty_a'           => $qty_a,
                        'qty_ng'          => $qty_ng,
                        'weight_kg'       => round($qty_a * rand(8, 15) / 100, 1),
                    ];
                }
            }

            $db->table('die_casting_production')->insertBatch($rows);
            echo "✅ Dummy Die Casting Production seed berhasil dibuat (" . count($rows) . " rows)\n";
            return;
        }

        // 4️⃣ Seed berdasarkan daily schedule
        $rows = [];

        foreach ($schedules as $sch) {
            $target = (int) $sch['target_per_shift'];

            $qty_a  = rand((int)($target * 0.7), $target);
            $qty_ng = rand(0, (int)($qty_a * 0.05));
            $qty_p  = $qty_a + $qty_ng;

            $rows[] = [
                'production_date' => $today,
                'shift_id'        => $sch['shift_id'],
                'machine_id'      => $sch['machine_id'],
                'product_id'      => $sch['product_id'],
                'qty_p'           => $qty_p,
                'qty_a'           => $qty_a,
                'qty_ng'          => $qty_ng,
                'weight_kg'       => round($qty_a * rand(8, 15) / 100, 1),
            ];
        }

        $db->table('die_casting_production')->insertBatch($rows);

        echo "✅ Die Casting Production seed berhasil dibuat (" . count($rows) . " rows)\n";
    }
}

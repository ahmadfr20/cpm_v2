<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class MasterProductsSeeder extends Seeder
{
    public function run()
    {
        $db = \Config\Database::connect();

        // =========================
        // 1) DATA BARU PRODUCTS
        // format: [customer_name, part_no, part_name]
        // =========================
        $rows = [
            ['FG - Sandy Globalindo, PT', 'SND01', 'BLOK SND 01 (CASTING) X-ONE/ JUPITER'],
            ['FG - Sandy Globalindo, PT', 'SND02', 'BLOK SND 02 (CASTING) SUPRA'],
            ['FG - Sandy Globalindo, PT', 'SND03', 'BLOK SND 03 (CASTING) KHARISMA'],
            ['FG - Sandy Globalindo, PT', 'SND04', 'BLOK SND 04 (CASTING) BLADE'],
            ['FG - Sandy Globalindo, PT', 'SND05', 'BLOK SND 05 (CASTING) KHARISMA'],
            ['FG - Hega Industri Indonesia,PT', 'B1-2705', 'BODY STORMA X'],
            ['FG - Hega Industri Indonesia,PT', '8997209891541', 'BOTTOM STROMA 1-POLOS'],
            ['FG - Egi Optik Indonesia, PT', '9835.01.01.005', 'BRACKET 005'],
            ['FG - Mesin Isuzu Indonesia, PT', 'MRM-8980826151', 'BRACKET ASM : GENERATOR'],
            ['FG - Mesin Isuzu Indonesia, PT', 'MRM- 8975208530', 'BRACKET ASM : GENERATOR'],
            ['FG - Suzuki Indomobil Sales, PT', '11651-71LGO-000S', 'BRACKET ENG LH MTG'],
            ['FG - Suzuki Indomobil Sales, PT', '11751-74LA0-000S', 'BRACKET ENG LH MTG NO. 2'],
            ['FG - Suzuki Indomobil Motor, PT', '11749-68K01-000', 'BRACKET, ENG RR MTG (YR9)'],
            ['FG - Suzuki Indomobil Sales, PT', '11749-68K01-000S', 'BRACKET, ENG RR MTG (YR9) S'],
            ['FG - Egi Optik Indonesia, PT', '9835.01.07.002', 'CAP 002'],
            ['FG - Suzuki Indomobil Sales, PT', '17561-68K00-000', 'CAP WATER THERMO'],
            ['FG - Suzuki Indomobil Sales, PT', '17561-79100-000', 'CAP WATER THERMOSTAT'],
            ['FG - Suzuki Indomobil Motor, PT', '17570-80C00', 'CAP, WATER OUTLET'],
            ['FG - Suzuki Indomobil Sales, PT', '17570-80C00-000S', 'CAP, WATER OUTLET S'],
            ['FG - Suzuki Indomobil Sales, PT', '17561-73000-000S', 'CAP, WATER THERMOSTAT'],
            ['FG - Kiyokuni Indonesia, PT', '1798456-00', 'CARRIAGE ASSEMBLY; B'],
            ['FG - Indonesia Epson Industry, PT', '179845600', 'CARRIAGE ASSEMBLY;B ASP'],
            ['FG - Patco Elektronik Teknologi, PT', '1875161-01', 'CARRIAGE ASSY.,SUB -LOTUS 2  ( MP )'],
            ['FG - Patco Elektronik Teknologi, PT', '1906369-00', 'CARRIAGE SUB ASSY;B (FTI-2)'],
            ['FG - Egi Optik Indonesia, PT', '9835.01.00.065', 'CASE 065'],
            ['FG - Suzuki Indomobil Sales, PT', '25121-60K00-000S', 'CASE COMP GEAR SHIFT LEVER'],
            ['FG - Suzuki Indomobil Motor, PT', '17690-52S00-0001', 'CASE COMP. THERMOSTAT'],
            ['FG - Mesin Isuzu Indonesia, PT', '8980947990', 'CASE FRONT RT 50'],
            ['FG - Suzuki Indomobil Sales, PT', '17690-52S00-000S', 'CASE THERMOSTAT'],
            ['FG - Suzuki Indomobil Motor, PT', '25121-60K00-000', 'CASE, GEAR SHIFT LEVER'],
            ['FG - Suzuki Indomobil Sales, PT', '11121-30A01-L000S', 'CCH LH'],
            ['FG - Suzuki Indomobil Sales, PT', 'CCHR1', 'CCH R1'],
            ['FG - Suzuki Indomobil Sales, PT', 'CCHR2', 'CCH R2'],
            ['FG - Suzuki Indomobil Sales, PT', '11121-B09G1-0N000S', 'CCHLH'],
            ['FG - Suzuki Indomobil Sales, PT', '11134-09D01L000S', 'COV CYL HEAD FD110-CDT'],
            ['FG - Suzuki Indomobil Sales, PT', '11171-B46G0-0N000S', 'COV CYL HEAD UY'],
            ['FG - Suzuki Indomobil Sales, PT', 'CBC01', 'COVER BELT COOLING'],
            ['FG - Suzuki Indomobil Sales, PT', '11149-09D01L000S', 'COVER CYL HEAD NO. 2 FD110-CDT'],
            ['FG - Suzuki Indomobil Sales, PT', '11121-30A01L000', 'COVER CYLINDER HEAD LH CCH UW-125 AFT. BURRY'],
            ['FG - Suzuki Indomobil Sales, PT', '11170-13H00-000', 'COVER CYLINDER HEAD UW 125'],
            ['FG - Suzuki Indomobil Sales, PT', '11134-09D01L000', 'COVER CYLINDER HEAD UW 125 AFT-BURRY'],
            ['FG - Suzuki Indomobil Sales, PT', '11170-13H00-000S', 'COVER CYLINDER HEAD UW125 SC'],
            ['FG - Suzuki Indomobil Sales, PT', '11171-B46G00N000', 'COVER CYLINDER HEAD UY 125'],
            ['FG - Mesin Isuzu Indonesia, PT', 'MRM-8973064312', 'COVER GEAR CASE 4JA'],
            ['FG - Yasunaga Indonesia, PT', '823LW25000', 'COVER L W'],
            ['FG - Yasunaga Indonesia, PT', '823LW25000P', 'COVER L W PROCESSING'],
            ['FG - Suzuki Indomobil Sales, PT', '11351-23F71260', 'COVER MAGNET XB-511'],
            ['FG - Suzuki Indomobil Sales, PT', '11351-B45H00NOP6', 'COVER MAGNET XC-601'],
            ['FG - Sandy Globalindo, PT', 'COVEROIL-01', 'COVER OIL'],
            ['FG - Hega Industri Indonesia,PT', 'C1-0735', 'COVER STROMA X-INFITE LOGO'],
            ['FG - Sharprindo Dinamika Prima, PT', 'CRC-FSE168/SP200', 'CRANK CASE ENGINE SE 168S/SP 200'],
            ['FG - Mesin Isuzu Indonesia, PT', 'MRM-8975208300DOM', 'DUCT ASM: WATER BY PASS'],
            ['FG - Mesin Isuzu Indonesia, PT', 'MRM-8975208300EXP', 'DUCT ASM: WATER BY PASS EXP'],
            ['FG - Mesin Isuzu Indonesia, PT', 'MRM- 8975208290', 'DUCT THERMOSTAT'],
            ['FG - Mesin Isuzu Indonesia, PT', 'MRM-8975208290DOM', 'DUCT THERMOSTAT'],
            ['FG - Mesin Isuzu Indonesia, PT', 'MRM - 8975208290', 'DUCT THERMOSTAT'],
            ['FG - Mesin Isuzu Indonesia, PT', 'MRM-8975208290EXP', 'DUCT THERMOSTAT EXP'],
            ['FG - Sharprindo Dinamika Prima, PT', 'MTN-000936', 'EJECTOR ROD DIA. 36X600x480 M24'],
            ['FG - Teckindo Prima Gemilang Jaya, PT', 'FGP30HD', 'FGP30HD Pump Body'],
            ['FG - Teckindo Prima Gemilang Jaya, PT', 'FGP30HD.00', 'FGP30HD Pump Cover'],
            ['FG - Teckindo Prima Gemilang Jaya, PT', 'FGP30STD.18', 'FGP30STD.18 Water Inlet'],
            ['FG - Teckindo Prima Gemilang Jaya, PT', 'FGP30STD.26', 'FGP30STD.26 Water Outlet'],
            ['FG - Sharprindo Dinamika Prima, PT', 'GA-000661', 'GENERAL CHECK MOBIL ERTIGA B 2308 FFZ'],
            ['FG - Electra Mobilitas Indonesia ,PT', '10-FT-1X011-01-03', 'HANDLEBAR RISER CLAMP'],
            ['FG - Electra Mobilitas Indonesia ,PT', '28-A00530-0001', 'HANDLEBAR RISER CLAMP BLACK MATTE'],
            ['FG - Electra Distribusi Indonesia ,PT', '10-FT-1X011-01-03F', 'HANDLEBAR RISER CLAMP F'],

            // HOLDER series (Denso)
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159889-2090', 'HOLDER -2090'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159889-2100', 'HOLDER -2100'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159888-0580', 'HOLDER-0580'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159889-0590', 'HOLDER-0590'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159888-0600', 'HOLDER-0600'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159889-0610', 'HOLDER-0610'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159889-1220', 'HOLDER-1220'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159889-1230', 'HOLDER-1230'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159889-1240', 'HOLDER-1240'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159889-1250', 'HOLDER-1250'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159889-1320', 'HOLDER-1320'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159889-1330', 'HOLDER-1330'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159889-1340', 'HOLDER-1340'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159889-1350', 'HOLDER-1350'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159889-1470', 'HOLDER-1470'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159889-1481', 'HOLDER-1481'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159889-1490', 'HOLDER-1490'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159889-1501', 'HOLDER-1501'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159889-1650', 'HOLDER-1650'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159889-1660', 'HOLDER-1660'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159889-1670', 'HOLDER-1670'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159889-1680', 'HOLDER-1680'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159889-1750', 'HOLDER-1750'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159889-1760', 'HOLDER-1760'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159889-1910', 'HOLDER-1910'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159889-1920', 'HOLDER-1920'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159889-2110', 'HOLDER-2110'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159889-2120', 'HOLDER-2120'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159878-4030', 'HOLDER-4030'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159878-4490', 'HOLDER-4490'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159878-4510', 'HOLDER-4510'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159878-5090', 'HOLDER-5090'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159878-5130', 'HOLDER-5130'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159878-5140', 'HOLDER-5140'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159878-5571', 'HOLDER-5571'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159878-7080', 'HOLDER-7080'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159878-7090', 'HOLDER-7090'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159878-7100', 'HOLDER-7100'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159878-7110', 'HOLDER-7110'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159879-7590', 'HOLDER-7590'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159879-7600', 'HOLDER-7600'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159879-7610', 'HOLDER-7610'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159879-7620', 'HOLDER-7620'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159878-7690', 'HOLDER-7690'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159878-7700', 'HOLDER-7700'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159878-7710', 'HOLDER-7710'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159878-7720', 'HOLDER-7720'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159878-7790', 'HOLDER-7790'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159878-7791', 'HOLDER-7791'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159878-8440', 'HOLDER-8440'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159878-8450', 'HOLDER-8450'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159878-9690', 'HOLDER-9690'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159878-9700', 'HOLDER-9700'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159878-9710', 'HOLDER-9710'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159878-9720', 'HOLDER-9720'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159879-9730', 'HOLDER-9730'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159879-9740', 'HOLDER-9740'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159879-9750', 'HOLDER-9750'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE159879-9760', 'HOLDER-9760'],

            ['FG - Sharprindo Dinamika Prima, PT', 'MTN-001028', 'HOSE BRAIDED WB-32x127CM FITTING FEMALE STxST'],
            ['FG - Sharprindo Dinamika Prima, PT', 'MTN-000976', 'HOSE HYDRAULIC COA-6x165CM FITTING FEMALE STxST INCLUDE ELBOW 3/4 STRAIGHT'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE059111-0160', 'HOUSING-0160'],
            ['FG - Denso Manufacturing Indonesia, PT', 'AE059111-9710C', 'HOUSING-9710 C'],
            ['FG - Suzuki Indomobil Sales, PT', '17131-80000-000S', 'HUB, ENGINE COOLING FAN'],
            ['FG - Sharprindo Dinamika Prima, PT', 'MTN-000940', 'INSERT PIN CASE COMP #2 MOVE DIA. 10x62.42'],
            ['FG - Sharprindo Dinamika Prima, PT', 'MTN-000899', 'INSERT PIN COVER LW #2 DIA.11x58.75MM (INCLUDE COATING FOMERA)'],
            ['FG - Mesin Isuzu Indonesia, PT', '8980422950', 'INTERMEDIATE PLATE MUX (XPI) 8971702040'],
            ['FG - Sharprindo Dinamika Prima, PT', 'MTN-000970', 'INVERTER TOSHIBA VFNC3-2002P 3PH 200/240 50/60HZ'],
            ['FG - Sharprindo Dinamika Prima, PT', 'GA-000597', 'KABEL ISI 4 NYYHY 4x2.5'],
            ['FG - Sharprindo Dinamika Prima, PT', 'MTN-000912', 'KUNCI MATA SHOCK BAUT ROOFING MAGNETIC BOR DIA.8x65MM'],
            ['FG - Sharprindo Dinamika Prima, PT', 'GA-000652', 'LEMARI VS 602 SPESIFIKASI : LEMARI PINTU SLIDING KACA UK. W85xD40xH180CM, BAHAN PLAT TEBAL 0.5MM INCLUDE FINISHING POWDER COATING MERK VIP STAR'],
            ['FG - Electra Distribusi Indonesia ,PT', '50-SA-1X025-13-04F', 'LICENSE PLATE HOLDER BRACKET PRODUCT MATTE F'],
            ['FG - Electra Mobilitas Indonesia ,PT', '260-00100-0103', 'LICENSE PLATE HOLDER BRACKET PRODUCT P.24'],
            ['FG - Nakakin Indonesia, PT', '16111-1CPM', 'MANIFOLD 2DP'],
            ['FG - Nakakin Indonesia, PT', 'ME-221779', 'MANIFOLD INLET A ME221779'],
            ['FG - Nakakin Indonesia, PT', 'ME-223992', 'MANIFOLD INLET B ME223992'],
            ['FG - Electra Distribusi Indonesia ,PT', '70-PT-1X020-00-02F', 'MOTOR SPACER/ADAPTER F'],
            ['FG - Electra Mobilitas Indonesia ,PT', '40-FR-1X021-1301-C200', 'PASSANGER REAR HANDLE ( BLANK )'],
            ['FG - Electra Mobilitas Indonesia ,PT', '40-FR-1X013-01-05', 'PASSENGER FOOTPEG LEFT'],
            ['FG - Electra Mobilitas Indonesia ,PT', '25-A00512-0001', 'PASSENGER FOOTPEG LEFT BLACK MATTE'],
            ['FG - Electra Distribusi Indonesia ,PT', '40-FR-1X013-01-05F', 'PASSENGER FOOTPEG LEFT F'],
            ['FG - Electra Mobilitas Indonesia ,PT', '40-FR-1X014-01-05', 'PASSENGER FOOTPEG RIGHT'],
            ['FG - Electra Mobilitas Indonesia ,PT', '25-A00511-0001', 'PASSENGER FOOTPEG RIGHT BLACK MATTE'],
            ['FG - Electra Distribusi Indonesia ,PT', '40-FR-1X014-01-05F', 'PASSENGER FOOTPEG RIGHT F'],
            ['FG - Electra Mobilitas Indonesia ,PT', '40-FR-1X021-13-01', 'PASSENGER REAR HANDLE BLACK MATTE'],
            ['FG - Electra Distribusi Indonesia ,PT', '40-FR-1X021-13-01F', 'PASSENGER REAR HANDLE MATTE F'],
            ['FG - Electra Mobilitas Indonesia ,PT', '40-FR-1X021-C2-00', 'PASSENGER REAR HANDLE WHITE'],
            ['FG - Electra Distribusi Indonesia ,PT', '40-FR-1X021-C2-00F', 'PASSENGER REAR HANDLE WHITE F'],
            ['FG - Mesin Isuzu Indonesia, PT', 'MRM-8971702040', 'PLATE INTERMEDIATE MSG'],
            ['FG - Hega Industri Indonesia,PT', '8997209891602', 'POLE SHAFT'],
            ['FG - Electra Mobilitas Indonesia ,PT', '70-PT-1X020-00-02', 'QS 138 MOTOR SPACER ADAPTER'],
            ['FG - Sharprindo Dinamika Prima, PT', 'GA-000628', 'REFILL APAR POWDER 50KG'],
            ['FG - Sharprindo Dinamika Prima, PT', 'GA-000460', 'SAKLAR ENGKEL BROCO O/B'],
            ['FG - Sharprindo Dinamika Prima, PT', 'GA-000636', 'SELANG KB 3/16x40CM + BENYO'],
            ['FG - Hega Industri Indonesia,PT', '8997209891565', 'SIDE CLIP STROMA 1'],
            ['FG - Sharprindo Dinamika Prima, PT', 'MTN-000901', 'SOLIDE TYRE AICHI 700-12'],
            ['FG - Shin Heung Indonesia, PT', 'LANGKF570WJ62', 'STAND BASE ANGLE L'],
            ['FG - Shin Heung Indonesia, PT', 'LANGKF571WJ62', 'STAND BASE ANGLE R'],
            ['FG - Electra Mobilitas Indonesia ,PT', '10-FT-1X008-01-03', 'STEERING SHAFT BASE'],
            ['FG - Electra Mobilitas Indonesia ,PT', '10-FT-1X008-01-02', 'STEERING SHAFT BASE - EMI'],
            ['FG - Electra Mobilitas Indonesia ,PT', '28-A00519-0001', 'STEERING SHAFT BASE BLACK MATTE'],
            ['FG - Electra Distribusi Indonesia ,PT', '10-FT-1X008-01-02F', 'STEERING SHAFT BASE F'],
            ['FG - Yasunaga Indonesia, PT', '813LW40001', 'TANK L W N'],
            ['FG - Nakakin Indonesia, PT', 'ME014341-L1', 'THERMOSTAT'],
            ['FG - Nakakin Indonesia, PT', 'A400-203-00-73', 'THERMOSTAT HOUSING'],
            ['FG - Hega Industri Indonesia,PT', '8997209891558', 'TOP COVER STROMA 1-POLOS'],
            ['FG - Hega Industri Indonesia,PT', '8997209891558--1565', 'TOP COVER STROMA 1-POLOS-SIDE CLIP STROMA 1'],
            ['FG - Electra Mobilitas Indonesia ,PT', '28-A00530-TX00', 'TX-HANDLE BAR RISER CLAMP'],
            ['FG - Electra Mobilitas Indonesia ,PT', '25-A00511-TX00', 'TX-PASSENGER FOOTPEG LEFT'],
            ['FG - Electra Mobilitas Indonesia ,PT', '25-A00512-TX00', 'TX-PASSENGER FOOTPEG RIGHT'],
            ['FG - Electra Mobilitas Indonesia ,PT', '28-A00519-TX00', 'TX-STEERING SHAFT BASE'],
            ['FG - Mesin Isuzu Indonesia, PT', '8980358811', 'XCF - CASE FRONT MUX 8980358811'],
        ];

        // =========================
        // 2) Hitung nama unik di data baru
        //    (dipakai untuk aturan "nama sama tapi part_no beda")
        // =========================
        $nameCount = [];
        foreach ($rows as $r) {
            $pn = $this->normPartName($r[2]);
            $nameCount[$pn] = ($nameCount[$pn] ?? 0) + 1;
        }

        // =========================
        // 3) Upsert Customers + Products
        // =========================
        foreach ($rows as $r) {
            [$custRaw, $partNoRaw, $partNameRaw] = $r;

            $custName = $this->normCustomerName($custRaw);
            $partNo   = $this->normPartNo($partNoRaw);
            $partName = $this->normPartName($partNameRaw);

            if ($partNo === '' || $partName === '' || $custName === '') {
                continue;
            }

            $customerId = $this->getOrCreateCustomerId($db, $custName);

            // 1) Jika part_no sudah ada -> update
            $existingByPartNo = $db->table('products')
                ->select('id, part_no, part_name')
                ->where('part_no', $partNo)
                ->get()->getRowArray();

            if ($existingByPartNo) {
                $db->table('products')
                    ->where('id', $existingByPartNo['id'])
                    ->update([
                        'part_name'   => $partName,
                        'customer_id' => $customerId,
                        'is_active'   => 1,
                        'updated_at'  => date('Y-m-d H:i:s'),
                    ]);
                continue;
            }

            // 2) part_no belum ada:
            //    kalau nama unik di data baru dan ada di DB -> update part_no + customer_id
            $pnKey = $this->normPartName($partName);
            if (($nameCount[$pnKey] ?? 0) === 1) {
                $existingByName = $db->table('products')
                    ->select('id, part_no, part_name')
                    ->where('part_name', $partName)
                    ->get()->getRowArray();

                if ($existingByName) {
                    $db->table('products')
                        ->where('id', $existingByName['id'])
                        ->update([
                            'part_no'     => $partNo,
                            'customer_id' => $customerId,
                            'is_active'   => 1,
                            'updated_at'  => date('Y-m-d H:i:s'),
                        ]);
                    continue;
                }
            }

            // 3) Insert produk baru
            $db->table('products')->insert([
                'part_no'         => $partNo,
                'part_name'       => $partName,
                'customer_id'     => $customerId,
                // biarkan default table: cycle_time=40, cavity=2, efficiency_rate=100
                'is_active'       => 1,
                'created_at'      => date('Y-m-d H:i:s'),
                'updated_at'      => date('Y-m-d H:i:s'),
            ]);
        }
    }

    // =========================
    // Helpers
    // =========================
    private function normCustomerName(string $s): string
    {
        $s = trim($s);
        $s = preg_replace('/^FG\s*-\s*/i', '', $s); // hapus "FG -"
        $s = str_replace([' ,', '  '], [',', ' '], $s);
        $s = str_replace(' ,PT', ', PT', $s);
        return trim($s);
    }

    private function normPartNo(string $s): string
    {
        $s = trim($s);
        $s = preg_replace('/\s+/', ' ', $s); // rapikan spasi
        return $s;
    }

    private function normPartName(string $s): string
    {
        $s = trim($s);
        $s = preg_replace('/\s+/', ' ', $s);
        return $s;
    }

    private function getOrCreateCustomerId($db, string $customerName): int
    {
        $row = $db->table('customers')
            ->select('id')
            ->where('customer_name', $customerName)
            ->get()->getRowArray();

        if ($row) {
            return (int) $row['id'];
        }

        // bikin customer_code otomatis yg unik
        $base = 'AUTO';
        $suffix = date('ymdHis');
        $code = $base . '-' . $suffix;

        // pastikan unik (rare collision)
        $exists = $db->table('customers')->where('customer_code', $code)->countAllResults();
        if ($exists) {
            $code .= '-' . rand(100, 999);
        }

        $db->table('customers')->insert([
            'customer_code' => $code,
            'customer_name' => $customerName,
            'created_at'    => date('Y-m-d H:i:s'),
        ]);

        return (int) $db->insertID();
    }
}

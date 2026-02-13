<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class ProductsBulkUpdateSeeder extends Seeder
{
    public function run()
    {
        $db = db_connect();

        // ============================================================
        // DATA: format kolom:
        // No | Part Prod | Part No | Product Name | Customer | Ascas | Runner | CT | Cavity | Eff | Notes
        // ============================================================
        $tsv = <<<'TSV'
1	Blok SND 01	SND01	BLOK SND 01 (CASTING) X-ONE/ JUPITER	SANDY GLOBALINDO, PT	1,255	745	75	1	100.00	
2	Blok SND 02	SND02	BLOK SND 02 (CASTING) SUPRA	SANDY GLOBALINDO, PT	1,22	780	75	1	100.00	
3	Blok SND 03	SND03	BLOK SND 03 (CASTING) KHARISMA	SANDY GLOBALINDO, PT	1,09	910	75	1	100.00	
4	Blok SND 04	SND04	BLOK SND 04 (CASTING) BLADE	SANDY GLOBALINDO, PT	1,11	345	75	1	100.00	
5	Blok SND 05	SND05	BLOK SND 05 (CASTING) KHARISMA	SANDY GLOBALINDO, PT	1,22	345	75	1	100.00	
6	Body StromaX	B1-2705	BODY STORMA X	Hega Industri Indonesia,PT	520	480	105	1	100.00	
7	Bottom Stroma -1	8,99721E+12	BOTTOM STROMA 1-POLOS	Hega Industri Indonesia,PT	520	480	105	1	100.00	
8	-	9835.01.01.005	BRACKET 005	Egi Optik Indonesia, PT	0	0	40	2	100.00	-
9	Bracket ASM Generator	MRM-8980826151	BRACKET ASM : GENERATOR	MESIN ISUZU INDONESIA, PT	2,085	830	75	1	100.00	
10	Bracket ASM Generator	MRM- 8975208530	BRACKET ASM : GENERATOR	MESIN ISUZU INDONESIA, PT	2,085	830	75	1	100.00	
11	Bracket-71	11651-71LGO-000S	BRACKET ENG LH MTG	SUZUKI INDOMOBIL SALES, PT	880	1,621	72	2	100.00	
12	Bracket-74	11751-74LA0-000S	BRACKET ENG LH MTG NO. 2	SUZUKI INDOMOBIL SALES, PT	1,33	691	58	1	100.00	
13	Bracket YR-9	11749-68K01-000	BRACKET, ENG RR MTG (YR9)	SUZUKI INDOMOBIL MOTOR, PT	965	555	60	1	100.00	
14	Bracket YR-9	11749-68K01-000S	BRACKET, ENG RR MTG (YR9) S	SUZUKI INDOMOBIL SALES, PT	965	555	60	1	100.00	
15	-	9835.01.07.002	CAP 002	Egi Optik Indonesia, PT	0	0	40	2	100.00	-
16	-	17561-68K00-000	CAP WATER THERMO	SUZUKI INDOMOBIL SALES, PT	0	0	40	2	100.00	-
17	-	17561-79100-000	CAP WATER THERMOSTAT	SUZUKI INDOMOBIL SALES, PT	0	0	40	2	100.00	-
18	-	17570-80C00	CAP, WATER OUTLET	SUZUKI INDOMOBIL MOTOR, PT	0	0	40	2	100.00	-
19	-	17570-80C00-000S	CAP, WATER OUTLET S	SUZUKI INDOMOBIL SALES, PT	0	0	40	2	100.00	-
20	-	17561-73000-000S	CAP, WATER THERMOSTAT	SUZUKI INDOMOBIL SALES, PT	0	0	40	2	100.00	-
21	-	1798456-00	CARRIAGE ASSEMBLY; B	KIYOKUNI INDONESIA, PT	0	0	40	2	100.00	-
22	-	179845600	CARRIAGE ASSEMBLY;B ASP	INDONESIA EPSON INDUSTRY, PT	0	0	40	2	100.00	-
23	-	1875161-01	CARRIAGE ASSY.,SUB -LOTUS 2 ( MP )	PATCO ELEKTRONIK TEKNOLOGI, PT	0	0	40	2	100.00	-
24	-	1906369-00	CARRIAGE SUB ASSY;B (FTI-2)	PATCO ELEKTRONIK TEKNOLOGI, PT	0	0	40	2	100.00	-
25	-	9835.01.00.065	CASE 065	Egi Optik Indonesia, PT	0	0	40	2	100.00	-
26	-	25121-60K00-000S	CASE COMP GEAR SHIFT LEVER	SUZUKI INDOMOBIL SALES, PT	0	0	40	2	100.00	-
27	-	17690-52S00-0001	CASE COMP. THERMOSTAT	SUZUKI INDOMOBIL MOTOR, PT	0	0	40	2	100.00	-
28	RT-50	8980947990	CASE FRONT RT 50	MESIN ISUZU INDONESIA, PT	6,8	3	95	1	100.00	
29	-	17690-52S00-000S	CASE THERMOSTAT	SUZUKI INDOMOBIL SALES, PT	0	0	40	2	100.00	-
30	-	25121-60K00-000	CASE, GEAR SHIFT LEVER	SUZUKI INDOMOBIL MOTOR, PT	0	0	40	2	100.00	-
31	-	11121-30A01-L000S	CCH LH	SUZUKI INDOMOBIL SALES, PT	0	0	40	2	100.00	-
32	-	CCHR1	CCH R1	SUZUKI INDOMOBIL SALES, PT	0	0	40	2	100.00	-
33	-	CCHR2	CCH R2	SUZUKI INDOMOBIL SALES, PT	0	0	40	2	100.00	-
34	-	11121-B09G1-0N000S	CCHLH	SUZUKI INDOMOBIL SALES, PT	0	0	40	2	100.00	-
35	-	11134-09D01L000S	COV CYL HEAD FD110-CDT	SUZUKI INDOMOBIL SALES, PT	0	0	40	2	100.00	-
36	-	11171-B46G0-0N000S	COV CYL HEAD UY	SUZUKI INDOMOBIL SALES, PT	0	0	40	2	100.00	-
37	-	CBC01	COVER BELT COOLING	SUZUKI INDOMOBIL SALES, PT	0	0	40	2	100.00	-
38	-	11149-09D01L000S	COVER CYL HEAD NO. 2 FD110-CDT	SUZUKI INDOMOBIL SALES, PT	0	0	40	2	100.00	-
39	-	11121-30A01L000	COVER CYLINDER HEAD LH CCH UW-125 AFT. BURRY	SUZUKI INDOMOBIL SALES, PT	0	0	40	2	100.00	-
40	-	11170-13H00-000	COVER CYLINDER HEAD UW 125	SUZUKI INDOMOBIL SALES, PT	0	0	40	2	100.00	-
41	-	11134-09D01L000	COVER CYLINDER HEAD UW 125 AFT-BURRY	SUZUKI INDOMOBIL SALES, PT	0	0	40	2	100.00	-
42	-	11170-13H00-000S	COVER CYLINDER HEAD UW125 SC	SUZUKI INDOMOBIL SALES, PT	0	0	40	2	100.00	-
43	-	11171-B46G00N000	COVER CYLINDER HEAD UY 125	SUZUKI INDOMOBIL SALES, PT	0	0	40	2	100.00	-
44	CGC 4JA-1	MRM-8973064312	COVER GEAR CASE 4JA	MESIN ISUZU INDONESIA, PT	1,465	1,735	90	1	100.00	
45	Cover LW	823LW25000	COVER L W	YASUNAGA INDONESIA, PT	3,05	2,25	100	1	100.00	
46	-	823LW25000P	COVER L W PROCESSING	YASUNAGA INDONESIA, PT	0	0	40	2	100.00	-
47	-	11351-23F71260	COVER MAGNET XB-511	SUZUKI INDOMOBIL SALES, PT	0	0	40	2	100.00	-
48	-	11351-B45H00NOP6	COVER MAGNET XC-601	SUZUKI INDOMOBIL SALES, PT	0	0	40	2	100.00	-
49	-	COVEROIL-01	COVER OIL	SANDY GLOBALINDO, PT	0	0	40	2	100.00	-
50	Cover StromaX	C1-0735	COVER STROMA X-INFITE LOGO	Hega Industri Indonesia,PT	500	500	86	1	100.00	
51	-	CRC-FSE168/SP200	CRANK CASE ENGINE SE 168S/SP 200	SHARPRINDO DINAMIKA PRIMA, PT	0	0	40	2	100.00	-
52	-	MRM-8975208300DOM	DUCT ASM: WATER BY PASS	MESIN ISUZU INDONESIA, PT	0	0	40	2	100.00	-
53	-	MRM-8975208300EXP	DUCT ASM: WATER BY PASS EXP	MESIN ISUZU INDONESIA, PT	0	0	40	2	100.00	-
54	Duct Thermostat	MRM- 8975208290	DUCT THERMOSTAT	MESIN ISUZU INDONESIA, PT	415	955	60	2	100.00	
55	Duct Thermostat	MRM-8975208290DOM	DUCT THERMOSTAT	MESIN ISUZU INDONESIA, PT	415	955	60	2	100.00	
56	-	MRM - 8975208290	DUCT THERMOSTAT	MESIN ISUZU INDONESIA, PT	0	0	40	2	100.00	-
57	-	MRM-8975208290EXP	DUCT THERMOSTAT EXP	MESIN ISUZU INDONESIA, PT	0	0	40	2	100.00	-
58	-	MTN-000936	EJECTOR ROD DIA. 36X600x480 M24	SHARPRINDO DINAMIKA PRIMA, PT	0	0	40	2	100.00	-
59	Pump Body	FGP30HD	FGP30HD Pump Body	TECKINDO PRIMA GEMILANG JAYA, PT	1,629	1,601	90	1	100.00	
60	Pump Cover	FGP30HD.00	FGP30HD Pump Cover	TECKINDO PRIMA GEMILANG JAYA, PT	1,035	975	85	1	100.00	
61	Water Inlet	FGP30STD.18	FGP30STD.18 Water Inlet	TECKINDO PRIMA GEMILANG JAYA, PT	388	389	45	2	100.00	
62	Water Outlet	FGP30STD.26	FGP30STD.26 Water Outlet	TECKINDO PRIMA GEMILANG JAYA, PT	680	410	45	1	100.00	
63	-	GA-000661	GENERAL CHECK MOBIL ERTIGA B 2308 FFZ	SHARPRINDO DINAMIKA PRIMA, PT	0	0	40	2	100.00	-
64	-	10-FT-1X011-01-03	HANDLEBAR RISER CLAMP	Electra Mobilitas Indonesia,PT	0	0	40	2	100.00	-
65	-	28-A00530-0001	HANDLEBAR RISER CLAMP BLACK MATTE	Electra Mobilitas Indonesia,PT	0	0	40	2	100.00	-
66	-	10-FT-1X011-01-03F	HANDLEBAR RISER CLAMP F	Electra Distribusi Indonesia,PT	0	0	40	2	100.00	-
67	H-2090	AE159889-2090	HOLDER -2090	DENSO MANUFACTURING INDONESIA, PT	135	628	41	2	100.00	
68	H-2100	AE159889-2100	HOLDER -2100	DENSO MANUFACTURING INDONESIA, PT	164	669	41	2	100.00	
69	H-0580	AE159888-0580	HOLDER-0580	DENSO MANUFACTURING INDONESIA, PT	360	720	47	1	100.00	
70	H-0590	AE159889-0590	HOLDER-0590	DENSO MANUFACTURING INDONESIA, PT	120	595	43	2	100.00	
71	H-0600	AE159888-0600	HOLDER-0600	DENSO MANUFACTURING INDONESIA, PT	360	720	47	1	100.00	
72	H-0610	AE159889-0610	HOLDER-0610	DENSO MANUFACTURING INDONESIA, PT	120	595	43	2	100.00	
73	H-1220	AE159889-1220	HOLDER-1220	DENSO MANUFACTURING INDONESIA, PT	160	450	40	2	100.00	
74	H-1230	AE159889-1230	HOLDER-1230	DENSO MANUFACTURING INDONESIA, PT	135	480	40	2	100.00	
75	H-1240	AE159889-1240	HOLDER-1240	DENSO MANUFACTURING INDONESIA, PT	160	451	40	2	100.00	
76	H-1250	AE159889-1250	HOLDER-1250	DENSO MANUFACTURING INDONESIA, PT	135	480	40	2	100.00	
77	H-1320	AE159889-1320	HOLDER-1320	DENSO MANUFACTURING INDONESIA, PT	85	626	51	4	100.00	
78	H-1330	AE159889-1330	HOLDER-1330	DENSO MANUFACTURING INDONESIA, PT	85	630	51	4	100.00	
79	H-1340	AE159889-1340	HOLDER-1340	DENSO MANUFACTURING INDONESIA, PT	90	621	51	4	100.00	
80	H-1350	AE159889-1350	HOLDER-1350	DENSO MANUFACTURING INDONESIA, PT	90	625	51	4	100.00	
81	H-1470	AE159889-1470	HOLDER-1470	DENSO MANUFACTURING INDONESIA, PT	100	622	51	4	100.00	
82	H-1481	AE159889-1481	HOLDER-1481	DENSO MANUFACTURING INDONESIA, PT	95	625	42	2	100.00	
83	H-1490	AE159889-1490	HOLDER-1490	DENSO MANUFACTURING INDONESIA, PT	100	621	51	4	100.00	
84	H-1501	AE159889-1501	HOLDER-1501	DENSO MANUFACTURING INDONESIA, PT	95	625	42	2	100.00	
85	H-1650	AE159889-1650	HOLDER-1650	DENSO MANUFACTURING INDONESIA, PT	85	545	51	4	100.00	
86	H-1660	AE159889-1660	HOLDER-1660	DENSO MANUFACTURING INDONESIA, PT	85	540	51	4	100.00	
87	H-1670	AE159889-1670	HOLDER-1670	DENSO MANUFACTURING INDONESIA, PT	100	531	51	4	100.00	
88	H-1680	AE159889-1680	HOLDER-1680	DENSO MANUFACTURING INDONESIA, PT	100	525	51	4	100.00	
89	H-1750	AE159889-1750	HOLDER-1750	DENSO MANUFACTURING INDONESIA, PT	120	505	42	2	100.00	
90	H-1760	AE159889-1760	HOLDER-1760	DENSO MANUFACTURING INDONESIA, PT	130	495	42	2	100.00	
91	H-1910	AE159889-1910	HOLDER-1910	DENSO MANUFACTURING INDONESIA, PT	120	510	42	2	100.00	
92	H-1920	AE159889-1920	HOLDER-1920	DENSO MANUFACTURING INDONESIA, PT	130	500	42	2	100.00	
93	H-2110	AE159889-2110	HOLDER-2110	DENSO MANUFACTURING INDONESIA, PT	136	677	41	2	100.00	
94	H-2120	AE159889-2120	HOLDER-2120	DENSO MANUFACTURING INDONESIA, PT	163	640	41	2	100.00	
95	H-4030	AE159878-4030	HOLDER-4030	DENSO MANUFACTURING INDONESIA, PT	0	0	40	2	100.00	
96	H-4490	AE159878-4490	HOLDER-4490	DENSO MANUFACTURING INDONESIA, PT	100	100	40	2	100.00	
97	H-4510	AE159878-4510	HOLDER-4510	DENSO MANUFACTURING INDONESIA, PT	0	0	40	2	100.00	
98	H-5090	AE159878-5090	HOLDER-5090	DENSO MANUFACTURING INDONESIA, PT	100	100	40	2	100.00	
99	H-5130	AE159878-5130	HOLDER-5130	DENSO MANUFACTURING INDONESIA, PT	145	533	35	2	100.00	
100	H-5140	AE159878-5140	HOLDER-5140	DENSO MANUFACTURING INDONESIA, PT	135	650	40	2	100.00	
101	H-5571	AE159878-5571	HOLDER-5571	DENSO MANUFACTURING INDONESIA, PT	0	0	40	2	100.00	
102	H-7090	AE159878-7080	HOLDER-7080	DENSO MANUFACTURING INDONESIA, PT	0	0	40	2	100.00	
103	H-7090	AE159878-7090	HOLDER-7090	DENSO MANUFACTURING INDONESIA, PT	0	0	40	2	100.00	
104	H-7100	AE159878-7100	HOLDER-7100	DENSO MANUFACTURING INDONESIA, PT	112	538	39	2	100.00	
105	H-7110	AE159878-7110	HOLDER-7110	DENSO MANUFACTURING INDONESIA, PT	170	650	39	2	100.00	
106	H-7590	AE159879-7590	HOLDER-7590	DENSO MANUFACTURING INDONESIA, PT	150	500	39	2	100.00	
107	H-7600	AE159879-7600	HOLDER-7600	DENSO MANUFACTURING INDONESIA, PT	135	555	39	2	100.00	
108	H-7610	AE159879-7610	HOLDER-7610	DENSO MANUFACTURING INDONESIA, PT	150	500	39	2	100.00	
109	H-7620	AE159879-7620	HOLDER-7620	DENSO MANUFACTURING INDONESIA, PT	135	555	39	2	100.00	
110	H-7690	AE159878-7690	HOLDER-7690	DENSO MANUFACTURING INDONESIA, PT	92	554	40	2	100.00	
111	H-7700	AE159878-7700	HOLDER-7700	DENSO MANUFACTURING INDONESIA, PT	385	845	49	1	100.00	
112	H-7710	AE159878-7710	HOLDER-7710	DENSO MANUFACTURING INDONESIA, PT	92	554	40	2	100.00	
113	H-7720	AE159878-7720	HOLDER-7720	DENSO MANUFACTURING INDONESIA, PT	385	845	49	1	100.00	
114	H-7790	AE159878-7790	HOLDER-7790	DENSO MANUFACTURING INDONESIA, PT	0	0	40	2	100.00	
115	H-7791	AE159878-7791	HOLDER-7791	DENSO MANUFACTURING INDONESIA, PT	120	1,045	45	2	100.00	
116	H-8440	AE159878-8440	HOLDER-8440	DENSO MANUFACTURING INDONESIA, PT	375	615	45	1	100.00	
117	H-8450	AE159878-8450	HOLDER-8450	DENSO MANUFACTURING INDONESIA, PT	130	595	41	2	100.00	
118	H-9690	AE159878-9690	HOLDER-9690	DENSO MANUFACTURING INDONESIA, PT	142	618	41	2	100.00	
119	H-9770	AE159878-9700	HOLDER-9700	DENSO MANUFACTURING INDONESIA, PT	187	768	41	2	100.00	
120	H-9710	AE159878-9710	HOLDER-9710	DENSO MANUFACTURING INDONESIA, PT	142	618	41	2	100.00	
121	H-9720	AE159878-9720	HOLDER-9720	DENSO MANUFACTURING INDONESIA, PT	187	768	41	2	100.00	
122	H-9730	AE159879-9730	HOLDER-9730	DENSO MANUFACTURING INDONESIA, PT	127	588	41	2	100.00	
123	H-9740	AE159879-9740	HOLDER-9740	DENSO MANUFACTURING INDONESIA, PT	420	688	60	1	100.00	
124	H-9750	AE159879-9750	HOLDER-9750	DENSO MANUFACTURING INDONESIA, PT	127	588	41	2	100.00	
125	H-9760	AE159879-9760	HOLDER-9760	DENSO MANUFACTURING INDONESIA, PT	420	688	60	1	100.00	
133	Plate XPI	8980422950	INTERMEDIATE PLATE MUX (XPI) 8971702040	MESIN ISUZU INDONESIA, PT	2,66	1,34	100	1	100.00	
140	Manifold 2DP	16111-1CPM	MANIFOLD 2DP	NAKAKIN INDONESIA, PT	125	379	40	2	100.00	
141	Manifold 779	ME-221779	MANIFOLD INLET A ME221779	NAKAKIN INDONESIA, PT	2,7	2,575	82	1	100.00	
142	Manifold 992	ME-223992	MANIFOLD INLET B ME223992	NAKAKIN INDONESIA, PT	1,235	1,885	75	1	100.00	
165	Steering Shaft	10-FT-1X008-01-03	STEERING SHAFT BASE	Electra Mobilitas Indonesia,PT	296	1,179	60	2	100.00	
169	Tank LWN	813LW40001	TANK L W N	YASUNAGA INDONESIA, PT	2,035	965	82	1	100.00	
171	THERMOSTAT HOUSING	A400-203-00-73	THERMOSTAT HOUSING	NAKAKIN INDONESIA, PT	530	420	41	1	100.00	
178	XCF	8980358811	XCF - CASE FRONT MUX 8980358811	MESIN ISUZU INDONESIA, PT	7	-3	95	1	100.00	
TSV;

        // ========================= Helpers =========================
        $clean = function ($v): string {
            $v = trim((string) $v);
            $v = preg_replace('/\s+/', ' ', $v ?? '');
            return $v ?? '';
        };

        // parse numeric to int/float (handles: 1,22 => 1220 ; 2,085 => 2085 ; 6,8 => 6800 ; -3 => -3000)
        $toGram = function ($v): int {
            $s = trim((string) $v);
            if ($s === '' || $s === '-') return 0;

            // scientific notation -> treat as string? but for weights unlikely.
            // if scientific exists, try float
            $sNorm = str_replace(' ', '', $s);

            // if contains comma and not dot => Indonesian decimal format
            if (strpos($sNorm, ',') !== false && strpos($sNorm, '.') === false) {
                $f = (float) str_replace(',', '.', $sNorm);

                // if looks like kg (< 10 or <= 10-ish) -> grams
                if (abs($f) > 0 && abs($f) < 10) {
                    return (int) round($f * 1000);
                }

                // if >= 10 then it's already grams but with decimal comma? rare -> round
                return (int) round($f);
            }

            // plain number possibly like 1255, 520, -3000
            if (is_numeric($sNorm)) {
                $f = (float) $sNorm;

                // if looks like kg and small, convert to gram
                if (abs($f) > 0 && abs($f) < 10) {
                    return (int) round($f * 1000);
                }

                return (int) round($f);
            }

            // fallback: strip non-numeric except - and .
            $sNorm = preg_replace('/[^0-9\.\-]/', '', $sNorm);
            if ($sNorm === '' || $sNorm === '-') return 0;

            $f = (float) $sNorm;
            if (abs($f) > 0 && abs($f) < 10) return (int) round($f * 1000);
            return (int) round($f);
        };

        $toInt = function ($v): int {
            $s = trim((string) $v);
            if ($s === '' || $s === '-') return 0;
            $s = str_replace(',', '.', $s);
            return (int) round((float) $s);
        };

        $toFloat = function ($v): float {
            $s = trim((string) $v);
            if ($s === '' || $s === '-') return 0.0;
            $s = str_replace(',', '.', $s);
            return (float) $s;
        };

        $normalizePartProd = function (string $partProd): string {
            $partProd = trim($partProd);
            if ($partProd === '' || $partProd === '-') return '';

            // H-XXXX => HOLDER-XXXX
            if (preg_match('/^H-(\d+)/i', $partProd, $m)) {
                return 'HOLDER-' . $m[1];
            }
            return $partProd;
        };

        // cache customer_name => id (lowercase)
        $customerMap = [];
        $customers = $db->table('customers')->select('id, customer_name')->get()->getResultArray();
        foreach ($customers as $c) {
            $customerMap[strtolower(trim($c['customer_name'] ?? ''))] = (int) $c['id'];
        }

        // ========================= Parse Rows =========================
        $lines = preg_split("/\r\n|\n|\r/", trim($tsv));
        $rows = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;

            // allow tab, but if user pasted spaces, handle with preg_split
            $cols = explode("\t", $line);
            if (count($cols) < 10) {
                $cols = preg_split('/\s{2,}/', $line);
            }

            // normalize to 11 cols
            $cols = array_pad($cols, 11, '');

            $rows[] = [
                'part_prod'  => $clean($cols[1] ?? ''),
                'part_no'    => $clean($cols[2] ?? ''),
                'part_name'  => $clean($cols[3] ?? ''),
                'customer'   => $clean($cols[4] ?? ''),
                'ascas'      => $clean($cols[5] ?? ''),
                'runner'     => $clean($cols[6] ?? ''),
                'ct'         => $clean($cols[7] ?? ''),
                'cavity'     => $clean($cols[8] ?? ''),
                'eff'        => $clean($cols[9] ?? ''),
                'notes'      => $clean($cols[10] ?? ''),
            ];
        }

        // ========================= Update DB =========================
        $updated = 0;
        $skipped = 0;
        $notFound = [];

        $db->transStart();

        foreach ($rows as $r) {
            $partNo = $r['part_no'];
            if ($partNo === '') {
                $skipped++;
                continue;
            }

            // find product by part_no case-insensitive
            $product = $db->table('products')
                ->select('id, part_no')
                ->where('LOWER(part_no)', strtolower($partNo))
                ->get()
                ->getRowArray();

            if (!$product) {
                $notFound[] = $partNo;
                $skipped++;
                continue;
            }

            $customerId = null;
            $custKey = strtolower(trim($r['customer']));
            if ($custKey !== '' && isset($customerMap[$custKey])) {
                $customerId = $customerMap[$custKey];
            }

            $dataUpdate = [
                'part_prod'        => $normalizePartProd($r['part_prod']),
                'part_name'        => $r['part_name'],
                'weight_ascas'     => $toGram($r['ascas']),
                'weight_runner'    => $toGram($r['runner']),
                'cycle_time'       => $toInt($r['ct']),
                'cavity'           => $toInt($r['cavity']),
                'efficiency_rate'  => $toFloat($r['eff']),
                'notes'            => ($r['notes'] === '-' ? '' : $r['notes']),
            ];

            // update customer_id only if found
            if ($customerId !== null) {
                $dataUpdate['customer_id'] = $customerId;
            }

            $db->table('products')->where('id', (int) $product['id'])->update($dataUpdate);
            $updated++;
        }

        $db->transComplete();

        // ========================= Output log =========================
        echo "ProductsBulkUpdateSeeder\n";
        echo "Updated : {$updated}\n";
        echo "Skipped : {$skipped}\n";

        if (!empty($notFound)) {
            echo "Not found (part_no):\n";
            foreach ($notFound as $pn) {
                echo "- {$pn}\n";
            }
        }
    }
}

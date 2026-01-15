<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class ProductSeederV2 extends Seeder
{
    public function run()
    {
        $products = [
            ['H-5130',145,533],
            ['H-5140',135,650],
            ['H-5561 #2',318,467],
            ['H-7100',112,538],
            ['H-7110',170,650],
            ['H-7690 #2',92,554],
            ['H-7700 #2',385,845],
            ['H-7710',92,554],
            ['H-7720 #2',385,845],
            ['H-7791 #2',120,1045],
            ['H-7590 #2',150,500],
            ['H-7600 #2',135,555],
            ['H-7610 #2',150,500],
            ['H-7620 #2',135,555],
            ['H-8440',375,615],
            ['H-8450',130,595],
            ['H-9690',142,618],
            ['H-9700',187,768],
            ['H-9710',142,618],
            ['H-9720',187,768],
            ['H-9730',127,588],
            ['H-9740',420,688],
            ['H-9750',127,588],
            ['H-9760 #2',420,688],
            ['H-0580 #2',360,720],
            ['H-0590',120,595],
            ['H-0600 #2',360,720],
            ['H-0610',120,595],
            ['H-1220',160,450],
            ['H-1230',135,480],
            ['H-1240',160,451],
            ['H-1250',135,480],
            ['H-1320',85,626],
            ['H-1330',85,630],
            ['H-1340',90,621],
            ['H-1350',90,625],
            ['H-1470',100,622],
            ['H-1481',95,625],
            ['H-1490',100,621],
            ['H-1501',95,625],
            ['H-1650',85,545],
            ['H-1660',85,540],
            ['H-1670',100,531],
            ['H-1680',100,525],
            ['H-1750',120,505],
            ['H-1760',130,495],
            ['H-1910',120,510],
            ['H-1920',130,500],
            ['H-2090',135,628],
            ['H-2100',164,669],
            ['H-2110',136,677],
            ['H-2120',163,640],

            ['Housing-5050 #1',173,652],
            ['Housing-5050 #2',173,652],
            ['Housing-1W #4',173,627],
            ['Housing-1W #6',173,627],

            ['CGC 4JA-1',1465,1735],
            ['CGC 4JB-1',1195,1805],

            ['Duct Asm',430,383],
            ['Duct Asm #2',430,383],
            ['Duct Thermostat',415,955],

            ['Bracket ASM Generator',2085,830],

            ['Plate MSG',1215,747],
            ['Plate XPI',2660,1340],

            ['RT-50',6800,3000],
            ['XCF',7000,-3000],

            ['Case Comp #3',210,307],
            ['Case Comp #2',210,307],

            ['CGSL APV',395,455],
            ['CWO',83,586],

            ['Bracket YR-9 #2',965,555],
            ['CWT YL-8',93,385],
            ['Bracket-74',1330,691],
            ['Bracket-71',880,1621],

            ['Stand Base LR',150,150],
            ['Manifold 2DP',125,379],
            ['Manifold 779 #2',2700,2575],
            ['Manifold 992 #2',1235,1885],

            ['Thermostat TD',530,420],
            ['Thermostat Housing',530,420],

            ['Carriage Bamboo 2',107,179],
            ['Carriage Lotus 2',70,345],
            ['Carriage FT-2 #2',35,345],
            ['Carriage FT-2 #1',35,345],

            ['Blok SND 01',1255,745],
            ['Blok SND 02',1220,780],
            ['Blok SND 03',1090,910],
            ['Blok SND 04',1110,345],
            ['Blok SND 05',1220,345],

            ['Foot Page-RH',184,866],
            ['Foot Page-LH',184,866],

            ['Handle Bar',80,835],
            ['Steering Shaft',296,1179],
            ['Spacer Adapter',200,625],
            ['License Plate',515,600],
            ['Rear Handle Seat',1423,2797],

            ['Pump Body',1629,1601],
            ['Pump Cover',1035,975],
            ['Water Inlet',388,389],
            ['Water Outlet',680,410],

            ['Tank LWN #2',2035,965],
            ['Tank LWN',2035,965],

            ['Cover LW #2',3050,2250],
            ['Cover LW',3050,2250],

            ['Crank Case',2035,2000],

            ['Bottom Stroma -1',520,480],
            ['Body StromaX',520,480],
            ['Cover Stroma-1',500,500],
            ['Cover StromaX',500,500],

            ['Polo Shaft',550,450],
            ['OFF',0,0],
        ];

        foreach ($products as $p) {

            // cari existing by part_no atau part_name
            $existing = $this->db->table('products')
                ->groupStart()
                    ->where('part_no', $p[0])
                    ->orWhere('part_name', $p[0])
                ->groupEnd()
                ->get()
                ->getRowArray();

            if ($existing) {
                // UPDATE
                $this->db->table('products')
                    ->where('id', $existing['id'])
                    ->update([
                        'weight_ascas'  => $p[1],
                        'weight_runner' => $p[2],
                    ]);
            } else {
                // INSERT
                $this->db->table('products')->insert([
                    'part_no'        => $p[0],
                    'part_name'      => $p[0],
                    'weight_ascas'   => $p[1],
                    'weight_runner'  => $p[2],
                ]);
            }
        }
    }
}

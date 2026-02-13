<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run()
    {
        $data = [
            ['customer_code' => 'C-0001',  'customer_name' => 'ASMO INDONESIA, PT'],
            ['customer_code' => 'C-0002',  'customer_name' => 'ASTRA DAIHATSU MOTOR, PT'],
            ['customer_code' => 'C-0003',  'customer_name' => 'DENSO MANUFACTURING INDONESIA, PT'],
            ['customer_code' => 'C-00004', 'customer_name' => 'DES'],
            ['customer_code' => 'C-0004',  'customer_name' => 'DUTA NICHIRINDO PRATAMA, PT'],
            ['customer_code' => 'C-0005',  'customer_name' => 'EGI OPTIC INDONESIA, PT'],
            ['customer_code' => 'C-0006',  'customer_name' => 'ELECTRA DISTRIBUSI INDONESIA, PT'],
            ['customer_code' => 'C-0007',  'customer_name' => 'ELECTRA MOBILITAS INDONESIA, PT'],
            ['customer_code' => 'C-0008',  'customer_name' => 'HEGA INDONESIA, PT'],
            ['customer_code' => 'C-0009',  'customer_name' => 'INDONESIA EPSON INDUSTRY, PT'],
            ['customer_code' => 'C-0010',  'customer_name' => 'ISK INDONESIA, PT'],
            ['customer_code' => 'C-0011',  'customer_name' => 'KARYABAN TEKNIK, PT'],
            ['customer_code' => 'C-0012',  'customer_name' => 'KENDALI PARAMITA, PT'],
            ['customer_code' => 'C-0013',  'customer_name' => 'KIYOKUNI INDONESIA, PT'],
            ['customer_code' => 'C-0014',  'customer_name' => 'MAJU BERSAMA RAMLI, PT'],
            ['customer_code' => 'C-0015',  'customer_name' => 'MEIWA KOGYO INDONESIA, PT'],
            ['customer_code' => 'C-0016',  'customer_name' => 'MESIN ISUZU INDONESIA, PT'],
            ['customer_code' => 'C-00002', 'customer_name' => 'MIZU YAMA UTAMA, PT'],
            ['customer_code' => 'C-0017',  'customer_name' => 'NAKAKIN INDONESIA, PT'],
            ['customer_code' => 'C-0018',  'customer_name' => 'PATCO ELEKTRONIK TEKNOLOGI, PT'],
            ['customer_code' => 'C-0019',  'customer_name' => 'PRICOLL, PT'],
            ['customer_code' => 'C-0020',  'customer_name' => 'PROGRESS DIE CASTING INDONESIA, PT'],
            ['customer_code' => 'C-0021',  'customer_name' => 'PROGRESS TOYO INDONESIA, PT'],
            ['customer_code' => 'C-00003', 'customer_name' => 'PT. ATMI IGI CENTER'],
            ['customer_code' => 'C-00000', 'customer_name' => 'Pelanggan Umum'],
            ['customer_code' => 'C-0022',  'customer_name' => 'SANDY GLOBALINDO, PT'],
            ['customer_code' => 'C-0023',  'customer_name' => 'SANKEI DHARMA INDONESIA, PT'],
            ['customer_code' => 'C-0036',  'customer_name' => 'SHARPRINDO DINAMIKA PRIMA, PT'],
            ['customer_code' => 'C-0025',  'customer_name' => 'SHINHEUNG INDONESIA, PT'],
            ['customer_code' => 'C-0026',  'customer_name' => 'SUMHING INDONESIA, PT'],
            ['customer_code' => 'C-0027',  'customer_name' => 'SUZUKI INDOMOBIL MOTOR, PT'],
            ['customer_code' => 'C-0028',  'customer_name' => 'SUZUKI INDOMOBIL SALES, PT'],
            ['customer_code' => 'C-0037',  'customer_name' => 'TECKINDO PRIMA GEMILANG JAYA, PT'],
            ['customer_code' => 'C-0029',  'customer_name' => 'TIGA REKSA PERDANA, PT'],
            ['customer_code' => 'C-0030',  'customer_name' => 'TJOKRO BERSAUDARA COMENINDO, PT'],
            ['customer_code' => 'C-0031',  'customer_name' => 'TRI CIPTA, PT'],
            ['customer_code' => 'C-0032',  'customer_name' => 'TRIMETAL, PT'],
            ['customer_code' => 'C-0033',  'customer_name' => 'YASUNAGA INDONESIA, PT'],
            ['customer_code' => 'C-0034',  'customer_name' => 'YOGYA PRESISI, PT'],
            ['customer_code' => 'C-0035',  'customer_name' => 'YOSHINOBU, PT'],
        ];

        // insert batch
        $this->db->table('customers')->insertBatch($data);
    }
}

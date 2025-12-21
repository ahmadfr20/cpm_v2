<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class MasterSeeder extends Seeder
{
    public function run()
    {
        $this->call('ShiftSeeder');
        $this->call('TimeSlotSeeder');
        $this->call('CustomerSeeder');
        $this->call('MachineSeeder');
        $this->call('ProductSeeder');
    }
}

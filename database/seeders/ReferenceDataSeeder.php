<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Exam;
use App\Models\LeadStatus;
use App\Models\Source;
use App\Models\State;
use Illuminate\Database\Seeder;

class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['New', false], ['Not Contacted', false], ['Interested', false], ['Not Interested', true],
            ['Call Back', false], ['No Response', false], ['Follow-up Required', false],
            ['Converted', true], ['Closed', true],
        ];
        foreach ($statuses as $i => [$name, $closed]) {
            LeadStatus::updateOrCreate(['name' => $name], ['sort_order' => $i + 1, 'is_closed' => $closed]);
        }

        foreach ([
            ['Collegioz website', 'website'],
            ['Vinayaka Consultancy website', 'website'],
            ['ugpgcolleges.com', 'website'],
            ['CareerMentors360 website', 'website'],
            ['Manual entry', 'manual'],
        ] as [$name, $type]) {
            Source::firstOrCreate(['name' => $name], ['type' => $type]);
        }

        foreach (['MBBS', 'BDS', 'B.Tech', 'MBA', 'BBA', 'B.Com', 'B.Sc Nursing', 'BAMS', 'BHMS', 'LLB', 'M.Tech', 'MCA', 'BCA', 'B.Pharm'] as $c) {
            Course::firstOrCreate(['name' => $c]);
        }
        foreach (['NEET UG', 'JEE Main', 'JEE Advanced', 'CAT', 'MAT', 'CUET UG', 'CUET PG', 'CLAT', 'GATE', 'KCET', 'MHT CET'] as $e) {
            Exam::firstOrCreate(['name' => $e]);
        }
        foreach ([
            'Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh', 'Goa', 'Gujarat', 'Haryana',
            'Himachal Pradesh', 'Jharkhand', 'Karnataka', 'Kerala', 'Madhya Pradesh', 'Maharashtra', 'Manipur',
            'Meghalaya', 'Mizoram', 'Nagaland', 'Odisha', 'Punjab', 'Rajasthan', 'Sikkim', 'Tamil Nadu', 'Telangana',
            'Tripura', 'Uttar Pradesh', 'Uttarakhand', 'West Bengal', 'Delhi', 'Jammu and Kashmir', 'Ladakh',
            'Chandigarh', 'Puducherry', 'Andaman and Nicobar Islands', 'Dadra and Nagar Haveli and Daman and Diu', 'Lakshadweep',
        ] as $s) {
            State::firstOrCreate(['name' => $s]);
        }
    }
}

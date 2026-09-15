<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\User;
use App\Support\DefaultForm;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $manager = User::updateOrCreate(
            ['email' => 'manager@example.com'],
            [
                'name' => 'Event Manager',
                'password' => 'password',
                'role' => 'manager',
            ]
        );

        $event = Event::updateOrCreate(
            ['registration_token' => 'IT2026ABC'],
            [
                'event_code' => 'EVT-2026-01',
                'title' => 'សម័យបង្ហាត់បច្ចេកវិទ្យាព័ត៌មានវិទ្យា ២០២៦',
                'description' => 'កម្មវិធីបង្ហាត់បង្ហាញ និងបណ្តុះបណ្តាលជំនាញបច្ចេកវិទ្យាព័ត៌មានវិទ្យា។ សូមបំពេញទម្រង់ខាងក្រោមដើម្បីចុះឈ្មោះចូលរួម។',
                'location' => 'ភ្នំពេញ',
                'start_date' => '2026-09-20',
                'end_date' => '2026-09-21',
                'status' => 'open',
                'created_by' => $manager->id,
            ]
        );

        if ($event->questions()->count() === 0) {
            foreach (DefaultForm::questions() as $index => $q) {
                $event->questions()->create([...$q, 'order' => $index + 1]);
            }
        }
    }
}

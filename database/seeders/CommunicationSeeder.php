<?php

namespace Database\Seeders;

use App\Models\ErpNotice;
use App\Models\Event;
use App\Models\Message;
use Illuminate\Database\Seeder;

class CommunicationSeeder extends Seeder
{
    public function run(): void
    {
        ErpNotice::firstOrCreate(
            ['title' => 'Half Yearly Exam Schedule Released'],
            ['content' => 'The schedule for the half yearly examinations has been published. Please check the Exam Management section for details.', 'type' => 'Notice', 'audience' => 'Students', 'publish_date' => '2026-07-20', 'status' => 'Published']
        );
        ErpNotice::firstOrCreate(
            ['title' => 'Staff Meeting — Monthly Review'],
            ['content' => 'All staff are requested to attend the monthly review meeting in the conference hall.', 'type' => 'Circular', 'audience' => 'Staff', 'publish_date' => '2026-07-25', 'status' => 'Published']
        );

        Event::firstOrCreate(
            ['title' => 'Annual Sports Day'],
            ['description' => 'Inter-house athletics and sports competitions.', 'venue' => 'School Ground', 'event_date' => '2026-09-05', 'start_time' => '08:00', 'end_time' => '16:00', 'status' => 'Scheduled']
        );

        Message::firstOrCreate(
            ['subject' => 'Fee Reminder'],
            ['channel' => 'SMS', 'audience' => 'Parents', 'body' => 'Dear Parent, the quarterly fee is due by 5th August. Kindly pay at the earliest.', 'recipient_count' => 1, 'status' => 'Sent', 'sent_at' => '2026-07-22 10:00:00']
        );
    }
}

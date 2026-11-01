<?php

namespace Database\Seeders;

use App\Models\College;
use App\Models\Course;
use App\Models\Exam;
use App\Models\LeadStatus;
use App\Models\Source;
use App\Models\State;
use App\Models\Student;
use App\Models\Team;
use App\Models\User;
use App\Services\LeadIntakeService;
use App\Services\LeadWorkService;
use Illuminate\Database\Seeder;

/**
 * DUMMY DATA ONLY. Builds the 11-person team (1 Super Admin, 4 Team Leads,
 * 6 Counsellors) and fake students. Never run on the live CRM.
 * Number of students: DEMO_STUDENTS env (default 500).
 */
class DemoDataSeeder extends Seeder
{
    public const PASSWORD = 'Demo-pass-2026';

    public function run(LeadIntakeService $intake, LeadWorkService $work): void
    {
        if (app()->environment('production')) {
            $this->command?->error('Refusing to load dummy data in production.');

            return;
        }

        $admin = User::firstOrCreate(['email' => 'admin@example.test'], [
            'name' => 'Demo Super Admin', 'password' => self::PASSWORD, 'role' => 'super_admin',
        ]);

        $staff = [];
        foreach ([1 => 2, 2 => 2, 3 => 2, 4 => 0] as $t => $counsellors) {
            $lead = User::firstOrCreate(['email' => "lead$t@example.test"], [
                'name' => "Demo Team Lead $t", 'password' => self::PASSWORD, 'role' => 'team_lead',
            ]);
            $team = Team::firstOrCreate(['name' => "Team $t"], ['lead_user_id' => $lead->id]);
            $lead->update(['team_id' => $team->id]);
            $staff[] = $lead;
            for ($c = 1; $c <= $counsellors; $c++) {
                $staff[] = User::firstOrCreate(['email' => "counsellor$t$c@example.test"], [
                    'name' => "Demo Counsellor $t.$c", 'password' => self::PASSWORD,
                    'role' => 'counsellor', 'team_id' => $team->id,
                ]);
            }
        }

        foreach (['Demo Medical College, Pune', 'Demo Institute of Technology, Indore', 'Demo Business School, Jaipur', 'Demo Dental College, Nagpur'] as $c) {
            College::firstOrCreate(['name' => $c]);
        }

        $count = (int) env('DEMO_STUDENTS', 500);
        if (Student::count() >= $count) {
            return;
        }

        $faker = fake('en_IN');
        $sources = Source::where('type', 'website')->get()->push(
            Source::firstOrCreate(['name' => 'Demo Coaching Centre (physical list)'], ['type' => 'physical_upload'])
        );
        $courses = Course::pluck('name');
        $exams = Exam::pluck('name');
        $statuses = LeadStatus::all();
        $courseIds = Course::pluck('id', 'name');
        $examIds = Exam::pluck('id', 'name');
        $stateIds = State::pluck('id');
        $outcomes = ['Interested, wants fee details', 'Did not pick up', 'Asked to call after results', 'Parents will decide', 'Not interested this year', 'Comparing two colleges'];

        for ($i = 0; $i < $count; $i++) {
            $course = $courses->random();
            $exam = $exams->random();
            $res = $intake->intake([
                'name' => $faker->name(),
                // 9-prefixed demo numbers, never real ones from any list
                'mobile' => '9'.str_pad((string) ($i + 100000000), 9, '0', STR_PAD_LEFT),
                'email' => "student$i@example.test",
                'course' => $course,
                'exam' => $exam,
                'city_state' => $faker->city(),
                'admission_year' => 2026 + ($i % 2),
                'source_page' => '/demo-signup',
            ], $sources->random(), ['channel' => 'website']);

            $s = $res['student'];
            $s->update(['course_id' => $courseIds[$course], 'exam_id' => $examIds[$exam], 'state_id' => $stateIds->random()]);

            if ($i % 10 < 8) {   // most leads assigned, some left in the Unassigned list
                $work->assign($s, $admin, $staff[array_rand($staff)]);
                $s->refresh();
                for ($c = 0, $n = $i % 4; $c < $n; $c++) {
                    $work->logCall($s, $s->assignedUser, [
                        'called_at' => now()->subDays(10 - $c * 3)->setTime(10 + $c, 15),
                        'outcome' => $outcomes[array_rand($outcomes)],
                        'advice' => $c % 2 ? 'Suggested applying to two backup colleges' : null,
                        'lead_status_id' => $statuses->random()->id,
                        'priority' => ['hot', 'warm', 'cold'][$i % 3],
                        'next_follow_up_at' => now()->addDays(($i % 9) - 4)->setTime(11, 0),
                    ]);
                }
            }
        }
    }
}

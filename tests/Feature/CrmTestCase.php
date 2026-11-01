<?php

namespace Tests\Feature;

use App\Models\Source;
use App\Models\Student;
use App\Models\Team;
use App\Models\User;
use App\Services\LeadIntakeService;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class CrmTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $lead1;
    protected User $lead2;
    protected User $c1;   // counsellor in team 1
    protected User $c1b;  // second counsellor in team 1
    protected User $c2;   // counsellor in team 2

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceDataSeeder::class);
        config(['crm.require_admin_2fa' => false]);

        $this->admin = User::create(['name' => 'Admin', 'email' => 'a@x.test', 'password' => 'Secret-pass-1', 'role' => 'super_admin']);
        $this->lead1 = $this->makeLead(1);
        $this->lead2 = $this->makeLead(2);
        $this->c1 = $this->makeCounsellor('c1', $this->lead1);
        $this->c1b = $this->makeCounsellor('c1b', $this->lead1);
        $this->c2 = $this->makeCounsellor('c2', $this->lead2);
    }

    private function makeLead(int $n): User
    {
        $u = User::create(['name' => "Lead $n", 'email' => "l$n@x.test", 'password' => 'Secret-pass-1', 'role' => 'team_lead']);
        $t = Team::create(['name' => "Team $n", 'lead_user_id' => $u->id]);
        $u->update(['team_id' => $t->id]);

        return $u->fresh();
    }

    private function makeCounsellor(string $key, User $lead): User
    {
        return User::create(['name' => $key, 'email' => "$key@x.test", 'password' => 'Secret-pass-1', 'role' => 'counsellor', 'team_id' => $lead->team_id]);
    }

    protected function student(string $mobile = '9876543210', ?User $assignTo = null, string $name = 'Test Student'): Student
    {
        $res = app(LeadIntakeService::class)->intake(
            ['name' => $name, 'mobile' => $mobile],
            Source::where('name', 'Collegioz website')->first(),
            ['channel' => 'website'],
        );
        $s = $res['student'];
        if ($assignTo) {
            app(\App\Services\LeadWorkService::class)->assign($s, $this->admin, $assignTo);
        }

        return $s->fresh();
    }
}

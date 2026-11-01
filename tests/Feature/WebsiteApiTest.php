<?php

namespace Tests\Feature;

use App\Models\Enquiry;
use App\Models\Source;
use App\Models\Student;
use App\Models\WebsiteConnection;

class WebsiteApiTest extends CrmTestCase
{
    private function connect(string $name, string $source): array
    {
        $c = WebsiteConnection::make(['name' => $name, 'per_minute_limit' => 30]);
        $c->source_id = Source::where('name', $source)->value('id');
        $key = $c->issueKey();

        return [$c, $key];
    }

    private function payload(array $over = []): array
    {
        return array_merge(['name' => 'Priya Sharma', 'mobile' => '+91 98765 43210', 'email' => 'priya@example.test',
            'course' => 'MBBS', 'exam' => 'NEET UG', 'city_state' => 'Jaipur, Rajasthan', 'consent' => true,
            'source_page' => 'https://collegioz.example/mbbs'], $over);
    }

    public function test_each_website_shows_as_its_own_source(): void
    {
        [, $k1] = $this->connect('Collegioz', 'Collegioz website');
        [, $k2] = $this->connect('Vinayaka', 'Vinayaka Consultancy website');
        [, $k3] = $this->connect('UGPG', 'ugpgcolleges.com');

        $this->postJson('/api/v1/leads', $this->payload(), ['Authorization' => "Bearer $k1"])->assertCreated();
        $this->postJson('/api/v1/leads', $this->payload(), ['Authorization' => "Bearer $k2"])->assertCreated();
        $this->postJson('/api/v1/leads', $this->payload(['mobile' => '9123456789', 'email' => null]), ['X-Api-Key' => $k3])->assertCreated();

        $this->assertSame(2, Student::count());
        $this->assertEqualsCanonicalizing(['Collegioz website', 'Vinayaka Consultancy website', 'ugpgcolleges.com'],
            Enquiry::with('source')->get()->pluck('source.name')->all());
        $s = Student::where('mobile', '9876543210')->first();
        $this->assertSame(2, $s->enquiry_count);
        $this->assertSame('New', $s->status->name);
        $this->assertNull($s->assigned_user_id); // goes to the Unassigned list
    }

    public function test_reply_does_not_reveal_existing_students(): void
    {
        [, $k] = $this->connect('Collegioz', 'Collegioz website');
        $a = $this->postJson('/api/v1/leads', $this->payload(), ['Authorization' => "Bearer $k"]);
        $b = $this->postJson('/api/v1/leads', $this->payload(), ['Authorization' => "Bearer $k"]);
        $this->assertSame($a->json(), $b->json());
    }

    public function test_wrong_missing_or_switched_off_key_is_refused(): void
    {
        [$c, $k] = $this->connect('Collegioz', 'Collegioz website');
        $this->postJson('/api/v1/leads', $this->payload())->assertUnauthorized();
        $this->postJson('/api/v1/leads', $this->payload(), ['Authorization' => 'Bearer '.substr($k, 0, -1).'x'])->assertUnauthorized();

        $c->update(['is_active' => false]);
        $this->postJson('/api/v1/leads', $this->payload(), ['Authorization' => "Bearer $k"])->assertUnauthorized();
        $this->assertSame(0, Student::count());
    }

    public function test_replacing_a_key_stops_the_old_one(): void
    {
        [$c, $old] = $this->connect('Collegioz', 'Collegioz website');
        $new = $c->issueKey();
        $this->postJson('/api/v1/leads', $this->payload(), ['Authorization' => "Bearer $old"])->assertUnauthorized();
        $this->postJson('/api/v1/leads', $this->payload(), ['Authorization' => "Bearer $new"])->assertCreated();
    }

    public function test_validation_consent_and_spam_trap(): void
    {
        [, $k] = $this->connect('Collegioz', 'Collegioz website');
        $h = ['Authorization' => "Bearer $k"];
        $this->postJson('/api/v1/leads', $this->payload(['mobile' => '12345']), $h)->assertStatus(422)->assertJsonValidationErrors('mobile');
        $this->postJson('/api/v1/leads', $this->payload(['consent' => false]), $h)->assertStatus(422)->assertJsonValidationErrors('consent');
        $this->postJson('/api/v1/leads', $this->payload(['hp' => 'http://spam']), $h)->assertStatus(202);
        $this->assertSame(0, Student::count());
    }

    public function test_rate_limit_per_website(): void
    {
        [$c, $k] = $this->connect('Collegioz', 'Collegioz website');
        $c->update(['per_minute_limit' => 3]);
        foreach (range(1, 3) as $i) {
            $this->postJson('/api/v1/leads', $this->payload(['mobile' => "912345678$i"]), ['Authorization' => "Bearer $k"])->assertCreated();
        }
        $this->postJson('/api/v1/leads', $this->payload(['mobile' => '9123456785']), ['Authorization' => "Bearer $k"])->assertStatus(429);
    }

    public function test_admin_creates_website_and_sees_key_once(): void
    {
        $r = $this->actingAs($this->admin)->post(route('admin.websites.store'), ['name' => 'New Site', 'source_name' => 'newsite.in', 'per_minute_limit' => 20]);
        $r->assertRedirect(route('admin.websites'));
        $key = session('new_key.key');
        $this->assertMatchesRegularExpression('/^crm_[a-z0-9]{8}_[A-Za-z0-9]{40}$/', $key);
        $c = WebsiteConnection::where('name', 'New Site')->first();
        $this->assertNotSame($key, $c->key_hash);
        $this->assertSame('website', $c->source->type);
        $this->actingAs($this->admin)->get(route('admin.websites'))->assertOk()->assertSee($key); // shown once
        $this->actingAs($this->admin)->get(route('admin.websites'))->assertOk()->assertDontSee($key); // never again
        $this->actingAs($this->lead1)->get(route('admin.websites'))->assertForbidden();
    }
}

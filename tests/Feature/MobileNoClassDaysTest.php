<?php
namespace Tests\Feature;

use App\Models\NoClassDay;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileNoClassDaysTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost']);
        app('url')->forceRootUrl('http://localhost');
    }

    public function test_calendar_requires_authentication(): void
    {
        $this->getJson(route('api.no-class-days'))->assertUnauthorized();
    }

    public function test_parent_and_teacher_receive_calendar_without_archived_or_old_dates(): void
    {
        $this->travelTo(now()->startOfDay());
        NoClassDay::create(['date' => today(), 'name' => 'Today holiday', 'source' => 'manual']);
        NoClassDay::create(['date' => today()->subDays(2), 'name' => 'Recent holiday', 'source' => 'manual']);
        NoClassDay::create(['date' => today()->subDays(46), 'name' => 'Old holiday', 'source' => 'manual']);
        $archived = NoClassDay::create(['date' => today()->addDay(), 'name' => 'Archived holiday', 'source' => 'manual']);
        $archived->delete();
        foreach (['parent', 'teacher'] as $name) {
            $role = Role::firstOrCreate(['name' => $name]);
            Sanctum::actingAs(User::factory()->create(['role_id' => $role->id, 'username' => $role->name]));
            $this->getJson(route('api.no-class-days'))->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonCount(1, 'data.upcoming')
                ->assertJsonCount(1, 'data.recent')
                ->assertJsonPath('data.upcoming.0.name', 'Today holiday');
        }
    }

    public function test_other_roles_cannot_access_mobile_calendar(): void
    {
        $role = Role::firstOrCreate(['name' => 'admin']);
        Sanctum::actingAs(User::factory()->create(['role_id' => $role->id, 'username' => $role->name]));
        $this->getJson(route('api.no-class-days'))->assertForbidden();
    }
}
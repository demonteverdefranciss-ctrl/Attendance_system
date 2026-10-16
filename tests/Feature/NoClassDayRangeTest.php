<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\NoClassDay;
use App\Models\Notification;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NoClassDayRangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost']);
        app('url')->forceRootUrl('http://localhost');
        Queue::fake();
        $this->travelTo(now()->setDate(2026, 10, 12)->startOfDay());
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'username' => 'no-class-range-admin',
        ]);
        $this->actingAs($admin);
    }

    public function test_range_marks_weekdays_skips_weekends_and_preserves_existing_holidays(): void
    {
        $parentRole = Role::firstOrCreate(['name' => 'parent']);
        $parentUser = User::factory()->create([
            'role_id' => $parentRole->id,
            'username' => 'no-class-range-parent',
        ]);
        $guardian = Guardian::create([
            'user_id' => $parentUser->id,
            'first_name' => 'Test',
            'last_name' => 'Parent',
            'notify_pref' => 'push',
        ]);
        $googleHoliday = NoClassDay::create([
            'date' => '2026-10-14',
            'name' => 'Google holiday',
            'source' => NoClassDay::SOURCE_GOOGLE,
        ]);

        $this->post(route('admin.no-class-days.range'), [
            'from' => '2026-10-12',
            'to' => '2026-10-18',
            'name' => 'School break',
        ])->assertRedirect();

        $this->assertSame(5, NoClassDay::count());
        $this->assertSame(
            'School break',
            NoClassDay::whereDate('date', '2026-10-12')->firstOrFail()->name,
        );
        $this->assertSame(
            NoClassDay::SOURCE_MANUAL,
            NoClassDay::whereDate('date', '2026-10-12')->firstOrFail()->source,
        );
        $this->assertSame(
            'Google holiday',
            NoClassDay::whereDate('date', '2026-10-14')->firstOrFail()->name,
        );
        $this->assertSame(
            NoClassDay::SOURCE_GOOGLE,
            NoClassDay::whereDate('date', '2026-10-14')->firstOrFail()->source,
        );
        $this->assertFalse(NoClassDay::whereDate('date', '2026-10-17')->exists());
        $this->assertFalse(NoClassDay::whereDate('date', '2026-10-18')->exists());

        $this->assertSame(1, Notification::where('guardian_id', $guardian->id)->count());
        $notification = Notification::where('guardian_id', $guardian->id)->firstOrFail();
        $this->assertSame('no_class_day_range', $notification->type);
        $this->assertSame(4, count($notification->payload['dates']));
        $this->assertSame('School break', $notification->payload['name']);
        $this->assertSame('pending', $notification->status);
    }

    public function test_range_rejects_past_dates_reversed_ranges_and_ranges_over_one_year(): void
    {
        $this->post(route('admin.no-class-days.range'), [
            'from' => '2026-10-11',
            'to' => '2026-10-13',
        ])->assertSessionHasErrors('from');

        $this->post(route('admin.no-class-days.range'), [
            'from' => '2026-10-15',
            'to' => '2026-10-14',
        ])->assertSessionHasErrors('to');

        $this->post(route('admin.no-class-days.range'), [
            'from' => '2026-10-12',
            'to' => '2027-10-13',
        ])->assertSessionHasErrors('to');

        $this->assertSame(0, NoClassDay::count());
        $this->assertSame(0, Notification::count());
    }
}

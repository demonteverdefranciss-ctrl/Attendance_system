<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReportPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_preserves_filters_and_pdf_can_display_inline(): void
    {
        config(['app.url' => 'http://localhost']);
        app('url')->forceRootUrl('http://localhost');
        $role = Role::firstOrCreate(['name' => 'admin']);
        $this->actingAs(User::factory()->create(['role_id' => $role->id, 'username' => 'preview-admin']));
        $filters = ['from' => today()->subDays(6)->toDateString(), 'to' => today()->toDateString()];
        foreach (['csv', 'pdf'] as $format) {
            $this->get(route('reports.preview', [...$filters, 'format' => $format]))
                ->assertOk()->assertInertia(fn (Assert $page) => $page
                    ->component('Reports/Preview')->where('format', $format)
                    ->where('filters.from', $filters['from'])->where('filters.to', $filters['to'])
                    ->has('records', 0));
        }
        $inline = $this->get(route('reports.pdf', [...$filters, 'inline' => 1]))->assertOk();
        $this->assertStringStartsWith('inline;', $inline->headers->get('Content-Disposition'));
        $download = $this->get(route('reports.pdf', $filters))->assertOk();
        $this->assertStringStartsWith('attachment;', $download->headers->get('Content-Disposition'));
    }

    public function test_teacher_cannot_preview_an_unassigned_section(): void
    {
        config(['app.url' => 'http://localhost']);
        app('url')->forceRootUrl('http://localhost');
        $role = Role::firstOrCreate(['name' => 'teacher']);
        $this->actingAs(User::factory()->create(['role_id' => $role->id, 'username' => 'preview-teacher']));
        $this->get(route('reports.preview', ['format' => 'csv', 'section_id' => 999]))->assertForbidden();
    }
}

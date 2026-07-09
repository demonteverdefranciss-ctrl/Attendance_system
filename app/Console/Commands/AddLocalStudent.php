<?php

namespace App\Console\Commands;

use App\Models\Guardian;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Console\Command;

/**
 * Add a student to the local database (mirror a record created on Railway).
 */
class AddLocalStudent extends Command
{
    protected $signature = 'students:add-local
                            {--lrn= : Learner Reference Number}
                            {--first-name= : First name}
                            {--last-name= : Last name}
                            {--gender= : male or female}
                            {--section= : Section name (e.g. Mabini) or section id}
                            {--guardian= : Guardian id to link (optional)}
                            {--consent : Enable biometric consent}';

    protected $description = 'Create or update a student on localhost (copy from Railway manually)';

    public function handle(): int
    {
        $lrn = $this->option('lrn');
        $firstName = $this->option('first-name');
        $lastName = $this->option('last-name');

        if (! $firstName || ! $lastName) {
            $this->error('Provide --first-name and --last-name (and ideally --lrn).');

            return self::FAILURE;
        }

        $sectionId = $this->resolveSectionId($this->option('section'));
        if ($sectionId === null) {
            $this->error('Section not found. Use --section=Mabini or a numeric section id.');

            return self::FAILURE;
        }

        $student = Student::updateOrCreate(
            $lrn ? ['lrn' => $lrn] : ['first_name' => $firstName, 'last_name' => $lastName],
            [
                'section_id' => $sectionId,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'lrn' => $lrn,
                'gender' => $this->option('gender'),
                'consent_biometric' => (bool) $this->option('consent'),
                'is_active' => true,
            ]
        );

        $guardianId = $this->option('guardian');
        if ($guardianId) {
            $guardian = Guardian::find($guardianId);
            if ($guardian) {
                $guardian->students()->syncWithoutDetaching([
                    $student->id => ['relationship' => 'guardian', 'is_primary' => false],
                ]);
            }
        }

        $this->info("Student #{$student->id}: {$student->last_name}, {$student->first_name} (LRN: {$student->lrn}) in section id {$sectionId}.");

        return self::SUCCESS;
    }

    private function resolveSectionId(?string $section): ?int
    {
        if ($section === null || $section === '') {
            return Section::orderBy('id')->value('id');
        }

        if (ctype_digit($section)) {
            return Section::where('id', (int) $section)->value('id');
        }

        return Section::where('name', $section)->value('id');
    }
}

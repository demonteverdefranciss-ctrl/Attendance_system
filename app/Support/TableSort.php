<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

class TableSort
{
    public static function apply(Builder $query, array $columns, string $prefix = ''): void
    {
        $key = request()->query($prefix.'sort');
        if (!is_string($key) || !array_key_exists($key, $columns)) {
            return;
        }
        $direction = request()->query($prefix.'direction') === 'desc' ? 'desc' : 'asc';
        $query->reorder();
        foreach (is_array($columns[$key]) ? $columns[$key] : [$columns[$key]] as $column) {
            $query->orderBy($column, $direction);
        }
        $query->orderBy($query->getModel()->qualifyColumn('id'));
    }

    public static function related(string $model, string $column, string $foreignKey): Builder
    {
        $related = new $model;
        return $model::query()->select($column)->whereColumn($related->qualifyColumn('id'), $foreignKey)->limit(1);
    }

    public static function attendance(Builder $query, string $prefix = ''): void
    {
        self::apply($query, [
            'date' => self::related(\App\Models\AttendanceSession::class, 'session_date', 'attendance_records.session_id'),
            'student' => [self::related(\App\Models\Student::class, 'last_name', 'attendance_records.student_id'), self::related(\App\Models\Student::class, 'first_name', 'attendance_records.student_id')],
            'section' => \App\Models\AttendanceSession::query()->select('sections.name')->join('sections', 'sections.id', '=', 'attendance_sessions.section_id')->whereColumn('attendance_sessions.id', 'attendance_records.session_id')->limit(1),
            'status' => 'status', 'time_in' => 'time_in', 'time_out' => 'time_out', 'method' => 'method',
        ], $prefix);
    }

    public static function archive(Builder $query): void
    {
        $table = $query->getModel()->getTable();
        $title = match ($table) {
            'students', 'teachers', 'guardians' => ['last_name', 'first_name'],
            'no_class_days' => 'date',
            'schedules' => \App\Models\Section::withTrashed()->select('name')->whereColumn('sections.id', 'schedules.section_id')->limit(1),
            default => 'name',
        };
        self::apply($query, ['title' => $title, 'deleted_at' => 'deleted_at']);
    }
}

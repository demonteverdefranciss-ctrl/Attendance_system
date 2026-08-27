<?php

namespace App\Services;

use App\Models\NoClassDay;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GoogleHolidaySync
{
    /**
     * Pull holidays from Google Calendar into no_class_days.
     *
     * @return array{imported: int, updated: int, removed: int, skipped_manual: int, total: int}
     */
    public function sync(?Carbon $from = null, ?Carbon $to = null): array
    {
        if (! config('school_calendar.google_sync_enabled', true)) {
            throw new RuntimeException('Google Calendar holiday sync is disabled.');
        }

        $from ??= now()->subDays((int) config('school_calendar.sync_past_days', 30))->startOfDay();
        $to ??= now()->addDays((int) config('school_calendar.sync_future_days', 400))->endOfDay();

        $events = $this->fetchEvents($from, $to);

        $imported = 0;
        $updated = 0;
        $skippedManual = 0;
        $syncedDates = [];

        foreach ($events as $event) {
            $date = $event['date'];
            $name = mb_substr($event['name'], 0, 150);
            $syncedDates[] = $date;

            $existing = NoClassDay::query()->whereDate('date', $date)->first();

            if ($existing && $existing->source === 'manual') {
                $skippedManual++;

                continue;
            }

            if ($existing) {
                if ($existing->name !== $name || $existing->source !== 'google') {
                    $existing->update([
                        'name' => $name,
                        'source' => 'google',
                    ]);
                    $updated++;
                }

                continue;
            }

            NoClassDay::query()->create([
                'date' => $date,
                'name' => $name,
                'source' => 'google',
            ]);
            $imported++;
        }

        $removed = 0;
        if ($syncedDates !== []) {
            $removed = NoClassDay::query()
                ->where('source', 'google')
                ->whereDate('date', '>=', $from->toDateString())
                ->whereDate('date', '<=', $to->toDateString())
                ->whereNotIn('date', $syncedDates)
                ->delete();
        }

        return [
            'imported' => $imported,
            'updated' => $updated,
            'removed' => $removed,
            'skipped_manual' => $skippedManual,
            'total' => count($syncedDates),
        ];
    }

    /**
     * @return list<array{date: string, name: string}>
     */
    public function fetchEvents(Carbon $from, Carbon $to): array
    {
        $apiKey = trim((string) config('school_calendar.google_calendar_api_key', ''));

        if ($apiKey !== '') {
            return $this->fetchViaApi($from, $to, $apiKey);
        }

        return $this->fetchViaIcs($from, $to);
    }

    /**
     * @return list<array{date: string, name: string}>
     */
    private function fetchViaApi(Carbon $from, Carbon $to, string $apiKey): array
    {
        $calendarId = (string) config('school_calendar.google_calendar_id');
        $url = sprintf(
            'https://www.googleapis.com/calendar/v3/calendars/%s/events',
            rawurlencode($calendarId)
        );

        $events = [];
        $pageToken = null;

        do {
            $query = [
                'key' => $apiKey,
                'singleEvents' => 'true',
                'orderBy' => 'startTime',
                'timeMin' => $from->copy()->utc()->toIso8601String(),
                'timeMax' => $to->copy()->utc()->toIso8601String(),
                'maxResults' => 250,
            ];
            if ($pageToken) {
                $query['pageToken'] = $pageToken;
            }

            $response = Http::timeout(30)->acceptJson()->get($url, $query);

            if (! $response->successful()) {
                throw new RuntimeException(
                    'Google Calendar API error: '.$response->status().' '.$response->body()
                );
            }

            foreach ($response->json('items', []) as $item) {
                $date = $item['start']['date'] ?? null;
                if (! $date && isset($item['start']['dateTime'])) {
                    $date = Carbon::parse($item['start']['dateTime'])->toDateString();
                }
                $name = trim((string) ($item['summary'] ?? ''));
                if (! $date || $name === '') {
                    continue;
                }
                $events[] = ['date' => $date, 'name' => $name];
            }

            $pageToken = $response->json('nextPageToken');
        } while ($pageToken);

        return $this->uniqueByDate($events);
    }

    /**
     * @return list<array{date: string, name: string}>
     */
    private function fetchViaIcs(Carbon $from, Carbon $to): array
    {
        $icsUrl = trim((string) config('school_calendar.google_calendar_ics_url', ''));
        if ($icsUrl === '') {
            $calendarId = (string) config('school_calendar.google_calendar_id');
            $icsUrl = sprintf(
                'https://calendar.google.com/calendar/ical/%s/public/basic.ics',
                rawurlencode($calendarId)
            );
        }

        $response = Http::timeout(45)
            ->withHeaders(['User-Agent' => 'AttendanceSystemHolidaySync/1.0'])
            ->get($icsUrl);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Failed to download Google Calendar ICS (HTTP '.$response->status().').'
            );
        }

        $events = $this->parseIcs($response->body());
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        return $this->uniqueByDate(array_values(array_filter(
            $events,
            fn (array $e) => $e['date'] >= $fromDate && $e['date'] <= $toDate
        )));
    }

    /**
     * Minimal VEVENT parser for Google all-day holiday feeds.
     *
     * @return list<array{date: string, name: string}>
     */
    private function parseIcs(string $ics): array
    {
        // Unfold folded lines (RFC 5545).
        $ics = preg_replace("/\r\n[ \t]/", '', str_replace("\r\n", "\n", $ics)) ?? $ics;
        $ics = preg_replace("/\n[ \t]/", '', $ics) ?? $ics;

        $events = [];
        if (! preg_match_all('/BEGIN:VEVENT(.*?)END:VEVENT/s', $ics, $matches)) {
            Log::warning('Google holiday ICS contained no VEVENT blocks.');

            return [];
        }

        foreach ($matches[1] as $block) {
            $summary = null;
            $start = null;

            if (preg_match('/^SUMMARY:(.*)$/m', $block, $m)) {
                $summary = $this->unescapeIcs(trim($m[1]));
            }
            if (preg_match('/^DTSTART(?:;VALUE=DATE)?:(\d{8})/m', $block, $m)) {
                $raw = $m[1];
                $start = substr($raw, 0, 4).'-'.substr($raw, 4, 2).'-'.substr($raw, 6, 2);
            } elseif (preg_match('/^DTSTART[^:]*:(\d{8})T/m', $block, $m)) {
                $raw = $m[1];
                $start = substr($raw, 0, 4).'-'.substr($raw, 4, 2).'-'.substr($raw, 6, 2);
            }

            if (! $start || ! $summary) {
                continue;
            }

            $events[] = ['date' => $start, 'name' => $summary];
        }

        return $events;
    }

    private function unescapeIcs(string $value): string
    {
        return str_replace(
            ['\\\\', '\\;', '\\,', '\\n', '\\N'],
            ['\\', ';', ',', "\n", "\n"],
            $value
        );
    }

    /**
     * @param  list<array{date: string, name: string}>  $events
     * @return list<array{date: string, name: string}>
     */
    private function uniqueByDate(array $events): array
    {
        $byDate = [];
        foreach ($events as $event) {
            $byDate[$event['date']] = $event;
        }
        ksort($byDate);

        return array_values($byDate);
    }
}

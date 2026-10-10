<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\ReplyType;
use App\Models\JobApplication;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Numbers for the statistics page and GET /api/v1/stats: how many replies, how fast, by week and by sector.
 * A "reply" is a real answer from the company (automatic replies and bounces do not count).
 */
class ApplicationStats
{
    /** @return array<string, mixed> */
    public function forUser(User $user, int $weeks = 8): array
    {
        /** @var Collection<int, JobApplication> $sent */
        $sent = $user->jobApplications()
            ->whereNotNull('sent_at')
            ->with(['company', 'replies' => fn ($q) => $q->where('type', ReplyType::Reply->value)->orderBy('received_at')])
            ->get();

        $answered = $sent->filter(fn (JobApplication $a) => $a->replies->isNotEmpty());

        return [
            'by_status' => $this->byStatus($user),
            'sent' => $sent->count(),
            'answered' => $answered->count(),
            'reply_rate' => $this->rate($answered->count(), $sent->count()),
            'average_reply_days' => $answered->isEmpty() ? null : round($answered->avg(
                fn (JobApplication $a) => $a->sent_at->diffInHours($a->replies->first()->received_at) / 24
            ), 1),
            'by_week' => $this->byWeek($sent, $weeks),
            'by_sector' => $this->bySector($sent),
        ];
    }

    /** @return array<string, int> status value => count, every status present (0 when none) */
    private function byStatus(User $user): array
    {
        $counts = $user->jobApplications()->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return collect(ApplicationStatus::cases())
            ->mapWithKeys(fn (ApplicationStatus $s) => [$s->value => (int) ($counts[$s->value] ?? 0)])
            ->all();
    }

    /**
     * @param  Collection<int, JobApplication>  $sent
     * @return list<array{week: string, sent: int, answered: int, reply_rate: float|null}>
     */
    private function byWeek(Collection $sent, int $weeks): array
    {
        $result = [];
        for ($i = $weeks - 1; $i >= 0; $i--) {
            $start = CarbonImmutable::now()->startOfWeek()->subWeeks($i);
            $inWeek = $sent->filter(fn (JobApplication $a) => $a->sent_at->betweenIncluded($start, $start->endOfWeek()));
            $answered = $inWeek->filter(fn (JobApplication $a) => $a->replies->isNotEmpty())->count();
            $result[] = [
                'week' => $start->toDateString(),
                'sent' => $inWeek->count(),
                'answered' => $answered,
                'reply_rate' => $this->rate($answered, $inWeek->count()),
            ];
        }

        return $result;
    }

    /**
     * @param  Collection<int, JobApplication>  $sent
     * @return list<array{sector: string, sent: int, answered: int, reply_rate: float|null}>
     */
    private function bySector(Collection $sent): array
    {
        return $sent->groupBy(fn (JobApplication $a) => $a->company->activity ?: ($a->company->naf_code ?: 'Non renseigné'))
            ->map(function (Collection $group, string $sector) {
                $answered = $group->filter(fn (JobApplication $a) => $a->replies->isNotEmpty())->count();

                return ['sector' => $sector, 'sent' => $group->count(), 'answered' => $answered, 'reply_rate' => $this->rate($answered, $group->count())];
            })
            ->sortByDesc('sent')
            ->values()
            ->all();
    }

    /** Percentage with one decimal, or null when nothing was sent (no rate to compute). */
    private function rate(int $part, int $total): ?float
    {
        return $total === 0 ? null : round(100 * $part / $total, 1);
    }
}

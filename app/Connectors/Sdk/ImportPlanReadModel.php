<?php

declare(strict_types=1);

namespace App\Connectors\Sdk;

use App\Connectors\Sdk\Import\ImportPlanItemStatus;
use App\Connectors\Sdk\Import\ImportPlannedAction;
use App\Connectors\Sdk\Import\ImportPlanReason;
use App\Connectors\Sdk\Models\MediaImportPlan;
use App\Connectors\Sdk\Models\MediaImportPlanItem;
use App\Connectors\Sdk\Registry\ConnectorRegistry;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read model for the import dry run (V2 D). Produces secret-free view arrays for
 * `/imports`, `/imports/{plan}` and the dashboard summary.
 *
 * Reads STORED plan rows only — it never calls the network, never rebuilds a
 * normalization, never runs a snapshot and never creates a media item, edition or
 * file. Every list it returns is bounded.
 */
final class ImportPlanReadModel
{
    /** Plans listed on /imports. */
    private const MAX_PLANS = 20;

    /** Items listed per status section on a plan detail page. */
    private const MAX_ITEMS_PER_SECTION = 100;

    /** Duplicate suspects listed on a plan detail page. */
    private const MAX_DUPLICATES = 50;

    public function __construct(
        private readonly ConnectorRegistry $registry,
    ) {}

    /**
     * The /imports payload: aggregate cards, the latest plan and recent plans.
     *
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $latest = $this->latestPlan();

        return [
            'summary' => [
                'plan_count' => MediaImportPlan::query()->count(),
                'latest_status' => $latest === null ? 'empty' : $latest->status,
                'planned_items' => $latest === null ? 0 : $latest->planned_item_count,
                'ready_items' => $latest === null ? 0 : $latest->ready_count,
                'warning_items' => $latest === null ? 0 : $latest->warning_count,
                'blocked_items' => $latest === null ? 0 : $latest->blocked_count,
                'review_items' => $latest === null ? 0 : $latest->review_count,
                'duplicate_items' => $latest === null ? 0 : $latest->duplicate_count,
                'unsupported_items' => $latest === null ? 0 : $latest->unsupported_count,
                'skipped_items' => $latest === null ? 0 : $latest->skipped_count,
            ],
            'latestPlan' => $latest === null ? null : $this->planView($latest),
            'plans' => $this->recentPlans(),
        ];
    }

    /** The newest plan, tie-broken on the monotonic ULID so it is deterministic. */
    public function latestPlan(): ?MediaImportPlan
    {
        /** @var MediaImportPlan|null $plan */
        $plan = MediaImportPlan::query()
            ->with(['instance:id,connector_key', 'library:id,name'])
            ->latest('created_at')
            ->orderByDesc('id')
            ->first();

        return $plan;
    }

    /**
     * Dashboard-level summary of the latest dry run. Stored state only.
     *
     * @return array<string, mixed>
     */
    public function dashboardSummary(): array
    {
        $latest = $this->latestPlan();

        return [
            'plan_count' => MediaImportPlan::query()->count(),
            'status' => $latest === null ? 'empty' : $latest->status,
            'planned_items' => $latest === null ? 0 : $latest->planned_item_count,
            'ready_items' => $latest === null ? 0 : $latest->ready_count,
            'warning_items' => $latest === null ? 0 : $latest->warning_count + $latest->review_count,
            'blocked_items' => $latest === null ? 0 : $latest->blocked_count,
            'created_at' => $latest?->created_at?->toIso8601String(),
            'plan_id' => $latest === null ? null : $latest->id,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function recentPlans(): array
    {
        return array_values(MediaImportPlan::query()
            ->with(['instance:id,connector_key', 'library:id,name'])
            ->latest('created_at')
            ->orderByDesc('id')
            ->limit(self::MAX_PLANS)
            ->get()
            ->map(fn (MediaImportPlan $plan): array => $this->planView($plan))
            ->all());
    }

    /**
     * A plan header: what it covered, how it came out and how many of each kind of
     * line it holds. No items — the detail page loads those separately.
     *
     * @return array<string, mixed>
     */
    public function planView(MediaImportPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'scope' => $plan->scope_type,
            'status' => $plan->status,
            'connector' => $this->connectorLabel($plan->instance?->connector_key),
            'library_name' => $plan->library?->name,
            'source_item_count' => $plan->source_item_count,
            'planned_item_count' => $plan->planned_item_count,
            'ready_count' => $plan->ready_count,
            'warning_count' => $plan->warning_count,
            'blocked_count' => $plan->blocked_count,
            'skipped_count' => $plan->skipped_count,
            'review_count' => $plan->review_count,
            'duplicate_count' => $plan->duplicate_count,
            'unsupported_count' => $plan->unsupported_count,
            'truncated' => ($plan->summary['truncated'] ?? false) === true,
            'cap' => is_numeric($plan->summary['cap'] ?? null) ? (int) $plan->summary['cap'] : null,
            'reasons' => $this->summaryReasons($plan),
            'note' => is_string($plan->summary['note'] ?? null) ? $plan->summary['note'] : '',
            'created_at' => $plan->created_at?->toIso8601String(),
        ];
    }

    /**
     * The /imports/{plan} payload: the header plus one bounded, deterministic list
     * per outcome, so the page reads as "what would happen, and what has to be
     * decided first".
     *
     * @return array<string, mixed>
     */
    public function planDetail(MediaImportPlan $plan): array
    {
        return [
            'plan' => $this->planView($plan),
            'sections' => [
                'ready' => $this->items($plan, ImportPlanItemStatus::Ready),
                'warning' => $this->items($plan, ImportPlanItemStatus::Warning),
                'needs_review' => $this->items($plan, ImportPlanItemStatus::NeedsReview),
                'blocked' => $this->items($plan, ImportPlanItemStatus::Blocked),
                'skipped' => $this->items($plan, ImportPlanItemStatus::Skipped),
            ],
            'duplicates' => $this->duplicateSuspects($plan),
            'targets' => $this->plannedTargets($plan),
        ];
    }

    /**
     * One status section of a plan. Ordered by ULID so the same plan always renders
     * in the same order.
     *
     * @return array{data: list<array<string, mixed>>, total: int, shown: int}
     */
    private function items(MediaImportPlan $plan, ImportPlanItemStatus $status): array
    {
        $query = $this->scoped($plan)->where('status', $status->value);
        $total = (clone $query)->count();

        $rows = $query
            ->with(['instance:id,connector_key', 'library:id,name'])
            ->orderBy('id')
            ->limit(self::MAX_ITEMS_PER_SECTION)
            ->get();

        return [
            'data' => array_values($rows->map(fn (MediaImportPlanItem $item): array => $this->itemView($item))->all()),
            'total' => $total,
            'shown' => $rows->count(),
        ];
    }

    /**
     * The plan lines flagged as duplicate suspects — listed separately because
     * "two captured items claim the same identity" is the decision a later import
     * most needs a human for. Nothing here is merged or accepted.
     *
     * @return array{data: list<array<string, mixed>>, total: int, shown: int}
     */
    private function duplicateSuspects(MediaImportPlan $plan): array
    {
        $query = $this->scoped($plan)
            ->whereJsonContains('reasons', ImportPlanReason::DuplicateSuspect->value);

        $total = (clone $query)->count();

        $rows = $query
            ->with(['instance:id,connector_key', 'library:id,name'])
            ->orderBy('target_title')
            ->orderBy('id')
            ->limit(self::MAX_DUPLICATES)
            ->get();

        return [
            'data' => array_values($rows->map(fn (MediaImportPlanItem $item): array => $this->itemView($item))->all()),
            'total' => $total,
            'shown' => $rows->count(),
        ];
    }

    /**
     * The target structure a later import would build, aggregated by planned kind.
     * This is the "what would this become?" view — logical shapes only, never a
     * directory layout, because V2 D plans no file location at all.
     *
     * @return list<array<string, mixed>>
     */
    private function plannedTargets(MediaImportPlan $plan): array
    {
        $rows = $this->scoped($plan)
            ->toBase()
            ->select('planned_kind', 'planned_action')
            ->selectRaw('COUNT(*) AS item_count')
            ->selectRaw("COUNT(*) FILTER (WHERE status = 'ready') AS ready_count")
            ->selectRaw('COUNT(DISTINCT target_key) AS target_count')
            ->groupBy('planned_kind', 'planned_action')
            ->orderBy('planned_kind')
            ->orderBy('planned_action')
            ->get();

        $out = [];

        foreach ($rows as $row) {
            $action = ImportPlannedAction::tryFrom(is_string($row->planned_action) ? $row->planned_action : '')
                ?? ImportPlannedAction::NeedsReview;

            $out[] = [
                'planned_kind' => is_string($row->planned_kind) ? $row->planned_kind : 'unknown',
                'planned_action' => $action->value,
                'planned_action_label' => $action->label(),
                'item_count' => $this->countProp($row, 'item_count'),
                'ready_count' => $this->countProp($row, 'ready_count'),
                'target_count' => $this->countProp($row, 'target_count'),
            ];
        }

        return $out;
    }

    /** @return Builder<MediaImportPlanItem> */
    private function scoped(MediaImportPlan $plan): Builder
    {
        return MediaImportPlanItem::query()->where('media_import_plan_id', $plan->id);
    }

    /** @return array<string, mixed> */
    private function itemView(MediaImportPlanItem $item): array
    {
        $action = ImportPlannedAction::tryFrom($item->planned_action) ?? ImportPlannedAction::NeedsReview;

        return [
            'id' => $item->id,
            'target_title' => $item->target_title,
            'target_key' => $item->target_key,
            'target_parent_key' => $item->target_parent_key,
            'planned_kind' => $item->planned_kind,
            'planned_action' => $action->value,
            'planned_action_label' => $action->label(),
            'status' => $item->status,
            'confidence' => $item->confidence,
            'target_year' => $item->target_year,
            'target_season_number' => $item->target_season_number,
            'target_episode_number' => $item->target_episode_number,
            'reasons' => $this->reasonViews($item->reasons),
            'connector' => $this->connectorLabel($item->instance?->connector_key),
            'library_name' => $item->library?->name,
        ];
    }

    /**
     * The plan-level reason breakdown stored in the summary, re-validated against
     * the enum so nothing unexpected can reach the UI.
     *
     * @return list<array{code: string, message: string, item_count: int}>
     */
    private function summaryReasons(MediaImportPlan $plan): array
    {
        $stored = $plan->summary['reasons'] ?? [];

        if (!is_array($stored)) {
            return [];
        }

        $views = [];

        foreach ($stored as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $code = is_string($entry['code'] ?? null) ? $entry['code'] : '';
            $reason = ImportPlanReason::tryFrom($code);

            if ($reason === null) {
                continue;
            }

            $views[] = [
                'code' => $reason->value,
                'message' => $reason->message(),
                'item_count' => is_numeric($entry['item_count'] ?? null) ? (int) $entry['item_count'] : 0,
            ];
        }

        return $views;
    }

    /**
     * Reason codes → {code, message}. Unknown codes are dropped rather than echoed.
     *
     * @param  list<string>  $codes
     * @return list<array{code: string, message: string}>
     */
    private function reasonViews(array $codes): array
    {
        $views = [];

        foreach ($codes as $code) {
            $reason = ImportPlanReason::tryFrom($code);

            if ($reason !== null) {
                $views[] = ['code' => $reason->value, 'message' => $reason->message()];
            }
        }

        return $views;
    }

    /** @return array{key: string, label: string}|null */
    private function connectorLabel(?string $key): ?array
    {
        if ($key === null || !$this->registry->has($key)) {
            return null;
        }

        return ['key' => $key, 'label' => $this->registry->get($key)->label()];
    }

    /** COUNT(*) results come back as numeric strings on the pgsql driver. */
    private function countProp(object $row, string $key): int
    {
        $value = $row->{$key} ?? null;

        return is_numeric($value) ? (int) $value : 0;
    }
}

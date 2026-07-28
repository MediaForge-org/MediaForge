<?php

declare(strict_types=1);

namespace App\Connectors\Sdk;

use App\Connectors\Sdk\Import\ImportableMediaKind;
use App\Connectors\Sdk\Import\ImportExecutionAction;
use App\Connectors\Sdk\Import\ImportExecutionItemStatus;
use App\Connectors\Sdk\Import\ImportExecutionReason;
use App\Connectors\Sdk\Models\MediaExternalMapping;
use App\Connectors\Sdk\Models\MediaImportExecution;
use App\Connectors\Sdk\Models\MediaImportExecutionItem;
use App\Connectors\Sdk\Models\MediaImportPlan;
use App\Core\Media\MediaItem;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read model for the internal import (V2 E). Produces secret-free view arrays for
 * `/imports`, `/imports/runs/{run}` and the dashboard's internal-media summary.
 *
 * Reads STORED rows only — it never calls the network, never runs a snapshot, never
 * rebuilds a normalization, never creates a plan and never imports anything. Every
 * list it returns is bounded.
 */
final class MediaImportReadModel
{
    /** Executions listed on /imports. */
    private const MAX_EXECUTIONS = 10;

    /** Lines listed per section on a run detail page. */
    private const MAX_ITEMS_PER_SECTION = 100;

    /**
     * Aggregate of what actually lives in the internal catalog, for /imports and
     * the dashboard.
     *
     * @return array<string, mixed>
     */
    public function internalMediaSummary(): array
    {
        $counts = MediaItem::query()
            ->toBase()
            ->whereNull('deleted_at')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("COUNT(*) FILTER (WHERE source = 'connector_import') AS imported")
            ->selectRaw("COUNT(*) FILTER (WHERE media_type = 'movie') AS movies")
            ->selectRaw("COUNT(*) FILTER (WHERE media_type = 'show') AS series")
            ->selectRaw("COUNT(*) FILTER (WHERE media_type = 'season') AS seasons")
            ->selectRaw("COUNT(*) FILTER (WHERE media_type = 'episode') AS episodes")
            ->selectRaw("COUNT(*) FILTER (WHERE media_type IN ('audiobook','ebook')) AS books")
            ->first();

        $latest = $this->latestExecution();

        return [
            'media_items' => $this->countProp($counts, 'total'),
            'imported_items' => $this->countProp($counts, 'imported'),
            'movies' => $this->countProp($counts, 'movies'),
            'series' => $this->countProp($counts, 'series'),
            'seasons' => $this->countProp($counts, 'seasons'),
            'episodes' => $this->countProp($counts, 'episodes'),
            'books' => $this->countProp($counts, 'books'),
            'execution_count' => MediaImportExecution::query()->count(),
            'latest_execution' => $latest === null ? null : $this->executionView($latest),
            // Plans a human still has to look at before more can be imported.
            'plans_needing_review' => MediaImportPlan::query()
                ->whereIn('status', ['warnings', 'blocked'])
                ->count(),
        ];
    }

    /** The newest execution, tie-broken on the monotonic ULID so it is deterministic. */
    public function latestExecution(): ?MediaImportExecution
    {
        /** @var MediaImportExecution|null $execution */
        $execution = MediaImportExecution::query()
            ->with('plan')
            ->latest('created_at')
            ->orderByDesc('id')
            ->first();

        return $execution;
    }

    /**
     * Recent executions for the /imports list.
     *
     * @return list<array<string, mixed>>
     */
    public function recentExecutions(): array
    {
        return array_values(MediaImportExecution::query()
            ->with('plan')
            ->latest('created_at')
            ->orderByDesc('id')
            ->limit(self::MAX_EXECUTIONS)
            ->get()
            ->map(fn (MediaImportExecution $execution): array => $this->executionView($execution))
            ->all());
    }

    /**
     * The executions of one plan, so the plan page can say "already imported".
     *
     * @return list<array<string, mixed>>
     */
    public function executionsForPlan(MediaImportPlan $plan): array
    {
        return array_values(MediaImportExecution::query()
            ->where('media_import_plan_id', $plan->id)
            ->latest('created_at')
            ->orderByDesc('id')
            ->limit(self::MAX_EXECUTIONS)
            ->get()
            ->map(fn (MediaImportExecution $execution): array => $this->executionView($execution))
            ->all());
    }

    /**
     * Which plan ids already have at least one execution, keyed for a cheap lookup
     * in the /imports list.
     *
     * @param  list<string>  $planIds
     * @return array<string, int>
     */
    public function executionCountsByPlan(array $planIds): array
    {
        if ($planIds === []) {
            return [];
        }

        /** @var array<string, int> $out */
        $out = [];

        $rows = MediaImportExecution::query()
            ->toBase()
            ->select('media_import_plan_id')
            ->selectRaw('COUNT(*) AS execution_count')
            ->whereIn('media_import_plan_id', $planIds)
            ->groupBy('media_import_plan_id')
            ->get();

        foreach ($rows as $row) {
            if (is_string($row->media_import_plan_id)) {
                $out[$row->media_import_plan_id] = $this->countProp($row, 'execution_count');
            }
        }

        return $out;
    }

    /** @return array<string, mixed> */
    public function executionView(MediaImportExecution $execution): array
    {
        return [
            'id' => $execution->id,
            'plan_id' => $execution->media_import_plan_id,
            'status' => $execution->status,
            'scope' => $execution->plan?->scope_type,
            'imported_count' => $execution->imported_count,
            'skipped_count' => $execution->skipped_count,
            'already_existing_count' => $execution->already_existing_count,
            'failed_count' => $execution->failed_count,
            'candidate_count' => is_numeric($execution->summary['candidate_count'] ?? null)
                ? (int) $execution->summary['candidate_count']
                : 0,
            'reasons' => $this->summaryReasons($execution),
            'note' => is_string($execution->summary['note'] ?? null) ? $execution->summary['note'] : '',
            'created_at' => $execution->created_at?->toIso8601String(),
        ];
    }

    /**
     * The /imports/runs/{run} payload: the header plus one bounded, deterministic
     * list per outcome, so the page reads as "what was created, and what was not".
     *
     * @return array<string, mixed>
     */
    public function executionDetail(MediaImportExecution $execution): array
    {
        return [
            'execution' => $this->executionView($execution),
            'sections' => [
                'created' => $this->items($execution, ImportExecutionAction::Created),
                'linked_existing' => $this->items($execution, ImportExecutionAction::LinkedExisting),
                'skipped' => $this->itemsByStatus($execution, ImportExecutionItemStatus::Skipped),
                'failed' => $this->itemsByStatus($execution, ImportExecutionItemStatus::Failed),
            ],
        ];
    }

    /**
     * @return array{data: list<array<string, mixed>>, total: int, shown: int}
     */
    private function items(MediaImportExecution $execution, ImportExecutionAction $action): array
    {
        return $this->section($this->scoped($execution)->where('action', $action->value));
    }

    /**
     * @return array{data: list<array<string, mixed>>, total: int, shown: int}
     */
    private function itemsByStatus(MediaImportExecution $execution, ImportExecutionItemStatus $status): array
    {
        return $this->section($this->scoped($execution)->where('status', $status->value));
    }

    /**
     * @param  Builder<MediaImportExecutionItem>  $query
     * @return array{data: list<array<string, mixed>>, total: int, shown: int}
     */
    private function section(Builder $query): array
    {
        $total = (clone $query)->count();

        $rows = $query
            ->with([
                'catalogItem:id,connector_instance_id,connector_library_id,external_id',
                'catalogItem.instance:id,connector_key',
                'catalogItem.library:id,name',
            ])
            ->orderBy('id')
            ->limit(self::MAX_ITEMS_PER_SECTION)
            ->get();

        return [
            'data' => array_values($rows->map(fn (MediaImportExecutionItem $item): array => $this->itemView($item))->all()),
            'total' => $total,
            'shown' => $rows->count(),
        ];
    }

    /** @return Builder<MediaImportExecutionItem> */
    private function scoped(MediaImportExecution $execution): Builder
    {
        return MediaImportExecutionItem::query()->where('media_import_execution_id', $execution->id);
    }

    /** @return array<string, mixed> */
    private function itemView(MediaImportExecutionItem $item): array
    {
        $action = ImportExecutionAction::tryFrom($item->action) ?? ImportExecutionAction::Failed;
        $catalogItem = $item->catalogItem;

        return [
            'id' => $item->id,
            'title' => $item->title,
            'media_kind' => $item->media_kind,
            'action' => $action->value,
            'action_label' => $action->label(),
            'status' => $item->status,
            // A ULID only — there is no media item detail route yet, so the UI
            // must not turn this into a link.
            'media_item_id' => $item->media_item_id,
            'reasons' => $this->reasonViews($item->reason_codes),
            'connector' => $catalogItem?->instance?->connector_key,
            'library_name' => $catalogItem?->library?->name,
        ];
    }

    /**
     * Which captured catalog items already have an internal record, keyed by
     * catalog item id. Used to badge the catalog list; bounded by the page size.
     *
     * @param  list<string>  $catalogItemIds
     * @return array<string, true>
     */
    public function importedCatalogItemIds(array $catalogItemIds): array
    {
        if ($catalogItemIds === []) {
            return [];
        }

        /** @var array<string, true> $out */
        $out = [];

        foreach (MediaExternalMapping::query()
            ->whereIn('connector_catalog_item_id', $catalogItemIds)
            ->pluck('connector_catalog_item_id') as $id) {
            if (is_string($id)) {
                $out[$id] = true;
            }
        }

        return $out;
    }

    /**
     * The kinds the internal import covers, for the UI to explain what a run will
     * and will not touch.
     *
     * @return list<string>
     */
    public function importableKinds(): array
    {
        return ImportableMediaKind::values();
    }

    /**
     * The execution-level reason breakdown, re-validated against the enum so
     * nothing unexpected can reach the UI.
     *
     * @return list<array{code: string, message: string, item_count: int}>
     */
    private function summaryReasons(MediaImportExecution $execution): array
    {
        $stored = $execution->summary['reasons'] ?? [];

        if (!is_array($stored)) {
            return [];
        }

        $views = [];

        foreach ($stored as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $code = is_string($entry['code'] ?? null) ? $entry['code'] : '';
            $reason = ImportExecutionReason::tryFrom($code);

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
            $reason = ImportExecutionReason::tryFrom($code);

            if ($reason !== null) {
                $views[] = ['code' => $reason->value, 'message' => $reason->message()];
            }
        }

        return $views;
    }

    /** COUNT(*) results come back as numeric strings on the pgsql driver. */
    private function countProp(?object $row, string $key): int
    {
        if ($row === null) {
            return 0;
        }

        $value = $row->{$key};

        return is_numeric($value) ? (int) $value : 0;
    }
}

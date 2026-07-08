<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Artifacts\Artifact;
use App\Core\Audit\AuditLog;
use App\Core\Audit\AuditRecorder;
use App\Core\Audit\DatabaseAuditRecorder;
use App\Core\Jobs\CheckpointStore;
use App\Core\Jobs\DatabaseCheckpointStore;
use App\Core\Media\Credit;
use App\Core\Media\Library;
use App\Core\Media\MediaEdition;
use App\Core\Media\MediaFile;
use App\Core\Media\MediaItem;
use App\Core\Media\Person;
use App\Core\Media\Tag;
use App\Core\Provider\ProviderId;
use App\Core\Review\ReviewTask;
use App\Core\Settings\Setting;
use App\Core\Settings\SettingDefinition;
use App\Core\Settings\SettingsRegistry;
use App\Core\Settings\SettingType;
use App\Core\WatchState\UserWatchState;
use App\Core\WatchState\WatchStateEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the shared foundation contracts (audit, resumable jobs, settings)
 * that every module and connector builds upon.
 */
final class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingsRegistry::class);
        $this->app->bind(AuditRecorder::class, DatabaseAuditRecorder::class);
        $this->app->bind(CheckpointStore::class, DatabaseCheckpointStore::class);
    }

    public function boot(): void
    {
        $this->enforceMorphMap();
        $this->registerFoundationSettings();
    }

    /**
     * Stable morph aliases — used as audit `subject_type` and polymorphic keys.
     * enforceMorphMap also prevents class names leaking into the database.
     */
    private function enforceMorphMap(): void
    {
        Relation::enforceMorphMap([
            'user' => User::class,
            'library' => Library::class,
            'media_item' => MediaItem::class,
            'media_edition' => MediaEdition::class,
            'file' => MediaFile::class,
            'person' => Person::class,
            'credit' => Credit::class,
            'tag' => Tag::class,
            'provider_id' => ProviderId::class,
            'user_watch_state' => UserWatchState::class,
            'watch_state_event' => WatchStateEvent::class,
            'review_task' => ReviewTask::class,
            'artifact' => Artifact::class,
            'setting' => Setting::class,
            'audit_log' => AuditLog::class,
        ]);
    }

    private function registerFoundationSettings(): void
    {
        $registry = $this->app->make(SettingsRegistry::class);

        $definitions = [
            new SettingDefinition('api.token_expiry_days', 365, SettingType::Integer, 'Default lifetime of new API tokens in days.'),
            new SettingDefinition('api.rate_limit_standard', 300, SettingType::Integer, 'Standard read requests per minute.'),
            new SettingDefinition('api.rate_limit_write', 60, SettingType::Integer, 'Write requests per minute.'),
            new SettingDefinition('api.rate_limit_playback', 600, SettingType::Integer, 'Playback-report requests per minute.'),
            new SettingDefinition('security.cors_allowed_origins', [], SettingType::ArrayType, 'Allowed CORS origins for the REST API (empty = none).'),
        ];

        foreach ($definitions as $definition) {
            $registry->register($definition);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

final class RuntimeReset extends Command
{
    /** @var string */
    protected $signature = 'mediaforge:runtime:reset';

    /** @var string */
    protected $description = 'Force the stable production-build runtime: remove public/hot and clear caches';

    public function handle(): int
    {
        if (!app()->environment(['local', 'testing'])) {
            $this->error('mediaforge:runtime:reset is available only in local or testing environments.');

            return self::FAILURE;
        }

        $hot = public_path('hot');

        if (File::exists($hot)) {
            try {
                File::delete($hot);
                $this->info('Removed public/hot — Vite HMR pointer cleared.');
            } catch (Throwable $e) {
                $this->warn('Could not remove public/hot ('.$e->getMessage().').');
                $this->warn('Delete it from the host instead: Remove-Item public/hot -Force');
            }
        } else {
            $this->line('public/hot already absent — serving the production build.');
        }

        $this->call('optimize:clear');

        $this->newLine();
        $this->info('Runtime reset to production-build mode.');
        $this->line('Next: rebuild assets if needed (make assets) and hard-reload the browser with Ctrl+Shift+R.');

        return self::SUCCESS;
    }
}

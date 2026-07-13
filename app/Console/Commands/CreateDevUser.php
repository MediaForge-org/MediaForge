<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Support\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

final class CreateDevUser extends Command
{
    private const EMAIL = 'test@mediaforge.local';

    private const PASSWORD = 'test123456';

    /** @var string */
    protected $signature = 'mediaforge:dev-user';

    /** @var string */
    protected $description = 'Create or update the local MediaForge development user';

    public function handle(): int
    {
        if (!app()->environment(['local', 'testing'])) {
            $this->error('mediaforge:dev-user is available only in local or testing environments.');

            return self::FAILURE;
        }

        $user = User::withTrashed()->firstOrNew(['email' => self::EMAIL]);

        if ($user->trashed()) {
            $user->restore();
        }

        $user->fill([
            'name' => 'MediaForge Test User',
            'password_hash' => Hash::make(self::PASSWORD),
            'role' => Role::Member,
            'theme_preference' => 'system',
        ]);
        $user->save();

        $this->info('Development user ready: '.self::EMAIL);
        $this->line('Password: '.self::PASSWORD);

        return self::SUCCESS;
    }
}

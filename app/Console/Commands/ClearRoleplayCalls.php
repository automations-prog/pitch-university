<?php

namespace App\Console\Commands;

use App\Models\RoleplaySession;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Wipes every roleplay call: first the recordings in `roleplay-recordings`
 * (the folder itself stays), then the `roleplay_sessions` rows. Asks first
 * unless `--force` is passed.
 */
#[Signature('roleplay:clear {--force : Clear without asking for confirmation}')]
#[Description('Delete all roleplay call recordings, then all roleplay call sessions')]
class ClearRoleplayCalls extends Command
{
    private const string RECORDINGS_DIRECTORY = 'roleplay-recordings';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $disk = Storage::disk('local');
        $recordings = $disk->allFiles(self::RECORDINGS_DIRECTORY);
        $sessionCount = RoleplaySession::query()->count();

        if (! $this->option('force') && ! $this->confirm(
            'Delete '.count($recordings)." roleplay recordings and {$sessionCount} roleplay calls? This can't be undone.",
        )) {
            $this->warn('Nothing was deleted.');

            return self::FAILURE;
        }

        $disk->delete($recordings);
        $this->info('Deleted '.count($recordings).' roleplay recordings.');

        $deletedSessions = RoleplaySession::query()->delete();
        $this->info("Deleted {$deletedSessions} roleplay calls.");

        return self::SUCCESS;
    }
}

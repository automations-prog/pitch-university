<?php

use App\Models\RoleplaySession;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Storage::disk('local')->put('roleplay-recordings/one.webm', 'audio');
    Storage::disk('local')->put('roleplay-recordings/two.webm', 'audio');
    Storage::disk('local')->put('other/keep.txt', 'keep');
    RoleplaySession::factory()->count(2)->create();
});

test('it deletes the roleplay recordings and then the roleplay calls', function () {
    $this->artisan('roleplay:clear', ['--force' => true])->assertSuccessful();

    expect(Storage::disk('local')->allFiles('roleplay-recordings'))->toBeEmpty()
        ->and(Storage::disk('local')->exists('other/keep.txt'))->toBeTrue()
        ->and(RoleplaySession::count())->toBe(0);
});

test('it deletes nothing when the confirmation is declined', function () {
    $this->artisan('roleplay:clear')
        ->expectsConfirmation("Delete 2 roleplay recordings and 2 roleplay calls? This can't be undone.", 'no')
        ->assertFailed();

    expect(Storage::disk('local')->allFiles('roleplay-recordings'))->toHaveCount(2)
        ->and(RoleplaySession::count())->toBe(2);
});

<?php

use App\Models\FileActivity;
use App\Models\User;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->activityRoot = storage_path('framework/testing/activity-'.bin2hex(random_bytes(6)));
    File::ensureDirectoryExists($this->activityRoot);
    config(['share.driver' => 'local', 'share.root' => $this->activityRoot]);
});

afterEach(function () {
    File::deleteDirectory($this->activityRoot);
});

it('records successful and failed requests without storing file content or secrets', function () {
    $this->actingAs(User::factory()->create(['name' => 'Tester']));
    $this->postJson('/share/operations', ['action' => 'file', 'name' => 'note.txt', 'content' => 'PRIVATE-CONTENT', 'password' => 'PRIVATE-PASSWORD'])->assertOk();
    $this->postJson('/share/operations', ['action' => 'file', 'name' => 'note.txt'])->assertConflict();
    $rows = $this->getJson('/share/activity')->assertOk()->json('data');
    expect($rows)->toHaveCount(2);
    expect($rows[0]['status'])->toBe('failed');
    expect($rows[0]['http_status'])->toBe(409);
    expect($rows[1]['status'])->toBe('success');
    expect($rows[1]['actor_name'])->toBe('Tester');
    expect(json_encode($rows))->not->toContain('PRIVATE-CONTENT')->not->toContain('PRIVATE-PASSWORD')->not->toContain($this->activityRoot);
});

it('records rename destination download preparation and recycle bin original path', function () {
    $this->actingAs(User::factory()->create());
    File::put($this->activityRoot.'/a.txt', 'Example');
    $this->postJson('/share/operations', ['action' => 'rename', 'sources' => ['a.txt'], 'name' => 'b.txt'])->assertOk();
    $this->get('/share/download?path=b.txt')->assertDownload('b.txt');
    expect(FileActivity::where('action', 'download')->first()->status)->toBe('prepared');
    expect(FileActivity::where('action', 'rename')->first()->details['name'])->toBe('b.txt');
    $this->postJson('/share/operations', ['action' => 'delete', 'sources' => ['b.txt']])->assertOk();
    $id = $this->getJson('/share/trash')->json('entries.0.id');
    $this->postJson('/share/trash', ['action' => 'restore', 'id' => $id])->assertOk();
    expect(FileActivity::where('action', 'restore')->first()->details['original_path'])->toBe('b.txt');
});

it('only exposes the current users history for the current root', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner)->postJson('/share/operations', ['action' => 'file', 'name' => 'own.txt'])->assertOk();
    $this->actingAs(User::factory()->create())->getJson('/share/activity')->assertJsonPath('total', 0);
    $this->actingAs($owner)->getJson('/share/activity')->assertJsonPath('total', 1);
    config(['share.root' => $this->activityRoot.'-other']);
    $this->getJson('/share/activity')->assertJsonPath('total', 0);
});

it('filters and paginates history without creating history for the history view', function () {
    $this->actingAs(User::factory()->create());
    for ($index = 0; $index < 23; $index++) {
        FileActivity::create(['actor_key' => FileActivity::actorKey(), 'root_key' => FileActivity::rootKey(), 'actor_name' => 'Tester', 'action' => $index === 0 ? 'rename' : 'upload', 'status' => 'success', 'details' => []]);
    }
    $this->getJson('/share/activity')->assertJsonCount(20, 'data')->assertJsonPath('last_page', 2);
    $this->getJson('/share/activity?page=2')->assertJsonCount(3, 'data');
    $this->getJson('/share/activity?action=rename')->assertJsonPath('total', 1);
    $this->getJson('/share/activity?action=invalid')->assertUnprocessable();
    expect(FileActivity::count())->toBe(23);
});

it('requires authentication for activity history', function () {
    $this->getJson('/share/activity')->assertUnauthorized();
});

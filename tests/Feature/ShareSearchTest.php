<?php

use App\Models\User;
use App\Services\RecycleBin;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->searchRoot = storage_path('framework/testing/search-'.bin2hex(random_bytes(6)));
    config(['share.root' => $this->searchRoot, 'share.driver' => 'local']);
    File::ensureDirectoryExists($this->searchRoot.'/Finance/Archive');
    File::put($this->searchRoot.'/Finance/Archive/REPORT.txt', 'secret content');
    File::put($this->searchRoot.'/report-root.txt', '');
    File::ensureDirectoryExists($this->searchRoot.'/'.RecycleBin::DIRECTORY);
    File::put($this->searchRoot.'/'.RecycleBin::DIRECTORY.'/report-deleted.txt', '');
});

afterEach(function () { File::deleteDirectory($this->searchRoot); });

it('requires login for recursive searches', function () {
    $this->getJson('/share/search?q=report')->assertUnauthorized();
});

it('finds nested names without searching content or exposing recycle bin', function () {
    $this->actingAs(User::factory()->create());
    $response = $this->getJson('/share/search?q=report')->assertOk()->assertJsonCount(2, 'entries')->assertJsonPath('partial', false);
    expect(array_column($response->json('entries'), 'path'))->toContain('Finance/Archive/REPORT.txt', 'report-root.txt');
    $this->getJson('/share/search?q=secret')->assertOk()->assertJsonCount(0, 'entries');
    $this->getJson('/share/search?q=archive')->assertJsonPath('entries.0.directory', true);
});

it('restricts search to the requested folder and rejects invalid paths', function () {
    $this->actingAs(User::factory()->create());
    $this->getJson('/share/search?q=report&path=Finance')->assertOk()->assertJsonCount(1, 'entries')->assertJsonPath('entries.0.path', 'Finance/Archive/REPORT.txt');
    $this->getJson('/share/search?q=report&path=..')->assertUnprocessable();
    $this->getJson('/share/search?q=report&path='.RecycleBin::DIRECTORY)->assertForbidden();
    $this->getJson('/share/search?q=a')->assertUnprocessable();
});

it('reports incomplete results when the result limit is reached', function () {
    config(['share.search_results' => 1]);
    $this->actingAs(User::factory()->create());
    $this->getJson('/share/search?q=report')->assertOk()->assertJsonCount(1, 'entries')->assertJsonPath('partial', true);
});

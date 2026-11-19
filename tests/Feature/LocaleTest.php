<?php

use App\Models\User;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->withCredentials();
    $this->localeRoot = storage_path('framework/testing/locale-'.bin2hex(random_bytes(6)));
    config(['share.driver' => 'local', 'share.root' => $this->localeRoot]);
    File::ensureDirectoryExists($this->localeRoot);
});
afterEach(function () { File::deleteDirectory($this->localeRoot); });

it('localizes operations without translating file names or contents', function ($locale, $message) {
    $this->actingAs(User::factory()->create())->withUnencryptedCookie('app_locale', $locale);
    $this->postJson('/share/operations', ['action' => 'file', 'name' => '报告.txt', 'content' => 'Operasi berhasil.'])->assertOk()->assertJsonPath('message', $message);
    $this->getJson('/share/entries')->assertJsonPath('entries.0.name', '报告.txt');
    $this->getJson('/share/preview?path='.urlencode('报告.txt'))->assertJsonPath('content', 'Operasi berhasil.');
})->with([
    ['id', 'Operasi berhasil.'], ['en', 'Operation successful.'], ['zh-CN', '操作成功。'],
]);

it('localizes conflicts and validation errors', function () {
    $this->actingAs(User::factory()->create())->withUnencryptedCookie('app_locale', 'zh-CN');
    File::put($this->localeRoot.'/existing.txt', '');
    $this->postJson('/share/operations', ['action' => 'file', 'name' => 'existing.txt'])->assertConflict()->assertJsonPath('message', '该名称已存在，未覆盖任何文件。');
    $this->getJson('/share/search?q=a')->assertUnprocessable()->assertJsonPath('errors.q.0', '搜索词至少需要2个字符。');
});

it('falls back to Indonesian for unsupported locale cookies', function () {
    $this->actingAs(User::factory()->create())->withUnencryptedCookie('app_locale', '../../invalid');
    $this->postJson('/share/operations', ['action' => 'folder', 'name' => 'Example'])->assertOk()->assertJsonPath('message', 'Operasi berhasil.');
});

it('uses the selected language for login failures', function () {
    $this->withUnencryptedCookie('app_locale', 'zh-CN')->postJson('/login', ['email' => 'nonexistent@example.test', 'password' => 'not-a-real-password'])->assertUnprocessable()->assertJsonPath('errors.email.0', '邮箱或密码不正确。');
});

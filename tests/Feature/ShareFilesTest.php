<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->shareRoot = storage_path('framework/testing/share-'.bin2hex(random_bytes(6)));
    config(['share.root' => $this->shareRoot, 'share.driver' => 'local']);
    File::ensureDirectoryExists($this->shareRoot);
});

afterEach(function () {
    File::deleteDirectory($this->shareRoot);
});

it('requires authentication for every file endpoint', function () {
    $this->getJson('/share/entries')->assertUnauthorized();
    $this->getJson('/share/download?path=test.txt')->assertUnauthorized();
    $this->postJson('/share/operations', [])->assertUnauthorized();
    $this->postJson('/share/upload', [])->assertUnauthorized();
});

it('creates lists downloads copies moves and deletes real files', function () {
    $this->actingAs(User::factory()->create());
    $this->postJson('/share/operations', ['action' => 'folder', 'name' => 'Documents'])->assertOk();
    $this->postJson('/share/operations', ['action' => 'file', 'name' => 'note.txt', 'content' => 'Hello'])->assertOk();
    $this->getJson('/share/entries')->assertOk()->assertJsonCount(2, 'entries');
    $this->get('/share/download?path=note.txt')->assertDownload('note.txt');
    $this->postJson('/share/operations', ['action' => 'copy', 'sources' => ['note.txt'], 'path' => 'Documents'])->assertOk();
    expect(file_get_contents($this->shareRoot.'/Documents/note.txt'))->toBe('Hello');
    $this->postJson('/share/operations', ['action' => 'delete', 'sources' => ['note.txt']])->assertOk();
    $this->postJson('/share/operations', ['action' => 'move', 'sources' => ['Documents/note.txt']])->assertOk();
    expect(file_exists($this->shareRoot.'/Documents/note.txt'))->toBeFalse();
    $this->postJson('/share/operations', ['action' => 'delete', 'sources' => ['Documents', 'note.txt']])->assertOk();
    $this->getJson('/share/entries')->assertJsonCount(0, 'entries');
});

it('uploads files and never silently overwrites a collision', function () {
    $this->actingAs(User::factory()->create());
    $this->post('/share/upload', ['files' => [UploadedFile::fake()->createWithContent('report.txt', 'Original')]], ['Accept' => 'application/json'])->assertOk();
    $this->post('/share/upload', ['files' => [UploadedFile::fake()->createWithContent('report.txt', 'Changed')]], ['Accept' => 'application/json'])->assertConflict();
    $this->postJson('/share/operations', ['action' => 'file', 'name' => 'report.txt', 'content' => 'Changed'])->assertConflict();
    expect(file_get_contents($this->shareRoot.'/report.txt'))->toBe('Original');
});

it('rejects traversal absolute paths windows aliases and alternate streams', function (string $path) {
    $this->actingAs(User::factory()->create());
    $this->getJson('/share/entries?path='.urlencode($path))->assertUnprocessable();
})->with(['../', '..', '/etc', 'C:\\Windows', 'foo:bar', 'NUL', 'folder.', 'folder ', '\\\\server\\share']);

it('rejects deleting the root and copying a folder into itself', function () {
    $this->actingAs(User::factory()->create());
    $this->postJson('/share/operations', ['action' => 'delete', 'sources' => ['']])->assertUnprocessable();
    $this->postJson('/share/operations', ['action' => 'folder', 'name' => 'parent'])->assertOk();
    $this->postJson('/share/operations', ['action' => 'copy', 'sources' => ['parent'], 'path' => 'parent'])->assertUnprocessable();
});

it('preflights batch conflicts before changing any file', function () {
    $this->actingAs(User::factory()->create());
    File::makeDirectory($this->shareRoot.'/dest');
    File::put($this->shareRoot.'/a.txt', 'A');
    File::put($this->shareRoot.'/b.txt', 'B');
    File::put($this->shareRoot.'/dest/b.txt', 'Existing');
    $this->postJson('/share/operations', ['action' => 'move', 'sources' => ['a.txt', 'b.txt'], 'path' => 'dest'])->assertConflict();
    expect(file_exists($this->shareRoot.'/a.txt'))->toBeTrue();
    expect(file_exists($this->shareRoot.'/dest/a.txt'))->toBeFalse();
});

it('fails closed for unimplemented AD storage', function () {
    $this->actingAs(User::factory()->create());
    config(['share.driver' => 'ad']);
    $this->getJson('/share/entries')->assertStatus(503);
    $this->postJson('/share/operations', ['action' => 'file', 'name' => 'test.txt'])->assertStatus(503);
});

it('blocks local storage in production', function () {
    $this->actingAs(User::factory()->create());
    app()->instance('env', 'production');
    $this->getJson('/share/entries')->assertStatus(503);
});

it('renames files and folders without changing their contents', function () {
    $this->actingAs(User::factory()->create());
    File::makeDirectory($this->shareRoot.'/Documents');
    File::put($this->shareRoot.'/Documents/old.txt', 'Preserve me');
    $this->postJson('/share/operations', ['action' => 'rename', 'sources' => ['Documents/old.txt'], 'name' => 'new.txt'])->assertOk();
    expect(file_get_contents($this->shareRoot.'/Documents/new.txt'))->toBe('Preserve me');
    expect(file_exists($this->shareRoot.'/Documents/old.txt'))->toBeFalse();
    $this->postJson('/share/operations', ['action' => 'rename', 'sources' => ['Documents'], 'name' => 'Reports'])->assertOk();
    expect(file_get_contents($this->shareRoot.'/Reports/new.txt'))->toBe('Preserve me');
});

it('rejects rename collisions traversal multiple sources and empty root', function () {
    $this->actingAs(User::factory()->create());
    File::put($this->shareRoot.'/a.txt', 'A');
    File::put($this->shareRoot.'/b.txt', 'B');
    $this->postJson('/share/operations', ['action' => 'rename', 'sources' => ['a.txt'], 'name' => 'b.txt'])->assertConflict();
    $this->postJson('/share/operations', ['action' => 'rename', 'sources' => ['a.txt'], 'name' => '../escape'])->assertUnprocessable();
    $this->postJson('/share/operations', ['action' => 'rename', 'sources' => ['a.txt', 'b.txt'], 'name' => 'c.txt'])->assertUnprocessable();
    $this->postJson('/share/operations', ['action' => 'rename', 'sources' => [''], 'name' => 'root'])->assertUnprocessable();
    $this->postJson('/share/operations', ['action' => 'rename', 'sources' => ['a.txt'], 'name' => 'a.txt'])->assertOk();
    expect(file_get_contents($this->shareRoot.'/a.txt'))->toBe('A');
    expect(file_get_contents($this->shareRoot.'/b.txt'))->toBe('B');
});

it('requires authenticated verified users for preview', function () {
    $this->getJson('/share/preview?path=a.txt')->assertUnauthorized();
    $this->actingAs(User::factory()->unverified()->create())->getJson('/share/preview?path=a.txt')->assertForbidden();
});

it('previews text as JSON data without executing HTML or trimming content', function () {
    $this->actingAs(User::factory()->create());
    $content = "  <script>alert('test')</script>\nHello  ";
    File::put($this->shareRoot.'/note.txt', $content);
    $response = $this->getJson('/share/preview?path=note.txt')->assertOk()
        ->assertJson(['kind' => 'text', 'content' => $content])
        ->assertHeader('Content-Type', 'application/json')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('previews a real raster image with a verified MIME type', function () {
    $this->actingAs(User::factory()->create());
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6VQAAAABJRU5ErkJggg==');
    File::put($this->shareRoot.'/pixel.png', $png);
    $this->getJson('/share/preview?path=pixel.png')->assertOk()->assertJson(['kind' => 'image', 'source' => 'data:image/png;base64,'.base64_encode($png)]);
});

it('refuses active file formats binary text and fake images in preview', function (string $name, string $content) {
    $this->actingAs(User::factory()->create());
    File::put($this->shareRoot.'/'.$name, $content);
    $this->getJson('/share/preview?path='.urlencode($name))->assertStatus(415);
})->with([
    ['page.html', '<script>alert(1)</script>'],
    ['icon.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'],
    ['fake.png', '<html>not a PNG</html>'],
    ['binary.txt', "binary\0bytes"],
    ['invalid.txt', "\xff\xfe"],
]);

it('limits preview size and confines preview paths to the share', function () {
    $this->actingAs(User::factory()->create());
    File::put($this->shareRoot.'/large.txt', str_repeat('a', 1024 * 1024 + 1));
    $this->getJson('/share/preview?path=large.txt')->assertStatus(413);
    $this->getJson('/share/preview?path=../.env')->assertUnprocessable();
    $this->getJson('/share/preview?path=missing.txt')->assertNotFound();
});

it('moves deletions to a hidden bin and restores complete folders', function () {
    $this->actingAs(User::factory()->create());
    File::makeDirectory($this->shareRoot.'/Documents');
    File::put($this->shareRoot.'/Documents/a.txt', 'Keep this');
    $this->postJson('/share/operations', ['action' => 'delete', 'sources' => ['Documents']])->assertOk();
    expect(file_exists($this->shareRoot.'/Documents'))->toBeFalse();
    $this->getJson('/share/entries')->assertJsonCount(0, 'entries');
    $response = $this->getJson('/share/trash')->assertOk()->assertJsonCount(1, 'entries');
    $item = $response->json('entries.0');
    expect($item['original_path'])->toBe('Documents');
    $this->postJson('/share/trash', ['action' => 'restore', 'id' => $item['id']])->assertOk();
    expect(file_get_contents($this->shareRoot.'/Documents/a.txt'))->toBe('Keep this');
    $this->getJson('/share/trash')->assertJsonCount(0, 'entries');
});

it('keeps deleted data when the restore destination already exists or its parent is missing', function () {
    $this->actingAs(User::factory()->create());
    File::makeDirectory($this->shareRoot.'/Folder');
    File::put($this->shareRoot.'/Folder/a.txt', 'Old');
    $this->postJson('/share/operations', ['action' => 'delete', 'sources' => ['Folder/a.txt']])->assertOk();
    $id = $this->getJson('/share/trash')->json('entries.0.id');
    File::put($this->shareRoot.'/Folder/a.txt', 'New');
    $this->postJson('/share/trash', ['action' => 'restore', 'id' => $id])->assertConflict();
    expect(file_get_contents($this->shareRoot.'/Folder/a.txt'))->toBe('New');
    File::deleteDirectory($this->shareRoot.'/Folder');
    $this->postJson('/share/trash', ['action' => 'restore', 'id' => $id])->assertNotFound();
    $this->getJson('/share/trash')->assertJsonCount(1, 'entries');
});

it('only allows the deleting user to list restore or purge their bin items', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner);
    File::put($this->shareRoot.'/a.txt', 'Private');
    $this->postJson('/share/operations', ['action' => 'delete', 'sources' => ['a.txt']])->assertOk();
    $id = $this->getJson('/share/trash')->json('entries.0.id');
    $this->actingAs(User::factory()->create());
    $this->getJson('/share/trash')->assertJsonCount(0, 'entries');
    $this->postJson('/share/trash', ['action' => 'restore', 'id' => $id])->assertNotFound();
    $this->postJson('/share/trash', ['action' => 'purge', 'id' => $id, 'confirm' => true])->assertNotFound();
    $this->actingAs($owner)->postJson('/share/trash', ['action' => 'restore', 'id' => $id])->assertOk();
});

it('requires explicit confirmation for permanent deletion', function () {
    $this->actingAs(User::factory()->create());
    File::put($this->shareRoot.'/a.txt', 'Test');
    $this->postJson('/share/operations', ['action' => 'delete', 'sources' => ['a.txt']])->assertOk();
    $id = $this->getJson('/share/trash')->json('entries.0.id');
    $this->postJson('/share/trash', ['action' => 'purge', 'id' => $id])->assertUnprocessable();
    $this->getJson('/share/trash')->assertJsonCount(1, 'entries');
    $this->postJson('/share/trash', ['action' => 'purge', 'id' => $id, 'confirm' => true])->assertOk();
    $this->getJson('/share/trash')->assertJsonCount(0, 'entries');
    expect(file_exists($this->shareRoot.'/.sharefolder-trash/'.$id))->toBeFalse();
});

it('blocks direct access and creation of the internal bin directory', function () {
    $this->actingAs(User::factory()->create());
    $this->getJson('/share/trash')->assertOk();
    $this->getJson('/share/entries?path=.sharefolder-trash')->assertForbidden();
    $this->getJson('/share/download?path=.sharefolder-trash/metadata.json')->assertForbidden();
    $this->postJson('/share/operations', ['action' => 'folder', 'name' => '.SHAREFOLDER-TRASH'])->assertForbidden();
    $this->postJson('/share/operations', ['action' => 'delete', 'sources' => ['.sharefolder-trash']])->assertForbidden();
    $this->postJson('/share/trash', ['action' => 'restore', 'id' => '../escape'])->assertUnprocessable();
});

it('requires login for recycle bin endpoints', function () {
    $this->getJson('/share/trash')->assertUnauthorized();
    $this->postJson('/share/trash', [])->assertUnauthorized();
});

it('returns PDF bytes as JSON rather than executable inline content', function () {
    $this->actingAs(User::factory()->create());
    $pdf = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF";
    File::put($this->shareRoot.'/document.pdf', $pdf);
    $this->getJson('/share/preview?path=document.pdf')->assertOk()
        ->assertHeader('Content-Type', 'application/json')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertJson(['kind' => 'pdf', 'data' => base64_encode($pdf)]);
});

it('rejects disguised and oversized PDFs before preview', function () {
    $this->actingAs(User::factory()->create());
    File::put($this->shareRoot.'/fake.pdf', '<html><script>alert(1)</script></html>');
    $this->getJson('/share/preview?path=fake.pdf')->assertStatus(415);
    File::put($this->shareRoot.'/large.pdf', '%PDF-1.4'.str_repeat('x', 10 * 1024 * 1024));
    $this->getJson('/share/preview?path=large.pdf')->assertStatus(413);
});

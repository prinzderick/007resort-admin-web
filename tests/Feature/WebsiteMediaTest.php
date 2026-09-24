<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Tests\Support\CmsApi;
use Tests\Support\CmsTestCase;

/** Media library and the JSON behind the shared image picker. */
class WebsiteMediaTest extends CmsTestCase
{
    public function test_library_grid_shows_alt_tags_size_and_usage_counts(): void
    {
        $this->cms();
        $used = collect(CmsApi::load('media-list')['items'])->firstWhere('usageCount', '>', 0);
        $html = $this->get('/website/media?q=court&tag=padel')->assertOk()->assertSee('Media library')->assertSee($used['alt'])->assertSee('Used '.$used['usageCount'])->getContent();
        $this->assertStringContainsString('data-testid="media-grid"', $html);
        $this->assertStringContainsString('data-testid="uploader"', $html);
        $this->assertTrue($this->sentTo('GET', '/admin/cms/media', fn ($r) => str_contains($r->url(), 'q=court') && str_contains($r->url(), 'tag=padel')));
        $this->assertStringContainsString($used['tags'][0], $html);
    }

    public function test_empty_library_offers_the_first_upload(): void
    {
        $this->cms(['GET /admin/cms/media' => ['items' => [], 'nextCursor' => null]]);
        $this->get('/website/media')->assertOk()->assertSee('No pictures yet')->assertSee('Upload the first picture');
    }

    public function test_the_picker_json_is_normalised_and_searchable(): void
    {
        $this->cms();
        $m = CmsApi::first('media-list');
        $j = $this->getJson('/website/media/picker?q=x&tag=y&cursor=abc')->assertOk()->assertJsonStructure(['items' => [['id', 'url', 'thumbUrl', 'alt', 'tags', 'width', 'height', 'sizeBytes', 'usageCount']], 'nextCursor', 'tags'])->json();
        $this->assertSame($m['id'], $j['items'][0]['id']);
        $this->assertNotSame('', $j['items'][0]['thumbUrl']);
        $this->assertTrue($this->sentTo('GET', '/admin/cms/media', fn ($r) => str_contains($r->url(), 'q=x') && str_contains($r->url(), 'tag=y') && str_contains($r->url(), 'cursor=abc')));
    }

    public function test_the_picker_reports_api_failures_as_json(): void
    {
        $this->cms(['GET /admin/cms/media' => [503, ['title' => 'Unavailable', 'status' => 503, 'detail' => 'Down for maintenance']]]);
        $this->getJson('/website/media/picker')->assertStatus(503)->assertJsonPath('message', 'Down for maintenance');
    }

    public function test_upload_sends_multipart_with_an_idempotency_key_and_returns_the_new_item(): void
    {
        $m = CmsApi::first('media-list');
        $this->cms(['POST /admin/cms/media' => [201, $m]]);
        $this->postJson('/website/media/upload', ['file' => UploadedFile::fake()->image('pool.jpg', 400, 300), 'alt' => 'Pool'])->assertCreated()->assertJsonPath('item.id', $m['id']);
        $req = $this->lastSent('POST', 'admin/cms/media');
        $this->assertStringContainsString('multipart/form-data', $req->header('Content-Type')[0]);
        $this->assertNotEmpty($req->header('Idempotency-Key')[0]);
        $this->assertTrue($req->isMultipart());
        $names = collect($req->data())->pluck('name')->all();
        $this->assertContains('file', $names);
        $this->assertContains('alt', $names);
    }

    public function test_upload_errors_are_readable_json(): void
    {
        $this->cms(['POST /admin/cms/media' => [413, ['title' => 'Too large', 'status' => 413, 'code' => 'media_too_large', 'detail' => 'That image is larger than 8 MB.']]]);
        $this->postJson('/website/media/upload', ['file' => UploadedFile::fake()->image('big.jpg')])->assertStatus(413)->assertJsonPath('code', 'media_too_large')->assertJsonPath('message', 'That image is larger than 8 MB.');
        $this->postJson('/website/media/upload', [])->assertStatus(422);
    }

    public function test_editing_details_sends_lowercased_unique_tags(): void
    {
        $m = CmsApi::first('media-list');
        $this->cms(['PATCH /admin/cms/media/*' => $m]);
        $this->patch('/website/media/'.$m['id'], ['alt' => 'Guests by the pool', 'credit' => 'Photo: Me', 'sourceUrl' => '', 'tags' => ['Pool', 'pool', ' Family ']])->assertRedirect()->assertSessionHas('success');
        $b = $this->body('PATCH', 'admin/cms/media/'.$m['id']);
        $this->assertSame('Guests by the pool', $b['alt']);
        $this->assertSame(['pool', 'family'], $b['tags']);
        $this->assertNull($b['sourceUrl']);
        $this->patch('/website/media/'.$m['id'], ['alt' => str_repeat('a', 300)])->assertSessionHasErrors('alt');
    }

    public function test_usage_endpoint_and_delete_guarded_by_usage(): void
    {
        $m = collect(CmsApi::load('media-list')['items'])->firstWhere('usageCount', '>', 0);
        $this->cms();
        $this->getJson('/website/media/'.$m['id'].'/usage')->assertOk()->assertJsonPath('usageCount', 1);
        [$status, $problem] = CmsApi::failure('media-delete-in-use');
        $this->fakeApi(CmsApi::routes(['DELETE /admin/cms/media/*' => [$status, $problem]]));
        $res = $this->from('/website/media')->delete('/website/media/'.$m['id'])->assertRedirect('/website/media')->assertSessionHas('error_code', 'media_in_use');
        $res->assertSessionHas('blockers', fn ($b) => str_contains($b[0], $problem['usage'][0]['label']));
    }

    public function test_deleting_an_unused_picture_works(): void
    {
        $m = CmsApi::first('media-list');
        $this->cms(['DELETE /admin/cms/media/*' => [204, []]]);
        $this->delete('/website/media/'.$m['id'])->assertRedirect()->assertSessionHas('success', 'Picture deleted.');
    }

    public function test_without_media_permission_there_is_no_upload_edit_or_delete(): void
    {
        $this->cms(perms: CmsApi::VIEWER);
        $html = $this->get('/website/media')->assertOk()->getContent();
        foreach (['data-testid="uploader"', 'data-testid="edit-media"', 'data-testid="delete-media"'] as $x) {
            $this->assertStringNotContainsString($x, $html);
        }
        $this->post('/website/media/upload')->assertForbidden();
        $this->delete('/website/media/'.CmsApi::first('media-list')['id'])->assertForbidden();
        $this->patch('/website/media/'.CmsApi::first('media-list')['id'], ['alt' => 'x'])->assertForbidden();
    }

    public function test_every_website_page_carries_the_shared_picker_dialog(): void
    {
        $this->cms();
        $this->get('/website/blog/new')->assertOk()->assertSee('data-component="media-picker"', false)->assertSee('Choose a picture');
    }
}

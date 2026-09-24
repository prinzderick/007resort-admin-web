<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Tests\Support\CmsApi;
use Tests\Support\CmsTestCase;

/** Gallery: albums, the photo grid (upload, reorder, captions, cover, featured). */
class WebsiteGalleryTest extends CmsTestCase
{
    public function test_album_list_shows_real_albums_counts_and_statuses(): void
    {
        $this->cms();
        $a = CmsApi::first('albums');
        $html = $this->get('/website/gallery?status=PUBLISHED')->assertOk()->assertSee($a['title'])->assertSee('New album')->assertSee('Manage photos')->assertSee('Unpublish')->getContent();
        $this->assertStringContainsString('>'.$a['itemCount'].'<', $html);
        $this->assertTrue($this->sentTo('GET', '/admin/cms/gallery/albums', fn ($r) => str_contains($r->url(), 'status=PUBLISHED')));
    }

    public function test_empty_gallery_offers_add_first_album(): void
    {
        $this->cms(['GET /admin/cms/gallery/albums' => ['items' => [], 'nextCursor' => null]]);
        $this->get('/website/gallery')->assertOk()->assertSee('No albums yet')->assertSee('Add first album');
    }

    public function test_creating_an_album_goes_to_its_page(): void
    {
        $a = CmsApi::first('albums');
        $this->cms(['POST /admin/cms/gallery/albums' => [201, $a]]);
        $this->post('/website/gallery', ['title' => 'Poolside', 'description' => ''])->assertRedirect('/website/gallery/'.$a['id']);
        $this->assertSame(['title' => 'Poolside'], $this->body('POST', 'admin/cms/gallery/albums'));
        $this->post('/website/gallery', ['title' => ''])->assertSessionHasErrors('title');
    }

    public function test_album_page_shows_photos_with_cover_badge_upload_area_and_reorder_buttons(): void
    {
        $this->cms();
        $a = CmsApi::load('album');
        $items = CmsApi::load('album-items')['items'];
        $html = $this->get('/website/gallery/'.$a['id'])->assertOk()->assertSee($a['title'])->assertSee('Add photos')->assertSee('Choose from library')->assertSee('Album cover')->assertSee('Save gallery')->getContent();
        $this->assertSame(count($items), substr_count($html, 'data-testid="photo"'));
        $this->assertStringContainsString('data-testid="uploader"', $html);
        $this->assertStringContainsString('data-move="up"', $html);
        $this->assertStringContainsString('name="items['.$items[0]['id'].'][caption]"', $html);
        $this->assertStringContainsString('data-testid="photo-grid"', $html);
    }

    public function test_saving_the_gallery_patches_only_changed_photos_and_saves_the_new_order(): void
    {
        $a = CmsApi::load('album');
        $items = CmsApi::load('album-items')['items'];
        $this->cms(['PATCH /admin/cms/gallery/*' => $a, 'POST /admin/cms/gallery/albums/*/items/reorder' => ['updated' => count($items)]]);
        $form = ['title' => $a['title'], 'slug' => $a['slug'], 'description' => 'New words', 'sortOrder' => '', 'rowVersion' => $a['rowVersion'], 'items' => []];
        foreach (array_reverse($items) as $it) {
            $form['items'][$it['id']] = ['id' => $it['id'], 'caption' => (string) ($it['caption'] ?? ''), 'altText' => (string) ($it['altText'] ?? ''), 'tags' => implode(', ', $it['tags']), 'featured' => $it['featured'] ? '1' : '0'];
        }
        $changed = $items[1]['id'];
        $form['items'][$changed]['caption'] = 'Sunset by the deck';
        $form['items'][$changed]['tags'] = 'Sunset, POOL';
        $this->put('/website/gallery/'.$a['id'], $form)->assertRedirect()->assertSessionHas('success', fn ($m) => str_contains($m, 'album details') && str_contains($m, '1 photo') && str_contains($m, 'the order'));
        $this->assertSame('New words', $this->body('PATCH', 'admin/cms/gallery/albums/'.$a['id'])['description']);
        $patch = $this->body('PATCH', 'admin/cms/gallery/items/'.$changed);
        $this->assertSame('Sunset by the deck', $patch['caption']);
        $this->assertSame(['sunset', 'pool'], $patch['tags']);
        $this->assertFalse($this->sentTo('PATCH', '/admin/cms/gallery/items/'.$items[0]['id']), 'unchanged photos are not patched');
        $order = $this->body('POST', 'admin/cms/gallery/albums/'.$a['id'].'/items/reorder')['items'];
        $this->assertSame(array_column(array_reverse($items), 'id'), array_column($order, 'id'));
    }

    public function test_upload_endpoint_stores_the_media_then_adds_it_to_the_album(): void
    {
        $a = CmsApi::load('album');
        $media = CmsApi::first('media-list');
        $this->cms(['POST /admin/cms/media' => [201, $media], 'POST /admin/cms/gallery/albums/*/items' => [201, ['id' => 'i1', 'mediaId' => $media['id']]]]);
        $this->postJson('/website/gallery/'.$a['id'].'/upload', ['file' => UploadedFile::fake()->image('p.jpg', 200, 100)])->assertCreated()->assertJsonPath('item.id', 'i1');
        $this->assertTrue($this->sentTo('POST', '/admin/cms/media'));
        $this->assertSame($media['id'], $this->body('POST', 'admin/cms/gallery/albums/'.$a['id'].'/items')['mediaId']);
    }

    public function test_upload_errors_come_back_as_json_the_uploader_can_show(): void
    {
        $a = CmsApi::load('album');
        $this->cms(['POST /admin/cms/media' => [415, ['title' => 'Unsupported', 'status' => 415, 'code' => 'media_type_unsupported', 'detail' => 'Only JPEG, PNG, WebP and AVIF images are accepted.']]]);
        $this->postJson('/website/gallery/'.$a['id'].'/upload', ['file' => UploadedFile::fake()->create('x.txt', 3, 'text/plain')])->assertStatus(415)->assertJsonPath('message', 'Only JPEG, PNG, WebP and AVIF images are accepted.');
    }

    public function test_cover_add_from_library_remove_publish_and_delete(): void
    {
        $a = CmsApi::load('album');
        $it = CmsApi::load('album-items')['items'][0];
        $this->cms(['PATCH /admin/cms/gallery/albums/*' => $a, 'POST /admin/cms/gallery/albums/*' => $a, 'DELETE /admin/cms/gallery/*' => [204, []]]);
        $this->post('/website/gallery/'.$a['id'].'/cover', ['mediaId' => $it['mediaId']])->assertRedirect()->assertSessionHas('success', 'Album cover changed.');
        $this->assertSame($it['mediaId'], $this->body('PATCH', 'admin/cms/gallery/albums/'.$a['id'])['coverMediaId']);
        $this->post('/website/gallery/'.$a['id'].'/add', ['mediaId' => $it['mediaId']])->assertRedirect()->assertSessionHas('success');
        $this->delete('/website/gallery/'.$a['id'].'/items/'.$it['id'])->assertRedirect()->assertSessionHas('success', fn ($m) => str_contains($m, 'media library'));
        $this->post('/website/gallery/'.$a['id'].'/publish')->assertRedirect();
        $this->post('/website/gallery/'.$a['id'].'/archive')->assertRedirect();
        $this->delete('/website/gallery/'.$a['id'])->assertRedirect('/website/gallery')->assertSessionHas('success');
        $this->assertTrue($this->sentTo('POST', '/admin/cms/gallery/albums/'.$a['id'].'/publish'));
    }

    public function test_duplicate_photo_is_explained(): void
    {
        $a = CmsApi::load('album');
        $this->cms(['POST /admin/cms/gallery/albums/*/items' => [409, ['title' => 'Conflict', 'status' => 409, 'code' => 'duplicate_item', 'detail' => 'That media is already in this album.']]]);
        $this->from('/website/gallery/'.$a['id'])->post('/website/gallery/'.$a['id'].'/add', ['mediaId' => 'm'])->assertSessionHas('error', fn ($m) => str_contains($m, 'already in this album'));
    }

    public function test_view_only_sees_no_upload_save_or_publish(): void
    {
        $this->cms(perms: CmsApi::VIEWER);
        $a = CmsApi::load('album');
        $html = $this->get('/website/gallery/'.$a['id'])->assertOk()->assertSee('You can look at this album but not change it')->getContent();
        foreach (['data-testid="uploader"', 'data-component="save-bar"', 'data-testid="publish"', 'data-testid="set-cover"', 'data-testid="remove-photo"'] as $x) {
            $this->assertStringNotContainsString($x, $html);
        }
        $this->put('/website/gallery/'.$a['id'], [])->assertForbidden();
        $this->post('/website/gallery/'.$a['id'].'/upload')->assertForbidden();
        $this->post('/website/gallery/'.$a['id'].'/publish')->assertForbidden();
    }
}

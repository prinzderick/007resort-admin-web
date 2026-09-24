<?php

namespace Tests\Feature;

use App\Services\R007Api\Mock\MockCmsData;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** R007_MOCK=true: the Website area runs on the fixture CMS with NO backend, and follows the same rules as the contract. */
class WebsiteMockModeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['r007.mock' => true, 'r007.mock_scenario' => 'normal']);
        Cache::flush();
        Http::preventStrayRequests();
    }

    private function login(string $who = 'owner'): void
    {
        $this->post('/login', ['identifier' => $who, 'password' => 'password'])->assertRedirect('/');
    }

    public function test_the_owner_can_open_every_website_screen(): void
    {
        $this->login();
        $seed = MockCmsData::seed();
        $paths = ['/website', '/website/settings', '/website/homepage', '/website/pages', '/website/pages/new', '/website/pages/'.$seed['pages'][0]['id'], '/website/blog', '/website/blog/new', '/website/blog/'.$seed['posts'][0]['id'],
            '/website/blog/categories', '/website/blog/categories/new', '/website/events', '/website/events/new', '/website/events/'.$seed['events'][0]['id'], '/website/gallery', '/website/gallery/'.$seed['albums'][0]['id'],
            '/website/media', '/website/media/picker', '/website/subscribers', '/website/messages'];
        foreach ($paths as $p) {
            $this->get($p)->assertOk();
        }
        $this->get('/website/subscribers/export')->assertOk();
    }

    public function test_content_lifecycle_on_the_mock(): void
    {
        $this->login();
        $res = $this->post('/website/pages', ['title' => 'House Rules Two', 'bodyMarkdown' => '## Rules', 'intent' => 'publish'])->assertRedirect();
        $id = basename($res->headers->get('Location'));
        $this->get('/website/pages/'.$id)->assertOk()->assertSee('house-rules-two', false)->assertSee('Live on the website');
        $this->delete('/website/pages/'.$id)->assertSessionHas('error', fn ($m) => str_contains($m, 'Archive'));
        $this->post("/website/pages/{$id}/archive")->assertRedirect();
        $this->delete('/website/pages/'.$id)->assertRedirect('/website/pages');
        $this->post('/website/pages', ['title' => 'About us', 'slug' => 'about', 'bodyMarkdown' => 'x'])->assertSessionHas('error');
    }

    public function test_media_upload_edit_and_guarded_delete_on_the_mock(): void
    {
        $this->login();
        $item = $this->postJson('/website/media/upload', ['file' => UploadedFile::fake()->image('a.png', 100, 80)])->assertCreated()->json('item');
        $this->patch('/website/media/'.$item['id'], ['alt' => 'A picture', 'tags' => ['x']])->assertRedirect();
        $this->getJson('/website/media/'.$item['id'].'/usage')->assertOk()->assertJsonPath('usageCount', 0);
        $used = MockCmsData::seed()['media'][0]['id'];
        $this->delete('/website/media/'.$used)->assertSessionHas('error_code', 'media_in_use');
        $this->delete('/website/media/'.$item['id'])->assertSessionHas('success');
    }

    public function test_a_marketing_editor_cannot_publish_or_read_subscribers(): void
    {
        $this->login('marketing');
        $this->get('/website/pages')->assertOk();
        $this->get('/website/subscribers')->assertForbidden();
        $seed = MockCmsData::seed();
        $this->post('/website/pages/'.$seed['pages'][0]['id'].'/unpublish')->assertForbidden();
        $this->get('/website/pages/'.$seed['pages'][0]['id'])->assertOk()->assertSee('cms.publish');
    }
}

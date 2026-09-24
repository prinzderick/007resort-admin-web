<?php

namespace Tests\Feature;

use Tests\Support\CmsApi;
use Tests\Support\CmsTestCase;

/** Homepage builder: blocks by type, reorder, enable/disable, add/edit/remove. */
class WebsiteHomepageTest extends CmsTestCase
{
    /** @return list<array<string, mixed>> */
    private function ofType(string $type): array
    {
        return array_values(array_filter(CmsApi::load('home-sections')['items'], fn ($i) => $i['type'] === $type));
    }

    public function test_the_builder_lists_every_block_type_with_real_content(): void
    {
        $this->cms();
        $hero = $this->ofType('HERO_SLIDE')[0];
        $html = $this->get('/website/homepage')->assertOk()->assertSee('Hero slides')->assertSee('Highlights')->assertSee('Stats')->assertSee('Testimonials')->assertSee('FAQs')->assertSee('Partners')->assertSee('Call-to-action bands')->assertSee($hero['payload']['headline'])->getContent();
        $this->assertStringContainsString('data-testid="block-HERO_SLIDE"', $html);
        $this->assertSame(28, substr_count($html, 'data-testid="block-row"'));
        $this->assertStringContainsString('aria-label="Move '.e($hero['payload']['headline']).' down"', $html, 'accessible move buttons');
        $this->assertStringContainsString('x-data="cmsSortable"', $html);
        $this->assertStringContainsString('data-testid="hero-preview"', $html, 'live hero preview in the editor');
    }

    public function test_switching_a_block_on_or_off_calls_enable_and_disable(): void
    {
        $b = $this->ofType('STAT')[0];
        $this->cms(['POST /admin/cms/home-sections/*' => $b]);
        $this->post('/website/homepage/sections/'.$b['id'].'/disable')->assertRedirect()->assertSessionHas('success', fn ($m) => str_contains($m, 'hidden'));
        $this->post('/website/homepage/sections/'.$b['id'].'/enable')->assertRedirect()->assertSessionHas('success', fn ($m) => str_contains($m, 'now on the website'));
        $this->assertTrue($this->sentTo('POST', '/admin/cms/home-sections/'.$b['id'].'/disable'));
        $this->assertTrue($this->sentTo('POST', '/admin/cms/home-sections/'.$b['id'].'/enable'));
    }

    public function test_reordering_a_type_reuses_that_types_sort_values_in_the_new_order(): void
    {
        $stats = $this->ofType('STAT');
        $this->cms(['GET /admin/cms/home-sections' => ['items' => $stats, 'nextCursor' => null, 'media' => []], 'POST /admin/cms/home-sections/reorder' => ['updated' => count($stats)]]);
        $ids = array_column($stats, 'id');
        $slots = array_column($stats, 'sortOrder');
        sort($slots);
        $new = array_reverse($ids);
        $this->post('/website/homepage/reorder', ['type' => 'STAT', 'ids' => $new])->assertRedirect(route('website.homepage').'#stat')->assertSessionHas('success', 'Order saved.');
        $items = $this->body('POST', 'admin/cms/home-sections/reorder')['items'];
        $this->assertSame($new, array_column($items, 'id'));
        $this->assertSame($slots, array_column($items, 'sortOrder'));
    }

    public function test_adding_a_block_posts_type_and_payload_and_says_it_starts_off(): void
    {
        $this->cms(['POST /admin/cms/home-sections' => [201, $this->ofType('STAT')[0]]]);
        $this->post('/website/homepage/sections', ['type' => 'STAT', 'payload' => ['value' => '99+', 'label' => 'Rooms', 'suffix' => '', 'icon' => '']])->assertRedirect()->assertSessionHas('success', fn ($m) => str_contains($m, 'switched off'));
        $b = $this->body('POST', 'admin/cms/home-sections');
        $this->assertSame('STAT', $b['type']);
        $this->assertSame('99+', $b['payload']['value']);
        $this->assertNull($b['payload']['suffix']);
    }

    public function test_a_hero_slide_edit_sends_the_full_payload_with_the_row_version(): void
    {
        $h = $this->ofType('HERO_SLIDE')[0];
        $this->cms(['PATCH /admin/cms/home-sections/*' => $h]);
        $this->patch('/website/homepage/sections/'.$h['id'], ['type' => 'HERO_SLIDE', 'rowVersion' => $h['rowVersion'], 'payload' => ['headline' => 'New headline', 'subheadline' => '', 'mediaId' => $h['payload']['mediaId'], 'ctaLabel' => 'Go', 'ctaLink' => '/go', 'alignment' => 'CENTER']])->assertRedirect()->assertSessionHas('success', 'Saved.');
        $req = $this->lastSent('PATCH', 'admin/cms/home-sections/'.$h['id']);
        $this->assertSame('CENTER', $req->data()['payload']['alignment']);
        $this->assertNull($req->data()['payload']['subheadline']);
        $this->assertSame('"'.$h['rowVersion'].'"', $req->header('If-Match')[0]);
    }

    public function test_a_refused_block_reopens_its_dialog_with_the_errors(): void
    {
        $h = $this->ofType('HERO_SLIDE')[0];
        $this->cms(['PATCH /admin/cms/home-sections/*' => [422, ['title' => 'Validation failed', 'status' => 422, 'code' => 'validation_failed', 'detail' => 'One or more fields are invalid.', 'errors' => ['payload.headline' => ['The headline is required.']]]]]);
        $html = $this->followingRedirects()->from('/website/homepage')->patch('/website/homepage/sections/'.$h['id'], ['type' => 'HERO_SLIDE', '_dialog' => 'edit-'.$h['id'], 'payload' => ['headline' => '']])->assertOk()->getContent();
        $this->assertStringContainsString('The headline is required.', $html);
        $this->assertStringContainsString("open-modal', 'edit-".$h['id'], $html, 'the dialog with the problem is reopened');
    }

    public function test_removing_a_block_and_the_permission_gates(): void
    {
        $b = $this->ofType('PARTNER')[0];
        $this->cms(['DELETE /admin/cms/home-sections/*' => [204, []]]);
        $this->delete('/website/homepage/sections/'.$b['id'])->assertRedirect()->assertSessionHas('success', 'Removed.');
        $this->cms(perms: CmsApi::EDITOR);
        $html = $this->get('/website/homepage')->assertOk()->getContent();
        $this->assertStringNotContainsString('data-testid="toggle"', $html, 'switching on/off needs cms.publish');
        $this->post('/website/homepage/sections/'.$b['id'].'/enable')->assertForbidden();
        $this->cms(perms: CmsApi::VIEWER);
        $html = $this->get('/website/homepage')->assertOk()->assertSee('You can look at the homepage but not change it')->getContent();
        $this->assertStringNotContainsString('data-testid="delete-block"', $html);
        $this->assertStringNotContainsString('data-move="up"', $html);
        $this->post('/website/homepage/reorder', ['type' => 'STAT', 'ids' => ['x']])->assertForbidden();
    }

    public function test_an_empty_type_offers_add_first(): void
    {
        $this->cms(['GET /admin/cms/home-sections' => ['items' => [], 'nextCursor' => null, 'media' => []]]);
        $this->get('/website/homepage')->assertOk()->assertSee('Add first hero slide')->assertSee('Add first FAQ')->assertSee('No hero slides yet');
    }
}

<?php

namespace Tests\Feature;

use App\Support\Cms\Cms;
use Illuminate\Support\Facades\Http;
use Tests\Support\CmsApi;
use Tests\Support\CmsTestCase;

/** Pages, blog posts, blog categories and events: one list, one editor, real recorded API shapes. */
class WebsiteContentTest extends CmsTestCase
{
    // ---------------------------------------------------------------- lists

    public function test_page_list_renders_real_rows_with_chips_search_and_row_actions(): void
    {
        $this->cms();
        $p = CmsApi::first('pages-list');
        $html = $this->get('/website/pages?q=house&status=PUBLISHED')->assertOk()->assertSee($p['title'])->assertSee('Add page')->assertSee('Drafts')->assertSee('Archived')->assertSee('Unpublish')->getContent();
        $this->assertStringContainsString('data-testid="status-chips"', $html);
        $this->assertStringContainsString('data-testid="content-table"', $html);
        $this->assertTrue($this->sentTo('GET', '/admin/cms/pages', fn ($r) => str_contains($r->url(), 'q=house') && str_contains($r->url(), 'status=PUBLISHED')));
    }

    public function test_posts_list_shows_category_tags_dates_and_filters(): void
    {
        $this->cms();
        $post = CmsApi::first('posts-list');
        $this->get('/website/blog?categoryId='.$post['category']['id'].'&featured=true')->assertOk()->assertSee($post['title'])->assertSee($post['category']['name'])->assertSee('Publish date')->assertSee('Featured');
        $this->assertTrue($this->sentTo('GET', '/admin/cms/posts', fn ($r) => str_contains($r->url(), 'categoryId=') && str_contains($r->url(), 'featured=true')));
    }

    public function test_events_list_shows_lagos_times_and_recurrence(): void
    {
        $this->cms();
        $weekly = collect(CmsApi::load('events-list')['items'])->firstWhere('recurrence', 'WEEKLY');
        $lagos = Cms::when($weekly['startsAt']);
        $this->get('/website/events')->assertOk()->assertSee($weekly['title'])->assertSee($lagos)->assertSee('Weekly')->assertSee('Starts (Lagos)');
    }

    public function test_a_scheduled_post_is_labelled_scheduled(): void
    {
        $post = CmsApi::first('posts-list', 'PUBLISHED');
        $post['publishedAt'] = now()->addDays(3)->utc()->format('Y-m-d\TH:i:s.v\Z');
        $this->cms(['GET /admin/cms/posts' => ['items' => [$post], 'nextCursor' => null]]);
        $this->get('/website/blog')->assertOk()->assertSee('Scheduled');
    }

    public function test_empty_lists_offer_an_add_first_button(): void
    {
        $this->cms(['GET /admin/cms/pages' => ['items' => [], 'nextCursor' => null], 'GET /admin/cms/posts' => ['items' => [], 'nextCursor' => null], 'GET /admin/cms/events' => ['items' => [], 'nextCursor' => null], 'GET /admin/cms/post-categories' => ['items' => [], 'nextCursor' => null]]);
        $this->get('/website/pages')->assertOk()->assertSee('No pages yet')->assertSee('Add first page');
        $this->get('/website/blog')->assertOk()->assertSee('Add first post');
        $this->get('/website/events')->assertOk()->assertSee('Add first event');
        $this->get('/website/blog/categories')->assertOk()->assertSee('Add first category');
    }

    public function test_a_filter_with_no_matches_is_not_the_first_run_empty_state(): void
    {
        $this->cms(['GET /admin/cms/pages' => ['items' => [], 'nextCursor' => null]]);
        $this->get('/website/pages?q=zzz')->assertOk()->assertSee('Nothing matches')->assertDontSee('Add first page');
    }

    public function test_the_next_page_link_carries_the_api_cursor(): void
    {
        $this->cms(['GET /admin/cms/pages' => ['items' => CmsApi::load('pages-list')['items'], 'nextCursor' => 'CURSOR123']]);
        $this->get('/website/pages')->assertOk()->assertSee('Next page')->assertSee('cursor=CURSOR123', false);
    }

    public function test_a_view_only_person_sees_no_add_publish_or_delete(): void
    {
        $this->cms(perms: CmsApi::VIEWER);
        $html = $this->get('/website/pages')->assertOk()->getContent();
        $this->assertStringNotContainsString('data-testid="add-new"', $html);
        $this->assertStringNotContainsString('data-testid="delete"', $html);
        $this->assertStringNotContainsString('data-testid="unpublish"', $html);
        $this->get('/website/pages/new')->assertForbidden();
        $this->post('/website/pages', ['title' => 'x'])->assertForbidden();
        $this->post('/website/pages/'.CmsApi::first('pages-list')['id'].'/publish')->assertForbidden();
    }

    public function test_an_editor_without_publish_can_save_but_is_told_publishing_needs_permission(): void
    {
        $this->cms(perms: CmsApi::EDITOR);
        $p = CmsApi::first('pages-list');
        $html = $this->get('/website/pages/'.$p['id'])->assertOk()->assertSee('cms.publish')->getContent();
        $this->assertStringNotContainsString('data-testid="unpublish"', $html);
        $this->post('/website/pages/'.$p['id'].'/unpublish')->assertForbidden();
    }

    public function test_an_api_failure_shows_an_error_panel_not_a_crash(): void
    {
        $this->cms(['GET /admin/cms/posts' => [500, ['title' => 'Internal', 'status' => 500, 'code' => 'internal_error']]]);
        $this->get('/website/blog')->assertOk()->assertSee('could not be loaded');
    }

    public function test_a_forbidden_api_read_is_explained(): void
    {
        [$status, $problem] = CmsApi::failure('forbidden');
        $this->cms(['GET /admin/cms/pages' => [$status, $problem]]);
        $this->get('/website/pages')->assertOk()->assertSee('lacks permission');
    }

    // ---------------------------------------------------------------- editor

    public function test_the_page_editor_shows_every_field_the_real_api_returns(): void
    {
        $this->cms();
        $p = CmsApi::load('page-published');
        $html = $this->get('/website/pages/'.$p['id'])->assertOk()->assertSee($p['title'])->assertSee('Page text')->assertSee('Search engines and sharing')->assertSee('Unpublish')->assertSee('Live on the website since')->getContent();
        $this->assertStringContainsString('value="'.$p['slug'].'"', $html);
        $this->assertStringContainsString('name="rowVersion" value="'.$p['rowVersion'].'"', $html);
        $this->assertStringContainsString('data-testid="markdown-bodyMarkdown"', $html);
        $this->assertStringContainsString('data-testid="seo-snippet"', $html);
        $this->assertStringContainsString('x-data="cmsForm"', $html, 'unsaved-changes guard');
    }

    public function test_the_post_editor_offers_scheduling_categories_tags_and_a_cover(): void
    {
        $post = CmsApi::first('posts-list');
        $draft = ['status' => 'DRAFT', 'publishedAt' => null] + $post;
        $this->cms(['GET /admin/cms/posts/*' => $draft]);
        $html = $this->get('/website/blog/'.$post['id'])->assertOk()->assertSee('Publish date and time')->assertSee('Save and publish')->assertSee('Save as draft')->assertSee($post['category']['name'])->getContent();
        $this->assertStringContainsString('data-testid="image-field-coverMediaId"', $html);
        $this->assertStringContainsString($post['tags'][0], $html);
    }

    public function test_the_event_editor_shows_lagos_time_a_recurrence_preview_and_ticket_picker(): void
    {
        $this->cms();
        $e = CmsApi::load('event-published');
        $html = $this->get('/website/events/'.$e['id'])->assertOk()->assertSee('When')->assertSee('Every week')->assertSee('Tickets and booking')->getContent();
        $this->assertStringContainsString('name="startsAt" :value="v" value="'.Cms::toLocalInput($e['startsAt']).'"', $html);
        $this->assertStringContainsString('data-testid="occurrences"', $html);
        $this->assertStringContainsString('data-testid="ticket-picker"', $html);
    }

    // ---------------------------------------------------------------- writes

    public function test_creating_a_page_sends_only_filled_fields_and_lands_on_its_editor(): void
    {
        $id = '0192f6a0-7b1c-7d2e-9a3b-0000000a0001';
        $this->cms(['POST /admin/cms/pages' => [201, ['id' => $id] + CmsApi::load('page-published')]]);
        $this->post('/website/pages', ['title' => ' Contact info ', 'slug' => '', 'subtitle' => '', 'bodyMarkdown' => "## Hi\n\nText", 'seoDescription' => 'desc', 'showInFooter' => '1', 'sortOrder' => '', 'heroMediaId' => '', 'intent' => 'save'])
            ->assertRedirect('/website/pages/'.$id)->assertSessionHas('success', 'Page created.');
        $body = $this->body('POST', 'admin/cms/pages');
        $this->assertSame('Contact info', $body['title']);
        $this->assertArrayNotHasKey('slug', $body, 'a blank slug is generated by the API');
        $this->assertArrayNotHasKey('subtitle', $body);
        $this->assertArrayNotHasKey('sortOrder', $body, 'a blank order is not sent (the API answers 500 to sortOrder: null)');
        $this->assertTrue($body['showInFooter']);
        $this->assertNotNull($this->lastSent('POST', 'admin/cms/pages')->header('Idempotency-Key')[0] ?? null);
    }

    public function test_save_and_publish_creates_then_publishes(): void
    {
        $id = '0192f6a0-7b1c-7d2e-9a3b-0000000a0002';
        $this->cms(['POST /admin/cms/posts/*/publish' => CmsApi::first('posts-list'), 'POST /admin/cms/posts' => [201, ['id' => $id]]]);
        $this->post('/website/blog', ['title' => 'Hello', 'bodyMarkdown' => 'x', 'tags' => ['Pool', ' family '], 'featured' => '0', 'intent' => 'publish'])->assertRedirect('/website/blog/'.$id)->assertSessionHas('success', 'Post created and published.');
        $this->assertTrue($this->sentTo('POST', "/admin/cms/posts/{$id}/publish"));
        $body = $this->body('POST', 'admin/cms/posts');
        $this->assertSame(['Pool', 'family'], $body['tags']);
        $this->assertFalse($body['featured']);
    }

    public function test_a_scheduled_publish_converts_lagos_time_to_utc(): void
    {
        $post = CmsApi::first('posts-list');
        $this->cms(['PATCH /admin/cms/posts/*' => $post, 'POST /admin/cms/posts/*/publish' => ['publishedAt' => '2027-01-15T08:30:00.000Z'] + $post]);
        $this->put('/website/blog/'.$post['id'], ['title' => 'T', 'bodyMarkdown' => 'x', 'rowVersion' => '4', 'intent' => 'publish', 'publishAt' => '2027-01-15T09:30'])->assertRedirect()->assertSessionHas('success', 'Post saved and scheduled.');
        $this->assertSame('2027-01-15T08:30:00.000Z', $this->body('POST', 'admin/cms/posts/'.$post['id'].'/publish')['publishedAt'], '09:30 Lagos is 08:30 UTC');
        $this->assertSame('"4"', $this->lastSent('PATCH', 'admin/cms/posts/'.$post['id'])->header('If-Match')[0]);
    }

    public function test_an_event_is_sent_in_utc_with_recurrence_and_only_the_chosen_ticket_link(): void
    {
        $e = collect(CmsApi::load('events-list')['items'])->firstWhere('recurrence', 'WEEKLY');
        $this->cms(['PATCH /admin/cms/events/*' => $e]);
        $this->put('/website/events/'.$e['id'], ['title' => 'Live', 'category' => 'MUSIC', 'startsAt' => '2026-10-09T19:00', 'endsAt' => '2026-10-09T23:00', 'recurrence' => 'WEEKLY', 'recurrenceUntil' => '2026-12-25', 'capacity' => '150', 'ticketMode' => 'url', 'ticketUrl' => 'https://x.test/t', 'ticketProductId' => 'p1', 'facilityId' => '', 'rowVersion' => '2', 'intent' => 'save'])->assertRedirect();
        $b = $this->body('PATCH', 'admin/cms/events/'.$e['id']);
        $this->assertSame('2026-10-09T18:00:00.000Z', $b['startsAt']);
        $this->assertSame('2026-10-09T22:00:00.000Z', $b['endsAt']);
        $this->assertSame('WEEKLY', $b['recurrence']);
        $this->assertSame('2026-12-25', $b['recurrenceUntil']);
        $this->assertSame(150, $b['capacity']);
        $this->assertSame('https://x.test/t', $b['ticketUrl']);
        $this->assertNull($b['ticketProductId'], 'only the chosen ticket link is kept');
        $this->put('/website/events/'.$e['id'], ['title' => 'Live', 'category' => 'MUSIC', 'startsAt' => '2026-10-09T19:00', 'endsAt' => '2026-10-09T23:00', 'recurrence' => 'NONE', 'recurrenceUntil' => '2026-12-25', 'ticketMode' => 'none', 'rowVersion' => '2', 'intent' => 'save']);
        $b = $this->body('PATCH', 'admin/cms/events/'.$e['id']);
        $this->assertSame('NONE', $b['recurrence']);
        $this->assertNull($b['recurrenceUntil'], 'a one-off event has no repeat end date');
        $this->assertNull($b['ticketUrl']);
    }

    public function test_validation_errors_from_the_api_land_on_the_fields(): void
    {
        [$status, $problem] = CmsApi::failure('pages-create-invalid');
        $this->cms(['POST /admin/cms/pages' => [$status, $problem]]);
        $this->from('/website/pages/new')->post('/website/pages', ['title' => '', 'bodyMarkdown' => '', 'intent' => 'save'])->assertRedirect('/website/pages/new')->assertSessionHasErrors(['title', 'bodyMarkdown'])->assertSessionHas('error');
        $html = $this->followingRedirects()->from('/website/pages/new')->post('/website/pages', ['title' => '', 'bodyMarkdown' => '', 'intent' => 'save'])->assertOk()->getContent();
        $this->assertStringContainsString('The title field is required.', $html);
        $this->assertStringContainsString('data-invalid="true"', $html);
    }

    public function test_an_event_that_ends_before_it_starts_is_reported_on_the_end_field(): void
    {
        [$status, $problem] = CmsApi::failure('events-create-invalid');
        $this->cms(['POST /admin/cms/events' => [$status, $problem]]);
        $this->from('/website/events/new')->post('/website/events', ['title' => 'x', 'category' => 'MUSIC', 'startsAt' => '2026-10-10T19:00', 'endsAt' => '2026-10-10T18:00'])->assertSessionHasErrors('endsAt');
    }

    public function test_a_stale_save_explains_and_offers_a_reload(): void
    {
        [$status, $problem] = CmsApi::failure('pages-stale-update');
        $p = CmsApi::first('pages-list');
        $this->cms(['PATCH /admin/cms/pages/*' => [$status, $problem]]);
        $this->from('/website/pages/'.$p['id'])->put('/website/pages/'.$p['id'], ['title' => 'T', 'bodyMarkdown' => 'x', 'rowVersion' => '0', 'intent' => 'save'])->assertSessionHas('error_code', 'concurrency_conflict')->assertSessionHas('error', fn ($m) => str_contains($m, 'changed by someone else'));
    }

    public function test_publish_unpublish_and_archive_call_their_endpoints(): void
    {
        $p = CmsApi::first('pages-list');
        $this->cms(['POST /admin/cms/pages/*' => $p]);
        foreach (['publish' => 'published', 'unpublish' => 'unpublished', 'archive' => 'archived'] as $action => $word) {
            $this->post("/website/pages/{$p['id']}/{$action}")->assertRedirect()->assertSessionHas('success', fn ($m) => str_contains($m, $word));
            $this->assertTrue($this->sentTo('POST', "/admin/cms/pages/{$p['id']}/{$action}"));
        }
    }

    public function test_deleting_published_content_is_refused_with_the_apis_reason(): void
    {
        [$status, $problem] = CmsApi::failure('pages-published-delete');
        $p = CmsApi::first('pages-list');
        $this->cms(['DELETE /admin/cms/pages/*' => [$status, $problem]]);
        $this->from('/website/pages')->delete('/website/pages/'.$p['id'])->assertRedirect('/website/pages')->assertSessionHas('error', fn ($m) => str_contains($m, 'unpublished or archived'));
    }

    public function test_delete_of_a_draft_goes_back_to_the_list(): void
    {
        $p = CmsApi::first('pages-list');
        $this->cms(['DELETE /admin/cms/pages/*' => [204, []]]);
        $this->delete('/website/pages/'.$p['id'])->assertRedirect('/website/pages')->assertSessionHas('success', 'Page deleted.');
    }

    public function test_categories_use_the_same_screens_and_refuse_delete_while_in_use(): void
    {
        $c = CmsApi::first('post-categories');
        $this->cms();
        $this->get('/website/blog/categories')->assertOk()->assertSee($c['name'])->assertSee('Posts');
        $this->get('/website/blog/categories/'.$c['id'])->assertOk()->assertSee('Description');
        $this->fakeApi(CmsApi::routes(['DELETE /admin/cms/post-categories/*' => [409, ['code' => 'category_in_use', 'title' => 'Conflict', 'status' => 409, 'detail' => 'Posts still use this category; move or delete them first.']]]));
        $this->from('/website/blog/categories')->delete('/website/blog/categories/'.$c['id'])->assertSessionHas('error', fn ($m) => str_contains($m, 'Posts still use this category'));
    }

    public function test_route_parameters_do_not_get_mixed_up_between_resources(): void
    {
        $this->cms();
        $this->get('/website/blog/'.CmsApi::first('posts-list')['id'])->assertOk()->assertSee('Post text');
        $this->get('/website/events/'.CmsApi::first('events-list')['id'])->assertOk()->assertSee('Venue and price');
        $this->assertTrue($this->sentTo('GET', '/admin/cms/events/'));
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/admin/cms/posts/') && str_contains($r->url(), CmsApi::first('events-list')['id']));
    }
}

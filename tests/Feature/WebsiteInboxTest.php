<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Tests\Support\CmsApi;
use Tests\Support\CmsTestCase;

/** Subscribers (counts, growth, filters, CSV export, unsubscribe, erase) and the contact-form inbox. */
class WebsiteInboxTest extends CmsTestCase
{
    // ---------------------------------------------------------------- subscribers

    public function test_subscribers_table_shows_counts_growth_and_actions(): void
    {
        $this->cms();
        $d = CmsApi::load('subscribers');
        $html = $this->get('/website/subscribers?status=CONFIRMED&source=footer&q=ada')->assertOk()->assertSee('Subscribers')->assertSee($d['items'][0]['email'])->assertSee('Confirmed')->assertSee('Waiting to confirm')->assertSee('New in 30 days')->assertSee('Export CSV')->getContent();
        $this->assertStringContainsString('data-testid="sub-counts"', $html);
        $this->assertStringContainsString('<polyline', $html, 'growth sparkline');
        $this->assertStringContainsString('data-testid="unsubscribe"', $html);
        $this->assertStringContainsString('data-testid="erase"', $html);
        $this->assertTrue($this->sentTo('GET', '/admin/cms/subscribers', fn ($r) => str_contains($r->url(), 'status=CONFIRMED') && str_contains($r->url(), 'source=footer') && str_contains($r->url(), 'q=ada')));
        $this->assertStringContainsString('>'.(array_sum($d['counts'])).'<', preg_replace('/\s+/', '', str_replace('>', '>', $html)) ?: $html);
    }

    public function test_growth_counts_signups_in_the_last_thirty_days(): void
    {
        $recent = ['id' => 'a', 'email' => 'a@x.test', 'name' => null, 'source' => 'footer', 'status' => 'CONFIRMED', 'createdAt' => now()->subDays(2)->utc()->format('Y-m-d\TH:i:s.v\Z')];
        $old = ['id' => 'b', 'email' => 'b@x.test', 'name' => null, 'source' => 'footer', 'status' => 'CONFIRMED', 'createdAt' => now()->subDays(60)->utc()->format('Y-m-d\TH:i:s.v\Z')];
        $this->cms(['GET /admin/cms/subscribers' => ['items' => [$recent, $recent, $old], 'nextCursor' => null, 'counts' => ['pending' => 0, 'confirmed' => 3, 'unsubscribed' => 0]]]);
        $this->get('/website/subscribers')->assertOk()->assertSee('+2')->assertSee('Daily running total');
    }

    public function test_empty_subscribers_explain_where_sign_ups_come_from(): void
    {
        $this->cms(['GET /admin/cms/subscribers' => ['items' => [], 'nextCursor' => null, 'counts' => ['pending' => 0, 'confirmed' => 0, 'unsubscribed' => 0]]]);
        $this->get('/website/subscribers')->assertOk()->assertSee('No subscribers yet');
        $this->get('/website/subscribers?q=zzz')->assertOk()->assertSee('Nobody matches');
    }

    public function test_export_streams_the_apis_csv_and_is_permission_gated(): void
    {
        $csv = CmsApi::csv('subscribers-export');
        $this->cms(['GET /admin/cms/subscribers/export' => [200, [], []]]);
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response($csv, 200, ['Content-Type' => 'text/csv; charset=utf-8'])]);
        $res = $this->get('/website/subscribers/export?status=CONFIRMED')->assertOk();
        $this->assertStringStartsWith('text/csv', $res->headers->get('Content-Type'));
        $this->assertStringContainsString('subscribers-confirmed-', $res->headers->get('Content-Disposition'));
        $this->assertSame($csv, $res->streamedContent());
        $this->assertTrue($this->sentTo('GET', '/admin/cms/subscribers/export', fn ($r) => str_contains($r->url(), 'status=CONFIRMED')));

        $this->cms(perms: ['cms.view', 'cms.subscribers.view']);
        $html = $this->get('/website/subscribers')->assertOk()->assertSee('Export is not available')->getContent();
        $this->assertStringNotContainsString('data-testid="export-csv"', $html);
        $this->get('/website/subscribers/export')->assertForbidden();
    }

    public function test_unsubscribe_and_erase_call_the_api_and_are_gated(): void
    {
        $s = CmsApi::first('subscribers');
        $this->cms(['POST /admin/cms/subscribers/*/unsubscribe' => $s, 'DELETE /admin/cms/subscribers/*' => [204, []]]);
        $this->post('/website/subscribers/'.$s['id'].'/unsubscribe')->assertRedirect()->assertSessionHas('success', fn ($m) => str_starts_with($m, 'Unsubscribed'));
        $this->delete('/website/subscribers/'.$s['id'])->assertRedirect()->assertSessionHas('success', fn ($m) => str_starts_with($m, 'Erased for good'));
        $this->assertTrue($this->sentTo('DELETE', '/admin/cms/subscribers/'.$s['id']));
        $this->cms(perms: ['cms.view', 'cms.subscribers.view', 'cms.subscribers.export']);
        $html = $this->get('/website/subscribers')->assertOk()->getContent();
        $this->assertStringNotContainsString('data-testid="erase"', $html);
        $this->delete('/website/subscribers/'.$s['id'])->assertForbidden();
        $this->post('/website/subscribers/'.$s['id'].'/unsubscribe')->assertForbidden();
    }

    public function test_subscribers_api_failure_is_shown_inline(): void
    {
        $this->cms(['GET /admin/cms/subscribers' => [500, ['title' => 'Internal', 'status' => 500]]]);
        $this->get('/website/subscribers')->assertOk()->assertSee('could not be loaded');
    }

    // ---------------------------------------------------------------- messages

    public function test_inbox_shows_status_chips_with_counts_and_reply_links(): void
    {
        $this->cms();
        $d = CmsApi::load('messages');
        $new = collect($d['items'])->firstWhere('status', 'NEW');
        $html = $this->get('/website/messages?status=NEW&topic=BOOKING&q=hall')->assertOk()->assertSee($new['name'])->assertSee('Replied')->assertSee('Spam')->assertSee('Reply by email')->getContent();
        $this->assertStringContainsString('data-testid="status-chips"', $html);
        $this->assertStringContainsString('mailto:'.rawurlencode($new['email']), $html);
        $this->assertStringContainsString('data-testid="message-drawer"', $html);
        $this->assertTrue($this->sentTo('GET', '/admin/cms/messages', fn ($r) => str_contains($r->url(), 'status=NEW') && str_contains($r->url(), 'topic=BOOKING') && str_contains($r->url(), 'q=hall')));
        foreach (['new', 'read', 'replied', 'spam'] as $k) {
            $this->assertArrayHasKey($k, $d['counts']);
        }
    }

    public function test_empty_inbox_and_no_match_states(): void
    {
        $this->cms(['GET /admin/cms/messages' => ['items' => [], 'nextCursor' => null, 'counts' => ['new' => 0, 'read' => 0, 'replied' => 0, 'spam' => 0]]]);
        $this->get('/website/messages')->assertOk()->assertSee('No messages yet');
        $this->get('/website/messages?status=SPAM')->assertOk()->assertSee('No messages match');
    }

    public function test_mark_status_and_note_send_a_patch(): void
    {
        $m = CmsApi::load('messages')['items'][0];
        $this->cms(['PATCH /admin/cms/messages/*' => $m]);
        $this->patch('/website/messages/'.$m['id'], ['status' => 'REPLIED'])->assertRedirect()->assertSessionHas('success', 'Marked as replied.');
        $this->assertSame(['status' => 'REPLIED'], $this->body('PATCH', 'admin/cms/messages/'.$m['id']));
        $this->patch('/website/messages/'.$m['id'], ['internalNote' => ' Called back '])->assertRedirect()->assertSessionHas('success', 'Note saved.');
        $this->assertSame(['internalNote' => 'Called back'], $this->body('PATCH', 'admin/cms/messages/'.$m['id']));
        $this->patch('/website/messages/'.$m['id'], ['status' => 'BOGUS'])->assertSessionHasErrors('status');
    }

    public function test_bulk_actions_apply_to_each_selected_message_and_report_failures(): void
    {
        $ids = array_column(CmsApi::load('messages')['items'], 'id');
        $this->cms(['PATCH /admin/cms/messages/*' => CmsApi::load('messages')['items'][0], 'DELETE /admin/cms/messages/*' => [204, []]]);
        $this->post('/website/messages/bulk', ['ids' => $ids, 'action' => 'READ'])->assertRedirect()->assertSessionHas('success', count($ids).' messages marked as read.');
        $this->assertTrue($this->sentTo('PATCH', '/admin/cms/messages/'.$ids[1]));
        $this->post('/website/messages/bulk', ['ids' => [$ids[0]], 'action' => 'DELETE'])->assertRedirect()->assertSessionHas('success', '1 message erased.');
        $this->post('/website/messages/bulk', ['ids' => [], 'action' => 'READ'])->assertSessionHasErrors('ids');
        $this->fakeApi(CmsApi::routes(['PATCH /admin/cms/messages/*' => [404, ['title' => 'Not found', 'status' => 404, 'code' => 'not_found']]]));
        $this->post('/website/messages/bulk', ['ids' => [$ids[0]], 'action' => 'SPAM'])->assertRedirect()->assertSessionHas('error', fn ($m) => str_contains($m, '1 could not be changed'));
    }

    public function test_messages_need_the_inbox_permission(): void
    {
        $this->cms(perms: ['cms.view', 'cms.manage']);
        $this->get('/website/messages')->assertForbidden();
        $id = '0192f6a0-7b1c-7d2e-9a3b-000000000001';
        $this->patch('/website/messages/'.$id, ['status' => 'READ'])->assertForbidden();
        $this->post('/website/messages/bulk', ['ids' => ['x'], 'action' => 'READ'])->assertForbidden();
        $this->delete('/website/messages/'.$id)->assertForbidden();
    }

    public function test_erase_one_message(): void
    {
        $m = CmsApi::load('messages')['items'][0];
        $this->cms(['DELETE /admin/cms/messages/*' => [204, []]]);
        $this->delete('/website/messages/'.$m['id'])->assertRedirect()->assertSessionHas('success', 'Message erased.');
    }
}

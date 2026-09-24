<?php

namespace Tests\Feature;

use App\Auth\StaffSession;
use App\Support\Navigation;
use Tests\Support\CmsApi;
use Tests\Support\CmsTestCase;
use Tests\Support\RealApi;

/** Who sees the Website area, the overview and the dashboard card. */
class WebsiteAccessTest extends CmsTestCase
{
    public function test_sidebar_has_a_website_group_for_editors_and_none_for_cashiers(): void
    {
        $this->cms();
        $this->get('/website')->assertOk()->assertSee('Website home')->assertSee('Site settings')->assertSee('Media library')->assertSee('Subscribers')->assertSee('Messages');

        $this->fakeApi(CmsApi::routes());
        $this->signIn(['payment.take', 'order.create'], ['CASHIER']);
        $this->get('/website')->assertForbidden();
        $this->get('/website/pages')->assertForbidden();
        $this->get('/website/subscribers')->assertForbidden();
        $groups = collect(Navigation::for(app(StaffSession::class)))->pluck('title')->all();
        $this->assertNotContains('Website', $groups);
    }

    public function test_a_viewer_sees_only_the_entries_their_permissions_allow(): void
    {
        $this->cms(perms: CmsApi::VIEWER);
        $html = $this->get('/website')->assertOk()->getContent();
        $this->assertStringContainsString('data-nav="website.settings"', $html);
        $this->assertStringNotContainsString('data-nav="website.subscribers"', $html, 'subscribers need cms.subscribers.view');
        $this->assertStringNotContainsString('data-nav="website.messages"', $html);
        $this->get('/website/subscribers')->assertForbidden();
        $this->get('/website/messages')->assertForbidden();
    }

    public function test_overview_shows_real_counts_and_quick_actions(): void
    {
        $this->cms();
        $s = CmsApi::load('summary');
        $this->get('/website')->assertOk()->assertSee('Published posts')->assertSee((string) $s['posts']['published'])->assertSee('Write a blog post')->assertSee('Read new messages')->assertSee('Content at a glance');
    }

    public function test_overview_hides_quick_actions_the_person_cannot_use(): void
    {
        $this->cms(perms: CmsApi::VIEWER);
        $this->get('/website')->assertOk()->assertDontSee('Write a blog post')->assertDontSee('Read new messages')->assertSee('Edit the homepage');
    }

    public function test_overview_degrades_when_the_api_is_down(): void
    {
        $this->cms(['GET /admin/cms/summary' => [503, ['title' => 'Unavailable', 'status' => 503]]]);
        $this->get('/website')->assertOk()->assertSee('could not be loaded');
    }

    public function test_dashboard_has_a_website_card_with_counts_and_links(): void
    {
        $this->fakeApi(CmsApi::routes(RealApi::routes(true)));
        $this->signIn(array_merge(RealApi::ownerPermissions(), CmsApi::ALL), ['OWNER']);
        $s = CmsApi::load('summary');
        $html = $this->get('/')->assertOk()->assertSee('Website')->getContent();
        $this->assertMatchesRegularExpression('/data-testid="w-posts">\s*'.$s['posts']['published'].'\s*</', $html);
        $this->assertStringContainsString('data-testid="w-messages"', $html);
        $this->assertStringContainsString(route('website.posts.create'), $html);
    }

    public function test_dashboard_has_no_website_card_without_cms_view(): void
    {
        $this->fakeApi(RealApi::routes(true));
        $this->signIn(RealApi::ownerPermissions(), ['OWNER']);
        $this->get('/')->assertOk()->assertDontSee('data-testid="website-card"', false);
    }
}

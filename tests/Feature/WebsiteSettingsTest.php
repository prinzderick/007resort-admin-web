<?php

namespace Tests\Feature;

use Tests\Support\CmsApi;
use Tests\Support\CmsTestCase;

/** Site settings: eight tabs, one Save, only changed groups are sent (each with its own If-Match). */
class WebsiteSettingsTest extends CmsTestCase
{
    /** The form as the browser would post it, from the recorded settings. @return array<string, mixed> */
    private function form(array $over = []): array
    {
        $g = CmsApi::load('settings')['groups'];
        $f = ['versions' => array_map(fn ($x) => $x['rowVersion'], $g)];
        foreach (['brand' => ['name', 'tagline', 'logoMediaId'], 'contact' => ['phone', 'whatsapp', 'email', 'address', 'mapEmbedUrl', 'lat', 'lng'], 'social' => ['instagram', 'facebook', 'x', 'tiktok', 'youtube'], 'seo' => ['titleTemplate', 'defaultTitle', 'defaultDescription', 'ogImageMediaId'],
            'announcement' => ['enabled', 'text', 'link', 'tone'], 'booking' => ['ticketsCtaLabel', 'bookingCtaLabel', 'membershipCtaLabel', 'eventsCtaLabel'], 'footer' => ['text', 'copyright']] as $group => $keys) {
            foreach ($keys as $k) {
                $v = $g[$group]['value'][$k] ?? '';
                $f[$group][$k] = is_bool($v) ? ($v ? '1' : '0') : (string) ($v ?? '');
            }
        }
        foreach ($g['hours']['value']['weekly'] as $d) {
            $f['hours']['weekly'][$d['day']] = ['open' => $d['open'] ?? '', 'close' => $d['close'] ?? '', 'closed' => $d['closed'] ? '1' : '0'];
        }
        $f['hours']['notes'] = (string) ($g['hours']['value']['notes'] ?? '');
        foreach ($g['hours']['value']['holidays'] ?? [] as $i => $h) {
            $f['hours']['holidays'][$i] = ['date' => $h['date'], 'label' => $h['label'], 'open' => $h['open'] ?? '', 'close' => $h['close'] ?? '', 'closed' => ($h['closed'] ?? false) ? '1' : '0'];
        }

        return array_replace_recursive($f, $over);
    }

    public function test_settings_show_all_eight_tabs_with_the_saved_values(): void
    {
        $this->cms();
        $g = CmsApi::load('settings')['groups'];
        $html = $this->get('/website/settings')->assertOk()->assertSee('Brand and logo')->assertSee('Contact and map')->assertSee('Opening hours')->assertSee('Social links')->assertSee('Search and sharing')->assertSee('Announcement bar')->assertSee('Booking labels')->assertSee('Footer')->getContent();
        $this->assertStringContainsString('value="'.e($g['brand']['value']['name']).'"', $html);
        $this->assertStringContainsString(e($g['announcement']['value']['text']), $html);
        $this->assertStringContainsString('data-testid="announcement-preview"', $html, 'live preview');
        $this->assertStringContainsString('data-testid="hours-editor"', $html);
        $this->assertStringContainsString('name="hours[weekly][MON][open]"', $html);
        $this->assertStringContainsString('name="versions[brand]" value="'.$g['brand']['rowVersion'].'"', $html);
        $this->assertStringContainsString('x-data="cmsForm"', $html);
        foreach ($g['hours']['value']['holidays'] as $h) {
            $this->assertStringContainsString($h['date'], $html);
        }
    }

    public function test_saving_with_nothing_changed_sends_nothing(): void
    {
        $this->cms();
        $this->put('/website/settings', $this->form())->assertRedirect('/website/settings')->assertSessionHas('success', 'Nothing had changed, so nothing was saved.');
        $this->assertFalse($this->sentTo('PUT', '/admin/cms/settings'));
    }

    public function test_only_the_changed_group_is_saved_with_its_row_version(): void
    {
        $g = CmsApi::load('settings')['groups'];
        $this->cms(['PUT /admin/cms/settings/announcement' => ['group' => 'announcement'] + $g['announcement']]);
        $this->put('/website/settings', $this->form(['announcement' => ['text' => 'Pool closed Monday', 'tone' => 'WARNING', 'enabled' => '1']]))->assertRedirect('/website/settings')->assertSessionHas('success', fn ($m) => str_contains($m, 'Announcement bar'));
        $req = $this->lastSent('PUT', 'admin/cms/settings/announcement');
        $this->assertSame('"'.$g['announcement']['rowVersion'].'"', $req->header('If-Match')[0]);
        $this->assertSame(['enabled' => true, 'text' => 'Pool closed Monday', 'link' => $g['announcement']['value']['link'], 'tone' => 'WARNING'], $req->data()['value']);
        $this->assertFalse($this->sentTo('PUT', '/admin/cms/settings/brand'));
    }

    public function test_opening_hours_are_sent_as_seven_ordered_days_plus_holidays(): void
    {
        $g = CmsApi::load('settings')['groups'];
        $this->cms(['PUT /admin/cms/settings/hours' => ['group' => 'hours'] + $g['hours']]);
        $this->put('/website/settings', $this->form(['hours' => ['weekly' => ['SUN' => ['closed' => '1'], 'MON' => ['open' => '9:30', 'close' => '21:00']], 'notes' => '', 'holidays' => [9 => ['date' => '2026-12-31', 'label' => 'New Year\'s Eve', 'open' => '10:00', 'close' => '16:00', 'closed' => '0'], 10 => ['date' => '', 'label' => 'ignored']]]]))->assertRedirect();
        $v = $this->body('PUT', 'admin/cms/settings/hours')['value'];
        $this->assertSame(['MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN'], array_column($v['weekly'], 'day'));
        $this->assertSame(['day' => 'MON', 'open' => '09:30', 'close' => '21:00', 'closed' => false], $v['weekly'][0]);
        $this->assertSame(['day' => 'SUN', 'open' => null, 'close' => null, 'closed' => true], $v['weekly'][6]);
        $this->assertNull($v['notes']);
        $this->assertSame('New Year\'s Eve', end($v['holidays'])['label']);
        $this->assertCount(count($g['hours']['value']['holidays']) + 1, $v['holidays'], 'a holiday row without a date is dropped');
    }

    public function test_a_refused_group_keeps_the_edits_and_marks_its_tab(): void
    {
        $this->cms(['PUT /admin/cms/settings/brand' => [422, ['title' => 'Validation failed', 'status' => 422, 'code' => 'validation_failed', 'detail' => 'One or more fields are invalid.', 'errors' => ['value.name' => ['The name field is required.']]]]]);
        $this->from('/website/settings')->put('/website/settings', $this->form(['brand' => ['name' => '']]))->assertRedirect('/website/settings')->assertSessionHasErrors(['brand.name'])->assertSessionHas('error', fn ($m) => str_contains($m, 'Brand and logo'));
        $html = $this->followingRedirects()->from('/website/settings')->put('/website/settings', $this->form(['brand' => ['name' => '']]))->assertOk()->getContent();
        $this->assertStringContainsString('The name field is required.', $html);
        $this->assertStringContainsString('has errors', $html, 'the tab with the problem is flagged');
    }

    public function test_two_groups_one_saved_one_stale_reports_both(): void
    {
        $g = CmsApi::load('settings')['groups'];
        [$status, $problem] = CmsApi::failure('pages-stale-update');
        $this->cms(['PUT /admin/cms/settings/footer' => [$status, $problem], 'PUT /admin/cms/settings/announcement' => ['group' => 'announcement'] + $g['announcement']]);
        $res = $this->from('/website/settings')->put('/website/settings', $this->form(['announcement' => ['text' => 'New text'], 'footer' => ['text' => 'Changed footer']]))->assertRedirect('/website/settings');
        $res->assertSessionHas('success', fn ($m) => str_contains($m, 'Announcement bar'))->assertSessionHas('error', fn ($m) => str_contains($m, 'Footer'));
    }

    public function test_a_view_only_person_sees_the_values_but_no_save_bar(): void
    {
        $this->cms(perms: CmsApi::VIEWER);
        $html = $this->get('/website/settings')->assertOk()->assertSee('You can view the settings but not change them')->getContent();
        $this->assertStringNotContainsString('Save settings', $html);
        $this->put('/website/settings', $this->form())->assertForbidden();
    }

    public function test_settings_degrade_when_the_api_is_unavailable(): void
    {
        $this->cms(['GET /admin/cms/settings' => [502, ['title' => 'Bad gateway', 'status' => 502]]]);
        $this->get('/website/settings')->assertOk()->assertSee('could not be loaded');
    }
}

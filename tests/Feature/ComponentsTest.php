<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

/** One rendering test per shared Blade component: the design system's contract. */
class ComponentsTest extends TestCase
{
    private function render(string $blade): string
    {
        return Blade::render($blade);
    }

    public function test_status_pill_uses_one_tone_per_word_everywhere(): void
    {
        foreach (['CAPTURED' => 'success', 'PENDING' => 'warning', 'FAILED' => 'danger', 'VOIDED' => 'danger', 'REFUNDED' => 'info', 'ACTIVE' => 'success', 'INACTIVE' => 'danger', 'ONLINE' => 'success', 'OFFLINE' => 'danger', 'whatever' => 'neutral'] as $s => $tone) {
            $this->assertStringContainsString('data-tone="'.$tone.'"', $this->render("<x-status-pill status=\"{$s}\" />"), $s);
            $this->assertStringContainsString('data-tone="'.$tone.'"', $this->render("<x-badge status=\"{$s}\" />"), $s);
        }
        $this->assertStringContainsString('data-tone="success"', $this->render('<x-badge tone="good">x</x-badge>'));
    }

    public function test_money_formats_naira_and_shows_negatives_in_red(): void
    {
        $this->assertStringContainsString('₦12,345.00', $this->render('<x-money value="12345" />'));
        $neg = $this->render('<x-money value="-500.5" />');
        $this->assertStringContainsString('-₦500.50', $neg);
        $this->assertStringContainsString('text-red-700', $neg);
        $this->assertStringNotContainsString('text-red-700', $this->render('<x-money value="500" />'));
    }

    public function test_datetime_is_lagos_time_with_a_relative_hint(): void
    {
        $h = $this->render('<x-datetime at="2026-09-24T00:15:01Z" />');
        $this->assertStringContainsString('24 Sep 2026, 01:15', $h);
        $this->assertStringContainsString('datetime="2026-09-24T00:15:01Z"', $h);
        $this->assertMatchesRegularExpression('/title="[^"]*ago[^"]*"/', $h);
    }

    public function test_stat_card_shows_delta_direction_colour_and_sparkline(): void
    {
        $up = $this->render('<x-stat label="Sales" value="1" :delta="12.5" :spark="[1,2,3]" />');
        $this->assertStringContainsString('+12.5%', $up);
        $this->assertStringContainsString('text-brand-700', $up);
        $this->assertStringContainsString('<polyline', $up);
        $this->assertStringContainsString('text-red-700', $this->render('<x-stat label="Refunds" value="1" :delta="10" :invert="true" />'));
        $this->assertStringNotContainsString('data-testid="delta"', $this->render('<x-stat label="X" value="1" />'));
    }

    public function test_card_renders_title_subtitle_and_actions(): void
    {
        $h = $this->render('<x-card title="T" subtitle="S"><x-slot:aside>ACT</x-slot:aside>body</x-card>');
        foreach (['T', 'S', 'ACT', 'body'] as $x) {
            $this->assertStringContainsString($x, $h);
        }
    }

    public function test_alert_tones_have_the_right_aria_role(): void
    {
        $this->assertStringContainsString('role="alert"', $this->render('<x-alert tone="danger">x</x-alert>'));
        $this->assertStringContainsString('role="status"', $this->render('<x-alert tone="success">x</x-alert>'));
    }

    public function test_empty_state_shows_a_primary_action_only_when_given(): void
    {
        $with = $this->render('<x-empty-state title="Nothing" action="Add one" href="/x" />');
        $this->assertStringContainsString('Add one', $with);
        $this->assertStringContainsString('href="/x"', $with);
        $this->assertStringNotContainsString('href=', $this->render('<x-empty-state title="Nothing" />'));
    }

    public function test_skeleton_pagination_tabs_toggle_field_render(): void
    {
        view()->share('errors', new ViewErrorBag);
        $this->assertStringContainsString('skeleton', $this->render('<x-skeleton :rows="2" :cols="3" />'));
        $p = $this->render('<x-pagination :count="25" next="abc" />');
        $this->assertStringContainsString('Showing', $p);
        $this->assertStringContainsString('cursor=abc', $p);
        $this->assertStringNotContainsString('Next page', $this->render('<x-pagination :count="3" />'));
        $t = $this->render('<x-tabs :tabs="[\'a\' => \'A\', \'b\' => \'B\']" current="b" />');
        $this->assertStringContainsString('aria-selected="true"', $t);
        $this->assertStringContainsString('name="opt"', $this->render('<x-toggle name="opt" label="Option" :checked="true" />'));
        $f = $this->render('<x-input name="nm" label="Name" required hint="Help" />');
        foreach (['Name', 'Help', 'required'] as $x) {
            $this->assertStringContainsString($x, $f);
        }
        $this->assertStringContainsString('<option value="a"', $this->render('<x-select name="k" :options="[\'a\' => \'A\']" />'));
    }

    public function test_avatar_copy_rowmenu_modal_drawer_toasts_save_bar_render(): void
    {
        $this->assertStringContainsString('EO', $this->render('<x-avatar-name name="Ebiye Owei" sub="S-1" />'));
        $c = $this->render('<x-copy value="0123456789abcdef" />');
        $this->assertStringContainsString('clipboard', $c);
        $this->assertStringContainsString('01234567', $c);
        $this->assertStringContainsString('aria-haspopup', $this->render('<x-row-menu><a>x</a></x-row-menu>'));
        $this->assertStringContainsString('role="dialog"', $this->render('<x-modal name="m" title="M">x</x-modal>'));
        $this->assertStringContainsString('data-testid="drawer"', $this->render('<x-drawer />'));
        $this->assertStringContainsString('aria-live', $this->render('<x-toasts />'));
        $this->assertStringContainsString('Unsaved changes', $this->render('<x-save-bar />'));
    }

    public function test_table_tools_and_filter_chips(): void
    {
        $req = Request::create('/orders?status=SENT&facility=abc&cursor=zz', 'GET');
        app()->instance('request', $req);
        $chips = $this->render('<x-filter-bar />');
        $this->assertStringContainsString('Status:', $chips);
        $this->assertStringContainsString('Clear all', $chips);
        $this->assertStringNotContainsString('cursor', $chips);
        $tools = $this->render('<x-table-tools :csv="true" />');
        foreach (['Density', 'Columns', 'Export selected', 'CSV', 'per-page'] as $x) {
            $this->assertStringContainsString($x, $tools);
        }
    }

    public function test_icons_and_setup_progress_and_styleguide_render(): void
    {
        $this->assertStringContainsString('<svg', $this->render('<x-icon name="bell" />'));
        $this->signIn(['config.manage'])->get('/styleguide')->assertOk()->assertSee('Status pills')->assertSee('data-testid="styleguide-table"', false);
    }
}

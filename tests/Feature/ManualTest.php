<?php

namespace Tests\Feature;

use Tests\TestCase;

/** The staff manual is inside the portal for every signed-in person (online and property portal run the same code). */
class ManualTest extends TestCase
{
    public function test_guests_are_sent_to_sign_in(): void
    {
        $this->get('/help/manual')->assertRedirect(route('login'));
        $this->get('/help/manual.pdf')->assertRedirect(route('login'));
    }

    public function test_anyone_signed_in_can_read_it_without_any_permission(): void
    {
        $this->signIn([])
            ->get('/help/manual')
            ->assertOk()
            ->assertSee('Staff operations manual')
            ->assertSee('Waiter and bartender (tablet)')
            ->assertSee('Money rules on one page')
            ->assertSee('<table>', false);
    }

    public function test_the_menu_offers_it_to_everyone(): void
    {
        $this->signIn([])->get('/')->assertSee('Staff manual');
    }

    public function test_role_shortcuts_come_from_chapter_one_and_open_the_role_own_chapter(): void
    {
        $html = $this->signIn([])->get('/help/manual')->assertOk()->getContent();

        // label => the chapter that is that role's own
        foreach ([
            'Waiter or bartender' => 5, 'Reception cashier' => 7, 'Supervisor' => 8, 'Manager' => 13,
            'Owner or IT administrator' => 14, 'Anyone helping customers who book online' => 16,
        ] as $label => $chapter) {
            $this->assertMatchesRegularExpression(
                '/href="#chapter-'.$chapter.'"[^>]*data-testid="manual-role">'.preg_quote($label, '/').'</',
                $html,
                "{$label} should jump to chapter {$chapter}",
            );
        }
    }

    public function test_placeholders_in_angle_brackets_are_shown_not_swallowed(): void
    {
        $this->signIn([])->get('/help/manual')->assertSee('Added &lt;item&gt;.', false);
    }

    public function test_warning_boxes_are_marked_up(): void
    {
        $this->signIn([])->get('/help/manual')
            ->assertSee('class="sec sec-never"', false)
            ->assertSee('class="sec sec-wrong"', false)
            ->assertSee('class="p-job"', false);
    }

    public function test_the_designed_pdf_downloads_for_signed_in_staff(): void
    {
        $response = $this->signIn([])->get('/help/manual.pdf')->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', (string) file_get_contents($response->baseResponse->getFile()->getPathname()));
        $this->signIn([])->get('/help/manual')->assertSee('Download the PDF');
    }
}

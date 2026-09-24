<?php

namespace App\Support\Cms;

/**
 * The eight site-settings groups (docs/CMS_API.md 4.2) as form definitions. Field `name` is `group[key]`, so the API's 422 keys
 * (`brand.name`, or `value.name` which the controller re-keys) land on the right control.
 */
final class SettingsGroups
{
    public const DAYS = ['MON' => 'Monday', 'TUE' => 'Tuesday', 'WED' => 'Wednesday', 'THU' => 'Thursday', 'FRI' => 'Friday', 'SAT' => 'Saturday', 'SUN' => 'Sunday'];

    public const TONES = ['INFO' => 'Information', 'PROMO' => 'Promotion', 'WARNING' => 'Warning'];

    /** @return array<string, array{label: string, blurb: string, fields: list<array<string, mixed>>}> */
    public static function all(): array
    {
        $f = fn (string $g, string $k, string $type, string $label, array $more = []) => ['name' => "{$g}[{$k}]", 'key' => $k, 'type' => $type, 'label' => $label] + $more;

        return [
            'brand' => ['label' => 'Brand and logo', 'blurb' => 'The name and logo in the website header and browser tab.', 'fields' => [
                $f('brand', 'name', 'text', 'Business name', ['required' => true, 'max' => 80]),
                $f('brand', 'tagline', 'text', 'Tagline', ['max' => 140, 'hint' => 'A short line shown near the logo.']),
                $f('brand', 'logoMediaId', 'image', 'Logo', ['hint' => 'A wide logo on a transparent background works best.', 'ratio' => '3/1']),
            ]],
            'contact' => ['label' => 'Contact and map', 'blurb' => 'How guests reach you. Shown on the contact page and in the footer.', 'fields' => [
                $f('contact', 'phone', 'text', 'Phone', ['max' => 40, 'placeholder' => '+234 ...']),
                $f('contact', 'whatsapp', 'text', 'WhatsApp number', ['max' => 40, 'hint' => 'With country code. Adds a "Chat on WhatsApp" button.']),
                $f('contact', 'email', 'text', 'Email', ['max' => 120]),
                $f('contact', 'address', 'textarea', 'Address', ['max' => 300, 'rows' => 2]),
                $f('contact', 'mapEmbedUrl', 'url', 'Map embed address', ['max' => 800, 'hint' => 'In Google Maps choose Share, then Embed a map, and paste the address that starts with https://www.google.com/maps/embed.']),
                $f('contact', 'lat', 'decimal', 'Latitude', ['hint' => 'Optional, e.g. 4.9']),
                $f('contact', 'lng', 'decimal', 'Longitude', ['hint' => 'Optional, e.g. 6.2']),
            ]],
            'hours' => ['label' => 'Opening hours', 'blurb' => 'Shown on the website. These are for display; they do not change what can be booked.', 'fields' => [
                $f('hours', 'notes', 'textarea', 'Notes under the hours', ['max' => 300, 'rows' => 2, 'hint' => 'For example: Last entry one hour before closing.']),
            ]],
            'social' => ['label' => 'Social links', 'blurb' => 'Leave a box empty to hide that icon on the website.', 'fields' => array_map(
                fn ($k, $l, $ph) => $f('social', $k, 'url', $l, ['max' => 300, 'placeholder' => $ph]),
                ['instagram', 'facebook', 'x', 'tiktok', 'youtube'], ['Instagram', 'Facebook', 'X (Twitter)', 'TikTok', 'YouTube'],
                ['https://instagram.com/...', 'https://facebook.com/...', 'https://x.com/...', 'https://tiktok.com/@...', 'https://youtube.com/...'],
            )],
            'seo' => ['label' => 'Search and sharing', 'blurb' => 'What Google shows, and the picture used when a link is shared, unless a page sets its own.', 'fields' => [
                $f('seo', 'titleTemplate', 'text', 'Title pattern', ['max' => 100, 'hint' => 'Use %s where the page title goes, e.g. %s | 007 Resort & Spa.']),
                $f('seo', 'defaultTitle', 'text', 'Home page title', ['max' => 70]),
                $f('seo', 'defaultDescription', 'textarea', 'Default description', ['max' => 200, 'rows' => 3]),
                $f('seo', 'ogImageMediaId', 'image', 'Default share image', ['hint' => 'Shown when the link is shared on WhatsApp, Facebook and similar.', 'ratio' => '1200/630']),
            ]],
            'announcement' => ['label' => 'Announcement bar', 'blurb' => 'A slim message across the top of every page, for news like closures or offers.', 'fields' => [
                $f('announcement', 'enabled', 'toggle', 'Show the announcement bar', ['hint' => 'Switch off to hide it without losing the text.']),
                $f('announcement', 'text', 'text', 'Message', ['max' => 200]),
                $f('announcement', 'link', 'text', 'Link (optional)', ['max' => 300, 'placeholder' => '/events or https://...', 'hint' => 'Where the message takes people when clicked.']),
                $f('announcement', 'tone', 'segmented', 'Style', ['options' => self::TONES, 'default' => 'INFO']),
            ]],
            'booking' => ['label' => 'Booking labels', 'blurb' => 'The words on the main buttons across the website.', 'fields' => [
                $f('booking', 'ticketsCtaLabel', 'text', 'Tickets button', ['max' => 40, 'placeholder' => 'Book tickets']),
                $f('booking', 'bookingCtaLabel', 'text', 'Court and facility booking button', ['max' => 40, 'placeholder' => 'Book a court']),
                $f('booking', 'membershipCtaLabel', 'text', 'Membership button', ['max' => 40, 'placeholder' => 'Join the club']),
                $f('booking', 'eventsCtaLabel', 'text', 'Events button', ['max' => 40, 'placeholder' => 'Get tickets']),
            ]],
            'footer' => ['label' => 'Footer', 'blurb' => 'The text at the very bottom of every page.', 'fields' => [
                $f('footer', 'text', 'textarea', 'Footer text', ['max' => 300, 'rows' => 2]),
                $f('footer', 'copyright', 'text', 'Copyright line', ['max' => 120, 'placeholder' => '(c) 007 Resort & Spa']),
            ]],
        ];
    }
}

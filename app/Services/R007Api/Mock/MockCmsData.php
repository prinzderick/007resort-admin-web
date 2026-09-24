<?php

namespace App\Services\R007Api\Mock;

use Carbon\CarbonImmutable;

/** Seed content for the mock Website CMS API (shapes follow docs/CMS_API.md exactly). */
final class MockCmsData
{
    private static function id(string $kind, int $n): string
    {
        return sprintf('0192f6a0-%04x-7d2e-9a3b-%012x', crc32($kind) & 0xFFFF, $n);
    }

    public static function iso(int $minusSeconds = 0): string
    {
        return CarbonImmutable::now('UTC')->subSeconds($minusSeconds)->format('Y-m-d\TH:i:s.v\Z');
    }

    /** A small gradient placeholder picture as a data URI (no files needed). */
    public static function svg(string $label, string $a, string $b): string
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="1600" height="1067" viewBox="0 0 1600 1067"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="'.$a.'"/><stop offset="1" stop-color="'.$b.'"/></linearGradient></defs><rect width="1600" height="1067" fill="url(#g)"/><circle cx="1180" cy="330" r="150" fill="#ffffff" fill-opacity=".18"/><text x="80" y="960" font-family="sans-serif" font-size="72" fill="#ffffff" fill-opacity=".9">'.htmlspecialchars($label, ENT_XML1).'</text></svg>';

        return 'data:image/svg+xml;utf8,'.rawurlencode($svg);
    }

    /** @return array<string, mixed> */
    public static function seed(): array
    {
        $media = [];
        $pics = [['Pool deck', '#0f7d4f', '#1f9db5'], ['Restaurant', '#c9503c', '#e2a72e'], ['Spa', '#7b5cd6', '#2b6cde'], ['Football pitch', '#0a5035', '#74c49d'], ['Event hall', '#363e4a', '#9aa3b0'], ['Tennis court', '#2b6cde', '#1f9db5'], ['Rooms', '#e2a72e', '#c9503c'], ['Sunset bar', '#c9503c', '#7b5cd6'], ['Kids splash', '#1f9db5', '#74c49d'], ['Live band', '#141a22', '#7b5cd6'], ['Garden', '#3fa877', '#0b6541'], ['Reception', '#4f5866', '#a8dcc1']];
        foreach ($pics as $i => [$label, $a, $b]) {
            $url = self::svg($label, $a, $b);
            $media[] = ['id' => self::id('media', $i + 1), 'url' => $url, 'sourceUrl' => null, 'width' => 1600, 'height' => 1067, 'mimeType' => 'image/svg+xml', 'alt' => $label, 'credit' => $i % 3 === 0 ? 'Photo: 007 Resort' : '', 'dominantColor' => $a, 'variants' => [],
                'tags' => array_values(array_filter([['pool', 'food', 'spa', 'sports', 'events', 'sports', 'rooms', 'food', 'family', 'events', 'grounds', 'grounds'][$i], $i % 2 ? 'hero' : null])), 'sizeBytes' => 240000 + $i * 12000, 'originalName' => strtolower(str_replace(' ', '-', $label)).'.jpg', 'rowVersion' => 1, 'createdAt' => self::iso(86400 * (20 - $i)), 'updatedAt' => self::iso(86400 * (20 - $i))];
        }
        $m = fn (int $n) => self::id('media', $n);

        $settings = [
            'brand' => ['value' => ['name' => '007 Resort & Spa', 'tagline' => 'Play. Splash. Reset. Feast.', 'logoMediaId' => null]],
            'contact' => ['value' => ['phone' => '+234 800 000 0007', 'whatsapp' => '+234 800 000 0007', 'email' => 'hello@007resort.example', 'address' => 'Otueke, Bayelsa State, Nigeria', 'mapEmbedUrl' => null, 'lat' => 4.9, 'lng' => 6.2]],
            'hours' => ['value' => ['weekly' => array_map(fn ($d) => ['day' => $d, 'open' => '08:00', 'close' => in_array($d, ['FRI', 'SAT'], true) ? '23:00' : '22:00', 'closed' => false], ['MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN']), 'notes' => 'Last entry one hour before closing.', 'holidays' => [['date' => '2026-12-25', 'label' => 'Christmas Day', 'open' => '10:00', 'close' => '18:00', 'closed' => false]]]],
            'social' => ['value' => ['instagram' => 'https://instagram.com/007resort', 'facebook' => null, 'x' => null, 'tiktok' => null, 'youtube' => null]],
            'seo' => ['value' => ['titleTemplate' => '%s | 007 Resort & Spa', 'defaultTitle' => '007 Resort & Spa - play, splash, reset, feast', 'defaultDescription' => 'Pools, sports, spa, dining and events in one place.', 'ogImageMediaId' => $m(1)]],
            'announcement' => ['value' => ['enabled' => true, 'text' => 'Live band every Friday from 6pm.', 'link' => '/events', 'tone' => 'PROMO']],
            'booking' => ['value' => ['ticketsCtaLabel' => 'Book tickets', 'bookingCtaLabel' => 'Book a court', 'membershipCtaLabel' => 'Join the club', 'eventsCtaLabel' => 'Get tickets']],
            'footer' => ['value' => ['text' => 'Otueke, Bayelsa State. Open every day.', 'copyright' => '(c) 007 Resort & Spa']],
        ];
        foreach ($settings as $g => &$row) {
            $row += ['rowVersion' => 1, 'updatedAt' => self::iso(86400 * 3)];
        }
        unset($row);

        $home = [];
        $n = 0;
        $add = function (string $type, array $payload, bool $enabled = true) use (&$home, &$n) {
            $n++;
            $home[] = ['id' => self::id('home', $n), 'type' => $type, 'sortOrder' => $n * 10, 'enabled' => $enabled, 'payload' => $payload, 'rowVersion' => 1, 'createdAt' => self::iso(86400 * 10), 'updatedAt' => self::iso(86400 * 2)];
        };
        $add('HERO_SLIDE', ['headline' => 'Play. Splash. Reset. Feast.', 'subheadline' => 'Pools, courts, spa and a kitchen that never slows down.', 'mediaId' => $m(1), 'ctaLabel' => 'Book tickets', 'ctaLink' => '/tickets', 'alignment' => 'LEFT']);
        $add('HERO_SLIDE', ['headline' => 'Friday live band', 'subheadline' => 'Dinner on the deck, music till late.', 'mediaId' => $m(10), 'ctaLabel' => 'See events', 'ctaLink' => '/events', 'alignment' => 'CENTER']);
        $add('HERO_SLIDE', ['headline' => 'A quiet hour at the spa', 'subheadline' => 'Massage, sauna and a proper cup of tea.', 'mediaId' => $m(3), 'ctaLabel' => 'Plan a visit', 'ctaLink' => '/spa', 'alignment' => 'RIGHT'], false);
        foreach ([['Play', 'Courts, pitches and a games club.', 'From NGN 5,000', 'play', 4], ['Splash', 'Pools for grown-ups and for kids.', 'From NGN 3,000', 'splash', 9], ['Reset', 'Spa, sauna and quiet lounges.', 'From NGN 12,000', 'reset', 3], ['Feast', 'Restaurant, bush bar and grill.', null, 'feast', 2]] as [$t, $b, $p, $c, $img]) {
            $add('HIGHLIGHT', ['title' => $t, 'blurb' => $b, 'priceFrom' => $p, 'mediaId' => $m($img), 'link' => '/'.$c, 'category' => $c]);
        }
        foreach ([['Facilities', '26', '+', 'building'], ['Guests a month', '4,000', '+', 'users'], ['Years open', '9', '', 'calendar'], ['Rated by guests', '4.8', '/5', 'star']] as [$l, $v, $s, $i]) {
            $add('STAT', ['label' => $l, 'value' => $v, 'suffix' => $s, 'icon' => $i]);
        }
        foreach ([['Ada Obi', 'Regular guest', 'The pool deck at sunset is the best part of my week.', 5], ['Emeka N.', 'Football league captain', 'Booking the pitch online took a minute.', 5], ['Tolu A.', null, 'Great food, friendly staff.', 4]] as [$nm, $r, $q, $rt]) {
            $add('TESTIMONIAL', ['name' => $nm, 'role' => $r, 'quote' => $q, 'rating' => $rt, 'avatarMediaId' => null]);
        }
        foreach ([['Do I need to book?', 'Walk-ins are welcome. Courts and events sell out, so **book those online**.', 'Visiting'], ['Can I bring children?', 'Yes. The kids splash area is supervised.', 'Visiting'], ['Is there parking?', 'Free parking for all guests.', 'Getting here'], ['Do you take cards?', 'Cards, transfers and cash.', 'Payments']] as [$q, $a, $t]) {
            $add('FAQ', ['question' => $q, 'answer' => $a, 'topic' => $t]);
        }
        foreach (['Star Lager', 'Guinness', 'MTN', 'Paystack'] as $p) {
            $add('PARTNER', ['name' => $p, 'logoMediaId' => null, 'link' => null]);
        }
        $add('CTA_BAND', ['title' => 'Join the club', 'text' => 'Membership includes the pool, gym and discounts at every bar.', 'ctaLabel' => 'See memberships', 'ctaLink' => '/membership', 'mediaId' => $m(11)]);

        $body = "## Welcome\n\nWe are a **family-run** resort with pools, courts, a spa and a kitchen.\n\n- Open every day\n- Free parking\n- Kids welcome\n\nRead our [house rules](/house-rules).";
        $pages = [];
        foreach ([['about', 'About us', true, 'PUBLISHED', 1], ['contact-info', 'Contact info', true, 'PUBLISHED', 2], ['house-rules', 'House rules', true, 'PUBLISHED', 3], ['terms', 'Terms and conditions', true, 'PUBLISHED', 4], ['privacy', 'Privacy policy', true, 'DRAFT', 5]] as $i => [$slug, $title, $foot, $st, $so]) {
            $pages[] = ['id' => self::id('page', $i + 1), 'slug' => $slug, 'title' => $title, 'subtitle' => $i === 0 ? 'A family place by the river' : null, 'heroMediaId' => $i === 0 ? $m(11) : null, 'hero' => null, 'bodyMarkdown' => $body, 'bodyHtml' => '<h2>Welcome</h2>', 'seoTitle' => null, 'seoDescription' => $i === 0 ? 'Who we are and what you will find at 007 Resort & Spa.' : null, 'seoOgMediaId' => null, 'seoOgImage' => null,
                'showInFooter' => $foot, 'sortOrder' => $so * 10, 'status' => $st, 'publishedAt' => $st === 'PUBLISHED' ? self::iso(86400 * 9) : null, 'rowVersion' => 1, 'createdAt' => self::iso(86400 * 10), 'updatedAt' => self::iso(86400 * (6 - $i))];
        }

        $cats = [];
        foreach ([['news', 'News'], ['events-recap', 'Event recaps'], ['guides', 'Guides']] as $i => [$slug, $name]) {
            $cats[] = ['id' => self::id('cat', $i + 1), 'slug' => $slug, 'name' => $name, 'description' => null, 'sortOrder' => ($i + 1) * 10, 'postCount' => 0, 'rowVersion' => 1];
        }
        $posts = [];
        foreach ([['Five ways to spend a Saturday', 'guides', 'PUBLISHED', ['family', 'pool'], true, 2], ['Friday live band is back', 'news', 'PUBLISHED', ['music'], false, 5], ['New spa menu', 'news', 'DRAFT', ['spa'], false, 0], ['Football league recap', 'events-recap', 'PUBLISHED', ['sports'], false, 12], ['Christmas opening hours', 'news', 'PUBLISHED', [], false, -20], ['Kids splash area rules', 'guides', 'ARCHIVED', ['family'], false, 40]] as $i => [$title, $cat, $st, $tags, $feat, $ago]) {
            $cid = collect($cats)->firstWhere('slug', $cat)['id'];
            $posts[] = ['id' => self::id('post', $i + 1), 'slug' => strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $title), '-')), 'title' => $title, 'excerpt' => 'A short teaser for '.$title.'.', 'bodyMarkdown' => $body, 'bodyHtml' => '<p>x</p>', 'coverMediaId' => $m(($i % 10) + 1), 'cover' => null, 'authorName' => 'Ada', 'categoryId' => $cid, 'category' => ['id' => $cid, 'slug' => $cat, 'name' => collect($cats)->firstWhere('slug', $cat)['name']],
                'tags' => $tags, 'featured' => $feat, 'seoTitle' => null, 'seoDescription' => null, 'seoOgMediaId' => null, 'readingTimeMinutes' => 2, 'status' => $st, 'publishedAt' => $st === 'DRAFT' ? null : self::iso($ago * 86400), 'rowVersion' => 1, 'createdAt' => self::iso(86400 * 30), 'updatedAt' => self::iso(86400 * ($i + 1))];
        }

        $events = [];
        $nextFri = CarbonImmutable::now('Africa/Lagos')->next('friday')->setTime(18, 0);
        foreach ([['Friday live band', 'MUSIC', 'WEEKLY', $nextFri, 4, 'PUBLISHED', true, 'Poolside Deck', 'From NGN 5,000', 200], ['Sunday football league', 'SPORT', 'WEEKLY', CarbonImmutable::now('Africa/Lagos')->next('sunday')->setTime(16, 0), 2, 'PUBLISHED', false, 'Main pitch', 'Free entry', null], ['New year pool party', 'PARTY', 'NONE', CarbonImmutable::parse('2026-12-31 20:00', 'Africa/Lagos'), 6, 'PUBLISHED', true, 'Pool deck', 'NGN 15,000', 500], ['Spa day for two', 'WELLNESS', 'NONE', CarbonImmutable::now('Africa/Lagos')->addDays(20)->setTime(10, 0), 5, 'DRAFT', false, 'Spa', 'NGN 45,000', 20], ['Food festival', 'FOOD', 'NONE', CarbonImmutable::now('Africa/Lagos')->subDays(30)->setTime(12, 0), 5, 'ARCHIVED', false, 'Garden', 'Free', null]] as $i => [$title, $cat, $rec, $start, $dur, $st, $feat, $venue, $price, $cap]) {
            $events[] = ['id' => self::id('event', $i + 1), 'slug' => strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $title), '-')), 'title' => $title, 'summary' => 'Join us: '.$title.'.', 'bodyMarkdown' => 'Details coming soon.', 'coverMediaId' => $m(($i % 10) + 1), 'cover' => null, 'category' => $cat,
                'startsAt' => $start->utc()->format('Y-m-d\TH:i:s.v\Z'), 'endsAt' => $start->addHours($dur - 1 > 0 ? min($dur, 5) : 2)->utc()->format('Y-m-d\TH:i:s.v\Z'), 'venueLabel' => $venue, 'facilityId' => null, 'priceText' => $price, 'capacity' => $cap, 'ticketUrl' => $i === 0 ? 'https://example.com/tickets' : null, 'ticketProductId' => null,
                'recurrence' => $rec, 'recurrenceUntil' => $rec === 'WEEKLY' ? $start->addWeeks(12)->format('Y-m-d') : null, 'featured' => $feat, 'seoTitle' => null, 'seoDescription' => null, 'seoOgMediaId' => null, 'status' => $st, 'publishedAt' => $st === 'DRAFT' ? null : self::iso(86400 * 4), 'nextOccurrence' => null, 'rowVersion' => 1, 'createdAt' => self::iso(86400 * 12), 'updatedAt' => self::iso(86400 * ($i + 1))];
        }

        $albums = [
            ['id' => self::id('album', 1), 'slug' => 'poolside', 'title' => 'Poolside', 'description' => 'Sunny days on the deck.', 'coverMediaId' => $m(1), 'cover' => null, 'sortOrder' => 10, 'status' => 'PUBLISHED', 'publishedAt' => self::iso(86400 * 6), 'itemCount' => 0, 'rowVersion' => 1, 'createdAt' => self::iso(86400 * 9), 'updatedAt' => self::iso(86400 * 3)],
            ['id' => self::id('album', 2), 'slug' => 'events', 'title' => 'Events', 'description' => null, 'coverMediaId' => null, 'cover' => null, 'sortOrder' => 20, 'status' => 'DRAFT', 'publishedAt' => null, 'itemCount' => 0, 'rowVersion' => 1, 'createdAt' => self::iso(86400 * 8), 'updatedAt' => self::iso(86400 * 2)],
        ];
        $items = [];
        foreach ([1 => [1, 9, 8, 11], 2 => [10, 5]] as $aid => $ms) {
            foreach ($ms as $i => $mn) {
                $items[] = ['id' => self::id('gitem', $aid * 100 + $i), 'albumId' => self::id('album', $aid), 'mediaId' => $m($mn), 'caption' => $i === 0 ? 'The deck at golden hour' : null, 'altText' => null, 'category' => null, 'tags' => $i === 0 ? ['pool'] : [], 'featured' => $i === 0, 'sortOrder' => ($i + 1) * 10, 'rowVersion' => 1];
            }
        }

        $subs = [];
        $names = ['Ada', 'Emeka', 'Tolu', 'Ngozi', 'Chidi', 'Bisi', 'Amaka', 'Kelechi', 'Femi', 'Zainab'];
        for ($i = 0; $i < 34; $i++) {
            $st = $i % 9 === 0 ? 'PENDING' : ($i % 11 === 0 ? 'UNSUBSCRIBED' : 'CONFIRMED');
            $at = self::iso((int) (86400 * (0.4 + $i * 1.3)));
            $subs[] = ['id' => self::id('sub', $i + 1), 'email' => strtolower($names[$i % 10]).($i + 1).'@example.com', 'name' => $i % 4 === 0 ? null : $names[$i % 10], 'source' => ['footer', 'blog', 'event', 'popup', 'checkout'][$i % 5], 'status' => $st, 'consentText' => 'I agree to receive news.', 'consentedAt' => $at, 'confirmedAt' => $st === 'PENDING' ? null : $at, 'unsubscribedAt' => $st === 'UNSUBSCRIBED' ? $at : null, 'createdAt' => $at];
        }

        $msgs = [];
        foreach ([['Chinedu Eze', 'BOOKING', 'NEW', 'Hello, can we book the event hall for a 60-person birthday on 14 November? Please send a quote.'], ['Grace Peters', 'GENERAL', 'NEW', 'Do you allow outside cake for birthday dinners at the restaurant?'], ['Sam Ade', 'EVENTS', 'READ', 'Is the Friday band free for hotel guests?'], ['Lola K.', 'MEMBERSHIP', 'REPLIED', 'How much is the family membership?'], ['Promo Bot', 'OTHER', 'SPAM', 'Buy cheap watches now http://spam.example'], ['Tunde B.', 'FEEDBACK', 'NEW', 'Lovely evening, the suya was excellent. The car park lights need fixing.'], ['Ify O.', 'PRESS', 'READ', 'We would like to feature the resort in our weekend guide.'], ['Musa I.', 'GENERAL', 'NEW', 'What time do the pools close on Sundays?']] as $i => [$nm, $topic, $st, $msg]) {
            $msgs[] = ['id' => self::id('msg', $i + 1), 'name' => $nm, 'email' => strtolower(str_replace([' ', '.'], ['', ''], $nm)).'@example.com', 'phone' => $i % 2 ? null : '+234 803 000 00'.(10 + $i), 'topic' => $topic, 'message' => $msg, 'status' => $st, 'internalNote' => null, 'handledBy' => $st === 'REPLIED' ? 'Tunde Adebayo' : null, 'handledAt' => $st === 'REPLIED' ? self::iso(3600) : null, 'createdAt' => self::iso(3600 * (2 + $i * 7))];
        }

        return compact('media', 'settings', 'home', 'pages', 'cats', 'posts', 'events', 'albums', 'items', 'subs', 'msgs');
    }
}

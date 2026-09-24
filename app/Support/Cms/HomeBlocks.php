<?php

namespace App\Support\Cms;

/**
 * The seven homepage block types (docs/CMS_API.md 4.3): labels, form fields (payload keys are the API's), and how a row is summarised.
 * Field `name` is `payload[key]`. `title`/`sub`/`image` are the payload keys used for the row summary.
 */
final class HomeBlocks
{
    public const CATEGORIES = ['play' => 'Play', 'splash' => 'Splash', 'reset' => 'Reset', 'feast' => 'Feast'];

    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        $f = fn (string $k, string $type, string $label, array $more = []) => ['name' => "payload[{$k}]", 'key' => $k, 'type' => $type, 'label' => $label] + $more;

        return [
            'HERO_SLIDE' => ['label' => 'Hero slides', 'one' => 'hero slide', 'anchor' => '', 'title' => 'headline', 'sub' => 'subheadline', 'image' => 'mediaId',
                'blurb' => 'The big pictures at the top of the home page. They rotate, so keep to three or four.', 'add' => 'Add first hero slide', 'preview' => true, 'fields' => [
                    $f('headline', 'text', 'Headline', ['required' => true, 'max' => 140]),
                    $f('subheadline', 'textarea', 'Line under the headline', ['max' => 300, 'rows' => 2]),
                    $f('mediaId', 'image', 'Background picture', ['required' => true, 'ratio' => '16/9', 'hint' => 'A wide picture. Keep the important part in the middle.']),
                    $f('ctaLabel', 'text', 'Button text', ['max' => 40, 'placeholder' => 'Book tickets']),
                    $f('ctaLink', 'text', 'Button link', ['max' => 300, 'placeholder' => '/tickets or https://...']),
                    $f('alignment', 'segmented', 'Text position', ['options' => ['LEFT' => 'Left', 'CENTER' => 'Centre', 'RIGHT' => 'Right'], 'default' => 'LEFT']),
                ]],
            'HIGHLIGHT' => ['label' => 'Highlights', 'one' => 'highlight', 'anchor' => '#highlights', 'title' => 'title', 'sub' => 'blurb', 'image' => 'mediaId',
                'blurb' => 'The four things guests come for: Play, Splash, Reset and Feast.', 'add' => 'Add first highlight', 'fields' => [
                    $f('title', 'text', 'Title', ['required' => true, 'max' => 80]),
                    $f('blurb', 'textarea', 'Short description', ['required' => true, 'max' => 400, 'rows' => 3]),
                    $f('category', 'select', 'Which one is it', ['required' => true, 'options' => self::CATEGORIES]),
                    $f('priceFrom', 'text', 'Price note', ['max' => 60, 'placeholder' => 'From NGN 5,000']),
                    $f('link', 'text', 'Link', ['max' => 300, 'placeholder' => '/play']),
                    $f('mediaId', 'image', 'Picture', ['ratio' => '4/3']),
                ]],
            'STAT' => ['label' => 'Stats', 'one' => 'stat', 'anchor' => '#stats', 'title' => 'value', 'sub' => 'label', 'image' => null,
                'blurb' => 'Big numbers that show what the resort offers, such as 26+ facilities.', 'add' => 'Add first stat', 'fields' => [
                    $f('value', 'text', 'The number', ['required' => true, 'max' => 20, 'placeholder' => '26+']),
                    $f('suffix', 'text', 'After the number', ['max' => 20, 'placeholder' => 'optional, e.g. /5']),
                    $f('label', 'text', 'What it counts', ['required' => true, 'max' => 60, 'placeholder' => 'Facilities']),
                    $f('icon', 'text', 'Icon name', ['max' => 40, 'hint' => 'Optional. Ask the website team for the available icon names.']),
                ]],
            'TESTIMONIAL' => ['label' => 'Testimonials', 'one' => 'testimonial', 'anchor' => '#testimonials', 'title' => 'name', 'sub' => 'quote', 'image' => 'avatarMediaId',
                'blurb' => 'What guests say about you. Use real quotes with permission.', 'add' => 'Add first testimonial', 'fields' => [
                    $f('name', 'text', 'Guest name', ['required' => true, 'max' => 80]),
                    $f('role', 'text', 'Who they are', ['max' => 80, 'placeholder' => 'e.g. Regular guest']),
                    $f('quote', 'textarea', 'What they said', ['required' => true, 'max' => 600, 'rows' => 3]),
                    $f('rating', 'select', 'Stars', ['options' => ['5' => '5 stars', '4' => '4 stars', '3' => '3 stars', '2' => '2 stars', '1' => '1 star'], 'placeholder' => 'No stars']),
                    $f('avatarMediaId', 'image', 'Photo', ['ratio' => '1/1', 'hint' => 'Optional.']),
                ]],
            'FAQ' => ['label' => 'FAQs', 'one' => 'question', 'anchor' => '#faq', 'title' => 'question', 'sub' => 'topic', 'image' => null,
                'blurb' => 'Answers to the questions guests ask most.', 'add' => 'Add first FAQ', 'fields' => [
                    $f('question', 'text', 'Question', ['required' => true, 'max' => 200]),
                    $f('answer', 'textarea', 'Answer', ['required' => true, 'max' => 2000, 'rows' => 5, 'hint' => 'You can use Markdown: **bold**, [links](/page) and - bullet lists.']),
                    $f('topic', 'text', 'Group', ['max' => 60, 'placeholder' => 'e.g. Visiting', 'hint' => 'Questions with the same group are shown together.']),
                ]],
            'PARTNER' => ['label' => 'Partners', 'one' => 'partner', 'anchor' => '#partners', 'title' => 'name', 'sub' => 'link', 'image' => 'logoMediaId',
                'blurb' => 'Logos of brands and sponsors you work with.', 'add' => 'Add first partner', 'fields' => [
                    $f('name', 'text', 'Name', ['required' => true, 'max' => 80]),
                    $f('logoMediaId', 'image', 'Logo', ['ratio' => '3/2']),
                    $f('link', 'text', 'Link', ['max' => 300, 'placeholder' => 'https://...']),
                ]],
            'CTA_BAND' => ['label' => 'Call-to-action bands', 'one' => 'band', 'anchor' => '#cta', 'title' => 'title', 'sub' => 'text', 'image' => 'mediaId',
                'blurb' => 'A wide banner with one button, such as "Join the club".', 'add' => 'Add first band', 'fields' => [
                    $f('title', 'text', 'Title', ['required' => true, 'max' => 120]),
                    $f('text', 'textarea', 'Text', ['max' => 300, 'rows' => 2]),
                    $f('ctaLabel', 'text', 'Button text', ['required' => true, 'max' => 40]),
                    $f('ctaLink', 'text', 'Button link', ['required' => true, 'max' => 300, 'placeholder' => '/membership']),
                    $f('mediaId', 'image', 'Background picture', ['ratio' => '16/6']),
                ]],
        ];
    }
}

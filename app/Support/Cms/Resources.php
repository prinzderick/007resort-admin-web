<?php

namespace App\Support\Cms;

/**
 * Definitions for the content screens that share one list page and one editor (pages, blog posts, blog categories, events).
 * Field names are the API's (docs/CMS_API.md 4.4-4.6), so a 422 `errors` key lands on the field it belongs to.
 *
 * field: name, type (text|textarea|markdown|slug|image|toggle|select|segmented|tags|datetime|date|number|url), label, hint, required, max, options|optionsFrom
 * column kinds: title | status | text:<key> | date:<key> | thumb | count:<key> | tags
 */
final class Resources
{
    public const KEYS = ['pages', 'posts', 'events', 'categories'];

    public const EVENT_CATEGORIES = ['SPORT' => 'Sport', 'MUSIC' => 'Music', 'PARTY' => 'Party', 'WELLNESS' => 'Wellness', 'FOOD' => 'Food', 'OTHER' => 'Other'];

    /** @return array<string, mixed> */
    public static function get(string $key): array
    {
        $all = self::all();
        abort_unless(isset($all[$key]), 404);

        return $all[$key] + ['key' => $key];
    }

    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        $seo = fn () => ['title' => 'Search engines and sharing', 'subtitle' => 'How this appears on Google and when the link is shared. Leave blank to use the title and summary.', 'kind' => 'seo', 'fields' => [
            ['name' => 'seoTitle', 'type' => 'text', 'label' => 'Search title', 'max' => 70, 'hint' => 'Up to 70 letters. Blank = the title.'],
            ['name' => 'seoDescription', 'type' => 'textarea', 'label' => 'Search description', 'max' => 200, 'rows' => 3, 'hint' => 'One or two sentences.'],
            ['name' => 'seoOgMediaId', 'type' => 'image', 'label' => 'Share image', 'hint' => 'Shown when the link is shared on WhatsApp, Facebook and similar. Wide pictures work best.', 'ratio' => '1200/630'],
        ]];

        return [
            'pages' => [
                'label' => 'Page', 'plural' => 'Pages', 'base' => 'pages', 'api' => 'pages', 'publish' => true, 'versioned' => true, 'nav' => 'website.pages',
                'subtitle' => 'About, contact information, house rules, terms and privacy: the fixed pages of the website.',
                'empty' => ['title' => 'No pages yet', 'text' => 'Pages such as About us, House rules and Terms show in the website footer.', 'action' => 'Add first page'],
                'searchHint' => 'Title or address',
                'sitePath' => fn (array $i) => '/'.($i['slug'] ?? ''),
                'columns' => [['title', 'Page', 'title'], ['status', 'Status', 'status'], ['footer', 'In footer', 'bool:showInFooter'], ['updated', 'Last changed', 'date:updatedAt']],
                'filters' => [],
                'main' => [
                    ['title' => 'Page', 'fields' => [
                        ['name' => 'title', 'type' => 'text', 'label' => 'Title', 'required' => true, 'max' => 160],
                        ['name' => 'slug', 'type' => 'slug', 'label' => 'Web address', 'source' => 'title'],
                        ['name' => 'subtitle', 'type' => 'text', 'label' => 'Subtitle', 'max' => 200, 'hint' => 'One line under the title.'],
                        ['name' => 'bodyMarkdown', 'type' => 'markdown', 'label' => 'Page text', 'required' => true],
                    ]],
                    $seo(),
                ],
                'side' => [
                    ['title' => 'Top picture', 'fields' => [['name' => 'heroMediaId', 'type' => 'image', 'label' => 'Hero picture', 'hint' => 'Shown across the top of the page.']]],
                    ['title' => 'Website footer', 'fields' => [
                        ['name' => 'showInFooter', 'type' => 'toggle', 'label' => 'Link to this page in the footer', 'default' => false],
                        ['name' => 'sortOrder', 'type' => 'number', 'label' => 'Order in the footer', 'hint' => 'Lower numbers come first.', 'max' => 6],
                    ]],
                ],
            ],
            'posts' => [
                'label' => 'Post', 'plural' => 'Blog posts', 'base' => 'blog', 'api' => 'posts', 'publish' => true, 'versioned' => true, 'schedule' => true, 'nav' => 'website.posts',
                'subtitle' => 'News and stories. Write in Markdown, add a cover picture, and publish now or schedule for later.',
                'empty' => ['title' => 'No blog posts yet', 'text' => 'Posts appear on the blog page of the website once published.', 'action' => 'Add first post'],
                'searchHint' => 'Title, teaser or text',
                'sitePath' => fn (array $i) => '/blog/'.($i['slug'] ?? ''),
                'columns' => [['cover', '', 'thumb:cover'], ['title', 'Post', 'title'], ['category', 'Category', 'text:category.name'], ['tags', 'Tags', 'tags'], ['status', 'Status', 'status'], ['date', 'Publish date', 'date:publishedAt'], ['featured', 'Featured', 'bool:featured']],
                'filters' => [['name' => 'categoryId', 'label' => 'Category', 'optionsFrom' => 'categories', 'all' => 'All categories'], ['name' => 'featured', 'label' => 'Featured', 'options' => ['true' => 'Featured only', 'false' => 'Not featured'], 'all' => 'All posts']],
                'main' => [
                    ['title' => 'Post', 'fields' => [
                        ['name' => 'title', 'type' => 'text', 'label' => 'Title', 'required' => true, 'max' => 160],
                        ['name' => 'slug', 'type' => 'slug', 'label' => 'Web address', 'source' => 'title', 'prefix' => '/blog/'],
                        ['name' => 'excerpt', 'type' => 'textarea', 'label' => 'Short teaser', 'max' => 400, 'rows' => 3, 'hint' => 'Shown in blog lists. Blank = taken from the start of the text.'],
                        ['name' => 'bodyMarkdown', 'type' => 'markdown', 'label' => 'Post text', 'required' => true],
                    ]],
                    $seo(),
                ],
                'side' => [
                    ['title' => 'Cover picture', 'fields' => [['name' => 'coverMediaId', 'type' => 'image', 'label' => 'Cover', 'hint' => 'Shown at the top of the post and in lists.']]],
                    ['title' => 'Details', 'fields' => [
                        ['name' => 'categoryId', 'type' => 'select', 'label' => 'Category', 'optionsFrom' => 'categories', 'placeholder' => 'No category'],
                        ['name' => 'tags', 'type' => 'tags', 'label' => 'Tags', 'max' => 12, 'maxLength' => 32, 'lowercase' => true, 'hint' => 'Up to 12. Press Enter after each.'],
                        ['name' => 'authorName', 'type' => 'text', 'label' => 'Author', 'max' => 80],
                        ['name' => 'featured', 'type' => 'toggle', 'label' => 'Featured post', 'hint' => 'Shown first on the blog page.', 'default' => false],
                    ]],
                ],
            ],
            'events' => [
                'label' => 'Event', 'plural' => 'Events', 'base' => 'events', 'api' => 'events', 'publish' => true, 'versioned' => true, 'nav' => 'website.events',
                'subtitle' => 'Nights, tournaments and special days. Set the date in Lagos time; weekly events repeat until the date you choose.',
                'empty' => ['title' => 'No events yet', 'text' => 'Published events appear on the events page and on the home page.', 'action' => 'Add first event'],
                'searchHint' => 'Title or summary',
                'sitePath' => fn (array $i) => '/events/'.($i['slug'] ?? ''),
                'columns' => [['cover', '', 'thumb:cover'], ['title', 'Event', 'title'], ['category', 'Category', 'label:category'], ['starts', 'Starts (Lagos)', 'when:startsAt'], ['repeat', 'Repeats', 'repeat'], ['status', 'Status', 'status'], ['featured', 'Featured', 'bool:featured']],
                'filters' => [['name' => 'category', 'label' => 'Category', 'options' => self::EVENT_CATEGORIES, 'all' => 'All categories'], ['name' => 'when', 'label' => 'When', 'options' => ['upcoming' => 'Upcoming', 'past' => 'Past'], 'all' => 'Any time']],
                'extra' => [
                    ['name' => 'ticketUrl', 'type' => 'url', 'label' => 'Web address', 'max' => 500, 'placeholder' => 'https://...'],
                    ['name' => 'ticketProductId', 'type' => 'select', 'label' => 'Ticket product', 'optionsFrom' => 'products', 'placeholder' => 'Choose a ticket'],
                ],
                'main' => [
                    ['title' => 'Event', 'fields' => [
                        ['name' => 'title', 'type' => 'text', 'label' => 'Title', 'required' => true, 'max' => 160],
                        ['name' => 'slug', 'type' => 'slug', 'label' => 'Web address', 'source' => 'title', 'prefix' => '/events/'],
                        ['name' => 'summary', 'type' => 'textarea', 'label' => 'Summary', 'max' => 300, 'rows' => 2, 'hint' => 'One or two lines for lists and the home page.'],
                        ['name' => 'bodyMarkdown', 'type' => 'markdown', 'label' => 'Details'],
                    ]],
                    ['title' => 'When', 'subtitle' => 'Times are Lagos time.', 'cols' => 2, 'after' => 'pages.website.partials.event-occurrences', 'fields' => [
                        ['name' => 'startsAt', 'type' => 'datetime', 'label' => 'Starts', 'required' => true],
                        ['name' => 'endsAt', 'type' => 'datetime', 'label' => 'Ends', 'required' => true, 'hint' => 'After the start, and within 14 days.'],
                        ['name' => 'recurrence', 'type' => 'segmented', 'label' => 'Repeats', 'options' => ['NONE' => 'Does not repeat', 'WEEKLY' => 'Every week'], 'default' => 'NONE'],
                        ['name' => 'recurrenceUntil', 'type' => 'date', 'label' => 'Repeat until', 'hint' => 'The last day it can happen. Leave blank to keep repeating.'],
                    ]],
                    ['title' => 'Venue and price', 'cols' => 2, 'fields' => [
                        ['name' => 'venueLabel', 'type' => 'text', 'label' => 'Venue', 'max' => 120, 'placeholder' => 'e.g. Poolside Deck'],
                        ['name' => 'facilityId', 'type' => 'select', 'label' => 'Facility', 'optionsFrom' => 'facilities', 'placeholder' => 'Not linked to a facility', 'hint' => 'Optional: links the event to a place in the resort.'],
                        ['name' => 'priceText', 'type' => 'text', 'label' => 'Price', 'max' => 80, 'placeholder' => 'e.g. From NGN 5,000, or Free'],
                        ['name' => 'capacity', 'type' => 'number', 'label' => 'Capacity', 'max' => 6, 'hint' => 'Leave blank if not limited.'],
                    ]],
                    ['title' => 'Tickets and booking', 'subtitle' => 'Where the "Get tickets" button on the website goes.', 'view' => 'pages.website.partials.event-tickets'],
                    $seo(),
                ],
                'side' => [
                    ['title' => 'Cover picture', 'fields' => [['name' => 'coverMediaId', 'type' => 'image', 'label' => 'Cover']]],
                    ['title' => 'Details', 'fields' => [
                        ['name' => 'category', 'type' => 'select', 'label' => 'Category', 'options' => self::EVENT_CATEGORIES, 'required' => true],
                        ['name' => 'featured', 'type' => 'toggle', 'label' => 'Featured event', 'hint' => 'Highlighted on the home page.', 'default' => false],
                    ]],
                ],
            ],
            'categories' => [
                'label' => 'Category', 'plural' => 'Blog categories', 'base' => 'blog/categories', 'api' => 'post-categories', 'publish' => false, 'versioned' => false, 'nav' => 'website.posts',
                'subtitle' => 'Groups for blog posts, such as News or Guides. A category cannot be deleted while posts use it.',
                'empty' => ['title' => 'No categories yet', 'text' => 'Add a category, then choose it when writing a post.', 'action' => 'Add first category'],
                'searchHint' => 'Name',
                'columns' => [['title', 'Category', 'title:name'], ['count', 'Posts', 'count:postCount'], ['order', 'Order', 'text:sortOrder']],
                'filters' => [],
                'main' => [['title' => 'Category', 'fields' => [
                    ['name' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => true, 'max' => 80],
                    ['name' => 'slug', 'type' => 'slug', 'label' => 'Web address', 'source' => 'name', 'prefix' => '/blog/category/'],
                    ['name' => 'description', 'type' => 'textarea', 'label' => 'Description', 'max' => 300, 'rows' => 2],
                    ['name' => 'sortOrder', 'type' => 'number', 'label' => 'Order', 'hint' => 'Lower numbers come first.', 'max' => 6],
                ]]],
                'side' => [],
            ],
        ];
    }
}

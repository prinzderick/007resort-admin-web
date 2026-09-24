<?php

namespace App\Http\Controllers\Website;

use App\Support\Cms\Cms;

/** Website home: what is live, what needs attention, and shortcuts to the things editors do most. */
class OverviewController extends WebsiteController
{
    public function index()
    {
        $summary = $this->can(Cms::VIEW) ? $this->cms->fetch('summary') : null;

        return view('pages.website.index', ['summary' => $summary, 's' => $summary?->ok() ? (array) $summary->data : []]);
    }
}

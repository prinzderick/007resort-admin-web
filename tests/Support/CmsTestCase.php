<?php

namespace Tests\Support;

use Tests\TestCase;

/** Base for the Website screen tests: a signed-in editor and the recorded CMS API. */
abstract class CmsTestCase extends TestCase
{
    /**
     * @param  array<string, mixed>  $over  extra/overriding fake routes
     * @param  list<string>  $perms
     */
    protected function cms(array $over = [], array $perms = CmsApi::ALL): static
    {
        $this->fakeApi(CmsApi::routes($over));

        return $this->signIn($perms, ['MARKETING'], 'Ifeoma Nnadi');
    }

    /** The JSON body of the last request sent to the API for a method + path. @return array<string, mixed> */
    protected function body(string $method, string $path): array
    {
        return (array) $this->lastSent($method, $path)?->data();
    }
}

<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Companion to NoBusinessTablesTest: nothing in the app talks to a database of
 * its own (ADR-0007). Framework state uses file/array drivers only.
 */
class NoBusinessDatabaseTest extends TestCase
{
    private function base(string $p = ''): string
    {
        return dirname(__DIR__, 2).($p ? '/'.$p : '');
    }

    public function test_app_code_does_not_use_db_facade_or_query_builder(): void
    {
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->base('app'), \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->getExtension() !== 'php') {
                continue;
            }
            $src = (string) file_get_contents($f->getPathname());
            $this->assertDoesNotMatchRegularExpression('/\\b(DB::|Illuminate\\\\Support\\\\Facades\\\\DB\\b|Schema::|->newQuery\\(|Eloquent)/', $src, $f->getPathname().' touches a database.');
        }
    }

    public function test_no_database_directories_carry_schema(): void
    {
        $this->assertSame([], glob($this->base('database/migrations/*.php')) ?: []);
        $this->assertSame([], glob($this->base('app/Models/*.php')) ?: []);
    }

    public function test_session_cache_and_queue_are_not_database_backed_in_the_example_env(): void
    {
        $env = (string) file_get_contents($this->base('.env.example'));
        $this->assertMatchesRegularExpression('/^SESSION_DRIVER=file$/m', $env);
        $this->assertMatchesRegularExpression('/^CACHE_STORE=file$/m', $env);
        $this->assertMatchesRegularExpression('/^QUEUE_CONNECTION=sync$/m', $env);
        $this->assertDoesNotMatchRegularExpression('/^DB_CONNECTION=(?!\\s*$)/m', $env);
        $this->assertMatchesRegularExpression('/^R007_MOCK=false$/m', $env, 'Mock mode must be off by default.');
    }
}

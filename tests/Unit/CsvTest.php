<?php

namespace Tests\Unit;

use App\Support\Csv;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CsvTest extends TestCase
{
    #[DataProvider('cells')]
    public function test_cells(mixed $in, string $out): void
    {
        $this->assertSame($out, Csv::cell($in));
    }

    public static function cells(): array
    {
        return [
            'plain' => ['Bisi', 'Bisi'], 'null' => [null, ''], 'money stays exact' => ['1500.0000', '1500.0000'], 'negative number is not a formula' => ['-500.0000', '-500.0000'],
            'formula' => ['=SUM(A1)', "'=SUM(A1)"], 'plus' => ['+234', "'+234"], 'minus text' => ['-cmd', "'-cmd"], 'at' => ['@x', "'@x"], 'tab' => ["\tX", "'\tX"],
            'bool' => [true, 'true'], 'array' => [['a' => 1], '{"a":1}'],
        ];
    }
}

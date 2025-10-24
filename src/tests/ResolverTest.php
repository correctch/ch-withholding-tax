<?php

namespace Correctch\ChWithholdingTax\tests;

use Correctch\ChWithholdingTax\Canton;
use Correctch\ChWithholdingTax\Exceptions\YearNotFoundException;
use Correctch\ChWithholdingTax\WithholdingTax;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ResolverTest extends TestCase
{
    public function test_resolver()
    {
        $class = new WithholdingTax(Canton::AG, 'A0N');
        $out = $class->resolve(2025, 6500.00, 6500.00);
        $this->assertIsArray($out);

        $class = new WithholdingTax(Canton::AG, 'A0N');
        $out = $class->resolve(2022, 6500.00, 6500.00);
        $this->assertIsArray($out);
    }

    public function test_wrongCode()
    {
        $this->expectException(InvalidArgumentException::class);
        $class = new WithholdingTax(Canton::AG, 'S1U');
    }

    public function test_missingYear()
    {
        $this->expectException(YearNotFoundException::class);
        $class = new WithholdingTax(Canton::AG, 'A1N');
        $class->resolve(2000, 650.30, 120.0);
    }
}
<?php

namespace Correctch\ChWithholdingTax;

use Correctch\ChWithholdingTax\Exceptions\ParsingError;
use Correctch\ChWithholdingTax\Exceptions\YearNotFoundException;

class WithholdingTax
{
    public int $numberOfChildren;

    public function __construct(public Canton $canton, public string $code)
    {
        $code = strtoupper($code);
        if (! preg_match('/^[A-Z][0-9][Y,N]$/', $code)) {
            throw new \InvalidArgumentException("Invalid code format: {$code}");
        }

        $this->code = $code;
        $this->numberOfChildren = intval(substr($code, 1, 1));;
    }

    /**
     * @param int $year
     * @param float $wage
     * @param float $rate_determining_income
     * @return array
     * @throws ParsingError
     * @throws YearNotFoundException
     */
    public function resolve(int $year, float $wage, float $rate_determining_income): array
    {
        if($year < 2016 || $year > 2025) {
            throw new YearNotFoundException($year. 'does not exists in this package');
        }

        return $this->getTarifForWage($year, $wage, $rate_determining_income);
    }


    /**
     * @param float $wage Lohn bei 100%?
     * @throws ParsingError
     */
    public function getTarifForWage(int $year, float $wage, float $rate_determining_income): array
    {
        $handle = fopen(self::filePath($year, $this->canton), 'r');
        $validLine = null; // init as false
        $code = strtoupper($this->code);

        while (($buffer = fgets($handle)) !== false) {
            if (str_contains($buffer, $code)) {
                $buffer = str_replace(["\r", "\n"], '', $buffer);
                // offset is documented as +1, but offset starts at 0 in PHP
                $steuerbaresEinkommen = intval(substr($buffer, 24, 9)) / 100;
                $schritt = intval(substr($buffer, 33, 9)) / 100;
                $anzahlKinder = intval(substr($buffer, 43, 2));

                if ($this->numberOfChildren == $anzahlKinder && $rate_determining_income >= $steuerbaresEinkommen && $rate_determining_income <= ($steuerbaresEinkommen + $schritt)) {
                    $validLine = $buffer;
                    break; // Once you find the string, you should break out the loop.
                }
            }
        }
        fclose($handle);
        if (is_null($validLine)) {
            throw new ParsingError('Parsing Error, something with the code went wrong');
        }

        return $this->parseLine($validLine, $wage);
    }

    protected function parseLine(string $line, int $wage): array
    {
        $min = intval(substr($line, 45, 9)) / 100;
        $percentage = intval(substr($line, 54, 5)) / 100;
        // offset is documented as +1, but offset starts at 0 in PHP

        return [
            'wage' => $wage,
            'code' => $this->code,
            'taxable_income' => intval(substr($line, 24, 9)) / 100,
            'interval' => intval(substr($line, 33, 9)) / 100,
            'number_of_children' => intval(substr($line, 43, 2)),
            'min_taxable_amount' => $min,
            'tax_percentage' => $percentage,
            'calculated_deduction' => max($min, $wage * $percentage / 100),
        ];
    }

    public static function getCodesForCanton(int $year, Canton $canton): array
    {
        $handle = fopen(self::filePath($year, $canton), 'r');
        $arr = [];
        while (($buffer = fgets($handle)) !== false) {
            $code = trim(substr($buffer, 6, 10));
            if (! in_array($code, $arr)) {
                $arr[] = $code;
            }
        }

        return $arr;
    }

    private static function filePath(int $year, Canton $canton): string
    {
        return __DIR__.'/data/'.$year.'/'.$canton->value.'.txt';
    }

}
<?php

namespace Database\Seeders\Support;

/**
 * تخزين مؤقت لمعرّفات السجلات أثناء التنفيذ المتسلسل للـ Seeders
 */
class SeedRegistry
{
    /** @var array<string, int> */
    public static array $companies = [];

    /** @var array<string, int> patient_code => id */
    public static array $patients = [];

    /** @var array<string, int> case_no => id */
    public static array $cases = [];

    /** @var array<string, int> request_no => id */
    public static array $pricingRequests = [];

    /** @var array<string, int> quote_no => id */
    public static array $quotes = [];

    /** @var array<string, int> bom_no => id */
    public static array $boms = [];

    /** @var array<string, int> stock code => id */
    public static array $stockItems = [];

    /** @var array<string, int> supplier name => id */
    public static array $suppliers = [];

    public static function reset(): void
    {
        self::$companies = [];
        self::$patients = [];
        self::$cases = [];
        self::$pricingRequests = [];
        self::$quotes = [];
        self::$boms = [];
        self::$stockItems = [];
        self::$suppliers = [];
    }
}

<?php

namespace Tests\Feature;

use Enercalcapi\Readings\Services\ReadingsService;
use Illuminate\Support\Carbon;
use Orchestra\Testbench\TestCase;

class InitialTest extends TestCase
{
    public function testAssert()
    {
        $this->assertTrue(true);
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function testHi()
    {
        
        $test = new ReadingsService();

        $date = Carbon::now();
        $dateTo = Carbon::tomorrow();
        $eanArray = ['871687940033446910', '871687940033446132', '871687940033446149'];
        $eanArrayOneError = ['871687940033446910', '8716879400334461320', '871687940033446149'];
        $eanArrayAllError = ['8716879400334469100', '8716879400334461320', '8716879400334461490'];

        dd(__LINE__, $test->requestP4Data('interval', $eanArray, [$date, $dateTo]));

        $this->assertTrue(true);
    }
}

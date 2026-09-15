<?php

namespace Tests\Feature;

use Tests\TestCase;

class TimezoneTest extends TestCase
{
    public function test_application_timezone_is_malaysia(): void
    {
        $this->assertSame('Asia/Kuala_Lumpur', config('app.timezone'));
    }
}

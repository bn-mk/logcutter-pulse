<?php

namespace Logcutter\LogPulse\Http\Controllers\Debug;

use RuntimeException;

class LogBugController
{
    public function nullPropertyCrash(): never
    {
        $payload = null;

        $value = $payload->name;

        throw new RuntimeException((string) $value);
    }

    public function explicitException(): never
    {
        throw new RuntimeException('Intentional debug exception from LogBugController::explicitException');
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;

abstract class Controller
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Run a best-effort side-effect (realtime broadcast, email/db notification)
     * without letting a downstream failure (e.g. Reverb or SMTP unreachable)
     * turn the primary request into a 500.
     */
    protected function bestEffort(\Closure $callback): void
    {
        try {
            $callback();
        } catch (\Throwable $e) {
            report($e);
        }
    }
}

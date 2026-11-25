<?php

namespace Codeart\Core\Auth\Http;

use Illuminate\Http\Response;

/** Behind `admin.access`: 204 when the user may use the admin, 403 otherwise (probe for screens and tests). */
class AccessCheckController
{
    public function __invoke(): Response
    {
        return response()->noContent();
    }
}

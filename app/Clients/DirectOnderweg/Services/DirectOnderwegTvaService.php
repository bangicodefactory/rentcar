<?php

namespace App\Clients\DirectOnderweg\Services;

use App\Services\DefaultTvaService;

class DirectOnderwegTvaService extends DefaultTvaService
{
    // 20 % TVA (Netherlands / standard EU rate). Override $rate here
    // if the jurisdiction ever changes, or add extra logic as needed.
}

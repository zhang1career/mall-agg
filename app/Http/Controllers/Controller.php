<?php

namespace App\Http\Controllers;

use App\Http\Concerns\LogsHandledApiRequests;

abstract class Controller
{
    use LogsHandledApiRequests;
}

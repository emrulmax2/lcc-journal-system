<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    // $this->authorize() in the editorial controllers. Laravel 12's skeleton leaves the
    // base controller empty, so the trait has to be opted into here.
    use AuthorizesRequests;
}

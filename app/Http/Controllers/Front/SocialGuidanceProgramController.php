<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;

class SocialGuidanceProgramController extends Controller
{
    public function __invoke()
    {
        return view('front.programs.social-guidance');
    }
}

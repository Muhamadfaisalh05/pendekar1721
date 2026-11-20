<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\MasterTraining;

class SocialGuidanceProgramController extends Controller
{
    public function __invoke()
    {
        $training = MasterTraining::query()
            ->whereRaw('LOWER(title) = ?', ['bimbingan sosial'])
            ->first();

        return view('front.programs.social-guidance', [
            'training' => $training,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\MasterTraining;

class SewingProgramController extends Controller
{
    public function __invoke()
    {
        $training = MasterTraining::query()
            ->whereRaw('LOWER(title) = ?', ['menjahit'])
            ->first();

        return view('front.programs.sewing', [
            'training' => $training,
        ]);
    }
}

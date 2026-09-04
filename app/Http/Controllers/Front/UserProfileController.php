<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserProfileController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        $slug = $request->route('slug');

        // Use regex to match and capture the number at the end
        preg_match('/-(\d+)$/', $slug, $matches);

        if (isset($matches[1]) === false) {
            abort(404);
        }

        $userId = $matches[1];

        $user = User::query()
            ->where('id', '=', (int) $userId)
            ->where('user_type', '=', 'client')
            ->firstOrFail();

        return view('front.profile', ['user' => $user]);
    }
}

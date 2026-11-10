<?php

namespace App\Http\Controllers\Front;

use App\Enums\UserStatus;
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
        $slug = (string) $request->route('slug');

        // Use regex to match and capture the number at the end
        preg_match('/-(\d+)$/', $slug, $matches);

        if (isset($matches[1]) === false) {
            abort(404);
        }

        $userId = $matches[1];

        // Hardening: hanya profil berstatus Published yang boleh tampil publik.
        // Profil Draft/Archived tidak boleh diakses langsung via URL (IDOR/enumerasi),
        // konsisten dengan filter pada HomeController.
        $user = User::query()
            ->where('id', '=', (int) $userId)
            ->where('user_type', '=', 'client')
            ->where('status', '=', UserStatus::Published)
            ->firstOrFail();

        return view('front.profile', ['user' => $user]);
    }
}


<?php

namespace App\Http\Controllers\Front;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\MasterCity;
use App\Models\MasterEducationDegree;
use App\Models\MasterTraining;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        $cityId = $request->get('city_id');
        $trainingId = $request->get('training_id');
        $hasCurrentJob = $request->get('has_current_job');
        $gender = $request->get('gender');
        $educationDegreeId = $request->get('education_degree_id');

        $users = User::query()
            ->where('user_type', '=', 'client')
            ->where('status', '=', UserStatus::Published)
            ->when($cityId, function (Builder $query, $cityId) {
                return $query->whereHas('userWorkLocations', function (Builder $query) use ($cityId) {
                    $query->where('id', $cityId);
                });
            })
            ->when($trainingId, function (Builder $query, $trainingId) {
                return $query->whereHas('userTrainings', function (Builder $query) use ($trainingId) {
                    $query->where('id', $trainingId);
                });
            })
            ->when($hasCurrentJob !== null, function (Builder $query) use ($hasCurrentJob) {
                if ($hasCurrentJob == '1') {
                    return $query->whereHas('userExperiences', function (Builder $query) {
                        $query->where('is_current_job', true);
                    });
                } else {
                    return $query->whereDoesntHave('userExperiences', function (Builder $query) {
                        $query->where('is_current_job', true);
                    });
                }
            })
            ->when($gender, function (Builder $query) use ($gender) {
                return $query->whereHas('userProfile', function (Builder $query) use ($gender) {
                    $query->where('gender', $gender);
                });
            })
            ->when($educationDegreeId, function (Builder $query) use ($educationDegreeId) {
                return $query->whereHas('userProfile', function (Builder $query) use ($educationDegreeId) {
                    $query->where('education_degree_id', $educationDegreeId);
                });
            })
            ->with('userProfile')
            ->orderBy('name')
            ->paginate(12);

        $trainings = MasterTraining::query()
            ->orderBy('title')
            ->get();

        $cities = MasterCity::query()
            ->orderBy('title')
            ->get();

        $educationDegrees = MasterEducationDegree::query()
            ->orderBy('title')
            ->get();

        // Calculate stats
        $totalClients = User::query()
            ->where('user_type', '=', 'client')
            ->where('status', '=', UserStatus::Published)
            ->count();

        $totalClientsWorking = User::query()
            ->where('user_type', '=', 'client')
            ->where('status', '=', UserStatus::Published)
            ->whereHas('userExperiences', function (Builder $query) {
                $query->where('is_current_job', true);
            })
            ->count();

        $totalTrainings = MasterTraining::query()->count();

        return view('welcome', [
            'users' => $users,
            'trainings' => $trainings,
            'cities' => $cities,
            'educationDegrees' => $educationDegrees,
            'totalClients' => $totalClients,
            'totalClientsWorking' => $totalClientsWorking,
            'totalTrainings' => $totalTrainings
        ]);
    }
}

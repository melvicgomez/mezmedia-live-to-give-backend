<?php

namespace App\Http\Controllers;

use App\Helpers\CollectionHelper;
use App\Models\BcoinLog;
use App\Models\Charity;
use App\Models\FavoriteUsers;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RankingController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $user_id = auth()->user()->user_id;
        $per_page = !is_null($request->per_page) ? (int) $request->per_page : 50;
        $is_current = !is_null($request->is_current) ? $request->is_current : 1;

        $users = User::withSum(['bcoinTotal' => function ($query) {
            $query->where('amount', '>', 0);
        }], 'amount')
            ->withSum(['bcoinTotal as bcoin_total_rank' => function ($query) use ($is_current, $request) {
                $query
                    ->where('amount', '>', 0);
                if ($request->duration == "month") {
                    $query->whereBetween(
                        'created_at',
                        [
                            now()->subMonths($is_current == 1 ? 0 : 1)->startOfMonth()->format('Y-m-d'),
                            now()->subMonths($is_current == 1 ? 0 : 1)->endOfMonth()->format('Y-m-d')
                        ]
                    );
                } else if ($request->duration == "week") {
                    $query->whereBetween(
                        'created_at',
                        [
                            now()->subWeeks($is_current == 1 ? 0 : 1)->startOfWeek()->format('Y-m-d'),
                            now()->subWeeks($is_current == 1 ? 0 : 1)->endOfWeek()->format('Y-m-d')
                        ]
                    );
                }
            }], 'amount')

            ->withCount(['favoriteUsers as is_favorite' => function ($query) use ($user_id) {
                $query->where('user_id', $user_id);
            }]);

        if ($request->scope == "local") {
            $users->where('country_code', $request->country_code);
        }

        $users->where('privilege', 'user');
        $users->where('is_verified', 1);

        $users
            ->orderBy('bcoin_total_rank', 'desc') // sort users by their bcoin_total_rank
            ->orderBy('first_name', 'asc') // then sort alphabetically for similar bcoin_total_rank
            ->orderBy('last_name', 'asc');


        $usersWithRanking = $users
            ->get()
            ->map(function ($user, $key) {
                $user->ranking = $key + 1;
                return $user;
            });

        $bcoinOverall = $usersWithRanking->sum('bcoin_total_sum_amount');

        $alreadyFavoriteIn = $usersWithRanking
            ->filter(function ($user) {
                return $user->is_favorite == 1;
            });

        $extraFavorites = FavoriteUsers::with(['favoriteUser' => function ($query) use ($user_id) {
            $query
                ->withCount(['favoriteUsers as is_favorite' => function ($query) use ($user_id) {
                    $query->where('user_id', $user_id);
                }])
                ->withSum(['bcoinTotal' => function ($query) {
                    $query->where('amount', '>', 0);
                }], 'amount');
        }])
            ->where('user_id', $user_id)
            ->whereNotIn('favorite_user_id', $alreadyFavoriteIn->pluck('user_id'))
            ->get()
            ->toArray();

        $tempFave = [];
        foreach ($extraFavorites as $item) {
            $temp = $item['favorite_user'];
            $temp['ranking'] = null;
            $temp['is_ghost'] = 1;
            array_push(
                $tempFave,
                $temp
            );
        }

        $favoriteUsers = collect(array_merge(
            $alreadyFavoriteIn->sortBy([
                ['ranking', 'asc'],
                ['first_name', 'asc'],
                ['last_name', 'asc'],
            ])->toArray(),
            collect($tempFave)->sortBy([
                ['first_name', 'asc'],
                ['last_name', 'asc'],
            ])->toArray()
        ));




        if (!is_null($request->search)) {
            $usersWithRanking = $usersWithRanking->filter(function ($user) use ($request) {
                $found = false;
                $keywords = explode(" ", $request->search);
                $user_name = strtolower($user->first_name . " " . $user->last_name);

                // uncomment if want to include country in search
                // $user_name = strtolower($user->first_name . " " . $user->last_name . " " . $user->country_code);
                foreach ($keywords as $keyword) {
                    // check each word from keywords if it exists in the $user_name
                    if (strpos($user_name, strtolower($keyword)) !== false) {
                        // break the loop if keyword found a match
                        $found = true;
                        break;
                    }
                }
                return $found;
            });
        }

        $favorites = collect([
            'bcoin_overall' => $bcoinOverall,
            'favorite_users' => $favoriteUsers->values(),
        ]); // create a collection to add in the response
        return $favorites->merge(CollectionHelper::paginate($usersWithRanking, $per_page));
    }

    public function getOverallRankByCountry(Request $request)
    {

        $is_current = !is_null($request->is_current) ? $request->is_current : 1;
        $scope = !is_null($request->scope) ? $request->scope : "country";

        $users = User::where('is_verified', 1)->where('privilege', 'user')
            ->withSum(['bcoinTotal' => function ($query) use ($is_current, $request) {
                $query
                    ->where('amount', '>', 0);
                if ($request->duration == "month") {
                    $query->whereBetween(
                        'created_at',
                        [
                            now()->subMonths($is_current == 1 ? 0 : 1)->startOfMonth()->format('Y-m-d'),
                            now()->subMonths($is_current == 1 ? 0 : 1)->endOfMonth()->format('Y-m-d')
                        ]
                    );
                } else if ($request->duration == "week") {
                    $query->whereBetween(
                        'created_at',
                        [
                            now()->subWeeks($is_current == 1 ? 0 : 1)->startOfWeek()->format('Y-m-d'),
                            now()->subWeeks($is_current == 1 ? 0 : 1)->endOfWeek()->format('Y-m-d')
                        ]
                    );
                }
            }], 'amount')
            ->whereNotNull('country_code')
            ->get()
            ->groupBy($scope == "country" ? 'country_code' : 'business_area');

        $bcoinOverall = 0;
        $country_ranks = $users->map(function ($item) use ($request, &$bcoinOverall) {
            $bcoinOverall  += $item->sum('bcoin_total_sum_amount');
            if ($request->is_average)
                return $item->avg(function ($product) {
                    return $product['bcoin_total_sum_amount'] ? $product['bcoin_total_sum_amount'] : 0;
                });
        });

        $finalRanking = [];
        foreach ($country_ranks as $key => $value) {
            array_push($finalRanking,  [
                "key" => $key,
                "bcoin_total" => ceil($value),
            ]);
        }

        usort(
            $finalRanking,
            function ($a, $b) {
                return $a['bcoin_total'] <  $b['bcoin_total'];
            }
        );

        return [
            "bcoin_overall" => $bcoinOverall,
            "data" =>  $finalRanking
        ];
    }
}

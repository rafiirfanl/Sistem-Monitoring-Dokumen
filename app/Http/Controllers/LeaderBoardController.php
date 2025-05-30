<?php

namespace App\Http\Controllers;

use App\Models\Score;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\UserBadge;
use App\Models\DokumenDitugaskan;
use App\Models\Gamifikasi;
use App\Models\LeaderBoard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LeaderBoardController extends Controller
{
    public function index()
    {
        $tahun_aktif=TahunAjaran::tahunAktif()->first();
        $tahun_ajaran=TahunAjaran::orderBy('tahun_ajaran', 'desc')->get();

        $users=User::with(['score' => function($query) {
            $query->with(['scoreable' => function($query) {
                $query->with('dokumen_ditugaskan');
            }])->scoreTahun(request('tahun_ajaran'));
        }])->withTrashed()->whereHas('dosen_kelas', function($query) {
            $query->kelasTahun(request('tahun_ajaran'));
        })->where('role', '!=', 'admin')->get();

        // dd($users);

        $leaderboards=Gamifikasi::showRank($users);
        // dd($leaderboards[0]->score[0]->scoreable->nama_matkul);

       return view('user.leaderboard.tampil-leaderboard', ['tahun_aktif' => $tahun_aktif, 'tahun_ajaran' => $tahun_ajaran, 'leaderboards' => $leaderboards]);
    }

    public function showDetailScore($id_dosen) {
        

        $user=User::with(['score' => function($query) {
            $query->with(['scoreable' => ['dokumen_ditugaskan'], 'kelas' => ['matkul']])
            ->scoreTahun(request('tahun_ajaran'))->orderBy('updated_at', 'desc');
        }])->withTrashed()->where('id', $id_dosen)->get();
        $detail=Gamifikasi::showRank($user);
        // dd($detail[0]);
        return view('user.leaderboard.detail-score', ['detail' => $detail[0]]);
    }

    public function showRankandScore() {
        $tahun_aktif=TahunAjaran::tahunAktif()->first();
        $tahun_ajaran=TahunAjaran::orderBy('tahun_ajaran', 'desc')->get();

        $users=User::with(['score' => function($query) {
                $query->with(['scoreable' => ['dokumen_ditugaskan'], 'kelas' => ['matkul']])
                    ->scoreTahun(request('tahun_ajaran'))->orderBy('updated_at', 'desc');
            }])->withTrashed()->whereHas('dosen_kelas', function($query) {
                $query->kelasTahun(request('tahun_ajaran'));
            })->where('role', '!=', 'admin')->get();

        $leaderboards=Gamifikasi::showRank($users);
        
        $detail=null;
        foreach ($leaderboards as $leaderboard) {
            if($leaderboard->user->id == Auth::user()->id) {
                $detail=$leaderboard;
                break;
            }
        }

        // dd($detail);

        return view('dosen.peringkat-score', ['tahun_aktif' => $tahun_aktif, 'tahun_ajaran' => $tahun_ajaran, 'detail' => $detail, 'leaderboards' => $leaderboards]);

    }

    public function resultBadge(Request $request) {
        if(Gamifikasi::storeResultBadge($request->id_tahun_ajaran)) {

            return redirect('/badge')->with('success', 'Berhasil memberikan badge');
        }
    }

    // public function showResultBadges() {
    //     $tahun_aktif=TahunAjaran::tahunAktif()->first();
    //     $tahun_ajaran=TahunAjaran::orderBy('id_tahun_ajaran', 'desc')->get();
    //     $users=User::with(['user_badge' => function($query) use ($tahun_aktif) {
    //         $query->where('id_tahun_ajaran', (request('tahun_ajaran') ?? $tahun_aktif->id_tahun_ajaran));
    //         }, 'score' => function($query) {
    //         $query->scoreTahun(request('tahun_ajaran'));
    //     }])->whereHas('dosen_kelas', function($query) {
    //         $query->kelasTahun(request('tahun_ajaran'));
    //     })->where('role', '!=', 'admin')->get();

    //     $user_badges=showRank($users);
    //     // dd($user_badges);

    //     return view('user.leaderboard.tampil-perolehan-badge', ['tahun_aktif' => $tahun_aktif, 'tahun_ajaran' => $tahun_ajaran, 'user_badges' => $user_badges]);
    // }

    public function showResultBadge() {
        $tahun_aktif=TahunAjaran::tahunAktif()->first();
        $tahun_ajaran=TahunAjaran::orderBy('id_tahun_ajaran', 'desc')->get();
        $users=LeaderBoard::with(['user' => function($query) use($tahun_aktif) {
            $query->with(['user_badge' => function($query) use ($tahun_aktif) {
                $query->where('id_tahun_ajaran', (request('tahun_ajaran') ?? $tahun_aktif->id_tahun_ajaran));
            }])->withTrashed();
        }])->leaderboardTahun(request('tahun_ajaran'))->orderBy('skor', 'desc')->get();
        // dd(count($users !=0));

        return view('user.leaderboard.tampil-perolehan-badge', ['tahun_aktif' => $tahun_aktif, 'tahun_ajaran' => $tahun_ajaran, 'users' => $users]);
    }


    public function deleteBadge(Request $request) {
        DokumenDitugaskan::where('id_tahun_ajaran', $request->id_tahun_ajaran)->update(['pengumpulan' => 1]);
        LeaderBoard::where('id_tahun_ajaran', $request->id_tahun_ajaran)->delete();
        UserBadge::where('id_tahun_ajaran', $request->id_tahun_ajaran)->delete();
        Score::whereHas('scoreable', function($query) {
            $query->whereNull('file_dokumen')->whereDoesntHave('note');
        })->scoreTahun($request->tahun_ajaran)->update(['poin' => null]);
        Score::whereHas('scoreable', function($query) {
            $query->whereHas('note');
        })->scoreTahun($request->tahun_ajaran)->update(['poin' => Gamifikasi::getPointDokumenSalah()]);
        return redirect('/badge')->with('success', 'Berhasil menghapus badge');
    }
}
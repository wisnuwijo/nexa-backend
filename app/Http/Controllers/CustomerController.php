<?php

namespace App\Http\Controllers;

use App\Models\Aktivitas;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|unique:users,username',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'level' => 'required|exists:tabel_level,id',
            'gender' => 'required',
        ]);
        $user = new User();
        $user->name = $request->name;
        $user->username = $request->username;
        $user->email = $request->email;
        $user->password = Hash::make($request->password);
        $user->kode_customer = auth()->user()->kode_customer;
        $user->id_level = $request->level;
        $user->akun_aktif = 1;
        $user->gender = $request->gender;
        $user->created_by = auth()->user()->id;
        $user->save();
        $aktivitas = Aktivitas::create([
            'aktivitas_by' => auth()->user()->id,
            'aktivitas_name' => 'Menambahkan pengguna baru dengan username: '.$request->username ,
            'tanggal' => now(),
            'created_by' => auth()->user()->id,
            'created_at' => now(),
        ]);
        return response()->json([
            'message' => 'User berhasil ditambahkan',
            'data' => $user,
        ],201);
    }
    public function update(Request $request)
    {
        $user = User::find($request->id);
        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }
        $request->validate([
            'name' => 'required|string|max:255',
            'username' => [
                'required',
                Rule::unique('users')->ignore($user->id)
            ],
            'email' => [
                'required',
                'email',
                Rule::unique('users')->ignore($user->id)
            ],
            'level' => 'required|exists:tabel_level,id',
            'foto_profile' => 'nullable|image|mimes:jpeg,png,jpg', // max 2MB
        ]);

        $user->name = $request->name;
        $user->username = $request->username;
        $user->email = $request->email;
        $user->id_level = $request->level;
        $user->updated_by = auth()->user()->id;
        if ($request->hasFile('foto_profile')) {
        // Hapus foto lama jika ada
            if ($user->image && Storage::exists($user->image)) {
                Storage::delete($user->image);
            }

            $file = $request->file('foto_profile');
            $extension = $file->getClientOriginalExtension();
            // Buat nama unik: userID_timestamp.ext
            $filename = 'foto_' . $user->id . '_' . time() . '.' . $extension;

            // Simpan di folder 'public/foto_profil'
            $path =  $file->storeAs('foto_profil', $filename, 'public');

            // Simpan path ke database
            $user->image = $path;
        }
        $user->save();
        $aktivitas = Aktivitas::create([
            'aktivitas_by' => auth()->user()->id,
            'aktivitas_name' => 'Memperbarui data pengguna dengan id : '. $request->id,
            'tanggal' => now(),
            'created_by' => auth()->user()->id,
            'created_at' => now(),
        ]);

        return response()->json([
            'message' => 'User berhasil diperbarui',
            'data' => $user,
        ], 200);
    }
    public function listUser (Request $request)
    {
        $query = User::where("kode_customer", auth()->user()->kode_customer)
                    ->where("akun_aktif", 1);
        if ($request->filled('id_level')) {
            $query->where('id_level', $request->id_level);
        }
        $user = $query->get();
        return response()->json([
            'message' => 'List User',
            'data' => $user,
        ], 200);
    }
    public function detailUser (Request $request)
    {
        $request->validate([
            'id' => 'required|exists:users,id',
        ]);
        $user = DB::table('users')
        ->leftJoin('tabel_master_customer', 'users.kode_customer', '=', 'tabel_master_customer.kode_customer')
        ->where('users.id', $request->id)
        ->where('users.akun_aktif', 1)
        ->select(
            'users.*',
            'tabel_master_customer.nama_customer'
        )
        ->first();
        return response()->json([
            'message' => 'Detail User',
            'data' => $user,
        ], 200);
    }
    public function destroy(Request $request)
    {
        $user = User::find($request->id);

        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }

        $user->delete();
        $aktivitas = Aktivitas::create([
            'aktivitas_by' => auth()->user()->id,
            'aktivitas_name' => 'Menghapus data pengguna dengan id : '. $request->id,
            'tanggal' => now(),
            'created_by' => auth()->user()->id,
            'created_at' => now(),
        ]);

        return response()->json([
            'message' => 'User berhasil dihapus'
        ], 200);
    }
    public function countUser(Request $request)
    {
        $kodeCustomer = auth()->user()->kode_customer;
        $userLevels = DB::table('tabel_level')
            ->leftJoin('users', function($join) use ($kodeCustomer) {
                $join->on('tabel_level.id', '=', 'users.id_level')
                     ->where('users.akun_aktif', 1)
                     ->whereNull('users.deleted_at')
                     ->where('users.kode_customer', $kodeCustomer);
            })
            ->select(
                'tabel_level.id',
                'tabel_level.nama_level',
                DB::raw('COUNT(users.id) as jumlah_user')
            )
            ->groupBy('tabel_level.id', 'tabel_level.nama_level')
            ->get();
        

        return response()->json([
            'message' => 'Jumlah user aktif per level',
            'data' => $userLevels,
        ], 200);
    }
}

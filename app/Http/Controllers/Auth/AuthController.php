<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function showRegisterForm() { return view('auth.register'); }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name'=>['required','string','max:255'], 'whatsapp'=>['required','string','max:30'],
            'email'=>['required','email','max:255','unique:users,email'],
            'password'=>['required','confirmed',Password::min(8)],
        ]);
        $user = DB::transaction(function () use ($data) {
            $user = User::create($data + ['branch_id'=>null]);
            $user->assignRole('Customer');
            return $user;
        });
        Auth::login($user); $request->session()->regenerate();
        return redirect()->route('customer.dashboard');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            $user = Auth::user();
            if ($user->hasRole('Customer')) return redirect()->route('customer.dashboard');
            if ($user->hasAnyRole(['Super Admin','Owner','Admin EPUL','Admin RAPLY','Finance','Produksi'])) return redirect()->route('dashboard');
            Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken();
            return back()->withErrors(['email'=>'Akun tidak memiliki role yang diizinkan.'])->onlyInput('email');
        }

        return back()->withErrors([
            'email' => 'Email atau password salah.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }
}

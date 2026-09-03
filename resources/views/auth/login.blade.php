@extends('layouts.auth')
@section('title','Masuk')
@section('content')
<span class="auth-kicker auth-mono">Welcome back</span>
<h2 class="auth-display">Masuk ke dashboard.</h2>
<p class="auth-intro">Kelola pesanan dan pantau proses produksi dari akun Anda.</p>
@if($errors->any())<div class="auth-alert" role="alert">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('login') }}" class="auth-form" x-data="{submitting:false}" @submit="if(submitting){$event.preventDefault()}else{submitting=true}">
    @csrf
    <div class="auth-field"><label for="email">Alamat email</label><div class="auth-input-wrap"><input id="email" class="auth-input" type="email" name="email" value="{{ old('email') }}" placeholder="nama@email.com" autocomplete="email" required autofocus></div>@error('email')<p class="auth-error">{{ $message }}</p>@enderror</div>
    <div class="auth-field"><label for="password">Password</label><div class="auth-input-wrap"><input id="password" class="auth-input has-action" type="password" name="password" placeholder="Masukkan password" autocomplete="current-password" required><button class="auth-toggle" type="button" data-toggle-password="password" aria-label="Tampilkan password"><svg data-eye width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6z"/><circle cx="12" cy="12" r="2.5"/></svg><svg data-eye-off class="hidden" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 3l18 18M10.6 10.6a2 2 0 002.8 2.8M9.9 4.2A10.5 10.5 0 0112 4c6 0 9.5 8 9.5 8a16 16 0 01-2.2 3.2M6.6 6.6C4 8.3 2.5 12 2.5 12s3.5 8 9.5 8a10 10 0 004-.8"/></svg></button></div>@error('password')<p class="auth-error">{{ $message }}</p>@enderror</div>
    <div class="auth-options"><label class="auth-check"><input type="checkbox" name="remember" value="1" @checked(old('remember'))><span>Ingat saya</span></label><span class="auth-mono">SECURE LOGIN</span></div>
    <button class="auth-submit" type="submit" :disabled="submitting"><span x-show="!submitting">Masuk ke Dashboard</span><span x-show="submitting">Memverifikasi...</span><span>→</span></button>
</form>
<div class="auth-switch">Belum punya akun? <a href="{{ route('register') }}">Daftar sebagai customer</a></div>
<a class="auth-back auth-mono" href="{{ url('/') }}">← KEMBALI KE BERANDA</a>
@endsection

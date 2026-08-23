<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function edit() { return view('profile.edit',['user'=>auth()->user()]); }
    public function update(Request $request) { $user=$request->user();$data=$request->validate(['name'=>'required|string|max:255','email'=>'required|email|max:255|unique:users,email,'.$user->id,'password'=>'nullable|confirmed|min:8']);if(blank($data['password']??null))unset($data['password']);else $data['password']=Hash::make($data['password']);$user->update($data);return back()->with('message','Profil berhasil diperbarui.'); }
}

<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;


class UserController extends Controller
{
	/**
	 * Display a listing of the resource.
	 */
	public function index()
	{
		//
	}

	/**
	 * Show the form for creating a new resource.
	 */
	public function create()
	{

		return view('user.create');
	}


	/**
	 * Store a newly created resource in storage.
	 */
	public function store(Request $request)
	{

		// Validate input
		$request->validate([
			'username' => 'required|string|max:255',
			'email' => 'required|email|unique:users,email',
			'password' => 'required|string|min:6|max:20',
		]);

		$user = new User;
		$user->fill($request->all());
		$user->password = Hash::make($request->password);
		$user->save();

		return redirect()->route('welcome')->with('success', 'Utilisateur créé avec succès!');
	}

	public function profile()
	{
		$user = auth()->user();
		return view('user.profile', compact('user'));
	}

	/**
	 * Show the form for editing the specified resource.
	 */
	public function edit(User $user)
	{
		return view('user.edit', ['user' => $user]);
	}

	/**
	 * Update the specified resource in storage.
	 */
	public function update(Request $request, User $user)
	{

		$request->validate([
			'username' => 'required|string|max:255',
			'email' => 'required|email|unique:users,email,' . $user->id,
		]);


		$user->username = $request->input('username');
		$user->email = $request->input('email');
		$user->save();

		return redirect()->route('user.profile')->with('success', 'Profil mis à jour avec succès!');
	}


	/**
	 * Remove the specified resource from storage.
	 */
	public function destroy(User $user)
	{
		if (auth()->user()->id !== $user->id) {
			return redirect()->route('user.profile')->withErrors('Vous ne pouvez supprimer que votre propre compte.');
		}

		$user->delete();
		Auth::logout();

		return redirect()->route('welcome')->with('success', 'Compte supprimé avec succès.');
	}


	public function forgot()
	{
		return view('user.forgot');
	}

	public function email(Request $request)
	{
		$request->validate([
			'email' => ['required', 'email', 'exists:users,email']
		]);

		$user = User::where('email', $request->email)->first();

		// Générer un token temporaire
		$tempPassword = Str::random(45);

		// Mettre à jour l'utilisateur avec le token temporaire
		$user->temp_password = Hash::make($tempPassword);
		$user->save();

		$to_name = $user->name;
		$to_email = $user->email;
		$body = "<a href='" . route('user.reset', [$user->id, $tempPassword]) . "'>Cliquez ici pour réinitialiser votre mot de passe</a>";


		Mail::send(
			'user.mail',
			['name' => $to_name, 'body' => $body],
			function ($message) use ($to_email) {
				$message->to($to_email)->subject('Réinitialisation du mot de passe');
			}
		);

		return redirect(route('login'))->with('success', 'Veuillez vérifier votre email pour réinitialiser votre mot de passe.');
	}

	public function reset(User $user, $token)
	{
		// Vérifier le token
		if (Hash::check($token, $user->temp_password)) {
			return view('user.reset', ['user' => $user, 'token' => $token]);
		}

		return redirect(route('user.forgot'))->withErrors('Token invalide ou expiré.');
	}

	public function resetUpdate(User $user, $token, Request $request)
	{
		// Vérifier le token
		if (Hash::check($token, $user->temp_password)) {
			$request->validate([
				'password' => 'required|min:6|max:20|confirmed'
			]);

			// Mettre à jour le mot de passe et réinitialiser le token temporaire
			$user->password = Hash::make($request->password);
			$user->temp_password = null;
			$user->save();

			return redirect(route('login'))->with('success', 'Mot de passe modifié avec succès.');
		}

		return redirect(route('user.forgot'))->withErrors('Token invalide ou expiré.');
	}
}

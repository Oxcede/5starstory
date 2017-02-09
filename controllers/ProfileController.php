<?php
class ProfileController extends BaseController {
	
	public function user($username){
	
		$user = User::where('username', '=', $username);
		
		if($user->count())
		{
			$user = $user->first();
		
			return View::make('profile.user', array('title' => $user->username.' Profile'))
					->with('user', $user);
		}
		
		App::missing(function($exception)
		{
			return Response::view('errors.missing', array('title'=>'404 Page Not Found', 'error'=>'User Not Found'), 404);
		});
		
		return App::abort(404);
		
	}
	
}
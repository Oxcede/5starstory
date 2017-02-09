<?php
Class LanguageController extends BaseController {

    public function select($lang)
    {
        Session::put('lang', $lang);

        if(Auth::check())
        {
            $user = Auth::user();
            $user->lang = $lang;
            $user->save();

            return Redirect::intended('/');
        }

        return Redirect::intended('/');
    }

}
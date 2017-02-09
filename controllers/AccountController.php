<?php

use Postmark\PostmarkClient;
use Postmark\Models\PostmarkException;

class AccountController extends BaseController {

    public function getSignin()
    {
        $anvard = App::make('anvard');
        $providers = $anvard->getProviders();
        return View::make('account.signin', array('title' => trans('voc.sign_in'), 'providers' => $providers));
    }

    public function postSignin()
    {
        Input::merge(array_map('trim', Input::all()));
        $validator = Validator::make(Input::all(), array(
            'email'    => 'required|email',
            'password' => 'required'
        ));

        if ($validator->fails())
        {
            return Redirect::route('account-sign-in')
                ->withErrors($validator)
                ->with('global_error', trans('error.problem_sign_in'))
                ->withInput();
        } else
        {

            $auth = Auth::attempt(array(
                'email'    => Input::get('email'),
                'password' => Input::get('password'),
                'active'   => 1
            ), Input::has('remember'));

            if ($auth)
            {
                $user = Auth::user();

                // Set user right based on role
                $rights = DB::table('roles')->where('id', '=', $user->role)->first();
                $rights ? Session::put('rights', $rights->rights) : Session::put('rights', 0);

                // Save last login time
                DB::table('users')
                    ->where('id', '=', $user->id)
                    ->update(array('last_login'=>DB::raw('now()')));

                ($user->lang) ? Session::put('lang', $user->lang) : '';

                if($user->role==4)
                {
                    return Redirect::intended('/admin/users');
                }
                else
                {
                    return Redirect::intended('/projects');
                }

/*
                $contacts = DB::table('contacts')
                    ->select('email', 'address', 'city', 'zip')
                    ->where('user_id','=',$user->id)
                    ->first();
                $message = '';

                if(!empty($contacts))
                {
                    foreach($contacts as $k => $v)
                    {
                        if(empty($v))
                        {
                            $message .= trans('voc.'.$k).' ';
                        }
                    }

                    if(!empty($message)){
                        return Redirect::route('account-edit')
                            ->with('global', trans('voc.fill_in_your').' '.$message);
                    }

                    return Redirect::intended('/projects');

                } else {
                    $message = trans('voc.email').' '.trans('voc.address').' '.trans('voc.city').' '.trans('voc.zip');
                    return Redirect::route('account-edit')
                        ->with('global',trans('voc.fill_in_your').' '.$message);
                }
*/

            }
            else
            {
                $user = User::where('email', '=', Input::get('email'))->first();

                if(empty($user))
                {
                    $error = trans('error.no_registration_for_email');
                }
                else
                {
                    if($user->active==0){ $error = trans('error.account_not_active'); }
                    else{ $error = trans('error.wrong_password'); }
                }

                return Redirect::route('account-sign-in')
                    ->with('global_error', $error)
                    ->withInput();
            }
        }

    }

    public function getSignOut()
    {
        Auth::logout();
        Session::flush();
        //return Redirect::route('account-sign-in');
        return Redirect::to('anvard/logout');
    }

    public function getCreate()
    {
        $client = new Google_Client();
        $client->setClientId($_ENV['YT_BLOG_ID']);
        $client->setClientSecret($_ENV['YT_BLOG_SECRET']);
        $client->setScopes(array(
            "https://www.googleapis.com/auth/youtube.readonly",
            "https://www.googleapis.com/auth/yt-analytics.readonly",
            "https://www.googleapis.com/auth/userinfo.email",
            "https://www.googleapis.com/auth/userinfo.profile"));
        $redirect = URL::to('google_reg_redirect');
        $client->setRedirectUri($redirect);

        $authUrl = $client->createAuthUrl();

        $ref = '';
        if(Input::get('ref'))
        {
            $ref = Input::get('ref');

        }

        return View::make('account.create', array('title' => 'Registration Page', 'authUrl'=>$authUrl, 'ref'=>$ref));
    }

    public function google_register()
    {
        $client = new Google_Client();
        $client->setClientId($_ENV['YT_BLOG_ID']);
        $client->setClientSecret($_ENV['YT_BLOG_SECRET']);
        $client->setScopes(array(
            "https://www.googleapis.com/youtube/v3/channels",
            "email",
            "profile"));
        $redirect = URL::to('google_reg_redirect');
        $client->setRedirectUri($redirect);

        // Define an object that will be used to make all API requests.
        $youtube = new Google_Service_YouTube($client);
        $oauth = new Google_Service_Oauth2($client);

        if (Input::get('code')) {
            $client->authenticate(Input::get('code'));
            Session::put('token', $client->getAccessToken());
        }

        if (Session::get('token')) {
            $client->setAccessToken(Session::get('token'));
        }

        $info = array();
        // Check to ensure that the access token was successfully acquired.
        if ($client->getAccessToken()) {

            try {
                // Call the channels.list method to retrieve information about the
                // currently authenticated user's channel.
                $channelsResponse = $youtube->channels->listChannels('contentDetails, snippet, statistics', array(
                    'mine' => 'true',
                ));

                foreach ($channelsResponse['items'] as $channel) {
                    $info['channel'] = $channel['id'];

                    $info['title'] = $channel['snippet']['title'];

                    $info['views'] = $channel['statistics']['viewCount'];

                    $info['subscriptions'] = $channel['statistics']['subscriberCount'];

                    $info['thumb'] = $channel['snippet']['thumbnails']['default']['url'];

                    $info['url'] = 'http://www.youtube.com/channel/'.$channel['id'];
                }
            } catch (Google_ServiceException $e) {
                return Redirect::route('account-create')->with('global_error', sprintf('<p>A service error occurred: <code>%s</code></p>',
                    htmlspecialchars($e->getMessage())));
            } catch (Google_Exception $e) {
                return Redirect::route('account-create')->with('global_error', sprintf('<p>An client error occurred: <code>%s</code></p>',
                    htmlspecialchars($e->getMessage())));
            }

            Session::put('token', $client->getAccessToken());

            $test_exist = DB::table('blogs')
                ->where('channel', '=', $info['channel'])
                ->select('id', 'user_id')
                ->first();

            if(!empty($test_exist))
            {
                $user = $oauth->userinfo->get();
                $email = $user->email;
                $name = $user->name;
                $password = Hash::make(time().$email);
                User::where('id', '=', $test_exist->user_id)
                    ->update(array('email'=>$email,
                                   'password'=>$password,
                                   'role'=>'2', 'active'=>'1', 'username'=>$name,
                                   'updated_at'=>DB::raw('NOW()') ));
            }
            else
            {
                $user = $oauth->userinfo->get();
                $email = $user->email;
                $name = $user->name;
                $password = Hash::make(time().$email);
                $user_id = User::insertGetId(array('email'=>$email,
                                                   'password'=>$password,
                                                   'role'=>'2', 'active'=>'1', 'username'=>$name,
                                                   'created_at'=>DB::raw('NOW()'), 'updated_at'=>DB::raw('NOW()') ));

                Blog::insert(array(
                    'user_id'=>$user_id,
                    'media_id'=>'1',
                    'lang_id'=>'2',
                    'category_id'=>'99',
                    'title'=>$info['title'],
                    'url'=>$info['url'],
                    'thumb'=>$info['thumb'],
                    'channel'=>$info['channel'],
                    'yt_subscriptions'=>$info['subscriptions']
                ));

                DB::table('mail_settings')
                    ->insert(array(
                        'user_id'   =>  $user_id
                    ));

                if($info['channel']){
                    BloggerController::update_yt_avg_views($info['channel']);
                }
            }

            return Redirect::route('account-sign-in')->with('global', 'Account registered. You can now log in.');

        } else {
            return Redirect::route('account-create')->with('global_error', 'No token');
        }
    }

    public function postCreate()
    {
        $validator = Validator::make(Input::all(),
            array(
                'email'          => 'required|max:50|email|unique:users',
                'password'       => 'required|min:6',
                'password_again' => 'required|same:password',
                'type'           => 'required',
            )
        );

        if ($validator->fails())
        {
            return Redirect::back()
                ->withErrors($validator)
                ->withInput();
        } else
        {

            $email = Input::get('email');
            $username = Input::get('un');
            $password = Input::get('password');
            $role = Input::get('type');

            $company_id = null;

            if($role==3)
            {
                if(Input::get('ref'))
                {
                    $ref = Input::get('ref');

                    $company = DB::table('companies')
                        ->where('ref', '=', $ref)
                        ->first();

                    if(!empty($company))
                    {
                        $company_id = $company->id;
                    }
                }
            }

            // Activation code
            $code = str_random(60);

            $user = User::create(array(
                'email'    => $email,
                'username' => $username,
                'password' => Hash::make($password),
                'code'     => $code,
                'role'     => $role,
                'company_id'=> $company_id,
                'active'   => 0
            ));

            if ($user)
            {
                DB::table('mail_settings')
                    ->insert(array(
                        'user_id'   =>  $user->id
                    ));
/*
                Mail::send('emails.auth.activate', array('link' => URL::route('account-activate', $code), 'username' => $username), function ($message) use ($user)
                {
                    $message->to($user->email, $user->username)->subject('Activate your account');
                });
*/
                if(Session::get('lang')=='en')
                {
                    $template = 231501;
                }
                elseif(Session::get('lang')=='ru')
                {
                    $template = 245101;
                }
                else
                {
                    $template = 245101;
                }

                try{
                    $client = new PostmarkClient($_ENV['POSTMARK']);

                    $sendResult = $client->sendEmailWithTemplate(
                        "hi@blablablogger.com",
                        $user->email,
                        $template,
                        [
                            "name" => $username,
                            "action_url" => URL::route('account-activate', $code),
                            "email" => $user->email,
                        ]);

                }catch(PostmarkException $ex){
                    // If client is able to communicate with the API in a timely fashion,
                    // but the message data is invalid, or there's a server error,
                    // a PostmarkException can be thrown.
                    /*
                    echo $ex->httpStatusCode;
                    echo $ex->message;
                    echo $ex->postmarkApiErrorCode;
                    */
                    return Redirect::back()->with('global_error', 'Error sending e-mail.');
                }

                return Redirect::route('account-sign-in')
                    ->with('global', 'Your account created. We have sent you an email with activation link.');
            }

        }

    }

    public function getActivate($code)
    {

        $user = User::where('code', '=', $code)->where('active', '=', 0);

        if ($user->count())
        {
            $user = $user->first();

            // Activate user
            $user->active = 1;
            $user->code = '';

            if ($user->save())
            {
                return Redirect::route('home')
                    ->with('global', 'Account activated. You can now sign in.');
            }
        }

        return Redirect::route('home')->with('global_error', 'Could not activate account');

    }

    public function getChangePassword()
    {
        return View::make('account.password', array('title' => 'Change password'));
    }

    public function postChangePassword()
    {

        $validator = Validator::make(Input::all(),
            array(
                'old_password'   => 'required',
                'password'       => 'required|min:6',
                'password_again' => 'required|same:password',
            )
        );

        if ($validator->fails())
        {
            return Redirect::route('account-change-password')
                ->withErrors($validator);
        }
        else
        {

            $user = User::find(Auth::user()->id);

            $old_password = Input::get('old_password');
            $password = Input::get('password');

            if (Hash::check($old_password, $user->getAuthpassword()))
            {
                $user->password = Hash::make($password);

                if ($user->save())
                {
                    return Redirect::route('home')
                        ->with('global', 'Your password has been changed');
                }

            } else
            {
                return Redirect::route('home')
                    ->with('global_error', 'Your old password is incorrect');
            }

        }

        return Redirect::route('account-change-password')
            ->with('global_error', 'Your password could not be changed');

    }

    public function getForgotPassword()
    {
        return View::make('account.forgot', array('title' => 'Forgot Password'));
    }

    public function postForgotPassword()
    {

        $validator = Validator::make(Input::all(),
            array(
                'email' => 'required|email'
            )
        );

        if ($validator->fails())
        {
            return Redirect::route('account-forgot-password')
                ->withErrors($validator)
                ->withInput();
        }
        else
        {
            $user = User::where('email', '=', Input::get('email'))->first();
            if (!empty($user))
            {
                // create new code
                $code = str_random(60);
                // update users with code
                $user->code = $code;
                // send link with code
                if ($user->save())
                {
                    /*
                    Mail::send('emails.auth.forgot', array(
                            'link' => URL::route('account-recover', $code),
                            'username' => $user->username),
                        function ($message) use ($user)
                        {
                            $message->to($user->email, $user->username)->subject('Your password reset link');
                        });
                    */
                    if(Session::get('lang')=='en')
                    {
                        $template = 224561;
                    }
                    elseif(Session::get('lang')=='ru')
                    {
                        $template = 231502;
                    }
                    else
                    {
                        $template = 231502;
                    }

                    try{
                        $client = new PostmarkClient($_ENV['POSTMARK']);

                        $sendResult = $client->sendEmailWithTemplate(
                            "hi@blablablogger.com",
                            $user->email,
                            $template,
                            [
                                "product_name" => "BlaBlaBlogger",
                                "name" => $user->username,
                                "action_url" => URL::route('account-recover', $code)
                            ]);

                    }catch(PostmarkException $ex){
                        // If client is able to communicate with the API in a timely fashion,
                        // but the message data is invalid, or there's a server error,
                        // a PostmarkException can be thrown.
                        /*
                        echo $ex->httpStatusCode;
                        echo $ex->message;
                        echo $ex->postmarkApiErrorCode;
                        */
                        return Redirect::back()->with('global_error', 'Error sending e-mail.');
                    }

                    return Redirect::route('account-sign-in')->with('global', trans('voc.password_reset_msg') );
                }
            }
            else
            {

            }


/*
            $user = User::where('email', '=', Input::get('email'));

            if ($user->count())
            {
                $user = $user->first();

                $code = str_random(60);
                $password = str_random(10);

                $user->code = $code;
                $user->password_temp = Hash::make($password);

                if ($user->save())
                {
                    Mail::send('emails.auth.forgot', array(
                        'link' => URL::route('account-recover', $code),
                        'username' => $user->username, 'password' => $password),
                        function ($message) use ($user)
                    {
                        $message->to($user->email, $user->username)->subject('Your new password');
                    });

                    return Redirect::route('home')
                        ->with('global', 'We have sent you a new password by email.');
                }

            }
*/

        }

        return Redirect::route('account-forgot-password')
            ->with('global_error', 'Could not request new password');

    }

    public function getRecover($code)
    {

        $user = User::where('code', '=', $code)->first();

        if (!empty($user))
        {
            return View::make('account.reset_pw', array('title' => 'Reset password', 'code' => $code));
        }
        else
        {
            return View::make('account.forgot', array('title' => 'Forgot Password'))->with('global_error', 'Unregistered code');
        }

        // Form for new password
        // get user from code


        /*
        $user = User::where('code', '=', $code)
            ->where('password_temp', '!=', '');

        if ($user->count())
        {
            $user = $user->first();

            $user->password = $user->password_temp;
            $user->password_temp = '';
            $user->code = '';

            if ($user->save())
            {
                return Redirect::route('home')
                    ->with('global', 'Your account has been recovered and you can sign in with your new password.');
            }

            return Redirect::route('home')
                ->with('global_error', 'Could not recover your account.');
        }
        */

    }

    /*
     *  Reset password from reset link
     */
    public function postRecover()
    {

        $validator = Validator::make(Input::all(),
            array(
                'password'       => 'required|min:6',
                'password_again' => 'required|same:password',
            )
        );
        if ($validator->fails())
        {
            return Redirect::route('account-change-password')
                ->withErrors($validator);
        }
        else
        {

            $user = User::where('code', '=', Input::get('code'))->first();

            $password = Input::get('password');

            $user->password = Hash::make($password);
            $user->code = '';

            if ($user->save())
            {
                return Redirect::route('account-sign-in')
                    ->with('global', 'Your password has been changed');
            }

        }
    }


    public function getEdit()
    {
        $user = DB::table('users')
            ->leftJoin('contacts', 'users.id', '=', 'contacts.user_id')
            ->where('users.id','=',Auth::user()->id)
            ->first();

        return View::make('account.edit', array('title'=>trans('voc.account_edit'),'user'=>$user));
    }

    public function getEdit_2()
    {
        $user = Auth::user();

        $company = DB::table('companies')
            ->where('id', '=', Auth::user()->company_id)
            ->first();

        $business_types = DB::table('business_type')
            ->get();
        foreach($business_types as $type)
        {
            $company_types[$type->id] = trans('voc.'.$type->voc);
        }

        $countries = array('1' =>   'Estonia', '2'  =>  'Russia');
        /*
        $countries = array();
        $countries_o = DB::table('countries')->get();

        foreach($countries_o as $country)
        {
            $countries[$country->id] = trans('country.'.$country->name);
        }
        */

        $currencies = array();
        $currencies_o = DB::table('currencies')
            ->get();
        foreach($currencies_o as $cur)
        {
            $currencies[$cur->id] = $cur->cur;
        }

        return View::make('account.edit-2', array('title'=>trans('voc.account_edit'), 'user'=>$user,
            'countries' =>  $countries, 'company'=> $company, 'company_types' => $company_types,
            'currencies'    =>  $currencies ));
    }

    public function getEdit_yt_blogs()
    {
        $client = new Google_Client();
        $client->setClientId($_ENV['YT_BLOG_ID']);
        $client->setClientSecret($_ENV['YT_BLOG_SECRET']);
        $client->setScopes(array("https://www.googleapis.com/auth/youtube.readonly", "https://www.googleapis.com/auth/yt-analytics.readonly"));
        $redirect = URL::route('account-edit-yt-blogs');
        $client->setRedirectUri($redirect);

        // Define an object that will be used to make all API requests.
        $youtube = new Google_Service_YouTube($client);
        /*
        if (Input::get('code')) {
            if (strval(Session::get('state')) !== strval(Input::get('state'))) {
                die('The session state did not match.');
            }

            $client->authenticate(Input::get('code'));
            Session::put('token', $client->getAccessToken());
            header('Location: ' . $redirect);
        }

        if (Session::get('token')) {
            $client->setAccessToken(Session::get('token'));
        }
        */
        if (Input::get('code')) {
            $client->authenticate(Input::get('code'));
            Session::put('token', $client->getAccessToken());
        }

        if (Session::get('token')) {
            $client->setAccessToken(Session::get('token'));
        }

        $htmlBody = '';
        $data = array();

        foreach(DB::table('langs')->get() as $v)
        {
            $yt_language[$v->id] = $v->name;
        }

        $categories = DB::table('blog_categories')
            ->get();
        $cat = array();
        foreach($categories as $c)
        {
            $cat[$c->id] = trans('category.'.$c->name);
        }

        // Check to ensure that the access token was successfully acquired.
        if ($client->getAccessToken()) {
            try {
                // Call the channels.list method to retrieve information about the
                // currently authenticated user's channel.
                $channelsResponse = $youtube->channels->listChannels('contentDetails, snippet, statistics', array(
                    'mine' => 'true',
                ));

                foreach ($channelsResponse['items'] as $channel) {

                    $data[] = BloggerController::getBloggerInfo(false, $channel['id']);

                }

                if(isset($data[0]["url"]))
                {
                    foreach($data as $c)
                    {
                        $test = DB::table('blogs')
                            ->where('channel', '=', $c['channel'])
                            ->first();

                        if(empty($test))
                        {
                            DB::table('blogs')
                                ->insert(array(
                                    'user_id' => Auth::user()->id,
                                    'media_id' => '1',
                                    'lang_id' => '1',
                                    'category_id' => '99',
                                    'title' => $c['title'],
                                    'url' => $c['url'],
                                    'thumb' => $c['thumb'],
                                    'channel' => $c['channel'],
                                    'yt_subscriptions' => $c['subscriptions'],
                                ));

                            if(Input::get('channel'))
                            {
                                BloggerController::update_yt_avg_views($c['channel']);
                            }
                        }
                        else
                        {
                            return Redirect::route('account-edit-blogs')->with('global', 'Blog already exists');
                        }

                    }

                    return Redirect::route('account-edit-blogs')->with('global', 'Blog added');
                }
                else
                {
                    return Redirect::route('account-edit-blogs')->with('global_error', 'Invalid Youtube channel');
                }

            } catch (Google_ServiceException $e) {
                $htmlBody .= sprintf('<p>A service error occurred: <code>%s</code></p>',
                    htmlspecialchars($e->getMessage()));
            } catch (Google_Exception $e) {
                $htmlBody .= sprintf('<p>An client error occurred: <code>%s</code></p>',
                    htmlspecialchars($e->getMessage()));
            }

            Session::put('token', $client->getAccessToken());
        } else {
            $state = mt_rand();
            $client->setState($state);
            Session::put('state', $state);

            $authUrl = $client->createAuthUrl();
            $htmlBody = <<<END
              <h3>Authorization Required</h3>
              <p>You need to <a href="$authUrl">authorize access</a> before proceeding.<p>
END;
        }

        return View::make('account.yt_blog', array('title' => 'Edit blogs', 'body' => $htmlBody, 'data' => $data, 'categories'=> $cat, 'yt_language' => $yt_language));
    }

    public function getEdit_blogs()
    {

        // INSTAGRAM
        $instagram = new Instagram(array(
            'apiKey'      => $_ENV['INSTAGRAM_CLIENT_ID'],
            'apiSecret'   => $_ENV['INSTAGRAM_CLIENT_SECRET'],
            'apiCallback' => $_ENV['INSTAGRAM_REDIRECT_URI']
        ));
        // create login URL
        $igloginUrl = $instagram->getLoginUrl();

        // YOUTUBE
        $client = new Google_Client();
        $client->setClientId($_ENV['YT_BLOG_ID']);
        $client->setClientSecret($_ENV['YT_BLOG_SECRET']);
        $client->setScopes(array("https://www.googleapis.com/auth/youtube", "https://www.googleapis.com/auth/yt-analytics.readonly"));
        $redirect = URL::route('account-edit-yt-blogs');
        $client->setRedirectUri($redirect);
        $state = mt_rand();
        $client->setState($state);
        Session::put('state', $state);
        $authUrl = $client->createAuthUrl();

        $yt = DB::table('blogs')
            ->where('user_id', '=', Auth::user()->id)
            ->where('media_id', '=', '1')
            ->get();

        $ig = DB::table('blogs')
            ->where('user_id', '=', Auth::user()->id)
            ->where('media_id', '=', '2')
            ->get();

        foreach(DB::table('langs')->get() as $v)
        {
            $yt_language[$v->id] = $v->name;
        }

        $categories = DB::table('blog_categories')
            ->get();
        $cat = array();
        foreach($categories as $c)
        {
            $cat[$c->id] = trans('category.'.$c->name);
        }

        return View::make('account.edit-blogs', array('title' => trans('voc.edit_blogs'), 'yt' => $yt, 'ig' => $ig,
                                                      'categories'=> $cat, 'langs' => $yt_language,
                                                      'ig_link'=>$igloginUrl, 'yt_link' => $authUrl));
    }

    public function postEdit()
    {

        $contacts = Contact::where('user_id', '=', Auth::user()->id)->first();

        if (empty($contacts[0]))
        {
            $contact = Contact::create(array(
                'user_id'    => Auth::user()->id,
                'email' => Input::get('email'),
                'mobile' => Input::get('mobile'),
                'skype' => Input::get('skype'),
                'address' => Input::get('address'),
                'city' => Input::get('city'),
                'zip' => Input::get('zip'),
            ));

            if($contact)
            {
                return Redirect::route('account-edit')
                    ->with('global', trans('voc.changes_saved'));
            }
            else
            {
                return Redirect::route('account-edit')
                    ->with('global_error', 'Couldn\'t save changes' );
            }
        }
        else
        {
            $contacts->email = Input::get('email');
            $contacts->mobile = Input::get('mobile');
            $contacts->skype = Input::get('skype');
            $contacts->address = Input::get('address');
            $contacts->city = Input::get('city');
            $contacts->zip = Input::get('zip');

            if ($contacts->save())
            {
                return Redirect::route('account-edit')
                    ->with('global', trans('voc.changes_saved'));
            }
            else{
                return Redirect::route('account-edit')
                    ->with('global_error', 'Couldn\'t save changes' );
            }
        }
    }

    public function postEdit_company()
    {
        $company = DB::table('companies')
            ->where('id','=', Auth::user()->company_id)
            ->first();

        $now = DB::raw('now()');
        if(empty($company))
        {
            $ref = DB::raw('MD5(UUID())');
            $save = DB::table('companies')
                ->insertGetId(array(
                    'user_id'       =>  Auth::user()->id,
                    'type'          =>  Input::get('company_type'),
                    'company_name'  =>  Input::get('company_name'),
                    'first_name'    =>  Input::get('company_first_name'),
                    'last_name'     =>  Input::get('company_last_name'),
                    'reg_num'       =>  Input::get('company_reg_num'),
                    'nds'           =>  Input::get('company_nds'),
                    'country_id'    =>  Input::get('company_country'),
                    'email'         =>  Input::get('company_email'),
                    'phone'         =>  Input::get('company_phone'),
                    'website'       =>  Input::get('company_website'),
                    'ref'           =>  $ref,
                    'cur_id'        =>  Input::get('company_currency'),
                    'created_at'    =>  $now,
                ));

            if($save)
            {
                if(Input::file('company_logo'))
                {
                    if(file_exists('images/company_logo/'.$save.'.png'))
                    {
                        Image::make('images/company_logo/'.$save.'.png')->destroy();
                    }
                    Image::make(Input::file('company_logo'))->resize(null, 200, function ($constraint) {
                        $constraint->aspectRatio();
                    })->save('images/company_logo/'.$save.'.png');
                }

                DB::table('users')
                    ->where('id', '=', Auth::user()->id)
                    ->update(array('company_id' => $save));

                $result = Braintree_Customer::create([
                    'firstName'     =>  Input::get('company_first_name'),
                    'lastName'      =>  Input::get('company_last_name'),
                    'company'       =>  Input::get('company_name'),
                    'email'         =>  Input::get('company_email'),
                    'phone'         =>  Input::get('company_phone'),
                    'website'       =>  Input::get('company_website')
                ]);
                if($result->success)
                {
                    DB::table('companies')
                        ->where('id', '=', $save)
                        ->update(array('bt_id'=>$result->customer->id));
                }

                return Redirect::route('account-edit-2')
                    ->with('global', trans('voc.changes_saved'));
            }
            else
            {
                return Redirect::route('account-edit-2')
                    ->with('global_error', 'Couldn\'t save changes' );
            }
        }
        else
        {
            if(Input::file('company_logo'))
            {
                if(file_exists('images/company_logo/'.$company->id.'.png'))
                {
                    Image::make('images/company_logo/'.$company->id.'.png')->destroy();
                }
                Image::make(Input::file('company_logo'))->resize(null, 200, function ($constraint) {
                    $constraint->aspectRatio();
                })->save('images/company_logo/'.$company->id.'.png');
            }
            $save = DB::table('companies')
                ->where('id', $company->id)
                ->update(array(
                    'type'          =>  Input::get('company_type'),
                    'company_name'  =>  Input::get('company_name'),
                    'first_name'    =>  Input::get('company_first_name'),
                    'last_name'     =>  Input::get('company_last_name'),
                    'reg_num'       =>  Input::get('company_reg_num'),
                    'nds'           =>  Input::get('company_nds'),
                    'country_id'    =>  Input::get('company_country'),
                    'email'         =>  Input::get('company_email'),
                    'phone'         =>  Input::get('company_phone'),
                    'website'       =>  Input::get('company_website'),
                    'cur_id'        =>  Input::get('company_currency'),
                    'updated_at'    =>  $now,
                ));
            if ($save)
            {
                if(!empty($company->bt_id))
                {
                    /* Update braintree */
                    Braintree_Customer::update($company->bt_id, array(
                        'firstName'     =>  Input::get('company_first_name'),
                        'lastName'      =>  Input::get('company_last_name'),
                        'company'       =>  Input::get('company_name'),
                        'email'         =>  Input::get('company_email'),
                        'phone'         =>  Input::get('company_phone'),
                        'website'       =>  Input::get('company_website')
                    ));
                }

                return Redirect::route('account-edit-2')
                    ->with('global', trans('voc.changes_saved'));
            }
            else{
                return Redirect::route('account-edit-2')
                    ->with('global_error', 'Couldn\'t edit changes' );
            }

        }

    }

    public function postEdit_blogs()
    {
        /* Youtube */
        if( Input::get('yt_blog_id') )
        {
            foreach(Input::get('yt_blog_id') as $blog_id)
            {
                DB::table('blogs')
                    ->where('user_id', Auth::user()->id)
                    ->where('id', '=', $blog_id)
                    ->update(array(
                        'category_id' => Input::get('yt_category'.$blog_id),
                        'lang_id' => Input::get('yt_language'.$blog_id),
                    ));
            }
        }

        /* Instagram */
        if( Input::get('ig_blog_id') )
        {
            foreach(Input::get('ig_blog_id') as $ig_blog_id)
            {
                DB::table('blogs')
                    ->where('user_id', Auth::user()->id)
                    ->where('id', '=', $ig_blog_id)
                    ->update(array(
                        'category_id' => Input::get('ig_category'.$ig_blog_id),
                        'lang_id' => Input::get('ig_language'.$ig_blog_id),
                    ));
            }
        }
        
        return Redirect::route('account-edit-blogs')
            ->with('global', trans('voc.changes_saved'));
    }

    /**
     *  Create password for blogger from ref link
     */
    public function create_blogger_ref($ref_id)
    {
        $user = User::where('ref_id', '=', $ref_id)->first();

        return View::make('account/ref_create', array('title' => trans('voc.account_edit'), 'user' => $user));
    }

    /**
     *  Update password for blogger from ref link
     */
    public function update_blogger_ref()
    {
        $validator = Validator::make(Input::all(),
            array(
                'email'          => 'required|max:50|email|unique:users',
                'un'       => 'required|max:20|min:3',
                'password'       => 'required|min:6',
                'password_again' => 'required|same:password',
            )
        );

        if ($validator->fails())
        {
            return Redirect::back()
                ->withErrors($validator)
                ->withInput();
        } else
        {
            $email = Input::get('email');
            $username = Input::get('un');
            $password = Input::get('password');

            $user = DB::table('users')
                ->where('ref_id', '=', Input::get('ref_id'))
                ->where('role', '=', '2')
                ->update(array(
                        'email'    => $email,
                        'username' => $username,
                        'password' => Hash::make($password),
                        'active'   => 1,
                        'ref_id'   => ''
                        ));

            if ($user)
            {
                return Redirect::route('account-sign-in')
                    ->with('global', 'Account updated.');
            } else {
                return Redirect::back()->with('global_error', trans('error.error'));
            }
        }
    }

    /**
     *  Get list of company members
     *  and link to invite new company members
     */
    public function add_member()
    {
        $user = Auth::user();
        if($user->role==3 || $user->role==4)
        {
            $users = User::where('company_id', '=', $user->company_id)->get();

            $company = DB::table('companies')->where('id', '=', $user->company_id)->first();

            return View::make('account.add_member', array(
                'title'=>trans('voc.company_members'),
                'users'=>$users,
                'company'=>$company
            ));
        }
        else
        {
            App::missing(function($exception)
            {
                return Response::view('errors.missing', array('title'=>'No rights to view this page', 'error' => trans('voc.ermsg_no_rights')), 404);
            });
            return App::abort(404);
        }

    }

    /**
     *  Send invite member e-mail
     */
    public function send_member_invite_email()
    {
        $user = Auth::user();
        if($user->role==3 || $user->role==4)
        {
            $company = DB::table('companies')->where('id', '=', $user->company_id)->first();

            $email = Input::get('new_member_email');

            /*
            Mail::send('emails.member.company_invite',
                array(
                    'link' => URL::to('/account/create?role=company&ref='.$company->ref),
                    'username' => $user->username,
                    'company'=>$company
                ),
                function ($message) use ($email)
                {
                    $message->to($email)->subject('Invitation to 5starstory.com');
                }
            );
            */

            if(Session::get('lang')=='en')
            {
                $template = 247604;
            }
            elseif(Session::get('lang')=='ru')
            {
                $template = 247605;
            }
            else
            {
                $template = 247605;
            }
            try{

                $company_name = $company->company_name;
                $link = URL::to('/account/create?role=company&ref='.$company->ref);

                $client = new PostmarkClient($_ENV['POSTMARK']);

                $sendResult = $client->sendEmailWithTemplate(
                    "hi@blablablogger.com",
                    $email,
                    $template,
                    [
                        "company_name" => $company_name,
                        "registration_link" => $link,
                    ]);

                return true;
            } catch (Exception $e){
                return false;
            }

            return Redirect::back()->with('global', trans('voc.email_sent'));
        }
        else
        {
            App::missing(function($exception)
            {
                return Response::view('errors.missing', array('title'=>'No rights to view this page', 'error' => trans('voc.ermsg_no_rights')), 404);
            });
            return App::abort(404);
        }
    }


    /**
     *  Get mail settings and setings page
     */
    public static function get_email_settings($user_id)
    {
        $settings = DB::table('mail_settings')
            ->where('user_id', '=', $user_id)
            ->first();

        return $settings;
    }

    public function get_email_settings_page()
    {
        $user = Auth::user();

        $settings = $this->get_email_settings($user->id);

        return View::make('account.mail', array('title' => 'E-mail settings', 'settings' => $settings));
    }

    /**
     *  Update mail settings
     */
    public function post_email_settings()
    {
        if(Auth::check())
        {
            $user = Auth::user();

            $opt1 = Input::get('opt1');
            $opt2 = Input::get('opt2');
            $opt3 = Input::get('opt3');
            $opt4 = Input::get('opt4');
            $opt5 = Input::get('opt5');
            $opt6 = Input::get('opt6');
            $opt7 = Input::get('opt7');

            try{
                DB::table('mail_settings')
                    ->where('user_id', '=', $user->id)
                    ->update(array(
                        'opt1'  =>  $opt1,
                        'opt2'  =>  $opt2,
                        'opt3'  =>  $opt3,
                        'opt4'  =>  $opt4,
                        'opt5'  =>  $opt5,
                        'opt6'  =>  $opt6,
                        'opt7'  =>  $opt7,
                    ));
            }
            catch (Exception $e)
            {
                return Redirect::back()->with('global_error', trans('error.error').' '.$e);
            }

            return Redirect::back()->with('global', trans('voc.changes_saved'));

        }
        else
        {
            return Redirect::route('account-sign-in');
        }
    }

}
<?php

use Postmark\PostmarkClient;
use Postmark\Models\PostmarkException;

class AdminController extends \BaseController {

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index()
	{
		return View::make('admin.admin', array('title' => trans('voc.admin_panel')));
	}

    /**
     * Add new blogger admin form.
     *
     * @return Response
     */
    public function getAddBlogger()
    {
        foreach(DB::table('blog_categories')->get() as $v)
        {
            $category[$v->id] = trans('category.'.$v->name);
        }
        foreach(DB::table('langs')->get() as $l)
        {
            $languages[$l->id] = $l->name;
        }

        return View::make('admin.add_blogger', array('title' => trans('voc.add_blogger'),
                'languages' =>  $languages,
                                                     'categories' => $category));
    }

    /**
     * Add new blogger to DB.
     *
     * @return Response
     */
    public function postAddBlogger()
    {
        $validator = Validator::make(Input::all(),
            array(
                'channel'         => 'required',
            )
        );
        if ($validator->fails())
        {
            return Redirect::route('admin-add-blogger')
                ->withErrors($validator)
                ->with('global_error', 'Some fields are filled incorrectly or not filled')
                ->withInput();
        } else
        {
            /* Check for dupes */
            $check = Blog::where('channel', Input::get('channel'))->first();
            if(!empty($check)){
                return Redirect::back()->withInput()->with('global_error', 'DUPE !!!');
            }

            /* Save username */
            $ref_id = DB::raw('MD5(UUID())');

            $username = Input::get('username');

            if(empty($username)){ $username = Input::get('yt_title'); }

            $user = User::create(array(
                'email'    => '',
                'username' => $username,
                'password' => '',
                'code'     => '',
                'role'     => 2,
                'active'   => 0,
                'ref_id'   => $ref_id
            ));

            if($user)
            {

                if( Input::get('yt_title') || Input::get('yt_url') )
                {

                    Blog::create(array(
                            'user_id'       => $user->id,
                            'media_id'      => '1',
                            'category_id'   => Input::get('yt_category'),
                            'lang_id'       => Input::get('language'),
                            'title'         => Input::get('yt_title'),
                            'url'           => Input::get('yt_url'),
                            'thumb'         => Input::get('yt_thumb'),
                            'price'         => Input::get('yt_price'),
                            'yt_location'   => Input::get('yt_location'),
                            'channel'       => Input::get('channel'),
                        ));

                    BloggerController::update_yt_avg_views(Input::get('channel'));
                }

                if( Input::get('ig_userid') )
                {
                    Blog::create(array(
                        'user_id'       =>  $user->id,
                        'media_id'      =>  '2',
                        'lang_id'       =>  Input::get('language'),
                        'category_id'   =>  Input::get('ig_category'),
                        'ig_user_id'    =>  Input::get('ig_userid'),
                        'ig_username'   =>  Input::get('ig_username'),
                        'ig_media'      =>  Input::get('ig_media'),
                        'ig_follows'    =>  Input::get('ig_follows'),
                        ));
                }

                return Redirect::to('admin/edit_contacts/'.$user->id)
                    ->with('global','Blogger added');

            }

        }

        return Redirect::route('admin-add-blogger')
            ->with('global_error', 'Could not save new blogger');
    }

    /**
     * Edit blog info
     *
     * @param int $user
     * @return Response
     */
    public function getEditBlogs($user)
    {
        $yt = Blog::where('user_id', '=', $user)
            ->where('media_id', '=', '1')
            ->first();

        $ig = Blog::where('user_id', '=', $user)
            ->where('media_id', '=', '2')
            ->first();

        $categories = DB::table('blog_categories')
            ->get();

        $cat = array();

        foreach($categories as $c)
        {
            $cat[$c->id] = trans('category.'.$c->name);
        }

        foreach(DB::table('langs')->get() as $l)
        {
            $languages[$l->id] = $l->name;
        }

        return View::make('admin.edit_blogs', array('title' => trans('voc.edit_blogs'), 'yt' => $yt, 'ig' => $ig,
            'languages' => $languages, 'categories'=> $cat, 'user_id' => $user));
    }

    /**
     * Update blogs.
     */
    public function postEditBlogs()
    {
        $user_id = Input::get('user_id');

        /* Youtube */
        if( Input::get('yt_title') || Input::get('yt_url') )
        {
            $yt = DB::table('blogs')
                ->where('user_id','=', $user_id)
                ->where('media_id', '=', '1')
                ->first();

            if(!isset($yt))
            {
                DB::table('blogs')
                    ->insert(array(
                        'user_id'           => $user_id,
                        'media_id'          => '1',
                        'category_id'       => Input::get('yt_category'),
                        'title'             => Input::get('yt_title'),
                        'url'               => Input::get('yt_url'),
                        'thumb'             => Input::get('yt_thumb'),
                        'lang_id'           => Input::get('yt_language'),
                        'yt_location'       => Input::get('yt_location'),
                        'channel'           => Input::get('channel'),
                    ));

                BloggerController::update_yt_avg_views(Input::get('channel'));

            }
            else
            {
                DB::table('blogs')
                    ->where('user_id', $user_id)
                    ->where('media_id', '=', 1)
                    ->update(array(
                        'category_id'   => Input::get('yt_category'),
                        'lang_id'       => Input::get('language')
                    ));

            }
        }
        else
        {
            DB::table('blogs')
                ->where('media_id', '=', 1)
                ->where('user_id', '=', $user_id)
                ->delete();
        }

        /* Instagram */
        if( Input::get('ig_userid'))
        {
            $ig = DB::table('blogs')
                ->where('user_id','=', $user_id)
                ->where('media_id', '=', '2')
                ->first();

            $category_id    =   (Input::get('ig_category'))?Input::get('ig_category'):$ig->category_id;
            $ig_user_id     =   (Input::get('ig_userid'))?Input::get('ig_userid'):$ig->ig_userid;
            $ig_username    =   (Input::get('ig_username'))?Input::get('ig_username'):$ig->ig_username;
            $ig_media       =   (Input::get('ig_media'))?Input::get('ig_media'):$ig->ig_media;
            $ig_follows     =   (Input::get('ig_follows'))?Input::get('ig_follows'):$ig->ig_follows;
            $ig_thumb       =   (Input::get('ig_thumb'))?Input::get('ig_thumb'):$ig->thumb;

            if(!isset($ig))
            {
                DB::table('blogs')
                    ->insert(array(
                        'user_id'       =>  $user_id,
                        'media_id'      =>  '2',
                        'title'         =>  Input::get('ig_username'),
                        'url'           =>  'https://instagram.com/'.Input::get('ig_username'),
                        'thumb'         =>  Input::get('ig_thumb'),
                        'lang_id'       =>  Input::get('language'),
                        'category_id'   =>  Input::get('ig_category'),
                        'ig_user_id'    =>  Input::get('ig_userid'),
                        'ig_username'   =>  Input::get('ig_username'),
                        'ig_media'      =>  Input::get('ig_media'),
                        'ig_follows'    =>  Input::get('ig_follows'),
                    ));

            }
            else
            {
                DB::table('blogs')
                    ->where('user_id', $user_id)
                    ->where('media_id', '=', 2)
                    ->update(array(
                        'lang_id'       =>      Input::get('language'),
                        'title'         =>      Input::get('ig_username'),
                        'url'           =>      'https://instagram.com/'.Input::get('ig_username'),
                        'thumb'         =>      $ig_thumb,
                        'category_id'   =>      $category_id,
                        'ig_user_id'    =>      $ig_user_id,
                        'ig_username'   =>      $ig_username,
                        'ig_media'      =>      $ig_media,
                        'ig_follows'    =>      $ig_follows,
                    ));

            }
        }
        /*
        else
        {
            DB::table('blogs')
                ->where('media_id', '=', 2)
                ->where('user_id', '=', $user_id)
                ->delete();
        }
        */
        return Redirect::route('admin-bloggers')
            ->with('global', trans('voc.changes_saved'));
    }


    /**
     * Get user contacts.
     *
     * @param int $user_id
     * @return Response
     */
    public function getEditContacts($user_id)
    {
        $contacts = Contact::where('user_id','=', $user_id)->first();

        return View::make('admin.edit_contacts', array('title' => 'Edit contacts', 'contacts' => $contacts, 'user_id' => $user_id));
    }

    /**
     * Update user contacts.
     */
    public function postEditContacts()
    {
        $user_id = Input::get('user_id');

        $contacts = Contact::where('user_id', '=', $user_id)->first();

        if (empty($contacts))
        {
            $contact = Contact::create(array(
                'user_id'   => $user_id,
                'email'     => Input::get('email'),
                'mobile'    => Input::get('mobile'),
                'skype'     => Input::get('skype'),
                'address'   => Input::get('address'),
                'city'      => Input::get('city'),
                'zip'       => Input::get('zip'),
            ));

            if($contact)
            {
                return Redirect::route('admin-users')
                    ->with('global', trans('voc.changes_saved'));
            }
            else
            {
                return Redirect::to('admin/edit_contacts/'.$user_id)
                    ->with('global_error', 'Couldn\'t save changes' );
            }
        }
        else
        {
            $contacts->email    = Input::get('email');
            $contacts->mobile   = Input::get('mobile');
            $contacts->skype    = Input::get('skype');
            $contacts->address  = Input::get('address');
            $contacts->city     = Input::get('city');
            $contacts->zip      = Input::get('zip');

            if ($contacts->save())
            {
                return Redirect::route('admin-users')
                    ->with('global', trans('voc.changes_saved'));
            }
            else{
                return Redirect::to('admin/edit_contacts/'.$user_id)
                    ->with('global_error', 'Couldn\'t save changes' );
            }
        }
    }

    /**
     * Get user personal data
     *
     * @param int $user_id
     * @return Response
     */
    public function getEditCompany($user_id)
    {
        $company = DB::table('users')
            ->leftJoin('companies', 'users.id', '=', 'companies.user_id')
            ->where('users.id', '=', $user_id)
            ->first();

        $business_types = DB::table('business_type')
            ->get();
        foreach($business_types as $type)
        {
            $company_types[$type->id] = trans('voc.'.$type->voc);
        }

        return View::make('admin.edit_company', array('title'=>trans('voc.account_edit'), 'user_id'=>$user_id, 'company'=> $company, 'company_types' => $company_types));

    }

    /**
     * Update user personal data
     *
     * @return Response
     */
    public function postEditCompany()
    {
        $user_id = Input::get('user_id');

        $company = DB::table('companies')
            ->select('type', 'company_name', 'first_name', 'last_name', 'reg_num', 'nds')
            ->where('user_id','=', $user_id)
            ->first();

        if(empty($company))
        {

            $save = DB::table('companies')
                ->insert(array(
                    'user_id' => $user_id,
                    'type' => Input::get('company_info_type'),
                    'company_name' => Input::get('company_info_name'),
                    'first_name' => Input::get('company_info_first_name'),
                    'last_name' => Input::get('company_info_last_name'),
                    'reg_num' => Input::get('company_info_reg_num'),
                    'nds'   =>  Input::get('company_info_nds'),
                ));
            if($save)
            {
                return Redirect::back()
                    ->with('global', trans('voc.changes_saved'));
            }
            else
            {
                return Redirect::back()
                    ->with('global_error', 'Couldn\'t save changes' );
            }
        }
        else
        {
            $save = DB::table('companies')
                ->where('user_id', $user_id)
                ->update(array(
                    'type'          =>  Input::get('company_info_type'),
                    'company_name'  =>  Input::get('company_info_name'),
                    'first_name'    =>  Input::get('company_info_first_name'),
                    'last_name'     =>  Input::get('company_info_last_name'),
                    'reg_num'       =>  Input::get('company_info_reg_num'),
                    'nds'           =>  Input::get('company_info_nds'),
                ));
            if ($save)
            {
                return Redirect::back()
                    ->with('global', trans('voc.changes_saved'));
            }
            else{
                return Redirect::back()
                    ->with('global_error', 'Couldn\'t save changes' );
            }

        }
    }

    /**
     * Show event log.
     *
     * @return Response
     */
    public function getLog()
    {
        $events = DB::table('eventlogs AS e')
            ->leftJoin('events AS ev', 'ev.id', '=', 'e.event_id')
            ->leftJoin('users AS u', 'u.id', '=', 'e.user_id')
            ->leftJoin('projects AS pj', 'pj.id', '=', 'e.project_id')
            ->select('e.event_id', 'ev.name', 'e.created_at AS date', 'u.username', 'pj.title AS project', 'pj.id AS project_id')
            ->orderBy('e.id', 'DESC')
            ->paginate(50);

        return View::make('admin.eventlog', array('title' => 'Event Log', 'events' => $events));
    }

    /**
     * Show list of users.
     *
     * @return Response
     */
    public function getUsers()
    {
        $sortby = Input::get('sortby');
        $order = Input::get('order');

        if ($sortby && $order) {
            $users = DB::table('users AS u')
                ->leftJoin('roles AS r', 'r.id', '=', 'u.role')
                ->leftJoin(DB::raw('(SELECT count(id) AS count, company_id FROM `projects` GROUP BY company_id) pcount'), 'pcount.company_id', '=', 'u.company_id')
                ->leftJoin('blogs AS bl', function($join){
                    $join->on('bl.user_id', '=', 'u.id');
                })
                ->select('u.id', 'u.email', 'u.username', 'r.id AS role_id', 'r.name AS role', 'u.created_at',
                    'u.ref_id', 'u.last_login', 'pcount.count AS project_count', 'bl.thumb')
                ->orderBy($sortby, $order)
                ->paginate(20);
        } else {
            $users = DB::table('users AS u')
                ->leftJoin('roles AS r', 'r.id', '=', 'u.role')
                ->leftJoin(DB::raw('(SELECT count(id) AS count, company_id FROM `projects` GROUP BY company_id) pcount'), 'pcount.company_id', '=', 'u.company_id')
                ->leftJoin('blogs AS bl', function($join){
                    $join->on('bl.user_id', '=', 'u.id');
                })
                ->select('u.id', 'u.email', 'u.username', 'r.id AS role_id', 'r.name AS role', 'u.created_at',
                    'u.ref_id', 'u.last_login', 'pcount.count AS project_count', 'bl.thumb')
                ->paginate(20);
        }

        return View::make('admin.users', array('title' => trans('voc.view_users'), 'users' => $users, 'sortby' => $sortby, 'order' => $order));
    }

    public function getBloggers()
    {
        $sortby = (Input::get('sortby'))?Input::get('sortby'):'created_at';
        $order = (Input::get('order'))?Input::get('order'):'desc';

        $query = DB::table('users')
            ->where('role', '=', DB::raw('2'));

        $q = (Input::get('q'))?Input::get('q'):null;
        $uid = (Input::get('uid'))?Input::get('uid'):null;

        if(!empty($q))
        {
            $query->where('username', 'LIKE', '%'.$q.'%')
            ->orWhere('email', 'LIKE', '%'.$q.'%');
        }
        elseif (!empty($uid))
        {
            $query->where('id', '=', $uid);
        }

        $users_o = $query->orderBy($sortby, $order)->paginate(50);

        /*
        $users_o = DB::table('users')
            ->where('role', '=', DB::raw('2'))
            ->orderBy($sortby, $order)
            ->paginate(20);
        */

        $bloggers = array();

        foreach($users_o->all() as $user)
        {
            $bloggers[$user->id]['data'] = $this->get_blogger_info($user->id);
            $bloggers[$user->id]['user'] = $user;
        }

        return View::make('admin.bloggers', array('title' => trans('voc.bloggers'),
              'bloggers' => $bloggers,
              'sortby' => $sortby,
              'order' => $order,
              'users' => $users_o
                ));

    }

    private function get_blogger_info($user_id)
    {
        $data = array();

        $blogs = DB::table('blogs')
            ->where('user_id', '=', $user_id)
            ->get();

        if(!empty($blogs))
        {
            foreach($blogs as $blog)
            {
                $data['blogs'][] = $blog;
            }
        } else { $data['blogs'] = null; }

        $projects = DB::table('project_blogger_conditions')
            ->where('user_id', '=', $user_id)
            ->get();

        if(!empty($projects))
        {
            foreach($projects as $project)
            {
                $data['projects'][] = $project;
            }
        } else { $data['projects'] = null; }

        $requests = DB::table('project_requests')
            ->where('user_id', '=', $user_id)
            ->where('status', '=', DB::raw('1'))
            ->get();

        if(!empty($requests))
        {
            foreach($requests as $request)
            {
                $data['requests'][] = $request;
            }
        } else { $data['requests'] = null; }


        return $data;
    }

    public function post_ban_blogger()
    {
        $user_id = Input::get('user_id');
        DB::table('users')
            ->where('id','=', $user_id)
            ->update(array('active'=>'0'));
        return 'ok';
    }

    public function post_activate_blogger()
    {
        $user_id = Input::get('user_id');
        DB::table('users')
            ->where('id','=', $user_id)
            ->update(array('active'=>'1'));
        return 'ok';
    }

    public function getCompanies()
    {
        $sortby = (Input::get('sortby'))?Input::get('sortby'):'created_at';
        $order = (Input::get('order'))?Input::get('order'):'desc';

        $query = DB::table('users')
            ->where('role', '=', DB::raw('3'));

        $q = (Input::get('q'))?Input::get('q'):null;
        $uid = (Input::get('uid'))?Input::get('uid'):null;

        if(!empty($q))
        {
            $query->where('username', 'LIKE', '%'.$q.'%')
                ->orWhere('email', 'LIKE', '%'.$q.'%');
        }
        elseif (!empty($uid))
        {
            $query->where('id', '=', $uid);
        }

        $users_o = $query->orderBy($sortby, $order)->paginate(50);

        /*
        $users_o = DB::table('users')
            ->where('role', '=', DB::raw('3'))
            ->orderBy($sortby, $order)
            ->paginate(20);
        */

        $companies = array();

        foreach($users_o->all() as $user)
        {
            $companies[$user->id]['data'] = $this->get_company_info($user->company_id);
            $companies[$user->id]['user'] = $user;
        }

        return View::make('admin.companies', array('title' => trans('voc.companies'),
                                                  'companies' => $companies,
                                                  'sortby' => $sortby,
                                                  'order' => $order,
                                                  'users' => $users_o
        ));

    }

    private function get_company_info($company_id)
    {
        $data = array();

        $projects = DB::table('projects')
            ->where('company_id', '=', $company_id)
            ->get();

        if(!empty($projects))
        {
            foreach($projects as $project)
            {
                $data['projects'][] = $project;

                $budgets = DB::table('budget')
                    ->where('project_id', '=', $project->id)
                    ->get();
                $data['budget'][$project->id]['to_pay_confirmed'] = 0;
                $data['budget'][$project->id]['to_pay_requested'] = 0;
                $data['budget'][$project->id]['paid'] = 0;
                if(!empty($budgets))
                {
                    foreach($budgets as $budget)
                    {
                        if( $budget->status==null )
                        {
                            if($budget->payment_agree_company==1 && $budget->payment_agree_blogger==1)
                            {
                                $data['budget'][$project->id]['to_pay_confirmed'] += $budget->youtube_payment;
                                $data['budget'][$project->id]['to_pay_confirmed'] += $budget->instagram_payment;
                            }
                            else
                            {
                                $data['budget'][$project->id]['to_pay_requested'] += $budget->youtube_payment;
                                $data['budget'][$project->id]['to_pay_requested'] += $budget->instagram_payment;
                            }
                        }
                        if( $budget->status=='p' )
                        {
                            $data['budget'][$project->id]['paid'] += $budget->youtube_payment;
                            $data['budget'][$project->id]['paid'] += $budget->instagram_payment;
                        }
                    }
                } else {
                    $data['budget'][$project->id] = null;
                }
            }
        } else {
            $data['projects'] = null;
        }

        $data['company'] = DB::table('companies')
            ->where('id', '=', $company_id)
            ->first();

        return $data;
    }

    public function post_ban_company()
    {
        $user_id = Input::get('user_id');
        DB::table('users')
            ->where('id','=', $user_id)
            ->update(array('active'=>'0'));
        return 'ok';
    }

    public function post_activate_company()
    {
        $user_id = Input::get('user_id');
        DB::table('users')
            ->where('id','=', $user_id)
            ->update(array('active'=>'1'));
        return 'ok';
    }

    public function getProjects()
    {
        $sortby = (Input::get('sortby'))?Input::get('sortby'):'created_at';
        $order = (Input::get('order'))?Input::get('order'):'desc';

        $query = DB::table('projects');

        if($pid = Input::get('pid'))
        {
            $query->where('id', '=', $pid);
        }

        $projects_o = $query->orderBy($sortby, $order)
            ->paginate(50);

        $project_data = array();

        if(!empty($projects_o))
        {
            foreach($projects_o as $p)
            {
                $project_data[$p->id] = $this->get_project_info($p);
            }
        }

        return View::make('admin.projects', array(
            'title' => trans('voc.projects'),
            'projects' => $projects_o,
            'data' => $project_data,
            'sortby' => $sortby,
            'order' => $order,
        ));
    }

    private function get_project_info($p)
    {
        $data = array();

        $data['medias'] = DB::table('project_medias')
            ->where('project_id', '=', $p->id)
            ->get();

        $data['details'] = DB::table('project_details')
            ->where('project_id', '=', $p->id)
            ->get();

        $data['requests'] = DB::table('project_requests as pr')
            ->leftJoin('users as u', 'u.id', '=', 'pr.user_id')
            ->where('project_id', '=', $p->id)
            ->where('status', '=', DB::raw('1'))
            ->get();

        $data['budget'] = DB::table('budget')
            ->where('project_id', '=', $p->id)
            ->get();

        $data['conditions'] = DB::table('project_blogger_conditions as pbc')
            ->leftJoin('users as u', 'u.id', '=', 'pbc.user_id')
            ->where('project_id', '=', $p->id)
            ->get();

        $data['company'] = DB::table('companies')
            ->where('id', '=', $p->company_id)
            ->first();

        return $data;
    }

    public function get_edit_credentials($user)
    {
        $u = User::find($user);

        return View::make('admin.edit_credentials', array('title'=>'Edit credentials', 'user'=>$u));
    }

    public function post_edit_credentials()
    {
        $validator = Validator::make(Input::all(),
            array(
                'email'          => 'required|max:50|email|unique:users',
                'password'       => 'required|min:6'
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
            $password = Input::get('password');
            $user_id = Input::get('user_id');

            try{
                DB::table('users')->where('id', '=', $user_id)->update(array(
                    'email'    => $email,
                    'password' => Hash::make($password),
                    'active'   => 1
                ));
            }catch(Exception $e){
                return Redirect::back()->with('global_error', $e);
            }
            return Redirect::route('admin-users')->with('global', trans('voc.changes_saved'));
        }
    }

    public function getOffers()
    {

        $source = (Input::get('source'))?Input::get('source'):'company';

        $q = (Input::get('q'))?Input::get('q'):null;

        if($source=='company')
        {
            $query = DB::table('project_blogger_conditions as pbc')
                ->leftJoin('users as u', 'pbc.user_id', '=', 'u.id')
                ->leftJoin('projects as p', 'p.id', '=', 'pbc.project_id')
                ->leftJoin('companies as c', 'c.id', '=', 'p.company_id')
                ->leftJoin('budget as b', 'pbc.budget_id', '=', 'b.id')
                ->where('pbc.status', '=', DB::raw('1'));

            if(!empty($q))
            {
                $query->where('p.title', 'LIKE', '%'.$q.'%')
                    ->orWhere('c.company_name', 'LIKE', '%'.$q.'%')
                    ->orWhere('u.username', 'LIKE', '%'.$q.'%');
            }

            $offers_o = $query->select('u.username as blogger_username', 'u.id as blogger_user_id', 'c.company_name', 'b.youtube_payment', 'b.instagram_payment',
            'pbc.created_at', 'pbc.project_id', 'b.cur_id', 'p.title')
                ->orderBy('pbc.id', 'desc')->paginate(20);

            return View::make('admin.offers', array('title' => 'Offers', 'offers'=>$offers_o));
        }
        elseif($source=='blogger')
        {
            $query = DB::table('project_requests as pr')
                ->leftJoin('projects as p', 'pr.project_id', '=', 'p.id')
                ->leftJoin('users as u', 'u.id', '=', 'pr.user_id')
                ->leftJoin('companies as c', 'c.id', '=', 'p.company_id')
                ->leftJoin('eventlogs as e', function($join){
                    $join->on('e.project_id', '=', 'pr.project_id')
                        ->on('e.event_id', '=', DB::raw('30'));
                })
                ->where('pr.status', '=', DB::raw('1'));

            if(!empty($q))
            {
                $query->where('p.title', 'LIKE', '%'.$q.'%')
                    ->orWhere('c.company_name', 'LIKE', '%'.$q.'%')
                    ->orWhere('u.username', 'LIKE', '%'.$q.'%');
            }

            $offers_o = $query->select('u.username as blogger_username', 'u.id as blogger_user_id', 'c.company_name', 'pr.youtube_payment', 'pr.instagram_payment',
                'e.created_at', 'pr.project_id', 'p.title', 'pr.cur_id')
                ->orderBy('pr.id', 'desc')
                ->paginate(20);

            return View::make('admin.offers', array('title' => 'Offers', 'offers'=>$offers_o));
        }
        else
        {
            return App::abort(401);
        }

    }

	/**
	 * Show the form for creating a new resource.
	 *
	 * @return Response
	 */
	public function create()
	{
		//
	}


	/**
	 * Store a newly created resource in storage.
	 *
	 * @return Response
	 */
	public function store()
	{
		//
	}


	/**
	 * Display the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function show($id)
	{
		//
	}


	/**
	 * Show the form for editing the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function edit($id)
	{
		//
	}


	/**
	 * Update the specified resource in storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function update($id)
	{
		//
	}


	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function destroy($id)
	{
		//
	}

    public function test()
    {
        /*

        $mdata = array();
        $mdata['project_id'] = 81;
        $mdata['project_title'] = 'Test project title';
        $mdata['blogger_thumb'] = 'https://yt3.ggpht.com/-MS7Fn4PLlMU/AAAAAAAAAAI/AAAAAAAAAAA/Pr7yoQFXgkc/s88-c-k-no/photo.jpg';
        $mdata['blogger_name'] = 'Test blogger name';
        $mdata['details'] = array('title', 'script', 'products');

        return View::make('emails/to_company/opt6', array('data' => $mdata));

        */

    }

    public function test_payment()
    {
        //dd(Input::all());
        $result = Braintree_Transaction::sale([
            'amount' => Input::get('amount'),
            'paymentMethodNonce' => Input::get('payment_method_nonce'),
            'options' => [
                'submitForSettlement' => True
            ],
            'customerId'  =>  '53872306'
        ]);
        echo '<pre>';
        dd($result);
    }

    public function lols()
    {
        return View::make('lols/lol', array('title'=>'LOL TESTS'));
    }

    public function chart()
    {
        $views = DB::table('blogger_stats')
            ->where('user_id', '=', '7')
            ->get();

        $labels = '';
        $avg = '';
        $subs = '';

        $a  = 1;
        $c = count($views);

        if($c>0)
        {
            foreach($views as $view)
            {
                $labels .= '"'.substr($view->date,0,10).'"';
                $avg .= $view->avg;
                $subs .= $view->subs;
                if($a<$c)
                {
                    $labels .= ',';
                    $avg .= ',';
                    $subs .= ',';
                }
                $a++;
            }
        }

        $tests = Db::table('test_mass')
            ->whereBetween('views', array('900000000', '970000000'))
            ->orderBy('subs')
            ->limit(30)
            ->get();

        $a  = 1;
        $c = count($tests);
        $testlabels = '';
        $views = '';
        if($c>0)
        {
            foreach($tests as $test)
            {
                $testlabels .= '"'.substr($test->date,0,10).'"';
                $views .= $test->views;
                if($a<$c)
                {
                    $testlabels .= ',';
                    $views .= ',';
                }
                $a++;
            }
        }

        return View::make('lols/chart', array('title'=>'CHART TESTS',
                                              'labels'=>$labels,
                                              'avg'=>$avg,
                                              'subs'=>$subs,
            'testlabels'=>$testlabels,
            'views' =>$views
        ));
    }

    /* email */
    private function laknglkang()
    {
        try{
            $client = new PostmarkClient($_ENV['POSTMARK']);
            /*
            $sendResult = $client->sendEmail("hi@blablablogger.com",
                "oxcede@hotmail.com",
                "Hello from Postmark!",
                "This is just a friendly 'hello' from your friends at Postmark.");
            */
            $sendResult = $client->sendEmailWithTemplate(
                "hi@blablablogger.com",
                "anton.ovsjankin@gmail.com",
                224561,
                [
                    "product_name" => "Le Product",
                    "name" => "BOSS",
                    "action_url" => "http://dev.blablablogger.com",
                    "sender_name" => "BlaBlaCompany",
                    "product_address_line1" => "Something",
                    "product_address_line2" => "Something else",
                ]);

        }catch(PostmarkException $ex){
            // If client is able to communicate with the API in a timely fashion,
            // but the message data is invalid, or there's a server error,
            // a PostmarkException can be thrown.
            echo $ex->httpStatusCode;
            echo $ex->message;
            echo $ex->postmarkApiErrorCode;

        }catch(Exception $generalException){
            // A general exception is thown if the API
            // was unreachable or times out.
        }
    }


}

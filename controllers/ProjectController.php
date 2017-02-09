<?php

class ProjectController extends \BaseController {

	/**
	 * Display a listing of the resource.
	 *
     * @param int $type
     *
	 * @return Response
	 */

	public function index($type = null)
	{
        /* Redirect admins
        if(Auth::user()->role==4)
        {
            return Redirect::to('/admin/bloggers');
        }*/

        $projects = null;

        foreach(DB::table('langs')->get() as $v)
        {
            $langs[$v->id] = $v->name;
        }

        if(Auth::user()->role == 3)/* Company's projects */
        {
            $type = (Input::get('type'))?Input::get('type'):0;
            $status = (Input::get('status'))?Input::get('status'):1;
            $projects = DB::table('projects AS p')
                ->leftJoin('project_details AS pd', 'pd.project_id', '=', 'p.id')
                ->leftJoin(DB::raw('(SELECT project_id, group_concat(m.name) AS media, group_concat(pm.media_id) AS media_id FROM project_medias pm LEFT JOIN medias m ON m.id=pm.media_id GROUP BY project_id) medias'), 'medias.project_id', '=', 'p.id')
                ->leftJoin(DB::raw('(SELECT project_id, group_concat(l.name) AS lang, group_concat(pl.lang_id) AS lang_ids FROM project_langs pl LEFT JOIN langs l ON l.id=pl.lang_id GROUP BY project_id) langs'), 'langs.project_id', '=', 'p.id')
                ->select('p.id', 'p.title', 'p.description', 'p.company_id', 'p.valid', 'medias.media', 'medias.media_id',
                    'langs.lang', 'langs.lang_ids', 'p.type',
                    'pd.youtube_payment', 'pd.instagram_payment', 'p.cur_id'
                )
                ->where('p.company_id', '=', Auth::user()->company_id)
                ->where('p.status', '=', $status)
                ->where(function($q) use ($type){
                    if ($type!=0) {
                        $q->where('p.type', '=', $type);
                    }
                })
                /* Add p.type Filter HERE */
                ->orderBy('p.updated_at','desc')
                ->paginate(10);

            $budget = array();
            $blogger = array();
            $post = array();
            $logs = array();
            $requests = array();
            $doughnuts = array();
            $post_views = array();
            $request_thumbs = array();
            $total_yt_budget = array();
            $total_ig_budget = array();
            $offers_budget = array();

            if(!empty($projects))
            {
                foreach($projects as $project)
                {

                    $logs[$project->id] = DB::table('eventlogs')
                        ->where('project_id', '=', $project->id)
                        ->where('sub_company_id', '=', Auth::user()->company_id)
                        ->where('company_check', '=', '0')
                        ->count();

                    $conditions = DB::table('project_blogger_conditions AS pb')
                        ->leftJoin('budget AS b', 'pb.budget_id', '=', 'b.id')
                        ->where('pb.project_id', '=', $project->id)
                        ->where('pb.blog_id', '!=', '0')
                        ->where('pb.status', '<', '6')
                        ->select('pb.id', 'b.youtube_payment', 'b.payment_agree_company', 'b.payment_agree_blogger',
                                'b.instagram_payment', 'b.cur_id', 'pb.user_id', 'pb.blog_id', 'pb.project_id', 'pb.media_id',
                                'pb.promo_format', 'pb.release_date', 'pb.release_confirmation', 'pb.status')
                        ->get();

                    /* post views */
                    $post_views[$project->id] = DB::table('posts AS p')
                        ->leftJoin(DB::raw('(SELECT * FROM post_stats p WHERE id = (SELECT max(id) FROM post_stats p2
                            WHERE p2.post_id = p.post_id) ORDER BY date DESC) AS ps'), 'ps.post_id', '=', 'p.id')
                        ->where('p.project_id', '=', $project->id)
                        ->groupBy('p.project_id')
                        ->sum('ps.views');

                    $project_requests = DB::table('project_requests AS pr')
                        ->leftJoin('blogs AS b1', function($join){
                            $join->on('b1.user_id', '=', 'pr.user_id')
                                ->on('b1.media_id', '=', DB::raw('1'));
                        })
                        ->leftJoin('blogs AS b2', function($join){
                            $join->on('b2.user_id', '=', 'pr.user_id')
                                ->on('b2.media_id', '=', DB::raw('2'));
                        })
                        ->where('project_id', '=', $project->id)
                        ->where('status', '=', '1')
                        ->select('pr.user_id', 'b1.yt_avg_views', 'b1.yt_subscriptions', 'b1.title', 'b2.ig_username',
                            'b2.ig_follows', 'b2.ig_avg_views', 'pr.youtube_payment', 'pr.instagram_payment', 'pr.cur_id')
                        ->get();

                    // Offers + confirmed
                    $offers_budget[$project->id]['youtube_payment'] = 0;
                    $offers_budget[$project->id]['instagram_payment'] = 0;

                    foreach($project_requests as $r)
                    {
                        $request_thumbs[$project->id][$r->user_id]['thumb'] = BloggerController::get_blogger_thumb($r->user_id);
                        $request_thumbs[$project->id][$r->user_id]['user_id'] = $r->user_id;
                        $request_thumbs[$project->id][$r->user_id]['blog']['title'] = $r->title;
                        $request_thumbs[$project->id][$r->user_id]['blog']['yt_avg_views'] = $r->yt_avg_views;
                        $request_thumbs[$project->id][$r->user_id]['blog']['yt_subscriptions'] = $r->yt_subscriptions;
                        $request_thumbs[$project->id][$r->user_id]['blog']['ig_follows'] = $r->ig_follows;
                        $request_thumbs[$project->id][$r->user_id]['blog']['ig_avg_views'] = $r->ig_avg_views;
                        $request_thumbs[$project->id][$r->user_id]['blog']['ig_username'] = $r->ig_username;
                        $request_thumbs[$project->id][$r->user_id]['youtube_payment'] = $r->youtube_payment;
                        $request_thumbs[$project->id][$r->user_id]['instagram_payment'] = $r->instagram_payment;
                        $request_thumbs[$project->id][$r->user_id]['cur_id'] = $r->cur_id;
                        $request_thumbs[$project->id][$r->user_id]['offer'] = 2;
                        $offers_budget[$project->id]['youtube_payment'] += $r->youtube_payment;
                        $offers_budget[$project->id]['instagram_payment'] += $r->instagram_payment;
                    }

                    $requests[$project->id]['requests'] = count($project_requests);

                    $total_yt_budget[$project->id] = 0;
                    $total_ig_budget[$project->id] = 0;

                    if(!empty($conditions))
                    {
                        foreach($conditions as $c)
                        {
                            if($c->status==1)
                            {
                                $request_thumbs[$project->id][$c->user_id]['thumb'] = BloggerController::get_blogger_thumb($c->user_id);
                                $request_thumbs[$project->id][$c->user_id]['user_id'] = $c->user_id;
                                $request_thumbs[$project->id][$c->user_id]['youtube_payment'] = $c->youtube_payment;
                                $request_thumbs[$project->id][$c->user_id]['instagram_payment'] = $c->instagram_payment;
                                $request_thumbs[$project->id][$c->user_id]['cur_id'] = $c->cur_id;
                                $request_thumbs[$project->id][$c->user_id]['offer'] = 1;
                                $offers_budget[$project->id]['youtube_payment'] += $c->youtube_payment;
                                $offers_budget[$project->id]['instagram_payment'] += $c->instagram_payment;
                            }
                            elseif($c->status!=1)
                            {
                                $doughnuts[$project->id][$c->user_id] = BloggerController::get_graph($c->user_id, $project->id);
                                $blogger[$project->id][$c->id]['data'] = $c;

                                $total_yt_budget[$project->id] += $c->youtube_payment;
                                $total_ig_budget[$project->id] += $c->instagram_payment;
                            }
                            $budget[$project->id] = $project->cur_id;

                        }

                        // offers
                        $blogs = DB::table('blogs AS b')
                            ->leftJoin('project_blogger_conditions AS pb', 'pb.blog_id', '=', 'b.id')
                            ->leftJoin('blogs AS b2', function($join){
                                $join->on('b2.user_id', '=', 'b.user_id')
                                    ->on('b2.media_id', '=', DB::raw('2'));
                            })
                            ->where('pb.project_id', '=', $project->id)
                            ->select('b.channel', 'pb.id', 'b.yt_avg_views', 'pb.user_id', 'b.yt_subscriptions', 'b.thumb', 'b.title', 'b2.ig_username', 'b2.ig_follows', 'b2.ig_avg_views', 'pb.status')
                            ->get();

                        foreach($blogs as $blog)
                        {
                            if($blog->status==1)
                            {
                                $request_thumbs[$project->id][$blog->user_id]['blog']['title'] = $blog->title;
                                $request_thumbs[$project->id][$blog->user_id]['blog']['yt_avg_views'] = $blog->yt_avg_views;
                                $request_thumbs[$project->id][$blog->user_id]['blog']['yt_subscriptions'] = $blog->yt_subscriptions;
                                $request_thumbs[$project->id][$blog->user_id]['blog']['ig_follows'] = $blog->ig_follows;
                                $request_thumbs[$project->id][$blog->user_id]['blog']['ig_username'] = $blog->ig_username;
                                $request_thumbs[$project->id][$blog->user_id]['blog']['ig_avg_views'] = $blog->ig_avg_views;
                            }
                            else
                            {
                                $blogger[$project->id][$blog->id]['blog']['thumb'] = $blog->thumb;
                                $blogger[$project->id][$blog->id]['blog']['title'] = $blog->title;
                                $blogger[$project->id][$blog->id]['blog']['yt_avg_views'] = $blog->yt_avg_views;
                                $blogger[$project->id][$blog->id]['blog']['yt_subscriptions'] = $blog->yt_subscriptions;
                                $blogger[$project->id][$blog->id]['blog']['ig_follows'] = $blog->ig_follows;
                                $blogger[$project->id][$blog->id]['blog']['ig_username'] = $blog->ig_username;
                                $blogger[$project->id][$blog->id]['blog']['ig_avg_views'] = $blog->ig_avg_views;
                            }
                        }
                    }
                    else
                    {
                        $budget[$project->id] = $project->cur_id;
                    }
                }
            }

            return View::make('projects.index',
                array( 'title' => 'Projects', 'langs' => $langs, 'projects' => $projects, 'log' =>$logs,
                        'budget' => $budget, 'blogger' => $blogger, 'post' => $post,
                        'requests' => $requests, 'graph' => $doughnuts,
                        'post_views' => $post_views, 'total_yt_budget' => $total_yt_budget,
                        'total_ig_budget' => $total_ig_budget, 'offers_budget' => $offers_budget,
                        'request_thumbs' => $request_thumbs ));
        }
        elseif(Auth::user()->role == 2)/* Blogger's projects */
        {
            if(!isset($type)){ $type = 2; }

            if($type==1)/* advertisements */
            {
                $now = DB::raw('now()');

                $projects = DB::table('projects AS p')
                    ->leftJoin('project_details AS pd', 'pd.project_id', '=', 'p.id')
                    //->leftJoin(DB::raw('(SELECT project_id, group_concat(pm.media_id) AS medias FROM project_medias pm GROUP BY project_id) medias'), 'medias.project_id', '=', 'p.id')
                    //->leftJoin('project_medias AS pm', 'pm.project_id', '=', 'p.id')
                    //->where('pm.media_id', '=', '1')
                    ->where('p.type', '=', '2')
                    ->where('p.status', '=', '1')
                    ->where('p.valid', '>=', $now)
                    ->where(function($q){
                        if(Input::get('category'))
                        {
                            $q->where('p.category_id', '=', Input::get('category'));
                        }
                    })
                    ->whereNotIn('p.id', function($query){
                        $query->select('project_id');
                        $query->from('project_blogger_conditions');
                        $query->where('user_id', '=', Auth::user()->id);
                    })
                    ->whereNotIn('p.id', function($query){
                        $query->select('project_id');
                        $query->from('project_requests');
                        $query->where('user_id', '=', Auth::user()->id);
                    })
                    ->orderBy('p.id', 'desc')
                    ->select('p.id', 'p.valid', 'pd.instagram_payment', 'pd.youtube_payment', 'p.sf_post_conditions',
                    'p.description', 'p.title', 'p.company_id', 'pd.cur_id')
                    ->paginate(30);

                $company_info = null;

                foreach($projects as $pr)
                {

                    $company_info[$pr->id] = DB::table('companies')
                        ->where('id', '=', $pr->company_id)
                        ->first();

                    $project_medias[$pr->id] = DB::table('project_medias')
                        ->where('project_id', '=', $pr->id)
                        ->get();
                }

                $cats = DB::table('projects')
                    ->select('category_id')
                    ->where('type', '=', '2')
                    ->where('status', '=', '1')
                    ->where('valid', '>=', $now)
                    ->groupBy('category_id')
                    ->get();

                $categories = array();

                foreach($cats as $cat)
                {
                    $categories[$cat->category_id] = trans('category.project'.$cat->category_id);
                }
/*
                $categories = array('1' => trans('category.project1'), '2' => trans('category.project2'), '3' => trans('category.project3'),
                                    '4' => trans('category.project4'), '5' => trans('category.project5'), '6' => trans('category.project6'),
                                    '7' => trans('category.project7'), );
*/
                return View::make('projects.index',
                    array( 'title' => 'Projects', 'langs' => $langs, 'projects' => $projects,
                           'company' => $company_info, 'type' => $type, 'categories' => $categories ));
            }
            elseif($type==2)/* my projects */
            {
                $user = Auth::user();
/*
                $projects = DB::table('projects AS p')
                    ->leftJoin('project_blogger_conditions AS pb', 'p.id', '=', 'pb.project_id')
                    ->leftJoin(DB::raw('(SELECT
                        pp.post_id, pp.project_id, pp.title, pp.title_confirmation, pp.script, pp.script_confirmation,
                        pp.promoted_product_text, pp.ppt_confirmation, pp.products, pp.products_confirmation,
                        pp.release_date, pp.release_date_confirmation, pp.video_release_confirmation,
                        p.blog_link
                        FROM project_posts AS pp
                        LEFT JOIN posts AS p ON p.id=pp.post_id
                        WHERE pp.user_id='.$user->id.') AS posts'), 'posts.project_id', '=', 'pb.project_id')
                    ->leftJoin('project_medias AS pm', function($join){ $join->on('pm.project_id', '=', 'p.id')->on('pm.media_id', '=', DB::raw('1'));})
                    ->leftJoin(DB::raw('(SELECT project_id, group_concat(pm.media_id) AS medias FROM project_medias pm GROUP BY project_id) medias'), 'medias.project_id', '=', 'p.id')
                    ->select('pb.id', 'pb.project_id', 'p.company_id', 'p.title', 'p.description', 'p.valid', 'pb.budget_id', 'p.sf_post_conditions',
                        'pm.promo_format', 'pm.release_confirmation', 'pm.start_date', 'pm.end_date', 'pb.release_date', 'pb.status',
                        'posts.post_id', 'posts.title AS post_title', 'posts.title_confirmation', 'posts.script', 'posts.script_confirmation',
                        'posts.promoted_product_text', 'posts.ppt_confirmation', 'posts.products', 'posts.products_confirmation',
                        'posts.release_date AS post_release_date', 'posts.release_date_confirmation', 'posts.video_release_confirmation',
                        'posts.blog_link', 'medias.medias')
                    ->where('pb.user_id', '=', $user->id)
                    ->where('p.status', '=', '1')
                    ->whereNotIn('pb.status', array('6, 7'))
                    ->orderBy('pb.id','desc')
                    ->paginate(10);
*/
                // project_id, project title, project_description, youtube payment, instagram payment, company name
                // offer_valid, publish date, confirmations

                $query = DB::table('projects AS p')
                    ->leftJoin('project_requests AS pr', function($join) use ($user){
                        $join->on('pr.project_id', '=', 'p.id')
                        ->on('pr.user_id', '=', DB::raw($user->id));
                    })
                    ->leftJoin('project_blogger_conditions AS pb', function($join) use ($user){
                        $join->on('pb.project_id', '=', 'p.id')
                            ->on('pb.user_id', '=', DB::raw($user->id))
                            ->on('pb.status', '<', DB::raw('6'));
                    })
                    ->leftJoin('budget AS b', 'b.id', '=', 'pb.budget_id')
                    ->leftJoin('project_details AS pd', 'pd.project_id', '=', 'p.id')
                    ->leftJoin('project_posts AS pp', function($join) use ($user){
                        $join->on('pp.project_id', '=', 'p.id')
                            ->on('pp.user_id', '=', DB::raw($user->id));
                    })
                    ->leftJoin('project_medias AS pm', function($join){ $join->on('pm.project_id', '=', 'p.id')->on('pm.media_id', '=', DB::raw('1'));})
                    ->select('pr.user_id AS r_user', 'pb.user_id AS o_user', 'p.id AS project_id', 'p.title', 'p.company_id',
                        'p.description', 'pr.youtube_payment AS r_yt_payment', 'pr.instagram_payment AS r_ig_payment',
                        'pr.blogger_post_date', 'p.deleted_at', 'pr.status AS r_status',
                        'pr.company_agree AS r_company_agree', 'pr.blogger_agree AS r_blogger_agree',
                        'b.youtube_payment AS o_yt_payment', 'b.instagram_payment AS o_ig_payment',
                        'pd.youtube_payment AS d_yt_payment', 'pd.instagram_payment AS d_ig_payment', 'pd.cur_id',
                        'b.id AS budget_id', 'pb.status', 'p.sf_post_conditions', 'p.valid',
                        'pp.title AS post_title', 'pp.title_confirmation', 'pp.script', 'pp.script_confirmation',
                        'pp.promoted_product_text', 'pp.ppt_confirmation', 'pp.products', 'pp.products_confirmation',
                        'pp.release_date', 'pp.release_date_confirmation', 'pp.video_release_confirmation', 'pm.end_date',
                        'pm.release_confirmation'
                    );

                $query->where('p.status', '=', '1')->where(function($q) use ($user){
                    (Input::get('type'))?$type = Input::get('type'):$type = 10;
                    if($type == 1){// To me
                        $q->where('pb.user_id', '=', $user->id)->where('pb.status', '=', '1');
                    }
                    elseif($type == 2){// From me
                        $q->where('pr.user_id', '=', $user->id)->whereIn('pr.status', array('1', '3'));
                    }
                    elseif($type == 3){// In work
                        $q->where('pr.user_id', '=', $user->id)->where('pb.status', '>', '1')
                            ->orWhere('pb.user_id', '=', $user->id)->where('pb.status', '>', '1');
                    }
                    else{
                        $q->where('pr.user_id', '=', $user->id)
                            ->orWhere('pb.user_id', '=', $user->id);
                    }
                });


                $projects = $query->orderBy('p.id','desc')->paginate(30);

                $budget = array();
                $company_info = array();
                $logs = array();
                if(!empty($projects[0]) || (Input::get('type') && $user->role==2))
                {
                    foreach($projects as $pr)
                    {
                        $budget[$pr->project_id] = DB::table('budget AS b')
                            ->where('b.id', '=', $pr->budget_id)
                            ->first();

                        $company_info[$pr->project_id] = DB::table('companies')
                            ->where('id', '=', $pr->company_id)
                            ->first();

                        $logs[$pr->project_id] = DB::table('eventlogs')
                            ->where('project_id', '=', $pr->project_id)
                            ->where('sub_user_id', '=', $user->id)
                            ->where('blogger_check', '=', '0')
                            ->count();
                    }

                    return View::make('projects.index',
                        array( 'title' => 'Projects', 'langs' => $langs, 'projects' => $projects, 'log' => $logs,
                               'budget' => $budget, 'company' => $company_info, 'type' => $type ));
                }
                else
                {
                    $blogs_o = DB::table('blogs')
                        ->where('user_id', '=', Auth::user()->id)
                        ->groupBy('media_id')
                        ->select('media_id')
                        ->get();

                    $blogs = array();
                    $authUrl = null;
                    $igloginUrl = null;

                    foreach($blogs_o as $b)
                    {
                        $blogs[] = $b->media_id;
                    }

                    if(empty($blogs) || !in_array(1, $blogs))
                    {
                        // YOUTUBE
                        $client = new Google_Client();
                        $client->setClientId($_ENV['YT_BLOG_ID']);
                        $client->setClientSecret($_ENV['YT_BLOG_SECRET']);
                        $client->setScopes(array("https://www.googleapis.com/auth/youtube.readonly", "https://www.googleapis.com/auth/yt-analytics.readonly"));
                        $redirect = URL::route('account-edit-yt-blogs');
                        $client->setRedirectUri($redirect);
                        $state = mt_rand();
                        $client->setState($state);
                        Session::put('state', $state);
                        $authUrl = $client->createAuthUrl();
                    }
                    if(empty($blogs) || !in_array(2, $blogs))
                    {
                        // INSTAGRAM
                        $instagram = new Instagram(array(
                            'apiKey'      => $_ENV['INSTAGRAM_CLIENT_ID'],
                            'apiSecret'   => $_ENV['INSTAGRAM_CLIENT_SECRET'],
                            'apiCallback' => $_ENV['INSTAGRAM_REDIRECT_URI']
                        ));
                        // create login URL
                        $igloginUrl = $instagram->getLoginUrl();
                    }

                    $contacts = DB::table('contacts')
                        ->where('user_id', '=', Auth::user()->id)
                        ->first();


                    return View::make('projects.no_projects', array('title' => 'Projects', 'yt_link' => $authUrl,
                                                                    'ig_link' => $igloginUrl, 'contacts' => $contacts ));
                }

            }
        }
        elseif(Auth::user()->role==4)
        {
            $now = DB::raw('now()');

            $projects = DB::table('projects AS p')
                ->leftJoin('project_details AS pd', 'pd.project_id', '=', 'p.id')
                ->where('p.type', '=', '2')
                ->where('p.status', '=', '1')
                ->where('p.valid', '>=', $now)
                ->where(function($q){
                    if(Input::get('category'))
                    {
                        $q->where('p.category_id', '=', Input::get('category'));
                    }
                })
                ->orderBy('p.id', 'desc')
                ->select('p.id', 'p.valid', 'pd.instagram_payment', 'pd.youtube_payment', 'p.sf_post_conditions',
                    'p.description', 'p.title', 'p.company_id', 'pd.cur_id')
                ->paginate(30);

            $company_info = null;

            foreach($projects as $pr)
            {

                $company_info[$pr->id] = DB::table('companies')
                    ->where('id', '=', $pr->company_id)
                    ->first();

                $project_medias[$pr->id] = DB::table('project_medias')
                    ->where('project_id', '=', $pr->id)
                    ->get();
            }

            $cats = DB::table('projects')
                ->select('category_id')
                ->where('type', '=', '2')
                ->where('status', '=', '1')
                ->where('valid', '>=', $now)
                ->groupBy('category_id')
                ->get();

            $categories = array();

            foreach($cats as $cat)
            {
                $categories[$cat->category_id] = trans('category.project'.$cat->category_id);
            }

            return View::make('projects.index',
                array( 'title' => 'Projects', 'langs' => $langs, 'projects' => $projects,
                       'company' => $company_info, 'type' => '1', 'categories' => $categories ));
        }

        App::missing(function($exception)
        {
            return Response::view('errors.missing', array('title'=>'Projects not found', 'error' => trans('voc.error')), 404);
        });
        return App::abort(404);

	}


	/**
	 * Show the form for creating a new resource.
	 *
	 * @return Response
	 */
	public function create()
	{
        $company_id = Auth::user()->company_id;
        if(!$company_id)
        {
            return Redirect::route('account-edit-2')->with('global', trans('error.no_company_info'));
        }


        $type = (Input::get('type'))?Input::get('type'):1;

        foreach(DB::table('medias')->get() as $v)
        {
            $media[$v->id] = $v->name;
        }

        foreach(DB::table('langs')->get() as $v)
        {
            $langs[$v->id] = $v->name;
        }

        $project_valid = array('1'=>trans('category.1week'), '2'=>trans('category.2weeks'), '3'=>trans('category.1month'));

        $categories = array('1' => trans('category.project1'), '2' => trans('category.project2'), '3' => trans('category.project3'),
                            '4' => trans('category.project4'), '5' => trans('category.project5'), '6' => trans('category.project6'),
                            '7' => trans('category.project7'), );

        return View::make('projects.create', array(
            'title' => trans('voc.create_new_project_title'),
            'medias' => $media,
            'langs' => $langs,
            'categories' => $categories,
            'type' =>   $type,
            'project_valid' => $project_valid
        ));
	}


	/**
	 * Store a newly created resource in storage.
	 *
	 * @return Response
	 */
	public function store()
	{

        if(Input::get('media_1') || Input::get('media_2'))
        {

        }
        else
        {
            return Redirect::route('projects.create')
                ->with('global_error', trans('voc.media_not_selected'))
                ->withInput();
        }

        $validator = Validator::make(Input::all(),
            array(
                'project_title'         =>  'required',
                'description'           =>  'required',
            )
        );

        if ($validator->fails())
        {
            return Redirect::route('projects.create')
                ->withErrors($validator)
                ->with('global_error', trans('error.form_fields'))
                ->withInput();
        }
        else
        {
/* Project documents validation*/
            if (Input::hasFile('project_doc')) {
                $all_uploads = Input::file('project_doc');

                // Make sure it really is an array
                if (!is_array($all_uploads)) {
                    $all_uploads = array($all_uploads);
                }

                // Loop through all uploaded files
                foreach ($all_uploads as $upload) {
                    // Ignore array member if it's not an UploadedFile object, just to be extra safe
                    if (!is_a($upload, 'Symfony\Component\HttpFoundation\File\UploadedFile')) {
                        continue;
                    }

                    $validator = Validator::make(
                        array('file' => $upload),
                        array('file' => 'max:20000|mimes:pdf,xls,ppt,rtx,rtf,doc,docx,xlsx,word,xl,jpeg,jpg,png,pptx,ppsx')
                    );

                    if ($validator->fails())
                    {
                        return Redirect::route('projects.create')
                            ->withErrors($validator)
                            ->with('global_error', trans('error.incorrect_files'))
                            ->withInput();
                    }
                }
            }
            $title = Input::get('project_title');
            $description = Input::get('description');
            $link = Input::get('project_link');
/*
            if(Input::get('project_valid')==1){$valid = DB::raw('DATE_ADD(NOW(),INTERVAL +7 DAY)');}
            elseif(Input::get('project_valid')==2){$valid = DB::raw('DATE_ADD(NOW(),INTERVAL +14 DAY)');}
            elseif(Input::get('project_valid')==3){$valid = DB::raw('DATE_ADD(NOW(),INTERVAL +1 MONTH)');}
            else{$valid = DB::raw('DATE_ADD(NOW(),INTERVAL +1 MONTH)');}*/
            $valid = DB::raw('DATE_ADD(NOW(),INTERVAL +3 MONTH)');

            $category = Input::get('category');

            $shipping = (Input::get('shipment'))?Input::get('shipment'):0;

            $cur = DB::table('companies')
                ->where('id', '=', Auth::user()->company_id)
                ->select('cur_id')
                ->first();

/* Saving project data */
            $project = Project::create(array(
                'title'         => $title,
                'description'   => $description,
                'link'          => $link,
                'company_id'    => Auth::user()->company_id,
                'category_id'   => $category,
                'shipping'      => $shipping,
                'cur_id'        => $cur->cur_id,
                'status'        => '1',
                'valid'         => $valid
            ));
/* New Project ID */
            $project_id = $project->id;
/* Project image */
            if(Input::file('project_image'))
            {
                $img = Image::make(Input::file('project_image'));

                $height = $img->height();

                $size = $img->filesize();

                if($size>500000 && $height>600)
                {
                    Image::make(Input::file('project_image'))->resize(null, 600, function ($constraint) {
                        $constraint->aspectRatio();
                    })->save('images/projects/'.$project_id.'.png');
                }
                else
                {
                    Image::make(Input::file('project_image'))->save('images/projects/'.$project_id.'.png');
                }

            }
/* Saving project medias  */
            if(Input::get('media_1'))/* Youtube */
            {
                DB::table('project_medias')->insert(
                    array('project_id' => $project_id, 'media_id' => 1)
                );
            }
            if(Input::get('media_2'))/* Instagram */
            {
                DB::table('project_medias')->insert(
                    array('project_id' => $project_id, 'media_id' => 2)
                );
            }
/* Saving project languages */

            DB::table('project_langs')->insert(
                array('project_id' => $project_id, 'lang_id' => Input::get('project_language') )
            );

/* Project documents upload */
            if (Input::hasFile('project_doc')) {
                $all_uploads = Input::file('project_doc');

                // Make sure it really is an array
                if (!is_array($all_uploads)) {
                    $all_uploads = array($all_uploads);
                }

                // Loop through all uploaded files
                foreach ($all_uploads as $upload) {
                    $upload->move('./documents/'.$project_id.'/', $upload->getClientOriginalName());
                }
            }

            if ($project->count())
            {
                Eventlog::create(array(
                    'user_id'       =>  Auth::user()->id,
                    'company_id'    =>  Auth::user()->company_id,
                    'event_id'      =>  1,
                    'project_id'    =>  $project_id,
                    'sub_user_id'   =>  null,
                    'post_id'       =>  null,
                ));


                return Redirect::to('projects/'.$project_id.'/bloggers/create');

            }

        }

        return Redirect::route('projects.create')
            ->with('global_error', 'Could not create project');

    }


	/**
	 * Display the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function show($id)
	{
        $project = DB::table('projects AS p')
            ->leftJoin(DB::raw('(SELECT project_id, group_concat(m.name) AS media, group_concat(pm.media_id) AS media_id FROM project_medias pm LEFT JOIN medias m ON m.id=pm.media_id GROUP BY project_id) medias'), 'medias.project_id', '=', 'p.id')
            ->leftJoin(DB::raw('(SELECT project_id, group_concat(l.name) AS lang FROM project_langs pl LEFT JOIN langs l ON l.id=pl.lang_id GROUP BY project_id) langs'), 'langs.project_id', '=', 'p.id')
            ->select('p.id', 'p.title', 'p.description', 'p.company_id', 'p.views_target', 'medias.media', 'medias.media_id', 'langs.lang')
            ->where('p.id','=', $id)
            ->get();

        if(isset($project[0])){
            return View::make('projects.show', array( 'title' => 'Projects','project' => $project[0], 'pid'=>$id ));
        }

        App::missing(function($exception)
        {
            return Response::view('errors.missing', array('title'=>'Project not found', 'error' => trans('voc.ermsg_no_project')), 404);
        });
        return App::abort(404);
	}


	/**
	 * Show the form for editing the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function edit($id)
	{
        $project = DB::table('projects AS p')
            ->leftJoin('companies AS c', 'c.id', '=', 'p.company_id')
            ->leftJoin(DB::raw('(SELECT project_id, group_concat(pm.media_id) AS media_ids FROM project_medias pm GROUP BY project_id) medias'), 'medias.project_id', '=', 'p.id')
            ->leftJoin(DB::raw('(SELECT project_id, group_concat(pl.lang_id) AS lang_ids FROM project_langs pl GROUP BY project_id) langs'), 'langs.project_id', '=', 'p.id')
            ->select('p.id', 'p.title', 'p.description', 'p.category_id', 'p.link', 'p.company_id', 'p.shipping', 'p.valid','c.company_name', 'medias.media_ids', 'langs.lang_ids')
            ->where('p.id','=',$id)
            ->orderBy('p.id','desc')
            ->first();

        foreach(DB::table('medias')->get() as $v)
        {
            $media[$v->id] = $v->name;
        }

        foreach(DB::table('langs')->get() as $v)
        {
            $langs[$v->id] = $v->name;
        }

        $categories = array('1' => trans('category.project1'), '2' => trans('category.project2'), '3' => trans('category.project3'),
                            '4' => trans('category.project4'), '5' => trans('category.project5'), '6' => trans('category.project6'),
                            '7' => trans('category.project7'), );

        /*
        $editable_test = DB::table('project_blogger_conditions')
            ->where('project_id', '=', $id)
            ->whereIn('status', array('2', '3', '4', '5'))
            ->first();

        if(empty($editable_test->status))
        {
            $editable = 1;
        }
        else
        {
            $editable = 0;
        }
        */
        $editable = 1;

        $files = File::files('./documents/'.$id);

        $deleteable = OpenProjectController::deleteable($id);

        $project_valid = array('1'=>trans('category.1week'), '2'=>trans('category.2weeks'), '3'=>trans('category.1month'));

        if(isset($project) && $project->company_id==Auth::user()->company_id)
        {
            return View::make('projects.edit', array(
                'title'         => trans('voc.edit_project'),
                'project'       => $project,
                'medias'        => $media,
                'langs'         => $langs,
                'categories'    => $categories,
                'pid'           => $id,
                'files'         => $files,
                'deleteable'    => $deleteable,
                'project_valid' => $project_valid,
                'editable'      => $editable
            ));
        } else {
            App::missing(function($exception)
            {
                return Response::view('errors.missing', array('title'=>'Project error', 'error' => trans('voc.ermsg_edit_project')), 404);
            });
            return App::abort(404);
        }
	}


	/**
	 * Update the specified resource in storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function update($id)
	{

        if(Input::get('media_1') || Input::get('media_2'))
        {

        }
        else
        {
            return Redirect::route('projects.create')
                ->with('global_error', trans('voc.media_not_selected'))
                ->withInput();
        }

        $validator = Validator::make(Input::all(),
            array(
                'project_title'         => 'required',
                'description'           => 'required',
            )
        );

        if ($validator->fails())
        {
            return Redirect::to('projects/'.$id.'/edit')
                ->withErrors($validator)
                ->with('global_error', trans('error.form_fields'))
                ->withInput();
        } else
        {
            /* Project documents */
            if (Input::hasFile('project_doc')) {
                $all_uploads = Input::file('project_doc');

                // Make sure it really is an array
                if (!is_array($all_uploads)) {
                    $all_uploads = array($all_uploads);
                }

                // Loop through all uploaded files
                foreach ($all_uploads as $upload) {
                    // Ignore array member if it's not an UploadedFile object, just to be extra safe
                    if (!is_a($upload, 'Symfony\Component\HttpFoundation\File\UploadedFile')) {
                        continue;
                    }

                    $validator = Validator::make(
                        array('file' => $upload),
                        array('file' => 'max:20000|mimes:pdf,xls,ppt,rtx,rtf,doc,docx,xlsx,word,xl,jpeg,jpg,png,pptx,ppsx')
                    );

                    if ($validator->fails())
                    {
                        return Redirect::to('projects/'.$id.'/edit')
                            ->withErrors($validator)
                            ->with('global_error', trans('error.incorrect_files'))
                            ->withInput();
                    }
                    else
                    {
                        $upload->move('./documents/'.$id.'/', $upload->getClientOriginalName());
                    }
                }
            }
            /*-----------------------------*/

            $title          = Input::get('project_title');
            $description    = Input::get('description');
            $link           = Input::get('project_link');
            /*
            if(Input::get('project_valid')==1){$valid = DB::raw('DATE_ADD(created_at,INTERVAL +7 DAY)');}
            elseif(Input::get('project_valid')==2){$valid = DB::raw('DATE_ADD(created_at,INTERVAL +14 DAY)');}
            elseif(Input::get('project_valid')==3){$valid = DB::raw('DATE_ADD(created_at,INTERVAL +1 MONTH)');}
            else{$valid = DB::raw('DATE_ADD(created_at,INTERVAL +1 MONTH)');}
            */
            $category       = Input::get('category');
            $shipping       = Input::get('shipment');

            /* Updating project data */
            $project = Project::find($id);

            if($project->company_id == Auth::user()->company_id)
            {
                $project->title         = $title;
                $project->description   = $description;
                $project->link          = $link;
                $project->category_id   = $category;
                $project->shipping      = $shipping;

                $project->save();

                if(Input::get('delete_image'))
                {
                    if(file_exists('images/projects/'.$id.'.png'))
                    {
                        unlink('images/projects/'.$id.'.png');
                    }
                }
                if(Input::file('project_image'))
                {
                    if(file_exists('images/projects/'.$id.'.png'))
                    {
                        unlink('images/projects/'.$id.'.png');
                    }
                    $img = Image::make(Input::file('project_image'));

                    $height = $img->height();

                    $size = $img->filesize();

                    if($size>500000 && $height>600)
                    {
                        Image::make(Input::file('project_image'))->resize(null, 600, function ($constraint) {
                            $constraint->aspectRatio();
                        })->save('images/projects/'.$id.'.png');
                    }
                    else
                    {
                        Image::make(Input::file('project_image'))->save('images/projects/'.$id.'.png');
                    }
                }

                $project_medias = DB::table('project_medias')->where('project_id', $id)->get();

                $yt = 0; $ig = 0;
                foreach($project_medias as $media){
                    if($media->media_id==1){ $yt = 1; }
                    if($media->media_id==2){ $ig = 1; }
                }

                /* Update project medias  */
                if(Input::get('media_1'))/* Youtube */
                {
                    if($yt==0){
                        DB::table('project_medias')->insert(
                            array('project_id' => $id, 'media_id' => 1)
                        );
                    }
                }
                else
                {
                    if($yt==1)
                    {
                        DB::table('project_medias')->where('project_id', $id)->where('media_id', '=', '1')->delete();
                    }
                }
                if(Input::get('media_2'))/* Instagram */
                {
                    if($ig==0){
                        DB::table('project_medias')->insert(
                            array('project_id' => $id, 'media_id' => 2)
                        );
                    }
                }
                else
                {
                    if($ig==1)
                    {
                        DB::table('project_medias')->where('project_id', $id)->where('media_id', '=', '2')->delete();
                    }
                }

                if ($project)
                {
                    Eventlog::create(array(
                        'user_id'       => Auth::user()->id,
                        'company_id'    =>  Auth::user()->company_id,
                        'event_id'      => 2,
                        'project_id'    => $id,
                        'sub_user_id'   => null,
                        'post_id'       => null,
                    ));

                    return Redirect::to('projects/'.$id.'/bloggers/create')
                        ->with('global', trans('voc.changes_saved'));
                }
            }
            else
            {
                return Redirect::to('projects/'.$id.'/edit')
                    ->with('global_error', trans('voc.Error updating project'));
            }

        }

		return Redirect::to('projects/'.$id.'/edit')
            ->with('global_error', trans('voc.Error updating project'));

	}


	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function destroy($id)
	{
        $test = DB::table('projects AS p')
            ->leftJoin('project_blogger_conditions AS pbc', 'pbc.project_id', '=', 'p.id')
            ->whereIn('pbc.status', array(2,3,4,5))
            ->where('p.id', '=', $id)
            ->get();

        if(empty($test) || Auth::user()->role==4)
        {
            Project::where('id', '=', $id)
                ->update(array('status'=>'2'));

            Project::destroy($id);

            if(Auth::user()->role==4)
            {
                return Redirect::back()->with('global', 'Project removed');
            }

            return Redirect::to('/projects');
        } else {
            return Redirect::back()->with('global_error', trans('error.no_delete_project'));
        }
	}

    /**
     * Add request from blogger to take project
     * */
    public function add_request($id)
    {
        if(!Input::get('task_plan') || !Input::get('post_date') || (!Input::get('youtube_payment') && !Input::get('instagram_payment')) )
        {
            return Redirect::back()->withInput()->with('global_error', 'Some fields are not filled correctly');
        }

        $project = Project::find($id);

        if($project)
        {

            $project_details = DB::table('project_details')
                ->where('project_id', '=', $project->id)
                ->first();

            if($project_details->haggle_option==1)
            {
                $youtube_payment = (Input::get('youtube_payment'))?Input::get('youtube_payment'):null;
                $instagram_payment = (Input::get('instagram_payment'))?Input::get('instagram_payment'):null;
            }else
            {
                $youtube_payment = $project_details->youtube_payment;
                $instagram_payment = $project_details->instagram_payment;
            }

            $task = Input::get('task_plan');
            $post_date = Input::get('post_date');

            $test_for_dupes = DB::table('project_requests')
                ->where('user_id', '=', Auth::user()->id)
                ->where('project_id', '=', $id)
                ->where('status', '=', DB::raw('1'))
                ->first();


            if(empty($test_for_dupes))
            {
                $save = DB::table('project_requests')
                    ->insert(array(
                        'user_id'           => Auth::user()->id,
                        'project_id'        =>  $id,
                        'youtube_payment'   =>  $youtube_payment,
                        'instagram_payment' =>  $instagram_payment,
                        'cur_id'            =>  $project->cur_id,
                        'blogger_task_plan' =>  $task,
                        'blogger_post_date' =>  $post_date,
                        'blogger_agree'     =>  '1',
                        'status'            =>  '1'
                    ));
                if($save)
                {
                    $blogger = DB::table('blogs AS b')
                        ->leftJoin('users AS u', 'u.id', '=', 'b.user_id')
                        ->where('b.user_id', '=', Auth::user()->id)
                        ->whereNotNull('b.thumb')
                        ->first();

                    $mdata = array();
                    $mdata['project_id'] = $project->id;
                    $mdata['project_title'] = $project->title;
                    $mdata['blogger_thumb'] = $blogger->thumb;
                    $mdata['blogger_name'] = $blogger->title;

                    if(!NoticeController::notice_company($project->company_id, 1, $mdata))
                    {};

                    Eventlog::create(array(
                        'user_id'       => Auth::user()->id,
                        'event_id'      => 30,
                        'project_id'    => $id,
                        'sub_company_id'=> $project->company_id,
                        'post_id'       => null,
                        'blogger_check' => 1,
                        'company_check' => 0
                    ));

                    return Redirect::back()
                        ->with('global', trans('voc.application_sent'));
                }
            } else {
                return Redirect::back();
            }
        }
        else
        {
            return App::abort(401);
        }
    }

    /**
     * Accept project request
     */
    public function accept_request()
    {
        $project_id = Input::get('project_id');
        $user_id = Input::get('user_id');
        $pr = Project::find($project_id);

        $project_details = DB::table('project_details')
            ->where('project_id', '=', $project_id)
            ->first();

        if($pr->company_id==Auth::user()->company_id)
        {
            $blog = DB::table('blogs AS b')
                ->leftJoin('project_medias AS pm', function($join) use ($project_id){
                    $join->on('b.media_id', '=', 'pm.media_id')
                        ->on('pm.project_id', '=', DB::raw($project_id));
                })
                ->where('b.user_id', '=', $user_id)
                ->select('b.id', 'b.media_id')
                ->orderBy('pm.media_id')
                ->first();

            if($blog)
            {
                $request = DB::table('project_requests')
                    ->where('project_id', '=', $project_id)
                    ->where('user_id', '=', $user_id)
                    ->first();

                $youtube_payment = $request->youtube_payment;
                $instagram_payment = $request->instagram_payment;

                DB::table('project_requests')
                    ->where('project_id', '=', $project_id)
                    ->where('user_id', '=', $user_id)
                    ->update(array('status' => '2', 'company_agree' => '1'));

                $now = DB::raw('now()');

                $pbc = DB::table('project_blogger_conditions')
                    ->insertGetId(array(
                        'user_id'   =>  $user_id,
                        'blog_id'   =>  $blog->id,
                        'project_id'    =>  $project_id,
                        'media_id'  =>  $blog->media_id,
                        'status'    =>  '2',
                        'created_at'    =>  $now,
                        'updated_at'    =>  $now
                    ));

                $budget = Budget::insertGetId(array(
                    'project_id'            =>  $project_id,
                    'user_id'               =>  $user_id,
                    'pbc_id'                =>  $pbc,
                    'youtube_payment'       =>  $youtube_payment,
                    'instagram_payment'     =>  $instagram_payment,
                    'cur_id'                =>  $project_details->cur_id,
                    'payment_agree_company' =>  '1',
                    'payment_agree_blogger' =>  '1',
                ));

                DB::table('project_blogger_conditions')
                    ->where('project_id', '=', $project_id)
                    ->where('user_id', '=', $user_id)
                    ->update(array('budget_id'=>$budget));

                $mdata = array();

                $company = DB::table('companies')
                ->find(Auth::user()->company_id);

                $mdata['project_title'] = $pr->title;
                $mdata['project_id'] = $project_id;
                $mdata['company_name'] = $company->company_name;

                NoticeController::notice_blogger($user_id, 3, $mdata);

                Eventlog::create(array(
                    'user_id'       =>  Auth::user()->id,
                    'company_id'    =>  Auth::user()->company_id,
                    'event_id'      =>  31,
                    'project_id'    =>  $project_id,
                    'sub_user_id'   =>  $user_id,
                    'post_id'       =>  null,
                    'blogger_check' =>  0,
                    'company_check' =>  1
                ));

                return Redirect::back()->with('global', trans('voc.changes_saved'));
            }
            else
            {
                return Redirect::back()->with('global_error', 'User does not have a proper blog');
            }
        }
        else
        {
            return Redirect::back()->with('global_error', trans('error.no_rights'));
        }

    }

    /**
     *  Accept project request haggle
    */
    public function haggle_accept()
    {
        if(Input::get('project_id'))
        {
            $project = Project::find(Input::get('project_id'));
            $user = Auth::user();

            $project_details = DB::table('project_details')
                ->where('project_id', '=', $project->id)
                ->first();

            $blog = DB::table('blogs AS b')
                ->leftJoin('project_medias AS pm', function($join) use ($project){
                    $join->on('b.media_id', '=', 'pm.media_id')
                        ->on('pm.project_id', '=', DB::raw($project->id));
                })
                ->where('b.user_id', '=', $user->id)
                ->select('b.id', 'b.media_id')
                ->orderBy('pm.media_id')
                ->first();

            if($blog)
            {
                $request = DB::table('project_requests')
                    ->where('project_id', '=', $project->id)
                    ->where('user_id', '=', $user->id)
                    ->first();

                $youtube_payment = $request->youtube_payment;
                $instagram_payment = $request->instagram_payment;

                DB::table('project_requests')
                    ->where('project_id', '=', $project->id)
                    ->where('user_id', '=', $user->id)
                    ->update(array('status' => '2', 'blogger_agree' => '1'));

                $now = DB::raw('now()');

                $pbc = DB::table('project_blogger_conditions')
                    ->insertGetId(array(
                        'user_id'       =>  $user->id,
                        'blog_id'       =>  $blog->id,
                        'project_id'    =>  $project->id,
                        'media_id'      =>  $blog->media_id,
                        'status'        =>  '2',
                        'created_at'    =>  $now,
                        'updated_at'    =>  $now
                    ));

                $budget = Budget::insertGetId(array(
                    'project_id'            =>  $project->id,
                    'user_id'               =>  $user->id,
                    'pbc_id'                =>  $pbc,
                    'youtube_payment'       =>  $youtube_payment,
                    'instagram_payment'     =>  $instagram_payment,
                    'cur_id'                =>  $project_details->cur_id,
                    'payment_agree_company' =>  '1',
                    'payment_agree_blogger' =>  '1',
                ));

                DB::table('project_blogger_conditions')
                    ->where('project_id', '=', $project->id)
                    ->where('user_id', '=', $user->id)
                    ->update(array('budget_id'=>$budget));

                $blogger = DB::table('blogs AS b')
                    ->leftJoin('users AS u', 'u.id', '=', 'b.user_id')
                    ->where('b.user_id', '=', $user->id)
                    ->whereNotNull('b.thumb')
                    ->first();

                $mdata = array();
                $mdata['project_id'] = $project->id;
                $mdata['project_title'] = $project->title;
                $mdata['blogger_thumb'] = $blogger->thumb;
                $mdata['blogger_name'] = $blogger->title;

                NoticeController::notice_company($project->company_id, 2, $mdata);

                Eventlog::create(array(
                    'user_id'       => $user->id,/* Blogger id */
                    'event_id'      => 22,
                    'project_id'    => $project->id,
                    'sub_company_id'=> $project->company_id,
                    'post_id'       => null,
                    'blogger_check' => 1,
                    'company_check' => 0
                ));

                return Redirect::back()->with('global', trans('voc.changes_saved'));
            }
            else
            {
                return Redirect::back()->with('global_error', 'User does not have a proper blog');
            }
        }
        else
        {
            return Redirect::back()->with('global_error', 'Wrong project id');
        }
    }

    /**
     * Company Declines project request
     */
    public function decline_request()
    {
        $project_id = Input::get('project_id');
        $user_id = Input::get('user_id');
        $pr = Project::find($project_id);
        if($pr->company_id==Auth::user()->company_id)
        {
            DB::table('project_requests')
                ->where('project_id', '=', $project_id)
                ->where('user_id', '=', $user_id)
                ->update(array('status' => '3'));

            Eventlog::create(array(
                'user_id'       =>  Auth::user()->id,// company
                'company_id'    =>  Auth::user()->company_id,
                'event_id'      =>  32,
                'project_id'    =>  $project_id,
                'sub_user_id'   =>  $user_id,// blogger
                'post_id'       =>  null,
                'blogger_check' => 0,
                'company_check' => 1
            ));

            return Redirect::back()->with('global', trans('voc.changes_saved'));
        }
        else
        {
            return Redirect::back()->with('global_error', trans('error.no_rights'));
        }
    }

    /**
     * Blogger declined after haggle
     */
    public function decline_request_haggle()
    {
        $project_id = Input::get('project_id');
        $user_id = Input::get('user_id');
        $project = Project::find($project_id);
        if($user_id==Auth::user()->id)
        {
            DB::table('project_requests')
                ->where('project_id', '=', $project_id)
                ->where('user_id', '=', $user_id)
                ->update(array('status' => '3'));

            Eventlog::create(array(
                'user_id'           => Auth::user()->id,// blogger
                'event_id'          => 27,
                'project_id'        => $project_id,
                'sub_company_id'    => $project->company_id,// company
                'post_id'           => null,
                'blogger_check'     => 1,
                'company_check'     => 0
            ));

            return Redirect::back()->with('global', trans('voc.changes_saved'));
        }
        else
        {
            return Redirect::back()->with('global_error', trans('error.no_rights'));
        }
    }

    public function delete_file()
    {
        $project = Input::get('project');
        $filename = input::get('filename');
        //check rights
        $user = Auth::user();

        $p = Project::find($project);

        if($user->company_id == $p->company_id)
        {
            // if exists delete
            if(File::exists($filename))
            {
                File::delete($filename);
            }
            // if deleted
            if(!File::exists($filename))
            {
                return 'deleted';
            }
            else
            {
                return 'delete failed';
            }
        }
        else
        {
            return trans('error.no_rights');
        }
    }

}

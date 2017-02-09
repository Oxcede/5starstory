<?php

class OpenProjectController extends \BaseController {

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index()
	{
		//
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

    private $bloggers;
    private $project_bloggers;
    private function set_project_bloggers($project_id, $status = null)
    {
        $this->bloggers = DB::table('project_blogger_conditions AS pb')
            ->leftJoin('projects AS pr', 'pr.id', '=', 'pb.project_id')
            ->leftJoin('project_posts AS pp', function($j1)
            {
                $j1->on('pp.user_id', '=', 'pb.user_id')
                    ->on('pp.project_id', '=', 'pb.project_id');
            })
            ->leftJoin('project_medias AS pm', function($j2){
                $j2->on('pm.project_id', '=', 'pb.project_id')
                    ->on('pm.media_id', '=', 'pb.media_id');
            })
            ->leftJoin('users AS u', 'u.id', '=', 'pb.user_id')
            ->leftJoin('posts AS p', 'p.id', '=', 'pp.post_id')
            ->leftJoin('budget AS b', function($j3)
            {
                $j3->on('b.project_id', '=', 'pb.project_id')
                    ->on('b.user_id', '=', 'pb.user_id');
            })
            ->where('pb.project_id', '=', $project_id)
            ->where(function($q) use ($status){
                if($status)
                {
                    $q->where('pb.status', '=', $status);
                }
            })
            ->whereNotIn('pb.status', array(6,7))
            ->select(
                'pb.id AS pbc_id', 'pb.user_id', 'pb.project_id', 'pb.media_id', 'pb.status',
                'pm.promo_format', 'pm.rules', 'pm.release_confirmation', 'pm.posts_amount',
                'b.id AS budget_id', 'b.youtube_payment', 'b.payment_agree_blogger', 'b.payment_agree_company',
                'b.instagram_payment', 'b.cur_id', 'b.status AS budget_status',
                'pp.id AS project_post_id', 'pp.post_id', 'pp.title', 'pp.title_confirmation', 'pp.script',
                'pp.script_confirmation', 'pp.promoted_product_text', 'pp.ppt_confirmation', 'pp.products',
                'pp.products_confirmation', 'pp.release_date', 'pp.release_date_confirmation',
                'pp.video_release_confirmation', 'pp.payment_confirmation',
                'pp.ig_title', 'pp.ig_title_confirmation', 'pp.ig_release_date', 'pp.ig_release_date_confirmation',
                'u.email', 'u.username',
                'pr.title AS project_title',
                'p.blog_link', 'p.instagram_link'
            )
            ->orderBy('pb.status', 'desc')
            ->get();
        //dd($this->bloggers);

        $project_bloggers_o = DB::table('users AS u')
            ->leftJoin(DB::raw('(SELECT
                        id AS yt_id,
                        lang_id AS lang_id1,
                        url AS yt_url,
                        user_id AS user_id1,
                        media_id AS yt_media,
                        title AS yt_title,
                        channel AS yt_channel,
                        category_id AS yt_category_id,
                        yt_subscriptions,
                        yt_avg_views,
                        thumb
                        FROM blogs WHERE media_id="1") b1'), function($join){
                $join->on('b1.user_id1','=', 'u.id');
            })
            ->leftJoin(DB::raw('(SELECT
                        id AS ig_id,
                        lang_id AS lang_id2,
                        user_id AS user_id2,
                        media_id AS ig_media,
                        category_id AS ig_category_id,
                        ig_user_id,
                        ig_username,
                        ig_media AS ig_media_count,
                        ig_follows,
                        ig_avg_views,
                        thumb AS ig_thumb
                        FROM blogs WHERE media_id="2") b2'), function($join){
                $join->on('b2.user_id2','=', 'u.id');
            })
            ->whereRaw('
                        u.id IN (SELECT user_id FROM project_blogger_conditions WHERE project_id="'.$project_id.'")
                        AND (b1.yt_channel IS NOT NULL OR b2.ig_username IS NOT NULL)
                        ')->get();

        $project_bloggers = array();
        foreach($project_bloggers_o as $blog)
        {
            if($blog->user_id1 || $blog->user_id2)
            {

            $user_id = ($blog->user_id1)?$blog->user_id1:$blog->user_id2;
            $project_bloggers[$user_id]['media'] = ($blog->yt_media)?$blog->yt_media:$blog->ig_media;
            $project_bloggers[$user_id]['yt_title'] = $blog->yt_title;
            $project_bloggers[$user_id]['yt_media'] = $blog->yt_media;
            $project_bloggers[$user_id]['yt_subscriptions'] = $blog->yt_subscriptions;

            $project_bloggers[$user_id]['yt_category'] = $blog->yt_category_id;
            $project_bloggers[$user_id]['yt_avg_views'] = $blog->yt_avg_views;

            $project_bloggers[$user_id]['ig_media'] = $blog->ig_media;
            $project_bloggers[$user_id]['ig_avg_views'] = $blog->ig_avg_views;
            $project_bloggers[$user_id]['ig_follows'] = $blog->ig_follows;
            $project_bloggers[$user_id]['yt_info']['thumb'] = $blog->thumb;
            $project_bloggers[$user_id]['ig_thumb'] = $blog->ig_thumb;
            $project_bloggers[$user_id]['ig_username'] = $blog->ig_username;
            $project_bloggers[$user_id]['ig_category'] = $blog->ig_category_id;
            if($blog->ig_user_id)
            {
                $project_bloggers[$user_id]['ig_info'] = Cache::remember('ig'.$blog->ig_user_id, 60, function() use ($blog){

                    return BloggerController::getInstagram($blog->ig_user_id);

                });
            }
            }

        }

        $this->project_bloggers = $project_bloggers;
    }

	/**
	 * Display the specified resource.
	 *
	 * @param  int  $project_id
	 * @return Response
	 */
	public function show($project_id)
	{
        try{
            $project = Project::find($project_id);
        }
        catch(Exception $e)
        {
            App::missing(function($exception)
            {
                return Response::view('errors.missing', array('title'=>'Project not found', 'error' => 'Project doesn\'t exist'), 404);
            });
            return App::abort(404);
        }

        if($project)
        {
            if($project->type == 1)/* Offer */
            {
                if(Auth::user()->role == 3 || Auth::user()->role == 4)/* Company */
                {
                    if($project->company_id == Auth::user()->company_id || Auth::user()->role == 4)
                    {
                        //return $this->view_company_offer($project);

                        // 1 - requests, 2 - confirmed
                        $type = (Input::get('type'))?Input::get('type'):3;
                        if($type==1)
                        {
                            return $this->view_company_ad($project);
                        }
                        elseif($type==2)
                        {
                            return $this->view_company_offer($project);
                        }
                        else
                        {
                            $test = DB::table('project_blogger_conditions')
                                ->where('project_id', '=', $project->id)
                                ->whereIn('status', array('2', '3', '4', '5'))
                                ->get();
                            if(!empty($test))
                            {
                                return $this->view_company_offer($project);
                            } else {
                                return $this->view_company_ad($project);
                            }
                        }

                    }
                    else
                    {
                        App::missing(function($exception)
                        {
                            return Response::view('errors.missing', array('title'=>'Project not found', 'error' => trans('voc.ermsg_no_rights')), 404);
                        });
                        return App::abort(404);
                    }
                }
                elseif(Auth::user()->role == 2)/* Blogger */
                {
                    return $this->view_blogger_offer($project);
                }
                elseif(Auth::user()->role == 4)/* Admin */
                {
                    return 'admin project view HERE maybe';
                }
                else
                {
                    App::missing(function($exception)
                    {
                        return Response::view('errors.missing', array('title'=>'Project not found', 'error' => trans('error.opc001')), 404);
                    });
                    return App::abort(404);
                }
            }
            elseif($project->type == 2)/* Advertisement */
            {
                if(Auth::user()->role == 3 || Auth::user()->role == 4)/* Company's view advertisement */
                {
                    if($project->company_id == Auth::user()->company_id || Auth::user()->role == 4)
                    {
                        /* 1 - requests, 2 - confirmed */
                        $type = (Input::get('type'))?Input::get('type'):3;
                        if($type==1)
                        {
                            return $this->view_company_ad($project);
                        }
                        elseif($type==2)
                        {
                            return $this->view_company_offer($project);
                        }
                        else
                        {
                            $test = DB::table('project_blogger_conditions')
                                ->where('project_id', '=', $project->id)
                                ->whereIn('status', array('2', '3', '4', '5'))
                                ->get();
                            if(!empty($test))
                            {
                                return $this->view_company_offer($project);
                            } else {
                                return $this->view_company_ad($project);
                            }
                        }

                    }
                    else
                    {
                        App::missing(function($exception)
                        {
                            return Response::view('errors.missing', array('title'=>'No rights', 'error' => trans('voc.ermsg_no_rights')), 404);
                        });
                        return App::abort(404);
                    }
                }
                elseif(Auth::user()->role == 2)/* Blogger's view advertisement */
                {
                    $request = DB::table('project_requests')
                        ->where('user_id', '=', Auth::user()->id)
                        ->where('project_id', '=', $project_id)
                        ->first();

                    if($request)
                    {
                        if($request->status==1 || $request->status==3)
                        {
                            return $this->view_blogger_ad($project);
                        }
                        elseif($request->status==2)
                        {
                            return $this->view_blogger_offer($project);
                        }
                    }

                    $offer = DB::table('project_blogger_conditions')
                        ->where('project_id', '=', $project_id)
                        ->where('user_id', '=', Auth::user()->id)
                        ->first();

                    if($offer)
                    {
                        return $this->view_blogger_offer($project);
                    } else {
                        return $this->view_blogger_ad($project);
                    }

                }
                else
                {
                    App::missing(function($exception)
                    {
                        return Response::view('errors.missing', array('title'=>'Project not found', 'error' => trans('error.opc001')), 404);
                    });
                    return App::abort(404);
                }
            }
        }
        else
        {
            App::missing(function($exception)
            {
                return Response::view('errors.missing', array('title'=>'Project not found', 'error' => trans('error.opc001')), 404);
            });
            return App::abort(404);
        }
	}

    protected function view_blogger_offer($project)
    {
        $project_members = array();

        $company_info = DB::table('companies')
            ->where('id', '=', $project->company_id)
            ->select('company_name')
            ->first();

        $conditions = DB::table('project_blogger_conditions')
            ->where('project_id', '=', $project->id)
            ->where('user_id', '=', Auth::user()->id)
            ->first();

        $project_medias = DB::table('project_medias')
            ->where('project_id', '=', $project->id)
            ->orderBy('media_id')
            ->get();

        $other_bloggers = DB::table('project_blogger_conditions AS pb')
            ->leftJoin('users AS u', 'u.id', '=', 'pb.user_id')
            ->leftJoin('blogs AS b', 'b.user_id', '=', 'u.id')
            ->where('pb.project_id', '=', $project->id)
            ->whereNotIn('pb.status', array('6,7'))
            ->where('b.media_id', '=', '1')
            ->select('u.username', 'u.id', 'b.category_id', 'b.thumb', 'b.url', 'b.title', 'b.yt_subscriptions', 'b.channel')
            ->get();

        foreach($other_bloggers as $other)
        {
            if(!empty($other->channel)){
                $project_members[$other->id]['title'] = $other->title;
                $project_members[$other->id]['url'] = $other->url;
                $project_members[$other->id]['thumb'] = $other->thumb;
                $project_members[$other->id]['subscriptions'] = $other->yt_subscriptions;
                $project_members[$other->id]['category'] = $other->category_id;
                $project_members[$other->id]['user_id'] = $other->id;
            }
        }

        if( !empty($conditions) && $conditions->status != 6 && $conditions->status != 7 )
        {
            /* GET PROJECT POST */
            $products = DB::table('project_products')
                ->leftJoin('products', 'products.id', '=', 'project_products.product_id')
                ->where('project_id', '=', $project->id)
                ->get();

            $pp = DB::table('project_posts')
                ->where('project_id', '=', $project->id)
                ->where('user_id', '=', Auth::user()->id)
                ->first();

            $comments = DB::table('project_comments AS c')
                ->leftJoin('users AS u', 'u.id', '=', 'c.author_user_id')
                ->leftJoin('companies AS com', 'com.id', '=', 'c.company_id')
                ->where('project_id', '=', $project->id)
                ->select('u.username', 'c.text', 'c.date', 'c.type', 'c.author_user_id', 'c.receiver_user_id', 'com.company_name', 'c.company_id')
                ->orderBy('c.id', 'DESC')
                ->get();

            $budget = DB::table('budget')
                ->where('project_id', '=', $project->id)
                ->where('user_id', '=', Auth::user()->id)
                ->first();

            if($pp)
            {
                $video = Post::find($pp->post_id);
            } else
                $video = null;

            $events = DB::table('eventlogs AS e')
                ->leftJoin('users AS u', 'u.id', '=', 'e.user_id')
                ->leftJoin('companies AS c', 'c.id', '=', 'e.company_id')
                ->where('project_id', '=', $project->id)
                //->whereNotIn('e.event_id', array('33'))
                ->select('u.username', 'c.company_name', 'e.event_id', 'e.user_id', 'e.project_id',
                    'e.sub_company_id', 'e.sub_user_id', 'e.post_id', 'e.created_at')
                ->orderBy('e.id')
                ->get();

            $project_data = DB::table('projects AS p')
                ->leftJoin('project_details AS pd', 'pd.project_id', '=', 'p.id')
                ->leftJoin('companies AS ci', 'ci.id', '=', 'p.company_id')
                ->where('p.id', '=', $project->id)
                ->select(
                    'p.id', 'p.title', 'p.description', 'p.link',
                    'p.company_id', 'p.category_id', 'p.status', 'p.valid', 'p.shipping',
                    'pd.theme', 'pd.demography', 'pd.subscribers', 'pd.cur_id',
                    'pd.haggle_option', 'pd.views', 'pd.youtube_payment', 'pd.instagram_payment', 'ci.company_name'
                )
                ->get();

            $contacts = DB::table('contacts')
                ->where('user_id', '=', Auth::user()->id)
                ->first();

            $shipping_details = DB::table('shipping_details')
                ->where('project_id', '=', $project->id)
                ->where('blogger_id', '=', Auth::user()->id)
                ->first();

            $files = File::files('./documents/'.$project->id);

            $project_medias_s = DB::table('project_medias')
                ->where('project_id', '=', $project->id)
                ->select( DB::raw('group_concat(media_id) AS media') )
                ->first();

            return View::make('openproject.show_blogger', array(
                'pid'               =>  $project->id,
                'title'             =>  $project->title,
                'description'       =>  $project->description,
                'conditions'        =>  $conditions,
                'products'          =>  $products,
                'project'           =>  $project,
                'project_post'      =>  $pp,
                'project_medias'    =>  $project_medias,
                'project_medias_s'  =>  $project_medias_s->media, /* medias string */
                'video'             =>  $video,
                'comments'          =>  $comments,
                'budget'            =>  $budget,
                'company_id'        =>  $project->company_id,
                'company_name'      =>  $company_info,
                'events'            =>  $events,
                'project_members'   =>  $project_members,
                'project_data'      =>  $project_data,
                'type'              =>  '1',
                'files'             =>  $files,
                'contacts'          =>  $contacts,
                'shipping_details'  =>  $shipping_details,
            ));
        }
        else
        {
            App::missing(function($exception)
            {
                return Response::view('errors.missing', array('title'=>'Project not found', 'error' => trans('voc.ermsg_no_rights')), 404);
            });
            return App::abort(404);
        }
    }

    protected function view_blogger_ad($project)
    {
        $allow = 0;
        $req = 0;
        $available_medias = array();

        $blogs = DB::table('blogs')
            ->where('user_id', '=', Auth::user()->id)
            ->get();

        foreach($blogs as $b)
        {
            $available_medias[$b->media_id] = $b->media_id;
        }

        $request = DB::table('project_requests')
            ->where('user_id', '=', Auth::user()->id)
            ->where('project_id', '=', $project->id)
            ->first();

        if($request)
        {
            $req = $request->status;
        }

        if(!empty($available_medias) && !$request)
        {
            $allow = 1;
        }

        $project_data = DB::table('projects AS p')
            ->leftJoin('project_details AS pd', 'pd.project_id', '=', 'p.id')
            ->leftJoin('companies AS ci', 'ci.id', '=', 'p.company_id')
            ->where('p.id', '=', $project->id)
            ->select(
                'p.id', 'p.title', 'p.description', 'p.link',
                'p.company_id', 'p.category_id', 'p.status', 'p.valid', 'p.shipping',
                'pd.theme', 'pd.demography', 'pd.subscribers', 'pd.cur_id',
                'pd.haggle_option', 'pd.views', 'pd.youtube_payment', 'pd.instagram_payment', 'ci.company_name'
            )
            ->get();

        $project_medias_o = DB::table('project_medias')
            ->where('project_id', '=', $project->id)
            ->orderBy('media_id')
            ->get();

        $project_medias = DB::table('project_medias')
            ->where('project_id', '=', $project->id)
            ->select( DB::raw('group_concat(media_id) AS media') )
            ->first();

        $files = File::files('./documents/'.$project->id);

        $events = DB::table('eventlogs AS e')
            ->leftJoin('users AS u', 'u.id', '=', 'e.user_id')
            ->leftJoin('companies AS c', 'c.id', '=', 'e.company_id')
            ->where('project_id', '=', $project->id)
            ->where(function($q){
                $q->where('e.user_id', '=', Auth::user()->id)
                    ->orWhere('e.sub_user_id', '=', Auth::user()->id);
            })
            //->whereNotIn('e.event_id', array('33'))
            ->select('u.username', 'c.company_name', 'e.event_id', 'e.user_id', 'e.project_id',
                'e.sub_company_id', 'e.sub_user_id', 'e.post_id', 'e.created_at')
            ->orderBy('e.id')
            ->get();

        $comments = DB::table('project_comments AS c')
            ->leftJoin('users AS u', 'u.id', '=', 'c.author_user_id')
            ->leftJoin('companies AS com', 'com.id', '=', 'c.company_id')
            ->where('project_id', '=', $project->id)
            ->select('u.username', 'c.text', 'c.date', 'c.type', 'c.author_user_id', 'c.receiver_user_id', 'com.company_name', 'c.company_id')
            ->orderBy('c.id', 'DESC')
            ->get();

        return View::make('openproject.show_blogger', array(
            'pid'               =>  $project->id,
            'title'             =>  (!empty($project_data->title))?$project_data->title:'Project',
            'type'              =>  '2',
            'project_data'      =>  $project_data,
            'project_medias'    =>  $project_medias->media,/* medias string */
            'project_medias_o'  =>  $project_medias_o,
            'allow_apply'       =>  $allow,
            'request_status'    =>  $req,
            'request'           =>  $request,
            'files'             =>  $files,
            'project'           =>  $project,
            'available_medias'  =>  $available_medias,
            'events'            =>  $events,
            'comments'          =>  $comments,

        ));
    }

    protected function view_company_offer($project)
    {
        /* GET PROJECT POST */
        $this->set_project_bloggers($project->id);

        $bloggers = $this->bloggers;
        foreach($bloggers as $blogger)
        {
            if($blogger->status==4)
            {
                $blogger->blog_link;
            }
        }

        $products = DB::table('products AS p')
            ->leftJoin('project_products AS pp', 'pp.product_id', '=', 'p.id')
            ->where('pp.project_id', '=', $project->id)
            ->get();

        $project_medias = DB::table('project_medias')
            ->where('project_id', '=', $project->id)
            ->orderBy('media_id')
            ->get();

        $comments = DB::table('project_comments AS c')
            ->leftJoin('users AS u', 'u.id', '=', 'c.author_user_id')
            ->leftJoin('companies AS com', 'com.id', '=', 'c.company_id')
            ->where('project_id', '=', $project->id)
            ->select('u.username', 'c.text', 'c.date', 'c.type', 'c.receiver_company_id',
                'c.author_user_id', 'c.receiver_user_id', 'com.company_name', 'c.company_id')
            ->orderBy('c.id', 'DESC')
            ->get();

        $events = DB::table('eventlogs AS e')
            ->leftJoin('users AS u', 'u.id', '=', 'e.user_id')
            ->leftJoin('companies AS c', 'c.id', '=', 'e.company_id')
            ->where('project_id', '=', $project->id)
            //->whereNotIn('e.event_id', array('33'))
            ->select('u.username', 'c.company_name', 'e.event_id', 'e.user_id', 'e.project_id',
                'e.sub_user_id', 'e.sub_company_id', 'e.post_id', 'e.created_at')
            ->orderBy('e.id', 'desc')
            ->get();

        $files = File::files('./documents/'.$project->id);

        $shipping_o = DB::table('shipping_details')
            ->where('project_id', '=', $project->id)
            ->get();

        $shipping = array();
        foreach($shipping_o as $detail)
        {
            $shipping[$detail->blogger_id]['country'] = $detail->country;
            $shipping[$detail->blogger_id]['city'] = $detail->city;
            $shipping[$detail->blogger_id]['zip'] = $detail->zip;
            $shipping[$detail->blogger_id]['address'] = $detail->address;
            $shipping[$detail->blogger_id]['phone'] = $detail->phone;
            $shipping[$detail->blogger_id]['info'] = $detail->info;
            $shipping[$detail->blogger_id]['status'] = $detail->status;
        }

        $deleteable = $this->deleteable($project->id);

        $bells = DB::table('eventlogs')
            ->select(DB::raw('user_id, count(id) as alerts'))
            ->where('project_id', '=', $project->id)
            ->where('company_check', '=', '0')
            ->where('sub_company_id', '=', Auth::user()->company_id)
            ->groupBy('user_id')
            ->get();
        $logs = array();
        foreach($bells as $row)
        {
            $logs[$row->user_id] = $row->alerts;
        }

        $graph = array();
        foreach($this->bloggers as $b)
        {
            $graph[$b->user_id] = BloggerController::get_graph($b->user_id, $project->id);
        }

        $count_offers = DB::table('project_blogger_conditions')
            ->where('project_id', '=', $project->id)
            ->where('status', '=', DB::raw('1'))
            ->count('id');

        $count_requests = DB::table('project_requests')
            ->where('project_id', '=', $project->id)
            ->where('status', '=', DB::raw('1'))
            ->count('id');

        $requests_count = $count_offers + $count_requests;

        $bloggers_in_work = DB::table('project_blogger_conditions')
            ->whereIn('status', array('2', '3', '4', '5'))
            ->where('project_id', '=', $project->id)
            ->count('id');

        $project_medias_s = DB::table('project_medias')
            ->where('project_id', '=', $project->id)
            ->select( DB::raw('group_concat(media_id) AS media') )
            ->first();

        return View::make('openproject.show_company',
            array('title'               => $project->title,
                  'project'             => $project,
                  'project_medias'      => $project_medias,
                  'project_medias_s'    => $project_medias_s->media,// medias string
                  'bloggers'            => $bloggers,
                  'products'            => $products,
                  'comments'            => $comments,
                  'events'              => $events,
                  'project_bloggers'    => $this->project_bloggers,
                  'type'                => $project->type,
                  'files'               => $files,
                  'shipping'            => $shipping,
                  'deleteable'          => $deleteable,
                  'log'                 => $logs,
                  'graph'               => $graph,
                  'requests_count'      => $requests_count,
                  'in_work'             => $bloggers_in_work
            ));
    }


    protected function view_company_ad($project)
    {
        $type=1;
        $this->set_project_bloggers($project->id);
        /* REQUESTS SECTION */
        $request_bloggers = array();
        $request_bloggers_o = DB::table('users AS u')
            ->leftJoin(DB::raw('(SELECT
                        id AS yt_id,
                        lang_id AS lang_id1,
                        url AS yt_url,
                        user_id AS user_id1,
                        media_id AS yt_media,
                        title AS yt_title,
                        channel AS yt_channel,
                        thumb AS yt_thumb,
                        category_id AS yt_category_id,
                        yt_subscriptions,
                        yt_avg_views
                        FROM blogs WHERE media_id="1") b1'), function($join){
                $join->on('b1.user_id1','=', 'u.id');
            })
            ->leftJoin(DB::raw('(SELECT
                        id AS ig_id,
                        lang_id AS lang_id2,
                        user_id AS user_id2,
                        media_id AS ig_media,
                        category_id AS ig_category_id,
                        thumb AS ig_thumb,
                        ig_user_id,
                        ig_username,
                        ig_media AS ig_media_count,
                        ig_follows,
                        ig_avg_views
                        FROM blogs WHERE media_id="2") b2'), function($join){
                $join->on('b2.user_id2','=', 'u.id');
            })
            ->whereRaw('
                        u.id IN (SELECT user_id FROM project_requests WHERE project_id="'.$project->id.'" AND status="1")
                        AND (b1.yt_channel IS NOT NULL OR b2.ig_username IS NOT NULL)
                        ')->get();

        foreach($request_bloggers_o as $blog)
        {
            $user_id = ($blog->user_id1)?$blog->user_id1:$blog->user_id2;
            $request_bloggers[$user_id]['media'] = ($blog->yt_media)?$blog->yt_media:$blog->ig_media;
            $request_bloggers[$user_id]['thumb'] = ($blog->yt_thumb)?$blog->yt_thumb:$blog->ig_thumb;
            $request_bloggers[$user_id]['yt_title'] = $blog->yt_title;
            $request_bloggers[$user_id]['yt_media'] = $blog->yt_media;

            $request_bloggers[$user_id]['yt_category'] = $blog->yt_category_id;
            $request_bloggers[$user_id]['yt_subscriptions'] = $blog->yt_subscriptions;
            $request_bloggers[$user_id]['yt_avg_views'] = $blog->yt_avg_views;

            $request_bloggers[$user_id]['ig_media'] = $blog->ig_media;
            $request_bloggers[$user_id]['ig_category'] = $blog->ig_category_id;
            $request_bloggers[$user_id]['ig_avg_views'] = $blog->ig_avg_views;
            $request_bloggers[$user_id]['ig_follows'] = $blog->ig_follows;
            $request_bloggers[$user_id]['ig_username'] = $blog->ig_username;
            /*
            if($blog->yt_channel)
            {
                $request_bloggers[$user_id]['yt_info'] = BloggerController::getBloggerInfo(false, $blog->yt_channel);
            }
            if($blog->ig_user_id)
            {
                $request_bloggers[$user_id]['ig_info'] = BloggerController::getInstagram($blog->ig_user_id);
            }
            */
        }

        $requests = DB::table('project_requests')
            ->where('project_id', '=', $project->id)
            ->where('status', '=', DB::raw('1'))
            ->get();

        /*--*/
        $bloggers_in_work = DB::table('project_blogger_conditions')
            ->whereIn('status', array('2', '3', '4', '5'))
            ->where('project_id', '=', $project->id)
            ->count('id');

        /* BLOGGERS ACCEPTED */
        $project_bloggers = array();
        $bloggers_replies = DB::table('project_blogger_conditions')
            ->where('project_id', '=', $project->id)
            ->get();
        if($bloggers_replies)
        {
            $this->set_project_bloggers($project->id, 1);
            $project_bloggers = $this->project_bloggers;
        }
        /*--*/

        /* PROJECT DATA */
        $project_data = DB::table('projects AS p')
            ->leftJoin('project_details AS pd', 'pd.project_id', '=', 'p.id')
            ->leftJoin('companies AS c', 'c.id', '=', 'p.company_id')
            ->where('p.id', '=', $project->id)
            ->select(
                'p.id',
                'p.title',
                'p.description',
                'p.link',
                'p.company_id',
                'p.category_id',
                'p.type',
                'p.status',
                'p.valid',
                'p.sf_post_conditions',
                'p.sf_post_due_date',
                'p.form_type',
                'pd.theme',
                'pd.demography',
                'pd.subscribers',
                'pd.views',
                'pd.youtube_payment',
                'pd.instagram_payment',
                'pd.cur_id',
                'c.company_name'
            )
            ->first();

        $medias = DB::table('project_medias AS pm')
            ->leftJoin('medias AS m', 'm.id', '=', 'pm.media_id')
            ->where('pm.project_id', '=', $project->id)
            ->get();

        $comments = DB::table('project_comments AS c')
            ->leftJoin('users AS u', 'u.id', '=', 'c.author_user_id')
            ->leftJoin('companies AS com', 'com.id', '=', 'c.company_id')
            ->where('project_id', '=', $project->id)
            ->select('u.username', 'c.text', 'c.date', 'c.type', 'c.receiver_company_id',
                'c.author_user_id', 'c.receiver_user_id', 'com.company_name', 'c.company_id')
            ->orderBy('c.id', 'DESC')
            ->get();

        $events = DB::table('eventlogs AS e')
            ->leftJoin('users AS u', 'u.id', '=', 'e.user_id')
            ->leftJoin('companies AS c', 'c.id', '=', 'e.company_id')
            ->where('project_id', '=', $project->id)
            ->select('u.username', 'c.company_name', 'e.event_id', 'e.user_id', 'e.project_id', 'e.sub_user_id',
                'e.sub_company_id', 'e.post_id', 'e.created_at')
            ->get();
        /*--*/

        $files = File::files('./documents/'.$project->id);

        $deleteable = $this->deleteable($project->id);

        $requests_count = count($this->bloggers) + count($request_bloggers);

        $project_medias = DB::table('project_medias')
            ->where('project_id', '=', $project->id)
            ->select( DB::raw('group_concat(media_id) AS media') )
            ->first();

        return View::make('openproject.show_company_ad', array(
            'pid'               =>  $project->id,
            'title'             =>  $project->title,
            'project'           =>  $project,
            'type'              =>  $type,
            'project_data'      =>  $project_data,
            'project_medias'    =>  $medias,
            'project_medias_s'  =>  $project_medias->media, // string of medias
            'bloggers'          =>  $this->bloggers,
            'project_bloggers'  =>  $this->project_bloggers,
            'project_bloggers'  =>  $project_bloggers,
            'requests'          =>  $requests,
            'request_bloggers'  =>  $request_bloggers,
            'comments'          =>  $comments,
            'events'            =>  $events,
            'files'             =>  $files,
            'deleteable'        =>  $deleteable,
            'in_work'           =>  $bloggers_in_work,
            'requests_count'    =>  $requests_count
        ));
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
     * Save new values for blogger payment by Blogger
     *
     * @param  int  $project_id
     * @return Response
     */
    public function edit_payment($project_id)
    {
        $project = DB::table('project_blogger_conditions AS pb')
            ->leftJoin('projects AS p', function($join) use ($project_id)
            {
                $join->on('p.id', '=', 'pb.project_id')
                    ->on('p.id', '=', DB::raw($project_id));
            })
            ->where('p.id', '=', $project_id)
            ->where(function ($query) {
                $query->where('p.company_id', '=', Auth::user()->company_id)
                    ->orWhere('pb.user_id', '=', Auth::user()->id);
            })
            ->wherein('pb.status', array(1,2))
            ->select('p.company_id', 'pb.user_id AS blogger', 'p.category_id', 'p.title')
            ->first();

        if(!empty($project))
        {
            /* company updates */
            if($project->company_id == Auth::user()->company_id)
            {
                if((Input::get('youtube_payment')!=0) || (Input::get('instagram_payment')!=0))
                {
                    $budget = Budget::find(Input::get('budget_id'));

                    $budget->payment_agree_company = 1;
                    $budget->payment_agree_blogger = 0;
                    $budget->youtube_payment = Input::get('youtube_payment');
                    $budget->instagram_payment = Input::get('instagram_payment');

                    if($budget->save())
                    {
                        $mdata = array();

                        $company = DB::table('companies')
                            ->find(Auth::user()->company_id);

                        $mdata['project_title'] = $project->title;
                        $mdata['category_id'] = $project->category_id;
                        $mdata['project_id'] = $project_id;
                        $mdata['company_name'] = $company->company_name;

                        NoticeController::notice_blogger($budget->user_id, 2, $mdata);

                        Eventlog::create(array(
                            'user_id' => Auth::user()->id,
                            'event_id' => 25,
                            'project_id' => $project_id,
                            'sub_user_id' => $budget->user_id,
                            'post_id' => null,
                            'blogger_check' => 0,
                            'company_check' => 1
                        ));
                        return 'saved';
                    }
                    else
                    {
                        return 'error saving';
                    }
                }
                else
                {
                    return Redirect::back()->with('global_error', 'No values');
                }
            }
            /* blogger updates */
            elseif($project->blogger == Auth::user()->id)
            {
                if((Input::get('youtube_payment')!=0) || (Input::get('instagram_payment')!=0))
                {

                    $budget = Budget::where(function($query) use ($project_id){
                        $query->where('project_id', '=', DB::raw($project_id))
                            ->where('user_id', '=', Auth::user()->id);
                    })
                    ->first();

                    $budget->payment_agree_company = 0;
                    $budget->payment_agree_blogger = 1;
                    $budget->youtube_payment = Input::get('youtube_payment');

                    $budget->instagram_payment = Input::get('instagram_payment');

                    $mdata = array();
                    $mdata['blogger_name'] = Auth::user()->username;
                    $mdata['project_title'] = $project->title;
                    $mdata['project_id'] = $project_id;
                    NoticeController::notice_company($project->company_id, 4, $mdata);

                    if($budget->save())
                    {
                        Eventlog::create(array(
                            'user_id' => $project->blogger,
                            'event_id' => 23,
                            'project_id' => $project_id,
                            'sub_company_id' => $project->company_id,
                            'post_id' => null,
                            'blogger_check' => 1,
                            'company_check' => 0
                        ));
                        return Redirect::back()->with('global',trans('voc.changes_saved'));
                    }
                    else
                    {
                        return Redirect::back()->with('global_error', 'error saving');
                    }
                }
                else
                {
                    return Redirect::back()->with('global_error', 'No values');
                }

            }
            else return Redirect::back()->with('global_error', trans('error.error'));
        }
        else
        {
            return Redirect::back()->with('global_error', trans('error.no_rights'));
        }
    }


	/**
	 * Update the specified resource in storage.
	 *
	 * @return Response
	 */
	public function blogger_update()
	{
		//dd(Input::all());
        if(Input::get('project_id'))
        {
            $project = Project::find(Input::get('project_id'));
            $user = Auth::user();

            if(!empty($project->id))
            {
                /* Check rights */
                $check = DB::table('project_blogger_conditions')
                    ->where('project_id', '=', $project->id)
                    ->where('user_id', '=', $user->id)
                    ->first();

                if(!empty($check->id))
                {
                    /* Shipping check */
                    if($project->shipping==1)
                    {
                        $shipping_details = DB::table('shipping_details')
                            ->where('project_id', '=', $project->id)
                            ->where('blogger_id', '=', $user->id)
                            ->first();

                        $country = (Input::get('country'))?Input::get('country'):'';
                        $city = (Input::get('city'))?Input::get('city'):'';
                        $address = (Input::get('address'))?Input::get('address'):'';
                        $zip = (Input::get('zip'))?Input::get('zip'):'';
                        $phone = (Input::get('phone'))?Input::get('phone'):'';

                        /* Create new or update shipping details */
                        if(!empty($shipping_details))
                        {
                            //update
                            DB::table('shipping_details')
                                ->where('project_id', '=', $project->id)
                                ->where('blogger_id', '=', $user->id)
                                ->update(array(
                                    'country'   => $country,
                                    'city'      => $city,
                                    'address'   => $address,
                                    'zip'       => $zip,
                                    'phone'     => $phone,
                                ));
                        }
                        else
                        {
                            //create
                            DB::table('shipping_details')
                                ->insert(array(
                                    'project_id'=> $project->id,
                                    'blogger_id'=> $user->id,
                                    'country'   => $country,
                                    'city'      => $city,
                                    'address'   => $address,
                                    'zip'       => $zip,
                                    'phone'     => $phone,
                                    'status'    => '0'
                                ));
                        }
                    }

                    /* Saving post data */
                    $post = DB::table('project_posts')
                        ->where('project_id', '=', $project->id)
                        ->where('user_id', '=', $user->id)
                        ->first();
                    try
                    {
                        if(!$post)
                        {
                            $now = DB::raw('now()');

                            $post = Post::create(array(
                                'user_id'       =>  $user->id,
                                'project_id'    =>  $project->id,
                            ));

                            $ppost = Projectpost::create(array(
                                'user_id' => $user->id,
                                'post_id'   => $post->id,
                                'project_id' => $project->id,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ));
                            $p = Projectpost::find($ppost->id);
                        }
                        else
                        {
                            $p = Projectpost::find($post->id);
                        }

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

                        if(Input::get('video_title') && $p->title_confirmation!=1)
                        {
                            if($p->title != Input::get('video_title'))
                            {
                                $mdata['details'][] = 'video_title';

                                Eventlog::create(array(
                                    'user_id' => $user->id,
                                    'event_id' => 3,
                                    'project_id' => $project->id,
                                    'sub_company_id' => $project->company_id,
                                    'post_id' => null,
                                    'blogger_check' => 1,
                                    'company_check' => 0
                                ));
                            }
                            $p->title = Input::get('video_title');
                            DB::table('project_posts')
                                ->where('id', '=', $post->id)
                                ->update(array(
                                    'title_confirmation' => 0
                                ));
                        }
                        if(Input::get('ig_title') && $p->ig_title_confirmation!=1)
                        {
                            if($p->ig_title != Input::get('ig_title'))
                            {
                                $mdata['details'][] = 'ig_title';

                                    Eventlog::create(array(
                                    'user_id' => $user->id,
                                    'event_id' => 34,
                                    'project_id' => $project->id,
                                    'sub_company_id' => $project->company_id,
                                    'post_id' => null,
                                    'blogger_check' => 1,
                                    'company_check' => 0
                                ));
                            }
                            $p->ig_title = Input::get('ig_title');
                            DB::table('project_posts')
                                ->where('id', '=', $post->id)
                                ->update(array(
                                    'ig_title_confirmation' => 0
                                ));
                        }
                        if(Input::get('video_script') && $p->script_confirmation!=1)
                        {
                            if($p->script != Input::get('video_script'))
                            {
                                $mdata['details'][] = 'video_script';

                                    Eventlog::create(array(
                                    'user_id' => $user->id,
                                    'event_id' => 4,
                                    'project_id' => $project->id,
                                    'sub_company_id' => $project->company_id,
                                    'post_id' => null,
                                    'blogger_check' => 1,
                                    'company_check' => 0
                                ));
                            }
                            $p->script = Input::get('video_script');
                            DB::table('project_posts')
                                ->where('id', '=', $post->id)
                                ->update(array(
                                    'script_confirmation' => 0
                                ));

                        }
                        if(Input::get('product_text') && $p->ppt_confirmation!=1)
                        {
                            if($p->promoted_product_text != Input::get('product_text'))
                            {
                                $mdata['details'][] = 'product_text';

                                    Eventlog::create(array(
                                    'user_id' => $user->id,
                                    'event_id' => 5,
                                    'project_id' => $project->id,
                                    'sub_company_id' => $project->company_id,
                                    'post_id' => null,
                                    'blogger_check' => 1,
                                    'company_check' => 0
                                ));
                            }
                            $p->promoted_product_text = Input::get('product_text');
                            DB::table('project_posts')
                                ->where('id', '=', $post->id)
                                ->update(array(
                                    'ppt_confirmation' => 0
                                ));
                        }
                        if(Input::get('other_products') && $p->products_confirmation!=1)
                        {
                            if($p->products != Input::get('other_products'))
                            {
                                $mdata['details'][] = 'other_products';

                                    Eventlog::create(array(
                                    'user_id' => $user->id,
                                    'event_id' => 6,
                                    'project_id' => $project->id,
                                    'sub_company_id' => $project->company_id,
                                    'post_id' => null,
                                    'blogger_check' => 1,
                                    'company_check' => 0
                                ));
                            }
                            $p->products = Input::get('other_products');
                            DB::table('project_posts')
                                ->where('id', '=', $post->id)
                                ->update(array(
                                    'products_confirmation' => 0
                                ));
                        }
                        if(Input::get('release_date') && $p->release_date_confirmation!=1)
                        {
                            if($p->release_date != Input::get('release_date'))
                            {
                                $mdata['details'][] = 'release_date';

                                    Eventlog::create(array(
                                    'user_id' => $user->id,
                                    'event_id' => 7,
                                    'project_id' => $project->id,
                                    'sub_company_id' => $project->company_id,
                                    'post_id' => null,
                                    'blogger_check' => 1,
                                    'company_check' => 0
                                ));
                            }
                            $p->release_date = Input::get('release_date');
                            DB::table('project_posts')
                                ->where('id', '=', $post->id)
                                ->update(array(
                                    'release_date_confirmation' => 0
                                ));
                        }
                        if(Input::get('ig_release_date') && $p->ig_release_date_confirmation!=1)
                        {
                            if($p->release_date != Input::get('ig_release_date'))
                            {
                                $mdata['details'][] = 'ig_release_date';

                                    Eventlog::create(array(
                                    'user_id' => $user->id,
                                    'event_id' => 37,
                                    'project_id' => $project->id,
                                    'sub_company_id' => $project->company_id,
                                    'post_id' => null,
                                    'blogger_check' => 1,
                                    'company_check' => 0
                                ));
                            }
                            $p->ig_release_date = Input::get('release_date');
                            DB::table('project_posts')
                                ->where('id', '=', $post->id)
                                ->update(array(
                                    'ig_release_date_confirmation' => 0
                                ));
                        }
                        /* YOUTUBE POST */
                        if(Input::get('video_link'))
                        {
                            $video = Post::find($p->post_id);

                            if(strpos(Input::get('video_link'), '?v=') || strpos(Input::get('video_link'), 'youtu.be'))
                            {

                                /* update events if new link */
                                if($video->blog_link != Input::get('video_link'))
                                {
                                    NoticeController::notice_company($project->company_id, 5, $mdata);

                                    Eventlog::create(array(
                                        'user_id' => $user->id,
                                        'event_id' => 8,
                                        'project_id' => $project->id,
                                        'sub_company_id' => $project->company_id,
                                        'post_id' => null,
                                        'blogger_check' => 1,
                                        'company_check' => 0
                                    ));

                                }

                                /* update status */
                                $status = $this->set_post_stats(Input::get('video_link'), $post->id);

                                $project_media = DB::table('project_medias')
                                    ->where('media_id', '=', DB::raw('1'))
                                    ->where('project_id', '=', $project->id)
                                    ->first();

                                $confirmation = $project_media->release_confirmation[5];

                                if($confirmation == 0)
                                {
                                    if($status=='public')
                                    {
                                        DB::table('project_blogger_conditions')
                                            ->where('user_id', '=', $user->id)
                                            ->where('project_id', '=', $project->id)
                                            ->update(array(
                                                'status'    =>  '5'
                                            ));
                                    }
                                    else
                                    {
                                        DB::table('project_blogger_conditions')
                                            ->where('user_id', '=', $user->id)
                                            ->where('project_id', '=', $project->id)
                                            ->update(array(
                                                'status'    =>  '4'
                                            ));
                                    }
                                }
                                else
                                {
                                    DB::table('project_blogger_conditions')
                                        ->where('user_id', '=', $user->id)
                                        ->where('project_id', '=', $project->id)
                                        ->update(array(
                                            'status'    =>  '3'
                                        ));
                                }

                                $video->blog_link = Input::get('video_link');

                                DB::table('project_posts')
                                    ->where('id', '=', $post->id)
                                    ->update(array(
                                        'video_release_confirmation' => 0
                                    ));

                                if($video->save())
                                {

                                }
                                else
                                {
                                    return Redirect::back()
                                        ->withInput()
                                        ->with('global_error', trans('error.error').' #op005');
                                }

                            }
                            else
                            {

                                return Redirect::back()
                                    ->withInput()
                                    ->with('global_error', trans('error.error').' #op004');
                            }
                        }
                        /* INSTAGRAM POST */
                        if(Input::get('instagram_link'))
                        {
                            $video = Post::find($p->post_id);

                            if(strpos(Input::get('instagram_link'), 'instagram.com'))
                            {
                                /* update events if new link */
                                if($video->blog_link != Input::get('instagram_link'))
                                {
                                    Eventlog::create(array(
                                        'user_id' => $user->id,
                                        'event_id' => 40,
                                        'project_id' => $project->id,
                                        'sub_company_id' => $project->company_id,
                                        'post_id' => null,
                                        'blogger_check' => 1,
                                        'company_check' => 0
                                    ));
                                }

                                $video->instagram_link = Input::get('instagram_link');

                                if($video->save())
                                {

                                }
                                else
                                {
                                    return Redirect::back()
                                        ->withInput()
                                        ->with('global_error', trans('error.error').' #op007');
                                }

                            }
                            else
                            {

                                return Redirect::back()
                                    ->withInput()
                                    ->with('global_error', trans('error.error').' #op006');
                            }
                        }

                        if($p->save())
                        {

                            /* Send email */
                            NoticeController::notice_company($project->company_id, 3, $mdata);

                            return Redirect::back()
                                ->with('global', trans('voc.changes_saved'));
                        }
                        else
                        {
                            return Redirect::back()
                                ->withInput()
                                ->with('global_error', trans('error.error').' #op003');
                        }

                    }
                    catch(Exception $e){return $e;}
                }
                else
                {
                    return Redirect::back()
                        ->withInput()
                        ->with('global_error', trans('error.no_rights'));
                }
            }
            else
            {
                return Redirect::back()
                    ->withInput()
                    ->with('global_error', trans('error.error').' #op002');
            }
        }
        else
        {
            return Redirect::back()
                ->withInput()
                ->with('global_error', trans('error.error').' #op001');
        }
	}

    /**
     * Check project for delete option
     */
    public static function deleteable($project_id)
    {

        $test = DB::table('project_blogger_conditions')
            ->where('project_id', '=', $project_id)
            ->whereIn('status', array('2,3,4,5'))
            ->first();

        if(!empty($test->id))
        {
            return 0;
        }
        else
        {
            return 1;
        }

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

	/**
	 * Accept project by blogger.
	 *
	 * @param  int  $project_id
	 * @return Response
	 */
	public function accept_blogger($project_id)
	{
        $project = Project::find($project_id);

        if($project)
        {
            DB::table('project_blogger_conditions')
                ->where('project_id', '=', $project_id)
                ->where('user_id', '=', Auth::user()->id)
                ->update(array(
                    'status'    =>  2
                ));

            $budget = DB::table('project_blogger_conditions')
                ->where('project_id', '=', $project_id)
                ->where('user_id', '=', Auth::user()->id)
                ->first();

            DB::table('budget')
                ->where('id', '=', $budget->budget_id)
                ->update(array(
                    'payment_agree_blogger' =>  1,
                    'payment_agree_company' =>  1
                ));

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

            NoticeController::notice_company($project->company_id, 2, $mdata);

            Eventlog::create(array(
                'user_id'       => Auth::user()->id,/* Blogger id */
                'event_id'      => 22,
                'project_id'    => $project_id,
                'sub_company_id'=> $project->company_id,
                'post_id'       => null,
                'blogger_check' => 1,
                'company_check' => 0
            ));

            return Redirect::to('project/'.$project_id)->with('global', trans('voc.project_accepted'));
        }
        else
        {
            return App::abort(401);
        }
	}

    /** Accept edited project by company.
     *
     * @param  int  $project_id
     * @return Response
     */
    public function accept_company($project_id)
    {
        if(Input::get('pbc_id'))
        {
            /* check rights */
            $project = Project::find($project_id);
            if($project->company_id == Auth::user()->company_id)
            {

                $pbc = DB::table('project_blogger_conditions')->find(Input::get('pbc_id'));

                $budget = Budget::find($pbc->budget_id);

                if($budget->payment_agree_company==0 && $budget->payment_agree_blogger==1)
                {
                    DB::table('budget')
                        ->where('id', '=', $pbc->budget_id)
                        ->update(array(
                            'payment_agree_blogger' =>  1,
                            'payment_agree_company' =>  1
                        ));

                    /* accept project */
                    DB::table('project_blogger_conditions')
                        ->where('id', '=', Input::get('pbc_id'))
                        ->update(array(
                            'status'    =>  2
                        ));

                    $company = DB::table('companies')
                        ->find(Auth::user()->company_id);
                    $mdata = array();
                    $mdata['project_title'] = $project->title;
                    $mdata['project_id'] = $project_id;
                    $mdata['company_name'] = $company->company_name;

                    NoticeController::notice_blogger($pbc->user_id, 3, $mdata);


                    Eventlog::create(array(
                        'user_id'       => Auth::user()->id,
                        'company_id'    => Auth::user()->company_id,
                        'event_id'      => 24,
                        'project_id'    => $project_id,
                        'sub_user_id'   => $pbc->user_id,/* blogger_id */
                        'post_id'       => null,
                        'blogger_check' => 0,
                        'company_check' => 1
                    ));

                    return Redirect::back()->with('global', trans('voc.changes_saved'));
                }
                else
                {
                    return App::abort(401, trans('error.no_rights'));
                }

            } else { return App::abort(401, trans('error.no_rights')); }
        } else { return App::abort(401, 'no pbc_id'); }
    }

    /**
	 * Refuse project.
	 *
	 * @param  int  $project_id
	 * @return Response
	 */
	public function refuse($project_id)
	{
        $project = Project::find($project_id);

        if($project)
        {
            DB::table('project_blogger_conditions')
                ->where('project_id', '=', $project_id)
                ->where('user_id', '=', Auth::user()->id)
                ->update(array(
                    'status'    =>  7
                ));

            Eventlog::create(array(
                'user_id'           => Auth::user()->id,// blogger
                'event_id'          => 27,
                'project_id'        => $project_id,
                'sub_company_id'    => $project->company_id,// company
                'post_id'           => null,
                'blogger_check'     => 1,
                'company_check'     => 0
            ));

            return Redirect::to('projects/')->with('global', trans('voc.project_declined'));
        }
        else
        {
            return App::abort(401);
        }
	}

    /**
     * Confirm project details.
     *
     * @param  int  $project_id
     * @return Response
     */
    public function confirm($project_id)
    {
        $project = Project::find($project_id);

        $validator = Validator::make(Input::all(),
            array(
                'confirmation'          => 'required',
                'project_post_id'     => 'required',
            )
        );

        if ($validator->fails())
        {
            return App::abort(401);
        }
        else
        {
            if($project->company_id == Auth::user()->company_id)
            {
                $post = DB::table('project_posts')
                    ->where('id', '=', Input::get('project_post_id'))
                    ->first();
                $user = Auth::user();
                if($post)
                {
                    $mdata = array();

                    $company = DB::table('companies')
                        ->find($user->company_id);

                    $mdata['project_title'] = $project->title;
                    $mdata['category_id'] = $project->category_id;
                    $mdata['project_id'] = $project->id;
                    $mdata['company_name'] = $company->company_name;

                    switch(Input::get('confirmation'))
                    {
                        case('video_title_confirmation'):
                            DB::table('project_posts')
                                ->where('id', '=', Input::get('project_post_id'))
                                ->update(array('title_confirmation' => 1));

                            $mdata['confirmation'] = 'title';

                            Eventlog::create(array(
                                'user_id'       => $user->id,
                                'company_id'    => $user->company_id,/* company_id */
                                'event_id'      => 9,
                                'project_id'    => $project_id,
                                'sub_user_id'   => $post->user_id,
                                'post_id'       => null,
                                'blogger_check' => 0,
                                'company_check' => 1
                            ));
                            break;
                        case('ig_title_confirmation'):
                            DB::table('project_posts')
                                ->where('id', '=', Input::get('project_post_id'))
                                ->update(array('ig_title_confirmation' => 1));

                            $mdata['confirmation'] = 'title';

                            Eventlog::create(array(
                                'user_id'       => $user->id,
                                'company_id'    => $user->company_id,/* company_id */
                                'event_id'      => 35,
                                'project_id'    => $project_id,
                                'sub_user_id'   => $post->user_id,
                                'post_id'       => null,
                                'blogger_check' => 0,
                                'company_check' => 1
                            ));
                            break;
                        case('video_script_confirmation'):
                            DB::table('project_posts')
                                ->where('id', '=', Input::get('project_post_id'))
                                ->update(array('script_confirmation' => 1));

                            $mdata['confirmation'] = 'script';

                            Eventlog::create(array(
                                'user_id'       => $user->id,
                                'company_id'    => $user->company_id,/* company_id */
                                'event_id'      => 10,
                                'project_id'    => $project_id,
                                'sub_user_id'   => $post->user_id,
                                'post_id'       => null,
                                'blogger_check' => 0,
                                'company_check' => 1
                            ));
                            break;
                        case('ppt_confirmation'):
                            DB::table('project_posts')
                                ->where('id', '=', Input::get('project_post_id'))
                                ->update(array('ppt_confirmation' => 1));

                            $mdata['confirmation'] = 'product_text';

                            Eventlog::create(array(
                                'user_id'       => $user->id,
                                'company_id'    => $user->company_id,/* company_id */
                                'event_id'      => 11,
                                'project_id'    => $project_id,
                                'sub_user_id'   => $post->user_id,
                                'post_id'       => null,
                                'blogger_check' => 0,
                                'company_check' => 1
                            ));
                            break;
                        case('products_confirmation'):
                            DB::table('project_posts')
                                ->where('id', '=', Input::get('project_post_id'))
                                ->update(array('products_confirmation' => 1));

                            $mdata['confirmation'] = 'products';

                            Eventlog::create(array(
                                'user_id'       => $user->id,
                                'company_id'    => $user->company_id,/* company_id */
                                'event_id'      => 12,
                                'project_id'    => $project_id,
                                'sub_user_id'   => $post->user_id,
                                'post_id'       => null,
                                'blogger_check' => 0,
                                'company_check' => 1
                            ));
                            break;
                        case('release_date_confirmation'):
                            DB::table('project_posts')
                                ->where('id', '=', Input::get('project_post_id'))
                                ->update(array('release_date_confirmation' => 1));

                            $mdata['confirmation'] = 'release_date';

                            Eventlog::create(array(
                                'user_id'       => $user->id,/* company_id */
                                'company_id'    => $user->company_id,
                                'event_id'      => 13,
                                'project_id'    => $project_id,
                                'sub_user_id'   => $post->user_id,
                                'post_id'       => null,
                                'blogger_check' => 0,
                                'company_check' => 1
                            ));
                            break;
                        case('ig_release_date_confirmation'):
                            DB::table('project_posts')
                                ->where('id', '=', Input::get('project_post_id'))
                                ->update(array('ig_release_date_confirmation' => 1));

                            $mdata['confirmation'] = 'release_date';

                            Eventlog::create(array(
                                'user_id'       => $user->id,/* company_id */
                                'company_id'    => $user->company_id,
                                'event_id'      => 38,
                                'project_id'    => $project_id,
                                'sub_user_id'   => $post->user_id,
                                'post_id'       => null,
                                'blogger_check' => 0,
                                'company_check' => 1
                            ));
                            break;
                        case('video_release_confirmation'):
                            DB::table('project_posts')
                                ->where('id', '=', Input::get('project_post_id'))
                                ->update(array('video_release_confirmation' => 1));

                            $mdata['confirmation'] = 'video_release_confirmation';

                            Eventlog::create(array(
                                'user_id'       => $user->id,
                                'company_id'    => $user->company_id,/* company_id */
                                'event_id'      => 14,
                                'project_id'    => $project_id,
                                'sub_user_id'   => $post->user_id,
                                'post_id'       => $post->post_id,
                                'blogger_check' => 0,
                                'company_check' => 1
                            ));
                            $status = DB::table('post_stats')
                                ->where('post_id', '=', $post->post_id)
                                ->orderBy('date', 'desc')
                                ->first();

                            if(!empty($status))
                            {
                                if(!empty($status->status) && $status->status=='public')
                                {
                                    DB::table('project_blogger_conditions')
                                        ->where('project_id', '=', $project_id)
                                        ->where('user_id', '=', $post->user_id)
                                        ->update(array(
                                            'status' =>  '5'
                                        ));
                                }
                                else
                                {
                                    DB::table('project_blogger_conditions')
                                        ->where('project_id', '=', $project_id)
                                        ->where('user_id', '=', $post->user_id)
                                        ->update(array(
                                            'status' =>  '4'
                                        ));
                                }
                            }
                            else
                            {
                                DB::table('project_blogger_conditions')
                                    ->where('project_id', '=', $project_id)
                                    ->where('user_id', '=', $post->user_id)
                                    ->update(array(
                                        'status' =>  '4'
                                    ));
                            }


                            break;
                    }

                    NoticeController::notice_blogger($post->user_id, 4, $mdata);

                }
                else
                {
                    return App::abort(401);
                }
                return 'ok';
            }
            else
            {
                return App::abort(401);
            }
        }
    }

    /**
     *
     */
    public function haggle_request()
    {
        $project_id = (Input::get('project_id'))?Input::get('project_id'):null;

        $project = Project::find($project_id);

        if($project)
        {
            $youtube_payment = (Input::get('youtube_payment'))?Input::get('youtube_payment'):0;
            $instagram_payment = (Input::get('instagram_payment'))?Input::get('instagram_payment'):0;

            /* company changes */
            if(Auth::user()->role==3){

                $user = (Input::get('user_id'))?Input::get('user_id'):null;

                DB::table('project_requests')
                    ->where('project_id', '=', $project_id)
                    ->where('user_id', '=', $user)
                    ->update(array(
                        'youtube_payment'=>$youtube_payment,
                        'instagram_payment'=>$instagram_payment,
                        'company_agree'=>'1',
                        'blogger_agree'=>'0'
                    ));

                $mdata = array();

                $company = DB::table('companies')
                    ->find(Auth::user()->company_id);

                $mdata['project_title'] = $project->title;
                $mdata['category_id'] = $project->category_id;
                $mdata['project_id'] = $project_id;
                $mdata['company_name'] = $company->company_name;

                NoticeController::notice_blogger($user, 2, $mdata);

                Eventlog::create(array(
                    'user_id'       => Auth::user()->id,
                    'company_id'    => Auth::user()->company_id,/* company_id */
                    'event_id'      => 25,
                    'project_id'    => $project_id,
                    'sub_user_id'   => $user,
                    'post_id'       => null,
                    'blogger_check' => 0,
                    'company_check' => 1
                ));
            }
            /* blogger changes */
            elseif(Auth::user()->role==2){

                $user = Auth::user()->id;

                DB::table('project_requests')
                    ->where('project_id', '=', $project_id)
                    ->where('user_id', '=', $user)
                    ->update(array(
                        'youtube_payment'=>$youtube_payment,
                        'instagram_payment'=>$instagram_payment,
                        'company_agree'=>'0',
                        'blogger_agree'=>'1'
                    ));

                $mdata = array();
                $mdata['blogger_name'] = Auth::user()->username;
                $mdata['project_title'] = $project->title;
                $mdata['project_id'] = $project_id;
                NoticeController::notice_company($project->company_id, 4, $mdata);

                Eventlog::create(array(
                    'user_id' => $user,
                    'event_id' => 23,
                    'project_id' => $project_id,
                    'sub_company_id' => $project->company_id,
                    'post_id' => null,
                    'blogger_check' => 1,
                    'company_check' => 0
                ));
            }


            return Redirect::back();

        }
        else
        {
            return App::abort(401);
        }

    }


    /**
     * Decline project details.
     *
     * @param  int  $project_id
     * @return Response
     */
    public function decline($project_id)
    {
        $project = Project::find($project_id);

        $validator = Validator::make(Input::all(),
            array(
                'confirmation'          => 'required',
                'project_post_id'     => 'required',
            )
        );

        if ($validator->fails())
        {
            return App::abort(401);
        }
        else
        {
            if($project->company_id == Auth::user()->company_id)
            {
                $now = DB::raw('now()');

                $post = DB::table('project_posts')
                    ->where('id', '=', Input::get('project_post_id'))
                    ->first();
                if($post)
                {
                    $mdata = array();

                    $company = DB::table('companies')
                        ->find(Auth::user()->company_id);

                    $mdata['project_title'] = $project->title;
                    $mdata['project_id'] = $project->id;
                    $mdata['company_name'] = $company->company_name;

                    switch(Input::get('confirmation'))
                    {
                        case('video_title_confirmation'):
                            DB::table('project_posts')
                                ->where('id', '=', Input::get('project_post_id'))
                                ->update(array('title_confirmation' => 2));
                            DB::table('project_comments')
                                ->insert(array(
                                    'author_user_id'        => Auth::user()->id,
                                    'company_id'            => Auth::user()->company_id,
                                    'project_id'            => $project_id,
                                    'receiver_user_id'      => Input::get('receiver_id'),
                                    'text'                  => Input::get('comment'),
                                    'date'                  => $now,
                                    'type'                  => '1',
                                ));
                            Eventlog::create(array(
                                'user_id' => Auth::user()->id,
                                'company_id'    =>  Auth::user()->company_id,/* company_id */
                                'event_id' => 15,
                                'project_id' => $project_id,
                                'sub_user_id' => $post->user_id,
                                'post_id' => $post->post_id,
                                'blogger_check' => 0,
                                'company_check' => 1
                            ));
                            break;
                        case('ig_title_confirmation'):
                            DB::table('project_posts')
                                ->where('id', '=', Input::get('project_post_id'))
                                ->update(array('ig_title_confirmation' => 2));
                            DB::table('project_comments')
                                ->insert(array(
                                    'author_user_id'        => Auth::user()->id,
                                    'company_id'            => Auth::user()->company_id,
                                    'project_id'            => $project_id,
                                    'receiver_user_id'      => Input::get('receiver_id'),
                                    'text'                  => Input::get('comment'),
                                    'date'                  => $now,
                                    'type'                  => '7',
                                ));
                            Eventlog::create(array(
                                'user_id' => Auth::user()->id,
                                'company_id'    =>  Auth::user()->company_id,/* company_id */
                                'event_id' => 36,
                                'project_id' => $project_id,
                                'sub_user_id' => $post->user_id,
                                'post_id' => $post->post_id,
                                'blogger_check' => 0,
                                'company_check' => 1
                            ));
                            break;
                        case('video_script_confirmation'):
                            DB::table('project_posts')
                                ->where('id', '=', Input::get('project_post_id'))
                                ->update(array('script_confirmation' => 2));
                            DB::table('project_comments')
                                ->insert(array(
                                    'author_user_id'    => Auth::user()->id,
                                    'company_id'        => Auth::user()->company_id,
                                    'project_id'        => $project_id,
                                    'receiver_user_id'  => Input::get('receiver_id'),
                                    'text'              => Input::get('comment'),
                                    'date'              => $now,
                                    'type'              => '2',
                                ));
                            Eventlog::create(array(
                                'user_id'       => Auth::user()->id,/* company_id */
                                'company_id'    => Auth::user()->company_id,
                                'event_id'      => 16,
                                'project_id'    => $project_id,
                                'sub_user_id'   => $post->user_id,
                                'post_id'       => $post->post_id,
                                'blogger_check' => 0,
                                'company_check' => 1
                            ));
                            break;
                        case('ppt_confirmation'):
                            DB::table('project_posts')
                                ->where('id', '=', Input::get('project_post_id'))
                                ->update(array('ppt_confirmation' => 2));
                            DB::table('project_comments')
                                ->insert(array(
                                    'author_user_id'        => Auth::user()->id,
                                    'company_id'            => Auth::user()->company_id,
                                    'project_id'            => $project_id,
                                    'receiver_user_id'      => Input::get('receiver_id'),
                                    'text'                  => Input::get('comment'),
                                    'date'                  => $now,
                                    'type'                  => '3',
                                ));
                            Eventlog::create(array(
                                'user_id'       => Auth::user()->id,
                                'company_id'    => Auth::user()->company_id,/* company_id */
                                'event_id'      => 17,
                                'project_id'    => $project_id,
                                'sub_user_id'   => $post->user_id,
                                'post_id'       => $post->post_id,
                                'blogger_check' => 0,
                                'company_check' => 1
                            ));
                            break;
                        case('products_confirmation'):
                            DB::table('project_posts')
                                ->where('id', '=', Input::get('project_post_id'))
                                ->update(array('products_confirmation' => 2));
                            DB::table('project_comments')
                                ->insert(array(
                                    'author_user_id'        => Auth::user()->id,
                                    'company_id'            => Auth::user()->company_id,
                                    'project_id'            => $project_id,
                                    'receiver_user_id'      => Input::get('receiver_id'),
                                    'text'                  => Input::get('comment'),
                                    'date'                  => $now,
                                    'type'                  => '4',
                                ));
                            Eventlog::create(array(
                                'user_id'           => Auth::user()->id,
                                'company_id'        => Auth::user()->company_id,/* company_id */
                                'event_id'          => 18,
                                'project_id'        => $project_id,
                                'sub_user_id'       => $post->user_id,
                                'post_id'           => $post->post_id,
                                'blogger_check'     => 0,
                                'company_check'     => 1
                            ));
                            break;
                        case('release_date_confirmation'):
                            DB::table('project_posts')
                                ->where('id', '=', Input::get('project_post_id'))
                                ->update(array('release_date_confirmation' => 2));
                            DB::table('project_comments')
                                ->insert(array(
                                    'author_user_id'        => Auth::user()->id,
                                    'company_id'            => Auth::user()->company_id,
                                    'project_id'            => $project_id,
                                    'receiver_user_id'      => Input::get('receiver_id'),
                                    'text'                  => Input::get('comment'),
                                    'date'                  => $now,
                                    'type'                  => '5',
                                ));
                            Eventlog::create(array(
                                'user_id'           => Auth::user()->id,
                                'company_id'        => Auth::user()->company_id,/* company_id */
                                'event_id'          => 19,
                                'project_id'        => $project_id,
                                'sub_user_id'       => $post->user_id,
                                'post_id'           => $post->post_id,
                                'blogger_check'     => 0,
                                'company_check'     => 1
                            ));
                            break;
                        case('ig_release_date_confirmation'):
                            DB::table('project_posts')
                                ->where('id', '=', Input::get('project_post_id'))
                                ->update(array('ig_release_date_confirmation' => 2));
                            DB::table('project_comments')
                                ->insert(array(
                                    'author_user_id'        => Auth::user()->id,
                                    'company_id'            => Auth::user()->company_id,
                                    'project_id'            => $project_id,
                                    'receiver_user_id'      => Input::get('receiver_id'),
                                    'text'                  => Input::get('comment'),
                                    'date'                  => $now,
                                    'type'                  => '8',
                                ));
                            Eventlog::create(array(
                                'user_id'           => Auth::user()->id,
                                'company_id'        => Auth::user()->company_id,/* company_id */
                                'event_id'          => 39,
                                'project_id'        => $project_id,
                                'sub_user_id'       => $post->user_id,
                                'post_id'           => $post->post_id,
                                'blogger_check'     => 0,
                                'company_check'     => 1
                            ));
                            break;
                        case('video_release_confirmation'):
                            DB::table('project_posts')
                                ->where('id', '=', Input::get('project_post_id'))
                                ->update(array('video_release_confirmation' => 2));
                            DB::table('project_comments')
                                ->insert(array(
                                    'author_user_id'        => Auth::user()->id,
                                    'company_id'            => Auth::user()->company_id,
                                    'project_id'            => $project_id,
                                    'receiver_user_id'      => Input::get('receiver_id'),
                                    'text'                  => Input::get('comment'),
                                    'date'                  => $now,
                                    'type'                  => '6',
                                ));
                            Eventlog::create(array(
                                'user_id'       => Auth::user()->id,
                                'company_id'    => Auth::user()->company_id,/* company_id */
                                'event_id'      => 20,
                                'project_id'    => $project_id,
                                'sub_user_id'   => $post->user_id,
                                'post_id'       => $post->post_id,
                                'blogger_check' => 0,
                                'company_check' => 1
                            ));
                            DB::table('project_blogger_conditions')
                                ->where('project_id', '=', $project_id)
                                ->where('user_id', '=', $post->user_id)
                                ->update(array(
                                    'status' =>  '2'
                                ));
                            break;
                    }

                    NoticeController::notice_blogger($post->user_id, 4, $mdata);
                }
                else
                {
                    return App::abort(401);
                }
                $objDateTime = new DateTime('NOW'); return $objDateTime->format("Y-m-d H:i:s");
            }
            else
            {

            }
        }
    }

    /**
     * Comments.
     *
     * @param  int  $project_id
     * @return Response
     */
    public function comment($project_id)
    {
        $now = DB::raw('now()');

        $project = Project::find($project_id);
        $company_check = 0;
        $blogger_check = 0;
        if($project->company_id==Auth::user()->company_id)
        {
           $company_check = 1;
        }
        else
        {
            $blogger_check = 1;
        }

        try{

            if(Auth::user()->role==3)
            {
                DB::table('project_comments')
                    ->insert(array(
                        'author_user_id'        => Auth::user()->id,
                        'company_id'            => Auth::user()->company_id,
                        'project_id'            => $project_id,
                        'receiver_user_id'      => Input::get('receiver_id'),
                        'text'                  => Input::get('comment'),
                        'date'                  => $now,
                        'type'                  => '11',
                    ));
                $mdata = array();
                $mdata["chat_msg"] = Input::get('comment');
                $c = DB::table('companies')->find(Auth::user()->company_id);
                $mdata['company_name'] = $c->company_name;
                $mdata['project_title'] = $project->title;
                $mdata['project_id'] = $project_id;
                NoticeController::notice_blogger(Input::get('receiver_id'), 8, $mdata);

                Eventlog::create(array(
                    'user_id'       =>  Auth::user()->id,// sender
                    'company_id'    =>  Auth::user()->company_id,
                    'event_id'      =>  33,
                    'project_id'    =>  $project_id,
                    'sub_user_id'   =>  Input::get('receiver_id'),// receiver
                    'post_id'       =>  null,
                    'blogger_check' =>  $blogger_check,
                    'company_check' =>  $company_check
                ));
            }
            elseif(Auth::user()->role==2)
            {
                DB::table('project_comments')
                    ->insert(array(
                        'author_user_id'        => Auth::user()->id,
                        'company_id'            => null,
                        'project_id'            => $project_id,
                        'receiver_company_id'   => $project->company_id,
                        'text'                  => Input::get('comment'),
                        'date'                  => $now,
                        'type'                  => '11',
                    ));

                $mdata = array();
                $mdata["chat_msg"] = Input::get('comment');
                $mdata["blogger_name"] = Auth::user()->username;
                $mdata['project_title'] = $project->title;
                $mdata['project_id'] = $project_id;
                NoticeController::notice_company($project->company_id, 8, $mdata);

                Eventlog::create(array(
                    'user_id'           =>  Auth::user()->id,// sender
                    'event_id'          =>  33,
                    'project_id'        =>  $project_id,
                    'sub_company_id'    =>  $project->company_id,// receiver
                    'post_id'           =>  null,
                    'blogger_check'     =>  $blogger_check,
                    'company_check'     =>  $company_check
                ));
            }


            $objDateTime = new DateTime('NOW'); return $objDateTime->format("Y-m-d H:i:s");
        }
        catch(Exception $e)
        {
            return App::abort(401);
        }

    }

    /**
     * Update shipping status
     */
    public function update_shipping($project_id)
    {
        $project = Project::find($project_id);

        /* Blogger update */
        if(Auth::user()->role==2)
        {
            $blogger_id = Auth::user()->id;
            $status = (Input::get('status'))?Input::get('status'):0;

            if(!empty($status))
            {
                try
                {
                    DB::table('shipping_details')
                        ->where('blogger_id', '=', $blogger_id)
                        ->where('project_id', '=', $project_id)
                        ->update(array(
                            'status'    =>  $status
                        ));

                    if($status==2)
                    {

                        $mdata = array();
                        $mdata['project_id'] = $project->id;
                        $mdata['project_title'] = $project->title;
                        $mdata['blogger_name'] = Auth::user()->username;

                        NoticeController::notice_company($project->company_id, 6, $mdata);

                        Eventlog::create(array(
                            'user_id' => Auth::user()->id,// blogger
                            'event_id' => 29,
                            'project_id' => $project_id,
                            'sub_company_id' => $project->company_id,// company
                            'post_id' => null,
                            'blogger_check' => 1,
                            'company_check' => 0
                        ));
                    }

                    return 'ok';
                }
                catch(Exception $e)
                {
                    return $e;
                }
            }
        }
        /* Company update */
        elseif(Auth::user()->role==3)
        {
            if($project->company_id==Auth::user()->company_id)
            {

                $blogger_id = (Input::get('blogger_id'))?Input::get('blogger_id'):0;
                $status = (Input::get('status'))?Input::get('status'):0;
                $info = (Input::get('shipping_info'))?Input::get('shipping_info'):'';

                try
                {
                    DB::table('shipping_details')
                        ->where('blogger_id', '=', $blogger_id)
                        ->where('project_id', '=', $project_id)
                        ->update(array(
                            'info'      =>  $info,
                            'status'    =>  $status
                        ));

                    if($status==1)
                    {
                        $company = DB::table('companies')
                            ->find(Auth::user()->company_id);
                        $mdata = array();
                        $mdata['company_name'] = $company->company_name;
                        $mdata['project_title'] = $project->title;
                        $mdata['project_id'] = $project_id;

                        NoticeController::notice_blogger($blogger_id, 6, $mdata);

                        Eventlog::create(array(
                            'user_id' => Auth::user()->id,// company
                            'company_id'    =>  Auth::user()->company_id,
                            'event_id' => 28,
                            'project_id' => $project_id,
                            'sub_user_id' => $blogger_id,// blogger
                            'post_id' => null,
                            'blogger_check' => 0,
                            'company_check' => 1
                        ));
                    }

                }
                catch(Exception $e)
                {
                    return Redirect::back()
                    ->withInput()
                    ->with('global', trans('error.error').' #op006');
                }

                return Redirect::back()
                    ->with('global', trans('voc.changes_saved'));
            }
            else
            {
                return Redirect::back()
                    ->with('global_error', trans('error.no_rights'));
            }
        }
    }

    /**
     *  Update event logs
     */
    public function update_events()
    {
        if(Input::get('project_id') && Auth::user()->role==2)
        {
            try{
                DB::table('eventlogs')
                    ->where('sub_user_id', '=', Auth::user()->id)
                    ->where('project_id', '=', Input::get('project_id'))
                    ->update(array(
                        'blogger_check' =>  '1'
                    ));
            } catch(Exception $e){ return $e; }
        }
        elseif(Input::get('project_id') && Input::get('blogger_id') && Auth::user()->role==3)
        {
            try{
            DB::table('eventlogs')
                ->where('sub_company_id', '=', Auth::user()->company_id)
                ->where('user_id', '=', Input::get('blogger_id'))
                ->where('project_id', '=', Input::get('project_id'))
                ->update(array(
                    'company_check' =>  '1'
                ));
            } catch(Exception $e){ return $e; }
        }
    }

    /**
     *  Create project payment page view
    */
    public function get_payment($project_id)
    {
        $project = Project::find($project_id);

        if($project->company_id == Auth::user()->company_id)
        {
            $bloggers = DB::table('project_blogger_conditions AS pb')
                ->leftJoin('budget AS b', 'b.id', '=', 'pb.budget_id')
                ->leftJoin('blogs AS bl', 'bl.id', '=', 'pb.blog_id')
                ->leftJoin('transactions AS tr', 'tr.bt_id', '=', 'b.bt_id')
                ->select('pb.status AS project_status', 'b.youtube_payment', 'bl.thumb', 'tr.bt_id', 'bl.user_id AS blogger_id',
                    'bl.title', 'b.status AS budget_status', 'b.id AS budget_id', 'b.cur_id')
                ->where('pb.project_id', '=', $project_id)
                //->whereIn('pb.status', array(2,3,4,5))
                ->orderBy('pb.status', 'desc')
                ->get();

            $company_service_fee = DB::table('companies')
                ->where('id', '=', Auth::user()->company_id)
                ->select('service_fee')
                ->first();

            $transactions = DB::table('transactions AS t')
                ->leftJoin('budget AS b', 'b.bt_id', '=', 't.bt_id')
                ->where('b.project_id', '=', $project_id)
                ->select('t.bt_id', 't.id', 't.sum', 't.created_at')
                ->groupBy('t.bt_id')
                ->get();

            if(!empty($company_service_fee->service_fee))
            {
                $company_fee = $company_service_fee->service_fee/100;
            }else{
                $company_fee = 0.1;
            }

            return View::make('openproject/payment', array(
                'title'             =>  'Project Payment',
                'service_fee'       =>  $company_fee,
                'bloggers'          =>  $bloggers,
                'project_id'        =>  $project_id,
                'project'           =>  $project,
                'token'             =>  Braintree_ClientToken::generate(),
                'transactions'      =>  $transactions,
            ));
        } else {
            App::missing(function($exception)
            {
                return Response::view('errors.missing', array('title'=>'No rights', 'error' => trans('voc.ermsg_no_rights')), 404);
            });
            return App::abort(404);
        }

    }

    /**
     *  Process payment
     */
    public function post_payment($project_id)
    {

        if(Input::get('budget')==null)
        {
            return Redirect::back()->withInput()->with('global', 'Select bloggers');
        }

        $bloggers = DB::table('project_blogger_conditions AS pb')
            ->leftJoin('budget AS b', 'b.id', '=', 'pb.budget_id')
            ->select('b.youtube_payment', 'b.id AS budget_id')
            ->where('pb.project_id', '=', $project_id)
            ->whereIn('b.id', Input::get('budget'))
            ->get();

        $sum = 0;
        $company_service_fee = DB::table('companies')
            ->where('id', '=', Auth::user()->company_id)
            ->select('service_fee')
            ->first();

        if(!empty($company_service_fee->service_fee))
        {
            $company_fee = $company_service_fee->service_fee/100;
        }else{
            $company_fee = 0.1;
        }

        foreach($bloggers as $blogger)
        {
            $sum += $blogger->youtube_payment;
        }
        $fee = $sum*$company_fee;
        //$fee = $sum*0.1;
        $total = $sum + $fee;

        if($total==Input::get('total_sum')*1)
        {

            /* Get customer id */
            $customer_id = null;
            $customer = DB::table('companies')
                ->where('id', '=', Auth::user()->company_id)
                ->first();

            $customer_id = ($customer->bt_id)?$customer->bt_id:null;

            /* Transaction */
            try
            {
                $result = Braintree_Transaction::sale([
                    'amount' => $total,
                    'paymentMethodNonce' => Input::get('payment_method_nonce'),
                    'options' => [
                        'submitForSettlement' => True
                    ],
                    'customerId'  =>  $customer_id
                ]);


            } catch (Exception $e) {
                // This shouldn't happen. It's some problem between us and Braintree, having nothing to do with the client
                Log::error('WEIRD UNEXPECTED ERROR doing the Braintree transaction, happened BEFORE result', array('Error' => print_r($e, true)));
                return Redirect::back()->withInput()->with('global_error', 'We encountered an unexpected credit card processing error. Please try again. If the error persists, please contact us.');
            }
            if (! $result->success) {
                //print_r($result);die;
                // There's been an error with their transaction
                Log::error('Error processing registration', array('Error' => print_r($result, true)));
                return Redirect::back()->withInput()->with('global_error', 'Sorry, we couldn\'t process your transaction.
                Please try checking your information again, or try a different card.
                Make sure the billing address you gave matches the address on file with your credit-card company.');
            }
            else
            {
                $now = DB::raw('now()');
                foreach(Input::get('budget') as $budget)
                {
                    /* Budget update */
                    try
                    {
                        Budget::where('id', '=', $budget)
                        ->update(array('status' => 'p', 'bt_id' => $result->transaction->id));
                    }
                    catch(Exception $e){
                        return Redirect::back()->with('global_error', 'Error updating budget');
                    }
                }
                DB::table('transactions')
                    ->insert(array(
                        'user_id'   =>  Auth::user()->id,
                        'company_id'=>  Auth::user()->company_id,
                        'bt_id'     =>  $result->transaction->id,
                        'service_fee'=> $fee,
                        'sum'       =>  $total,
                        'status'    =>  $result->transaction->status,
                        'created_at'=>  $now,
                        'updated_at'=>  $now
                    ));

                return Redirect::back()
                    ->with('global', trans('submitted_for_settlement'));
            }
        }
        else
        {
            return Redirect::back()->withInput()->with('global_error', 'Wrong sum');
        }

    }

    /**
     *  HTML report
     */
    public function html_report($project_id)
    {
        $project = Project::find($project_id);

        if(Auth::user()->company_id==$project->company_id || Auth::user()->role==4)
        {
            $data = $this->report_data($project_id);

            return View::make('openproject.report', array('title' => 'Project report', 'data' => $data, 'project' => $project));
        }
        else
        {
            App::missing(function($exception)
            {
                return Response::view('errors.missing', array('title'=>'No rights', 'error' => trans('voc.ermsg_no_rights')), 404);
            });
            return App::abort(404);
        }
    }

    /**
     *  PDF report
     */
    public function pdf_report($project_id)
    {
        $project = Project::find($project_id);

        if(Auth::user()->company_id==$project->company_id || Auth::user()->role==4)
        {
            $data = $this->report_data($project_id);

            $pdf = PDF::loadView('openproject.report_pdf', array('data'=>$data));
            return $pdf->stream('p_report_'.$project_id.'.pdf');
        }
        else
        {
            App::missing(function($exception)
            {
                return Response::view('errors.missing', array('title'=>'No rights', 'error' => trans('voc.ermsg_no_rights')), 404);
            });
            return App::abort(404);
        }
    }

    /**
     *  Get report data
     */
    private function report_data($project_id)
    {

        $project = Project::find($project_id);

        $data = array();

        $bloggers = DB::table('project_blogger_conditions')
            ->where('project_id', '=', $project_id)
            ->orderBy('status', 'DESC')
            ->get();


        $count = count($bloggers);
        $data['count'] = $count;

        foreach($bloggers as $blogger)
        {

            $data[$blogger->user_id]['budget'] = DB::table('budget')
                ->where('id', '=', $blogger->budget_id)
                ->first();

            $data[$blogger->user_id]['blog'] = DB::table('blogs')
                ->where('id', '=', $blogger->blog_id)
                ->first();

            $data[$blogger->user_id]['post'] = DB::table('project_posts')
                ->leftJoin('posts', 'posts.id', '=', 'project_posts.post_id')
                ->where('project_posts.project_id', '=', $project_id)
                ->where('project_posts.user_id', '=', $blogger->user_id)
                ->first();

            $data[$blogger->user_id]['project_medias'] = DB::table('project_medias')
                ->where('project_id', '=', $project_id)
                ->get();

            if($blogger->status==1){
                if($data[$blogger->user_id]['budget']->payment_agree_blogger==1){
                    $data[$blogger->user_id]['project_status'] = trans('status.c1-1');
                }
                elseif($data[$blogger->user_id]['budget']->payment_agree_company==1){
                    $data[$blogger->user_id]['project_status'] = trans('status.c1-2');
                }
            }
            elseif($blogger->status==2){
                if(!empty($data[$blogger->user_id]['post']))
                {
                    if($data[$blogger->user_id]['post']->title_confirmation==null && $data[$blogger->user_id]['post']->script_confirmation==null
                        && $data[$blogger->user_id]['post']->ppt_confirmation==null && $data[$blogger->user_id]['post']->products_confirmation==null
                        && $data[$blogger->user_id]['post']->release_date_confirmation==null && $data[$blogger->user_id]['post']->video_release_confirmation==null){
                        $data[$blogger->user_id]['project_status'] = trans('status.c2-1');
                    }elseif($data[$blogger->user_id]['post']->title_confirmation==0 || $data[$blogger->user_id]['post']->script_confirmation==0
                        || $data[$blogger->user_id]['post']->ppt_confirmation==0 || $data[$blogger->user_id]['post']->products_confirmation==0
                        || $data[$blogger->user_id]['post']->release_date_confirmation==0 || $data[$blogger->user_id]['post']->video_release_confirmation==0){
                        $data[$blogger->user_id]['project_status'] = trans('status.c2-2');
                    }
                }
                else
                {
                    $data[$blogger->user_id]['project_status'] = trans('status.c2-1');
                }
            }
            elseif($blogger->status==3){
                $data[$blogger->user_id]['project_status'] = trans('status.c3');
            }
            elseif($blogger->status==4){
                $data[$blogger->user_id]['project_status'] = trans('status.c4');
            }
            elseif($blogger->status==5){
                $data[$blogger->user_id]['project_status'] = trans('status.c5');
            }
            elseif($blogger->status==6 || $blogger->status==7){
                $data[$blogger->user_id]['project_status'] = trans('status.c7');
            }

            if(!empty($data[$blogger->user_id]['blog']->yt_avg_views) && !empty($data[$blogger->user_id]['budget']->youtube_payment))
            {
                $data[$blogger->user_id]['avg_view_cost'] = round($data[$blogger->user_id]['budget']->youtube_payment/$data[$blogger->user_id]['blog']->yt_avg_views, 2);
                $data[$blogger->user_id]['avg_view_cost_1000'] = round($data[$blogger->user_id]['budget']->youtube_payment/$data[$blogger->user_id]['blog']->yt_avg_views * 1000, 2);
            }

            if(!empty($data[$blogger->user_id]['post']->blog_link))
            {
                $data[$blogger->user_id]['post_stats'] = DB::table('post_stats')
                    ->where('post_id', '=', $data[$blogger->user_id]['post']->post_id)
                    ->orderBy('id', 'desc')
                    ->limit('1')
                    ->first();

                if(!empty($data[$blogger->user_id]['post_stats']->views) && !empty($data[$blogger->user_id]['budget']->youtube_payment))
                {
                    $data[$blogger->user_id]['post_view_cost'] = round($data[$blogger->user_id]['budget']->youtube_payment / $data[$blogger->user_id]['post_stats']->views, 2);
                    $data[$blogger->user_id]['post_view_cost_1000'] = round($data[$blogger->user_id]['budget']->youtube_payment / $data[$blogger->user_id]['post_stats']->views * 1000, 2);
                }

            }

            if($project->shipping==1)
            {
                $data[$blogger->user_id]['shipping'] = DB::table('shipping_details')
                    ->where('project_id', '=', $project_id)
                    ->where('blogger_id', '=', $blogger->user_id)
                    ->first();
            }

        }

        $req_bloggers = DB::table('project_requests')
            ->where('project_id', '=', $project_id)
            ->where('status', '=', DB::raw('1'))
            ->get();

        if(!empty($req_bloggers))
        {
            $count_req = count($req_bloggers);
            $data['count'] += $count_req;

            foreach($req_bloggers as $blogger)
            {

                $data[$blogger->user_id]['blog'] = DB::table('blogs')
                    ->where('user_id', '=', $blogger->user_id)
                    ->where('media_id', '=', DB::raw('1'))
                    ->first();

                $data[$blogger->user_id]['post'] = DB::table('project_posts')
                    ->leftJoin('posts', 'posts.id', '=', 'project_posts.post_id')
                    ->where('project_posts.project_id', '=', $project_id)
                    ->where('project_posts.user_id', '=', $blogger->user_id)
                    ->first();

                $data[$blogger->user_id]['project_medias'] = DB::table('project_medias')
                    ->where('project_id', '=', $project_id)
                    ->get();

                $data[$blogger->user_id]['project_status'] = trans('status.c1-1');

                if(!empty($data[$blogger->user_id]['blog']->yt_avg_views) && !empty($data[$blogger->user_id]['budget']->youtube_payment))
                {
                    $data[$blogger->user_id]['avg_view_cost'] = round($blogger->youtube_payment/$data[$blogger->user_id]['blog']->yt_avg_views, 2);
                    $data[$blogger->user_id]['avg_view_cost_1000'] = round($blogger->youtube_payment/$data[$blogger->user_id]['blog']->yt_avg_views * 1000, 2);
                }

                $data[$blogger->user_id]['post_view_cost'] = 0;
                $data[$blogger->user_id]['post_view_cost_1000'] = 0;

                if($project->shipping==1)
                {
                    $data[$blogger->user_id]['shipping'] = DB::table('shipping_details')
                        ->where('project_id', '=', $project_id)
                        ->where('blogger_id', '=', $blogger->user_id)
                        ->first();
                }
            }
        }

        return $data;

    }

    public function set_post_stats($link, $id)
    {

        $client = new Google_Client();
        $DEVELOPER_KEY = $_ENV['GOOGLE_DEV_KEY'];
        $client->setDeveloperKey($DEVELOPER_KEY);

        if(!empty($link) && preg_match('%^((https?://)|(www\.))([a-z0-9-].?)+(:[0-9]+)?(/.*)?$%i', $link))
        {

            if(strpos($link, '?v='))
            {
                $videoId = substr(strstr($link, '?v='),3, 11);
            }
            elseif(strpos($link, 'youtu.be'))
            {
                $videoId = substr(strstr($link, 'be/'),3, 11);
            }

            $youtube = new Google_Service_YouTube($client);
            $video_stats = $youtube->videos->listVideos('snippet, statistics, status', array(
                'id'    =>  $videoId,
            ));

            foreach($video_stats['items'] as $val)
            {
                $views          = $val['statistics']['viewCount'];
                $yt_id          = $val->id;
                $likes          = $val['statistics']['likeCount'];
                $dislikes       = $val['statistics']['dislikeCount'];
                $comments       = $val['statistics']['commentCount'];
                $status         = $val['status']['privacyStatus'];
                $title          = $val['snippet']['title'];
                $publish_date   = $val['snippet']['publishedAt'];
            }

            $now = DB::raw('NOW()');

            try{
                DB::table('posts')
                    ->where('id', '=', $id)
                    ->update(array('name'=>$title, 'release_date'=>$publish_date));

                DB::table('post_stats')
                    ->insert(array(
                        'post_id'   =>  $id,
                        'yt_id'     =>  $yt_id,
                        'views'     =>  $views,
                        'likes'     =>  $likes,
                        'dislikes'  =>  $dislikes,
                        'comments'  =>  $comments,
                        'status'    =>  $status,
                        'date'      =>  $now
                    ));
            }catch(Exception $e)
            {
                return false;
            }

            return $status;

        }

    }

    /***
     * Views counter
     */
    public function view_counter()
    {
        $project_id = Input::get('project_id');

        DB::table('project_details')
            ->where('project_id', '=', $project_id)
            ->increment('ad_views_counter');
    }

    public static function parse_url($content)
    {
        $re = '/((([A-Za-z]{3,9}:(?:\/\/)?)(?:[-;:&=\+\$,\w]+@)?[A-Za-z0-9.-]+|(?:www.|[-;:&=\+\$,\w]+@)[A-Za-z0-9.-]+)((?:\/[\+~%\/.\w-_]*)?\??(?:[-\+=&;%@.\w_]*)#?(?:[\w]*))?)/';
        $content = preg_replace($re, '<a href="http://$0" rel="nofollow">$0</a>', $content);

        return $content;
    }

    public static function short_url($url)
    {
        if (strlen($url) >= 40) {
            return substr($url, 0, 30). " ... " . substr($url, -5);
        }
        else {
            return $url;
        }
    }

    public static function reverse_datetime($date)
    {
        if(strlen($date)>10)
        {
            $format = 'Y-m-d H:i:s';
            $date = DateTime::createFromFormat($format, $date);

            return $date->format('d-m-Y H:i:s');
        }
        else
        {
            $format = 'Y-m-d';
            $date = DateTime::createFromFormat($format, $date);

            return $date->format('d-m-Y');
        }
    }

}

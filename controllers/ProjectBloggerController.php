<?php

class ProjectBloggerController extends \BaseController {

	/**
	 * Display a listing of the resource.
	 *
     * @param int $project_id
	 * @return Response
	 */
	public function index($project_id)
	{
        $b = DB::table('project_blogger_conditions AS bp')
            ->leftJoin('blogs AS b', function($join)
            {
                $join->on('b.user_id', '=', 'bp.user_id')
                    ->on('b.media_id', '=', 'bp.media_id');
            })
            ->leftJoin('users AS u', 'u.id', '=', 'bp.user_id' )
            ->leftJoin('medias AS m', 'm.id', '=', 'b.media_id')
            ->leftJoin('blog_categories AS c', 'c.id', '=', 'b.category_id')
            ->select('bp.id', 'u.id AS user_id', 'u.username', 'b.media_id', 'b.title', 'b.url', 'b.channel', 'm.name AS media', 'c.name AS category')
            ->where('bp.project_id', '=', $project_id)
            ->get();

        $bloggers = array();
        $a = 0;

        foreach($b as $v)
        {
            $bloggers[$a]['id'] = $v->id;
            $bloggers[$a]['user_id'] = $v->user_id;
            $bloggers[$a]['username'] = $v->username;
            $bloggers[$a]['media'] = $v->media;
            $bloggers[$a]['title'] = $v->title;
            $bloggers[$a]['url'] = $v->url;
            $bloggers[$a]['category'] = trans('category.'.$v->category);
            if($v->media_id==1)
            {
                $bloggers[$a]['info'] = BloggerController::getBloggerInfo(false, $v->channel);
            }
            elseif($v->media_id==2)
            {

            }
            $a++;
        }

        return View::make('projects.bloggers.index', array('title'=>trans('voc.project_bloggers'), 'pid'=>$project_id, 'bloggers'=>$bloggers ));
	}


	/**
	 * Show the form for creating a new resource.
	 *
	 * @param int $project_id
	 * @return Response
	 */
	public function create($project_id)
	{
        $b = DB::table('project_blogger_conditions AS bp')
            ->leftJoin('blogs AS b1', function($join){
                $join->on('bp.user_id', '=', 'b1.user_id')
                    ->on('b1.media_id', '=', DB::raw('1'));
            })
            ->leftJoin('blogs AS b2', function($join){
                $join->on('bp.user_id', '=', 'b2.user_id')
                    ->on('b2.media_id', '=', DB::raw('2'));
            })
            ->leftJoin('blog_categories AS c', 'c.id', '=', 'b1.category_id')
            ->leftJoin('budget AS bt', 'bp.budget_id', '=', 'bt.id')
            ->select('b1.id', 'c.name AS category',
                'b1.channel', 'b1.media_id AS yt_media', 'b1.title', 'b1.url', 'b1.user_id', 'b1.yt_avg_views', 'b1.yt_subscriptions',
                'b1.thumb',
                'b2.media_id AS ig_media', 'b2.ig_user_id', 'b2.ig_username', 'bp.status', 'b2.ig_avg_views', 'b2.ig_follows',
                'bt.youtube_payment', 'bt.payment_agree_company', 'bt.payment_agree_blogger', 'bt.instagram_payment', 'bt.id AS budget_id',
                'bt.cur_id'
            )
            ->where('bp.project_id', '=', $project_id)
            ->get();

        $bloggers = array();
        $a = 0;

        foreach($b as $v)
        {
            $bloggers[$a]['id'] = $v->id;
            $bloggers[$a]['user_id'] = $v->user_id;
            $bloggers[$a]['yt_media'] = $v->yt_media;
            $bloggers[$a]['ig_media'] = $v->ig_media;
            $bloggers[$a]['category'] = trans('category.'.$v->category);
            $bloggers[$a]['status'] = $v->status;
            $bloggers[$a]['yt_avg_views'] = $v->yt_avg_views;
            $bloggers[$a]['yt_subscriptions'] = $v->yt_subscriptions;
            $bloggers[$a]['ig_avg_views'] = $v->ig_avg_views;
            $bloggers[$a]['ig_follows'] = $v->ig_follows;
            $bloggers[$a]['youtube_payment'] = $v->youtube_payment;
            $bloggers[$a]['payment_agree_company'] = $v->payment_agree_company;
            $bloggers[$a]['payment_agree_blogger'] = $v->payment_agree_blogger;
            $bloggers[$a]['instagram_payment'] = $v->instagram_payment;
            $bloggers[$a]['budget_id'] = $v->budget_id;
            $bloggers[$a]['thumb'] = $v->thumb;
            $bloggers[$a]['cur_id'] = $v->cur_id;
            if($v->yt_media==1)
            {
                $bloggers[$a]['title'] = $v->title;
                $bloggers[$a]['url'] = $v->url;
            }
            $a++;
        }

        /* From Session */

        $session_bloggers = array();

        if(Session::get('bloggers'))
        {
            $bl_str = '';

            $s_bloggers = Session::get('bloggers');

            foreach($s_bloggers as $s_b)
            {
                $bl_str .= $s_b.', ';
            }
            $bl_str = rtrim(trim($bl_str), ',');

            $session_bloggers = DB::table('users AS u')
                ->leftJoin(DB::raw('(SELECT
                id AS yt_id,
                lang_id AS lang_id1,
                url AS yt_url,
                user_id AS user_id1,
                media_id AS yt_media,
                title AS yt_title,
                thumb,
                channel AS yt_channel,
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
                ig_user_id,
                ig_username,
                ig_media AS ig_media_count,
                ig_follows,
                ig_avg_views
                FROM blogs WHERE media_id="2") b2'), function($join){
                    $join->on('b2.user_id2','=', 'u.id');
                })
                ->whereRaw('
                u.id IN ('.$bl_str.')
                AND u.id NOT IN (SELECT user_id FROM project_blogger_conditions WHERE project_id="'.$project_id.'")
                AND (b1.yt_channel IS NOT NULL OR b2.ig_username IS NOT NULL)
                ')->get();
        }

        $medias = array();

        $m = DB::table('project_medias')
            ->where('project_id', '=', $project_id)
            ->get();
        foreach($m as $media)
        {
            if(!empty($media))
            {
                if($media->media_id==1)
                {
                    $medias[] = '1';
                }
                elseif($media->media_id==2)
                {
                    $medias[] = '2';
                }
            }
        }

        $c = DB::table('blog_categories')->where('id', '!=', DB::raw('99'))->get();

        $categories = array('0' => trans('category.category'));

        foreach($c as $v)
        {
            $categories[$v->id] = trans('category.'.$v->name);
        }

        $project = Project::find($project_id);

        $company = DB::table('companies')
            ->where('id', '=', Auth::user()->company_id)
            ->first();
        $langs[0] = trans('voc.all');

        foreach(DB::table('langs')->get() as $v)
        {
            $langs[$v->id] = $v->name;
        }

        $c = DB::table('blog_categories')->where('id', '!=', DB::raw('99'))->get();

        $theme = array('0' => trans('voc.all'));

        foreach($c as $v)
        {
            $theme[$v->id] = trans('category.'.$v->name);
        }

        $demography = array('1' => 'demo1', '2' => 'demo2');
        $subscribers = array(
            '1' => '1,000',
            '2' => '5,000',
            '3' => '10,000',
            '4' => '25,000',
            '5' => '50,000',
            '6' => '100,000',
        );
        $views = array(
            '1' => '1,000',
            '2' => '5,000',
            '3' => '10,000',
            '4' => '25,000',
            '5' => '50,000',
            '6' => '100,000'
        );

        $cur = $project->cur_id;

        $details = DB::table('project_details')
            ->where('project_id', '=', $project_id)
            ->first();

        return View::make('projects.bloggers.create', array(
                'title' => trans('voc.add_bloggers'),
                'bloggers' => $bloggers,
                'pid' => $project_id,
                'project' => $project,
                'categories' => $categories,
                'session_bloggers'   => $session_bloggers,
                'company'   =>  $company,
                'details' => $details,
                'langs'     =>  $langs,
                'currency' => $cur,
                'demography' => $demography,
                'subscribers' => $subscribers,
                'views' => $views,
                'theme' =>  $theme,
                'medias' => $medias
            ));

	}


	/**
	 * Store a newly created resource in storage.
	 *
     * @param int $project_id
	 * @return Response
	 */
	public function store($project_id)
	{
        $project = Project::find($project_id);

        $cur = ($project->cur_id)?$project->cur_id:1;

        if($project->company_id==Auth::user()->company_id)
        {
            $project = Project::find($project_id);

            $company = DB::table('companies')
                ->where('id', '=', Auth::user()->company_id)
                ->first();

            $mdata = array();
            $mdata['project_id'] = $project->id;
            $mdata['project_title'] = $project->title;
            $mdata['category_id'] = $project->category_id;
            $mdata['company_name'] = $company->company_name;

            if(Input::get('public'))
            {
                $type = 2;
            }
            else
            {
                $type = 1;
            }

            $project->type = $type;

            $project->save();

            $data = Input::get('bloggers');

            if($data)
            {
                foreach($data as $v)
                {
                    $blog = Blog::find($v);
                    if($blog)
                    {
                        if(Input::get('yt_payment_'.$v) || Input::get('ig_payment_'.$v))
                        {
                            $now = DB::raw('now()');

                            DB::table('project_blogger_conditions')
                                ->insert(array(
                                    'user_id' => $blog->user_id,
                                    'blog_id'   => $blog->id,
                                    'project_id' => $project_id,
                                    'media_id' => $blog->media_id,
                                    'created_at' => $now,
                                    'updated_at' => $now,
                                ));

                            Eventlog::create(array(
                                'user_id' => Auth::user()->id,
                                'company_id'    => Auth::user()->company_id,/* company_id */
                                'event_id' => 21,
                                'project_id' => $project_id,
                                'sub_user_id' => $blog->user_id,
                                'post_id' => null,
                                'blogger_check' => 0,
                                'company_check' => 1
                            ));

                            $pbc = DB::table('project_blogger_conditions')
                                ->where('blog_id', '=', $blog->id)
                                ->where('project_id', '=', $project_id)
                                ->first();

                            $youtube_payment = Input::get('yt_payment_'.$v);
                            $instagram_payment = Input::get('ig_payment_'.$v);

                            DB::table('budget')
                                ->insert(array(
                                    'project_id'    =>  $project_id,
                                    'user_id'       =>  $blog->user_id,
                                    'pbc_id'        =>  $pbc->id,
                                    'youtube_payment'   =>  $youtube_payment,
                                    'payment_agree_company' =>  1,
                                    'payment_agree_blogger' =>  0,
                                    'instagram_payment'    =>  $instagram_payment,
                                    'cur_id'        =>  $cur,
                                    'created_at'    =>  $now,
                                    'updated_at'    =>  $now,
                                ));

                            $budget = DB::table('budget')
                                ->where('project_id', '=', $project_id)
                                ->where('user_id', '=', $blog->user_id)
                                ->first();

                            DB::table('project_blogger_conditions')
                                ->where('blog_id', '=', $blog->id)
                                ->where('project_id', '=', $project_id)
                                ->update(array('budget_id' => $budget->id));

                            if(!NoticeController::notice_blogger($blog->user_id, 1, $mdata)){};

                        }

                    } else {
                        return Redirect::to('projects/'.$project_id.'/bloggers/create')
                            ->with('global_error', trans('error.error'));
                    }

                }

            }
            if($type==2)
            {

                $details = DB::table('project_details')
                    ->where('project_id', '=', $project_id)
                    ->first();

                $haggle_option = (Input::get('haggle_option'))?Input::get('haggle_option'):0;

                $now = DB::raw('now()');

                if($details)
                {
                    /* update */
                    $save_details = DB::table('project_details')
                        ->where('project_id', '=', $project_id)
                        ->update(array(
                            'theme'         =>  Input::get('theme'),
                            'demography'    =>  Input::get('demography'),
                            'subscribers'   =>  Input::get('subscribers'),
                            'views'         =>  Input::get('views'),
                            'youtube_payment'       =>  Input::get('youtube_payment'),
                            'instagram_payment'    =>  Input::get('instagram_payment'),
                            'cur_id'        =>  $cur,
                            'haggle_option' =>  $haggle_option,
                            'timestamp'     =>  $now,
                        ));
                }
                else
                {
                    /* insert */
                    $save_details = DB::table('project_details')
                        ->insert(array(
                            'project_id'    =>  $project_id,
                            'theme'         =>  Input::get('theme'),
                            'demography'    =>  Input::get('demography'),
                            'subscribers'   =>  Input::get('subscribers'),
                            'views'         =>  Input::get('views'),
                            'youtube_payment'       =>  Input::get('youtube_payment'),
                            'instagram_payment'    =>  Input::get('instagram_payment'),
                            'cur_id'        =>  $cur,
                            'haggle_option' =>  $haggle_option,
                            'timestamp'     =>  $now,
                        ));
                }

                if(!$save_details)
                {
                    /* return back, save failed */
                    return Redirect::to('projects/'.$project_id.'/bloggers/create')
                        ->with('global_error', trans('error.pbc005'))
                        ->withInput();
                }
                else
                {
                    /* return to post, saved */
                    return Redirect::to('projects/'.$project_id.'/posts/create')
                        ->with('global', trans('voc.data_saved'));
                }
            }
            elseif(!$type)
            {
                return Redirect::to('projects/'.$project_id.'/bloggers/create')
                    ->with('global_error', trans('error.pbc006'));
            }/* wrong type */

            return Redirect::to('projects/'.$project_id.'/posts/create')
                ->with('global', trans('voc.changes_saved'));

        }/* if has rights to edit */
        else
        {
            App::missing(function($exception)
            {
                return Response::view('errors.missing', array('title'=>'Project error', 'error' => trans('error.no_rights')), 404);
            });
            return App::abort(404);
        }
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
	 * Remove the blogger from project.
	 *
	 * @param  int  $project_id
	 * @param  int  $blogger_id
	 * @return Response
	 */
	public function destroy($project_id, $blogger_id)
	{
		if(!empty($blogger_id))
        {
            $project = Project::find($project_id);

            if(!empty($project))
            {
                $blogger = DB::table('project_blogger_conditions')
                    ->where('user_id', '=', $blogger_id)
                    ->where('project_id', '=', $project_id)
                    ->first();

                if(!empty($blogger))
                {
                    if($project->company_id == Auth::user()->company_id || $blogger_id == Auth::user()->id )
                    {
                        if( $blogger->status < 3 )
                        {
                            DB::table('project_blogger_conditions')
                                ->where('user_id', '=', $blogger_id)
                                ->where('project_id', '=', $project_id)
                                ->delete();

                            DB::table('budget')
                                ->where('user_id', '=', $blogger_id)
                                ->where('project_id', '=', $project_id)
                                ->delete();

                            Eventlog::create(array(
                                'user_id' => Auth::user()->id,/* Company */
                                'event_id' => 26,
                                'project_id' => $project_id,
                                'sub_user_id' => $blogger_id,/* blogger_id */
                                'post_id' => null,
                                'blogger_check' => 0,
                                'company_check' => 1
                            ));

                            return Redirect::back()
                                ->with('global', 'Blogger removed from project');
                        }
                        else
                        {
                            return Redirect::back()
                                ->with('global_error', trans('error.pbc001'));
                        }
                    }
                    else
                    {
                        return Redirect::back()
                            ->with('global_error', trans('error.pbc002'));
                    }
                }
                else
                {
                    return Redirect::back()
                        ->with('global_error', trans('error.pbc003'));
                }
            }
            else
            {
                return Redirect::back()
                    ->with('global_error', trans('error.pbc004'));
            }
        }
        else
        {
            return Redirect::back(trans('error.error'));
        }
	}


}

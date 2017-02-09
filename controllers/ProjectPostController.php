<?php

class ProjectPostController extends \BaseController {

	/**
	 * Display a listing of the resource.
	 *
     * @param  int  $project_id
	 * @return Response
	 */
	public function index($project_id)
	{
		$pp = DB::table('project_blogger_conditions')
            ->where('project_id', '=', $project_id)
            ->first();

        return View::make('projects.posts.index', array('title' => trans('voc.project_posts'), 'posts' => $pp, 'pid' => $project_id ));
	}


	/**
	 * Show the form for creating a new resource. (For company)
	 *
     * @param  int  $project_id
	 * @return Response
	 */
	public function create($project_id)
	{
        $project = Project::find($project_id);

        if($project->company_id == Auth::user()->company_id)
        {
            $conditions = DB::table('project_medias')
                ->where('project_id', '=', $project_id)
                ->get();

            $promo_formats = array('1' => trans('category.promo1'),
                                   '2' => trans('category.promo2'),
                                   '3' => trans('category.promo3'),
                                   '4' => trans('category.promo4'),
                                   '5' => trans('category.promo5'),
                                   '6' => trans('category.promo6'),
                                   '7' => trans('category.promo7'),
                                   '8' => trans('category.promo8'), );

            return View::make('projects.posts.create', array('title' => trans('voc.post'), 'pid' => $project_id, 'project' => $project,
                                                             'promo_formats' => $promo_formats, 'conditions' => $conditions));
        }
        else
        {
            return Redirect::back();
        }

	}


	/**
	 * Store a newly created resource in storage.
	 *
     * @param  int  $project_id
	 * @return Response
	 */
	public function store($project_id)
	{
        $p = Project::find($project_id);

        if($p->company_id==Auth::user()->company_id)
        {
            $type = (Input::get('form_type'))?Input::get('form_type'):1;

            if($type==1)/* Simple form */
            {
                $p->sf_post_conditions = Input::get('post_conditions');
                $p->sf_post_due_date = Input::get('post_due_date');
                $p->form_type = $type;
                $p->save();

                DB::table('project_medias')
                    ->where('project_id', '=', $p->id)
                    ->update(array(
                        'release_confirmation' => '000001',
                    ));

                return Redirect::route('projects.index')->with('global', trans('voc.changes_saved'));
            }
            elseif($type==2)/* Complete form */
            {
                for($a=1;$a<=2;$a++)
                {
                    if(Input::get('media'.$a))
                    {
                        $title_c    = Input::get('title_confirmation'.$a)?Input::get('title_confirmation'.$a):'0';
                        $script_c   = Input::get('script_confirmation'.$a)?Input::get('script_confirmation'.$a):'0';
                        $ppt_c      = Input::get('ppt_confirmation'.$a)?Input::get('ppt_confirmation'.$a):'0';
                        $products_c = Input::get('products_confirmation'.$a)?Input::get('products_confirmation'.$a):'0';
                        $date_c     = Input::get('release_date_confirmation'.$a)?Input::get('release_date_confirmation'.$a):'0';
                        $video_c    = Input::get('video_release_confirmation'.$a)?Input::get('video_release_confirmation'.$a):'0';

                        $release_confirmation = $title_c.$script_c.$ppt_c.$products_c.$date_c.$video_c;

                        $promo_format   = Input::get('promo_format'.$a);
                        $post_rules     = Input::get('post_rules'.$a);
                        $post_rules2    = Input::get('post_rules2'.$a);
                        $post_rules3    = Input::get('post_rules3'.$a);
                        $post_time_from = Input::get('post_time_from'.$a);
                        $post_time_to   = Input::get('post_time_to'.$a);
                        $posts_amount   = (Input::get('posts_amount'.$a))?Input::get('posts_amount'.$a):1;

                        DB::table('project_medias')
                            ->where('project_id', '=', $project_id)
                            ->where('media_id', '=', $a)
                            ->update(array(
                                'promo_format' => $promo_format,
                                'rules' => $post_rules,
                                'rules2' => $post_rules2,
                                'rules3' => $post_rules3,
                                'start_date' => $post_time_from,
                                'end_date' => $post_time_to,
                                'posts_amount' => $posts_amount,
                                'release_confirmation' => $release_confirmation,
                            ));
                    }
                }
                $p->form_type = $type;
                $p->save();
                return Redirect::route('projects.index')->with('global', trans('voc.changes_saved'));
            }
            else
            {
                return Redirect::back()->with('global_error', trans('error.error'));
            }

        }
        else
        {
            return Redirect::back()->with('global_error', trans('error.no_rights'));
        }

	}


	/**
	 * Display the specified resource.
	 *
	 * @param  int  $project_id
	 * @param  int  $post_id
	 * @return Response
	 */
	public function show($project_id, $post_id)
	{
		$post = DB::table('project_posts AS pp')
            ->where('pp.project_id', '=', $project_id)
            ->where('pp.post_id', '=', $post_id)
            ->get();

        return View::make('projects.posts.show', array('title' => 'Show post', 'post' => $post));
	}


	/**
	 * Show the form for editing the specified resource.(Available to post creator[blogger])
	 *
     * @param  int  $project_id
     * @param  int  $post_id
	 * @return Response
	 */
	public function edit($project_id, $post_id)
	{
        $post = DB::table('project_posts AS pp')
            ->where('pp.project_id', '=', $project_id)
            ->where('pp.post_id', '=', $post_id)
            ->get();

        return View::make('projects.posts.edit', array('title' => 'Edit post', 'post' => $post));
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


}

<?php

class AjaxController extends \BaseController {

	/**
	 * Save data to db.
	 *
	 * @return void
	 */
    /*
	public function save()
	{
        $post = DB::table('project_posts')
            ->where('project_id', '=', Input::get('project'))
            ->where('user_id', '=', Auth::user()->id)
            ->first();
        try
        {
            if(!$post)
            {
                $now = DB::raw('now()');

                $video = Post::create(array(
                    'user_id'       =>  Auth::user()->id,
                    'project_id'    =>  Input::get('project'),
                ));

                $post = Projectpost::create(array(
                        'user_id' => Auth::user()->id,
                        'post_id'   => $video->id,
                        'project_id' => Input::get('project'),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ));
            }
            $p = Projectpost::find($post->id);

            if(Input::get('name') == 'video_title')
            {
                $p->title = Input::get('value');
                DB::table('project_posts')
                    ->where('id', '=', $post->id)
                    ->update(array(
                        'title_confirmation' => 0
                    ));
            }
            elseif(Input::get('name') == 'video_script')
            {
                $p->script = Input::get('value');
                DB::table('project_posts')
                    ->where('id', '=', $post->id)
                    ->update(array(
                        'script_confirmation' => 0
                    ));
            }
            elseif(Input::get('name') == 'product_text')
            {
                $p->promoted_product_text = Input::get('value');
                DB::table('project_posts')
                    ->where('id', '=', $post->id)
                    ->update(array(
                        'ppt_confirmation' => 0
                    ));
            }
            elseif(Input::get('name') == 'other_products')
            {
                $p->products = Input::get('value');
                DB::table('project_posts')
                    ->where('id', '=', $post->id)
                    ->update(array(
                        'products_confirmation' => 0
                    ));
            }
            elseif(Input::get('name') == 'release_date')
            {
                $p->release_date = Input::get('value');
                DB::table('project_posts')
                    ->where('id', '=', $post->id)
                    ->update(array(
                        'release_date_confirmation' => 0
                    ));
            }
            elseif(Input::get('name') == 'video_link')
            {
                $video = Post::find($p->post_id);

                if(strpos(Input::get('value'), '?v=') || strpos(Input::get('value'), 'youtu.be'))
                {

                    $video->blog_link = Input::get('value');

                    DB::table('project_posts')
                        ->where('id', '=', $post->id)
                        ->update(array(
                            'video_release_confirmation' => 0
                        ));


                    if($video->save())
                    {
                        return 'saved';
                    }
                    else
                    {
                        return 'save failed';
                    }

                }
                else
                {

                    return 'save failed';
                }
            }

            if($p->save())
            {
                return 'saved';
            }
            else
            {
                return 'save failed';
            }

        }
        catch(Exception $e){return $e;}
	}
*/
    public function add_session_blogger()
    {
        $bloggers = array();

        if(Session::get('bloggers'))
            $bloggers = Session::get('bloggers');
        else
            Session::put('bloggers', $bloggers);

        $bloggers[] = Input::get('blogger');

        Session::put('bloggers', $bloggers);

        return count(Session::get('bloggers'));
    }

    public function remove_session_blogger()
    {
        $bloggers = Session::get('bloggers');

        if(($key = array_search(Input::get('blogger'), $bloggers)) !== false) {
            unset($bloggers[$key]);
        }

        Session::put('bloggers', $bloggers);

        return count(Session::get('bloggers'));
    }

    public function session_bloggers_list()
    {
        $bloggers = Session::get('bloggers');
        if(!empty($bloggers))
        {
            $html = '';

            $b = DB::table('blogs')
                ->whereIn('user_id', $bloggers)
                ->whereNotNull('title')
                ->get();

            foreach($b as $blog)
            {
                $src = (!empty($blog->thumb))?URL::to($blog->thumb):'images/nopicture.png';

                $html .= '<li><a href="'.URL::to('blogger/'.$blog->user_id).'"><img src="'.$src.'" class="circle pull-left" height="30" /><span class="mleft10 pull-left" style="width:180px;">'.$blog->title.'</span><div class="clear"></div></a></li>';
            }
            $html .= '<div class="clear"></div>';

            return $html;
        }
    }

    public function form_settings()
    {
        Session::put('form_settings', Input::get('form_settings'));
    }

}

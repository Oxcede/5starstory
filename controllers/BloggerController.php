<?php

class BloggerController extends \BaseController {

    public static function getBloggerInfo($username, $channelId)
    {
        $info = array();

        if(Auth::user()->role==4)
        {
            if(!empty($username))
            {
                $test = DB::table('blogs')
                    ->where('title', '=', $username)
                    ->first();
                if(!empty($test))
                {
                    $info['dupe'] = 'dupe';
                }
            }
            if(!empty($channelId))
            {
                $test = DB::table('blogs')
                    ->where('channel', '=', $channelId)
                    ->first();
                if(!empty($test))
                {
                    $info['dupe'] = 'dupe';
                }
            }
            if(!empty($info))
            {
                return $info;
            }
        }

        $info['title'] = '';
        $info['channel'] = '';
        $info['subscriptions'] = '';
        $info['views'] = '';
        $info['thumb'] = '';
        $info['location'] = '';
        $info['check'] = $channelId.':'.$username;

        $DEVELOPER_KEY = $_ENV['GOOGLE_DEV_KEY'];

        if(!empty($channelId))
        {

            $client = new Google_Client();
            $client->setDeveloperKey($DEVELOPER_KEY);

            // Define an object that will be used to make all API requests.
            $youtube = new Google_Service_YouTube($client);


            $channelsResponse = $youtube->channels->listChannels('snippet, statistics', array(
                'id' => $channelId
            ));

            foreach ($channelsResponse['items'] as $channel)
            {

                $info['channel'] = $channel['id'];

                $info['title'] = $channel['snippet']['title'];

                $info['views'] = $channel['statistics']['viewCount'];

                $info['subscriptions'] = $channel['statistics']['subscriberCount'];

                $info['thumb'] = $channel['snippet']['thumbnails']['default']['url'];

                $info['url'] = 'http://www.youtube.com/channel/'.$channel['id'];

            }

        }
        elseif(!empty($username))
        {
            $client = new Google_Client();
            $client->setDeveloperKey($DEVELOPER_KEY);

            // Define an object that will be used to make all API requests.
            $youtube = new Google_Service_YouTube($client);


            $channelsResponse = $youtube->channels->listChannels('snippet, statistics', array(
                'forUsername' => $username
            ));

            foreach ($channelsResponse['items'] as $channel)
            {

                $info['channel'] = $channel['id'];

                $info['title'] = $channel['snippet']['title'];

                $info['views'] = $channel['statistics']['viewCount'];

                $info['subscriptions'] = $channel['statistics']['subscriberCount'];

                $info['thumb'] = $channel['snippet']['thumbnails']['default']['url'];

                $info['url'] = 'http://www.youtube.com/channel/'.$channel['id'];

            }
        }

        return $info;

    }

    public function index()
    {
        $c = DB::table('blog_categories')->where('id', '!=', DB::raw('99'))->get();

        $categories = array('0' => trans('voc.all'));

        foreach($c as $v)
        {
            $categories[$v->id] = trans('category.'.$v->name);
        }

        $langs[0] = trans('voc.all');

        foreach(DB::table('langs')->get() as $v)
        {
            $langs[$v->id] = $v->name;
        }

        return View::make('bloggers.index', array('title' => 'Bloggers', 'categories' => $categories, 'langs' => $langs));
    }

    public function postFilter()
    {
        try{

            $project_id = (Input::get('pid'))?Input::get('pid'):0;

            $query = DB::table('users AS u')
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
                    thumb AS yt_thumb
                FROM blogs WHERE media_id="1" AND deleted_at IS NULL) b1'), function($join){
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
                FROM blogs WHERE media_id="2" AND deleted_at IS NULL) b2'), function($join){
                    $join->on('b2.user_id2','=', 'u.id');
                })
                ->whereRaw('
                u.role="2"
                AND u.id NOT IN (SELECT user_id FROM project_blogger_conditions WHERE project_id="'.$project_id.'")
                AND (b1.yt_channel IS NOT NULL OR b2.ig_username IS NOT NULL)
                ');
            /* FILTERS */
            $query->where(function($q)
            {

                if(Input::get('blogger_search'))
                {
                    if(strlen(Input::get('blogger_search'))>0)
                    $q->where('yt_title', 'LIKE', '%'.Input::get('blogger_search').'%')
                    ->orWhere('ig_username', 'LIKE', '%'.Input::get('blogger_search').'%');
                }

                if(Input::get('media')==1)
                {
                    if ($media = Input::get('media')) {
                        $q->where('b1.yt_media', '=', $media);
                    }
                    if ($lang = Input::get('language')) {
                        $q->where('b1.lang_id', '=', $lang);
                    }
                    if ($subs = Input::get('subscriptions')) {
                        $q->where('yt_subscriptions', '>=', $subs);
                    }
                    if ($cat = Input::get('category')) {
                        $q->where('b1.category_id', '=', $cat);
                    }
                }
                elseif(Input::get('media')==2)
                {
                    if ($media = Input::get('media')) {
                        $q->where('b2.ig_media', '=', $media);
                    }
                    if ($lang = Input::get('language')) {
                        $q->where('b2.lang_id2', '=', $lang);
                    }
                    if ($subs = Input::get('subscriptions')) {
                        $q->where('b2.ig_follows', '>=', $subs);
                    }
                    if ($cat = Input::get('category')) {
                        $q->where('b2.ig_category_id', '=', $cat);
                    }
                }
                else
                {
                    if ($cat = Input::get('category')) {
                        $q->whereRaw('(b1.yt_category_id = '.$cat.' OR b2.ig_category_id = '.$cat.')');
                    }
                    if ($subs = Input::get('subscriptions')) {
                        $q->where('yt_subscriptions', '>=', $subs);
                    }
                    if ($lang = Input::get('language')) {
                        $q->where('b1.lang_id1', '=', $lang);
                    }
                }

            });
            if(Input::get('order')){
                if(Input::get('order')=='subs'){
                    $query->orderBy('b1.yt_subscriptions', 'desc');
                }
                elseif(Input::get('order')=='avg')
                {
                    $query->orderBy('b1.yt_avg_views', 'desc');
                }
            }
            else
            {
                $query->orderBy('b1.yt_subscriptions', 'desc');
            }

            $company = DB::table('companies')
                ->where('id', '=', Auth::user()->company_id)
                ->first();

            if(!empty($project_id))
            {
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

                $b = $query->paginate(50);
                return View::make('projects.bloggers.create_ajax', array('bloggers' => $b, 'company' => $company,
                'medias' => $medias));
            }
            else
            {
                $b = $query->paginate(50);
                return View::make('bloggers.create_ajax', array('bloggers' => $b));
            }

        }
        catch(Exception $e)
        {
            return 'error';
        }

    }

    public static function fetchData($url){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    public static function removeEmoji($text) {

        $clean_text = "";

        $regexEmoticons = '/[\x{1F600}-\x{1F64F}]/u';
        $clean_text = preg_replace($regexEmoticons, '', $text);

        $regexSymbols = '/[\x{1F300}-\x{1F5FF}]/u';
        $clean_text = preg_replace($regexSymbols, '', $clean_text);

        $regexTransport = '/[\x{1F680}-\x{1F6FF}]/u';
        $clean_text = preg_replace($regexTransport, '', $clean_text);

        $regexMisc = '/[\x{2600}-\x{26FF}]/u';
        $clean_text = preg_replace($regexMisc, '', $clean_text);

        $regexDingbats = '/[\x{2700}-\x{27BF}]/u';
        $clean_text = preg_replace($regexDingbats, '', $clean_text);

        return $clean_text;
    }

    public function getInstagramInfo()
    {

        $accessToken = $_ENV['INSTAGRAM_TOKEN'];

        $result = $this->fetchData('https://api.instagram.com/v1/users/search?q='.Input::get('q').'&access_token='.$accessToken);
        $result = json_decode($result);

        if($result->meta->code==200 && !empty($result->data))
        {
            foreach($result->data as $user)
            {
                if($user->username==Input::get('q'))
                {
                    $result = $this->fetchData('https://api.instagram.com/v1/users/'.$user->id.'/?access_token='.$accessToken);
                    $result = json_decode($result);

                    if($result)
                    {
                        if($result->meta->code==200 && !empty($result->data))
                        {
                            return View::make('bloggers.get_instagram_ajax', array('result' => $result));
                        }
                    }
                    else
                    {
                        echo 'No user info'; exit;
                    }
                }
            }
            echo 'No matches';
        }
        else
        {
            echo 'No search results';
        }
    }

    public static function getInstagram($userid)
    {

        $accessToken = $_ENV['INSTAGRAM_TOKEN'];

        $return = array();

        $result = BloggerController::fetchData('https://api.instagram.com/v1/users/'.$userid.'/?access_token='.$accessToken);
        $result = json_decode($result);

        if($result)
        {
            if($result->meta->code==200 && !empty($result->data))
            {
                $return['meta'] = 200;
                $return['data'] = $result->data;
                return $return;
            }
        }
        else
        {
            $return['meta'] = 404;
        }
    }

    public static function update_yt_avg_views($channel)
    {
        $client = new Google_Client();
        $DEVELOPER_KEY = $_ENV['GOOGLE_DEV_KEY'];
        $client->setDeveloperKey($DEVELOPER_KEY);

        $videos = '';
        // Define an object that will be used to make all API requests.
        $youtube = new Google_Service_YouTube($client);

        $channelsResponse = $youtube->channels->listChannels('snippet, statistics', array(
            'id' => $channel
        ));

        $subscriptions = 0;
        foreach ($channelsResponse['items'] as $ch)
        {

            $subscriptions = $ch['statistics']['subscriberCount'];

        }

        $channelsResponse = $youtube->search->listSearch('snippet', array(
            'channelId'     =>  $channel,
            'maxResults'    =>  12,
            'order'         =>  'date',
            'type'          =>  'video'
        ));

        foreach($channelsResponse['items'] as $video)
        {
            $videos .= $video['id']['videoId'].', ';
        }

        $video_stats = $youtube->videos->listVideos('statistics, snippet', array(
            'id'    =>  $videos,
        ));

        /* extra channel info block */
        $vsum = 0;
        $step = 0;
        foreach($video_stats['items'] as $val)
        {
            if($step!=0 && $step<=10)
            {
                $vsum += $val['statistics']['viewCount'];
            }
            $step++;
        }
        /* average views for last 10 videos */
        $avg = $vsum/10;

        $yt_avg_views = 0;
        if($avg){
            $yt_avg_views = floor($avg);
        }

        DB::table('blogs')
            ->where('channel', '=', $channel)
            ->update(array(
                'yt_subscriptions'  =>  $subscriptions,
                'yt_avg_views'      =>  $yt_avg_views
            ));

        return true;
    }

    public static function get_video_info($id)
    {
        $client = new Google_Client();
        $DEVELOPER_KEY = $_ENV['GOOGLE_DEV_KEY'];
        $client->setDeveloperKey($DEVELOPER_KEY);
        $youtube = new Google_Service_YouTube($client);
        $return = array();
        $video_stats = $youtube->videos->listVideos('statistics, snippet', array(
            'id'    =>  $id,
        ));
        foreach($video_stats['items'] as $val)
        {
            $return['viewCount']        = $val['statistics']['viewCount'];
            $return['likeCount']        = $val['statistics']['likeCount'];
            $return['dislikeCount']     = $val['statistics']['dislikeCount'];
            $return['favoriteCount']    = $val['statistics']['favoriteCount'];
            $return['commentCount']     = $val['statistics']['commentCount'];
            $return['publishedAt']      = $val['snippet']['publishedAt'];
            $return['channelId']        = $val['snippet']['channelId'];
            $return['title']            = $val['snippet']['title'];
        }

        return $return;
    }


    /**
     *  Get blogger's project % completion
     *  returns array('plus', 'minus')
     */
    public static function get_graph($user_id, $project_id)
    {
        try
        {
            $g = array('plus'=>0, 'minus'=>100);

            /* GET PBC STATUS */
            $status = DB::table('project_blogger_conditions')
                ->where('user_id', '=', $user_id)
                ->where('project_id', '=', $project_id)
                ->select('status')
                ->first();

            /* GET BUDGET STATUS */
            $budget = DB::table('budget')
                ->where('user_id', '=', $user_id)
                ->where('project_id', '=', $project_id)
                ->first();

            /* GET PROJECT MEDIAS */
            $confirmations = DB::table('project_medias')
                ->where('project_id', '=', $project_id)
                ->where('media_id', '=', DB::raw('1'))
                ->first();

            /* PROJECT POSTS */
            $post = DB::table('project_posts')
                ->where('user_id', '=', $user_id)
                ->where('project_id', '=', $project_id)
                ->first();

            if($budget->status=='p' && $status->status==5)
            {
                $g['plus'] = 100;
                $g['minus'] = 0;
                return $g;
            }
            elseif($status->status==1)
            {
                /*
                    $budget->payment_agree_company
                    $budget->payment_agree_blogger
                */
                $g['plus'] = 0;
                $g['minus'] = 100;
                return $g;
            }
            elseif($status->status==2)
            {
                $g['plus'] = 10;
                $g['minus'] = 90;
                if(!empty($confirmations) && !empty($confirmations->release_confirmation) && !empty($post))
                {
                    $c = $confirmations->release_confirmation;
                    if($c[0]==1 && $post->title_confirmation==1){ $g['plus'] += 10; $g['minus'] -= 10; }
                    if($c[1]==1 && $post->script_confirmation==1){ $g['plus'] += 10; $g['minus'] -= 10; }
                    if($c[2]==1 && $post->ppt_confirmation==1){ $g['plus'] += 10; $g['minus'] -= 10; }
                    if($c[3]==1 && $post->products_confirmation==1){ $g['plus'] += 10; $g['minus'] -= 10; }
                    if($c[4]==1 && $post->release_date_confirmation){ $g['plus'] += 10; $g['minus'] -= 10; }
                }
                if($budget->status=='p'){ $g['plus'] += 10; $g['minus'] -= 10; }
                return $g;
            }
            elseif($status->status==3)
            {
                if($budget->status=='p'){ $g['plus'] = 80; $g['minus'] = 20; }
                else
                {
                    $g['plus'] = 70; $g['minus'] = 10;
                }
                return $g;
            }
            elseif($status->status==4)
            {
                if($budget->status=='p'){ $g['plus'] = 90; $g['minus'] = 10; }
                else
                {
                    $g['plus'] = 80; $g['minus'] = 20;
                }
                return $g;
            }
            elseif($status->status==5 && $budget->status!='p')
            {
                $g['plus'] = 90; $g['minus'] = 10;
                return $g;
            }
            else
            {
                $g['plus'] = 0;
                $g['minus'] = 100;
                return $g;
            }
        }
        catch(Exception $e)
        {
            //dd($e);
            $g['plus'] = 0;
            $g['minus'] = 0;
            return $g;
        }
    }

    public function delete($id)
    {
        if($id)
        {
            Blog::where('id', '=', $id)->delete();
            return Redirect::back()->with('global', 'Deleted');
        }
    }

    public function instagram()
    {

        $instagram = new Instagram(array(
            'apiKey'      => $_ENV['INSTAGRAM_CLIENT_ID'],
            'apiSecret'   => $_ENV['INSTAGRAM_CLIENT_SECRET'],
            'apiCallback' => $_ENV['INSTAGRAM_REDIRECT_URI']
        ));

        // receive OAuth code parameter
        $code = Input::get('code');

        // check whether the user has granted access
        if (isset($code))
        {
            // receive OAuth token object
            $data = $instagram->getOAuthToken($code);
            $instagram->setAccessToken($data);
            // now you have access to all authenticated user methods
            $user = $instagram->getUser();

            $test = DB::table('blogs')
                ->where('ig_user_id', '=', $user->data->id)
                ->first();
            if(empty($test))
            {
                DB::table('blogs')
                    ->insert(array(
                        'user_id' => Auth::user()->id,
                        'category_id' => '99',
                        'media_id' => '2',
                        'lang_id' => '1',
                        'title' => $user->data->username,
                        'url' => 'http://www.instagram.com/'.$user->data->username,
                        'thumb' => $user->data->profile_picture,
                        'ig_user_id' => $user->data->id,
                        'ig_username' => $user->data->username,
                        'ig_media' => $user->data->counts->media,
                        'ig_follows' => $user->data->counts->followed_by
                    ));

                return Redirect::route('account-edit-blogs')->with('global', 'Blog added');
            }
            else
            {
                return Redirect::back()->with('global_error', 'Blog already exists');
            }
        }
        else
        {
            // check whether an error occurred
            if (Input::get('error')) {
                echo 'An error occurred: ' . Input::get('error_description');
            }
        }


    }

    public static function get_blogger_thumb($user_id)
    {
        $blog = DB::table('blogs')
            ->where('user_id', '=', $user_id)
            ->whereNotNull('thumb')
            ->orderBy('media_id')
            ->first();

        if(!empty($blog))
        {
            if(!empty($blog->thumb))
            {
                return $blog->thumb;
            }
            else
            {
                return URL::to('images/nopicture.png');
            }
        }
        else
        {
            return URL::to('images/nopicture.png');
        }

    }

}
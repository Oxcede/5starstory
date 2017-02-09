<?php

class OpenBloggerController extends BaseController {

    /**
     * Show blogger
     *
     * @param int $user
     * @return Response
     */
    public function show($user){

        if( Auth::user()->role < 3 && Auth::user()->id != $user )
        {
            App::missing(function($exception)
            {
                return Response::view('errors.missing', array('title'=>'No rights to view this page', 'error' => trans('voc.ermsg_no_rights')), 404);
            });
            return App::abort(404);
        }

        $media = (Input::get('media'))?Input::get('media'):null;

        $channels = DB::table('blogs')
            ->where('user_id', '=', $user)
            ->orderBy('media_id')
            //->where('media_id', '=', $media)
            ->get();

        if($media==null){
            foreach($channels as $ch)
            {
                return Redirect::to('blogger/'.$user.'?media='.$ch->media_id);
            }
        }


        $videos = '';
        $channel_stats = array();
        $video_stats = array();
        $instagram = array();
        $instagram_stats = array();
        $graph_views_labels = array();
        $graph_views_data = array();
        $graph_subs_labels = array();
        $graph_subs_data = array();

        foreach($channels as $k => $channel)
        {
            if($channel->media_id==1)
            {

                $yt_channel = $channel->channel;

                $client = new Google_Client();
                $DEVELOPER_KEY = $_ENV['GOOGLE_DEV_KEY'];
                $client->setDeveloperKey($DEVELOPER_KEY);

                // Define an object that will be used to make all API requests.
                $youtube = new Google_Service_YouTube($client);

                $channel_stats[$k] = Cache::remember('yt_'.$yt_channel, 60, function() use ($youtube, $yt_channel){
                    return $youtube->channels->listChannels('statistics, snippet', array(
                        'id' => $yt_channel
                    ));
                });

                $channelsResponse = $youtube->search->listSearch('snippet', array(
                    'channelId'     =>  $yt_channel,
                    'maxResults'    =>  20,
                    'order'         =>  'date',
                    'type'          =>  'video'
                ));

                foreach($channelsResponse['items'] as $video)
                {
                    $videos .= $video['id']['videoId'].', ';
                }

                $video_stats[$k] = $youtube->videos->listVideos('statistics, snippet', array(
                    'id'    =>  $videos,
                ));

                /* extra channel info block */
                $vsum = 0;
                $categories = array();
                $step = 0;
                foreach($video_stats[$k]['items'] as $val)
                {
                    if($step!=0 && $step<=10)
                    {
                        $vsum += $val['statistics']['viewCount'];
                        $categories[] = $val['snippet']['categoryId'];
                    }
                    $step++;
                }
                /* average views for last 10 videos */
                $avg = $vsum/10;
                $channel_stats[$k]['avgviews'] = floor($avg);

                /* common category */
                $c = array_count_values($categories);
                if(!empty($c)){
                    $channel_stats[$k]['category'] = array_search(max($c), $c);
                }
                else
                {
                    $channel_stats[$k]['category'] = null;
                }

                $videos = '';

                /* blogger stats */

                $stat_data = DB::table('blogger_stats')
                    ->where('channel', '=', $yt_channel)
                    ->orderBy('date', 'desc')
                    ->limit(30)
                    ->get();

                $c = count($stat_data);

                if($c>1)
                {
                    $channel_stats[$k]['yesterday_views'] = $stat_data[0]->views-$stat_data[1]->views;
                    $channel_stats[$k]['yesterday_subs'] = $stat_data[0]->subs-$stat_data[1]->subs;
                    if($c>=30)
                    {
                        $channel_stats[$k]['avg_views'] = floor(($stat_data[0]->views - $stat_data[29]->views)/30);
                        $channel_stats[$k]['30days_views'] = $stat_data[0]->views - $stat_data[29]->views;
                        $channel_stats[$k]['avg_subs'] = floor(($stat_data[0]->subs - $stat_data[29]->subs)/30);
                        $channel_stats[$k]['30days_subs'] = $stat_data[0]->subs - $stat_data[29]->subs;
                    }
                    else
                    {
                        $channel_stats[$k]['avg_views'] = floor(($stat_data[0]->views - $stat_data[$c-1]->views)/$c);
                        $channel_stats[$k]['30days_views'] = $stat_data[0]->views - $stat_data[$c-1]->views;
                        $channel_stats[$k]['avg_subs'] = floor(($stat_data[0]->subs - $stat_data[$c-1]->subs)/$c);
                        $channel_stats[$k]['30days_subs'] = $stat_data[0]->subs - $stat_data[$c-1]->subs;
                    }
                }
                else
                {
                    $channel_stats[$k]['yesterday_views'] = 0;
                    $channel_stats[$k]['avg_views'] = 0;
                    $channel_stats[$k]['30days_views'] = 0;
                    $channel_stats[$k]['yesterday_subs'] = 0;
                    $channel_stats[$k]['avg_subs'] = 0;
                    $channel_stats[$k]['30days_subs'] = 0;
                }
                $channel_stats[$k]['days_count'] = $c;

                /* views per day graph
                $stat_data = array_reverse($stat_data);
                $graph_views_labels = '';
                $graph_views_data = '';
                $a=1;
                if($c>0)
                {
                    foreach($stat_data as $test)
                    {
                        $graph_views_labels .= '"'.substr($test->date,0,10).'"';
                        $graph_views_data .= $test->views;
                        if($a<$c)
                        {
                            $graph_views_labels .= ',';
                            $graph_views_data .= ',';
                        }
                        $a++;
                    }
                } */
                /* subs per day graph
                $graph_subs_labels = '';
                $graph_subs_data = '';
                $a=1;
                if($c>0)
                {
                    foreach($stat_data as $test)
                    {
                        $graph_subs_labels .= '"'.substr($test->date,0,10).'"';
                        $graph_subs_data .= $test->subs;
                        if($a<$c)
                        {
                            $graph_subs_labels .= ',';
                            $graph_subs_data .= ',';
                        }
                        $a++;
                    }
                } */

                $channel_stats[$k]['url'] = $channel->url;

            }
            elseif($channel->media_id==2)
            {

                $ig_userid = $channel->ig_user_id;

                $stat_data = DB::table('blogger_stats')
                    ->where('channel', '=', $ig_userid)
                    ->orderBy('date', 'desc')
                    ->limit(30)
                    ->get();
                $c = count($stat_data);


                if($c>1)
                {
                    $instagram_stats[$k]['yesterday_subs'] = $stat_data[0]->subs-$stat_data[1]->subs;
                    if($c>=30)
                    {
                        $instagram_stats[$k]['avg_subs'] = floor(($stat_data[0]->subs - $stat_data[29]->subs)/30);
                        $instagram_stats[$k]['30days_subs'] = $stat_data[0]->subs - $stat_data[29]->subs;
                    }
                    else
                    {
                        $instagram_stats[$k]['avg_subs'] = floor(($stat_data[0]->subs - $stat_data[$c-1]->subs)/$c);
                        $instagram_stats[$k]['30days_subs'] = $stat_data[0]->subs - $stat_data[$c-1]->subs;
                    }
                }
                else
                {
                    $instagram_stats[$k]['yesterday_subs'] = 0;
                    $instagram_stats[$k]['avg_subs'] = 0;
                    $instagram_stats[$k]['30days_subs'] = 0;
                }
                $instagram_stats[$k]['days_count'] = $c;

                $accessToken = $_ENV['INSTAGRAM_TOKEN'];
                $amount = 25;
                $result = Cache::remember('instagram_'.$ig_userid, 60, function() use ($ig_userid, $amount, $accessToken){
                    return BloggerController::fetchData('https://api.instagram.com/v1/users/'.$ig_userid.'/media/recent/?access_token='.$accessToken.'&count='.$amount);
                });
                $result = json_decode($result);

                if(!empty($result->data))
                {
                    foreach ($result->data as $post)
                    {
                        $likes[] = $post->likes->count;

                        $instagram[$k][] = array(
                                        "header" => $channel->ig_username,
                                        "href"=>$post->link,
                                        "title"=>(!empty($post->caption->text))?BloggerController::removeEmoji($post->caption->text):'',
                                        "src"=>$post->images->standard_resolution->url,
                                        "likes"=>$post->likes->count,
                                        "comments"=>$post->comments->count);
                    }

                    $vsum = 0;
                    $step = 0;
                    foreach($likes as $val)
                    {
                        if($step!=0 && $step<=10)
                        {
                            $vsum += $val;
                        }
                        $step++;
                    }
                    /* average views for last 10 videos */
                    $avg = $vsum/10;
                    $instagram_stats[$k]["avg_likes"] = floor($avg);

                    $result = BloggerController::fetchData('https://api.instagram.com/v1/users/'.$ig_userid.'/?access_token='.$accessToken);
                    $result = json_decode($result);

                    $instagram_stats[$k]["image"] = $result->data->profile_picture;
                    $instagram_stats[$k]["username"] = $result->data->username;
                    $instagram_stats[$k]["media_c"] = $result->data->counts->media;
                    $instagram_stats[$k]["followed"] = $result->data->counts->followed_by;
                    $instagram_stats[$k]["category"] = trans('category.bc'.$channel->category_id);
                    $instagram_stats[$k]["url"] = $channel->url;

                }

            }

        }

        return View::make('bloggers.show', array('title' => 'Blogger info',
            'user'                  =>  $user,
            'videos'                =>  $video_stats,
            'instagram'             =>  $instagram,
            'instagram_stats'       =>  $instagram_stats,
            'channels'              =>  $channel_stats,
            'graph_views_labels'    =>  $graph_views_labels,
            'graph_views_data'      =>  $graph_views_data,
            'graph_subs_labels'     =>  $graph_subs_labels,
            'graph_subs_data'       =>  $graph_subs_data,
            'media'                 =>  $media
        ));

    }

}
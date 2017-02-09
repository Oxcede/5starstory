<?php
class CronJobs extends BaseController
{
    public function cron(){

        $client = new Google_Client();
        $DEVELOPER_KEY = $_ENV['GOOGLE_DEV_KEY'];
        $client->setDeveloperKey($DEVELOPER_KEY);

        $blogs = DB::table('blogs')
            ->get();

        $a = 0;
        $y = 0;
        $i = 0;

        $last_record = DB::table('blogger_stats')
            ->orderBy('id', 'desc')
            ->limit(1)
            ->get();

        if(!empty($last_record))
        {
            if((time()-(60*60*10)) > strtotime($last_record[0]->date))
            {
                foreach($blogs as $blog)
                {
                    $a++;
                    if($blog->media_id==1)
                    {
                        $y++;

                        if($blog->channel)
                        {
                            $videos = '';
                            // Define an object that will be used to make all API requests.
                            $youtube = new Google_Service_YouTube($client);

                            $channelsResponse = $youtube->channels->listChannels('snippet, statistics', array(
                                'id' => $blog->channel
                            ));

                            foreach ($channelsResponse['items'] as $channel)
                            {

                                $subscriptions = $channel['statistics']['subscriberCount'];
                                $views = $channel['statistics']['viewCount'];
                                $total = $channel['statistics']['videoCount'];
                                $thumb = $channel['snippet']['thumbnails']['default']['url'];

                            }

                            $channelsResponse = $youtube->search->listSearch('snippet', array(
                                'channelId'     =>  $blog->channel,
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
                            $yt_avg_views = floor($avg);

                            $now = DB::raw('NOW()');
                            DB::table('blogs')
                                ->where('id', '=', $blog->id)
                                ->update(array(
                                    'yt_subscriptions'  =>  $subscriptions,
                                    'yt_avg_views'      =>  $yt_avg_views,
                                    'thumb'             =>  $thumb,
                                    'updated_at'        =>  $now
                                ));

                            DB::table('blogger_stats')
                                ->insert(array(
                                    'user_id'   =>  $blog->user_id,
                                    'channel'   =>  $blog->channel,
                                    'views'     =>  $views,
                                    'subs'      =>  $subscriptions,
                                    'avg'       =>  $yt_avg_views,
                                    'total'     =>  $total,
                                    'date'      =>  $now
                                ));
                        }
                    }
                    elseif($blog->media_id==2)
                    {
                        $i++;
                        if($blog->ig_user_id)
                        {
                            $ig_userid = $blog->ig_user_id;

                            $accessToken = $_ENV['INSTAGRAM_TOKEN'];
                            $amount = 12;
                            $result = BloggerController::fetchData('https://api.instagram.com/v1/users/'.$ig_userid.'/media/recent/?access_token='.$accessToken.'&count='.$amount);
                            $result = json_decode($result);

                            $vsum = 0;
                            $step = 0;
                            foreach ($result->data as $post)
                            {
                                if($step!=0 && $step<=10)
                                {
                                    $vsum += $post->likes->count;
                                }
                                $step++;
                            }

                            /* average views for last 10 videos */
                            $avg = $vsum/10;
                            $ig_avg_views = floor($avg);

                            $result = BloggerController::fetchData('https://api.instagram.com/v1/users/'.$ig_userid.'/?access_token='.$accessToken);
                            $result = json_decode($result);

                            DB::table('blogs')
                                ->where('id', '=', $blog->id)
                                ->update(array(
                                    'ig_media' => $result->data->counts->media,
                                    'ig_follows' => $result->data->counts->followed_by,
                                    'ig_avg_views'  => $ig_avg_views
                                ));

                            $now = DB::raw('NOW()');
                            DB::table('blogger_stats')
                                ->insert(array(
                                    'user_id'   =>  $blog->user_id,
                                    'channel'   =>  $ig_userid,
                                    'subs'      =>  $result->data->counts->followed_by,
                                    'avg'       =>  $ig_avg_views,
                                    'total'     =>  $result->data->counts->media,
                                    'date'      =>  $now
                                ));

                        }
                    }
                }

                return 'Complete. All blogs: '.$a.'. Youtube: '.$y.'. Instagram: '.$i;
            }
            else
            {
                return 'DUPE';
            }
        }

    }

    public function cron_posts()
    {

        $client = new Google_Client();
        $DEVELOPER_KEY = $_ENV['GOOGLE_DEV_KEY'];
        $client->setDeveloperKey($DEVELOPER_KEY);

        $posts = DB::table('posts')
            ->get();

        foreach($posts as $post)
        {
            if(!empty($post->blog_link) && preg_match('%^((https?://)|(www\.))([a-z0-9-].?)+(:[0-9]+)?(/.*)?$%i', $post->blog_link))
            {

                if(strpos($post->blog_link, '?v='))
                {
                    $videoId = substr(strstr($post->blog_link, '?v='),3, 11);
                }
                elseif(strpos($post->blog_link, 'youtu.be'))
                {
                    $videoId = substr(strstr($post->blog_link, 'be/'),3, 11);
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

                DB::table('posts')
                    ->where('id', '=', $post->id)
                    ->update(array('name'=>$title, 'release_date'=>$publish_date));

                DB::table('post_stats')
                    ->insert(array(
                        'post_id'   =>  $post->id,
                        'yt_id'     =>  $yt_id,
                        'views'     =>  $views,
                        'likes'     =>  $likes,
                        'dislikes'  =>  $dislikes,
                        'comments'  =>  $comments,
                        'status'    =>  $status,
                        'date'      =>  $now
                    ));

                if($status=='public')
                {
                    DB::table('project_blogger_conditions')
                        ->where('project_id', '=', $post->project_id)
                        ->where('user_id', '=', $post->user_id)
                        ->where('status', '=', DB::raw('4'))
                        ->update(array(
                            'status' =>  '5'
                        ));
                }

            }
        }

        return 'Complete';

    }

    public function cron_transactions()
    {

        $now = DB::raw('now()');
        /* GET TRANSACTIONS */
        $transactions = DB::table('transactions')
            ->where('status', '=', 'submitted_for_settlement')
            ->get();

        foreach($transactions as $ta)
        {
            $transaction = Braintree_Transaction::find($ta->bt_id);

            DB::table('transactions')
                ->where('id', '=', $ta->id)
                ->update(array(
                    'status'        =>  $transaction->status,
                    'updated_at'    =>  $now
                ));

            /* UPDATE BUDGET STATUS based on $transaction->status */


        }

        return 'Done.';


    }


}
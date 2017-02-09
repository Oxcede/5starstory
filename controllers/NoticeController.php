<?php

use Postmark\PostmarkClient;
//use Postmark\Models\PostmarkAttachment;
//use Postmark\Models\PostmarkException;

class NoticeController extends \BaseController {

    /**
     * @param $user_id
     * @param $type
     * @param $data
     * @return bool
     */
    public static function notice_blogger($user_id, $type, $data)
    {
        $settings = DB::table('mail_settings AS ms')
            ->leftJoin('users AS u', 'ms.user_id', '=', 'u.id')
            ->where('user_id', '=', $user_id)
            ->first();

        /*$attachment = PostmarkAttachment::fromFile(URL::to('images/BlaBla-logo-black-wide.png'),
            "logo.png", "image/png");*/

        /*
         * 1 - new offers
         * 2 - payment changes
         * 3 - project accepted
         * 4 - project details accepted
         *
         * 6 - shipment dispatched
         *
         * 8 - project messages
         *
         * */

        switch($type){
            case(1):/* new offer from company */
                if($settings->opt1==1)
                {
                    if($settings->lang=='en')
                    {
                        $template = 247281;
                    }
                    elseif($settings->lang=='ru')
                    {
                        $template = 247103;
                    }
                    else
                    {
                        $template = 247103;
                    }
                    try{

                        $company_name = ($data['company_name'])?$data['company_name']:'';
                        $cat = ($data['category_id'])?$data['category_id']:99;
                        $project_title = ($data['project_title'])?$data['project_title']:'';
                        $project_link = URL::to('project/'.$data['project_id']);

                        $client = new PostmarkClient($_ENV['POSTMARK']);

                        $sendResult = $client->sendEmailWithTemplate(
                            "hi@blablablogger.com",
                            $settings->email,
                            $template,
                            [
                                "name" => $settings->username,
                                "company_name" => $company_name,
                                "project_category" => trans('category.project'.$cat),
                                "project_title" => $project_title,
                                "project_link" => $project_link
                            ]/*, true, null, true, null, null, null, null,
                            [$attachment] */);

                        return true;
                    } catch (Exception $e){
                        return $e;
                    }
                }
                else{ return false; } break;
            case(2):/* company updated payment for offer */
                if($settings->opt2==1)
                {
                    if($settings->lang=='en')
                    {
                        $template = 247481;
                    }
                    elseif($settings->lang=='ru')
                    {
                        $template = 247501;
                    }
                    else
                    {
                        $template = 247501;
                    }
                    try{

                        $company_name = ($data['company_name'])?$data['company_name']:'';
                        $project_title = ($data['project_title'])?$data['project_title']:'';
                        $project_link = URL::to('project/'.$data['project_id']);

                        $client = new PostmarkClient($_ENV['POSTMARK']);

                        $sendResult = $client->sendEmailWithTemplate(
                            "hi@blablablogger.com",
                            $settings->email,
                            $template,
                            [
                                "name" => $settings->username,
                                "company_name" => $company_name,
                                "project_title" => $project_title,
                                "project_link" => $project_link
                            ]/*, true, null, true, null, null, null, null,
                            [$attachment] */);

                        return true;
                    } catch (Exception $e){
                        return $e;
                    }
                }
                else{ return false; } break;
            case(3):/* request confirmed, edited offer confirmed */
                if($settings->opt3==1)
                {
                    if($settings->lang=='en')
                    {
                        $template = 247303;
                    }
                    elseif($settings->lang=='ru')
                    {
                        $template = 247282;
                    }
                    else
                    {
                        $template = 247282;
                    }
                    try{

                        $company_name = ($data['company_name'])?$data['company_name']:'';
                        $project_title = ($data['project_title'])?$data['project_title']:'';
                        $project_link = URL::to('project/'.$data['project_id']);

                        $client = new PostmarkClient($_ENV['POSTMARK']);

                        $sendResult = $client->sendEmailWithTemplate(
                            "hi@blablablogger.com",
                            $settings->email,
                            $template,
                            [
                                "name" => $settings->username,
                                "company_name" => $company_name,
                                "project_title" => $project_title,
                                "project_link" => $project_link
                            ]/*, true, null, true, null, null, null, null,
                            [$attachment]*/ );

                        return true;
                    } catch (Exception $e){
                        return $e;
                    }
                }
                else{ return false; } break;
            case(4):/* Project's post details accepted */
                if($settings->opt4==1)
                {
                    if($settings->lang=='en')
                    {
                        $template = 247510;
                    }
                    elseif($settings->lang=='ru')
                    {
                        $template = 247511;
                    }
                    else
                    {
                        $template = 247511;
                    }
                    try{

                        $company_name = ($data['company_name'])?$data['company_name']:'';
                        $project_title = ($data['project_title'])?$data['project_title']:'';
                        $project_link = URL::to('project/'.$data['project_id']);

                        $client = new PostmarkClient($_ENV['POSTMARK']);

                        $sendResult = $client->sendEmailWithTemplate(
                            "hi@blablablogger.com",
                            $settings->email,
                            $template,
                            [
                                "name" => $settings->username,
                                "company_name" => $company_name,
                                "project_title" => $project_title,
                                "project_link" => $project_link
                            ]/*, true, null, true, null, null, null, null,
                            [$attachment] */);

                        return true;
                    } catch (Exception $e){
                        return $e;
                    }
                }
                else{ return false; } break;
            case(5):
                if($settings->opt5==1)
                {
                    try{

                        Mail::send('emails.to_blogger.opt5', array('data' => $data), function ($message) use ($settings)
                        {
                            $message->to($settings->email, $settings->username)->subject(trans('voc.b_opt5'));
                        });

                        return true;
                    } catch (Exception $e){
                        return false;
                    }
                }
                else{ return false; } break;
            case(6):/* shipment */
                if($settings->opt6==1)
                {
                    if($settings->lang=='en')
                    {
                        $template = 267821;
                    }
                    elseif($settings->lang=='ru')
                    {
                        $template = 267722;
                    }
                    else
                    {
                        $template = 267722;
                    }
                    try{

                        $company_name = ($data['company_name'])?$data['company_name']:'';
                        $project_title = ($data['project_title'])?$data['project_title']:'';
                        $project_link = URL::to('project/'.$data['project_id']);

                        $client = new PostmarkClient($_ENV['POSTMARK']);

                        $sendResult = $client->sendEmailWithTemplate(
                            "hi@blablablogger.com",
                            $settings->email,
                            $template,
                            [
                                "name" => $settings->username,
                                "company_name" => $company_name,
                                "project_title" => $project_title,
                                "project_link" => $project_link
                            ]/*, true, null, true, null, null, null, null,
                            [$attachment] */);

                        return true;
                    } catch (Exception $e){
                        return $e;
                    }
                }
                else{ return false; } break;

            case(8):/* message from company */
                if($settings->lang=='en')
                {
                    $template = 247508;
                }
                elseif($settings->lang=='ru')
                {
                    $template = 247485;
                }
                else
                {
                    $template = 247485;
                }
                try{

                    $company_name = ($data['company_name'])?$data['company_name']:'';
                    $project_title = ($data['project_title'])?$data['project_title']:'';
                    $project_link = URL::to('project/'.$data['project_id']);
                    $chat_msg = ($data['chat_msg'])?$data['chat_msg']:'';

                    $client = new PostmarkClient($_ENV['POSTMARK']);

                    $sendResult = $client->sendEmailWithTemplate(
                        "hi@blablablogger.com",
                        $settings->email,
                        $template,
                        [
                            "name" => $settings->username,
                            "company_name" => $company_name,
                            "project_title" => $project_title,
                            "chat_message" => $chat_msg,
                            "project_link" => $project_link
                        ]/*, true, null, true, null, null, null, null,
                        [$attachment] */);

                    return true;
                } catch (Exception $e){
                    return $e;
                }
                break;

        }

    }

    /**
     * @param $company_id
     * @param $type
     * @param $data
     * @return bool
     */
    public static function notice_company($company_id, $type, $data)
    {

        $user = DB::table('mail_settings AS ms')
            ->leftJoin('users AS u', 'ms.user_id', '=', 'u.id')
            ->leftJoin('companies AS c', 'c.id', '=', 'u.company_id')
            ->where('u.company_id', '=', $company_id)
            ->orderBy('ms.id', 'ASC')
            ->first();
/*
        $attachment = PostmarkAttachment::fromFile(URL::to('images/BlaBla-logo-black-wide.png'),
            "logo.png", "image/png");
*/
        /*
         * 1 - requests
         * 2 - project accepted
         * 3 - project details' update
         * 4 - changed payment
         *
         * 6 - shipment received
         *
         * 8 - project messages
         *
         * */


        switch($type)
        {
            case(1):/* request to company from blogger */
                if($user->opt1==1)
                {
                    if($user->lang=='en')
                    {
                        $template = 247301;
                    }
                    elseif($user->lang=='ru')
                    {
                        $template = 247302;
                    }
                    else
                    {
                        $template = 247302;
                    }
                    try{

                        $blogger_name = ($data['blogger_name'])?$data['blogger_name']:'';
                        $project_title = ($data['project_title'])?$data['project_title']:'';
                        $project_link = URL::to('project/'.$data['project_id']);

                        $client = new PostmarkClient($_ENV['POSTMARK']);

                        $sendResult = $client->sendEmailWithTemplate(
                            "hi@blablablogger.com",
                            $user->email,
                            $template,
                            [
                                "name" => $user->company_name,
                                "blogger_name" => $blogger_name,
                                "project_title" => $project_title,
                                "project_link" => $project_link
                            ]/*, true, null, true, null, null, null, null,
                            [$attachment] */);

                        return true;
                    } catch (Exception $e){
                        Session::put('error', $e );
                        return false;
                    }
                }
                else{ continue; }
                break;
            case(2):/* blogger accepted offer from company */

                if($user->opt2==1)
                {
                    if($user->lang=='en')
                    {
                        $template = 247283;
                    }
                    elseif($user->lang=='ru')
                    {
                        $template = 247284;
                    }
                    else
                    {
                        $template = 247284;
                    }
                    try{

                        $blogger_name = ($data['blogger_name'])?$data['blogger_name']:'';
                        $project_title = ($data['project_title'])?$data['project_title']:'';
                        $project_link = URL::to('project/'.$data['project_id']);

                        $client = new PostmarkClient($_ENV['POSTMARK']);

                        $sendResult = $client->sendEmailWithTemplate(
                            "hi@blablablogger.com",
                            $user->email,
                            $template,
                            [
                                "name" => $user->company_name,
                                "blogger_name" => $blogger_name,
                                "project_title" => $project_title,
                                "project_link" => $project_link
                            ]/*, true, null, true, null, null, null, null,
                            [$attachment] */);

                        return true;
                    } catch (Exception $e){
                        return false;
                    }
                }
                else{ continue; }

                break;
            case(3):/* Project's post details update */
                if($user->opt3==1)
                {
                    if($user->lang=='en')
                    {
                        $template = 247502;
                    }
                    elseif($user->lang=='ru')
                    {
                        $template = 247503;
                    }
                    else
                    {
                        $template = 247503;
                    }
                    try{

                        $blogger_name = ($data['blogger_name'])?$data['blogger_name']:'';
                        $project_title = ($data['project_title'])?$data['project_title']:'';
                        $project_link = URL::to('project/'.$data['project_id']);

                        $client = new PostmarkClient($_ENV['POSTMARK']);

                        $sendResult = $client->sendEmailWithTemplate(
                            "hi@blablablogger.com",
                            $user->email,
                            $template,
                            [
                                "name" => $user->company_name,
                                "blogger_name" => $blogger_name,
                                "project_title" => $project_title,
                                "project_link" => $project_link
                            ]/*, true, null, true, null, null, null, null,
                            [$attachment] */);

                        return true;
                    } catch (Exception $e){
                        return false;
                    }
                }
                else{ continue; }


                break;
            case(4):/* Offer updated. Payment */
                if($user->opt4==1)
                {
                    if($user->lang=='en')
                    {
                        $template = 247482;
                    }
                    elseif($user->lang=='ru')
                    {
                        $template = 247483;
                    }
                    else
                    {
                        $template = 247483;
                    }
                    try{

                        $blogger_name = ($data['blogger_name'])?$data['blogger_name']:'';
                        $project_title = ($data['project_title'])?$data['project_title']:'';
                        $project_link = URL::to('project/'.$data['project_id']);

                        $client = new PostmarkClient($_ENV['POSTMARK']);

                        $sendResult = $client->sendEmailWithTemplate(
                            "hi@blablablogger.com",
                            $user->email,
                            $template,
                            [
                                "name" => $user->company_name,
                                "blogger_name" => $blogger_name,
                                "project_title" => $project_title,
                                "project_link" => $project_link
                            ]/*, true, null, true, null, null, null, null,
                            [$attachment] */);

                        return true;
                    } catch (Exception $e){
                        return false;
                    }
                }
                else{ continue; }


                break;
            case(5):
                break;
            case(6):/* shipment received */
                if($user->opt6==1)
                {
                    if($user->lang=='en')
                    {
                        $template = 267822;
                    }
                    elseif($user->lang=='ru')
                    {
                        $template = 267723;
                    }
                    else
                    {
                        $template = 267723;
                    }
                    try{

                        $blogger_name = ($data['blogger_name'])?$data['blogger_name']:'';
                        $project_title = ($data['project_title'])?$data['project_title']:'';
                        $project_link = URL::to('project/'.$data['project_id']);

                        $client = new PostmarkClient($_ENV['POSTMARK']);

                        $sendResult = $client->sendEmailWithTemplate(
                            "hi@blablablogger.com",
                            $user->email,
                            $template,
                            [
                                "name" => $user->company_name,
                                "blogger_name" => $blogger_name,
                                "project_title" => $project_title,
                                "project_link" => $project_link
                            ]/*, true, null, true, null, null, null, null,
                            [$attachment] */);

                        return true;
                    } catch (Exception $e){
                        return false;
                    }
                }
                else{ continue; }


                break;

            case(8):
                if($user->lang=='en')
                {
                    $template = 247502;
                }
                elseif($user->lang=='ru')
                {
                    $template = 247504;
                }
                else
                {
                    $template = 247504;
                }
                try{

                    $blogger_name = ($data['blogger_name'])?$data['blogger_name']:'';
                    $project_title = ($data['project_title'])?$data['project_title']:'';
                    $project_link = URL::to('project/'.$data['project_id']);
                    $chat_msg = ($data['chat_msg'])?$data['chat_msg']:'';

                    $client = new PostmarkClient($_ENV['POSTMARK']);

                    $sendResult = $client->sendEmailWithTemplate(
                        "hi@blablablogger.com",
                        $user->email,
                        $template,
                        [
                            "name"          => $user->company_name,
                            "blogger_name"  => $blogger_name,
                            "project_title" => $project_title,
                            "chat_message"  => $chat_msg,
                            "project_link"  => $project_link
                        ]/*, true, null, true, null, null, null, null,
                        [$attachment] */);

                    return true;
                } catch (Exception $e){
                    return false;
                }

                break;

        }

    }

    public function unsubscribe()
    {
        $type = Input::get('type');

        $user = Auth::user();

        try{
            DB::table('mail_settings')
                ->where('user_id', '=', $user->id)
                ->update(array(
                    'opt'.$type => 0
                ));

            return Redirect::route('email-settings')->with('global', trans('voc.changes_saved'));
        }catch(Exception $e)
        {
            return Redirect::route('email-settings')->with('global_error', trans('error.error'));
        }


    }


}

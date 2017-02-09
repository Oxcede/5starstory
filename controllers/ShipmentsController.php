<?php

class ShipmentsController extends \BaseController {

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index()
	{
		$user = Auth::user();

        /* Blogger's shipments */
        if($user->role==2)
        {
            $projects = DB::table('projects AS p')
                ->leftJoin('project_blogger_conditions AS pbc', 'pbc.project_id', '=', 'p.id')
                ->leftJoin('shipping_details AS sd', function($join){
                    $join->on('sd.project_id', '=', 'p.id')
                        ->on('sd.blogger_id', '=', 'pbc.user_id');
                })
                ->leftJoin('companies AS c', 'c.id', '=', 'p.company_id')
                ->where('pbc.user_id', '=', $user->id)
                ->where('p.shipping', '=', DB::raw('1'))
                ->where('p.status', '=', DB::raw('1'))
                ->whereIn('pbc.status', array('2', '3', '4', '5'))
                ->select('sd.status', 'p.id AS project_id', 'p.company_id', 'p.title AS project_title',
                    'sd.country', 'sd.city', 'sd.zip', 'sd.address', 'sd.phone', 'sd.info',
                    'c.company_name')
                ->get();

            $sent = array();
            $received = array();

            foreach($projects as $project)
            {
                $sent[$project->project_id] = DB::table('eventlogs')
                    ->where('company_id', '=', $project->company_id)
                    ->where('sub_user_id', '=', $user->id)
                    ->where('event_id', '=', DB::raw('28'))
                    ->where('project_id', '=', $project->project_id)

                    ->first();

                $received[$project->project_id] = DB::table('eventlogs')
                    ->where('sub_company_id', '=', $project->company_id)
                    ->where('user_id', '=', $user->id)
                    ->where('event_id', '=', DB::raw('29'))
                    ->where('project_id', '=', $project->project_id)
                    ->first();
            }

            return View::make('shipment.index', array(
                'title' =>  'Shipments',
                'data'  =>  $projects,
                'user'  =>  $user,
                'sent'  =>  $sent,
                'received'  =>  $received
            ));
        }
        /* Company's shipments */
        elseif($user->role==3)
        {
            $projects = DB::table('projects AS p')
            ->leftJoin('project_blogger_conditions AS pbc', 'pbc.project_id', '=', 'p.id')
            ->leftJoin('shipping_details AS sd', function($join){
                $join->on('sd.project_id', '=', 'p.id')
                    ->on('sd.blogger_id', '=', 'pbc.user_id');
            })
            ->leftJoin('blogs AS bl', 'bl.id', '=', 'pbc.blog_id')
            ->where('p.company_id', '=', $user->company_id)
            ->where('p.shipping', '=', DB::raw('1'))
            ->where('p.status', '=', DB::raw('1'))
            ->whereIn('pbc.status', array('2', '3', '4', '5'))
            ->select('sd.status', 'p.id AS project_id', 'p.title AS project_title', 'sd.country', 'sd.city', 'sd.zip', 'sd.address',
                'sd.phone', 'sd.info', 'bl.title AS blog_title', 'pbc.user_id AS blogger_id', 'bl.thumb')
            ->get();

            $sent = array();
            $received = array();

            foreach($projects as $project)
            {
                $sent[$project->project_id][$project->blogger_id] = DB::table('eventlogs')
                    ->where('company_id', '=', $user->company_id)
                    ->where('sub_user_id', '=', $project->blogger_id)
                    ->where('event_id', '=', DB::raw('28'))
                    ->where('project_id', '=', $project->project_id)

                    ->first();

                $received[$project->project_id][$project->blogger_id] = DB::table('eventlogs')
                    ->where('sub_company_id', '=', $user->company_id)
                    ->where('user_id', '=', $project->blogger_id)
                    ->where('event_id', '=', DB::raw('29'))
                    ->where('project_id', '=', $project->project_id)
                    ->first();
            }

            return View::make('shipment.index', array(
                'title' =>  'Shipments',
                'data'  =>  $projects,
                'user'  =>  $user,
                'sent'  =>  $sent,
                'received'  =>  $received
            ));

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


}

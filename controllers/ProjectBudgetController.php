<?php

class ProjectBudgetController extends \BaseController {

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
        $bloggers = DB::table('project_blogger_conditions AS pb')
            ->leftJoin('users AS u', 'u.id', '=', 'pb.user_id')
            ->leftJoin('budget AS b', 'b.id', '=', 'pb.budget_id')
            ->leftJoin('projects AS p', 'p.id', '=', 'pb.project_id')
            ->where('pb.project_id', '=', $id)
            ->where('p.user_id', '=', Auth::user()->id)
            ->select('b.id AS budget_id', 'b.blogger_payment', 'b.free_order_sum', 'u.username', 'u.id AS blogger_id')
            ->get();

		return View::make('projects.budget.create', array('title' => trans('voc.budget'), 'pid' => $id, 'bloggers' => $bloggers));
	}


	/**
	 * Update the specified resource in storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function update($id)
	{
        $cur = (Session::get('cur'))?Session::get('cur'):1;
        /* Check edit rights */
        $right = DB::table('projects')
            ->where('id', '=', $id)
            ->first();
        if($right->user_id == Auth::user()->id)
        {
            /* count bloggers */
            $bloggers = DB::table('project_blogger_conditions')
                ->where('project_id', '=', $id)
                ->get();
            $count = count($bloggers);
            /* loop form */
            for( $a=1; $a<=$count; $a++ )
            {
                $ifbudget = Budget::find(Input::get('budget_id_'.$a));

                if( !empty( $ifbudget->id ) )
                {
                    $budget = Budget::find($ifbudget->id);

                    $budget->blogger_payment    = Input::get('blogger_payment_'.$a);
                    $budget->payment_agree_company  = 1;
                    $budget->free_order_sum          = Input::get('free_order_sum'.$a);

                    if($budget->save())
                    {
                        Eventlog::create(array(
                            'user_id' => Auth::user()->id,
                            'event_id' => 12,
                            'project_id' => $id,
                            'sub_user_id' => null,
                            'post_id' => null,
                        ));
                    }
                    else
                    {
                        return Redirect::back()
                            ->with('global_error', 'Error updating');
                    }
                }
                else
                {
                    $save = Budget::create(array(
                            'project_id'        => $id,
                            'user_id'           => Input::get('blogger_id_'.$a),
                            'blogger_payment'   => Input::get('blogger_payment_'.$a),
                            'payment_agree_company' => 1,
                            'free_order_sum'         => Input::get('free_order_sum'.$a),
                            'cur_id'            =>  $cur,
                        ));
                    if($save->count())
                    {
                        DB::table('project_blogger_conditions')
                            ->where('project_id', '=', $id)
                            ->where('user_id', '=', Input::get('blogger_id_'.$a))
                            ->update(array('budget_id' => $save->id));
                    }
                    else
                    {
                        Redirect::back()
                            ->with('global_error', 'Error saving');
                    }
                }
            }
            return Redirect::to('projects/'.$id.'/posts')
                ->with('global', 'Budget updated');
        }
        else
        {
            Redirect::back()
                ->with('global_error', 'You have no rights to edit this project');
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


}

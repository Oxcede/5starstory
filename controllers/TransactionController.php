<?php

class TransactionController extends \BaseController {

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index()
	{
        $user = Auth::user();

        /* Blogger */
        if($user->role==2)
        {

            $tr = DB::table('budget AS bt')
                ->leftJoin('transactions AS tr', 'tr.bt_id', '=', 'bt.bt_id')
                ->whereNotNull('tr.bt_id')
                ->where('bt.user_id', '=', $user->id)
                ->get();

            if($tr)
            {
                foreach($tr as $bt)
                {
                    $transactions[$bt->bt_id] = DB::table('transactions AS tr')
                        ->leftJoin('budget AS bt', 'bt.bt_id', '=', 'tr.bt_id')
                        ->leftJoin('projects AS p', 'p.id', '=', 'bt.project_id')
                        ->leftJoin('blogs AS bl', function($join){ $join->on('bl.user_id', '=', 'bt.user_id')->on('bl.media_id', '=', DB::raw('1'));})
                        ->where('tr.bt_id', '=', $bt->bt_id)
                        ->select(
                            'p.id AS project_id', 'p.title', 'tr.created_at', 'tr.updated_at', 'bt.user_id AS blogger_id',
                            'bl.title AS blog_title', 'bt.youtube_payment', 'bl.thumb', 'tr.sum', 'tr.service_fee',
                            'bt.cur_id', 'tr.status'
                        )
                        ->get();
                }
                return View::make('payments.index', array('title'=>'Payments', 'transactions'=>$transactions));
            }
            else
            {
                return View::make('payments.index', array('title'=>'Payments'));
            }

            return Response::view('errors.missing', array('title'=>'No rights', 'error' => 'No transactions'), 404);
            //dd($transactions);

        }
        /* Company */
        elseif($user->role==3)
        {
            $transactions = array();

            $tr = DB::table('transactions')
                ->where('company_id', '=', $user->company_id)
                ->select('bt_id')
                ->groupBy('bt_id')
                ->get();

            if(!empty($tr))
            {
                foreach($tr as $bt)
                {
                    $transactions[$bt->bt_id] = DB::table('transactions AS tr')
                        ->leftJoin('budget AS bt', 'bt.bt_id', '=', 'tr.bt_id')
                        ->leftJoin('projects AS p', 'p.id', '=', 'bt.project_id')
                        ->leftJoin('blogs AS bl', function($join){ $join->on('bl.user_id', '=', 'bt.user_id')->on('bl.media_id', '=', DB::raw('1'));})
                        ->where('tr.bt_id', '=', $bt->bt_id)
                        ->select(
                            'p.id AS project_id', 'p.title', 'tr.created_at', 'tr.updated_at', 'bt.user_id AS blogger_id',
                            'bl.title AS blog_title', 'bt.youtube_payment', 'bl.thumb', 'tr.sum', 'tr.service_fee',
                            'bt.cur_id'
                        )
                        ->get();
                }
            }

            //echo '<pre>';
            //dd($transactions);

            return View::make('payments.index', array(
                'title'         =>  trans('voc.payments'),
                'transactions'  =>  $transactions
            ));

        }
        /* Admin */
        elseif($user->role==4)
        {

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

    /**
     *  Generates transaction pdf invoice
     */
    public function invoice_pdf($bt_id)
    {
        $user = Auth::user();

        $company = DB::table('companies')
            ->where('id', '=', $user->company_id)
            ->first();

        $data = DB::table('transactions AS tr')
            ->leftJoin('budget AS b', 'tr.bt_id', '=', 'b.bt_id')
            ->where('tr.bt_id', '=', $bt_id)
            ->where('tr.company_id', '=', $user->company_id)
            ->select('tr.sum', 'tr.created_at', 'b.cur_id')
            ->get();

        $sum = 0;
        $tax = 0;
        $taxpc = 0;
        $service_fee = (!empty($company->service_fee))?$company->service_fee:19;

        if($company->country_id==1)
        {
            $taxpc = 18;
        }

        if($data)
        {
            foreach($data as $tr)
            {
                $sum += $tr->sum;
            }
        }

        $sum = $sum + $sum/100*$service_fee;

        $tax = $sum/100*$taxpc;

        $taxfree_sum = $sum;

        $sum = $sum + $tax;



        $pdf = PDF::loadView('payments.invoice_pdf', array(
            'id'=>$bt_id,
            'data'=>$data,
            'tax'=>$tax,
            'sum'=>$sum,
            'company'=>$company,
            'service_fee'=>$service_fee,
            'taxfree_sum'=>$taxfree_sum
        ));
        return $pdf->stream('invoice_'.$bt_id.'.pdf');
    }

    /**
     *  Generates transaction pdf invoice
     */
    public function invoice_html($bt_id)
    {
        return View::make('payments.invoice_html', array('id'=>$bt_id));
    }


}

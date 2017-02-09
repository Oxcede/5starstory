<?php
Class CurrencyController extends BaseController {

    public function select($cur)
    {
        Session::put('cur', $cur);

        return Redirect::back();
    }

    public static function set_currency()
    {
        $company_id = Auth::user()->company_id;

        if($company_id)
        {
            $c = DB::table('companies')
                ->where('id', Auth::user()->company_id)
                ->first();

            Session::put('cur', $c->cur_id);
        } else {
            Session::put('cur', 1);
        }


    }

}
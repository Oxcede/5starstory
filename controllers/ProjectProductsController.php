<?php

class ProjectProductsController extends \BaseController {

	/**
	 * Display a listing of the resource.
	 *
     * @param  int  $id
	 * @return Response
	 */
	public function index($id)
	{
        $products = DB::table('project_products AS pp')
            ->leftJoin('products AS p', 'p.id', '=', 'pp.product_id')
            ->select('p.id', 'p.title', 'p.description', 'p.link', 'p.image')
            ->where('pp.project_id',$id)
            ->get();

		return View::make('projects.products.index', array('title' => 'Products', 'products' => $products, 'pid' => $id));
	}


	/**
	 * Show the form for creating a new resource.
	 *
     * @param  int  $project_id
	 * @return Response
	 */
	public function create($project_id)
	{
        $products = Product::where('user_id', '=', Auth::user()->id)
            ->get();

        if(!empty($products[0]))
        {
            foreach($products as $v)
            {
                $p[$v->id] = $v->title;
            }
        } else { $p = array(); }

        $pp = DB::table('project_products')
            ->where('project_id', '=', $project_id)
            ->get();

        if(!empty($pp[0]))
        {
            foreach($pp as $sel)
            {
                $selected[$sel->product_id] = $sel->product_id;
            }
        } else { $selected = array(); }

        $product_c = DB::table('product_categories')
            ->get();
        $product_categories = array();
        foreach($product_c as $v)
        {
            $product_categories[$v->id] = trans('category.'.$v->name);
        }


		return View::make('projects.products.create', array('title' => trans('voc.add_products'), 'products' => $p, 'selected' => $selected, 'pid' => $project_id, 'categories' => $product_categories ));
	}


	/**
	 * Store a newly created resource in storage.
	 *
     * @param  int  $project_id
	 * @return Response
	 */
	public function store($project_id)
	{
        if(Input::get('project_products'))
        {
            DB::table('project_products')
                ->where('project_id', '=', $project_id)
                ->delete();

            foreach(Input::get('project_products') as $v)
            {
                DB::table('project_products')
                    ->insert(array('project_id' => $project_id, 'product_id' => $v));

            }
        }

        if(Input::get('product_title'))
        {
            $validator = Validator::make(Input::all(),
                array(
                    'product_title'         => 'required',
                    'product_description'   => 'required',
                )
            );

            if ($validator->fails())
            {
                return Redirect::to('projects/'.$project_id.'/products/create')
                    ->withErrors($validator)
                    ->with('global_error', 'Some fields are filled incorrectly or not filled')
                    ->withInput();
            }
            else
            {

                /* Saving product data */
                $product = Product::create(array(
                    'title'         => Input::get('product_title'),
                    'category_id'   => Input::get('product_category'),
                    'description'   => Input::get('product_description'),
                    'user_id'       => Auth::user()->id,
                    'link'          => Input::get('product_link'),
                ));

                if ($product)
                {

                    if(file_exists(URL::to('images/products/'.$product->id.'.jpg')))
                    {
                        Image::make('images/products/'.$product->id.'.jpg')->destroy();
                    }
                    if(Input::file('product_image'))
                        Image::make(Input::file('product_image'))->resize(300, 200)->save('images/products/'.$product->id.'.jpg');

                    $pp = DB::table('project_products')
                        ->insert(array('project_id' => $project_id, 'product_id' => $product->id));

                    if($pp)
                    {
                        Eventlog::create(array(
                            'user_id' => Auth::user()->id,
                            'event_id' => 11,
                            'project_id' => $project_id,
                            'product_id' => $product->id,
                            'post_id' => null,
                        ));

                        return Redirect::to('projects/'.$project_id.'/budget');
                    }

                }
                else
                {
                    return 'fail';
                }

            }
        }

        return Redirect::to('projects/'.$project_id.'/budget')
            ->with('global', 'Products updated');
	}


	/**
	 * Display the specified resource.
	 *
	 * @param  int  $project_id
	 * @param  int  $product_id
	 * @return Response
	 */
	public function show($project_id, $product_id)
	{
		return $project_id.' '.$product_id;
	}


	/**
	 * Show the form for editing the specified resource.
	 *
	 * @param  int  $project_id
	 * @param  int  $product_id
	 * @return Response
	 */
	public function edit($project_id, $product_id)
	{
		//
	}


	/**
	 * Update the specified resource in storage.
	 *
     * @param  int  $project_id
     * @param  int  $product_id
	 * @return Response
	 */
	public function update($project_id, $product_id)
	{
		//
	}


	/**
	 * Remove the specified resource from storage.
	 *
     * @param  int  $project_id
     * @param  int  $product_id
	 * @return Response
	 */
	public function destroy($project_id, $product_id)
	{
        $check = DB::table('projects')
            ->where('id','=', $project_id)
            ->where('user_id','=',Auth::user()->id)
            ->get();
        if($check)
        {
            $delete = DB::table('project_products')
                ->where('product_id','=', $product_id)
                ->where('project_id','=', $project_id)
                ->delete();
            if($delete)
            {
                return Redirect::back()->with('global','Product removed');
            }
            return Redirect::back()->with('global_error','Couldn\'t remove product');
        }
        return Redirect::back()->with('global_error','Wrong project');
	}


}

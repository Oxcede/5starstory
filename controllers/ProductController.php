<?php

class ProductController extends \BaseController {

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index()
	{
		
		return View::make('products.products', array('title'=>trans('voc.products'),'products'=>Product::where('user_id','=',Auth::user()->id)->paginate(6)));
	}


	/**
	 * Show the form for creating a new resource.
	 *
	 * @return Response
	 */
	public function create()
	{
        $product_c = DB::table('product_categories')
            ->get();

        $product_categories = array();

        foreach($product_c as $v)
        {
            $product_categories[$v->id] = trans('category.'.$v->name);
        }

		return View::make('products.create', array('title' => trans('voc.create_new_product'), 'categories' => $product_categories ));
	}


	/**
	 * Store a newly created resource in storage.
	 *
	 * @return Response
	 */
	public function store()
	{
        $validator = Validator::make(Input::all(),
            array(
                'product_title'         => 'required',
                'product_category'      => 'required',
                'product_description'   => 'required',
            )
        );

        if ($validator->fails())
        {
            return Redirect::route('products.create')
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

            if(Input::file('product_image'))
            {
                Image::make(Input::file('product_image'))->resize(null, 200, function ($constraint) {
                    $constraint->aspectRatio();
                })->save('images/products/'.$product->id.'.png');
            }

            if ($product->count())
            {
                Eventlog::create(array(
                    'user_id' => Auth::user()->id,
                    'event_id' => 5,
                    'project_id' => null,
                    'product_id' => $product->id,
                    'post_id' => null,
                ));

                return Redirect::route('products.index')
                    ->with('global', 'Your product has been created.');
            }

        }
        return Redirect::route('products.create')
            ->with('global_error', 'Could not create product');

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
	 * @param  int  $product_id
	 * @return Response
	 */
	public function edit($product_id)
	{

        $product = Product::find($product_id);

        if($product)
        {
            $product_c = DB::table('product_categories')
                ->get();
            $product_categories = array();

            foreach($product_c as $v)
            {
                $product_categories[$v->id] = trans('category.'.$v->name);
            }

            return View::make('products.edit', array('title' => trans('voc.edit_product'), 'product' => $product, 'categories' => $product_categories ));
        }

        App::missing(function($exception)
        {
            return Response::view('errors.missing', array('title'=>'Product not found', 'error' => 'Product doesn\'t exist'), 404);
        });
        return App::abort(404);

	}


	/**
	 * Update the specified resource in storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function update($id)
	{
        $validator = Validator::make(Input::all(),
            array(
                'product_title'         => 'required',
                'product_description'   => 'required',
            )
        );

        if ($validator->fails())
        {
            return Redirect::route('products.create')
                ->withErrors($validator)
                ->with('global_error', 'Some fields are filled incorrectly or not filled')
                ->withInput();
        }
        else
        {

            /* Saving product data */
            $product = Product::find($id);
            $product->title = Input::get('product_title');
            $product->description = Input::get('product_description');
            $product->link = Input::get('product_link');
            $product->save();

            if(Input::file('product_image'))
            {
                if(file_exists('images/products/'.$id.'.png'))
                {
                    Image::make('images/products/'.$id.'.png')->destroy();
                }
                Image::make(Input::file('product_image'))->resize(null, 200, function ($constraint) {
                    $constraint->aspectRatio();
                })->save('images/products/'.$id.'.png');
            }


            if ($product->count())
            {
                Eventlog::create(array(
                    'user_id' => Auth::user()->id,
                    'event_id' => 6,
                    'project_id' => null,
                    'product_id' => $id,
                    'post_id' => null,
                ));

                return Redirect::route('products.index')
                    ->with('global', 'Your product has been updated.');
            }

        }
        return Redirect::route('products/'.$id.'/edit')
            ->with('global_error', 'Could not update product');
	}


	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function destroy($id)
	{
        /*try{*/
            $now = DB::raw('NOW()');

            $delete = DB::table('products')
                ->where('id', $id)
                ->where('user_id', Auth::user()->id)
                ->update(array('deleted_at'=>$now));
        /*}
        catch(Exception $e){
            return Redirect::back()
                ->with('global_error', 'Could not delete product');
        }*/

        if ($delete)
        {
            return Redirect::back()
                ->with('global', 'Product deleted');
        }
        else
        {
            return Redirect::back()
                ->with('global_error', 'Could not delete product');
        }
	}


}

<?php

class HomeController extends BaseController {

	public function home()
	{

        return View::make('home', array( 'title' => '5 Star Story'));

	}

}

<?php

/**
 * Pubsubhubbub routes
 */

Route::group(['namespace' => 'Ortegacmanuel\PubsubhubbubLaravel\Controllers'], function()
{

	Route::get('/pubsubhubbub/push/hub', function (Request $request) {
		return 'ok';
	})->name('push_actions.get');

	Route::post('/pubsubhubbub/push/hub', 'PushActionsController@handle')->name('push_actions.post');

});

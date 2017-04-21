<?php

namespace Ortegacmanuel\PubsubhubbubLaravel;

use Ortegacmanuel\ActivitystreamsLaravel\Activity;
use Illuminate\Support\Facades\Log;

class ActivityObserver
{
    /**
     * Listen to the Activity created event.
     *
     * @param  User  $user
     * @return void
     */
    public function created(Activity $activity)
    {

        $atom = $activity->userFeedForNotice();

        Log::info("observer ejecutandose");

        $feed = route('user_feed', $activity->actor->id);

        Log::info("para el feed $feed");

        $subscribers = HubSub::where('topic', $feed)->get();

        foreach ($subscribers as $subscriber) {

            $headers = array('Content-Type' => 'application/atom+xml');
            if ($subscriber->secret) {
                $hmac = hash_hmac('sha1', $atom, $subscriber->secret);
                $headers['X-Hub-Signature'] = 'sha1=' . $hmac;
            } else {
                $hmac = '(none)';
            }

            Log::info("About to push feed to $subscriber->callback for {$subscriber->getTopic()}, HMAC $hmac");

            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $subscriber->callback, [
                'allow_redirects' => false,
                'headers' => $headers,
                'body' => $atom
            ]);

            $status = $response->getStatusCode();            

            Log::info("Push para el feed $feed se ejecuto con el status code: $status");
        }
    }
}
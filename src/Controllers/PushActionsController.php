<?php

 namespace Ortegacmanuel\PubsubhubbubLaravel\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Ortegacmanuel\PubsubhubbubLaravel\HubSub;

class PushActionsController extends Controller
{

	protected $request;

	public function __construct(Request $request)
	{
	    $this->request = $request;
	}

    public function handle()
    {

        $mode = $this->request->input('hub_mode');

        switch ($mode) {
	        case "subscribe":
	        case "unsubscribe":
	            $this->subunsub($mode);
	            break;
	        case "publish":
				abort(400, 'Publishing outside feeds not supported.');
	        default:
				abort(400, sprintf('Unrecognized mode "%s".',$mode));
        }
    }

    /**
     * Process a request for a new or modified PuSH feed subscription.
     * If asynchronous verification is requested, updates won't be saved immediately.
     *
     * HTTP return codes:
     *   202 Accepted - request saved and awaiting verification
     *   204 No Content - already subscribed
     *   400 Bad Request - rejecting this (not specifically spec'd)
     */
    public function subunsub($mode)
    {

        $callback = $this->request->input('hub_callback');

        Log::info('New PuSH hub request ('. $mode .') for callback '. $callback);

		$topic = $this->request->input('hub_topic');

        if (!$this->recognizedFeed($topic)) {
            common_debug('PuSH hub request had unrecognized feed topic=='. $topic);
            // TRANS: Client exception. %s is a topic.
            abort(400, sprintf('Unsupported hub.topic %s this hub only serves local user and group Atom feeds.', $topic));
        }

        $lease = $this->request->input('hub_lease_seconds', null);

        if ($mode == 'subscribe' && $lease != '' && !preg_match('/^\d+$/', $lease)) {
            Log::info('PuSH hub request had invalid lease_seconds=='. $lease);
            // TRANS: Client exception. %s is the invalid lease value.
            abort(400, sprintf('Invalid hub.lease "%s". It must be empty or positive integer.', $lease));
        }

        $secret = $this->request->input('hub_secret', null);

        if ($secret != '' && strlen($secret) >= 200) {
            Log::info('PuSH hub request had invalid secret=='. $secret);
            // TRANS: Client exception. %s is the invalid hub secret.
            abort(400, sprintf('Invalid hub.secret "%s". It must be under 200 bytes.', $secret));
        }

        $sub = HubSub::find(HubSub::hashkey($topic, $callback));

        if (!$sub instanceof HubSub) {
            // Creating a new one!
            Log::info('PuSH creating new HubSub entry for topic=='. $topic .' to remote callback '. $callback);

            $sub = new HubSub();
            $sub->topic = $topic;
            $sub->callback = $callback;
        }
        if ($mode == 'subscribe') {
            if ($secret) {
                $sub->secret = $secret;
            }
            if ($lease) {
                $sub->setLease(intval($lease));
            }
        }

        Log::info('PuSH hub request is now:'. $sub);

        $verify = $this->request->input('hub.verify'); // TODO: deprecated
        $token = $this->request->input('hub.verify_token', null); // TODO: deprecated
        if ($verify == 'sync') {    // pre-0.4 PuSH
            $sub->verify($mode, $token);
            return response('', 204);
        } else {    // If $verify is not "sync", we might be using PuSH 0.4
            //$sub->scheduleVerify($mode, $token);    // If we were certain it's PuSH 0.4, token could be removed
            // TODO implementar scheduleVerify utilizando queuing laravel
            $sub->verify($mode, $token);
            return response('', 202);
        }
    }

          /**
     * Check whether the given URL represents one of our canonical
     * user or group Atom feeds.
     *
     * @param string $feed URL
     * @return boolean true if it matches, false if not a recognized local feed
     * @throws exception if local entity does not exist
     */
    protected function recognizedFeed($feed)
    {
        $matches = array();
        // Simple mapping to local ID for user or group
        if (preg_match('!/(\d+)\.atom$!', $feed, $matches)) {
            $id = $matches[1];
            $params = array('id' => $id, 'format' => 'atom');

            // Double-check against locally generated URLs

            $actor_model = \Config::get('pubsubhubbub-laravel.actor_model');

            switch ($feed) {
                case route('user_feed', $id):
                    $user = $actor_model::find($id);
                    if (!$user instanceof $actor_model) {
                        // TRANS: Client exception. %s is a feed URL.
                        abort(400, sprintf('Invalid hub.topic "%s". User does not exist.', $feed));
                    }
                    return true;
                default:
                   return false;
                /** TODO implementar las feeds de los grupos
                case common_local_url('ApiTimelineGroup', $params):
                    $group = Local_group::getKV('group_id', $id);
                    if (!$group instanceof Local_group) {
                        // TRANS: Client exception. %s is a feed URL.
                        throw new ClientException(sprintf(_m('Invalid hub.topic "%s". Local_group does not exist.'),$feed));
                    }
                    return true;
                **/
            }
            common_debug("Feed was not recognized by any local User or Group Atom feed URLs: {$feed}");
            return false;
        }

        common_debug("Unknown feed URL structure, can't match against local user, group or profile_list: {$feed}");
        return false;        
    }
}

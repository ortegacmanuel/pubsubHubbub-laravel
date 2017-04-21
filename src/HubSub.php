<?php

namespace Ortegacmanuel\PubsubhubbubLaravel;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class HubSub extends Model
{

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'hubsub';

    protected $primaryKey = 'hashkey';

    public $incrementing = false;    

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['created_at', 'updated_at'];

    public function getTopic()
    {
        return $this->topic;
    }

    public function getLeaseTime()
    {
        if (empty($this->sub_start) || empty($this->sub_end)) {
            return null;
        }
        $length = strtotime($this->sub_end) - strtotime($this->sub_start);
        assert($length > 0);
        return $length;
    }

    static function hashkey($topic, $callback)
    {
        return sha1($topic . '|' . $callback);
    }

    public function setHashkey()
    {
        $this->hashkey = self::hashkey($this->topic, $this->callback);
    }    

    /**
     * Validates a requested lease length, sets length plus
     * subscription start & end dates.
     *
     * Does not save to database -- use before insert() or update().
     *
     * @param int $length in seconds
     */
    function setLease($length)
    {
        Log::info('PuSH hub got requested lease_seconds=='. $length);

        assert(is_int($length));

        $min = 86400;   // 3600*24 (one day)
        $max = 86400 * 30;

        if ($length == 0) {
            // We want to garbage collect dead subscriptions!
            $length = $max;
        } elseif( $length < $min) {
            $length = $min;
        } else if ($length > $max) {
            $length = $max;
        }

        Log::info('PuSH hub after sanitation: lease_seconds=='. $length);

        $this->sub_start = Carbon::now();
        $this->sub_end = Carbon::now()->addSeconds($length);
    }

    /**
     * Send a verification ping to subscriber, and if confirmed apply the changes.
     * This may create, update, or delete the database record.
     *
     * @param string $mode 'subscribe' or 'unsubscribe'
     * @param string $token hub.verify_token value, if provided by client
     * @throws ClientException on failure
     */
    function verify($mode, $token=null)
    {
        assert($mode == 'subscribe' || $mode == 'unsubscribe');

        $challenge = PubsubhubbubUtils::commonRandomHexstr(32);

        $params = array('hub.mode' => $mode,
                        'hub.topic' => $this->getTopic(),
                        'hub.challenge' => $challenge);
        if ($mode == 'subscribe') {
            $params['hub.lease_seconds'] = $this->getLeaseTime();
        }
        if ($token !== null) {  // TODO: deprecated in PuSH 0.4
            $params['hub.verify_token'] = $token;   // let's put it in there if remote uses PuSH <0.4
        }

        // Any existing query string parameters must be preserved
        $url = $this->callback;
        if (strpos($url, '?') !== false) {
            $url .= '&';
        } else {
            $url .= '?';
        }
        $url .= http_build_query($params, '', '&');

        $client = new \GuzzleHttp\Client();
        $response = $client->request('GET', $url);

        $status = $response->getStatusCode();

        /*
        $request = new HTTPClient();
        $response = $request->get($url);
        $status = $response->getStatus();
        */

        if ($status >= 200 && $status < 300) {
            Log::info("Verified {$mode} of {$this->callback}:{$this->getTopic()}");
        } else {
            // TRANS: Client exception. %s is a HTTP status code.
            abort(400, sprintf('Hub subscriber verification returned HTTP %s.',$status));
        }

        $old = HubSub::find(HubSub::hashkey($this->getTopic(), $this->callback));
        if ($mode == 'subscribe') {
            if ($old instanceof HubSub) {
                $old->topic = $this->topic;
                $old->callback = $this->callback;
                $old->secret = $this->secret;
                $old->sub_start = $this->sub_start;
                $old->sub_end = $this->sub_end;
                $old->save();
            } else {
                $this->setHashkey();
                $ok = $this->save();
            }
        } else if ($mode == 'unsubscribe') {
            if ($old instanceof HubSub) {
                $old->delete();
            } else {
                // That's ok, we're already unsubscribed.
            }
        }
    }    
}
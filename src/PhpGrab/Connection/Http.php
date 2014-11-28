<?php
#namespace PhpGrab\Connection;

class Http {

    const DEFAULT_TIMEOUT = 60;

    public $cookies,
        $curl,
        $result,
        $log_path;

    private //$proxies,
        //$user_agents,
        $user_agent,
        $sec_min,
        $sec_max,
        $cookie_file,
        $_resp_hdr;

    protected $default_header = array(
        'Accept'          => '',
        'Accept-Language' => 'en-us,en',
        'Accept-Charset'  => 'UTF-8,ISO-8859-1;q=0.7,*;q=0.7',
        'Keep-Alive'      => 300,
        'Connection'      => 'keep-alive',
        'Expect'          => '',
        'Pragma'          => '',
    );


    public function __construct()
    {
        $this->curl 		   = curl_init();
        //$this->proxies 		   = array();
        $this->user_agents 	   = array();
        $this->cookies 		   = array();
        $this->log_path		   = 'output/';
        $this->init();
    }

    public function reset()
    {
        $this->close();
        $this->curl = curl_init();
        $this->init();
    }

    public function init(){
        $options = array(
            CURLOPT_RETURNTRANSFER 	=> true,
            CURLOPT_FOLLOWLOCATION 	=> false,
            CURLOPT_HEADERFUNCTION  => array($this, 'parse_hdr'),
            CURLOPT_ENCODING        => 'gzip,deflate',
            CURLOPT_HTTPHEADER      => $this->default_header,
            #CURLOPT_ENCODING       	=> "",
            #CURLOPT_HTTPPROXYTUNNEL => true,
            #CURLOPT_PROXYAUTH		=> CURLAUTH_BASIC,
            #CURLOPT_COOKIEFILE	    => '',
            #CURLOPT_COOKIEJAR	    => '',
            #CURLOPT_AUTOREFERER		=> true,
            CURLOPT_SSL_VERIFYPEER	=> false,
            CURLOPT_SSL_VERIFYHOST	=> false,
            CURLOPT_CONNECTTIMEOUT	=> self::DEFAULT_TIMEOUT,
            CURLOPT_TIMEOUT         => self::DEFAULT_TIMEOUT,
            CURLOPT_VERBOSE         => false
        );

        $this->assing_opt($options);
        //$this->cookie_file = 'grobot_cookies_' . time() . '.txt';
    }

    public function set_header($header=array()) {
        $this->default_header = array_merge($this->default_header, $header);
    }

    public function unset_header($header)
    {
        if (!is_array($header)) {
            $header = array($header);
        }

        foreach ($header as $h) {
            unset($this->default_header[$h]);
        }
        return true;
    }

    static public function prepare_header_key($key)
    {
        return ('X-' == substr($key, 0, 2))
            ? $key
            : implode('-', array_map('ucfirst', explode('-', strtolower($key))));
    }

    /**
     * Parses HTTP headers for cookies
     *
     * @param resource $conn cURL connection resource
     * @param string   $hdr  HTTP header line
     * @return int Header line length
     */
    public function parse_hdr($conn, $hdr)
    {
        list($key, $value) =
            array_pad(array_map('trim', explode(':', $hdr, 2)), 2, null);
        $key = $this->prepare_header_key($key);
        if (null !== $value) {
            if ('Set-Cookie' == $key) {
                $crumbs = array();
                foreach (explode(';', $value) as $s) {
                    $s = array_map('trim', explode('=', $s, 2));
                    $crumbs[$s[0]] = @$s[1];
                }
                reset($crumbs);
                list($k, $v) = each($crumbs);
                if (empty($crumbs['domain'])) {
                    $u = parse_url($this->last_url());
                    $crumbs['domain'] = $u['host'];
                }
                if ('deleted' == $v) {
                    unset($this->cookies[$k][$crumbs['domain']]);
                } else {
                    $this->cookies[$k][$crumbs['domain']] = array(
                        'value'   => $v,
                        'expires' => strtotime(@$crumbs['expires']),
                    );
                }
            } else {
                $this->_resp_hdr[$key] = $value;
                #print "key: $key :: value:: $value";
            }
        }
        return strlen($hdr);
    }

    /**
     * Returns HTTP response headers value
     *
     * @param string $name HTTP response header name, null to return all headers
     * @return array|string|null
     */
    public function get_response_header($name=null)
    {
        if (null === $name) {
            return $this->_resp_hdr;
        } else {
            $name = $this->prepare_header_key($name);
            return isset($this->_resp_hdr[$name])
                ? $this->_resp_hdr[$name]
                : null;
        }
    }
    public function get_cookies()
    {
        return $this->cookies;
    }

    public function load_cookies(array $cookies)
    {
        $this->cookies = $cookies;
    }

    public function delete_cookies_all()
    {
        $this->cookies = array();
    }

    public function set_cookies($url)
    {
        $url = parse_url($url);
        $cookies = array();
        $options = array();

        foreach ($this->cookies as $k => $cookie) {
            foreach ($cookie as $domain => $v) {
                if ($v['expires'] && (time() > $v['expires'])) {
                    unset($this->cookies[$k][$domain]);
                } else if (!$domain || (false !== strpos(
                            '.' . $url['host'],
                            $domain
                        ))) {
                    $cookies[$k] = $v['value'];
                    if ($url['host'] == $domain) {
                        break;
                    }
                }
            }
            if (empty($this->cookies[$k])) {
                unset($this->cookies[$k]);
            }
        }

        if (count($cookies)) {
            $options[CURLOPT_COOKIE] = array();
            foreach ($cookies as $k => $v) {
                $options[CURLOPT_COOKIE][] = "{$k}={$v}";
            }
            $options[CURLOPT_COOKIE] = implode('; ', $options[CURLOPT_COOKIE]);
        }

        #print_r($options);
        #curl_setopt_array($this->curl, $options);
        $this->assing_opt($options);
        #$this->assing_opt(CURLOPT_COOKIE,  $cookiesstr/*$cookies*/);
        return true;
    }

    public function get_post_string(array $fields, $encode=true)
    {
        $a = array();
        foreach ($fields as $k => $v) {
            if ($encode) {
                $a[] = rawurlencode($k) . '=' . rawurlencode($v);
            } else {
                $a[] = $k . '=' . $v;
            }
        }
        return implode('&', $a);
    }

    public function assing_opt($curl_opt, $value=NULL)
    {
        if(isset($value)){
            $curl_opt = array($curl_opt => $value);
        }

        curl_setopt_array($this->curl, $curl_opt);
        return true;

    }

    public function get_result($url=null, $sleep=true)
    {

        if (isset($url)) {
            $this->assing_opt(CURLOPT_URL, $url);
        }
        if ($sleep) {
            $this->snooze();
        }

        #for ($i=0; $i<3; $i++) {
        $this->set_cookies($url);
        $this->result = curl_exec($this->curl);
        #	break;
        #}
        #}

        #if ($this->auto_handle_cookies) {
        //$this->handle_cookies();

        #}
        if (3 == (int)($this->get_info(CURLINFO_HTTP_CODE)/100)) {

            $location = $this->get_response_header('Location');
            if ($location) {

                $last_url  = parse_url($this->last_url());
                $pathArray = explode("/", $last_url['path']);
                $pathArray[sizeof($pathArray)-1] = $location ;

                $url = $last_url['scheme']."://".$last_url['host'].implode("/", $pathArray);
                /* print "\n";
                 print_r($url);
                 print "\n";*/
                return $this->get($url);
            }
            //print "no location present";
            //print "\n";
        }

        return $this;//$this->result;
    }

    public function get_error()
    {
        return curl_errno($this->curl);
    }

    public function get_info($opt=0)
    {
        return curl_getinfo($this->curl, $opt);
    }

    private function snooze()
    {
        if (isset($this->sec_min) && isset($this->sec_max)) {
            sleep(rand($this->sec_min, $this->sec_max));
        } else {
            sleep(rand(12, 20));
        }

    }

    public function set_sleep_range($sec_min, $sec_max)
    {
        $this->sec_min = $sec_min;
        $this->sec_max = $sec_max;
    }

    /*
    * Sets the proxy to use in requests
    *
    * @param string $proxy the proxy to be used
    *
    * @return voids
    */
    public function set_proxy($proxy)
    {
        $this->assing_opt(CURLOPT_PROXY, trim($proxy));
    }

//	public function reset_user_agent()
//	{
//
//		if (! sizeof($this->user_agents) > 0) {
//			$this->user_agents = explode("\n", file_get_contents("./files/user_agent_list.csv"));
//		}
//
//		if ($this->assing_opt(CURLOPT_USERAGENT,
//			trim($this->user_agents[rand(0, sizeof($this->user_agents)-1)]))
//		){
//			return true;
//		}// else {
//			return false;
//		//}
//	}

    public function set_user_agent($user_agent)
    {
        $this->user_agent = $user_agent;
        $this->assing_opt(CURLOPT_USERAGENT, $user_agent);
    }

    public function get_user_agent()
    {
        return $this->user_agent;
    }
    /**
     * Return a random user agent as String
     *
     */
//	public function get_random_useragent()
//	{
//		if (! sizeof($this->user_agents) > 0) {
//			return trim($this->user_agents[rand(0, sizeof($this->user_agents)-1)]);
//		}
//
//		return false;
//	}

    public function close()
    {
        curl_close($this->curl);
        return true;
    }

    /**
     * Alias for request_get()
     */
    /*public function get($url, $referer=null)
    {
        return $this->request_get($url, $referer);
    }*/

    /**
     * get data from a given URL (http get)
     */
    public function get($url, $referer=null)
    {
        if (isset($referer)) {
            $this->assing_opt(CURLOPT_REFERER, $referer);
        } /*else {
			$this->assing_opt(CURLOPT_REFERER, "");
		}*/

        $this->assing_opt(CURLOPT_HTTPGET, true);
        return $this->get_result($url);
    }

    /**
     * Alias for request_post()
     */
    //public function post($url, $referer=null, $data, $multipart=false)
    //{
    //	return $this->request_post($url, $referer, $data, $multipart);
    //}

    /**
     * post data to a given URL (http post)
     */
    public function post($url, $referer=null, $data, $multipart=false)
    {
        if (isset($referer)) {
            $this->assing_opt(CURLOPT_REFERER, $referer);
        }/* else {
			$this->assing_opt(CURLOPT_REFERER, "");
		}*/

        if (! $multipart) {
            $data = $this->get_post_string($data);
        }
        //print $url;
        //print $data."\n";

        $this->assing_opt(CURLOPT_POST, true);
        $this->assing_opt(CURLOPT_POSTFIELDS, $data);
        return $this->get_result($url);
    }

    public function postForm (Form $form, $method='post') {

        $callback = array($this, $method);
        $args = array($form->get_action());

        switch ($this->_method) {
            case 'get':
                $args[] = $this->to_string();
                break;

            case 'post':
            case 'ajax':
                $args[] = (self::CONTENT_TYPE_MULTIPART == $this->_content_type)
                    ? $this->to_array()
                    : $this->to_string();
                $args[] = null;
                break;

            default:
                $callback = $args = null;
        }

        return call_user_func_array($callback, $args);
        
    }

    public function ajax($url, $referer=null, $post=null,
                         $accept='application/json, text/javascript, */*')
    {
        if ($accept) {
            //$accept_old = $this->get_header('Accept');
            $accept_old = $this->default_header['Accept'];
            $this->default_header['Accept'] = $accept;
            // $this->set_headers('Accept', $accept);

        }

        $this->default_header['X-Requested-With'] = 'XMLHttpRequest';
        $this->assing_opt(array(CURLOPT_HTTPHEADER => $this->default_header));


        $response = isset($post) ? $this->post($url, $referer, $post)
            : $this->get($url, $referer);

        unset($this->default_header['X-Requested-With']);
        //$this->remove_headers('X-Requested-With');

        if ($accept) {
            $this->default_header['Accept'] = $accept_old;
            //$this->set_headers('Accept', $accept_old);
        }

        return $response;
    }

    public function last_url()
    {
        return $this->get_info(CURLINFO_EFFECTIVE_URL);
    }

    public function dump($file=null)
    {
        $path = isset($file) ? $this->log_path.$file
            : $this->log_path.time().'.html';

        file_put_contents($path, $this->result);
    }

}
?>
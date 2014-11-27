<?php
/**
 * @package Base
 */

/**
 * URL container
 *
 * @property string $scheme
 * @property string $user
 * @property string $pass
 * @property string $userpass
 * @property string $host
 * @property int    $port
 * @property string $hostport
 * @property string $path
 * @property array  $query
 * @property string $fragment
 * @property-read bool $is_valid
 *
 * @package Base
 */
class Url extends Container
{
    const DEFAULT_SCHEME = 'http';
    const DEFAULT_HOST   = 'localhost';


    /**
     * Parses query string.
     * parse_str() sucks.
     *
     * @param string $query
     * @return array|false
     */
    static public function parse_query($query)
    {
        $a = array();
        foreach (explode('&', trim($query, '?')) as $s) {
            $s = array_map('urldecode', array_pad(explode('=', $s, 2), 2, ''));
            $a[$s[0]] = $s[1];
        }
        return count($a)
            ? $a
            : false;
    }

    /**
     * Parses an URL strings to URL components
     *
     * @param string $url
     * @return array
     */
    static public function parse($url)
    {
        $components = @parse_url((false === strpos($url, '://'))
            ? self::DEFAULT_SCHEME . '://' . self::DEFAULT_HOST . '/' . ltrim($url, '/')
            : $url);

        if (empty($components['path'])) {
            $components['path'] = '/';
        }

        $k = 'query';
        $components[$k] = isset($components[$k])
            ? self::parse_query($components[$k])
            : array();

        foreach (array('user', 'pass', 'fragment') as $k) {
            $components[$k] = isset($components[$k])
                ? urldecode($components[$k])
                : '';
        }

        return $components;
    }

    /**
     * Constructs an URL string from unescaped components
     *
     * @param array $components
     * @return string
     */
    static public function compose(array $components)
    {
        $url = (!empty($components['scheme'])
            ? $components['scheme']
            : self::DEFAULT_SCHEME) . '://';

        if (!empty($components['user'])) {
            $url .=
                urlencode($components['user']) .
                (!empty($components['pass'])
                    ? ':' . urlencode($components['pass'])
                    : '') . '@';
        }

        $url .=
            $components['host'] .
            (!empty($components['port'])
                ? ":{$components['port']}"
                : '');

        $url .= !empty($components['path'])
            ? $components['path']
            : '/';

        if (!empty($components['query'])) {
            $m = '__toString';
            foreach ($components['query'] as &$v) {
                if (is_object($v) && method_exists($v, $m)) {
                    $v = $v->{$m}();
                }
            }
            $url .= '?' . http_build_query($components['query']);
        }

        if (!empty($components['fragment'])) {
            $url .= '#' . urlencode($components['fragment']);
        }

        return $url;
    }


    /**
     * Instantiates an URL object parsing a string
     *
     * @param string $url
     */
    public function __construct($url=null)
    {
        if (!$this->scheme) {
            $this->scheme = self::DEFAULT_SCHEME;
        }
        if ($url) {
            $this->set($url);
        }
    }

    /**
     * Parses an URL string
     *
     * @param string $url {@link http://tools.ietf.org/html/rfc2396} subset
     * @return Url
     */
    public function set($url)
    {
        $this->_data = $this->parse(is_object($url)
            ? $url->__toString()
            : $url);
        if (!is_array($this->_data)) {
            $this->_data = array();
        }
        return $this;
    }

    /**
     * Returns URL string
     *
     * @return string|false
     */
    public function get()
    {
        return $this->is_valid
            ? $this->compose($this->_data)
            : false;
    }

    /**
     * @ignore
     */
    public function get_is_valid()
    {
        return (bool)$this->host && (self::DEFAULT_HOST != $this->host);
    }

    /**
     * @ignore
     */
    public function set_userpass($userpass)
    {
        $a = array_map('urldecode', explode(':', $userpass, 2));
        list($this->_data['user'], $this->_data['pass']) =
            (1 == count($a))
                ? array($a[0], '')
                : $a;
        return $this;
    }

    /**
     * @ignore
     */
    public function get_userpass()
    {
        return
            urlencode($this->user) .
            ($this->pass
                ? ':' . urlencode($this->pass)
                : '');
    }

    /**
     * @ignore
     */
    public function set_host($host)
    {
        $a = explode(':', $host, 2);
        if (1 == count($a)) {
            $this->_data['host'] = $a[0];
        } else {
            list($this->_data['host'], $this->_data['port']) = $a;
        }
        return $this;
    }

    /**
     * @ignore
     */
    public function set_port($port)
    {
        $this->_data['port'] = max(0, (int)$port);
        return $this;
    }

    /**
     * @ignore
     */
    public function set_hostport($hostport)
    {
        return $this->set_host($hostport);
    }

    /**
     * @ignore
     */
    public function get_hostport()
    {
        return
            $this->host .
            ($this->port
                ? ":{$this->port}"
                : '');
    }

    public function get_domain()
    {
        if ($this->is_valid) {
            $a = explode('.', $this->host);
            return implode('.', array_slice($a, count($a) - 2));
        }
    }

    /**
     * @ignore
     */
    public function set_path($path)
    {
        if (!$path || ('/' != $path[0])) {
            $path = "/{$path}";
        }
        $this->_data['path'] = $path;
        return $this;
    }

    /**
     * @ignore
     */
    public function set_query($query)
    {
        $this->_data['query'] = $query
            ? (is_array($query)
                ? $query
                : self::parse_query($query))
            : array();
        return $this;
    }

    public function add_query($key, $value=null)
    {
        $this->_data['query'] = array_merge($this->_data['query'], (!is_array($key)
            ? array($key => $value)
            : $key));
        return $this;
    }

    /**
     * Alias for {@link ::get()}
     *
     * @return string|false
     */
    public function __toString()
    {
        return $this->get();
    }
}

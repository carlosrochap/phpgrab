<?php
namespace PhpGrab\Html;

use DOMDocument;

/**
 * HTML forms extractor/submitter
 *
 * @property string $content_type Form's content type (enctype attribute)
 * @property string $enctype      Alias for {@link ::$content_type}
 * @property string $method       Form's request method
 * @property string $action       Form's action URL
 * @property string $ref_url      Reference URL to construct form's action URL
 *
 * @package Html
 * @subpackage Form
 */
class Form
{
    /**
     * Form's submit content types
     */
    const CONTENT_TYPE_URLENCODED = 'application/x-www-form-urlencoded';
    const CONTENT_TYPE_MULTIPART  = 'multipart/form-data';

    /**
     * Form's submission methods
     */
    const METHOD_GET  = 'get';
    const METHOD_POST = 'post';


    /**
     * Reference url to construct an absolute action url
     *
     * @var string
     */
    protected $_ref_url = '';

    /**
     * Form's action URL
     *
     * @var string
     */
    protected $_action = '';

    /**
     * Form's submit method
     *
     * @var string
     */
    protected $_method = '';

    /**
     * Form's submit content type
     *
     * @var string
     */
    protected $_content_type = '';

    /**
     * List of file upload fields
     *
     * @var array
     */
    protected $_file_fields = array();


    /**
     * Extracts a specific HTML form from arbitrary string
     *
     * @param string        $src        Form source
     * @param object|string $ref_url    Reference url to construct absolute
     *                                  action url, or connection object to
     *                                  to use the last requested url
     * @param string|array  $attr       Optional attribute name or hash table
     *                                  of attributes to select a form by, or
     *                                  zero-based index of a form
     * @param string        $attr_value Attribute value if name specified
     * @return array|Html_Form|false Form object or list of ojects on success
     */
    static public function get($src, $ref_url, $attr=null, $attr_value=null)
    {
        if (null !== $attr) {
            $attr = (is_int($attr) || is_numeric($attr))
                ? max(0, (int)$attr)
                : array_filter(array_map('strtolower', (is_array($attr)
                    ? $attr
                    : array($attr => $attr_value))));
        }

        $result = false;

        $dom = new DOMDocument();
        if (@$dom->loadHTML($src)) {
            $forms = $dom->getElementsByTagName('form');
            if (is_array($attr) || (null === $attr)) {
                $result = array();
                for ($i = 0; $form = $forms->item($i); $i++) {
                    if ($attr) {
                        $is_found = true;
                        foreach ($attr as $name => &$value) {
                            if ($value != strtolower($form->getAttribute($name))) {
                                $is_found = false;
                                break;
                            }
                        }
                        if ($is_found) {
                            $result = new self($form, $ref_url);
                            break;
                        }
                    } else {
                        $result[] = new self($form, $ref_url);
                    }
                }
                if (!count($result)) {
                    $result = false;
                }
            } else if ($form = $forms->item($attr)) {
                $result = new self($form, $ref_url);
                unset($form);
            }
            unset($forms);
        }
        unset($dom);

        return $result;
    }

    /**
     * Returns absolute url constructed from a relative one and an absolute
     * reference one
     *
     * @param string $url     Relative url
     * @param string $ref_url Reference URL (absolute)
     * @return string
     * @throws InvalidArgumentException When reference URL is not absolute
     */
    static public function get_absolute_url($url, $ref_url)
    {
        if (!$url) {
            return $ref_url;
        }

        if (false !== strpos($url, '://')) {
            return $url;
        }

        if (!$ref_url instanceof Url) {
            $ref_url = new Url($ref_url);
        }
        if (!$ref_url->is_valid) {
            throw new InvalidArgumentException(
                "Invalid reference URL {$ref_url}"
            );
        }

        $tmp_url = clone $ref_url;
        unset($tmp_url['query'], $tmp_url['fragment']);

        if ('/' == $url[0]) {
            $tmp_url->path = $url;
        } else {
            $a = explode('?', $url, 2);
            $url = $a[0];
            $query = @$a[1];

            $a = explode('/', trim($tmp_url->path));
            $b = explode('/', trim($url));
            if ($b[0]) {
                $a[count($a) - 1] = $url;
            }

            $tmp_url->path = implode('/', $a);
            $tmp_url->query = $query;
        }
        return $tmp_url->get();
    }


    /**
     * Parses a form DOM node creating a hash table of form elements
     *
     * @param DOMElement|string $src     Either form DOM node or HTML string
     *                                   to extract the first form
     * @param object|string     $ref_url Reference url to construct an
     *                                   absolute action url, or connection
     *                                   object to use last requested url
     * @throws InvalidArgumentException When provided with invalid
     *                                  (unparseable) HTML string
     */
    public function __construct($src=null, $ref_url=null)
    {
        $this->set_method();
        $this->set_content_type();

        if ($src) {
            $dom = null;
            if (!is_object($src)) {
                $dom = new DOMDocument();
                $src = @$dom->loadHTML($src)
                    ? $dom->getElementsByTagName('form')->item(0)
                    : false;
            }
            if (!$src || !$src instanceof DOMElement) {
                throw new InvalidArgumentException(
                    'Invalid (unparseable) HTML source'
                );
            }
            $this->load($src, $ref_url);
            unset($dom);
        } else if ($ref_url) {
            $this->ref_url = $ref_url;
        }
    }

    /**
     * Extracts HTML elements with specific tag name and 'name' attribute set
     *
     * @param DOMElement $src
     * @param string     $tag_name
     * @return array
     */
    protected function _extract_usable_elements(DOMElement $src, $tag_name)
    {
        $elems = array();

        foreach ($src->getElementsByTagName($tag_name) as $elem) {
            if (
                $elem->getAttribute('name') &&
                !$elem->hasAttribute('disabled')
            ) {
                $elems[] = $elem;
            }
        }

        return $elems;
    }

    /**
     * Extracts usable <INPUT> elements from form node
     *
     * @param DOMElement $src
     * @return array Hash table of found named <INPUT> elements' names mapped
     *               to their values
     */
    protected function _extract_inputs(DOMElement $src)
    {
        $checkable_types = array('radio', 'checkbox');
        $skippable_types = array('button', 'reset');

        $inputs = array();

        foreach ($this->_extract_usable_elements($src, 'input') as $input) {
            $type = $input->hasAttribute('type')
                ? strtolower($input->getAttribute('type'))
                : 'text';
            $name = $input->getAttribute('name');

            if (in_array($type, $checkable_types)) {
                if ($input->hasAttribute('checked')) {
                    $inputs[$name] = $input->hasAttribute('value')
                        ? $input->getAttribute('value')
                        : 'on';
                }
            } else if (!in_array($type, $skippable_types)) {
                $inputs[$name] = $input->getAttribute('value');
                if ('file' == $type) {
                    $this->_file_fields[] = $name;
                }
            }
        }

        return $inputs;
    }

    /**
     * Extracts usable <SELECT> elements from form node
     *
     * @param DOMElement $src
     * @return array Hash table of found named <SELECT> elements' names mapped
     *               to their selected or default options' values
     */
    protected function _extract_selects(DOMElement $src)
    {
        $selects = array();

        foreach ($this->_extract_usable_elements($src, 'select') as $select) {
            $name = $select->getAttribute('name');

            $is_multiple = $select->hasAttribute('multiple');
            if ($is_multiple) {
                $selects[$name] = array();
            }

            foreach ($select->getElementsByTagName('option') as $i => $option) {
                $value = $option->hasAttribute('value')
                    ? $option->getAttribute('value')
                    : $option->textContent;

                $is_selected = $option->hasAttribute('selected');
                if (!$i || $is_selected) {
                    if ($is_multiple) {
                        $selects[$name][] = $value;
                    } else {
                        $selects[$name] = $value;
                        if ($is_selected) {
                            break;
                        }
                    }
                }
            }
        }

        return $selects;
    }

    /**
     * Extracts usable <BUTTON> elements from form node
     *
     * @param DOMElement $src
     * @return array Hash table of found named <BUTTON> elements' names mapped
     *               to their values
     */
    protected function _extract_buttons(DOMElement $src)
    {
        $buttons = array();

        foreach ($this->_extract_usable_elements($src, 'button') as $button) {
            if ($button->hasAttribute('value') &&
                (!$button->hasAttribute('type') ||
                 ('submit' == $button->getAttribute('type')))) {

                $buttons[$button->getAttribute('name')] =
                    $button->getAttribute('value');
            }
        }

        return $buttons;
    }

    /**
     * Extracts usable <TEXTAREA> elements from form node
     *
     * @param DOMElement $src
     * @return array Hash table of found named <TEXTAREA> elements' names
     *               mapped to their values (content)
     */
    protected function _extract_textareas(DOMElement $src)
    {
        $textareas = array();

        foreach ($this->_extract_usable_elements($src, 'textarea') as $textarea) {
            $textareas[$textarea->getAttribute('name')] =
                $textarea->textContent;
        }

        return $textareas;
    }

    /**
     * Loads form attributes and elements from DOMElement source
     *
     * @param DOMElement    $src
     * @param object|string $ref_url Reference url to construct an absolute
     *                               action url, or connection object to use
     *                               last requested url
     */
    public function load(DOMElement $src, $ref_url=null)
    {
        if ($ref_url) {
            $this->ref_url = $ref_url;
        }

        $this->action = str_replace(
            ' ',
            '',
            strtr($src->getAttribute('action'), " \r\n\t", '    ')
        );
        $this->method = $src->getAttribute('method');
        $this->content_type = $src->getAttribute('enctype');

        $this->_file_fields = array();
        $this->_data = array_merge(
            $this->_extract_inputs($src),
            $this->_extract_selects($src),
            $this->_extract_buttons($src),
            $this->_extract_textareas($src)
        );

        return $this;
    }

    /**
     * Sets reference URL to construct action URL
     *
     * @param object|string $ref_url Either string or object with last_url
     *                               public property
     */
    public function set_ref_url($ref_url)
    {
        $this->_ref_url = is_object($ref_url)
            ? $ref_url->last_url
            : $ref_url;
        return $this;
    }

    /**
     * Fetches reference URL
     *
     * @return string
     */
    public function get_ref_url()
    {
        return $this->_ref_url;
    }

    /**
     * Sets the form's action url, constructing an absolute one using
     * the reference one provided when creating the form instance
     *
     * @param string $action
     */
    public function set_action($action)
    {
        $this->_action = $this->_ref_url
            ? $this->get_absolute_url($action, $this->_ref_url)
            : $action;
        return $this;
    }

    /**
     * Returns the form's action URL (absolute)
     *
     * @return string
     */
    public function get_action()
    {
        return $this->_action;
    }

    /**
     * Sets the form's submit method
     *
     * @param string $method
     */
    public function set_method($method=self::METHOD_GET)
    {
        $method = strtolower($method);
        if ($method && in_array($method, array(
            self::METHOD_GET,
            self::METHOD_POST
        ))) {
            $this->_method = $method;
        }

        return $this;
    }

    /**
     * Returns the form's submission method
     *
     * @return string
     */
    public function get_method()
    {
        return $this->_method;
    }

    /**
     * Sets the form's data content type (enctype)
     *
     * @param string $content_type
     */
    public function set_content_type($content_type=self::CONTENT_TYPE_URLENCODED)
    {
        $content_type = strtolower($content_type);
        if ($content_type && in_array($content_type, array(
            self::CONTENT_TYPE_URLENCODED,
            self::CONTENT_TYPE_MULTIPART
        ))) {
            $this->_content_type = $content_type;
            if (self::CONTENT_TYPE_MULTIPART == $content_type) {
                $this->_method = self::METHOD_POST;
            }
        }
        return $this;
    }

    /**
     * Returns the form's enctype (content-type)
     *
     * @return string
     */
    public function get_content_type()
    {
        return $this->_content_type;
    }

    /**
     * Alias for {@link ::set_content_type()}
     */
    public function set_enctype($content_type=self::CONTENT_TYPE_MULTIPART)
    {
        return $this->set_content_type($content_type);
    }

    /**
     * Alias for {@link ::get_content_type()}
     */
    public function get_enctype()
    {
        return $this->get_content_type();
    }

    /**
     * Adds a file to upload upon submitting the form
     *
     * @param string|array $field Form element (field) name or map of fields
     *                            and file names
     * @param string       $fn    Uploaded file name
     * @throws InvalidArgumentException When file not found or unreadable
     */
    public function add_file($field, $fn=null)
    {
        if (!is_array($field)) {
            $field = array($field => $fn);
        }
        foreach (array_map('realpath', $field) as $k => $v) {
            if (!is_file($v) || !is_readable($v)) {
                throw new InvalidArgumentException(
                    "File {$v} not found or not readable"
                );
            }
            $this->_data[$k] = "@{$v}";
        }
        $this->method = self::METHOD_POST;
        $this->content_type = self::CONTENT_TYPE_MULTIPART;
        return $this;
    }

    /**
     * Checks if a field is a file upload field
     *
     * @param array|string $field A field or a list of fields to check
     * @return array|bool
     */
    public function is_file($field)
    {
        return is_array($field)
            ? array_filter($field, array($this, 'is_file'))
            : (false !== array_search($field, $this->_file_fields));
    }

    /**
     * Submits the form using provided connection with optional referer
     * (if specified, otherwise last response url will be used)
     *
     * @param Connection_Interface $connection Connection to use
     * @param string               $referer    Optional referer
     * @return mixed Request's response body on success
     */
    public function submit(Connection_Interface $connection, $referer=null, array $hdr=array())
    {
        if (null === $referer) {
            $referer = $this->_ref_url;
        }

        $callback = array($connection, $this->_method);
        $args = array($this->_action);

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

        if ($callback && $args) {
            $args[] = $referer;
            $args[] = $hdr;
            return call_user_func_array($callback, $args);
        }
    }

    /**
     * @see Container::offsetUnset()
     */
    public function offsetUnset($offset)
    {
        if (false !== ($i = array_search($offset, $this->_file_fields))) {
            unset($this->_file_fields[$i]);
        }
        return parent::offsetUnset($offset);
    }

    /**
     * Adds to parent {@link Base::offsetSet()} method special handling
     * for file upload fields
     *
     * @param string $offset
     * @param mixed  $value
     */
    public function offsetSet($offset, $value)
    {
        if ($this->is_file($offset)) {
            $this->add_file($offset, $value);
        } else {
            parent::offsetSet($offset, $value);
        }
    }

    /**
     * @ignore
     */
    public function __sleep()
    {
        return array_merge(parent::__sleep(), array(
            '_ref_url',
            '_action',
            '_method',
            '_content_type',
            '_file_fields',
        ));
    }

    /**
     * Returns array representation of the form
     *
     * @return array
     */
    public function to_array()
    {
        return $this->_data;
    }

    /**
     * Returns string representation of the form
     *
     * @return string
     */
    public function to_string()
    {
        return http_build_query($this->_data);
    }

    public function clear()
    {
        $this->_data = array();
        return $this;
    }

    /**
     * Alias for {@link ::to_string()}
     */
    public function __toString()
    {
        return $this->to_string();
    }

    /**
     * Populates form fields from arbitrary array, treating fields that start
     * with '@' as file fields when the form's content type is multipart/form-data.
     *
     * @param array $fields
     */
    public function from_array(array $fields)
    {
        foreach ($fields as $k => &$v) {
            if (is_array($v)) {
                unset($fields[$k]);
            } else if (is_object($v)) {
                $m = '__toString';
                $v = method_exists($v, $m)
                    ? $v->{$m}()
                    : (string)$v;
            }
        }
        if (self::CONTENT_TYPE_MULTIPART == $this->_content_type) {
            foreach ($fields as $k => &$v) {
                if (is_string($v) && $v && ('@' == $v[0])) {
                    $this->add_file($v);
                    unset($fields[$k]);
                }
            }
        }
        $this->_data = array_merge($this->_data, $fields);
        return $this;
    }

    /**
     * Populates form fields from HTTP query string, treating fields that start
     * with '@' as file fields when the form's content type is multipart/form-data.
     *
     * @param string $fields
     */
    public function from_string($fields)
    {
        $fields_array = Url::parse_query($fields);
        return $fields_array
            ? $this->from_array($fields_array)
            : $this;
    }
}

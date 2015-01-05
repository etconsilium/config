<?php

namespace Noodlehaus;

use Noodlehaus\Exception\ParseException;
use Noodlehaus\Exception\FileNotFoundException;
use Noodlehaus\Exception\UnsupportedFormatException;
use \Symfony\Component\Yaml\Yaml;
use \CFPropertyList as Plist;

/**
 * Config
 *
 * @package    Config
 * @author     Jesus A. Domingo <jesus.domingo@gmail.com>
 * @author     Hassan Khan <contact@hassankhan.me>
 * @author     Vlad A. Koltsov <v.koltsov@gmail.com>
 * @link       https://github.com/noodlehaus/config
 * @license    MIT
 */
class Config
{
    /**
     * Stores the configuration data
     *
     * @var array|null
     */
    protected $data = null;

    /**
     * Caches the configuration data
     *
     * @var array
     */
    protected $cache = array();

    /**
     * Realpath configuration file
     *
     * @var string
     */
    protected $filename = null;

    /**
     * Types: PHP, Ini, XML, JSON, YAML, Plist files and Array()
     */
    const ARR = 'Array';
    const PHP = 'Php';
    const INI = 'Ini';
    const XML = 'Xml';
    const YAML = 'Yaml';
    const PLST = 'Plist';
    const CVS = 'Cvs';

    protected $ext_list = [
        self::ARR => []
        ,self::CVS => ['cvs','txt','']
        ,self::INI => ['ini','init']
        ,self::PHP => ['php','inc']
        ,self::PLST => []   //  ?
        ,self::XML => ['xml']
        ,self::YAML => ['yml','yaml']
    ];


    /**
    * Static method for loading a config instance.
    *
    * @param  string|array $param
    * @param  string $type
    *
    * @return Config
    */
    public static function load($param, $type=null)
    {
        return new static($path);
    }

    /**
     * Save the current configuration data into file
     *
     * @param type $filename
     * @param  string $type
     *
     * @return void
     */
    public function save($filename, $type=null)
    {
        return ;
    }

    /**
    * Loads a supported configuration file format.
    *
    * @param  string|array $param filename or array data or string data
    * @param  string $type
    *
    * @return void
    *
    * @throws FileNotFoundException      If a file is not found at `$path`
    * @throws UnsupportedFormatException If `$path` is an unsupported file format
    */
    public function __construct($param, $type=null)
    {
        // Get file information
        $info = pathinfo($path);

        // Check if config file exists or throw an exception
        if (!file_exists($path)) {
            throw new FileNotFoundException("Configuration file: [$path] cannot be found");
        }

        // Check if a load-* method exists for the file extension, if not throw exception
        $load_method = 'load' . ucfirst($info['extension']);
        if (!method_exists(__CLASS__, $load_method)) {
            throw new UnsupportedFormatException('Unsupported configuration format');
        }

        // Try and load file
        $this->data = $this->$load_method($path);

    }

    /**
     * Loads a PHP file and gets its' contents as an array
     *
     * @param  string $path
     *
     * @return array
     *
     * @throws ParseException             If the PHP file throws an exception
     * @throws UnsupportedFormatException If the PHP file does not return an array
     */
    protected function loadPhp($path)
    {
        // Require the file, if it throws an exception, rethrow it
        try {
            $temp = require $path;
        }
        catch (\Exception $ex) {
            throw new ParseException(
                array(
                    'message'   => 'PHP file threw an exception',
                    'exception' => $ex
                )
            );
        }

        // If we have a callable, run it and expect an array back
        if (is_callable($temp)) {
            $temp = call_user_func($temp);
        }

        // Check for array, if its anything else, throw an exception
        if (!$temp || !is_array($temp)) {
            throw new UnsupportedFormatException('PHP file does not return an array');
        }

        return $temp;
    }

    /**
     * Loads an INI file as an array
     *
     * @param  string $path
     *
     * @return array
     *
     * @throws ParseException If there is an error parsing the INI file
     */
    protected function loadIni($path)
    {
        $data = @parse_ini_file($path, true);

        if (!$data) {
            $error = error_get_last();
            throw new ParseException($error);
        }

        return $data;
    }

    /**
     * Loads a JSON file as an array
     *
     * @param  string $path
     *
     * @return array
     *
     * @throws ParseException If there is an error parsing the JSON file
     */
    protected function loadJson($path)
    {
        $data = json_decode(file_get_contents($path), true);

        if (function_exists('json_last_error_msg')) {
            $error_message = json_last_error_msg();
        } else {
            $error_message  = 'Syntax error';
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            $error = array(
                'message' => $error_message,
                'type'    => json_last_error(),
                'file'    => $path
            );
            throw new ParseException($error);
        }

        return $data;
    }


    /**
     * Loads a XML file as an array
     *
     * @param  string $path
     *
     * @return array
     *
     * @throws ParseException If there is an error parsing the XML file
     */
    protected function loadXml($path)
    {
        libxml_use_internal_errors(true);

        $data = simplexml_load_file($path, null, LIBXML_NOERROR);

        if ($data === false) {
            $errors = libxml_get_errors();
            $latestError = array_pop($errors);
            $error = array(
                'message' => $latestError->message,
                'type'    => $latestError->level,
                'code'    => $latestError->code,
                'file'    => $latestError->file,
                'line'    => $latestError->line
            );
            throw new ParseException($error);
        }

        $data = json_decode(json_encode($data), true);

        return $data;
    }

    /**
     * Loads a YAML file as an array
     *
     * @param  string $path
     *
     * @return array
     *
     * @throws ParseException If If there is an error parsing the YAML file
     */
    protected function loadYaml($path)
    {
        try {
            $data = Yaml::parse($path);
        }
        catch(\Exception $ex) {
            throw new ParseException(
                array(
                    'message'   => 'Error parsing YAML file',
                    'exception' => $ex
                )
            );
        }

        return $data;
    }

    /**
    * Gets a configuration setting using a simple or nested key.
    * Nested keys are similar to JSON paths that use the dot
    * dot notation.
    *
    * @param  string $key
    * @param  mixed  $default
    *
    * @return mixed
    */
    public function get($key, $default = null) {

        // Check if already cached
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $segs = explode('.', $key);
        $root = $this->data;

        // nested case
        foreach ($segs as $part) {
            if (isset($root[$part])){
                $root = $root[$part];
                continue;
            }
            else {
                $root = $default;
                break;
            }
        }

        // whatever we have is what we needed
        return ($this->cache[$key] = $root);
    }

    /**
    * Function for setting configuration values, using
    * either simple or nested keys.
    *
    * @param  string $key
    * @param  mixed  $value
    *
    * @return void
    */
    public function set($key, $value) {

        $segs = explode('.', $key);
        $root = &$this->data;

        // Look for the key, creating nested keys if needed
        while ($part = array_shift($segs)) {
            if (!isset($root[$part]) && count($segs)) {
                $root[$part] = array();
            }
            $root = &$root[$part];
        }

        // Assign value at target node
        $this->cache[$key] = $root = $value;
    }

    /**
     * ArrayAccess Methods
     */

    /**
     * Gets a value using the offset as a key
     *
     * @param  string $offset
     *
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * Checks if a key exists
     *
     * @param  string $offset
     *
     * @return bool
     */
    public function offsetExists($offset)
    {
        return !is_null($this->get($offset));
    }

    /**
     * Sets a value using the offset as a key
     *
     * @param  string $offset
     * @param  mixed  $value
     *
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        $this->set($offset, $value);
    }

    /**
     * Deletes a key and its value
     *
     * @param  string $offset
     *
     * @return void
     */
    public function offsetUnset($offset)
    {
        $this->set($offset, NULL);
    }


}

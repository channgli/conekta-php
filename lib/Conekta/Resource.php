<?php

namespace Conekta;

use \Conekta\Object;
use \Conekta\Requestor;
use \Conekta\Error;
use \Conekta\Lang;
use \Conekta\Util;
use \Conekta\Conekta;

abstract class Resource extends Object
{
    public static function className($class)
    {
        // Useful for namespaces: Foo\Charge
        if ($postfix = strrchr($class, '\\')) {
            $class = substr($postfix, 1);
        }
        if (substr($class, 0, strlen('Conekta')) == 'Conekta') {
            $class = substr($class, strlen('Conekta'));
        }
        $class = str_replace('_', '', $class);
        $name = urlencode($class);
        $name = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $name));

        return $name;
    }

    protected static function _getBase($class, $method)
    {
        $args = array_slice(func_get_args(), 2);

        return call_user_func_array(array($class, $method), $args);
    }

    public static function classUrl($class = null)
    {
        if (empty($class)) {
            throw new NoConnectionError(
                Lang::translate('error.resource.id', Lang::EN, array('RESOURCE' => "NULL")),
                Lang::translate('error.resource.id_purchaser', Conekta::$locale)
            );
        }
        $base = self::_getBase($class, 'className', $class);
        return "/{$base}s";
    }

    protected static function _scpWhere($class, $params)
    {
        if (Conekta::$apiVersion == "1.1.0") {
            $base = self::_getBase($class, 'className', $class);
            $instance = new ConektaList($base);
        } else {
            $instance = new Object();
        }
        $requestor = new Requestor();
        $url = self::classUrl($class);
        $response = $requestor->request('get', $url, $params);
        $instance->loadFromArray($response);

        return $instance;
    }

    protected static function _scpFind($class, $id)
    {
        $instance = new $class($id);
        $requestor = new Requestor();
        $url = $instance->instanceUrl();
        $response = $requestor->request('get', $url);
        $instance->loadFromArray($response);

        return $instance;
    }

    protected static function _scpCreate($class, $params)
    {
        $requestor = new Requestor();
        $url = self::classUrl($class);
        $response = $requestor->request('post', $url, $params);
        $instance = new $class();
        $instance->loadFromArray($response);

        return $instance;
    }

    public function instanceUrl()
    {
        $id = $this->id;
        if (!$id) {
            throw new Error(
                Lang::translate('error.resource.id', Lang::EN, array('RESOURCE' => get_class())),
                Lang::translate('error.resource.id_purchaser', Conekta::$locale)
            );
        }
        $class = get_class($this);
        $base = $this->classUrl($class);
        $extn = urlencode($id);

        return "{$base}/{$extn}";
    }

    protected function _delete($parent = null, $member = null)
    {
        self::_customAction('delete', null, null);
        if (isset($parent) && isset($member)) {
            $obj = $this->$parent->$member;
            if (strpos(get_class($obj), 'Object') !== false) {
                foreach ($this->$parent->$member as $k => $v) {
                    if (strpos($v->id, $this->id) !== false) {
                        $this->$parent->$member->_values = Util::shiftArray($this->$parent->$member->_values, $k);
                        $this->$parent->$member->loadFromArray($this->$parent->$member->_values);
                        $this->$parent->$member->offsetUnset(count($this->$parent->$member) - 1);
                        break;
                    }
                }
            } else {
                unset($this->$parent->$member);
            }
        }

        return $this;
    }

    protected function _update($params)
    {
        $requestor = new Requestor();
        $url = $this->instanceUrl();
        $response = $requestor->request('put', $url, $params);
        $this->loadFromArray($response);

        return $this;
    }

    protected function _createMember($member, $params)
    {
        $requestor = new Requestor();
        $url = $this->instanceUrl().'/'.$member;
        $response = $requestor->request('post', $url, $params);

        if (strpos(get_class($this->$member), 'Object') !== false ||
            strpos($member, 'cards') !== false ||
            strpos($member, 'payout_methods') !== false) {
            if (empty($this->$member)) {
                $this->$member = new Object();
            }

            $this->$member->loadFromArray(array_merge(
                $this->$member->_toArray(),
                array($response)
            ));

            $this->loadFromArray();
            $instances = $this->$member;
            $instance = end($instances);
        } else {
            $class = '\\Conekta\\' . ucfirst($member);
            $instance = new $class();
            $instance->loadFromArray($response);
            $this->$member = $instance;
            $this->_setVal($member, $instance);
            $this->loadFromArray();
        }

        return $instance;
    }

    protected function _customAction($method = 'post', $action = null, $params = null)
    {
        $requestor = new Requestor();
        if (isset($action)) {
            $url = $this->instanceUrl().'/'.$action;
        } else {
            $url = $this->instanceUrl();
        }

        $response = $requestor->request($method, $url, $params);
        $this->loadFromArray($response);

        return $this;
    }
}

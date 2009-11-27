<?php

class Model_Result
{
    private $_stmt = null;
    private $_model = null;
    private $_data = array();
    private $_pages = null;

    public function __construct($stmt, $model)
    {
        $stmt->setFetchMode(PDO::FETCH_CLASS, 'Model_Result', array($stmt, $model));
        $this->_stmt = $stmt;
        $this->_model = $model;
        foreach ($model->hasMany as $item) {
            $i = ucfirst($item);
            $o = new $i;
            $where = array(
                $model->_key => $this->_data[$model->_key]
            );
            $this->_data[$item] = $o->select($where);
        }
    }

    public function __set($key, $value)
    {
        $this->_data[$key] = $value;
    }

    public function __get($key)
    {
        return isset($this->_data[$key]) ? $this->_data[$key] : '';
    }

    public function __call($method, $args) 
    {
        $return = call_user_func_array(array($this->_stmt, $method), $args);
        if ($return instanceof Model_Result) {
            $this->_data = $return->data();
        }
        return $return;
    }

    public function data()
    {
        return $this->_data;
    }

    public function rows()
    {
        $sql = $this->_stmt->queryString;
        $db = new Model;
        $r = $db->query($sql);
        $c = count($r->fetchAll());
        return $c;
    }

    public function __toString()
    {
        $string = $this->_model->str;
        preg_match_all('@:\w+:@', $string, $matches);
        foreach ($matches[0] as $item) {
            $valor  = $this->_data[substr($item, 1, -1)];
            $string = str_replace($item, $valor, $string);
        }
        return $string;
    }

    public function setStr($string)
    {
        $this->_model->str = $string;
    }

    public function pages($pages=null)
    {
        if ($pages) {
            $this->_pages = $pages;
        }
        return $this->_pages;
    }

    public function tableRow($before=null, $after = null)
    {
        $data = $this->data();
        $data = array_diff_key($data, array_fill_keys($this->_model->hasMany, true));
        foreach(array('before', 'after') as $item) {
            foreach ($this->data() as $key=>$value) {
                $$item = str_replace(":{$key}:", $value, $$item);
            }
        }
        return "<tr>$before<td>".implode('</td><td>', $data)."</td>$after</tr>";
    }

    public function tableRows() 
    {
        $table = array();
        do {
            $table[] = $this->tableRow();
        } while ($this->fetch());
        return implode("\n", $table);
    }
}

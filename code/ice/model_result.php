<?php

class Model_Result
{
    private $_stmt    = null;
    private $_model   = null;
    private $_data    = array();
    private $_pages   = null;
    private $_altated = false;
    public $_extra   = null;

    /**
     * __construct - Constroi um model_result
     * 
     * @param mixed $stmt 
     * @param mixed $model 
     * @param mixed $getRelatedJoin 
     * @access public
     * @return void
     */
    public function __construct($stmt, $model, $getRelatedJoin=true, $extra_select=false)
    {
        $stmt->setFetchMode(PDO::FETCH_CLASS, 'Model_Result',
            array($stmt, $model));
        $this->_stmt = $stmt;
        $this->_model = $model;
        foreach ($model->_hasMany as $item) {
            $i = ucfirst($item);
            $o = new $i;
            if (!isset($this->_data[$model->_key])) {
                continue;
            }
            $where = array(
                $model->_key => $this->_data[trim($model->_key)],
            );
            $this->_data[$item] = $o->select($where);
        }
        if ($getRelatedJoin) {
            $this->mk_multiple_joins();
            $this->mk_related_join();
        }
        if ($extra_select) {
            preg_match('@:(\w+):@', $extra_select['sqlJoin'], $matches);
            $id_join = $this->_data[$matches[1]];
            $sql_join = str_replace($matches[0], $id_join, $extra_select['sqlJoin']);
            $r = $this->_model->query($sql_join);
            $this->_extra[$extra_select['table']] = $r->fetch();
        }
    }

    public function mk_multiple_joins()
    {
        $model = $this->_model;
        foreach ($model->_multipleJoin as $key=>$value) {
            $i = ucfirst(strtolower($value));
            $o = new $i;
            if (!isset($this->_data[$model->_key])) {
                continue;
            }
            $table = array($model->_table, $o->_table);
            sort($table);
            $table = implode('_', $table);
            $where = array(
                $model->_key => $this->_data[trim($model->_key)],
            );
            $select = 'SELECT '.$o->_key.' FROM '.$table.$o->_where($where);
            $ids_r  = $o->query($select, PDO::FETCH_NUM);
            $ids    = array();
            if ($ids_r) {
                foreach ($ids_r->fetchAll() as $id) {
                    $ids[] = $id[0];
                }
            }
            $ids = '(true = false) OR ( '.$o->_key.' IN ('.implode(',', $ids).') )';
            $sql = 'SELECT * FROM '.$o->_table.' WHERE '. $ids;

            $sqlJoin = PHP_EOL.
                'SELECT t.* FROM '.$table.' t '.PHP_EOL.
                ' WHERE '.PHP_EOL.
                '         t.'.$model->_key.' = '.$this->_data[$model->_key].PHP_EOL.
                '     AND t.'.$o->_key.' = :'.$o->_key.':';
            $sqlJoin = compact('table', 'sqlJoin');

            $stmt = $o->prepare($sql);
            $stmt->setFetchMode(PDO::FETCH_CLASS,
                'Model_Result', array($stmt, $o, false, $sqlJoin));
            $stmt->execute();
            $this->_data[$key] = $stmt->fetch();
        }
    }

    public function mk_related_join()
    {
        $model = $this->_model;
        foreach ($model->_relatedJoin as $key => $value) {
            $i = ucfirst(strtolower($value));
            $o = new $i;
            if (!isset($this->_data[$model->_key]) || !is_scalar($model->_key)) {
                continue;
            }
            $this->_data[$key] = $o->get($this->_data[trim($o->_key)], null, 'one');
        }
    }

    public function __set($key, $value)
    {
        if ($this->_model) {
            if (substr($key,0,1)!=='_') {
                $this->_data[$key] = $value;
                $this->_altated = true;
                return true;
            }
        }
        $this->_data[$key] = $value;
    }

    public function __get($key)
    {
        return (array_key_exists($key, $this->_data)) ? $this->_data[$key] : '';
    }

    public function __call($method, $args)
    {
        if ('add' === substr($method, 0, 3)) {
            return $this->add(substr($method, 3), $args);
        }
        if ('remove' === substr($method, 0, 6)) {
            return $this->remove(substr($method, 6), $args);
        }
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
        $string = $this->_model->_str;
        preg_match_all('@:\w+:@', $string, $matches);
        foreach ($matches[0] as $item) {
            $valor  = $this->_data[substr($item, 1, -1)];
            $string = str_replace($item, $valor, $string);
        }
        return $string;
    }

    public function setStr($string)
    {
        $this->_model->_str = $string;
    }

    public function pages($pages=null)
    {
        if ($pages) {
            $this->_pages = $pages;
        }
        return $this->_pages;
    }

    public function tableRow($before=null, $after = null, $fields = null)
    {
        $data       = $this->data();
        $data_trued = array_fill_keys($this->_model->_hasMany, true);
        $data       = array_diff_key($data, $data_trued);
        if ($fields !== null) {
            $data = array_intersect_key($data, array_fill_keys($fields, true));
        }

        foreach(array('before', 'after') as $item) {
            foreach ($this->data() as $key=>$value) {
                $$item = str_replace(":{$key}:", $value, $$item);
            }
        }

        $row = implode('</td><td>', $data);
        if ($row) {
            $row = "<td>$row</td>";
        }
        return "<tr>$before$row$after</tr>";
    }

    public function tableRows($before=null, $after = null, $fields = null)
    {
        $table = array();
        do {
            $table[] = $this->tableRow($before, $after, $fields);
        } while ($this->fetch());
        return implode("\n", $table);
    }

    public function save()
    { 
        $data = $this->_data;
        // limpando _hasMany
        $keys = array_fill_keys($this->_model->_hasMany, true);
        $data = array_diff_key($data, $keys);
        // limpando _multipleJoin
        $keys = array_fill_keys(array_keys($this->_model->_multipleJoin), true);
        $data = array_diff_key($data, $keys);
        // limpando _relatedJoin
        $keys = array_fill_keys(array_keys($this->_model->_relatedJoin), true);
        $data = array_diff_key($data, $keys);

        return $this->_model->save($data);
    }

    public function pagination($page)
    {
        if (!$this->pages() OR $this->pages() == 1) {
            return '<!-- No pages defined for pagination! -->';
        }
        $init = $last = array();
        if ($page > 1) {
            $init = array(
                    '',
                    '<li class="primeiro"><a href="?p=1">Primeiro</a></li>',
                    '<li class="anterior"><a href="?p='.($page-1).'">Anterior</a></li>',
                    ''
                    );
        }
        if ($page < $this->pages()) {
            $last = array(
                    '',
                    '<li class="proximo"><a href="?p='.($page+1).'">Próximo</a></li>',
                    '<li class="ultimo"><a href="?p='.$this->pages().'">Último</a></li>',
                    '',
                    );
        }
        $pages = array();
        foreach (range(1, $this->pages()) as $p) {
            $class = '';
            $link  = '<a href="?p='.$p.'">'.$p.'</a>';
            if ($p==$page) {
                $class = ' class="atual"';
                $link  = $p;
            }
            $pages[] = "<li$class>$link</li>";
        }
        $paginacao = array_merge($init, $pages, $last);
        $paginacao = implode("\n", $paginacao);
        return "<ul class=\"paginacao\">\n$paginacao\n</ul>";
    }

    public function add($model, $args)
    {
        if (1!==count($args)) {
            throw new Exception('Quantidade de argumentos invalidos.');
        }
        if (!in_array($model, $this->_model->_multipleJoin)) {
            throw new Exception('RelatedJoin not found in declaration.');
        }
        $o = new $model;
        $table = array($this->_model->_table, $o->_table);
        sort($table);
        $table = implode('_', $table);
        $add = $args[0];
        if (!is_scalar($add)) {
            if ('Model_Result'!==get_class($add)) {
                throw new Exception('Invalid argument to add RelatedJoin.');
            }
            $add = $add->data();
            $add = $add[$o->_key];
        }
        $sql = "INSERT INTO $table ({$this->_model->_key}, {$o->_key})
                VALUES ({$this->_data[$this->_model->_key]}, {$add})";
		
		
        $o->exec($sql);

		$error = $o->errorInfo();
		if(isset($error[1])){
            throw new Exception ("<h1>SQL Error</h1> ({$error[1]}) {$error[2]}");
        }
		 
        $this->mk_multiple_joins();
        return true;
    }

    public function remove($model, $args)
    {
        if (1!==count($args)) {
            throw new Exception('Quantidade de argumentos invalidos.');
        }
        if (!in_array($model, $this->_model->_multipleJoin)) {
            throw new Exception('RelatedJoin not found in declaration.');
        }
        $o = new $model;
        $table = array($this->_model->_table, $o->_table);
        sort($table);
        $table = implode('_', $table);
        $remove = $args[0];
        if (!is_scalar($remove)) {
            if ('Model_Result'!==get_class($remove)) {
                throw new Exception('Invalid argument to remove RelatedJoin.');
            }
            $remove = $remove->data();
            $remove = $remove[$o->_key];
        }
        $sql = 'DELETE FROM '.$table.' WHERE '.$o->_key.' = '.$remove;
        $o->exec($sql);

		$error = $o->errorInfo();
		if(isset($error[1])){
            throw new Exception ("<h1>SQL Error</h1> ({$error[1]}) {$error[2]}");
        }
        $this->mk_multiple_joins();
        return true;
    }

    public function __destruct()
    {
        if ($this->_altated) {
            $this->save();
        }
    }
}

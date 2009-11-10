<?php
include 'config.php';
include 'ice/model.php';

define('DB_DNS', 'sqlite://'
                .dirname(__FILE__)
                .DIRECTORY_SEPARATOR.'banco.db');

class User extends Model {}

class ModelTest extends UnitTestCase
{
    public function setUp()
    {
        createDatabase();
    }

    public function testConnect()
    {
        $user = new User;
        $this->assertEqual('user', $user->_table, 'A tabela foi definida. %s');
        $this->assertEqual('id_user', $user->_key, 
                'A chave primaria foi definida. %s');
    }

    public function testSimpleQuery()
    {
        $user = new User;
        $result = $user->query('SELECT count(*) as C FROM user');
        $this->assertEqual(get_class($result), 'PDOStatement', 
            'Retornou um objeto de resultado de PDO');
        $this->assertEqual(2, $result->fetchObject()->C,
            'Deveria retornar dois. %s');
    }

    public function testGet()
    {
        $user = new User;
        $result = $user->get(1);
        $this->assertEqual(get_class($result), 'Model_Result',
            'O retorno do get é um Model_Result. %s');
        $this->assertEqual('Alice', $result->nome, 
            'Deveria retornar o primeiro resultado');
    }
}
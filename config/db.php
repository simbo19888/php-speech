<?php

//return [
//    'class' => 'yii\db\Connection',
//    'dsn' => 'pgsql:host=192.168.100.102;dbname=postgres',
//    'username' => 'postgres',
//    'password' => 'qweasdzxc',
//    'charset' => 'utf8',

return [
    'class' => 'yii\db\Connection',
    'dsn' => 'pgsql:host=127.0.0.1;port=5432;dbname=postgres',
    'username' => 'postgres',
    'password' => 'qweasdzxc',
    'charset' => 'utf8',

    // Schema cache options (for production environment)
    //'enableSchemaCache' => true,
    //'schemaCacheDuration' => 60,
    //'schemaCache' => 'cache',
];

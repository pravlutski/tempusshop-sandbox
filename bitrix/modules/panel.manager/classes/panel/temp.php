<?php
class DBTemp {
    private $host = '127.0.0.1';
    private $username = 'tempusshop';
    private $password = 'Afb}A2sdfU~QPBT34fsEGr';
    private $dbname = 'tempusshop_db_temp';
    private $connection;
    public $affectedRows;

    private $selectStmt = '';
    private $whereAll = [];
    private $whereCond = '';
    private $orderCond = '';
    private $limitCond = '';
    private $groupCond = '';

    public function __construct() {
        // Устанавливаем корректный заголовок для вывода русских символов.
        header("Content-Type: text/html; charset=utf-8");

        $this->connect();
    }

    private function connect() {
        $this->connection = mysqli_connect($this->host, $this->username, $this->password, $this->dbname);

        if (!$this->connection) {
            trigger_error("Ошибка подключения: " . mysqli_connect_error(), E_USER_ERROR);
        }

        // Устанавливаем кодировку соединения (UTF-8).
        $this->connection->set_charset("utf8");
    }

    public function query($sql) {
        $result = mysqli_query($this->connection, $sql);

        if (!$result) {
            trigger_error("Ошибка выполнения запроса: " . mysqli_error($this->connection), E_USER_ERROR);
        }

        return $result;
    }

    public function select( array $select, string $table ):DBTemp
    {
      if ( empty( $select ) || empty( $table )  ){
        trigger_error( "Select statement or table name is empty", E_USER_ERROR );
      }
      $selectStr = implode(',', $select);
      $this->selectStmt = "SELECT $selectStr FROM `$table`";

      return $this;
    }

    public function where( string $column, string|int|float|array $value, string $operator = '=' ):DBTemp
    {
      return $this->whereBase($column, $value, $operator, 'AND');
    }

    public function orWhere( string $column, string|int|float|array $value, string $operator = '=' ):DBTemp
    {
      return $this->whereBase($column, $value, $operator, 'OR');
    }

    public function whereNot( string $column, string|int|float|array $value, string $operator = '=' ):DBTemp
    {
      return $this->whereBase($column, $value, $operator, 'AND', 'NOT');
    }

    public function orWhereNot( string $column, string|int|float|array $value, string $operator = '=' ):DBTemp
    {
      return $this->whereBase($column, $value, $operator, 'OR', 'NOT');
    }

    private function whereBase( string $column, string|int|float|array $value, string $operator = '=', string $merge = "AND", string $opposite = '' ):DBTemp
    {
      if ( empty( $column ) || empty( $value )  ){
        trigger_error( "One of the parameters is empty", E_USER_ERROR );
      }
      if ( empty($this->selectStmt) ){
        trigger_error( "Select statment is not set", E_USER_ERROR);
      }
      if ( !in_array($operator, ['=', '<', '>', '<=', '>=', '!=', '<>', 'LIKE']) ){
        trigger_error( "Operator is not allowed", E_USER_ERROR);
      }
      if ( !is_array($value) ){
        $whereStr = "`{$column}` {$operator} ?";
        $this->whereAll[] = $value;

        if ( empty($this->whereCond) ){
          $this->whereCond = " WHERE {$opposite} {$whereStr}";
        }else{
          $this->whereCond .= " {$merge} {$opposite} {$whereStr}";
        }
      }else{

        $this->whereAll = array_merge( $this->whereAll, $value);
        $placeholders = implode(',', array_fill(0, count($value), '?'));

        $whereStr = "{$column} {$opposite} IN ({$placeholders})";
        if ( empty($this->whereCond) ){
          $this->whereCond = " WHERE {$whereStr}";
        }else{
          $this->whereCond .= " {$merge} {$whereStr}";
        }
      }
      return $this;
    }

    public function desc( string $column ):DBTemp
    {
      return $this->orderBase($column, 'DESC');
    }

    public function asc( string $column ):DBTemp
    {
      return $this->orderBase($column, 'asc');
    }

    private function orderBase( string $column, string $sort ):DBTemp
    {
      if ( empty( $column ) || empty( $sort )  ){
        trigger_error( "One of the parameters is empty. Method: orderBy", E_USER_ERROR );
      }
      if ( empty($this->selectStmt) ){
        trigger_error( "Select statment is not set", E_USER_ERROR);
      }
      $this->orderCond = "ORDER BY {$column} {$sort}";

      return $this;
    }

    public function limit( int $limit ):DBTemp
    {
      if ( empty( $limit ) ){
        trigger_error( "One of the parameters is empty. Method: orderBy", E_USER_ERROR );
      }
      if ( empty($this->selectStmt) ){
        trigger_error( "Select statment is not set", E_USER_ERROR);
      }
      $this->limitCond = "LIMIT {$limit}";

      return $this;
    }

    public function group( string $group ):DBTemp
    {
      if ( empty( $group ) ){
        trigger_error( "One of the parameters is empty. Method: group", E_USER_ERROR );
      }
      if ( empty($this->selectStmt) ){
        trigger_error( "Select statment is not set", E_USER_ERROR);
      }
      $this->groupCond = "GROUP BY {$group}";

      return $this;
    }

    public function make()
    {
      if ( empty($this->selectStmt) ){
        trigger_error( "Select statment is not set", E_USER_ERROR);
      }

      $dataTypes = [
        'string' => 's',
        'integer' => 'i',
        'double' => 'd',
      ];

      $types = '';
      if ( !empty($this->whereCond) ){
        foreach ( $this->whereAll as $val ){
          $type = $dataTypes[ gettype($val) ];
          if ( $dataTypes[ gettype($val) ] ){
            $types .= $type;
          }
        }
      }
      $strSql = "{$this->selectStmt} {$this->whereCond} {$this->groupCond} {$this->orderCond} {$this->limitCond}";

      $stmt = mysqli_prepare($this->connection, $strSql);
      if ( !$stmt ) {
        trigger_error("Ошибка подготовки запроса: " . mysqli_error($this->connection), E_USER_ERROR);
      }

      // Собираем все значения в один плоский массив для bind_param, если задано условие
      if ( !empty($this->whereAll) ){
        $params = array_merge( [$stmt, $types], $this->whereAll );
        $result = call_user_func_array("mysqli_stmt_bind_param", $this->refValues($params));

        if( !$result ){
          trigger_error("Ошибка связывания параметров: " . mysqli_error($this->connection), E_USER_ERROR);
          mysqli_stmt_close($stmt);
        }
      }

      if( !mysqli_stmt_execute($stmt) ) {
          trigger_error("Ошибка выполнения запроса: " . mysqli_stmt_error($stmt), E_USER_ERROR);
          mysqli_stmt_close($stmt);
      }
      $this->affectedRows = mysqli_stmt_affected_rows($stmt);
      // mysqli_stmt_close($stmt);

      // Получаем метаданные результата (описания столбцов)
      $resultMeta = mysqli_stmt_result_metadata($stmt);
      if (!$resultMeta) {
          mysqli_stmt_close($stmt);
          return []; // Или выбросить исключение
      }

      $fields = [];
      $out = [];

      while ($field = mysqli_fetch_field($resultMeta)) {
          $fields[] = &$out[$field->name]; // Привязываем переменные к результату
      }

      call_user_func_array([$stmt, 'bind_result'], $fields);

      $results = [];
      while (mysqli_stmt_fetch($stmt)) {
          $row = [];
          foreach ($out as $key => $value) {
              $row[$key] = $value; // Копируем значения, чтобы избежать перезаписи при следующей итерации
          }
          $results[] = $row;
      }

      mysqli_stmt_close($stmt);

      $this->clearVariables();
      return $results; // Возвращаем массив с результатами
    }

    private function clearVariables():void
    {
      $this->selectStmt = '';
      $this->whereAll = [];
      $this->whereCond = '';
      $this->orderCond = '';
      $this->limitCond = '';
    }

    public function update( string $table, array $data, array $where ):bool
    {
      if ( empty($table) ) trigger_error("Table name cannot be empty", E_USER_ERROR);
      if ( empty($data) ) trigger_error("Insert array cannot be empty", E_USER_ERROR);
      if ( empty($where) ) trigger_error("WHERE clause cannot be empty", E_USER_ERROR);

      $dataTypes = [
        'string' => 's',
        'integer' => 'i',
        'double' => 'd',
      ];
      $types = '';

      $this->validateUpdateArray( $data );
      $this->validateWhereClause( $where );

      foreach ( $data as $col => $val ){
        $setClause[] = "{$col} = ?";
        $type = strval(gettype($val));

        if ( isset($dataTypes[$type]) ){
          $types .= $dataTypes[ $type ];
        } else {
          trigger_error("Key '{$col}' has invalid data type - {$type}", E_USER_ERROR);
        }
        unset($type);
      }
      $setClause = implode( ',', $setClause );

      $whereOccurs = [];
      foreach ( $where as $cond ){
        $whereClause[] = "{$cond['column']} {$cond['operator']} ?";
        $type = gettype($cond['value']);
        $whereOccurs[] = $cond['value'];
        if ( isset($dataTypes[$type]) ){
          $types .= $dataTypes[ $type ];
        } else {
          trigger_error("Invalid value type in where clause - {$type}", E_USER_ERROR);
        }
        unset($type);
      }
      $whereClause = implode( " AND ", $whereClause );

      $strSql = "UPDATE {$table} SET {$setClause} WHERE {$whereClause}";

      $stmt = mysqli_prepare($this->connection, $strSql);
      if ( !$stmt ) {
        trigger_error("Ошибка подготовки запроса: " . mysqli_error($this->connection), E_USER_ERROR);
      }

      $params = array_merge( [$stmt, $types], array_values($data), $whereOccurs );
      $result = call_user_func_array("mysqli_stmt_bind_param", $this->refValues($params));

      if( !$result ){
          trigger_error("Ошибка связывания параметров: " . mysqli_error($this->connection), E_USER_ERROR);
          mysqli_stmt_close($stmt);
        }
      if( !mysqli_stmt_execute($stmt) ) {
          trigger_error("Ошибка выполнения запроса: " . mysqli_stmt_error($stmt), E_USER_ERROR);
          mysqli_stmt_close($stmt);
      }
      $this->affectedRows = mysqli_stmt_affected_rows($stmt);
      mysqli_stmt_close($stmt);

      return true;
    }

    public function delete( string $table, array $where ):bool
    {
      if ( empty($table) ) trigger_error("Table name cannot be empty", E_USER_ERROR);
      if ( empty($where) ) trigger_error("Insert where clause cannot be empty", E_USER_ERROR);
      $this->validateWhereClause( $where );

      $dataTypes = [
        'string' => 's',
        'integer' => 'i',
        'double' => 'd',
      ];
      $types = '';

      $whereOccurs = [];
      foreach ( $where as $cond ){
        $whereClause[] = "{$cond['column']} {$cond['operator']} ?";
        $type = gettype($cond['value']);
        $whereOccurs[] = $cond['value'];
        if ( isset($dataTypes[$type]) ){
          $types .= $dataTypes[ $type ];
        } else {
          trigger_error("Invalid value type in where clause - {$type}", E_USER_ERROR);
        }
        unset($type);
      }
      $whereClause = implode( " AND ", $whereClause );

      $strSql = "DELETE FROM {$table} WHERE {$whereClause}";

      $stmt = mysqli_prepare($this->connection, $strSql);
      if ( !$stmt ) {
        trigger_error("Ошибка подготовки запроса: " . mysqli_error($this->connection), E_USER_ERROR);
      }

      $params = array_merge( [$stmt, $types], $whereOccurs );
      $result = call_user_func_array("mysqli_stmt_bind_param", $this->refValues($params));

      if( !$result ){
          trigger_error("Ошибка связывания параметров: " . mysqli_error($this->connection), E_USER_ERROR);
          mysqli_stmt_close($stmt);
        }
      if( !mysqli_stmt_execute($stmt) ) {
          trigger_error("Ошибка выполнения запроса: " . mysqli_stmt_error($stmt), E_USER_ERROR);
          mysqli_stmt_close($stmt);
      }
      $this->affectedRows = mysqli_stmt_affected_rows($stmt);
      mysqli_stmt_close($stmt);

      return true;
    }

    public function insert( string $table, array $data ):bool
    {
      if ( empty($table) ) trigger_error("Table name cannot be empty", E_USER_ERROR);
      if ( empty($data) ) trigger_error("Insert array cannot be empty", E_USER_ERROR);

      $validate = $this->validateInsertArray( $data );
      if ( $validate !== true ) trigger_error( $validate, E_USER_ERROR );

      $fields = array_keys($data[0]);
      $fieldsStr = implode(',', $fields);

      $placeholders = implode(',', array_fill(0, count($fields), '?'));
      $valuesPlaceholders = implode(', ', array_fill(0, count($data), "({$placeholders})"));

      $strSql = "INSERT INTO {$table} ({$fieldsStr}) VALUES {$valuesPlaceholders}";

      $stmt = mysqli_prepare($this->connection, $strSql);
      if (!$stmt) {
        trigger_error("Ошибка подготовки запроса: " . mysqli_error($this->connection), E_USER_ERROR);
        return false;
      }
      $types = str_repeat('s', count($fields) * count($data));

      $allValues = [];
      foreach ($data as $row) {
        $allValues = array_merge($allValues, array_values($row));
      }

      $params = array_merge([$stmt, $types], $allValues);
      $result = call_user_func_array("mysqli_stmt_bind_param", $this->refValues($params));

      if( !$result ){
          trigger_error("Ошибка связывания параметров: " . mysqli_error($this->connection), E_USER_ERROR);
          mysqli_stmt_close($stmt);
          return false;
        }
      if( !mysqli_stmt_execute($stmt) ) {
          trigger_error("Ошибка выполнения запроса: " . mysqli_stmt_error($stmt), E_USER_ERROR);
          mysqli_stmt_close($stmt);
          return false;
      }
      $this->affectedRows = mysqli_stmt_affected_rows($stmt);
      mysqli_stmt_close($stmt);

      return true;
    }

    private function validateWhereClause( array $array ):bool
    {
      $expectedNumericKey = 0;
      $allowedOperators = [">","<","<=", ">=", "=", "!=", "LIKE"];
      foreach ( $array as $key => $innerArray ){
        if ( $key !== $expectedNumericKey ){
          trigger_error( "Inacceptable where clause array structure: first layer keys must be default", E_USER_ERROR);
        }
        if ( !is_array($innerArray) ){
          trigger_error( "Inacceptable where clause array structure: inner value must be an array", E_USER_ERROR);
        }
        if ( empty($innerArray['column']) || empty($innerArray['operator']) || empty($innerArray['value']) ){
          trigger_error( "Inacceptable where clause array structure: invalid inner value structure", E_USER_ERROR);
        }
        if ( !is_string( $innerArray["column"] ) ){
          trigger_error( "Inacceptable where clause array structure: column name must be a string", E_USER_ERROR);
        }
        if ( !in_array($innerArray["operator"], $allowedOperators) ){
          trigger_error( "Inacceptable where clause array structure: invalid operator", E_USER_ERROR);
        }
      }
      return true;
    }

    private function validateUpdateArray( array $data ):bool
    {
      foreach ( $data as $key => $value ){
        if ( !is_string($key) ){
          trigger_error( "Inacceptable where clause array structure: invalid operator", E_USER_ERROR );
        }
      }
      return true;
    }

    private function validateInsertArray( array $array ):bool
    {
      $expectedNumericKey = 0;
      $fields = [];

      foreach( $array as $key => $layer1 ){
        if ( $key !== $expectedNumericKey )
         trigger_error( "Inacceptable array structure: first layer keys must be default", E_USER_ERROR);
        if ( !is_array($layer1) )
         trigger_error("Inacceptable array structure: first layer value must be an array (layer 1 key {$key})", E_USER_ERROR);

        if ( empty($fields) ) $fields = array_keys($layer1);

        foreach ( $layer1 as $field => $layer2 ){
          if ( !is_string($field) )
           trigger_error("Inacceptable array structure: second layer key must be a string (layer 1 key: {$key})", E_USER_ERROR);
          if ( is_array($layer2) )
           trigger_error("Inacceptable array structure: second layer value cannot be an array (layer 1 key: {$key})", E_USER_ERROR);
          if ( !in_array($field, $fields) )
           trigger_error("Inacceptable array structure: second layer values' fields mismatch (layer 1 key: {$key})", E_USER_ERROR);
        }
        $expectedNumericKey++;
      }
      return true;
    }

    private function refValues( array $arr ):array
    {
      $refs = [];
      foreach($arr as $key => $value){
        $refs[$key] = &$arr[$key];
      }
      return $refs;
    }

    public function fetchAll($result) {
        return mysqli_fetch_all($result, MYSQLI_ASSOC);
    }

    public function getConnection() {
       return $this->connection;
    }

    public function close() {
        mysqli_close($this->connection);
    }
}

<?php
/*======================================================================*\
||                 Cybershade CMS - Your CMS, Your Way                  ||
\*======================================================================*/
defined('INDEX_CHECK') or die('Error: Cannot access directly.');

/**
* SQL Query Builder
*
* @version      1.0
* @since        1.0.0
* @author       xLink
*/
class queryBuilder extends coreObj{

    private $queryType = '';
    private $_fields   = array();
    private $_values   = array();
    private $_tables   = array();
    
    private $_join     = '';
    private $_using    = '';
    private $_on       = array();
    
    private $_where    = array();
    private $_limit    = 0;
    private $_offset   = 0;
    
    private $_orderBy  = array();
    private $_order    = 'ASC';
    private $_groupBy  = array();

/**
  //
  //-- Query Type Functions
  //
**/
    public function select(){
        $this->setQueryType('select');

        $args = $this->_getArgs(func_get_args());
            foreach($args as $key => $arg){
                $this->addField($arg, $key);
            }

        return $this;
    }

        public function insertInto($table){
            $this->setQueryType('insert');
            $this->_tables[] = $table;
            return $this;
        }

        public function deleteFrom($table){
            $this->setQueryType('delete');
            $args = $this->_getArgs(func_get_args());
            $this->_tables = $args;
            return $this;
        }

        public function update($table){
            $this->setQueryType('update');
            $this->_tables = array($table);
            return $this;
        }

/**
  //
  //-- Core Functions
  //
**/

    public function from($tables){
        if($this->queryType != 'SELECT'){
            trigger_error('Error: Only SELECT operators.', E_USER_ERROR);
        }

        $this->_tables = $this->_getArgs(func_get_args());

        return $this;
    }

        public function addField($field, $key=null){
            if(!is_string($field)){ trigger_error('addField; typeOf $field != "string"', E_USER_ERROR); }

            if(!in_array($field, $this->_fields)){
                if(empty($key)){
                    $this->_fields[] = $field;
                }else{
                    $this->_fields[$key] = $field;
                }
            }
            return $this;
        }

    public function where($cond1, $operand, $cond2){
        return $this->_addWhereOn($cond1, $operand, $cond2, '', 'where');
    }

        public function andWhere($cond1, $operand, $cond2){
            return $this->_addWhereOn($cond1, $operand, $cond2, 'AND', 'where');
        }

        public function orWhere($cond1, $operand, $cond2){
            return $this->_addWhereOn($cond1, $operand, $cond2, 'OR', 'where');
        }

    public function join($table){
        $this->_join = sprintf('JOIN %s', $this->_buildTables($table));

        return $this;
    }
        
        public function leftJoin($table){
            $this->_join = sprintf('LEFT JOIN %s', $this->_buildTables($table));

            return $this;
        }
        
        public function rightJoin($table){
            $this->_join = sprintf('RIGHT JOIN %s', $this->_buildTables($table));

            return $this;
        }
        
        public function using($field){
            $this->_using = $field;

            return $this;
        }
        
    public function on($c1, $operand, $c2){
        return $this->_addWhereOn($c1, $operand, $c2, '', 'on');
    }
        
        public function andOn($c1, $operand, $c2){
            return $this->_addWhereOn($c1, $operand, $c2, 'AND', 'on');
        }
        
        public function orOn($c1, $operand, $c2){
            return $this->_addWhereOn($c1, $operand, $c2, 'AND', 'on');
        }

    public function args(){
        if (!in_array($this->queryType, array('INSERT', 'UPDATE'))) {
            trigger_error('Error: Only INSERT and Update operations.', E_USER_ERROR);
        }

        $args = $this->_getArgs(func_get_args());

        $this->fields(array_keys($args));
        $this->values(array_values($args));

        return $this;
    }

    public function fields($fields) {
        if (!in_array($this->queryType, array('INSERT', 'UPDATE'))) {
            trigger_error('Error: Only INSERT and Update operations.', E_USER_ERROR);
        }

        $args = $this->_getArgs(func_get_args());
        $this->_fields = $args;

        return $this;
    }

    public function values($values) {
        if (!in_array($this->queryType, array('INSERT', 'UPDATE'))) {
            trigger_error('Error: Only INSERT and Update operations.', E_USER_ERROR);
        }

        $args = $this->_getArgs(func_get_args());
            if (count($args) != count($this->_fields)) {
                trigger_error('Error: Number of values has to be equal to the number of fields.', E_USER_ERROR);
            }

        if ($this->queryType == 'INSERT') {
            $this->_values[] = $args;
        } elseif ($this->queryType == 'UPDATE') {
            $this->_values = $args;
        }

        return $this;
    }

    public function set($field) {
        $args = func_get_args();

        if (count($args) == 2) {
            $args = array($args[0] => $args[1]);
        } else {
            $args = $this->_getArgs(func_get_args());
        }

        foreach ($args as $field => $val) {
            if (!in_array($field, $this->_fields)) {
                $this->_fields[] = $field;
                $this->_values[] = $val;
            }
        }

        return $this;
    }

    public function limit($limit = 0){
        $limit =(int)abs($limit);
        $this->_limit = $limit;

        return $this;
    }

    public function order($order){
        $order = strtoupper($order);
        if(in_array($order, array('ASC', 'DESC'))){
            $this->_order = $order;
        }

        return $this;
    }

    public function orderBy($orderBy){
        $args = $this->_getArgs(func_get_args());
        $this->_orderBy = $args;

        return $this;
    }

    public function groupBy($groupBy){
        $args = $this->_getArgs(func_get_args());
        $this->_groupBy = $args;

        return $this;
    }

    public function offset($offset = 0){
        $offset =(int)abs($offset);
        if($offset){
            $this->_offset = $offset;
        }

        return $this;
    }


/**
  //
  //-- Build Functions
  //
**/
    public function build(){
        $statement = array();
        $this->_buildOperator($statement);
        $this->{'_build'.$this->queryType}($statement);
        
        $this->_buildJoin($statement);

        $this->_buildWhereOn($statement, 'where');

        $this->_buildGroupBy($statement);

        $this->_buildOrderBy($statement);

        $this->_buildLimit($statement);

        $statement = implode(' ', $statement);

        return $statement;
    }
        
        private function _buildJoin(&$statement){
            if(!$this->_join){ return; }

            $statement[] = $this->_join;
            if($this->_using){
                $statement[] = sprintf('USING(%s)', $this->_using);
            }
            $this->_buildWhereOn($statement, 'on');
        }

        private function _buildUpdate(&$statement){
            $statement[] = sprintf('%s', $this->_buildTables($this->_tables));
            $statement[] = 'SET';

            $set = array();
            foreach($this->_fields as $k => $f){
                $set[] = sprintf('`%s` = %s', $f, $this->_sanitizeValue($this->_values[$k]));
            }

            $statement[] = implode(', ', $set);
        }

        private function _buildDELETE(&$statement){
            $statement[] = sprintf('FROM %s', $this->_buildTables($this->_tables));
        }

        private function _buildSELECT(&$statement){
            $statement[] = $this->_buildFields($this->_fields);
            $statement[] = sprintf('FROM %s', $this->_buildTables($this->_tables));
        }

        private function _buildINSERT(&$statement){
            $statement[] = 'INTO';
            $statement[] = sprintf('%s', $this->_buildTables($this->_tables));
            $this->_buildINSERTFields($statement);

            $statement[] = 'VALUES';
            $this->_buildINSERTValues($statement);
        }

        private function _buildINSERTFields(&$statement){
            $statement[] = sprintf('("%s")', implode('", "', $this->_fields));
        }

        private function _buildINSERTValues(&$statement){
            $values = array();
            foreach($this->_values as $field => $val){
                foreach($val as &$v){
                    $v = $this->_sanitizeValue($v);
                }
                $values[] = sprintf('(%s)', implode(', ', $val));
            }
            $statement[] = implode(', ', $values);
        }

        private function _buildOperator(&$statement){
            $statement[] = $this->queryType;
        }

        private function _buildFields($fields){
            $_fields = array();

            if(!is_array($fields)){ $fields = array($fields); }

            foreach($fields as $key => $field){
                $field = explode('.', $field);

                if(count($field) == 1){
                    $_fields[] = sprintf('`%s`', $field[0]);
                    continue;
                }

                if(!is_number($key)){
                    $_fields[] = sprintf('%s.`%s` as `%s`', $field[0], $field[1], $key);
                }else{
                    $_fields[] = sprintf('%s.`%s`', $field[0], $field[1]);
                }
            }

            return implode(', ', $_fields);
        }

        private function _buildTables($tables){
            $_tables = array();

            if(!is_array($tables)){ return sprintf('`%s`', $table); }

            foreach($tables as $key => $table){
                if(isset($key) && !empty($key)){
                    $_tables[] = sprintf('`%s` as %s', $table, $key);
                }else{
                    $_tables[] = $table;
                }
            }
            return implode(', ', $_tables);
        }

        private function _buildWhereOn(&$statement, $type){
            if(!in_array($this->queryType, array('UPDATE', 'DELETE', 'SELECT'))){ return; }

            if(!count($this->{'_'.strtolower($type)})){ return; }

            $statement[] = strtoupper($type);
            foreach($this->{'_'.strtolower($type)} as $where){
                $tmp = array($where['type'], $where['cond1'], $where['operand']);
                $tmp[1] = $this->_buildFields($tmp[1]);

                if($where['operand'] != 'IN'){
                    if($type == 'where'){
                        $tmp[] = $this->_sanitizeValue($where['cond2'], $where['operand'] == 'LIKE');
                    }else{
                        $tmp[] = $where['cond2'];
                    }
                }else{

                    $ins = array();
                    if(!is_array($where['cond2'])){
                        $ins = array($where['cond2']);
                    }else{
                        foreach($where['cond2'] as $c2){
                            $ins[] = $this->_sanitizeValue($c2, false);
                        }
                    }

                    $tmp[2] = sprintf('%s ("%s")', $tmp[2], implode(', ', $ins));
                }
                $statement[] = implode(' ', $tmp);
            }
        }

        private function _buildGroupBy(&$statement){
            if($this->queryType != 'SELECT'){ return; }

            if(!count($this->_groupBy)){ return; }

            $statement[] = 'GROUP BY';
            $gbs = array();
            foreach($this->_groupBy as $gb){
                $gbs[] = sprintf('`%s`', $gb);
            }
            $statement[] = implode(', ', $gbs);
        }

        private function _buildOrderBy(&$statement){
            if($this->queryType != 'SELECT'){ return; }

            if(!count($this->_orderBy)){ return; }

            $statement[] = 'ORDER BY';

            $obs = array();
            foreach($this->_orderBy as $ob){
                if(in_array(strtoupper($ob), array('ASC', 'DESC'))){ $this->_order = strtoupper($ob); continue; }

                $obs[] = $this->_buildFields($ob);
            }

            $statement[] = implode(', ', $obs);
            $statement[] = $this->_order;
        }

        private function _buildLimit(&$statement){
            if($this->_offset > 0 && $this->_limit > 0){
                $statement[] = sprintf('LIMIT %s, %s', $this->_offset, $this->_limit);

            }elseif($this->_offset > 0){
                $statement[] = sprintf('OFFSET %d', $this->_offset);

            }elseif($this->_limit > 0){
                $statement[] = sprintf('LIMIT %d', $this->_limit);
            }
        }

/**
  //
  //-- Extra Functions
  //
**/

    private function _addWhereOn($cond1, $operand, $cond2, $type, $property){
        $operand = strtoupper($operand);
        if(!in_array($operand, array('=', '>', '<', '<>', '!=', '<=', '>=', 'LIKE', 'IN'))){
            trigger_error('Unsupported operand:'.$operand, E_USER_ERROR);
        }
        $this->{'_'.$property}[] = array(
            'cond1'   => $cond1,
            'cond2'   => $cond2,
            'operand' => $operand,
            'type'    => $type
        );
        return $this;
    }

    private function _getArgs($args){
        $argsCnt = count($args);
        if(!$argsCnt){ return array(); }

        if($argsCnt == 1){
            if(!is_array($args[0])){ return array($args[0]); }

            return $args[0];
        }else{
            $return = array();
            foreach($args as $arg){ $return[] = $arg; }

            return $return;
        }

        return array();
    }

    protected function _sanitizeValue($val) {
        if(is_number($val)){
            return $val; 
        }
        if(in_array($val, array('NULL', 'true', 'false', null))){
            return $val;
        } 

        return '"' . $val . '"';
    }

    private function setQueryType($queryType){
        if($this->queryType){
            trigger_error('Can\'t modify the operator.', E_USER_ERROR);

        }elseif(!in_array($queryType, array('select', 'insert', 'delete', 'update'))){
            trigger_error('Unsupported operator:'.strtoupper($queryType), E_USER_ERROR);

        }else{
            $this->queryType = strtoupper($queryType);
        }
    }

}

?>
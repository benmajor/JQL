<?php

namespace BenMajor\JQL;

class JQL
{
    private $fields    = [ ];
    private $where     = [ ];
    private $order     = [ ];
    private $limitAmt  = null;
    private $offsetAmt = 0;
    
    private $data;
    private $assoc;
    private $result;
    
    private $functionRegEx = '/([A-Z_]+)\s*([(](.+)[)])\s*([AS|as]*+)\s*([a-zA-z0-9]*+)/';
    
    private $allowedFunctions = [
        # String functions:
        'CHAR_LENGTH', 'CHARACTER_LENGTH', 'CONCAT', 'CONCAT_WS', 'FORMAT', 'LCASE', 'LEFT', 'LOWER', 'LPAD', 'LTRIM', 'REPLACE', 'REVERSE', 'RIGHT', 'RPAD', 'RTRIM', 'SUBSTR', 'SUBSTRING', 'TRIM', 'UCASE', 'UPPER', 
        
        # Numeric functions:
        
        # Date functions:
    ];
    
    function __construct( $json )
    {
        if( is_string($json) )
        {
            $decoded = json_decode($json, true);
            $error   = json_last_error();
            
            # There was an error parsing the JSON string -- it might be a file:
            if( $error !== JSON_ERROR_NONE )
            {
                if( file_exists($json) )
                {
                    $decoded = json_decode( file_get_contents($json), true );
                    $error   = json_last_error();
                    
                    if( $error !== JSON_ERROR_NONE )
                    {
                        throw new QueryException('Error parsing JSON file: '.$error);
                    }
                }
                else
                {
                    throw new QueryException('Error parsing JSON string: '.$error);
                }
            }
            
            $this->data = $decoded;
        }
        elseif( is_object($json) || is_array($json) )
        {
            $this->data = $json;
        }
        else
        {
            throw new QueryException('Parameter passed to JQL must be a valid JSON string or object / array.');
        }
        
        $this->assoc = json_decode( json_encode($this->data), true );
    }
    
    # Start by adding which fields to select:
    public function select( $fields )
    {
        if( is_array($fields) )
        {
            foreach( $fields as $field )
            {
                if( ! is_string($field) )
                {
                    throw new QueryException('Parameter passed to select() must be an array of strings or a string of *.');
                }
            }
            
            $this->fields = $fields;
        }
        elseif( $fields == '*' )
        {
            $this->fields = '*';
        }
        else
        {
            throw new QueryException('Parameter passed to select() must be an array of strings or a string of *.');
        }
        
        # Return the object to preserve method-chaining:
        return $this;
    }
    
    # Add a where clause:
    public function where( string $where )
    {
        #  Give preference to OR queries:
        if( strstr($where, ' OR ') )
        {
            $operand = 'OR';
            $parts = explode(' OR ', $where);
        }
        else
        {
            $operand = 'AND';
            $parts = explode(' AND ', $where);
        }
        
        $clauses = [
            'type' => $operand,
            'ops'  => [ ]
        ];
        
        foreach( $parts as $clause )
        {
            # Handle NULL:
            if( strstr($clause, ' IS NULL')  )
            {
                $clauseParts = explode('IS NULL', $clause);
                
                $clauses['ops'][] = [
                    'column'  => trim($clauseParts[0]),
                    'operand' => '=',
                    'compare' => null
                ];
            }
            
            # Handle NOT NUll:
            elseif( strstr($clause, ' IS NOT NULL') )
            {
                $clauseParts = explode('IS NOT NULL', $clause);
                
                $clauses['ops'][] = [
                    'column'  => trim($clauseParts[0]),
                    'operand' => '!=',
                    'compare' => null
                ];
            }
            
            # Handle LIKE:
            elseif( strstr($clause, ' LIKE ') )
            {
                $clauseParts = explode(' LIKE ', $clause);
                
                $clauses['ops'][] = [
                    'column'  => trim($clauseParts[0]),
                    'operand' => 'LIKE',
                    'compare' => trim( end($clauseParts) )
                ];
            }
            
            # Handle SLIKE:
            elseif( strstr($clause, ' SLIKE ') )
            {
                $clauseParts = explode(' SLIKE ', $clause);
                
                $clauses['ops'][] = [
                    'column'  => trim($clauseParts[0]),
                    'operand' => 'SLIKE',
                    'compare' => trim( end($clauseParts) )
                ];
            }
            
            # Handle LIKE:
            elseif( strstr($clause, ' NOT LIKE ') )
            {
                $clauseParts = explode(' LIKE ', $clause);
                
                $clauses['ops'][] = [
                    'column'  => trim($clauseParts[0]),
                    'operand' => 'NOT LIKE',
                    'compare' => trim( end($clauseParts) )
                ];
            }
            
            # Handle SLIKE:
            elseif( strstr($clause, ' NOT SLIKE ') )
            {
                $clauseParts = explode(' SLIKE ', $clause);
                
                $clauses['ops'][] = [
                    'column'  => trim($clauseParts[0]),
                    'operand' => 'NOT SLIKE',
                    'compare' => trim( end($clauseParts) )
                ];
            }
            
            # It's a normal operator:
            else
            {
                $operands = [ '==', '>=', '<=', '<>', '!=', '=', '<', '>' ];
                
                foreach( $operands as $find )
                {
                    if( strstr($clause, $find) )
                    {
                        $clauseParts = explode($find, $clause);
                        
                        $clauses['ops'][] = [
                            'column'  => trim($clauseParts[0]),
                            'operand' => $find,
                            'compare' => trim(end($clauseParts))
                        ];
                    }
                }
            }
        }
        
        $this->where = $clauses;
        
        # Return the object to preserve method-chaining:
        return $this;
    }
    
    # Set the order:
    public function order( string $field, string $dir = 'ASC' )
    {
        $dir = strtoupper($dir);
        
        if( ! in_array($dir, [ 'ASC', 'DESC' ]) )
        {
            throw new QueryException('Invalid sort order specified. Must be one of: ASC, DESC.');
        }
        
        $this->order[$field] = $dir;
        
        # Return object to preserve method-chaining:
        return $this;
    }
    
    # Set the limit and optionally the offset:
    public function limit( int $limit, int $offset = null )
    {
        $this->limitAmt = $limit;
        
        if( ! is_null($offset) )
        {
            $this->offset($offset);
        }
        
        return $this;
    }
    
    # Set the offset explicitly:
    public function offset( int $offset = 0 )
    {
        $this->offsetAmt = $offset;
        
        return $this;
    }
    
    # Return the first example:
    public function fetchOne()
    {
        $this->execute();
        
        return $this->result[0];
    }
    
    # Return all matches:
    public function fetch()
    {
        $this->execute();
        
        if( $this->limitAmt != null )
        {
            return array_slice($this->result, $this->offsetAmt, $this->limitAmt);
        }
        
        return $this->result;
    }
    
    # Count the number of matches:
    public function count()
    {
        $this->execute();
        
        return count($this->result);
    }
    
    # Execute the query:
    private function execute()
    {
        $matches  = [ ];
        $return   = [ ];
        
        # Is there a WHERE clause?
        if( ! empty($this->where) )
        {
            $type        = $this->where['type'];
            $clauseCount = count($this->where['ops']);
                
            foreach( $this->assoc as $row )
            {
                $matchCount  = 0;
                
                foreach( $this->where['ops'] as $clause )
                {
                    if( $this->check_clause( $clause, $row ) )
                    {
                        $matchCount++;
                    }
                }
                
                if( ($type == 'AND' && $matchCount == $clauseCount) || ($type == 'OR' && $matchCount > 0) )
                {
                    $matches[] = $row;
                }
            }
        }
        
        # No where clause, so just assign all entries:
        else
        {
            if( is_array($this->data) )
            {
                $matches = $this->assoc;
            }
            else
            {
                $matches[] = $this->assoc;
            }
        }
        
        # Now we need to sort the matches:
        if( ! empty($this->order) )
        {
            uasort($matches, function($a, $b) {
                
                $c = 0;
                
                foreach( $this->order as $field => $direction )
                {
                    if( is_numeric($a[$field]) )
                    {
                        $c.= ($direction == 'ASC') ? $a[$field] - $b[$field]
                                                   : $b[$field] - $a[$field];
                    }
                    else
                    {
                        $c.= ($direction == 'ASC') ? $a[$field] > $b[$field]
                                                   : $b[$field] > $a[$field];
                    }
                }
                
                return $c;
            });
        }
        
        if( $this->fields != '*' && count($this->fields) )
        {
            # Loop over the matches and handle the fields:
            foreach( $matches as $match )
            {
                $tmp = [ ];
                
                # Loop over the fields and handle them:
                foreach( $this->fields as $field )
                {
                    # If it's a function, execute it:
                    if( preg_match($this->functionRegEx, $field, $functionParts) )
                    {
                        $key = (empty($functionParts[5])) ? $field : $functionParts[5];
                        $tmp[ $key ] = $this->execute_function( $functionParts[1], (empty($functionParts[3]) ? [ ] : explode(',', $functionParts[3])), $match );
                    }
                    else
                    {
                        $tmp[$field] = $match[$field];
                    }
                }
                
                $return[] = $tmp;
            }
        }
        else
        {
            $return = $matches;
        }
        
        $this->result = $return;
    }
    
    # Actually run a function:
    private function execute_function( $function, $args = [ ], $row )
    {
        if( ! in_array($function, $this->allowedFunctions) )
        {
            throw new SyntaxException('Unknown function: '.$function);
        }
        
        # Clean up the arguments:
        array_walk( $args, function(&$item) { $item = trim($item); $item = trim($item, "'"); });
        
        $value = null;
        
        # Returns the length of a string (in characters)
        if( $function == 'CHAR_LENGTH' || $function == 'CHARACTER_LENGTH' )
        {
            if( count($args) != 1 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 1 parameter.');
            }
            
            $value = (array_key_exists($args[0], $row)) ? strlen( $row[$args[0]] ) : strlen($args[0]);
        }
        
        # Adds two or more expressions together
        elseif( $function == 'CONCAT' )
        {
            if( count($args) < 1 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 1 parameter.');
            }
            
            $tmp = [ ];
            
            foreach( $args as $field )
            {
                $tmp[] = (array_key_exists($field, $row)) ? $row[$field] : $field;
            }
            
            $value = implode($tmp);
        }
        
        # Adds two or more expressions together with a separator
        elseif( $function == 'CONCAT_WS' )
        {
            if( count($args) < 2 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 2 parameters.');
            }
            
            $tmp = [ ];
            $sep = $args[0];
            
            foreach( array_slice($args, 1) as $field )
            {
                $tmp[] = (array_key_exists($field, $row)) ? $row[$field] : $field;
            }
            
            $value = implode($sep, $tmp);
        }
        
        # Formats a number to a format like "#,###,###.##", rounded to a specified number of decimal places
        elseif( $function == 'FORMAT' )
        {
            if( count($args) != 2 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 2 parameters.');
            }
            
            if( !ctype_digit($args[1]) )
            {
                throw new SyntaxException('Second parameter of function '.$function.' must be an integer.');
            }
            
            $operator = (array_key_exists($args[0], $row)) ? $row[$args[0]] : $args[0];
            
            if( ! is_numeric($operator) )
            {
                $operator = 0;
            }
            
            $value = number_format( $operator, $args[1], '.', ',' );
        }
        
        # Converts a string to lower-case
        elseif( $function == 'LCASE' || $function == 'LOWER' )
        {
            if( count($args) != 1 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 1 parameter.');
            }
            
            $operator = (array_key_exists($args[0], $row)) ? $row[$args[0]] : $args[0];
            
            $value = strtolower($operator);
        }
        
        # Extracts a number of characters from a string (starting from left)
        elseif( $function == 'LEFT' )
        {
            if( count($args) != 2 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 2 parameters.');
            }
            
            if( ! ctype_digit($args[1]) )
            {
                throw new SyntaxException('Second parameter of function '.$function.' must be an integer.');
            }
            
            $operator = (array_key_exists($args[0], $row)) ? $row[$args[0]] : $args[0];
            
            $value = substr($operator, 0, $args[1]);
        }
        
        # Left-pads a string with another string, to a certain length
        elseif( $function == 'LPAD' || $function == 'RPAD' )
        {
            if( count($args) != 3 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 3 parameters.');
            }
            
            # Is the second parameter a number?
            if( ! ctype_digit($args[1]) )
            {
                throw new SyntaxException('Second parameter of function '.$function.' must be an integer.');
            }
            
            $operator = (array_key_exists($args[0], $row)) ? $row[$args[0]] : $args[0];
            
            $value = str_pad($operator, $args[1], $args[2], (($function == 'LPAD') ? STR_PAD_LEFT : STR_PAD_RIGHT));
        }
        
        # Removes leading spaces from a string
        elseif( $function == 'LTRIM' || $function == 'RTRIM' || $function == 'TRIM' )
        {
            if( count($args) != 1 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 1 parameter.');
            }
            
            $operator = (array_key_exists($args[0], $row)) ? $row[$args[0]] : $args[0];
            
            if( $function == 'LTRIM' )
            {
                $value = ltrim($operator);
            }
            elseif( $function == 'RTRIM' )
            {
                $value = rtrim($operator);
            }
            else
            {
                $value = trim($operator);
            }
        }
        
        # Replaces all occurrences of a substring within a string, with a new substring
        elseif( $function == 'REPLACE' )
        {
            if( count($args) != 3 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 3 parameters.');
            }
            
            $operator = (array_key_exists($args[0], $row)) ? $row[$args[0]] : $args[0];
            
            $value = str_replace($args[1], $args[2], $operator);
        }
        
        # Reverses a string and returns the result
        elseif( $function == 'REVERSE' )
        {
            if( count($args) != 1 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 1 parameter.');
            }
            
            $operator = (array_key_exists($args[0], $row)) ? $row[$args[0]] : $args[0];
            
            $value = strrev($operator);
        }
        
        # Extracts a number of characters from a string (starting from right)
        elseif( $function == 'RIGHT' )
        {
            if( count($args) != 2 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 2 parameters.');
            }
            
            if( ! ctype_digit($args[1]) )
            {
                throw new SyntaxException('Second parameter of function '.$function.' must be an integer.');
            }
            
            $operator = (array_key_exists($args[0], $row)) ? $row[$args[0]] : $args[0];
            
            $value = substr($operator, (0 - $args[1]));
        }
        
        # Extracts a substring from a string (starting at any position)
        elseif( $function == 'SUBSTR' || $function == 'SUBSTRING' )
        {
            if( count($args) != 3 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 3 parameters.');
            }
            
            if( ! ctype_digit($args[1]) )
            {
                throw new SyntaxException('Second parameter of function '.$function.' must be an integer.');
            }
            
            if( ! ctype_digit($args[2]) )
            {
                throw new SyntaxException('Third parameter of function '.$function.' must be an integer.');
            }
            
            $operator = (array_key_exists($args[0], $row)) ? $row[$args[0]] : $args[0];
            
            $value = substr($operator, $args[1], $args[2]);
        }
        
        # Converts a string to upper-case
        elseif( $function == 'UCASE' || $function == 'UPPERCASE' )
        {
            if( count($args) != 1 )
            {
                throw new SyntaxException('Invalid parameter count for function '.$function.'; expects exactly 1 parameters.');
            }
            
            $operator = (array_key_exists($args[0], $row)) ? $row[$args[0]] : $args[0];
            
            $value = strtoupper($operator);
        }
        
        return $value;
    }
    
    # Run a where clause:
    private function check_clause( $clause, $row )
    {
        if( ! array_key_exists($clause['column'], $row) )
        {
            throw new SyntaxException('Specified column \''.$clause['column'].'\' does not exist.');
        }
        
        # This is the map of comparrison keywords:
        $keywordMap = [
            'null'  => null,
            'true'  => true,
            'false' => false,
        ];
        
        $value   = $row[ $clause['column'] ];
        $compare = (array_key_exists(strtolower($clause['compare']), $keywordMap)) ? $keywordMap[strtolower($clause['compare'])] : $clause['compare'];
        
        # LIKE clauses a bit different:
        if( in_array($clause['operand'], [ 'LIKE', 'SLIKE', 'NOT LIKE', 'NOT SLIKE' ]) )
        {
            $first = $compare[0];
            $last  = $compare[( strlen($compare) - 1)];
            
            $clean        = strtolower($value);
            $cleanCompare = strtolower($compare);
            
            # Is it a wildcard?
            if( $first == '%' || $last == '%')
            {
                # Start AND end are wildcards:
                if( $first == $last )
                {
                    $compareClean = trim($compare, '%');
                    
                    switch( $clause['operand'] )
                    {
                        case 'LIKE':
                            return strstr(strtolower($value), strtolower($compareClean));
                            break;
                        
                        case 'NOT LIKE':
                            return ! strstr(strtolower($value), strtolower($compareClean));
                            break;
                        
                        case 'SLIKE':
                            return strstr($value, $compareClean);
                            break;
                        
                        case 'NOT SLIKE':
                            return ! strstr($value, $compareClean);
                            break;
                    }
                }
                
                # Only beginning:
                elseif( $first == '%' || $last == '%' )
                {
                    if( $first == '%' )
                    {
                        $finder = substr($compare, 1);
                        $substr = substr($value, (0 - strlen($finder)));
                    }
                    else
                    {
                        $finder = substr($compare, -1);
                        $substr = substr($value, 0, (strlen($finder)));
                    }                    
                    
                    switch( $clause['operand'] )
                    {
                        case 'LIKE':
                            return strtolower($finder) == strtolower($substr);
                            break;
                        
                        case 'NOT LIKE':
                            return strtolower($finder) != strtolower($substr);
                            break;
                        
                        case 'SLIKE':
                            return $finder == $substr;
                            break;
                        
                        case 'NOT SLIKE':
                            return $finder != $substr;
                            break;
                    }
                    
                }
            }
            else
            {
                switch( $clause['operand'] )
                {
                    case 'LIKE':
                        return $clean == $cleanCompare;
                        break;
                    
                    case 'NOT LIKE':
                        return $clean != $cleanCompare;
                        break;
                    
                    case 'SLIKE':
                        return $value == $compare;
                        break;
                    
                    case 'NOT SLIKE':
                        return $value != $compare;
                        break;
                }
            }
        }
        else
        {
            switch( $clause['operand'] )
            {
                case '=':
                    return strtolower($value) == strtolower($compare);
                    break;
                
                case '==':
                    return $value == $compare;
                    break;
                
                case '>':
                    return $value > $compare;
                    break;
                
                case '<':
                    return $value < $compare;
                    break;
                
                case '!=':
                    return $value != $compare;
                    break;
                
                case '<=':
                    return $value <= $compare;
                    break;
                
                case '>=':
                    return $value >= $compare;
                    break;
                
                case '<>':
                    return $value <> $compare;
                    break;
            }
        }
    }
}
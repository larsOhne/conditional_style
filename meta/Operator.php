<?php

namespace dokuwiki\plugin\structcondstyle\meta;

/**
 * Class Operator
 *
 * Describes a boolean operator used to evaluate conditions
 *
 * @package  dokuwiki\plugin\structcondstyle\meta
 */
class Operator
{
    private $reg;
    private $eval_func;

    function __construct($reg, $eval_func)
    {
        $this->reg = $reg;
        $this->eval_func = $eval_func;
    }

    function getReg(){
        return $this->reg;
    }

    function evaluate($lhs, $rhs){
        // Evaluate the operator
        $eval = $this->eval_func;
        return $eval($lhs,$rhs);
    }
}

class NumericOperator extends Operator
{

    private function make_numeric($value){
        if(is_numeric($value))
            return floatval($value);
        if(is_string($value)){
            if($value == "now")
                return time();
            else
                return strtotime($value);
            
        }
        else
            return FALSE;
    }

    function evaluate($lhs, $rhs)
    {
        // First convert both sides to their numerical values
        $lvalue = $this->make_numeric($lhs);
        $rvalue = $this->make_numeric($rhs);

        // If value could not be parsed, return false
        if($lvalue === FALSE or $rvalue === FALSE)
            return false;

        // otherwise simply return the super function 
        return parent::evaluate($lvalue, $rvalue);
    }

}

?>
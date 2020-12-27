<?php

namespace dokuwiki\plugin\conditionalstyle\meta;

/**
 * Class Operator
 *
 * Describes a boolean operator used to evaluate conditions
 *
 * @package  dokuwiki\plugin\conditionalstyle\meta
 */
class Operator
{
    private $reg;
    private $eval_func_name;

    function __construct($reg,$eval_func)
    {
        $this->reg = $reg;
        $this->eval_func_name = $eval_func;
    }

    function getReg(){
        return $this->reg;
    }

    function evaluate($lhs, $rhs){
        return call_user_func($this->eval_func_name,$lhs,$rhs);
    }
}

?>
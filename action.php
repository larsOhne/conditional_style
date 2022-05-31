<?php
/**
 * DokuWiki Plugin structcondstyle (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Lars Ohnemus <ohnemus.lars@gmail.com>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) {
    die();
}

use dokuwiki\plugin\structcondstyle\meta\Operator;
use dokuwiki\plugin\structcondstyle\meta\NumericOperator;

class action_plugin_structcondstyle extends DokuWiki_Action_Plugin
{
    protected $search_configs = [];
    protected $ops = [];


    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     *
     * @return void
     */
    public function register(Doku_Event_Handler $controller)
    {
        // Define functions that are used by multiple operators
        $not_func = function($lhs, $rhs){return $lhs !== $rhs;};
        $in_func = function($lhs, $rhs){
            // Check if $lhs is array or string
            if(is_array($lhs)){
                return in_array($rhs,$lhs);
            }else if(is_string($lhs)){
                return strpos($lhs,$rhs) !== false;
            }else return false;
            };



        $this->ops = [  "="         => new Operator("=", function($lhs, $rhs){return $lhs == $rhs;}),
                        "!="        => new Operator("!=", $not_func),
                        "not"       => new Operator("not", $not_func),
                        "<"         => new NumericOperator("<", function($lhs, $rhs){return $lhs < $rhs;}),
                        "<="        => new NumericOperator("<=", function($lhs, $rhs){return $lhs <= $rhs;}),
                        ">"         => new NumericOperator(">", function($lhs, $rhs){return $lhs > $rhs;}),
                        ">="        => new NumericOperator(">=", function($lhs, $rhs){return $lhs >= $rhs;}),
                        "contains"  => new Operator("contains",$in_func),
                        "in"  => new Operator("in",$in_func)
                    ];


        $controller->register_hook('PLUGIN_STRUCT_CONFIGPARSER_UNKNOWNKEY', 'BEFORE', $this, 'handle_plugin_struct_configparser_unknownkey');        
        $controller->register_hook('PLUGIN_STRUCT_AGGREGATIONTABLE_RENDERRESULTROW', 'BEFORE', $this, 'handle_plugin_struct_aggregationtable_renderresultrow_before');        
        $controller->register_hook('PLUGIN_STRUCT_AGGREGATIONTABLE_RENDERRESULTROW', 'AFTER', $this, 'handle_plugin_struct_aggregationtable_renderresultrow_after');
   
    }

    /**
     * [Custom event handler which performs action]
     *
     * Called for event:
     *
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     *
     * @return void
     */
    public function handle_plugin_struct_configparser_unknownkey(Doku_Event $event, $param)
    {
        // Retrieve the key and data and value passed for this agregation line
        $data = $event->data;
        $key = $data['key'];
        $val = trim($data['val']);

        // If the key is not associated with this plugin, return instantly
        if ($key != 'condstyle') return;

        // Else prevent errors and default handling to inject custom code into struct
        $event->preventDefault();
        $event->stopPropagation();

        // Try to parse value into ternary statement and show error message if it does not work out
        if(!preg_match('/\s*[a-zA-z]+\s*.+\s*[a-zA-z0-9]+\s*\?\s*".+"\s*:\s*.+"/',$val)){
            msg("condstyle: $val is not correct", -1);
            return;
        }

        // split value
        $condition = preg_split("/\s*\?\s*/",$val,2)[0];
        $styles = preg_split("/\s*\?\s*/",$val,2)[1];
        $style_true = trim(preg_split('/"\s*:\s*"/',$styles)[0],'"');
        $style_false = trim(preg_split('/"\s*:\s*"/',$styles)[1],'"');

        // Parse operator
        $operator = NULL;
        
        foreach ($this->ops as $k => $op) {
            $op_reg = $op->getReg();
            if(preg_match("/\s*[a-zA-z]+\s*$op_reg\s*[a-zA-z0-9]+\s*/",$condition)){
                $operator = $op_reg;
            }
        }

        if(!$operator){
            msg("condstyle: unknown operator ($val)", -1);
        }
        
        // parse column and argument
        $column = trim(preg_split("/\s*$operator\s*/",$condition)[0]);
        $argument = trim(preg_split("/\s*$operator\s*/",$condition)[1]);

        // package parsed command into data
        $config = array("operator"    => $operator,
                        "column"      => $column,
                        "argument"    => $argument,
                        "style_true"  => $style_true,
                        "style_false" => $style_false
                        );

        // Check if condstyle is already existing in data
        if(!isset($data['config'][$key])){
            $data['config'][$key] = [];
        }

        // Add command to data
        $data['config'][$key][] = $config; 
    }

     /**
     * [Custom event handler which performs action]
     *
     * Called for event:
     *
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     *
     * @return void
     */
    public function handle_plugin_struct_aggregationtable_renderresultrow_before(Doku_Event $event, $param)
    {
        // Retrieve mode, renderer and data from this event
        $mode = $event->data['mode'];
        $renderer = $event->data['renderer'];
        $data = $event->data['data'];

        // Check if mode is xhtml otherwise return
        if ($mode != 'xhtml') return;
                

        // get all styles
        $condstyles = $data['condstyle'];
        if(!isset($condstyles)) return;

        // loop over each style
        foreach ($condstyles as $stylenum => $style) {
            
            // Unpack condition and styles
            $operator = NULL;
            $column = NULL;
            $argument = NULL;
            extract($style);

            // Check if valid data was send, otherwise move to next style
            if(!$operator) continue;

            // Query struct database to get full schema info (in case the column used for condition is not displayed)
            /** @var SearchConfig $searchConfig */
            $searchConfig = $event->data['searchConfig'];
            $searchConfig_hash = spl_object_hash($searchConfig) . $stylenum;

            if (!isset($this->search_configs[$searchConfig_hash])) {
                // Add new Entry for this search configuration
                $this->search_configs[$searchConfig_hash] = [];

                // Retrieve Column
                $cond_column = $searchConfig->findColumn($column);
                //dbg(var_dump($cond_column));

                // Add all columns to be sure that all information was retrieved and execute query
                $searchConfig->addColumn('*');
                $result = $searchConfig->execute();

                // Check for each row if the condition matches and add store that information for later use
                foreach ($result as $rownum => $row) {
                    /** @var Value $value */
                    foreach ($row as $colnum => $value) {
                        if ($value->getColumn() === $cond_column) {
                            $row_val = $value->getRawValue();
                            // check condition
                            $cond_applies = $this->ops[$operator]->evaluate($row_val,$argument);

                            // store condition to inject style later
                            $this->search_configs[$searchConfig_hash][$rownum] = $cond_applies;

                            break;
                        }
                    }
                }

            }
        
        }// END style loop

        // save row start position
        $event->data['rowstart']= mb_strlen($renderer->doc);
        
    }

     /**
     * [Custom event handler which performs action]
     *
     * Called for event:
     *
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     *
     * @return void
     */
    public function handle_plugin_struct_aggregationtable_renderresultrow_after(Doku_Event $event, $param)
    {
        // Retrieve and check mode
        $mode = $event->data['mode'];
        if ($mode != 'xhtml') return;

        // Retrieve renderer
        $renderer = $event->data['renderer'];

        // Retrieve searchconfig and data
        /** @var SearchConfig $searchConfig */
        $searchConfig = $event->data['searchConfig'];
        $data = $event->data['data'];
        $rownum  = $event->data['rownum'];
        $rowstart = $event->data['rowstart'];

        // get all styles
        $condstyles = $data['condstyle'];
        if(!isset($condstyles)) return;

        // String to store all styles
        $style_tag = "";

        // loop over each style
        foreach ($condstyles as $stylenum => $style) {
            // Unpack condition and styles
            $style_true = NULL;
            $style_false = NULL;
            extract($style);

            // Check if proper info is available
            if (!isset($style_true)) continue;
            if (!isset($style_false)) continue;
            if (!$rowstart) continue;
            

            // Lookup the style condition
            $searchConfig_hash = spl_object_hash($searchConfig) . $stylenum;
            $cond_applies = $this->search_configs[$searchConfig_hash][$rownum];
            if (!isset($cond_applies)) continue;

            // set the style for this column based on condition
            $style_tag .= $cond_applies ? $style_true : $style_false;
            
        } // END style loop

        
        // split doc to inject styling
        $rest = mb_substr($renderer->doc, 0,  $rowstart);
        $row = mb_substr($renderer->doc, $rowstart);
        $row = ltrim($row);
        //check if we processing row
        if (mb_substr($row, 0, 3) != '<tr') return;

        $tr_tag = mb_substr($row, 0, 3);
        $tr_rest = mb_substr($row, 3);


        // inject style into document
        if(trim($style_tag) != "")
            $renderer->doc = $rest . $tr_tag . ' style="'.$style_tag.'" ' . $tr_rest;


    }

}


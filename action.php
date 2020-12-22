<?php
/**
 * DokuWiki Plugin conditionalstyle (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Lars Ohnemus <lars.ohnemus@mu-zero.de>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) {
    die();
}

class action_plugin_conditionalstyle extends DokuWiki_Action_Plugin
{
    protected $search_configs = [];

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     *
     * @return void
     */
    public function register(Doku_Event_Handler $controller)
    {
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
        $possible_ops = ["=","<",">","!="];
        foreach ($possible_ops as $k => $op) {
            if(preg_match("/\s*[a-zA-z]+\s*$op\s*[a-zA-z0-9]+\s*/",$condition)){
                $operator = $op;
            }
        }

        if(!$operator){
            msg("condstyle: unknown operator ($val)", -1);
        }
        
        // parse column and argument
        $column = trim(preg_split("/\s*$operator\s*/",$condition)[0]);
        $argument = trim(preg_split("/\s*$operator\s*/",$condition)[1]);

        // package parsed command into data
        $data['config'][$key] = array("operator"    => $operator,
                                      "column"      => $column,
                                      "argument"    => $argument,
                                      "style_true"  => $style_true,
                                      "style_false" => $style_false
                                     );
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
                
        // Unpack condition and styles
        $operator = NULL;
        $column = NULL;
        $argument = NULL;
        $style_true = NULL;
        $style_false = NULL;
        extract($data['condstyle']);

        // Check if valid data was send, otherwise return
        if(!$operator) return;


        // Query struct database to get full schema info (in case the column used for condition is not displayed)
        /** @var SearchConfig $searchConfig */
        $searchConfig = $event->data['searchConfig'];
        $searchConfig_hash = spl_object_hash($searchConfig);
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
                        $cond_applies = false;
                        switch ($operator) {
                            case '=':
                                $cond_applies = $row_val == $argument;
                                break;
                            case '<':
                                //TODO not validated
                                $num_arg = floatval($argument);
                                $num_row_val = floatval($argument);
                                $cond_applies = $num_row_val < $num_arg;
                                break;
                            default:
                                msg("condstyle: unknown operator ($operator)", -1);
                                break;
                        }

                        // store condition to inject style later
                        $this->search_configs[$searchConfig_hash][$rownum] = $cond_applies;

                        break;
                    }
                }
            }

        }

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
                
        // Unpack condition and styles
        $operator = NULL;
        $column = NULL;
        $argument = NULL;
        $style_true = NULL;
        $style_false = NULL;
        extract($data['condstyle']);

        // Check if proper info is available
        if (!isset($style_true)) return;
        if (!isset($style_false)) return;
        if (!$rowstart) return;
        

        // Lookup the style condition
        $searchConfig_hash = spl_object_hash($searchConfig);
        $cond_applies = $this->search_configs[$searchConfig_hash][$rownum];
        if (!isset($cond_applies)) return;

        // set the style for this column based on condition
        $style = $cond_applies ? $style_true : $style_false;
        
        // split doc to inject styling
        $rest = mb_substr($renderer->doc, 0,  $rowstart);
        $row = mb_substr($renderer->doc, $rowstart);
        $row = ltrim($row);
        //check if we processing row
        if (mb_substr($row, 0, 3) != '<tr') return;

        $tr_tag = mb_substr($row, 0, 3);
        $tr_rest = mb_substr($row, 3);

        if(trim($style) != "")
            $renderer->doc = $rest . $tr_tag . ' style="'.$style.'" ' . $tr_rest;


    }

}


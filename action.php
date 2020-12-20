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
        $controller->register_hook(' PLUGIN_STRUCT_AGGREGATIONTABLE_RENDERRESULTROW', 'BEFORE', $this, 'handle_plugin_struct_aggregationtable_renderresultrow_before');        
        $controller->register_hook(' PLUGIN_STRUCT_AGGREGATIONTABLE_RENDERRESULTROW', 'AFTER', $this, 'handle_plugin_struct_aggregationtable_renderresultrow_after');
   
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
        if(!preg_match('/\s*[a-zA-z]+\s*(=|<|>|!=)\s*[a-zA-z0-9]+\s*\?\s*".+"\s*:\s*.+"/',$val)){
            msg("condstyle: $val is not correct", -1);
            return;
        }

        // split value
        $condition = preg_split("/\s*\?\s*/",$val,2)[0];
        $styles = preg_split("/\s*\?\s*/",$val,2)[1];
        $style_true = trim(preg_split('/"\s*:\s*"/',$styles)[0],'"');
        $style_false = trim(preg_split('/"\s*:\s*"/',$styles)[1],'"');
        
        $data['config'][$key] = [$condition,$style_true,$style_false];
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
    }

}


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
        $controller->register_hook(' PLUGIN_STRUCT_AGGREGATIONTABLE_RENDERRESULTROW', 'BEFORE', $this, 'handle_plugin_struct_aggregationtable_renderresultrow');        
        $controller->register_hook(' PLUGIN_STRUCT_AGGREGATIONTABLE_RENDERRESULTROW', 'AFTER', $this, 'handle_plugin_struct_aggregationtable_renderresultrow');
   
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
        $data = $event->data;
        $key = $data['key'];
        if ($key != 'rowcolor') return;

        $event->preventDefault();
        $event->stopPropagation();

        $val = trim($data['val']);
        $data['config'][$key] = $val;
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
    public function handle_plugin_struct_aggregationtable_renderresultrow(Doku_Event $event, $param)
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
    public function handle_plugin_struct_aggregationtable_renderresultrow(Doku_Event $event, $param)
    {
    }

}


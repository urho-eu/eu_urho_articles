<?php
/**
 * @package eu_urho_articles
 */
class eu_urho_articles_injector
{
    public function inject_process(midgardmvc_core_request $request)
    {
        static $connected = false;

        if ( ! $connected)
        {
            // Subscribe to content changed signals from Midgard
            midgard_object_class::connect_default('fi_openkeidas_articles_article', 'action-create', array('fi_openkeidas_articles_injector', 'check_node'), array($request));
            $connected = true;
        }

        $component = $request->get_node()->get_component();
    }

    public function inject_template(midgardmvc_core_request $request)
    {
        // We inject the template to provide Open Keidas styling
        $request->add_component_to_chain(midgardmvc_core::get_instance()->component->get('eu_urho_articles'), true);
    }
}
?>

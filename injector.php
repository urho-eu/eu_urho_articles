<?php
/**
 * @package eu_urho_articles
 */
class eu_urho_articles_injector
{
    /**
     * @todo: docs
     */
    public function inject_process(midgardmvc_core_request $request)
    {
        static $connected = false;

        if ( ! $connected)
        {
            // Subscribe to content changed signals from Midgard
            midgard_object_class::connect_default('eu_urho_articles_article', 'action-create', array('eu_urho_articles_injector', 'check_node'), array($request));
            $connected = true;
        }

        $component = $request->get_node()->get_component();
    }

    /**
     * @todo: docs
     */
    public function inject_template(midgardmvc_core_request $request)
    {
        // We inject the template to provide Open Keidas styling
        $request->add_component_to_chain(midgardmvc_core::get_instance()->component->get('eu_urho_articles'), true);
    }

    /**
     * Sets the article's node by checking the request and the current node
     *
     */
    public static function check_node(eu_urho_articles_article $article, $params)
    {
        if ($article->node)
        {
            return;
        }

        $request = midgardmvc_core::get_instance()->context->get_request();
        $node = $request->get_node();
        if (!$node)
        {
          return;
        }

        $node_object = $node->get_object();
        if (!$node_object instanceof midgardmvc_core_node)
        {
          return;
        }

        $article->node = $node_object->id;
        $article->lang = midgardmvc_core::get_instance()->i18n->get_language();
    }
}
?>

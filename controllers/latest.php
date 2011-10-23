<?php
class eu_urho_articles_controllers_latest
{
    var $mvc = null;
    var $request = null;

    /**
     * @todo: docs
     */
    public function __construct(midgardmvc_core_request $request)
    {
        $this->mvc = midgardmvc_core::get_instance();
        $this->request = $request;
    }

    /**
     * Generates a short abstract from the article
     */
    public function generate_abstract($string, $maxlength)
    {
        $newlinize_tags = array('<br />', '</p>', '</div>', '</h1>', '</h2>', '</h3>');
        foreach ($newlinize_tags as $tag)
        {
            $string = str_replace($tag, "\n", $string);
        }
        $string = strip_tags($string);
        if (mb_strlen($string) <= $maxlength)
        {
            return $string;
        }

        $buffer = $maxlength * 0.1;
        $string = substr($string, 0, $maxlength + $buffer);

        $last_period = mb_strrpos($string, '.');
        if (   $last_period !== false
            && $last_period > ($maxlength * 0.8))
        {
            // Found a period in the last 20% of string, go with it.
            return mb_substr($string, 0, $last_period + 1);
        }

        $last_space = mb_strrpos($string, ' ');
        return mb_substr($string, 0, $last_space);
    }

    /**
     * @todo: docs
     */
    public function get_items(array $args)
    {
        $node = $this->request->get_node()->get_object();

        $node->rdfmapper = new midgardmvc_ui_create_rdfmapper($node);
        $this->data['node'] = $node;

        if (!isset($args['page']))
        {
            $args['page'] = 0;
        }
        elseif ($args['page'] == 0)
        {
            $this->mvc->head->relocate($this->mvc->dispatcher->generate_url('index', array(), $this->request));
        }

        $items_per_page = $this->mvc->configuration->index_items;

        if (isset($args['limit']))
        {
            $items_per_page = $args['limit'];
        }
        $offset = (int) $items_per_page * $args['page'];

        $qs = $this->prepare_qs($node, $items_per_page, $offset);

        if ($args['page'] > 0)
        {
            if ($args['page'] == 1)
            {
                $this->data['previous_page'] = $this->mvc->dispatcher->generate_url('index', array(), $this->request);
            }
            else
            {
                $this->data['previous_page'] = $this->mvc->dispatcher->generate_url('index_page', array('page' => $args['page'] - 1), $this->request);
            }
        }

        $next_qs = $this->prepare_qs($node, $items_per_page, $offset + $items_per_page);
        $next_qs->execute();
        $next_items = $next_qs->list_objects();

        if (count($next_items) > 0)
        {
            $this->data['next_page'] = $this->mvc->dispatcher->generate_url('index_page', array('page' => $args['page'] + 1), $this->request);
        }

        $qs->execute();
        $items = $qs->list_objects();

        if (   $args['page'] > 0
            && empty($items))
        {
            throw new midgardmvc_exception_notfound("Page {$args['page']} not found)");
        }

        $this->data['more'] = false;
        $this->data['lead'] = new midgardmvc_ui_create_container();
        $this->data['items'] = new midgardmvc_ui_create_container();

        foreach ($items as $key => $item)
        {
            $item->abstract = $this->generate_abstract($item->content, 200);

            if ($item->node == $node->id)
            {
                // Local news item
                $item->url = $this->mvc->dispatcher->generate_url('item_read', array('item' => $item->guid), $this->request);
            }
            else
            {
                $subnode = $this->get_node($item->node);
                if ($subnode->get_component() != 'eu_urho_articles')
                {
                    continue;
                }
                $item->url = $this->mvc->dispatcher->generate_url('item_read', array('item' => $item->guid), $subnode->get_path());
            }

            if ($key == 0)
            {
                $item->urlpattern = $this->data['lead']->urlpattern;
                $this->data['lead']->attach($item);
            }
            else
            {
                $this->data['items']->attach($item);
                $this->data['more'] = true;
            }
        }

        if ($this->request->get_node()->get_parent_node() != $this->mvc->hierarchy->get_root_node())
        {
            $this->data['subnodes'] = $this->request->get_node()->get_child_nodes();
        }

        // Read container type from config to know whether items can be created to this node
        $this->data['container_type'] = $this->mvc->configuration->index_container;

        if ($this->data['container_type'] == 'http://purl.org/dc/dcmitype/Collection')
        {
            // Define placeholder to be used with UI on empty containers
            $dummy = new eu_urho_articles_article();
            $dummy->url = '#';
            $this->data['items']->set_placeholder($dummy);
            $this->data['items']->set_urlpattern($this->mvc->dispatcher->generate_url('item_read', array('item' => 'GUID'), $this->request));
        }

        $this->mvc->head->set_title($this->data['node']->title);
    }

    /**
     * Returns a QuerySelect object
     */
    private function prepare_qs(midgardmvc_core_node $node, $limit, $offset = 0)
    {
        $storage = new midgard_query_storage('eu_urho_articles_article');

        $qc = new midgard_query_constraint_group('AND');
        $qc->add_constraint(new midgard_query_constraint(
            new midgard_query_property('node'),
            '=',
            new midgard_query_value($node->id)
        ));
        $qc->add_constraint(new midgard_query_constraint(
            new midgard_query_property('lang', $storage),
            '=',
            new midgard_query_value($this->mvc->i18n->get_language())
        ));

        if ( ! midgardmvc_ui_create_injector::can_use() )
        {
            // Regular user, hide unapproved articles
            // TODO: This check should be moved to authentication service when QB has signals
            $qc->add_constraint(new midgard_query_constraint(
                new midgard_query_property('metadata.isapproved'),
                '=',
                new midgard_query_value(true)
            ));
        }

        $qs = new midgard_query_select($storage);

        $qs->add_order(new midgard_query_property('metadata.created'), SORT_DESC);
        $qs->set_constraint($qc);
        $qs->set_offset($offset);
        $qs->set_limit($limit);

        return $qs;
    }
}

w<?php

require_once(ROOT_DIR . 'Domain/ResourceGroup.php');

class FakeResourceGroupTree extends ResourceGroupTree
{
    /**
     * @param $resources ResourceDto[]
     */
    public function WithAllResources($resources)
    {
        $this->resources = $resources;
    }
}

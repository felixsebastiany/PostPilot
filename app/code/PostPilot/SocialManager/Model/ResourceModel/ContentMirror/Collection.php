<?php
declare(strict_types=1);

namespace PostPilot\SocialManager\Model\ResourceModel\ContentMirror;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use PostPilot\SocialManager\Model\ContentMirror;
use PostPilot\SocialManager\Model\ResourceModel\ContentMirror as ContentMirrorResource;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(ContentMirror::class, ContentMirrorResource::class);
    }
}

<?php
declare(strict_types=1);

namespace PostPilot\SocialManager\Model\ResourceModel\SocialConnection;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use PostPilot\SocialManager\Model\SocialConnection;
use PostPilot\SocialManager\Model\ResourceModel\SocialConnection as SocialConnectionResource;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(SocialConnection::class, SocialConnectionResource::class);
    }
}

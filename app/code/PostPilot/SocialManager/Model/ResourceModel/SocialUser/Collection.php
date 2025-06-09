<?php
declare(strict_types=1);

namespace PostPilot\SocialManager\Model\ResourceModel\SocialUser;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use PostPilot\SocialManager\Model\SocialUser;
use PostPilot\SocialManager\Model\ResourceModel\SocialUser as SocialUserResource;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(SocialUser::class, SocialUserResource::class);
    }
}

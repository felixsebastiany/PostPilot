<?php
declare(strict_types=1);

namespace PostPilot\SocialManager\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class ContentMirror extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('postpilot_content_mirror', 'id');
    }
}

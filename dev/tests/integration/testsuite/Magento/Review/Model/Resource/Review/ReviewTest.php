<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Review\Model\Resource\Review;

use Magento\Framework\App\Resource;

/**
 * Class ReviewTest
 */
class ReviewTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var \Magento\Review\Model\Resource\Review
     */
    protected $reviewResource;

    /**
     * @var \Magento\Review\Model\Resource\Review\Collection
     */
    protected $reviewCollection;

    /**
     * @var \Magento\Framework\App\Resource
     */
    protected $resource;

    /**
     * @var \Magento\Framework\DB\Adapter\AdapterInterface
     */
    protected $connection;

    /**
     * @return void
     */
    protected function setUp()
    {
        $this->objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();
        $this->resource = $this->objectManager->get('Magento\Framework\App\Resource');
        $this->connection = $this->resource->getConnection();
        $this->reviewCollection = $this->objectManager->create('Magento\Review\Model\Resource\Review\Collection');
        $this->reviewResource =  $this->objectManager->create('Magento\Review\Model\Resource\Review');
    }

    /**
     * @magentoDataFixture Magento/Review/_files/customer_review_with_rating.php
     */
    public function testAggregate()
    {
        $rating = $this->reviewCollection->getFirstItem();
        $this->reviewResource->aggregate($rating);

        $select = $this->connection->select()->from($this->resource->getTableName('review_entity_summary'));
        $result = $this->connection->fetchRow($select);

        $this->assertEquals(1, $result['reviews_count']);
        $this->assertEquals(40, $result['rating_summary']);
    }
}

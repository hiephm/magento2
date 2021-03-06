<?php
/**
 *
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Quote\Model\Quote\Item;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\LocalizedException;

class Repository implements \Magento\Quote\Api\CartItemRepositoryInterface
{
    /**
     * Quote repository.
     *
     * @var \Magento\Quote\Model\QuoteRepository
     */
    protected $quoteRepository;

    /**
     * Product repository.
     *
     * @var \Magento\Catalog\Api\ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var \Magento\Quote\Api\Data\CartItemInterfaceFactory
     */
    protected $itemDataFactory;

    /**
     * @var \Magento\Quote\Model\Quote\Item\CartItemProcessorInterface[]
     */
    protected $cartItemProcessors;

    /**
     * @param \Magento\Quote\Model\QuoteRepository $quoteRepository
     * @param \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
     * @param \Magento\Quote\Api\Data\CartItemInterfaceFactory $itemDataFactory
     * @param \Magento\Quote\Model\Quote\Item\CartItemProcessorInterface[] $cartItemProcessors
     */
    public function __construct(
        \Magento\Quote\Model\QuoteRepository $quoteRepository,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Quote\Api\Data\CartItemInterfaceFactory $itemDataFactory,
        array $cartItemProcessors = []
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->productRepository = $productRepository;
        $this->itemDataFactory = $itemDataFactory;
        $this->cartItemProcessors = $cartItemProcessors;
    }

    /**
     * {@inheritdoc}
     */
    public function getList($cartId)
    {
        $output = [];
        /** @var  \Magento\Quote\Model\Quote $quote */
        $quote = $this->quoteRepository->getActive($cartId);

        /** @var  \Magento\Quote\Model\Quote\Item  $item */
        foreach ($quote->getAllItems() as $item) {
            if (!$item->isDeleted() && !$item->getParentItemId()) {
                $output[] = $this->addProductOptions($item->getProductType(), $item);
            }
        }
        return $output;
    }

    /**
     * {@inheritdoc}
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function save(\Magento\Quote\Api\Data\CartItemInterface $cartItem)
    {
        $qty = $cartItem->getQty();
        if (!is_numeric($qty) || $qty <= 0) {
            throw InputException::invalidFieldValue('qty', $qty);
        }
        $cartId = $cartItem->getQuoteId();

        /** @var \Magento\Quote\Model\Quote $quote */
        $quote = $this->quoteRepository->getActive($cartId);

        $itemId = $cartItem->getItemId();
        try {
            /** update item */
            if (isset($itemId)) {
                $item = $quote->getItemById($itemId);
                if (!$item) {
                    throw new NoSuchEntityException(
                        __('Cart %1 doesn\'t contain item  %2', $cartId, $itemId)
                    );
                }
                $productType = $item->getProduct()->getTypeId();
                $buyRequestData = $this->getBuyRequest($productType, $cartItem);
                if (is_object($buyRequestData)) {
                    /** update item product options */
                    /** @var  \Magento\Quote\Model\Quote\Item $cartItem */
                    $cartItem = $quote->updateItem($itemId, $buyRequestData);
                } else {
                    /** update item qty */
                    $item->setData('qty', $qty);
                }
            } else {
                /** add item to shopping cart */
                $product = $this->productRepository->get($cartItem->getSku());
                $productType = $product->getTypeId();
                /** @var  \Magento\Quote\Model\Quote\Item|string $cartItem */
                $cartItem = $quote->addProduct($product, $this->getBuyRequest($productType, $cartItem));
                if (is_string($cartItem)) {
                    throw new \Magento\Framework\Exception\LocalizedException(__($cartItem));
                }
            }
            $this->quoteRepository->save($quote->collectTotals());
        } catch (\Exception $e) {
            if ($e instanceof NoSuchEntityException || $e instanceof LocalizedException) {
                throw $e;
            }
            throw new CouldNotSaveException(__('Could not save quote'));
        }
        $itemId = $cartItem->getId();
        foreach ($quote->getAllItems() as $quoteItem) {
            if ($itemId == $quoteItem->getId()) {
                return $this->addProductOptions($productType, $quoteItem);
            }
        }
        throw new CouldNotSaveException(__('Could not save quote'));
    }

    /**
     * @param string $productType
     * @param \Magento\Quote\Api\Data\CartItemInterface $cartItem
     * @return \Magento\Framework\DataObject|float
     */
    protected function getBuyRequest(
        $productType,
        \Magento\Quote\Api\Data\CartItemInterface $cartItem
    ) {
        $params = (isset($this->cartItemProcessors[$productType]))
            ? $this->cartItemProcessors[$productType]->convertToBuyRequest($cartItem)
            : null;
        return ($params === null) ? $cartItem->getQty() : $params->setQty($cartItem->getQty());
    }

    /**
     * @param string $productType
     * @param \Magento\Quote\Api\Data\CartItemInterface $cartItem
     * @return  \Magento\Quote\Api\Data\CartItemInterface
     */
    protected function addProductOptions(
        $productType,
        \Magento\Quote\Api\Data\CartItemInterface $cartItem
    ) {
        $cartItem = (isset($this->cartItemProcessors[$productType]))
            ? $this->cartItemProcessors[$productType]->processProductOptions($cartItem)
            : $cartItem;
        return $cartItem;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteById($cartId, $itemId)
    {
        /** @var \Magento\Quote\Model\Quote $quote */
        $quote = $this->quoteRepository->getActive($cartId);
        $quoteItem = $quote->getItemById($itemId);
        if (!$quoteItem) {
            throw new NoSuchEntityException(
                __('Cart %1 doesn\'t contain item  %2', $cartId, $itemId)
            );
        }
        try {
            $quote->removeItem($itemId);
            $this->quoteRepository->save($quote->collectTotals());
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('Could not remove item from quote'));
        }

        return true;
    }
}

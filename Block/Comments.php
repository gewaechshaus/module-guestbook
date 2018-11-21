<?php

namespace Encomage\GuestBook\Block;

use Magento\Framework\View\Element\Template;
use Encomage\GuestBook\Api\GuestBookRepositoryInterface;
use Encomage\GuestBook\Api\Data\GuestBookInterface;
use Encomage\GuestBook\Helper\Config;
use Magento\Customer\Model\Session;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;

/**
 * Class Comments
 *
 * @package Encomage\GuestBook\Block
 */
class Comments extends Template
{
    /**
     * @var GuestBookRepositoryInterface
     */
    protected $guestBookRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @var SortOrderBuilder
     */
    protected $sortOrderBuilder;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var Session
     */
    protected $customerSession;

    /**
     * @var null
     */
    protected $loadedCollection = null;

    /**
     * Comments constructor.
     *
     * @param Template\Context $context
     * @param GuestBookRepositoryInterface $guestBookRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param SortOrderBuilder $sortOrderBuilder
     * @param Config $config
     * @param Session $session
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        GuestBookRepositoryInterface $guestBookRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        SortOrderBuilder $sortOrderBuilder,
        Config $config,
        Session $session,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->guestBookRepository = $guestBookRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->sortOrderBuilder = $sortOrderBuilder;
        $this->config = $config;
        $this->customerSession = $session;
    }

    /**
     * @return mixed|null
     */
    public function getLoadedList()
    {
        if ($this->loadedCollection === null) {
            $this->loadedCollection = $this->_getGuestBookQuestionCollection();
        }

        return $this->loadedCollection;
    }

    /**
     * @param $messageId
     * @return mixed
     */
    public function hasAnswer($messageId)
    {
        if ($this->customerSession->isLoggedIn()) {
            $this->searchCriteriaBuilder->addFilter(
                'customSearchFilter',
                [
                    GuestBookInterface::MESSAGE_ID => $messageId,
                    GuestBookInterface::SESSION_ID => $this->customerSession->getCustomer()->getRpToken(),
                ]
            );
        } else {
            $this->searchCriteriaBuilder->addFilter('customSearchFilter', $messageId);
        }
        $searchCriteria = $this->searchCriteriaBuilder->create();
        /** @var \Encomage\GuestBook\Api\GuestBookRepositoryInterface */
        $collection = $this->guestBookRepository->getList($searchCriteria);

        return $collection->getItems();
    }

    /**
     * @return bool
     */
    public function isAllowed()
    {
        if ($this->config->isAllowGuests() || $this->customerSession->isLoggedIn()) {
            return true;
        }

        return false;
    }

    /**
     * @return Config
     */
    public function getConfigHelper()
    {
        return $this->config;
    }

    /**
     * @return string
     */
    public function getAnswerUrl()
    {
        return $this->getUrl('guestbook/ajax/answer');
    }

    /**
     * @return string
     */
    public function getListingUrl()
    {
        return $this->getUrl('guestbook/ajax/listing');
    }

    /**
     * @return string
     */
    public function getPagerHtml()
    {
        return $this->getChildHtml('pager');
    }

    /**
     * @return bool|\Magento\Framework\View\Element\BlockInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getFormBlock()
    {
        return $this->getLayout()->getBlock('guest.book.form');
    }

    /**
     * @param string $pagerName
     * @return $this
     */
    public function setPagerName($pagerName)
    {
        $this->setData('pager_name', $pagerName);
        return $this;
    }

    /**
     * @return bool|string
     */
    public function getPagerName()
    {
        return ($this->hasData('pager_name')) ? (string)$this->getData('pager_name') : false;
    }

    /**
     * @return mixed
     */
    protected function _getGuestBookQuestionCollection()
    {
        $this->sortOrderBuilder->setField(GuestBookInterface::MESSAGE_ID);
        $direction = $this->config->getSortDirection();
        $sortOrder = $this->sortOrderBuilder->setDirection($direction)->create();
        if ($this->customerSession->isLoggedIn()) {
            $this->searchCriteriaBuilder->addFilter(
                'statusCustomFilter',
                $this->customerSession->getCustomer()->getRpToken()
            );
        } else {
            $this->searchCriteriaBuilder->addFilter('statusCustomFilter', null);
        }
        $this->searchCriteriaBuilder->addFilter('onlyQuestions', null);
        $this->searchCriteriaBuilder->addSortOrder($sortOrder);
        $searchCriteria = $this->searchCriteriaBuilder->create();
        /** @var \Encomage\GuestBook\Api\GuestBookRepositoryInterface */
        $collection = $this->guestBookRepository->getList($searchCriteria);

        return $collection;
    }

    /**
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();
        if ($this->getPagerName()) {
            $list = $this->getLoadedList();
            if ($list) {
                /** @var $pager \Magento\Theme\Block\Html\Pager */
                $pager = $this->getLayout()
                    ->createBlock('Magento\Theme\Block\Html\Pager', $this->getPagerName())
                    ->setTemplate('Magento_Theme::html/pager.phtml')
                    ->setLimit($this->config->getRecordsPerPage())
                    ->setShowPerPage(true)
                    ->setCollection($list->getCollection())
                    ->setAvailableLimit([])
                    ->setCurrentPage(1);
                $this->setChild('pager', $pager);
            }
        }
        
        return $this;
    }
}
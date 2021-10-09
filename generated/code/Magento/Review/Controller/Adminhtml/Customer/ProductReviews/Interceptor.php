<?php
namespace Magento\Review\Controller\Adminhtml\Customer\ProductReviews;

/**
 * Interceptor class for @see \Magento\Review\Controller\Adminhtml\Customer\ProductReviews
 */
class Interceptor extends \Magento\Review\Controller\Adminhtml\Customer\ProductReviews implements \Magento\Framework\Interception\InterceptorInterface
{
    use \Magento\Framework\Interception\Interceptor;

    public function __construct(\Magento\Backend\App\Action\Context $context, \Magento\Customer\Model\CustomerIdProvider $customerIdProvider)
    {
        $this->___init();
        parent::__construct($context, $customerIdProvider);
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        $pluginInfo = $this->pluginList->getNext($this->subjectType, 'execute');
        return $pluginInfo ? $this->___callPlugins('execute', func_get_args(), $pluginInfo) : parent::execute();
    }

    /**
     * {@inheritdoc}
     */
    public function dispatch(\Magento\Framework\App\RequestInterface $request)
    {
        $pluginInfo = $this->pluginList->getNext($this->subjectType, 'dispatch');
        return $pluginInfo ? $this->___callPlugins('dispatch', func_get_args(), $pluginInfo) : parent::dispatch($request);
    }
}
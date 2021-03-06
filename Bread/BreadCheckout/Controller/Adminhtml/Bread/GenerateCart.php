<?php

namespace Bread\BreadCheckout\Controller\Adminhtml\Bread;

use Braintree\Exception;

class GenerateCart extends \Magento\Backend\App\Action
{
    /** @var \Bread\BreadCheckout\Helper\Quote */
    protected  $helper;
    protected  $cart;
    protected  $config;
    protected  $paymentApiClient;
    protected  $customerHelper;
    protected  $breadMethod;
    protected  $urlHelper;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Bread\BreadCheckout\Helper\Quote $helper,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Bread\BreadCheckout\Model\Payment\Api\Client $paymentApiClient,
        \Bread\BreadCheckout\Helper\Customer $customerHelper,
        \Bread\BreadCheckout\Model\Payment\Method\Bread $breadMethod,
        \Bread\BreadCheckout\Helper\Url $urlHelper
    )
    {
        $this->resultFactory = $context->getResultFactory();
        $this->helper = $helper;
        $this->cart = $cart;
        $this->config = $scopeConfig;
        $this->paymentApiClient = $paymentApiClient;
        $this->customerHelper = $customerHelper;
        $this->breadMethod = $breadMethod;
        $this->urlHelper = $urlHelper;
        parent::__construct($context);
    }

    /**
     * Generate cart from backend
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        try {
            $quote = $this->helper->getSessionQuote();

            $ret = [ "error"       => false,
                     "successRows" => [],
                     "errorRows"   => [],
                     "cartUrl"     => ""
            ];

            if (!$quote || ($quote && $quote->getItemsQty() == 0)) {
                throw new Exception(__("Cart is empty"));
            }

            if ($quote->getPayment()->getMethodInstance()->getCode() != $this->breadMethod->getMethodCode()) {
                throw new Exception(__("In order to checkout with bread you must choose bread as payment option."));
            }

            if (!$this->helper->getShippingOptions()) {
                throw new Exception(__("Please specify a shipping method."));
            }

            $arr = [];

            $arr["expiration"] = date('Y-m-d', strtotime("+" . $this->config->getValue('checkout/cart/delete_quote_after', \Magento\Store\Model\ScopeInterface::SCOPE_STORE) . "days"));
            $arr["options"] = [];
            $arr["options"]["orderRef"] = $quote->getId();

            $arr["options"]["completeUrl"] = $this->urlHelper->getLandingPageURL();
            $arr["options"]["errorUrl"] = $this->urlHelper->getLandingPageURL(true);

            $arr["options"]["shippingOptions"] = [ $this->helper->getShippingOptions() ];

            $arr["options"]["shippingContact"] = $this->helper->getShippingAddressData();
            $arr["options"]["billingContact"] = $this->helper->getBillingAddressData();

            $arr["options"]["items"] = $this->helper->getQuoteItemsData();

            $arr["options"]["discounts"] = $this->helper->getDiscountData() ? $this->helper->getDiscountData() : [];

            $arr["options"]["tax"] = $this->helper->getTaxValue();

            $result = $this->paymentApiClient->submitCartData($arr);

            $ret["successRows"] = [
                __("Cart with Financing was successfully created."),
                __('Following link can be used by your customer to complete purchase.'),
                sprintf('<a href="%1$s">%1$s</a>', $result["url"])
            ];

            $ret["cartUrl"] = $result["url"];

        }catch (\Exception $e){
            $ret["error"] = true;
            $ret["errorRows"][] = __("There was an error in cart creation:");
            $ret["errorRows"][] = $e->getMessage();
        }
        return $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_JSON)->setData($ret);
    }
}
<?php
/**
 * Helps Integration With Catalog
 *
 * @author  Bread   copyright   2016
 * @author  Joel    @Mediotype
 * @author  Miranda @Mediotype
 */
namespace Bread\BreadCheckout\Helper;

class Catalog extends Data
{
    /** @var \Magento\Catalog\Block\Product\View */
    protected $productViewBlock;
    protected $storeManager;

    public function __construct(
        \Magento\Framework\App\Helper\Context $helperContext,
        \Magento\Framework\Model\Context $context,
        \Magento\Catalog\Block\Product\View $productViewBlock,
        \Magento\Framework\App\Request\Http\Proxy $request,
        \Magento\Framework\Encryption\Encryptor $encryptor,
        \Magento\Framework\UrlInterfaceFactory $urlInterfaceFactory,
        \Magento\Catalog\Api\ProductRepositoryInterfaceFactory $productRepositoryFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->productViewBlock = $productViewBlock;
        $this->_productRepositoryFactory = $productRepositoryFactory;
        $this->storeManager = $storeManager;
        parent::__construct($helperContext, $context, $request, $encryptor, $urlInterfaceFactory);
    }

    /**
     * Get html param string for default button size based on configuration
     *
     * @return string
     */
    public function getDefaultButtonSizeHtml()
    {
        if ($this->useDefaultButtonSize()) {
            return 'data-bread-default-size="true"';
        }

        return '';
    }

    /**
     * Get Formatted Product Data Array
     *
     * @param \Magento\Catalog\Model\Product $product
     * @param \Magento\Catalog\Model\Product $baseProduct
     * @param int $qty
     * @param null $lineItemPrice
     * @return array
     */
    public function getProductDataArray(
        \Magento\Catalog\Model\Product $product,
        \Magento\Catalog\Model\Product $baseProduct = null,
        $qty = 1,
        $lineItemPrice = null
    ) {
    
        $theProduct     = ($baseProduct == null) ? $product : $baseProduct;
        $skuString      = $this->getSkuString($product, $theProduct);
        $price          = ($lineItemPrice !== null) ? $lineItemPrice * 100 : ( ( $baseProduct == null ) ?
                $product->getFinalPrice() : $baseProduct->getFinalPrice() ) * 100;

        $productData = [
            'name'      => ( $baseProduct == null ) ? $product->getName() : $baseProduct->getName(),
            'price'     => $price,
            'sku'       => ( $baseProduct == null ) ? $skuString : ($baseProduct['sku'].'///'.$skuString),
            'detailUrl' => ( $baseProduct == null ) ? $product->getProductUrl() : $baseProduct->getProductUrl(),
            'quantity'  => $qty,
        ];

        $imgSrc = $this->getImgSrc($product);
        if ($imgSrc != null) {
            $productData['imageUrl'] = $imgSrc;
        }

        return $productData;
    }

    /**
     * Return Product SKU Or Formatted SKUs for Products With Options
     *
     * @param \Magento\Catalog\Model\Product $product
     * @param \Magento\Catalog\Model\Product $theProduct
     * @return string
     */
    protected function getSkuString(
        \Magento\Catalog\Model\Product $product,
        \Magento\Catalog\Model\Product $theProduct
    ) {
    
        $selectedOptions    = $theProduct->getTypeInstance(true)->getOrderOptions($theProduct);

        if (!array_key_exists('options', $selectedOptions)) {
            return (string) $product->getSku();
        }

        $skuString  = $product->getData('sku');
        foreach ($selectedOptions['options'] as $key => $value) {
            if ($value['option_type'] == 'multiple') {
                $selectedOptionValues = explode(',', $value['option_value']);
            } else {
                $selectedOptionValues = [$value['option_value']];
            }
            foreach ($selectedOptionValues as $selectedOptionValue) {
                $found = false;
                foreach ($theProduct->getOptions() as $option) {
                    if ($found) {
                        break;
                    }
                    if (!empty($option->getValues())) {
                        if ($option->getTitle() == $value['label']) {
                            foreach ($option->getValues() as $optionValue) {
                                if ($selectedOptionValue == $optionValue->getOptionTypeId()) {
                                    $skuString = $skuString . '***' . $optionValue->getSku();
                                    $found = true;
                                    break;
                                }
                            }
                        }
                    } elseif ($value['label'] == $option['title']) {
                        $skuString = $skuString . '***' . $option->getSku() . '===' . $value['option_value'];
                        break;
                    }
                }
            }
        }

        return $skuString;
    }

    /**
     * Get Img Src Value
     *
     * @param   \Magento\Catalog\Model\Product $product
     * @return null|string
     */
    protected function getImgSrc(\Magento\Catalog\Model\Product $product)
    {
        if( $this->isInAdmin() ) {
            $product = $this->_productRepositoryFactory->create()->getById($product->getId());
            $imageUrl = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . 'catalog/product' . $product->getImage();
            return $imageUrl;
        }

        try {
            return (string) $this->productViewBlock->getImage($product, 'product_small_image')->getImageUrl();
        } catch (\Exception $e) {
            return null;
        }
    }
}

<?php
/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 *
 * Glory to Ukraine! Glory to the heroes!
 */

namespace Magefan\ShopifyBlogExport\Block\Adminhtml\Export\Form;

/**
 * Form export form block
 */
class Form extends \Magento\Backend\Block\Widget\Form\Generic
{
    /**
     * @var \Magento\Store\Model\System\Store
     */
    protected $_systemStore;

    /**
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Data\FormFactory $formFactory
     * @param \Magento\Store\Model\System\Store $systemStore
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Data\FormFactory $formFactory,
        \Magento\Store\Model\System\Store $systemStore,
        array $data = []
    ) {
        $this->_systemStore = $systemStore;
        parent::__construct($context, $registry, $formFactory, $data);
    }

    /**
     * Prepare form
     *
     * @return $this
     */
    protected function _prepareForm()
    {
        $form = $this->_formFactory->create(
            ['data' => ['id' => 'edit_form', 'action' => $this->getData('action'), 'method' => 'post']]
        );
        $form->setUseContainer(true);

        $data = $this->_coreRegistry->registry('export_config')->getData();

        $type = $this->getRequest()->getParam('type');

        /*
         * Checking if user have permissions to save information
         */
        if ($this->_authorization->isAllowed('Magefan_ShopifyBlogExport::export')) {
            $isElementDisabled = false;
        } else {
            $isElementDisabled = true;
        }
        $isElementDisabled = false;

        $form->setHtmlIdPrefix('export_');

        $fieldset = $form->addFieldset('base_fieldset', ['legend' => '']);

        $fieldset->addField(
            'type',
            'hidden',
            [
                'name' => 'type',
                'required' => true,
                'disabled' => $isElementDisabled,
            ]
        );

        $fieldset->addField(
            'shopify_import_key',
            'text',
            [
                'name' => 'shopify_import_key',
                'label' => __('Shopify Export Key'),
                'title' => __('Shopify Export Key'),
                'required' => true,
                'disabled' => $isElementDisabled,
                'after_element_html' => '<small>You can find it in your Shopify store admin panel > Apps > Magefan Blog > Configuration > Key..</small>',
            ]
        );

        $fieldset->addField(
            'shopify_domain',
            'text',
            [
                'label' => __('Shopify Store Name'),
                'title' => __('Shopify Store Name'),
                'name' => 'shopify_domain',
                'required' => true,
                'disabled' => $isElementDisabled,
                'after_element_html' => '<small>E.g. mystore.myshopify.com</small>',
            ]
        );

        /**
         * Check is single store mode
        if (!in_array($type, ['aw', 'aw2', 'mageplaza', 'mageplaza1', 'mirasvit'])) {
            if (!$this->_storeManager->isSingleStoreMode()) {
                $field = $fieldset->addField(
                    'store_id',
                    'select',
                    [
                        'name' => 'store_id',
                        'label' => __('Store View'),
                        'title' => __('Store View'),
                        'required' => true,
                        'values' => $this->_systemStore->getStoreValuesForForm(false, true),
                        'disabled' => $isElementDisabled,
                    ]
                );
                $renderer = $this->getLayout()->createBlock(
                    \Magento\Backend\Block\Store\Switcher\Form\Renderer\Fieldset\Element::class
                );
                $field->setRenderer($renderer);
            } else {
                $fieldset->addField(
                    'store_id',
                    'hidden',
                    ['name' => 'store_id', 'value' => $this->_storeManager->getStore(true)->getId()]
                );

                $data['store_id'] = $this->_storeManager->getStore(true)->getId();
            }
        }
        */
        $this->_eventManager->dispatch('magefan_shopifyblogexport_export_' . $type . '_prepare_form', ['form' => $form]);

        $data['type'] = $type;

        $form->setValues($data);

        $this->setForm($form);

        return parent::_prepareForm();
    }
}

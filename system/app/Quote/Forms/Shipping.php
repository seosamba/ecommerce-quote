<?php

class Quote_Forms_Shipping extends Forms_Checkout_Shipping {

	public function init() {
		parent::init();

        $translator =  Zend_Registry::get('Zend_Translate');

        $this->addElement(new Zend_Form_Element_Select(array(
            'name'         => 'mobilecountrycode',
            'label'        => 'Mobile',
            'multiOptions' => Tools_System_Tools::getFullCountryPhoneCodesList(true, array(), true),
            'value'        => Models_Mapper_ShoppingConfig::getInstance()->getConfigParam('country'),
            'style'        => 'width: 41.667%;',
        )));

        $this->addElement(new Zend_Form_Element_Text(array(
            'name'     => 'mobile',
            'label'    => null,
            'value'    => '',
            'style'    => 'width: 58.333%;'
        )));

        $this->getElement('firstname')->setRequired(true)->setAttribs(array('class' => 'quote-required required'));

        $this->addElement(new Zend_Form_Element_Select(array(
            'name'         => 'prefix',
            'id'           => 'prefix',
            'label'        => $translator->translate('Prefix'),
            'value'        => $this->_prefix,
            'multiOptions' => array('' => $translator->translate('Select')) + Tools_System_Tools::getAllowedPrefixesList()
        )));

        $this->addElement(new Zend_Form_Element_Text(array(
            'name'  => 'position',
            'label' => 'Position',
            'rows'  => '3'
        )));

        $this->addDisplayGroups(array(
            'lcol' => array(
                'prefix',
                'firstname',
                'lastname',
                'company',
                'position',
                'email',
                'address1',
                'address2'
            ),
            'rcol' => array(
                'country',
                'city',
                'state',
                'zip'
            ),
            'bottom' => array('shippingInstructions')
        ));

        $this->getDisplayGroup('lcol')
            ->setDecorators(array(
                'FormElements',
                'Fieldset'
            ));

        $this->getDisplayGroup('rcol')
            ->setDecorators(array(
                'FormElements',
                'Fieldset'
            ));

        $this->getDisplayGroup('bottom')
            ->setDecorators(array(
                'FormElements',
                'Fieldset'
            ));

        $this->setElementDecorators(array(
            'ViewHelper',
            'Label',
            array('HtmlTag', array('tag' => 'p'))
        ));

        $this->getElement('phone')->removeDecorator('HtmlTag');
        $this->getElement('phonecountrycode')->removeDecorator('HtmlTag');
        $this->getElement('mobile')->removeDecorator('HtmlTag');
        $this->getElement('mobilecountrycode')->removeDecorator('HtmlTag');

        $this->addDisplayGroup(array(
            'mobilecountrycode',
            'mobile'
        ),'mobilesShippingBlock',array('HtmlTag', array('tag' => 'div')));

        $this->addDisplayGroup(array(
            'phonecountrycode',
            'phone'
        ),'phonesShippingBlock',array('HtmlTag', array('tag' => 'div')));

        $mobilesBlock = $this->getDisplayGroup('mobilesShippingBlock');
        $mobilesBlock->setDecorators(array(
            'FormElements',
            array('HtmlTag',array('tag'=>'p', 'class' => 'mobile-desktop-phone-block'))
        ));

        $phonesBlock = $this->getDisplayGroup('phonesShippingBlock');
        $phonesBlock->setDecorators(array(
            'FormElements',
            array('HtmlTag',array('tag'=>'p', 'class' => 'mobile-desktop-phone-block'))
        ));

	}



}
